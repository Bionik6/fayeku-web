<?php

namespace App\Actions\Auth;

use App\Enums\Auth\LegalForm;
use App\Models\Auth\Company;
use App\Models\Compta\PartnerInvitation;
use App\Models\PME\Client;
use App\Support\PhoneNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Persistance des étapes de l'onboarding PME.
 *
 * Chaque méthode est idempotente : appeler `persistIdentity` deux fois de
 * suite avec les mêmes données produit le même résultat.
 */
class CompleteOnboardingStep
{
    /**
     * @param  array{name: string, legal_form: LegalForm|string, sector: string, ninea?: ?string, rccm?: ?string, address?: ?string, billing_email?: ?string}  $data
     */
    public function persistIdentity(Company $company, array $data, ?UploadedFile $logo = null): void
    {
        $legalForm = $data['legal_form'] instanceof LegalForm
            ? $data['legal_form']->value
            : (string) $data['legal_form'];

        $company->update([
            'name' => trim($data['name']),
            'legal_form' => $legalForm,
            'sector' => $data['sector'],
            'ninea' => $this->emptyToNull($data['ninea'] ?? null),
            'rccm' => $this->emptyToNull($data['rccm'] ?? null),
            'address' => $this->emptyToNull($data['address'] ?? null),
            'email' => $this->emptyToNull($data['billing_email'] ?? null),
        ]);

        if ($logo) {
            $this->persistLogo($company, $logo);
        }
    }

    public function persistSignature(Company $company, string $senderName, ?string $senderRole = null): void
    {
        $company->update([
            'sender_name' => trim($senderName) ?: null,
            'sender_role' => $this->emptyToNull($senderRole),
        ]);
    }

    /**
     * @param  array{name: string, phone?: ?string, phone_country?: ?string, email?: ?string}  $data
     */
    public function createFirstClient(Company $company, array $data): Client
    {
        $phone = null;
        if (filled($data['phone'] ?? null)) {
            $countryCode = $data['phone_country'] ?? $company->country_code ?? 'SN';
            $phone = PhoneNumber::normalize($data['phone'], $countryCode);
        }

        return Client::query()->create([
            'company_id' => $company->id,
            'name' => trim($data['name']),
            'phone' => $phone,
            'email' => $this->emptyToNull($data['email'] ?? null),
        ]);
    }

    public function markCompleted(Company $company): void
    {
        $company->update(['setup_completed_at' => now()]);

        // Backfill du nom de la PME dans les invitations cabinet (auparavant
        // dans CompanySetupController) — utilisé par le dashboard cabinet.
        PartnerInvitation::query()
            ->where('sme_company_id', $company->id)
            ->whereNull('invitee_company_name')
            ->update(['invitee_company_name' => $company->name]);

        session()->forget('invitee_company_name');
    }

    private function persistLogo(Company $company, UploadedFile $logo): void
    {
        $extension = strtolower($logo->getClientOriginalExtension() ?: 'png');
        $path = "company-logos/{$company->id}.{$extension}";

        if ($company->logo_path && $company->logo_path !== $path && Storage::exists($company->logo_path)) {
            Storage::delete($company->logo_path);
        }

        Storage::put($path, file_get_contents($logo->getRealPath()));

        $company->update(['logo_path' => $path]);
    }

    private function emptyToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
