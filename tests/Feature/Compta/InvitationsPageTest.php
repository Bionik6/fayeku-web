<?php

use App\Models\Auth\AccountantCompany;
use App\Models\Auth\Company;
use App\Models\Compta\Commission;
use App\Models\Compta\PartnerInvitation;
use App\Models\Shared\User;
use App\Services\Compta\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function invTestCreateFirm(int $smeCount = 0): array
{
    $user = User::factory()->accountantFirm()->create();
    $firm = Company::factory()->accountantFirm()->create();
    $firm->users()->attach($user->id, ['role' => 'admin']);

    $smes = [];
    for ($i = 0; $i < $smeCount; $i++) {
        $sme = Company::factory()->create();
        AccountantCompany::create([
            'accountant_firm_id' => $firm->id,
            'sme_company_id' => $sme->id,
            'started_at' => now()->subMonths(3),
        ]);
        $smes[] = $sme;
    }

    return compact('user', 'firm', 'smes');
}

// ─── Accès & rendu ────────────────────────────────────────────────────────────

test('la page invitations est accessible pour un utilisateur authentifié', function () {
    ['user' => $user] = invTestCreateFirm();

    $this->actingAs($user)
        ->get(route('invitations.index'))
        ->assertSuccessful();
});

test('la page invitations redirige un utilisateur non authentifié', function () {
    $this->get(route('invitations.index'))
        ->assertRedirect(route('login'));
});

test('le composant invitations se rend sans erreur pour un user sans cabinet', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::compta.invitations.index')
        ->assertOk();
});

test('le composant invitations affiche les sections principales', function () {
    ['user' => $user] = invTestCreateFirm();

    Livewire::actingAs($user)
        ->test('pages::compta.invitations.index')
        ->assertOk()
        ->assertSee('Invitations & activations')
        ->assertSee('Suivi des invitations')
        ->assertSee('Inviter une PME');
});

test('la section développer votre portefeuille est visible', function () {
    ['user' => $user] = invTestCreateFirm();

    Livewire::actingAs($user)
        ->test('pages::compta.invitations.index')
        ->assertSee('Développez votre portefeuille partenaire')
        ->assertSee('Partager votre lien partenaire')
        ->assertSee('Invitations en attente');
});

test('la section revenus partenaire affiche le lien vers les commissions', function () {
    ['user' => $user] = invTestCreateFirm();

    Livewire::actingAs($user)
        ->test('pages::compta.invitations.index')
        ->assertSee('Revenus partenaire ce mois-ci')
        ->assertSee('Voir mes commissions');
});

// ─── KPIs ────────────────────────────────────────────────────────────────────

test('les KPIs affichent les bons compteurs', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-1',
        'invitee_company_name' => 'Test SARL',
        'invitee_name' => 'Test Contact',
        'invitee_phone' => '+221770000001',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-2',
        'invitee_company_name' => 'Activated Co',
        'invitee_name' => 'Contact 2',
        'invitee_phone' => '+221770000002',
        'status' => 'accepted',
        'accepted_at' => now(),
        'channel' => 'whatsapp',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::compta.invitations.index');

    expect($component->get('totalSent'))->toBe(2);
    expect($component->get('pendingCount'))->toBe(1);
    expect($component->get('activatedThisMonth'))->toBe(1);
    expect($component->get('conversionRate'))->toBe(50);
});

// ─── Pont vers Commissions ────────────────────────────────────────────────────

test('commissionMonthTotal retourne le total des commissions du mois en cours', function () {
    ['user' => $user, 'firm' => $firm, 'smes' => $smes] = invTestCreateFirm(1);

    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 15_000,
        'period_month' => now()->startOfMonth(),
        'status' => 'pending',
    ]);

    Commission::create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id' => $smes[0]->id,
        'amount' => 8_000,
        'period_month' => now()->subMonth()->startOfMonth(),
        'status' => 'paid',
        'paid_at' => now()->subDays(10),
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::compta.invitations.index');

    expect($component->get('commissionMonthTotal'))->toBe(15_000);
});

test('commissionMonthTotal vaut 0 sans commissions ce mois', function () {
    ['user' => $user] = invTestCreateFirm();

    $component = Livewire::actingAs($user)
        ->test('pages::compta.invitations.index');

    expect($component->get('commissionMonthTotal'))->toBe(0);
});

// ─── Événement invitation-sent ────────────────────────────────────────────────

