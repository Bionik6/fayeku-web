<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountantJoinRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MarketingPageController extends Controller
{
    public function home(): View
    {
        return $this->render('marketing.home', [
            'title' => 'Fayeku | Facturation & relances WhatsApp pour PME',
            'description' => 'Facturez proprement, relancez sur WhatsApp et collaborez mieux avec votre cabinet comptable depuis une plateforme pensée pour le Sénégal.',
        ]);
    }

    public function enterprises(): View
    {
        return $this->render('marketing.enterprises', [
            'title' => 'Fayeku Entreprises | Facturation simple pour PME',
            'description' => 'Créez vos factures, suivez vos impayés et relancez automatiquement sur WhatsApp avec une interface pensée pour les PME.',
        ]);
    }

    public function accountants(): View
    {
        return $this->render('marketing.accountants', [
            'title' => 'Fayeku Compta | Pour experts-comptables',
            'description' => 'Centralisez les factures de vos PME clientes, collectez les pièces et activez un programme partenaire récurrent avec Fayeku Compta.',
        ]);
    }

    public function accountantsJoin(): View
    {
        return $this->render('marketing.accountants-join', [
            'title' => 'Rejoindre Fayeku Compta | Formulaire experts-comptables',
            'description' => 'Rejoignez Fayeku Compta avec un formulaire dédié aux cabinets d’expertise comptable au Sénégal.',
        ]);
    }

    public function accountantsJoinStore(StoreAccountantJoinRequest $request): RedirectResponse
    {
        // TODO: store lead or send notification email
        return redirect()->route('marketing.accountants.join')
            ->with('success', 'Votre demande a bien été reçue. Un conseiller Fayeku vous contactera sous 24h pour valider votre accès Compta.');
    }

    public function pricing(): View
    {
        return $this->render('marketing.pricing', [
            'title' => 'Tarifs Fayeku | Plans Basique, Essentiel, Entreprise',
            'description' => 'Comparez les plans Fayeku pour la facturation, les relances WhatsApp et le pilotage de trésorerie des PME au Sénégal.',
        ]);
    }

    public function compliance(): View
    {
        return $this->render('marketing.compliance', [
            'title' => 'Conformité Fayeku | DGID Sénégal',
            'description' => 'Découvrez l’approche Fayeku pour la conformité DGID au Sénégal, pensée pour les PME et cabinets.',
        ]);
    }

    public function contact(): View
    {
        return $this->render('marketing.contact', [
            'title' => 'Contact Fayeku | Demander une démo ou démarrer 2 mois d’essai',
            'description' => 'Contactez Fayeku pour lancer un essai, organiser une démo ou discuter de vos besoins de facturation et conformité.',
        ]);
    }

    public function legal(string $page): View
    {
        abort_unless(in_array($page, ['mentions-legales', 'confidentialite'], true), 404);

        return match ($page) {
            'mentions-legales' => $this->render('marketing.legal-mentions', [
                'title' => 'Mentions légales Fayeku',
                'description' => 'Mentions légales du site fayeku.sn — informations sur l’éditeur et l’hébergement.',
            ]),
            'confidentialite' => $this->render('marketing.privacy', [
                'title' => 'Politique de confidentialité Fayeku',
                'description' => 'Politique de confidentialité de Fayeku — collecte, utilisation et protection de vos données personnelles.',
            ]),
        };
    }

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
        ], $data));
    }
}
