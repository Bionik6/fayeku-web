<?php

namespace App\Http\Controllers\PME;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyLogoController
{
    public function __invoke(): StreamedResponse|Response
    {
        $company = auth()->user()?->smeCompany();

        abort_unless($company && $company->logo_path, 404);
        abort_unless(Storage::exists($company->logo_path), 404);

        $mime = Storage::mimeType($company->logo_path) ?: 'image/png';

        return Storage::response($company->logo_path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