test('onInvitationSent rafraîchit la liste des invitations', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $component = Livewire::actingAs($user)
        ->test('pages::compta.invitations.index');

    expect($component->get('totalSent'))->toBe(0);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'new-token',
        'invitee_company_name' => 'New Co',
        'invitee_name' => 'New Contact',
        'invitee_phone' => '+221770000010',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $component->dispatch('invitation-sent');

    expect($component->get('totalSent'))->toBe(1);
    expect($component->get('pendingCount'))->toBe(1);
});

// ─── Filtres ─────────────────────────────────────────────────────────────────

test('le filtre non ouverts fonctionne', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-a',
        'invitee_company_name' => 'Not Opened Co',
        'invitee_name' => 'Contact A',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-b',
        'invitee_company_name' => 'Opened Co',
        'invitee_name' => 'Contact B',
        'status' => 'pending',
        'link_opened_at' => now(),
        'channel' => 'whatsapp',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::compta.invitations.index')
        ->call('setFilter', 'not_opened');

    expect($component->get('invitations'))->toHaveCount(1);
    expect($component->get('invitations')->first()->invitee_company_name)->toBe('Not Opened Co');
});

test('le filtre activés fonctionne', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-c',
        'invitee_company_name' => 'Active Co',
        'invitee_name' => 'Contact C',
        'status' => 'accepted',
        'accepted_at' => now(),
        'channel' => 'whatsapp',
    ]);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-d',
        'invitee_company_name' => 'Pending Co',
        'invitee_name' => 'Contact D',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::compta.invitations.index')
        ->call('setFilter', 'activated');

    expect($component->get('invitations'))->toHaveCount(1);
    expect($component->get('invitations')->first()->invitee_company_name)->toBe('Active Co');
});

test('le filtre à relancer affiche les invitations de plus de 2 jours', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $old = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-old',
        'invitee_company_name' => 'Old Pending Co',
        'invitee_name' => 'Contact Old',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);
    DB::table('partner_invitations')
        ->where('id', $old->id)
        ->update(['created_at' => now()->subDays(5)]);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-new',
        'invitee_company_name' => 'New Pending Co',
        'invitee_name' => 'Contact New',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::compta.invitations.index')
        ->call('setFilter', 'to_remind');

    expect($component->get('invitations'))->toHaveCount(1);
    expect($component->get('invitations')->first()->invitee_company_name)->toBe('Old Pending Co');
});

// ─── Recherche ───────────────────────────────────────────────────────────────

test('la recherche filtre par nom d\'entreprise', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-e',
        'invitee_company_name' => 'Dakar Import',
        'invitee_name' => 'Contact E',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'token-f',
        'invitee_company_name' => 'Thiès Export',
        'invitee_name' => 'Contact F',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::compta.invitations.index')
        ->set('inviteSearch', 'Dakar');

    expect($component->get('invitations'))->toHaveCount(1);
    expect($component->get('invitations')->first()->invitee_company_name)->toBe('Dakar Import');
});

// ─── InvitationService : composers ───────────────────────────────────────────

test('composeWhatsAppMessage en plan essentiel utilise le label et le prix essentiel', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'created_by_user_id' => $user->id,
        'token' => 'compose-1',
        'invitee_company_name' => 'Promo Co',
        'invitee_name' => 'Awa Ndiaye',
        'invitee_phone' => '+221770000077',
        'recommended_plan' => 'essentiel',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $message = app(InvitationService::class)->composeWhatsAppMessage($invitation);

    expect($message)
        ->toContain('Bonjour Awa,')
        ->toContain($firm->name)
        ->toContain('/join/'.$firm->invite_code)
        ->toContain('*2 mois offerts*')
        ->toContain('plan Essentiel')
        ->toContain('20 000 FCFA')
        ->toContain('✓ Factures pro')
        ->toContain('Activez votre compte ici 👉');
});

test('composeWhatsAppMessage en plan basique utilise le label et le prix basique', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'created_by_user_id' => $user->id,
        'token' => 'compose-2',
        'invitee_company_name' => 'Basic Co',
        'invitee_name' => 'Omar Ba',
        'invitee_phone' => '+221770000066',
        'recommended_plan' => 'basique',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $message = app(InvitationService::class)->composeWhatsAppMessage($invitation);

    expect($message)
        ->toContain('Bonjour Omar,')
        ->toContain('*2 mois offerts*')
        ->toContain('plan Basique')
        ->toContain('10 000 FCFA');
});

