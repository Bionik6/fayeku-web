<?php

namespace App\Http\Controllers;

use App\Enums\Compta\LeadSource;
use App\Http\Requests\StoreAccountantJoinRequest;
use App\Http\Requests\StoreContactRequest;
use App\Mail\Compta\AccountantLeadReceivedMail;
use App\Mail\Compta\NewAccountantLeadAlertMail;
use App\Mail\Marketing\ContactReceivedMail;
use App\Mail\Marketing\NewContactAlertMail;
use App\Models\Compta\AccountantLead;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class MarketingPageController extends Controller
{
    public function home(): View
    {
        return $this->render('marketing.home', [
            'title' => 'Logiciel de facturation pour PME au Sénégal | Fayeku',
            'description' => 'Logiciel de facturation pour PME au Sénégal : devis, factures, relances WhatsApp automatiques et collaboration cabinet comptable. 2 mois d’essai.',
            'pageType' => 'home',
            'faqItems' => array_slice((array) config('marketing.faq_items', []), 0, 5),
        ]);
    }

    public function enterprises(): View
    {
        return $this->render('marketing.enterprises', [
            'title' => 'Logiciel facturation PME Sénégal — Devis, factures, recouvrement | Fayeku',
            'description' => 'Logiciel de facturation pour PME au Sénégal : créez vos devis et factures, suivez vos impayés et automatisez vos relances WhatsApp depuis Dakar.',
            'pageType' => 'enterprises',
            'breadcrumbs' => [
                ['name' => 'Accueil', 'url' => config('marketing.site.url')],
                ['name' => 'Entreprises', 'url' => config('marketing.site.url').'/entreprises'],
            ],
        ]);
    }

    public function accountants(): View
    {
        return $this->render('marketing.accountants', [
            'title' => 'Logiciel pour cabinet comptable au Sénégal — Cockpit multi-clients | Fayeku',
            'description' => 'Logiciel pour cabinet comptable et expert-comptable au Sénégal : cockpit multi-clients gratuit, collecte de pièces, exports Sage/EBP, commission partenaire 15%.',
            'pageType' => 'accountants',
            'breadcrumbs' => [
                ['name' => 'Accueil', 'url' => config('marketing.site.url')],
                ['name' => 'Experts-comptables', 'url' => config('marketing.site.url').'/accountants'],
            ],
        ]);
    }

    public function accountantsJoin(): View
    {
        return $this->render('marketing.accountants-join', [
            'title' => 'Rejoindre Fayeku Compta — Cabinets comptables Sénégal',
            'description' => 'Rejoignez Fayeku Compta : formulaire dédié aux cabinets d’expertise comptable au Sénégal pour activer votre cockpit gratuit en 24 h.',
            'pageType' => 'lead',
            'noindex' => true,
        ]);
    }

    public function accountantsJoinStore(StoreAccountantJoinRequest $request): RedirectResponse
    {
        $lead = AccountantLead::create($request->validated() + ['source' => LeadSource::Organic]);

        foreach (config('fayeku.admin_emails', []) as $adminEmail) {
            Mail::to($adminEmail)->send(new NewAccountantLeadAlertMail($lead));
        }

        Mail::to($lead->email)->send(new AccountantLeadReceivedMail($lead));

        return redirect()->route('marketing.accountants.join')
            ->with('success', 'Votre demande a bien été reçue. Un conseiller Fayeku vous contactera sous 24h pour valider votre accès Compta.');
    }

    public function pricing(): View
    {
        return $this->render('marketing.pricing', [
            'title' => 'Tarifs logiciel facturation Sénégal — dès 10 000 FCFA/mois | Fayeku',
            'description' => 'Tarifs Fayeku : plans Basique, Essentiel et Entreprise pour la facturation, les relances WhatsApp et la trésorerie des PME au Sénégal. 2 mois d’essai.',
            'pageType' => 'pricing',
            'breadcrumbs' => [
                ['name' => 'Accueil', 'url' => config('marketing.site.url')],
                ['name' => 'Tarifs', 'url' => config('marketing.site.url').'/pricing'],
            ],
            'faqItems' => array_slice((array) config('marketing.faq_items', []), 0, 5),
        ]);
    }

    public function compliance(): View
    {
        return $this->render('marketing.compliance', [
            'title' => 'Facturation électronique DGID Sénégal — Loi de Finances 2025 | Fayeku',
            'description' => 'Conformité DGID au Sénégal : ce que prévoit la Loi de Finances 2025 sur la facturation électronique et comment Fayeku prépare les PME et cabinets comptables.',
            'pageType' => 'compliance',
            'breadcrumbs' => [
                ['name' => 'Accueil', 'url' => config('marketing.site.url')],
                ['name' => 'Conformité', 'url' => config('marketing.site.url').'/conformite'],
            ],
        ]);
    }

    public function contact(): View
    {
        return $this->render('marketing.contact', [
            'title' => 'Démo & essai 2 mois — Logiciel facturation PME Sénégal | Fayeku',
            'description' => 'Contactez Fayeku pour démarrer 2 mois d’essai, organiser une démo ou discuter de vos besoins de facturation et de conformité DGID au Sénégal.',
            'pageType' => 'contact',
            'breadcrumbs' => [
                ['name' => 'Accueil', 'url' => config('marketing.site.url')],
                ['name' => 'Contact', 'url' => config('marketing.site.url').'/contact'],
            ],
        ]);
    }

    public function contactStore(StoreContactRequest $request): RedirectResponse
    {
        $payload = $request->validated();

        foreach (config('fayeku.admin_emails', []) as $adminEmail) {
            Mail::to($adminEmail)->send(new NewContactAlertMail($payload));
        }

        Mail::to($payload['email'])->send(new ContactReceivedMail($payload));

        return redirect()->route('marketing.contact')
            ->with('success', 'Votre message a bien été reçu. L’équipe Fayeku vous recontacte sous 24h ouvrées.');
    }

    public function seoLanding(string $slug): View
    {
        $landings = (array) config('marketing-seo.landings', []);

        abort_unless(array_key_exists($slug, $landings), 404);

        $landing = $landings[$slug];

        $breadcrumbs = [
            ['name' => 'Accueil', 'url' => config('marketing.site.url')],
            ['name' => $landing['eyebrow'] ?? $landing['h1'], 'url' => config('marketing.site.url').'/'.$slug],
        ];

        return $this->render('marketing.seo-landing', [
            'title' => $landing['meta_title'],
            'description' => $landing['meta_description'],
            'pageType' => $landing['page_type'] ?? 'seo-software',
            'breadcrumbs' => $breadcrumbs,
            'faqItems' => $landing['faq'] ?? [],
            'landing' => $landing,
        ]);
    }

    public function legal(string $page): View
    {
        abort_unless(in_array($page, ['mentions-legales', 'confidentialite'], true), 404);

        return match ($page) {
            'mentions-legales' => $this->render('marketing.legal-mentions', [
                'title' => 'Mentions légales | Fayeku',
                'description' => 'Mentions légales du site fayeku.sn — informations sur l’éditeur, l’hébergement et le contact.',
                'pageType' => 'legal',
            ]),
            'confidentialite' => $this->render('marketing.privacy', [
                'title' => 'Politique de confidentialité | Fayeku',
                'description' => 'Politique de confidentialité de Fayeku — collecte, utilisation et protection de vos données personnelles.',
                'pageType' => 'legal',
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(string $view, array $data = []): View
    {
        $site = config('marketing.site');
        $path = request()->path();
        $canonicalPath = $path === '/' ? '' : '/'.trim($path, '/');

        return view($view, array_merge([
            'site' => $site,
            'navigation' => config('marketing.navigation'),
            'legalLinks' => config('marketing.legal_links'),
            'metaTitle' => $data['title'] ?? $site['name'],
            'metaDescription' => $data['description'] ?? $site['description'],
            'metaKeywords' => implode(', ', $site['keywords']),
            'canonicalUrl' => rtrim($site['url'], '/').($canonicalPath ?: '/'),
            'pageType' => $data['pageType'] ?? null,
            'breadcrumbs' => $data['breadcrumbs'] ?? [],
            'faqItems' => $data['faqItems'] ?? [],
            'noindex' => $data['noindex'] ?? false,
        ], $data));
    }
}