test('composeWhatsAppMessage signe avec le prénom de l\'utilisateur quand connu', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();
    $user->forceFill(['first_name' => 'Aliou', 'last_name' => 'Sarr'])->save();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'created_by_user_id' => $user->id,
        'token' => 'compose-sig',
        'invitee_name' => 'Khady Diop',
        'invitee_phone' => '+221770000099',
        'recommended_plan' => 'essentiel',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $message = app(InvitationService::class)->composeWhatsAppMessage($invitation);

    expect($message)
        ->toMatch('/Aliou\s*\n'.preg_quote($firm->name, '/').'/');
});

test('composeWhatsAppMessage en mode reminder utilise un texte de rappel', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'created_by_user_id' => $user->id,
        'token' => 'compose-3',
        'invitee_company_name' => 'Reminder Co',
        'invitee_name' => 'Aïssatou Fall',
        'invitee_phone' => '+221770000055',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $message = app(InvitationService::class)->composeWhatsAppMessage($invitation, 'reminder');

    expect($message)
        ->toContain('Petit rappel')
        ->toContain('Aïssatou')
        ->toContain('/join/'.$firm->invite_code);
});

test('composeEmail retourne un sujet et un corps avec le lien et la signature', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();
    $user->forceFill(['first_name' => 'Aliou', 'last_name' => 'Sarr'])->save();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'created_by_user_id' => $user->id,
        'token' => 'compose-email',
        'invitee_company_name' => 'Email Co',
        'invitee_name' => 'Ibrahima Diop',
        'invitee_email' => 'contact@example.com',
        'recommended_plan' => 'essentiel',
        'status' => 'pending',
        'channel' => 'email',
    ]);

    $email = app(InvitationService::class)->composeEmail($invitation);

    expect($email)->toHaveKeys(['subject', 'body']);
    expect($email['subject'])->toContain($firm->name)->toContain('Fayeku');
    expect($email['body'])
        ->toContain('Bonjour Ibrahima,')
        ->toContain('Notre cabinet utilise désormais Fayeku')
        ->toContain('• Émettre vos factures')
        ->toContain('plan Essentiel')
        ->toContain('20 000 FCFA')
        ->toContain('/join/'.$firm->invite_code)
        ->toContain('Aliou Sarr')
        ->toContain($firm->name);
});

test('composeEmail en plan basique utilise le prix basique', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'created_by_user_id' => $user->id,
        'token' => 'compose-email-basic',
        'invitee_name' => 'Bineta',
        'invitee_email' => 'bineta@example.com',
        'recommended_plan' => 'basique',
        'status' => 'pending',
        'channel' => 'email',
    ]);

    $email = app(InvitationService::class)->composeEmail($invitation);

    expect($email['body'])
        ->toContain('plan Basique')
        ->toContain('10 000 FCFA');
});

test('composePartnerShareMessage produit le message générique avec le lien et la signature', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();
    $user->forceFill(['first_name' => 'Aliou', 'last_name' => 'Sarr'])->save();

    $message = app(InvitationService::class)->composePartnerShareMessage($firm, $user);

    expect($message)
        ->toContain('*Fayeku*')
        ->toContain('*2 mois offerts*')
        ->toContain('✓ Factures pro')
        ->toContain('✓ Relances WhatsApp/Email automatiques')
        ->toContain('✓ Vision claire de votre trésorerie à 30 et 90 jours')
        ->toContain('/join/'.$firm->invite_code)
        ->toContain('Aliou')
        ->toContain($firm->name);
});

test('composePartnerShareMessage tombe en arrière sur le nom du cabinet sans utilisateur', function () {
    ['firm' => $firm] = invTestCreateFirm();

    $message = app(InvitationService::class)->composePartnerShareMessage($firm);

    expect($message)
        ->toContain('/join/'.$firm->invite_code)
        ->toContain($firm->name);
});

test('composeEmail en mode reminder ajoute [Rappel] dans le sujet', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'created_by_user_id' => $user->id,
        'token' => 'compose-email-rem',
        'invitee_company_name' => 'Reminder Email Co',
        'invitee_name' => 'Mariama Sy',
        'invitee_email' => 'mariama@example.com',
        'status' => 'pending',
        'channel' => 'email',
    ]);

    $email = app(InvitationService::class)->composeEmail($invitation, 'reminder');

    expect($email['subject'])->toStartWith('[Rappel]');
    expect($email['body'])
        ->toContain('Mariama')
        ->toContain('Petit rappel');
});

// ─── InvitationService : link builders ───────────────────────────────────────

test('buildWhatsAppLink utilise wa.me + chiffres seulement et encode le message', function () {
    ['firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'link-1',
        'invitee_company_name' => 'Link Co',
        'invitee_name' => 'Khady',
        'invitee_phone' => '+221770000088',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    $link = app(InvitationService::class)->buildWhatsAppLink($invitation);

    expect($link)
        ->toStartWith('https://wa.me/221770000088?text=')
        ->toContain(rawurlencode($firm->name));
});

test('buildMailtoLink utilise mailto: et encode subject/body en RFC3986', function () {
    ['firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'link-2',
        'invitee_company_name' => 'Mailto Co',
        'invitee_name' => 'Cheikh',
        'invitee_email' => 'cheikh@example.com',
        'status' => 'pending',
        'channel' => 'email',
    ]);

    $link = app(InvitationService::class)->buildMailtoLink($invitation);

    expect($link)
        ->toStartWith('mailto:')
        ->toContain('cheikh%40example.com')
        ->toContain('subject=')
        ->toContain('body=');
});

// ─── InvitationService : state mutators ──────────────────────────────────────

test('markSent met à jour le canal et le statut à pending', function () {
    ['firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'state-1',
        'invitee_name' => 'Test',
        'invitee_phone' => '+221770000044',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    app(InvitationService::class)->markSent($invitation, 'email');

    $invitation->refresh();
    expect($invitation->channel)->toBe('email');
    expect($invitation->status)->toBe('pending');
});

test('markReminded incrémente reminder_count et met à jour last_reminder_at', function () {
    ['firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'state-2',
        'invitee_name' => 'Test Remind',
        'invitee_phone' => '+221770000033',
        'status' => 'pending',
        'channel' => 'whatsapp',
        'reminder_count' => 2,
    ]);

    app(InvitationService::class)->markReminded($invitation, 'whatsapp');

    $invitation->refresh();
    expect($invitation->reminder_count)->toBe(3);
    expect($invitation->last_reminder_at)->not->toBeNull();
    expect($invitation->channel)->toBe('whatsapp');
});

// ─── Modale : invite-pme-modal ────────────────────────────────────────────────

test('confirmSent crée une PartnerInvitation pour le canal whatsapp', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    Livewire::actingAs($user)
        ->test('invite-pme-modal')
        ->set('inviteCompanyName', 'Nouvelle PME SARL')
        ->set('inviteContactName', 'Fatou Sow')
        ->set('inviteCountryCode', 'SN')
        ->set('invitePhone', '770000123')
        ->set('invitePlan', 'essentiel')
        ->set('inviteChannel', 'whatsapp')
        ->call('confirmSent', 'whatsapp')
        ->assertDispatched('invitation-sent')
        ->assertDispatched('toast');

    $invitation = PartnerInvitation::where('accountant_firm_id', $firm->id)->first();
    expect($invitation)->not->toBeNull();
    expect($invitation->invitee_company_name)->toBe('Nouvelle PME SARL');
    expect($invitation->invitee_name)->toBe('Fatou Sow');
    expect($invitation->invitee_phone)->toBe('+221770000123');
    expect($invitation->channel)->toBe('whatsapp');
    expect($invitation->status)->toBe('pending');
    expect($invitation->created_by_user_id)->toBe($user->id);
});

test('confirmSent crée une PartnerInvitation pour le canal email', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    Livewire::actingAs($user)
        ->test('invite-pme-modal')
        ->set('inviteCompanyName', 'Email PME')
        ->set('inviteContactName', 'Modou')
        ->set('inviteEmail', 'modou@example.com')
        ->set('invitePlan', 'basique')
        ->set('inviteChannel', 'email')
        ->call('confirmSent', 'email')
        ->assertDispatched('invitation-sent');

    $invitation = PartnerInvitation::where('accountant_firm_id', $firm->id)->first();
    expect($invitation->invitee_email)->toBe('modou@example.com');
    expect($invitation->channel)->toBe('email');
});

test('confirmSent exige un téléphone pour le canal whatsapp', function () {
    ['user' => $user] = invTestCreateFirm();

    Livewire::actingAs($user)
        ->test('invite-pme-modal')
        ->set('inviteCompanyName', 'Sans Tel')
        ->set('inviteContactName', 'Fatou')
        ->call('confirmSent', 'whatsapp')
        ->assertHasErrors(['invitePhone' => 'required']);
});

test('confirmSent exige un email pour le canal email', function () {
    ['user' => $user] = invTestCreateFirm();

    Livewire::actingAs($user)
        ->test('invite-pme-modal')
        ->set('inviteCompanyName', 'Sans Email')
        ->set('inviteContactName', 'Modou')
        ->set('inviteChannel', 'email')
        ->call('confirmSent', 'email')
        ->assertHasErrors(['inviteEmail' => 'required']);
});

test('confirmSent refuse les doublons par téléphone sur la même firme', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'dup-token',
        'invitee_company_name' => 'Existing',
        'invitee_name' => 'Already',
        'invitee_phone' => '+221770000123',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    Livewire::actingAs($user)
        ->test('invite-pme-modal')
        ->set('inviteCompanyName', 'Doublon')
        ->set('inviteContactName', 'Fatou')
        ->set('invitePhone', '770000123')
        ->call('confirmSent', 'whatsapp')
        ->assertHasErrors('invitePhone');

    expect(PartnerInvitation::where('accountant_firm_id', $firm->id)->count())->toBe(1);
});

test('le mode followup recharge une invitation existante', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'follow-token',
        'invitee_company_name' => 'Suivi Co',
        'invitee_name' => 'Awa',
        'invitee_phone' => '+221770000077',
        'invitee_email' => 'awa@example.com',
        'recommended_plan' => 'essentiel',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);

    Livewire::actingAs($user)
        ->test('invite-pme-modal')
        ->call('openFollowup', $invitation->id, 'reminder')
        ->assertSet('inviteCompanyName', 'Suivi Co')
        ->assertSet('inviteContactName', 'Awa')
        ->assertSet('invitePhone', '+221770000077')
        ->assertSet('inviteEmail', 'awa@example.com')
        ->assertSet('context', 'reminder');
});

test('le mode followup en relance incrémente reminder_count', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'follow-rem',
        'invitee_company_name' => 'Relance Co',
        'invitee_name' => 'Bineta',
        'invitee_phone' => '+221770000088',
        'recommended_plan' => 'essentiel',
        'status' => 'pending',
        'channel' => 'whatsapp',
        'reminder_count' => 1,
    ]);

    Livewire::actingAs($user)
        ->test('invite-pme-modal')
        ->call('openFollowup', $invitation->id, 'reminder')
        ->call('confirmSent', 'whatsapp')
        ->assertDispatched('invitation-sent');

    $invitation->refresh();
    expect($invitation->reminder_count)->toBe(2);
    expect($invitation->last_reminder_at)->not->toBeNull();
});

test('le mode followup en renvoi remet status à pending et reset reminder_count', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $invitation = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'follow-resend',
        'invitee_company_name' => 'Renvoi Co',
        'invitee_name' => 'Cheikh',
        'invitee_phone' => '+221770000099',
        'recommended_plan' => 'essentiel',
        'status' => 'expired',
        'channel' => 'whatsapp',
        'reminder_count' => 4,
    ]);

    Livewire::actingAs($user)
        ->test('invite-pme-modal')
        ->call('openFollowup', $invitation->id, 'resend')
        ->call('confirmSent', 'whatsapp');

    $invitation->refresh();
    expect($invitation->status)->toBe('pending');
    expect($invitation->reminder_count)->toBe(0);
    expect($invitation->expires_at)->not->toBeNull();
});

test('les boutons relancer/renvoyer dispatchent open-invite-pme-followup', function () {
    ['user' => $user, 'firm' => $firm] = invTestCreateFirm();

    $old = PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'old-pending',
        'invitee_company_name' => 'À relancer',
        'invitee_name' => 'X',
        'invitee_phone' => '+221770000111',
        'status' => 'pending',
        'channel' => 'whatsapp',
    ]);
    DB::table('partner_invitations')->where('id', $old->id)->update(['created_at' => now()->subDays(5)]);

    PartnerInvitation::create([
        'accountant_firm_id' => $firm->id,
        'token' => 'expired',
        'invitee_company_name' => 'Expirée Co',
        'invitee_name' => 'Y',
        'invitee_phone' => '+221770000222',
        'status' => 'expired',
        'channel' => 'whatsapp',
    ]);

    Livewire::actingAs($user)
        ->test('pages::compta.invitations.index')
        ->assertSee('Relancer')
        ->assertSee('Renvoyer');
});
