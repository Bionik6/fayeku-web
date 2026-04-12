# AGENT.md — Fayeku

> This file is the single source of truth for every developer and AI agent working on this
> codebase. Read it fully before writing any code, creating any file, or making any
> architectural decision. When in doubt, re-read this file before asking.

---

## 1. Project Overview

**Fayeku** is a B2B SaaS platform for African SMEs — primarily Senegal, secondarily
Ivory Coast. It covers professional invoicing, automated WhatsApp debt collection,
treasury visibility, and structured collaboration between SMEs and their accounting firms.

### Two distinct user profiles, one platform

| Profile | Product name | Primary job |
|---|---|---|
| SME owner / team | Fayeku PME | Create invoices, track cash, manage collections |
| Accountant / firm | Fayeku Compta | Monitor client portfolio, export to accounting software, earn commissions |

### Non-negotiable product constraints

- Authentication is by **phone number + password only**. No email login.
- **OTP (SMS) is required at registration only**, never at login.
- Country selection at registration: **Senegal (+221)** and **Ivory Coast (+225)** only.
- Phone numbers are stored in **E.164 format** (`+221771234567`).
- **WhatsApp is the primary channel** for payment reminders. **SMS and email are also
  supported** on all plans. The PME chooses the channel per reminder at send time.
  Quota (e.g. 20/month on Basique) applies to the **total count across all channels**,
  not per channel.
- **Ivory Coast FNE (Facture Normalisée Electronique) is live and mandatory.**
  The API integration guide is published at `fne.dgi.gouv.ci`. Integration uses
  REST/JSON with Bearer token auth. This is a confirmed integration target for launch.
- **Senegal DGID** electronic invoicing is legislated but the technical API is not
  yet published. Fayeku must be architecturally ready to integrate when it drops.
- All monetary amounts are stored as **integers** (whole FCFA — no cents, no floats).

---

## 2. Technology Stack

Every item below is a confirmed decision. Do not suggest alternatives unless asked.

| Layer | Choice | Version |
|---|---|---|
| Framework | Laravel | v13 |
| PHP | PHP | 8.5 |
| Frontend | TALL stack | Tailwind CSS v4, Alpine.js v3, Livewire v4 |
| UI components | Tailwind UI (`templates/tailwind-plus/`) | — |
| Authentication backend | Laravel Fortify | v1 |
| Database | PostgreSQL | 16 |
| Primary key type | **ULID** (not UUID, not auto-increment) | — |
| Cache | Redis | — |
| Queue driver (production) | Database (`database` driver, PostgreSQL `jobs` table) | — |
| Real-time | Not implemented for now | — |
| SMS / OTP delivery | Abstracted behind `SmsProviderInterface` — provider TBD | — |
| SMS reminders | Reuses `SmsProviderInterface` — same provider, different message type | — |
| WhatsApp reminders | Abstracted behind `WhatsAppProviderInterface` — provider TBD | — |
| Email reminders | Abstracted behind `EmailReminderInterface` — uses Laravel `Mail` facade | — |
| Mobile API auth | Laravel Sanctum (token-based) | — |
| PDF generation | Abstracted behind `PdfGeneratorInterface` — implementation TBD | — |
| File storage | Local disk for now — use Laravel's `Storage` facade only, S3-compatible later | — |
| Accounting exports | Sage 100, EBP Compta, Excel/CSV at launch | — |
| Commission payouts | Abstracted behind `PayoutInterface` — provider TBD | — |
| Testing | Pest PHP | v4 (PHPUnit v12) |
| Code formatter | Laravel Pint | v1 |
| Local dev | Laravel Herd (native macOS) | — |
| CI | GitHub Actions | — |

### Confirmed package versions (do not upgrade without approval)

```
php                  8.5
laravel/framework    v13
laravel/fortify      v1
laravel/prompts      v0
livewire/livewire    v4
livewire/flux        v2
laravel/boost        v2
laravel/pail         v1
laravel/pint         v1
laravel/sail         v1
laravel/mcp          v0
pestphp/pest         v4
phpunit/phpunit      v12
tailwindcss          v4
```

> Do not change any dependency version without explicit approval.

### UI development rules

- **Do NOT use Flux UI (`<flux:*>`) components for new UI development.** Flux may remain in legacy code but must not be added to new or modified views.
- **All new UI must be built with Tailwind CSS utility classes**, using `templates/tailwind-plus/` as the component reference library.
- Modals, drawers, forms, tables, buttons, and badges must follow patterns from `templates/tailwind-plus/application-ui/`.
- Buttons follow the primary/secondary color convention: primary = `bg-primary text-white hover:bg-primary-strong`, secondary = `border border-slate-200 bg-white text-slate-700 hover:border-primary/30 hover:text-primary`.
- Use inline SVG icons (Heroicons) instead of `<flux:icon>`.

### Queue notes

Run `php artisan queue:table` and `php artisan queue:failed-table` to generate the
`jobs` and `failed_jobs` migrations. Do not use Redis queues unless explicitly asked.

---

## 3. Agent Tools & Skill Activation (Laravel Boost)

This project uses **Laravel Boost** (MCP server). Agents must use these tools before
writing code. Never guess what a framework feature does — search first.

### 3.1 Mandatory tool usage

| Situation | Tool to use |
|---|---|
| Any Laravel / ecosystem question | `search-docs` before writing code |
| Reading from the database | `database-query` tool |
| Inspecting table structure | `database-schema` before writing migrations or models |
| Sharing a URL with the user | `get-absolute-url` to get the correct scheme/domain/port |
| Debugging browser errors | `browser-logs` tool (recent logs only) |
| Executing PHP for debugging | `php artisan tinker --execute "..."` |
| Checking routes | `php artisan route:list` |
| Reading config values | Read config files or `php artisan config:show [key]` |
| Checking env values | Read `.env` directly |

### 3.2 Skill activation — activate before writing, not after getting stuck

| Domain | Skill to activate | When |
|---|---|---|
| Livewire | `livewire-development` | Any `wire:` directive, Livewire component, reactivity issue |
| Pest tests | `pest-testing` | Writing, editing, fixing, or refactoring any test |
| Tailwind CSS | `tailwindcss-development` | Any Tailwind class, responsive layout, dark mode |
| Fortify auth | `fortify-development` | Login, registration, OTP, password reset, 2FA |

### 3.3 Search-docs usage rules

- Use `search-docs` for **broad, simple, topic-based queries** — not full sentences.
- Pass multiple queries at once: `['livewire validation', 'wire:model', 'form validation']`.
- Do not add package names to queries — package context is already included automatically.
- The tool returns version-specific documentation for the exact packages installed.

---

## 4. Architecture: Domain-Organized Monolith

### 4.1 Core Principles

- **One deployable application, standard Laravel structure with domain subdirectories.**
  No microservices. No separate deployments. No separate repositories.
- **All code lives in `app/`** with domain subdirectories: `PME/`, `Compta/`, `Auth/`, `Shared/`.
  Each domain groups its Models, Services, Controllers, Enums, Policies, etc. under the
  standard Laravel directories (e.g. `app/Models/PME/`, `app/Services/Compta/`).
- **Cross-domain communication uses Events for side effects and direct Service
  injection for queries.** Domain A can call a public Service class from Domain B.
- **Shared code lives in `app/*/Shared/` subdirectories.** Anything used by more than one domain
  goes there (e.g. `app/Models/Shared/User.php`, `app/Services/Shared/OtpService.php`).
- **No circular dependencies.** Both `PME` and `Compta` may depend on `Shared`.
  `PME` and `Compta` must never depend on each other directly.

### 4.2 Directory Structure

Code is organized within the standard `app/` directory using domain subdirectories.

```
app/
├── Models/
│   ├── PME/          Invoice, InvoiceLine, Quote, QuoteLine, Client, Reminder, ReminderRule
│   ├── Compta/       ExportHistory, Commission, CommissionPayment, PartnerInvitation, DismissedAlert
│   ├── Auth/         Company, AccountantCompany, Subscription
│   └── Shared/       User, Country
├── Services/
│   ├── PME/          InvoiceService, QuoteService, CurrencyService, PdfService, ClientService,
│   │                 ReminderService, EmailReminderService, SmsReminderService, WhatsAppReminderService,
│   │                 TreasuryService, ForecastService
│   ├── Compta/       ExportService, EbpExporter, ExcelExporter, SageExporter, CommissionService,
│   │                 InvitationService, PortfolioService, AlertService, ComplianceService,
│   │                 DGIDConnector, FNEFiscalConnector
│   ├── Auth/         AuthService
│   └── Shared/       OtpService, QuotaService, FakeSmsProvider, FakeWhatsAppProvider, TwilioWhatsAppProvider
├── Http/
│   ├── Controllers/
│   │   ├── PME/      InvoicePdfController, QuotePdfController, TreasuryExportController
│   │   ├── Compta/   ExportDownloadController, JoinController
│   │   └── Auth/     LoginController, RegisterController, CompanySetupController, OtpController,
│   │                 PasswordResetController, LogoutController
│   └── Requests/
│       └── Auth/     CompanySetupRequest, LoginRequest, RegisterRequest, VerifyOtpRequest, etc.
├── Enums/
│   ├── PME/          InvoiceStatus, QuoteStatus, LineType, ReminderChannel, ReminderMode
│   ├── Compta/       ExportFormat, PartnerTier, CertificationAuthority, FiscalCountry
│   └── Shared/       QuotaType
├── Policies/PME/     InvoicePolicy, QuotePolicy, ClientPolicy, ReminderPolicy
├── Events/PME/       InvoiceCreated, InvoiceMarkedOverdue, InvoicePaid, QuoteAccepted
├── Listeners/PME/    NotifyAccountantOnNewInvoice
├── Mail/PME/         InvoiceMail
├── Jobs/PME/         SendReminderJob
├── Interfaces/
│   ├── PME/          ReminderChannelInterface
│   ├── Compta/       AccountingExporterInterface, FiscalConnectorInterface
│   └── Shared/       SmsProviderInterface, WhatsAppProviderInterface, PdfGeneratorInterface,
│                     PayoutInterface, EmailReminderInterface
├── DTOs/Compta/      FiscalCertification, FneInvoicePayload
├── Exceptions/Shared/ QuotaExceededException
├── Middleware/        EnsurePhoneVerified, EnsureProfileType
├── Traits/Shared/     HasUlid
├── Providers/
│   ├── PME/          PmeModuleServiceProvider
│   ├── Compta/       ComptaModuleServiceProvider
│   ├── Auth/         AuthModuleServiceProvider
│   └── Shared/       SharedServiceProvider
├── Livewire/         Sidebar components, actions
└── helpers.php

database/migrations/   All migrations (flat, auto-discovered by Laravel)
routes/                web.php, api.php, auth-web.php, auth-api.php, pme-web.php, pme-api.php, compta-web.php

tests/
├── Feature/
│   ├── PME/          Invoices, quotes, clients, collection, treasury tests
│   ├── Compta/       Export, partnership, portfolio, compliance tests
│   ├── Auth/         Login, registration, OTP, dashboard, sidebar tests
│   └── Shared/       Access control, support, settings tests
└── Unit/
    ├── Compta/       CommissionCalculationTest
    └── Shared/       HelpersTest
```

---

## 5. Provider Registration

```php
// bootstrap/providers.php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\Shared\SharedServiceProvider::class,
    App\Providers\Auth\AuthModuleServiceProvider::class,
    App\Providers\PME\PmeModuleServiceProvider::class,
    App\Providers\Compta\ComptaModuleServiceProvider::class,
];
```

Each parent provider registers its sub-providers:

```php
// app/Providers/PME/PmeModuleServiceProvider.php
class PmeModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(InvoicingServiceProvider::class);
        $this->app->register(ClientsServiceProvider::class);
        $this->app->register(CollectionServiceProvider::class);
        $this->app->register(TreasuryServiceProvider::class);
    }
}
```

Routes are loaded from the central `routes/` directory:

```php
// app/Providers/Auth/AuthModuleServiceProvider.php
public function boot(): void
{
    $this->loadRoutesFrom(base_path('routes/auth-web.php'));
    $this->loadRoutesFrom(base_path('routes/auth-api.php'));
}
```

Migrations live in `database/migrations/` (auto-discovered by Laravel).

---

## 6. Data Model & Ownership

### 5.1 Philosophy: Ownership via Foreign Keys

There is **no `tenants` table and no `tenant_id` on models**. Data isolation is
enforced through direct ownership foreign keys and Laravel Policies. Every
resource traces back to a `company` through its FK chain. Authorization is
checked explicitly via policies — not silently via a global scope trait.

```
users ──< company_user >── companies ──< invoices
                               │
                               └──< clients (per company)
```

### 5.2 Core Tables

```sql
-- users
-- One row per human being. A user can belong to one or more companies via the pivot.
id                  CHAR(26) PRIMARY KEY   -- ULID
first_name          VARCHAR(100)
last_name           VARCHAR(100)
phone               VARCHAR(20) UNIQUE     -- E.164
phone_verified_at   TIMESTAMP NULL
password            VARCHAR(255)           -- bcrypt hashed
profile_type        VARCHAR(20)            -- 'sme' | 'accountant'
country_code        CHAR(2)                -- 'SN' | 'CI'
is_active           BOOLEAN DEFAULT TRUE
remember_token      VARCHAR(100) NULL
created_at, updated_at

-- companies
-- Represents either an SME or an accounting firm. One company per business entity.
id                  CHAR(26) PRIMARY KEY   -- ULID
name                VARCHAR(255)
type                VARCHAR(20)            -- 'sme' | 'accountant_firm'
plan                VARCHAR(20) NULL       -- 'basique' | 'essentiel' | 'entreprise'
country_code        CHAR(2)                -- 'SN' | 'CI'
phone               VARCHAR(20) NULL       -- company contact phone
created_at, updated_at

-- company_user  (pivot: team membership)
-- A user can belong to multiple companies (e.g. an accountant who also runs an SME).
-- A company can have multiple users (team members).
id          CHAR(26) PRIMARY KEY
company_id  CHAR(26) FK → companies.id  ON DELETE CASCADE
user_id     CHAR(26) FK → users.id      ON DELETE CASCADE
role        VARCHAR(20)                  -- 'owner' | 'admin' | 'member'
created_at, updated_at
UNIQUE (company_id, user_id)

-- accountant_company  (pivot: accountant firm ↔ SME relationship)
-- Tracks the full history of which firm has managed which SME, and when.
-- An SME CAN have multiple active accountants simultaneously (ended_at IS NULL
-- for more than one row with the same sme_company_id is valid).
-- History is preserved: ended relationships keep their row with ended_at set.
-- An accountant firm can manage many SMEs simultaneously.
id                   CHAR(26) PRIMARY KEY
accountant_firm_id   CHAR(26) FK → companies.id  -- must be type='accountant_firm'
sme_company_id       CHAR(26) FK → companies.id  -- must be type='sme'
started_at           TIMESTAMP                    -- when this relationship became active
ended_at             TIMESTAMP NULL               -- NULL = currently active
ended_reason         VARCHAR(255) NULL
created_at, updated_at
-- No partial unique index on (sme_company_id) WHERE ended_at IS NULL.
-- Multiple active accountants per SME is intentionally allowed.
-- The only uniqueness constraint is on the pair (accountant_firm_id, sme_company_id)
-- to prevent duplicate active rows for the exact same firm+SME combination:
UNIQUE (accountant_firm_id, sme_company_id)
```

### 5.3 The Client Table

Clients are **per-company records**. Two different PMEs can both have "Sonatel" as
a client, but those are two separate rows in the `clients` table — each PME manages
its own client list independently. There is no shared global client registry.

```sql
-- clients
-- Owned by a company (the SME that manages this client relationship).
-- company_id is the owning SME, not Sonatel's own company record.
id          CHAR(26) PRIMARY KEY
company_id  CHAR(26) FK → companies.id   -- the PME that owns this client record
name        VARCHAR(255)
phone       VARCHAR(20) NULL
email       VARCHAR(255) NULL
address     TEXT NULL
created_at, updated_at
```

> Sow BTP has a `clients` row for Sonatel with `company_id = sow_btp_id`.
> Diop Services has a separate `clients` row for Sonatel with `company_id = diop_services_id`.
> These are independent records. Each PME controls their own copy.

### 5.4 Partner Invitations and the Auto-Link Flow

When an accountant invites an SME via their referral link, a `partner_invitations`
row is created with `status = 'pending'`. The SME then registers themselves through
the normal registration flow. On successful OTP verification, the system checks
whether the registering phone number matches a pending invitation — if it does,
two things happen automatically in the same database transaction:

1. An `accountant_company` row is created linking the new SME to the inviting firm
   (`started_at = now()`, `ended_at = NULL`).
2. The `partner_invitations` row is updated to `status = 'accepted'`.

If no matching invitation exists, registration completes normally with no accountant
link. The invitation token is embedded in the registration URL
(`/register?invite=TOKEN`) and stored on the session — it is never trusted from
user-submitted form data directly.

```sql
-- partner_invitations
id                   CHAR(26) PRIMARY KEY
accountant_firm_id   CHAR(26) FK → companies.id   -- the inviting firm
token                VARCHAR(64) UNIQUE            -- random 32-byte hex, URL-safe
invitee_phone        VARCHAR(20) NULL              -- pre-filled if accountant knows the number
invitee_name         VARCHAR(255) NULL             -- pre-filled company name (optional)
recommended_plan     VARCHAR(20) NULL              -- 'basique' | 'essentiel' | 'entreprise'
status               VARCHAR(20) DEFAULT 'pending' -- 'pending' | 'accepted' | 'expired'
expires_at           TIMESTAMP                     -- 30 days from creation
accepted_at          TIMESTAMP NULL
sme_company_id       CHAR(26) NULL FK → companies.id  -- filled on acceptance
created_at, updated_at
```

The `InvitationService` handles both the creation of the invitation and the
post-registration auto-link. The `AuthService` calls
`InvitationService::linkIfPendingInvitation($user, $sessionToken)` inside the
OTP verification transaction.

### 5.5 Ownership Chain Per Resource

| Resource | Owned by | FK |
|---|---|---|
| `invoices` | company (SME) | `company_id → companies.id` |
| `invoice_lines` | invoice | `invoice_id → invoices.id` |
| `quotes` | company (SME) | `company_id → companies.id` |
| `clients` | company (SME) | `company_id → companies.id` |
| `reminders` | invoice | `invoice_id → invoices.id` |
| `reminder_rules` | company (SME) | `company_id → companies.id` |
| `subscriptions` | company (SME) | `company_id → companies.id` |
| `quota_usage` | company (SME) | `company_id → companies.id` |
| `addon_purchases` | company (SME) | `company_id → companies.id` |
| `partner_invitations` | company (firm) | `accountant_firm_id → companies.id` |
| `commissions` | company (firm) | `accountant_firm_id → companies.id` |

The `reminders` table must store the chosen channel on every row:

```sql
-- reminders (key columns — not exhaustive)
id              CHAR(26) PRIMARY KEY
invoice_id      CHAR(26) FK → invoices.id
channel         VARCHAR(10)   -- 'whatsapp' | 'sms' | 'email'
status          VARCHAR(20)   -- 'pending' | 'sent' | 'delivered' | 'failed'
sent_at         TIMESTAMP NULL
message_body    TEXT
recipient_phone VARCHAR(20) NULL   -- used for whatsapp + sms
recipient_email VARCHAR(255) NULL  -- used for email
created_at, updated_at
```

`recipient_phone` and `recipient_email` are snapshotted at send time from the
client record — not joined at query time. This preserves the historical record
even if the client's contact info changes later.

### 5.6 How the Accountant Sees SME Data

The accountant's cockpit reads data from SME companies linked via
`accountant_company`. The `PortfolioService` always gates this through the
active relationship:

```php
// Get all SMEs currently accessible by this accountant firm
// (includes all active relationships — ended_at IS NULL)
$smeIds = AccountantCompany::query()
    ->where('accountant_firm_id', $firm->id)
    ->whereNull('ended_at')
    ->pluck('sme_company_id');

// Query invoices across those SMEs
$invoices = Invoice::query()
    ->whereIn('company_id', $smeIds)
    ->get();
```

An accountant never calls `Invoice::find($id)` directly without this gate.
It is enforced in `InvoicePolicy` by checking that an active `accountant_company`
row exists for that firm+SME pair (regardless of how many other active accountants
the SME also has).

---

## 7. Authorization: Laravel Policies

There is no global scope trait. Authorization is explicit, per-model, via
Laravel Policies. **Every model that a user can read or write must have a Policy.**

### 6.1 Policy Registration

Register all policies in `app/Providers/AppServiceProvider.php`:

```php
protected $policies = [
    Invoice::class        => InvoicePolicy::class,
    Quote::class          => QuotePolicy::class,
    Client::class         => ClientPolicy::class,
    Reminder::class       => ReminderPolicy::class,
    ReminderRule::class   => ReminderRulePolicy::class,
    Commission::class     => CommissionPolicy::class,
    PartnerInvitation::class => PartnerInvitationPolicy::class,
];
```

### 6.2 Policy Pattern — SME Resource

```php
// app/Policies/PME/InvoicePolicy.php
class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        // Case 1: user belongs to the owning SME company
        if ($user->companies()->where('companies.id', $invoice->company_id)->exists()) {
            return true;
        }

        // Case 2: user belongs to an accountant firm that actively manages this SME
        $userFirmIds = $user->companies()
            ->where('type', 'accountant_firm')
            ->pluck('companies.id');

        return AccountantCompany::query()
            ->whereIn('accountant_firm_id', $userFirmIds)
            ->where('sme_company_id', $invoice->company_id)
            ->whereNull('ended_at')
            ->exists();
    }

    public function create(User $user): bool
    {
        // Only SME users can create invoices
        return $user->companies()->where('type', 'sme')->exists();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        // Only the owning SME can modify an invoice
        return $user->companies()->where('companies.id', $invoice->company_id)->exists();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }
}
```

### 6.3 Controller Pattern

```php
// CORRECT — always use $this->authorize()
class InvoiceController extends Controller
{
    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);
        return view('invoicing::show', compact('invoice'));
    }

    public function store(StoreInvoiceRequest $request, InvoiceService $service): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $invoice = $service->create($request->validated());
        return redirect()->route('pme.invoices.show', $invoice);
    }
}

// WRONG — never query without authorization
class InvoiceController extends Controller
{
    public function show(string $id): View
    {
        $invoice = Invoice::findOrFail($id); // no authorization check — data leak risk
        return view('invoicing::show', compact('invoice'));
    }
}
```

---

## 8. Pricing, Plans, Quotas and Add-ons

### 7.1 Plans (SME only)

Fayeku Compta is **free** for accountants. Plans apply to SME companies only.

| | Basique | Essentiel | Entreprise |
|---|---|---|---|
| Price | 10 000 FCFA/month | 20 000 FCFA/month | Custom (quote) |
| Annual | 100 000 FCFA/year | 200 000 FCFA/year | Custom |
| Trial | 2 months free | 2 months free | 2 months free |
| Invoices | Unlimited | Unlimited | Unlimited |
| WhatsApp reminders/month | 20 | Unlimited | Unlimited |
| Users | 2 | Unlimited | Unlimited |
| Clients | Unlimited | Unlimited | Unlimited |
| Fayeku Compta access | Activatable | Full | Full |
| AI reminders | — | Yes | Yes |
| Client reliability score | — | Yes | Yes |
| 90-day treasury forecast | — | Yes | Yes |
| Quote → signature → invoice | — | Yes | Yes |
| Recurring invoices | — | Yes | Yes |
| Document vault | — | Yes | Yes |
| WhatsApp support < 4h | — | Yes | Yes |
| DGID compliance (SN) | — | Yes (when DGID API published) | Yes |
| FNE compliance (CI) | Yes (live) | Yes (live) | Yes (live) |
| Multi-entity | — | — | Yes |
| API + SSO | — | — | Yes |
| Internal validation workflows | — | — | Yes |
| Dedicated support + SLA | — | — | Yes |
| Custom onboarding | — | — | Yes |
| Entreprise limits | — | — | TBD (not decided) |

> All prices are stored as integers in FCFA. Monthly price for Basique = `10000`,
> Essentiel = `20000`. Annual prices are stored separately (not computed at runtime).

### 7.2 Add-ons (credit-based, one-time purchase)

Add-ons are **credits bought once** — not recurring subscriptions.
They do not expire. They stack on top of the plan's included quota.

| Add-on type | Unit | Behaviour |
|---|---|---|
| WhatsApp reminders | N extra reminders | Added to the monthly quota for the current billing period |
| Extra users | N extra seats | Added to the company's user cap permanently until cancelled |
| Extra clients | N extra client records | Added to the company's client cap |
| Extra document storage | N GB | Added to the storage quota |

> For the Essentiel and Entreprise plans where reminders are already unlimited,
> reminder add-ons are irrelevant and must not be purchasable. The `QuotaService`
> must check the plan before allowing an add-on purchase.

### 7.3 Quota Enforcement: Hard Blocking

When a company has exhausted a quota (plan allowance + purchased add-ons),
the action is **blocked**. The user sees an upgrade/purchase prompt. No
silent overages, no automatic end-of-month billing for overages.

Quota types subject to hard blocking:

| Quota | Resets | Add-on available |
|---|---|---|
| WhatsApp reminders | Monthly (1st of each month) | Yes |
| Users (seats) | Never — cap until add-on bought | Yes |
| Client records | Never — cap until add-on bought | Yes |
| Document storage | Never — cap until add-on bought | Yes |

**Monthly reset rule:** The plan's included reminder quota resets to the plan
default on the 1st of each month. Purchased add-on credits do **not** reset —
they carry over. Example: Basique has 20 included reminders/month. If the company
buys 10 extra credits, they have 30 for the current month. On the 1st, the included
20 resets but the 10 purchased credits remain.

### 7.4 Database Schema — Plans, Subscriptions, Quotas, Add-ons

```sql
-- plan_definitions
-- Static reference table. Seeded, not user-editable.
id                      CHAR(26) PRIMARY KEY
slug                    VARCHAR(20) UNIQUE     -- 'basique' | 'essentiel' | 'entreprise'
name                    VARCHAR(100)
price_monthly           INTEGER                -- FCFA, 0 for entreprise
price_annual            INTEGER                -- FCFA, 0 for entreprise
is_custom_pricing       BOOLEAN DEFAULT FALSE  -- TRUE for entreprise
trial_days              SMALLINT DEFAULT 60
reminders_per_month     INTEGER                -- -1 = unlimited
max_users               INTEGER                -- -1 = unlimited
max_clients             INTEGER                -- -1 = unlimited
max_storage_mb          INTEGER                -- -1 = unlimited
created_at, updated_at

-- subscriptions
-- One active subscription per SME company at a time.
id                      CHAR(26) PRIMARY KEY
company_id              CHAR(26) FK → companies.id
plan_slug               VARCHAR(20)            -- denormalised for query speed
price_paid              INTEGER                -- FCFA — 0 during trial
billing_cycle           VARCHAR(10)            -- 'monthly' | 'annual' | 'trial'
status                  VARCHAR(20)            -- 'trial' | 'active' | 'past_due' | 'cancelled'
trial_ends_at           TIMESTAMP NULL
current_period_start    TIMESTAMP
current_period_end      TIMESTAMP
cancelled_at            TIMESTAMP NULL
invited_by_firm_id      CHAR(26) NULL FK → companies.id  -- accountant who referred this SME
created_at, updated_at

-- quota_usage
-- Tracks consumed quota per company per billing period.
-- One row per company per quota_type per period_start.
-- Reset rows are created automatically on period rollover for monthly quotas.
id                      CHAR(26) PRIMARY KEY
company_id              CHAR(26) FK → companies.id
quota_type              VARCHAR(30)            -- 'reminders' | 'users' | 'clients' | 'storage_mb'
period_start            DATE                   -- 1st of month for monthly quotas, NULL for permanent
used                    INTEGER DEFAULT 0
created_at, updated_at
UNIQUE (company_id, quota_type, period_start)

-- addon_purchases
-- One row per purchase event. Credits are summed across all non-expired rows.
id                      CHAR(26) PRIMARY KEY
company_id              CHAR(26) FK → companies.id
addon_type              VARCHAR(30)            -- 'reminders' | 'users' | 'clients' | 'storage_mb'
credits_purchased       INTEGER                -- number of units bought
credits_remaining       INTEGER                -- decremented as used
price_paid              INTEGER                -- FCFA total for this purchase
purchased_at            TIMESTAMP
expires_at              TIMESTAMP NULL         -- NULL = never expires
created_at, updated_at
```

### 7.5 QuotaService — the single source of truth for limits

All quota checks and consumption **must go through `QuotaService`**.
No controller or Livewire component checks quotas directly.

```php
// app/Services/Shared/QuotaService.php

class QuotaService
{
    /**
     * Check whether the company can perform an action of the given type.
     * Throws QuotaExceededException if blocked.
     */
    public function authorize(Company $company, string $quotaType, int $amount = 1): void
    {
        if ($this->isUnlimited($company, $quotaType)) {
            return;
        }

        $available = $this->available($company, $quotaType);

        if ($available < $amount) {
            throw new QuotaExceededException($quotaType, $available);
        }
    }

    /**
     * Consume quota after a successful action.
     * Always call this AFTER the action succeeds, inside a DB transaction.
     */
    public function consume(Company $company, string $quotaType, int $amount = 1): void
    {
        // increments quota_usage.used for current period
    }

    /**
     * Returns total available = (plan allowance - used) + addon credits remaining.
     */
    public function available(Company $company, string $quotaType): int
    {
        // plan allowance for current period - used + sum of addon credits_remaining
    }

    public function isUnlimited(Company $company, string $quotaType): bool
    {
        // returns true if plan has -1 for this quota type
    }
}
```

**Usage pattern in a service:**

```php
// app/Services/PME/ReminderService.php

/**
 * Send a reminder via the chosen channel.
 * Channel is chosen by the PME at send time — per facture, not per client.
 * Quota is shared across all channels (20/month total on Basique, not per channel).
 */
public function send(Invoice $invoice, Company $company, ReminderChannel $channel): Reminder
{
    // 1. Check total quota BEFORE doing anything — channel is irrelevant to the count
    $this->quotaService->authorize($company, 'reminders');

    return DB::transaction(function () use ($invoice, $company, $channel) {
        // 2. Resolve the correct channel service and send
        $channelService = $this->resolveChannel($channel);
        $reminder = $channelService->send($invoice);

        // 3. Consume quota AFTER success, inside the transaction
        $this->quotaService->consume($company, 'reminders');

        return $reminder;
    });
}

/**
 * Resolve the correct channel implementation.
 * All three implement ReminderChannelInterface.
 */
private function resolveChannel(ReminderChannel $channel): ReminderChannelInterface
{
    return match ($channel) {
        ReminderChannel::WhatsApp => $this->whatsAppReminderService,
        ReminderChannel::Sms      => $this->smsReminderService,
        ReminderChannel::Email    => $this->emailReminderService,
    };
}
```

The `ReminderChannelInterface` defines the contract:

```php
// app/Interfaces/PME/ReminderChannelInterface.php
interface ReminderChannelInterface
{
    public function send(Invoice $invoice): Reminder;
}
```

### 7.6 Quota Types Enum

```php
// app/Enums/Shared/QuotaType.php
enum QuotaType: string
{
    case Reminders  = 'reminders';
    case Users      = 'users';
    case Clients    = 'clients';
    case StorageMb  = 'storage_mb';
}
```

### 7.7 Plan Seeder

`plan_definitions` is seeded once at setup and never modified by application code.
Create a `PlanDefinitionSeeder` in `database/seeders/`.

```php
// Basique
[
    'slug'               => 'basique',
    'price_monthly'      => 10000,
    'price_annual'       => 100000,
    'trial_days'         => 60,
    'reminders_per_month'=> 20,
    'max_users'          => 2,
    'max_clients'        => -1,   // unlimited
    'max_storage_mb'     => -1,   // TBD — set when decided
    'is_custom_pricing'  => false,
],
// Essentiel
[
    'slug'               => 'essentiel',
    'price_monthly'      => 20000,
    'price_annual'       => 200000,
    'trial_days'         => 60,
    'reminders_per_month'=> -1,   // unlimited
    'max_users'          => -1,   // unlimited
    'max_clients'        => -1,   // unlimited
    'max_storage_mb'     => -1,   // TBD
    'is_custom_pricing'  => false,
],
// Entreprise
[
    'slug'               => 'entreprise',
    'price_monthly'      => 0,    // custom — negotiated
    'price_annual'       => 0,
    'trial_days'         => 60,
    'reminders_per_month'=> -1,
    'max_users'          => -1,
    'max_clients'        => -1,
    'max_storage_mb'     => -1,
    'is_custom_pricing'  => true,
],
```

### 7.8 Monthly Quota Reset

A scheduled job runs on the 1st of each month and resets `quota_usage` rows
for `quota_type = 'reminders'` by creating a new row for the new `period_start`.
It does not touch `addon_purchases.credits_remaining` — those carry over.

```php
// app/Jobs/Shared/ResetMonthlyQuotasJob.php
// Scheduled in AppServiceProvider: Schedule::job(ResetMonthlyQuotasJob::class)->monthly();
```

---

## 9. Fiscal Compliance Integrations

### 8.1 Ivory Coast — FNE (Facture Normalisée Electronique) — LIVE

The FNE is **mandatory and active** in Côte d'Ivoire. All invoices emitted by
Ivorian companies on Fayeku must be certified via the FNE API before being
delivered to the client. This is not optional.

**Key facts from the official API documentation:**

| Property | Value |
|---|---|
| Authority | Direction Générale des Impôts (DGI) — Côte d'Ivoire |
| Portal | `fne.dgi.gouv.ci` |
| API style | RESTful, JSON, HTTP POST |
| Authentication | Bearer token (JWT) — API key from the FNE portal settings |
| Test environment | `http://54.247.95.108/ws` |
| Production URL | Provided by DGI after integration validation |
| Support email | `support.fne@dgi.gouv.ci` |
| Invoice types certified | Sale invoice, credit note (`avoir`), agricultural purchase order |
| Certification output | `reference` (invoice number), `token` (QR code URL), `balance_sticker` |

**Integration onboarding steps (one-time, done by Fayeku as the software editor):**

1. Register Fayeku on the FNE test environment at `http://54.247.95.108`
2. Develop and test the API integration
3. Generate specimen invoices and send to `support.fne@dgi.gouv.ci`
4. DGI validates conformity and provides the production URL
5. Production API key is visible in the FNE portal under "Paramétrage"

**What Fayeku stores after certification:**

The `invoices` table must have these FNE-specific columns for CI companies:

```sql
-- Added to invoices table (nullable — only populated for CI companies)
fne_reference       VARCHAR(50) NULL    -- e.g. "9606123E25000000019"
fne_token           VARCHAR(255) NULL   -- QR code verification URL
fne_certified_at    TIMESTAMP NULL      -- when certification succeeded
fne_balance_sticker INTEGER NULL        -- remaining sticker balance (informational)
fne_raw_response    JSONB NULL          -- full API response snapshot for audit
```

**The certification flow per invoice:**

```
1. SME creates invoice in Fayeku (status: 'draft')
2. SME clicks "Émettre la facture"
3. InvoiceService calls ComplianceService::certify($invoice)
4. ComplianceService resolves FneConnector (country = 'CI')
5. FneConnector maps invoice → FneInvoicePayload DTO
6. POST to FNE API: $url/external/invoices/sign
7. On success (HTTP 200):
   - Store fne_reference, fne_token, fne_certified_at on invoice
   - Set invoice status: 'certified'
8. On failure:
   - Log error, set invoice status: 'certification_failed'
   - Surface error to SME with actionable message
9. Certified invoice PDF includes QR code from fne_token
```

**`FiscalConnectorInterface` contract:**

```php
// app/Interfaces/Compta/FiscalConnectorInterface.php
interface FiscalConnectorInterface
{
    /**
     * Certify an invoice with the fiscal authority.
     * Returns a FiscalCertification value object on success.
     * Throws FiscalCertificationException on failure.
     */
    public function certify(Invoice $invoice): FiscalCertification;

    /**
     * Whether this connector is active for the given company country.
     */
    public function supportsCountry(string $countryCode): bool;
}
```

**`FneConnector` key mapping (Fayeku → FNE API):**

```php
// app/Services/Compta/FNEFiscalConnector.php
// Maps a Fayeku Invoice model to the FNE API request payload.

[
    'invoiceType'       => 'sale',                          // always 'sale' for standard invoices
    'paymentMethod'     => $this->mapPaymentMethod($invoice),
    'template'          => 'B2B',                           // B2B | B2C | B2G | B2F
    'clientNcc'         => $client->tax_id ?? null,         // required for B2B
    'clientCompanyName' => $client->name,
    'clientPhone'       => $client->phone,
    'clientEmail'       => $client->email,
    'pointOfSale'       => $company->name,
    'establishment'     => $company->name,
    'items'             => $this->mapLines($invoice->lines),
    // items per line:
    // 'taxes'       => ['TVA']   — TVA type (TVA | TVAB | TVAC | TVAD)
    // 'description' => line description
    // 'quantity'    => line quantity
    // 'amount'      => unit price HT (integer FCFA)
    // 'discount'    => line discount % (optional)
]
```

**VAT type mapping for FNE:**

| FNE tax code | Meaning | When to use |
|---|---|---|
| `TVA` | Standard VAT 18% | Default for most goods/services |
| `TVAB` | VAT reduced rate B | Specific goods — check CGI |
| `TVAC` | VAT exempt (convention) | Exempt transactions |
| `TVAD` | VAT reduced rate D | Specific goods — check CGI |

**`DgidConnector` (Senegal) — stub only:**

```php
// app/Services/Compta/DGIDConnector.php
class DgidConnector implements FiscalConnectorInterface
{
    public function certify(Invoice $invoice): FiscalCertification
    {
        // DGID API not yet published. This method must throw a clear exception
        // rather than silently failing.
        throw new \RuntimeException(
            'DGID API not yet available. Certification skipped for SN invoices.'
        );
    }

    public function supportsCountry(string $countryCode): bool
    {
        return $countryCode === 'SN';
    }
}
```

Do not attempt to call any DGID endpoint until the official API is published and
this file is updated. The stub exists so the architecture is ready.

**`ComplianceService` — country routing:**

```php
// app/Services/Compta/ComplianceService.php
class ComplianceService
{
    /** @param FiscalConnectorInterface[] $connectors */
    public function __construct(private array $connectors) {}

    public function certify(Invoice $invoice): void
    {
        $countryCode = $invoice->company->country_code; // 'SN' | 'CI'

        $connector = collect($this->connectors)
            ->first(fn($c) => $c->supportsCountry($countryCode));

        if (! $connector) {
            // No connector for this country — skip silently for now
            return;
        }

        $certification = $connector->certify($invoice);

        $invoice->update([
            'fne_reference'       => $certification->reference,
            'fne_token'           => $certification->token,
            'fne_certified_at'    => now(),
            'fne_balance_sticker' => $certification->balanceSticker,
            'fne_raw_response'    => $certification->rawResponse,
            'status'              => 'certified',
        ]);
    }
}
```

### 8.2 Senegal — DGID — Pending

The DGID electronic invoicing obligation is legislated (2025 Finance Law) but the
technical API specifications have not been published as of the current date.

**What to do now:**

- The `DgidConnector` stub is in place — the architecture is ready.
- The `invoices` table should reserve nullable columns for DGID certification
  fields (same pattern as FNE: `dgid_reference`, `dgid_token`, `dgid_certified_at`).
- When the DGID API is published, update this file with the real endpoint,
  auth method, and payload mapping before writing any code.

**What NOT to do:**

- Do not invent or guess DGID API endpoints.
- Do not reuse FNE endpoints for Senegalese companies.
- Do not make DGID certification block invoice emission — it must degrade
  gracefully until the API is live.

### 8.3 Environment Variables for Compliance

```dotenv
# Ivory Coast FNE — live
FNE_API_KEY=                          # Bearer token from FNE portal settings
FNE_API_URL=                          # Production URL provided by DGI after validation
FNE_TEST_URL=http://54.247.95.108/ws  # Test environment

# Senegal DGID — not yet available
# DGID_API_KEY=
# DGID_API_URL=
```

---

## 10. Primary Keys: ULID

All tables use ULID as primary key. Not UUID. Not auto-increment integers.

ULIDs are 26-character uppercase strings, lexicographically sortable, performant
on PostgreSQL indexes.

```php
// app/Traits/Shared/HasUlid.php
namespace App\Traits\Shared;

use Illuminate\Support\Str;

trait HasUlid
{
    public static function bootHasUlid(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::ulid();
            }
        });
    }

    public function getIncrementing(): bool { return false; }
    public function getKeyType(): string { return 'string'; }
}
```

Migration column types:

```php
// Primary key
$table->char('id', 26)->primary();

// Foreign key to any other table
$table->char('company_id', 26);
$table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
```

---

## 11. Authentication

### 8.1 Registration Flow (Web and Mobile)

```
1. User selects country: Senegal (+221) or Ivory Coast (+225)
2. User enters: phone number, password, password confirmation,
   company name, profile type (sme | accountant)
3. System normalises phone to E.164 format
4. System rejects if phone already registered
5. User record is created (unverified)
6. Company record is created (type based on profile_type)
7. company_user pivot row is created (role = 'owner')
8. OTP: 6 digits, sha256 hashed, stored in otp_codes, sent via SMS
9. User redirected to OTP verification screen
10. User enters OTP:
    - Valid + not expired + attempts < 3 → phone_verified_at set, logged in
    - Invalid → increment attempts; invalidate after 3rd failure
    - Expired → prompt to request a new OTP
11. On success: redirect to onboarding or dashboard
```

### 8.2 Login Flow (Web and Mobile)

```
1. User selects country code
2. User enters phone number + password
3. System validates credentials — no OTP
4. On success:
   - Web:        session started
                 profile_type = sme        → /pme/dashboard
                 profile_type = accountant → /compta/dashboard
   - Mobile API: Sanctum token returned in JSON body
```

### 8.3 OTP Rules

| Rule | Value |
|---|---|
| Code length | 6 digits |
| Storage | sha256 hashed — never stored or logged in plain text |
| Expiry | 10 minutes from creation |
| Max attempts | 3 — invalidated after third failure |
| Resend | Creates a new `otp_codes` row after expiry or invalidation |

### 8.4 Phone Number Rules

| Rule | Detail |
|---|---|
| Stored format | E.164 — `+221771234567` |
| User input | Digits only; system prepends country prefix |
| Uniqueness | `UNIQUE` constraint on `users.phone` |
| Normalisation | Strip spaces, dashes, parentheses before storing |

### 8.5 Auth Tables

```sql
-- otp_codes
id          CHAR(26) PRIMARY KEY
phone       VARCHAR(20)
code        CHAR(64)               -- sha256 hex hash
expires_at  TIMESTAMP
attempts    SMALLINT DEFAULT 0
used_at     TIMESTAMP NULL
created_at, updated_at
```

---

## 12. Routing

### 9.1 Route Prefixes

| Module / submodule | Web prefix | API prefix |
|---|---|---|
| Auth | `/` | `/api/auth` |
| PME — dashboard | `/pme` | `/api/pme` |
| PME — Invoicing | `/pme/invoices`, `/pme/quotes` | `/api/pme/invoices`, `/api/pme/quotes` |
| PME — Clients | `/pme/clients` | `/api/pme/clients` |
| PME — Collection | `/pme/collection` | `/api/pme/collection` |
| PME — Treasury | `/pme/treasury` | `/api/pme/treasury` |
| Compta — dashboard | `/compta` | `/api/compta` |
| Compta — Portfolio | `/compta/portfolio` | `/api/compta/portfolio` |
| Compta — Export | `/compta/export` | `/api/compta/export` |
| Compta — Partnership | `/compta/partnership` | `/api/compta/partnership` |
| Compta — Compliance | `/compta/compliance` | `/api/compta/compliance` |

### 9.2 Middleware Stack

```php
// Web — authenticated SME
Route::middleware(['web', 'auth', 'verified.phone', 'profile:sme'])
    ->prefix('pme')
    ->group(...);

// Web — authenticated accountant
Route::middleware(['web', 'auth', 'verified.phone', 'profile:accountant'])
    ->prefix('compta')
    ->group(...);

// Mobile API — Sanctum
Route::middleware(['api', 'auth:sanctum', 'verified.phone'])
    ->prefix('api/pme')
    ->group(...);
```

### 9.3 Middleware Aliases

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'profile'        => \App\Middleware\EnsureProfileType::class,
        'verified.phone' => \App\Middleware\EnsurePhoneVerified::class,
    ]);
})
```

---

## 13. Naming Conventions

### 10.1 PHP Classes

| Type | Convention | Example |
|---|---|---|
| Classes | PascalCase | `InvoiceService` |
| Methods | camelCase | `generatePdf()` |
| Variables | camelCase | `$companyId` |
| Constants | SCREAMING_SNAKE_CASE | `MAX_OTP_ATTEMPTS` |
| Interfaces | PascalCase + `Interface` | `SmsProviderInterface` |
| Traits | PascalCase, descriptive | `HasUlid` |
| Enums | PascalCase | `InvoiceStatus`, `PartnerTier` |
| Enum cases | PascalCase | `InvoiceStatus::Paid` |
| Events | Noun + past-tense verb | `InvoiceCreated` |
| Listeners | Action on reaction | `NotifyAccountantOnNewInvoice` |
| Jobs | Action + `Job` | `SendReminderJob`, `GenerateExportJob` |
| Policies | Model + `Policy` | `InvoicePolicy` |
| Form Requests | Action + resource + `Request` | `StoreInvoiceRequest` |

### 10.2 Database

| Type | Convention | Example |
|---|---|---|
| Tables | snake_case, plural | `invoice_lines`, `accountant_company` |
| Pivot tables | both model names, alphabetical | `accountant_company`, `company_user` |
| Columns | snake_case | `company_id`, `phone_verified_at` |
| Primary key | `id` CHAR(26) ULID | — |
| Foreign keys | `{singular_model}_id` | `company_id`, `invoice_id` |
| Booleans | `is_` or `has_` prefix | `is_active` |
| Timestamps | Laravel defaults | `created_at`, `updated_at` |
| Soft deletes | `deleted_at` | `deleted_at TIMESTAMP NULL` |
| Status columns | `VARCHAR` + PHP Enum validation | `status VARCHAR(30)` |
| Money columns | clear noun, integer | `subtotal`, `tax_amount`, `total` |

### 10.3 Livewire Components

| Type | Convention | Example |
|---|---|---|
| Class | PascalCase noun | `InvoiceForm`, `CockpitDashboard` |
| Blade tag | kebab-case, module-prefixed | `<livewire:invoicing.invoice-form />` |
| Properties | camelCase | `$companyId`, `$showModal` |
| Actions | camelCase verb | `saveInvoice()`, `deleteClient()` |

### 10.4 Blade Views

```
resources/views/pages/pme/invoices/
├── index.blade.php
├── create.blade.php
├── show.blade.php
└── partials/
    └── status-badge.blade.php

// Usage:
return view('invoicing::index');
return view('invoicing::partials.status-badge');
```

---

## 14. Model Rules

1. Every model uses `HasUlid`.
2. Always define `$fillable` explicitly. `$guarded = []` is forbidden.
3. Always cast dates, booleans, and JSON in `$casts`.
4. PHP 8.1 backed enums for all status/type columns — stored as `VARCHAR` in DB.
5. Money columns are always cast to `int`. Float and decimal are forbidden.
6. Relationships are always typed methods with explicit return types.
7. Use `SoftDeletes` on user-created documents (invoices, quotes, clients, reminders).
8. No model ever queries across company boundaries without going through a Policy
   or through the explicit `accountant_company` relationship.

```php
// Canonical model — app/Models/PME/Invoice.php

namespace App\Models\PME;

use App\Traits\Shared\HasUlid;
use App\Enums\PME\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasUlid, SoftDeletes;

    protected $fillable = [
        'company_id',
        'client_id',
        'reference',
        'status',
        'issued_at',
        'due_at',
        'subtotal',      // integer — whole FCFA
        'tax_amount',    // integer — whole FCFA
        'total',         // integer — whole FCFA
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'issued_at'  => 'date',
        'due_at'     => 'date',
        'paid_at'    => 'datetime',
        'subtotal'   => 'integer',
        'tax_amount' => 'integer',
        'total'      => 'integer',
        'status'     => InvoiceStatus::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Auth\Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Models\PME\Client::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
}
```

---

## 15. Service Layer Rules

- **Controllers are thin.** Validate → authorize → call one service method → return response.
- **All business logic lives in Service classes.** No Eloquent queries in controllers.
- **Services are injected** via the service container. Never `new ServiceClass()`.
- **Services may call other services** within the same module or from `Shared`.
  They must never directly query models from a different module — use that
  module's public Service or listen to its Events.
- Cross-module **data reads**: call the target module's public Service method.
- Cross-module **side effects**: dispatch an Event.

```php
// CORRECT
class InvoiceController extends Controller
{
    public function store(StoreInvoiceRequest $request, InvoiceService $service): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $invoice = $service->create($request->validated());
        return redirect()->route('pme.invoices.show', $invoice);
    }
}
```

---

## 16. Livewire Component Rules

This project uses **Livewire v4** with **Flux UI v2** (`livewire/flux`).
Activate the `livewire-development` and `fluxui-development` skills before working
on any component.

- Every component has a Blade view in the submodule's `resources/views/`.
- Use `#[Validate]` for inline property validation.
- Use `#[Locked]` on every server-originated property that must not be client-writable.
- Never query the database in a Blade view. Use computed properties or `render()`.
- Call `$this->authorize()` inside action methods that write data.
- `wire:loading` on every submit and destructive action button.
- Use Alpine.js for client-side interactions — never raw JavaScript.
- Use `<flux:*>` components for UI elements (buttons, inputs, modals, tables, badges).
  Activate `fluxui-development` skill before using any Flux component.
- State must be server-side. Validate and authorize in actions as you would in HTTP requests.

```php
// app/Livewire/PME/InvoiceForm.php

namespace App\Livewire\PME;

use App\Models\PME\Invoice;
use App\Services\PME\InvoiceService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class InvoiceForm extends Component
{
    #[Locked]
    public ?string $invoiceId = null;

    #[Locked]
    public string $companyId = '';

    #[Validate('required|string|size:26|exists:clients,id')]
    public string $clientId = '';

    #[Validate('required|date|after:today')]
    public string $dueAt = '';

    #[Validate('required|array|min:1')]
    public array $lines = [];

    public function save(InvoiceService $service): void
    {
        $this->authorize('create', Invoice::class);
        $this->validate();

        $service->create([
            'company_id' => $this->companyId,
            'client_id'  => $this->clientId,
            'due_at'     => $this->dueAt,
            'lines'      => $this->lines,
        ]);

        $this->redirect(route('pme.invoices.index'), navigate: true);
    }

    public function render(): \Illuminate\View\View
    {
        return view('invoicing::livewire.invoice-form');
    }
}
```

---

## 17. Testing Rules

- All tests use **Pest PHP v4** syntax (`it()`, `expect()`). No raw PHPUnit verbosity.
- Create tests with `php artisan make:test --pest {name}` (feature) or `--pest --unit` (unit).
- Run tests with `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Most tests should be **feature tests**. Use unit tests only for isolated service logic.
- Feature tests cover full HTTP request → response cycles including auth and policies.
- Every test file uses `uses(RefreshDatabase::class)`.
- Every model must have a factory. Use factory states before manually setting attributes.
- Every service method must have at least one test.
- Every route must have at least one feature test.
- **Do NOT delete tests without approval.**
- **Quota enforcement must be explicitly tested** for every quota-gated action:
  success within quota, blocked when exhausted, add-on credits extend the limit.
- **Monthly reset must be tested**: after `ResetMonthlyQuotasJob` runs, included
  quota is restored to the plan default and add-on credits are unchanged.
- **Ownership isolation must be explicitly tested:** user A must never read or
  write data belonging to company B.
- **Accountant access must be explicitly tested:** active link → access granted,
  ended link → access denied, multiple active accountants → all have access.

```php
uses(RefreshDatabase::class);

it('registers an SME user, creates company, and sends OTP', function () {
    $this->postJson('/api/auth/register', [
        'first_name'            => 'Moussa',
        'last_name'             => 'Diallo',
        'phone'                 => '771234567',
        'country_code'          => 'SN',
        'password'              => 'secret1234',
        'password_confirmation' => 'secret1234',
        'company_name'          => 'Sow BTP SARL',
        'profile_type'          => 'sme',
    ])->assertCreated();

    expect(\App\Models\Shared\User::first()->phone)->toBe('+221771234567');
    $this->assertDatabaseHas('companies', ['name' => 'Sow BTP SARL', 'type' => 'sme']);
    $this->assertDatabaseHas('otp_codes', ['phone' => '+221771234567']);
});

it('prevents a user from viewing another company invoice', function () {
    $companyA = Company::factory()->sme()->create();
    $companyB = Company::factory()->sme()->create();
    $userA    = User::factory()->for($companyA)->create();
    $invoice  = Invoice::factory()->for($companyB)->create();

    $this->actingAs($userA)
        ->getJson("/api/pme/invoices/{$invoice->id}")
        ->assertForbidden();
});

it('allows an accountant to view invoices of an actively linked SME', function () {
    $firm    = Company::factory()->accountantFirm()->create();
    $sme     = Company::factory()->sme()->create();
    $accUser = User::factory()->for($firm)->create();

    AccountantCompany::factory()->create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id'     => $sme->id,
        'started_at'         => now(),
        'ended_at'           => null,
    ]);

    $invoice = Invoice::factory()->for($sme)->create();

    $this->actingAs($accUser)
        ->getJson("/api/compta/portfolio/invoices/{$invoice->id}")
        ->assertOk();
});

it('allows multiple active accountants to access the same SME simultaneously', function () {
    $firmA   = Company::factory()->accountantFirm()->create();
    $firmB   = Company::factory()->accountantFirm()->create();
    $sme     = Company::factory()->sme()->create();
    $userA   = User::factory()->for($firmA)->create();
    $userB   = User::factory()->for($firmB)->create();

    AccountantCompany::factory()->create([
        'accountant_firm_id' => $firmA->id,
        'sme_company_id'     => $sme->id,
        'started_at'         => now(),
        'ended_at'           => null,
    ]);
    AccountantCompany::factory()->create([
        'accountant_firm_id' => $firmB->id,
        'sme_company_id'     => $sme->id,
        'started_at'         => now(),
        'ended_at'           => null,
    ]);

    $invoice = Invoice::factory()->for($sme)->create();

    $this->actingAs($userA)->getJson("/api/compta/portfolio/invoices/{$invoice->id}")->assertOk();
    $this->actingAs($userB)->getJson("/api/compta/portfolio/invoices/{$invoice->id}")->assertOk();
});

it('blocks an accountant from viewing invoices of a former client', function () {
    $firm    = Company::factory()->accountantFirm()->create();
    $sme     = Company::factory()->sme()->create();
    $accUser = User::factory()->for($firm)->create();

    AccountantCompany::factory()->create([
        'accountant_firm_id' => $firm->id,
        'sme_company_id'     => $sme->id,
        'started_at'         => now()->subYear(),
        'ended_at'           => now()->subMonth(), // relationship ended
    ]);

    $invoice = Invoice::factory()->for($sme)->create();

    $this->actingAs($accUser)
        ->getJson("/api/compta/portfolio/invoices/{$invoice->id}")
        ->assertForbidden();
});
```

---

## 18. Environment Variables

```dotenv
APP_NAME=Fayeku
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://fayeku.test

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=fayeku
DB_USERNAME=fayeku
DB_PASSWORD=

CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

OTP_EXPIRY_MINUTES=10
OTP_MAX_ATTEMPTS=3

# SMS — abstracted. Values: orange | twilio | infobip
SMS_PROVIDER=orange
ORANGE_SMS_API_KEY=
ORANGE_SMS_SENDER_ID=Fayeku

# WhatsApp — abstracted. Values: 360dialog | meta | twilio_whatsapp
WHATSAPP_PROVIDER=360dialog
WHATSAPP_API_KEY=
WHATSAPP_PHONE_NUMBER_ID=

FILESYSTEM_DISK=local

SANCTUM_STATEFUL_DOMAINS=localhost,fayeku.test

# Ivory Coast FNE — live integration
FNE_API_KEY=
FNE_API_URL=
FNE_TEST_URL=http://54.247.95.108/ws

# Senegal DGID — not yet available, uncomment when API is published
# DGID_API_KEY=
# DGID_API_URL=
```

---

## 19. Mandatory Rules for Agents

### Before writing any code

1. Activate the relevant **skill** from section 3.2 for the domain you are working in.
2. Use `search-docs` to look up the correct approach before writing any framework code.
3. Identify the correct module and submodule. Verify the path follows section 4.3.
4. If the code touches resources across two companies, confirm it goes through
   the relevant Policy — never a raw cross-company query.
5. Check sibling files for existing conventions before creating new files.
6. Check for existing components to reuse before writing a new one.

### When using Artisan

7. Use `php artisan make:` commands to create new files (migrations, controllers, models, etc.).
8. Pass `--no-interaction` to all Artisan commands.
9. Run `php artisan list` to discover available commands.
10. Use `php artisan make:class` for generic PHP classes.

### When writing migrations

11. Primary key: `$table->char('id', 26)->primary();`
12. All foreign keys: `$table->char('{model}_id', 26);`
13. No PostgreSQL native `ENUM` types — use `VARCHAR`. Values enforced in PHP.
14. The `accountant_company` table uses `UNIQUE(accountant_firm_id, sme_company_id)` —
    do NOT add a partial unique index `WHERE ended_at IS NULL`. Multiple active
    accountants per SME is intentionally allowed.
15. Always create factories and seeders for new models.

### When writing models

16. Every model uses `HasUlid`.
17. `$fillable` is always explicit. `$guarded = []` is forbidden.
18. Money columns are always `int`. Float is forbidden for money.
19. Namespace is `App\` with domain subdirectories (e.g. `App\Models\PME\Invoice`, `App\Services\Compta\ExportService`).
20. Always use explicit return type declarations on all methods.
21. Use PHP 8 constructor property promotion.
22. Avoid `DB::`; prefer `Model::query()`. Use eager loading to prevent N+1 queries.

### When writing Form Requests

23. Always create Form Request classes for validation — never inline validation in controllers.
24. Check sibling Form Requests for whether the project uses array or string validation rules.

### When writing quota-gated features

25. Call `$this->quotaService->authorize($company, $quotaType)` **before** the action.
26. Call `$this->quotaService->consume($company, $quotaType)` **inside the DB transaction**,
    after the action succeeds.
27. Never check `quota_usage` or `addon_purchases` directly — always go through `QuotaService`.
28. Never allow purchasing reminder add-ons on Essentiel or Entreprise plans —
    `isUnlimited()` must return `true` for those quota types.

### When writing controllers and Livewire components

29. Always call `$this->authorize()` before any write operation.
30. Never call `Model::find($id)` or `Model::all()` without an authorization check.
31. Controllers: validate → authorize → quota check (via service) → one service call → return.
32. `#[Locked]` on every server-originated Livewire property.
33. `wire:loading` on every submit button.
34. Use named routes and the `route()` function for URL generation — never hardcoded paths.
35. Use `config('fayeku.key')` — never `env('KEY')` directly outside of config files.

### When writing tests

36. Use `php artisan make:test --pest {name}` to create tests.
37. Run tests with `php artisan test --compact` or `--filter=testName`.
38. Every new resource gets an ownership isolation test (user A cannot access company B data).
39. Every accountant access path gets both an "active link" and an "ended link" test.
40. Every quota-gated action gets: success within quota, blocked when exhausted, add-on extension.
41. Run `php artisan test --compact` before marking any task complete.
42. Do NOT delete tests without approval.

### After modifying PHP files

43. Run `vendor/bin/pint --dirty --format agent` to format all modified PHP files.
    Do not run `--test` mode — always run with `--format agent` to fix issues.

### When displaying formatted values

Use the global helpers defined in `app/helpers.php` — never inline formatting logic in Blade
templates, Livewire components, or services.

| Helper | Output example | Notes |
|---|---|---|
| `format_date($date)` | `21 Jan 2026` | 3-letter French month abbreviation, no dot |
| `format_date($date, withTime: true)` | `21 Jan 2026, 14:35` | With time |
| `format_date($date, withYear: false)` | `21 Jan` | Without year |
| `format_month($date)` | `Janvier 2026` | Full French month name |
| `format_month($date, withYear: false)` | `Janvier` | Without year |
| `format_phone($phone)` | `+221 77 123 45 67` | Uses `fayeku.phone_countries` config |
| `format_money($amount)` | `14 632 000 FCFA` | XOF by default, delegates to `CurrencyService` |
| `format_money($amount, 'EUR')` | `12,50 EUR` | Amount in cents for currencies with decimals |
| `format_money($amount, withLabel: false)` | `14 632 000` | Without currency label |
| `format_money($amount, compact: true)` | `885 000 F` | Compact symbol for tables — XOF by default |
| `format_money($amount, 'EUR', compact: true)` | `€40,00` | Symbol before, no space |
| `format_money($amount, 'CHF', compact: true)` | `CHF 40.00` | Symbol before, with space |

**`compact: true` symbol reference:**

| Currency | Symbol | Position | Example |
|---|---|---|---|
| XOF (FCFA) | `F` | after + space | `885 000 F` |
| EUR | `€` | before | `€40,00` |
| USD | `$` | before | `$40.00` |
| GBP | `£` | before | `£40.00` |
| JPY | `¥` | before | `¥1,250` |
| CAD | `CA$` | before | `CA$40.00` |
| AUD | `A$` | before | `A$40.00` |
| HKD | `HK$` | before | `HK$40.00` |
| NZD | `NZ$` | before | `NZ$40.00` |
| CNH | `¥` | before | `¥40.00` |
| CHF | `CHF` | before + space | `CHF 40.00` |

Use `format_money($amount, compact: true)` in tables and compact displays. Use `format_money($amount)` for
verbose labels, detail views (invoices, summaries, alerts), **and KPI stat cards** — KPI cards always show
the full currency label (e.g. `14 632 000 FCFA`, never `14 632 000 F`).

Rules:
- **Never** use `number_format()`, `->diffForHumans()`, `->translatedFormat()`, or
  `->locale('fr_FR')` to display amounts, dates, or phone numbers in views.
- **Never** write an inline closure or local `$formatPhone` / `$formatDate` variable in a
  Blade `@php` block — extract to the helper instead.
- `format_money` expects amounts in the smallest stored unit, consistent
  with `CurrencyService::format()`: whole FCFA for XOF/JPY, cents for USD/EUR/etc.
- All helpers return `'—'` for `null` or empty input.

### General

44. Never log raw OTP codes.
45. Never hardcode phone country prefixes — use the `Country` model or config values.
46. Never instantiate services with `new` — always use dependency injection.
47. Order: migration → model → factory → policy → service → controller / Livewire → test → pint.
48. Use queued jobs (`ShouldQueue`) for time-consuming operations.
49. Do not create verification scripts or tinker scripts when tests cover the functionality.
50. Do not create documentation files unless explicitly requested.

---

## 20. Glossary

| Term | Meaning |
|---|---|
| Company | A business entity on Fayeku — either an SME or an accounting firm |
| SME | Petite et Moyenne Entreprise — a company of type `sme` |
| Accountant firm | A company of type `accountant_firm` — uses Fayeku Compta |
| `accountant_company` | Pivot table tracking the full history of which firm manages which SME |
| Active relationship | An `accountant_company` row where `ended_at IS NULL`. An SME can have multiple active relationships simultaneously — one per accountant firm that currently has access. |
| Client | A customer record owned by an SME — per-company, not shared globally |
| OTP | One-Time Password — 6-digit SMS code, registration only |
| Relance | Payment reminder sent to a debtor — via WhatsApp, SMS, or email. Channel chosen per reminder at send time. Quota is shared across all channels. |
| `ReminderChannel` | PHP enum: `WhatsApp`, `Sms`, `Email`. Stored as `VARCHAR` on the `reminders` table. |
| `ReminderChannelInterface` | Contract implemented by all three channel services. `ReminderService` dispatches to the correct one via `match`. |
| Recouvrement | Debt collection process |
| Trésorerie | Cash flow / treasury |
| DGID | Direction Générale des Impôts et des Domaines — Senegal's tax authority. Electronic invoicing is legislated but the API is not yet published. |
| FNE | Facture Normalisée Electronique — Ivory Coast's mandatory electronic invoicing system. Live since mid-2025. Portal: `fne.dgi.gouv.ci`. REST API with Bearer token auth. |
| `FneConnector` | The live CI fiscal connector. Sends POST to `$FNE_API_URL/external/invoices/sign`, stores `fne_reference`, `fne_token`, `fne_certified_at` on the invoice. |
| `DgidConnector` | Senegal fiscal connector stub. Throws a `RuntimeException` until the DGID API is published. Do not attempt to call any endpoint. |
| FCFA | West African CFA franc — stored as whole integers |
| E.164 | Phone format: `+221771234567` |
| ULID | Primary key type throughout — 26-char sortable string |
| Plan | Subscription tier: `basique`, `essentiel`, `entreprise` |
| Add-on | One-time credit purchase that extends a plan's quota (reminders, users, clients, storage) |
| Quota | A usage limit attached to a plan, tracked in `quota_usage`. Monthly quotas reset on the 1st. |
| Hard block | When quota is exhausted, the action is refused. No silent overage. |
| `QuotaService` | The single service responsible for all quota checks and consumption. Never bypass it. |
| `plan_definitions` | Seeded reference table. Never modified by application code at runtime. |
| Partner / Gold / Platinum | Accountant referral tiers by active referred SME count |
| Portefeuille | An accountant firm's active SME client portfolio |
| Sage 100 / EBP | Accounting software used by Senegalese and Ivorian firms |
| Flux UI | `livewire/flux` v2 — the UI component library used for `<flux:*>` elements |
| Boost | `laravel/boost` v2 — the MCP server providing `search-docs`, `database-query`, and other agent tools |
| Pint | `laravel/pint` v1 — the code formatter. Always run `vendor/bin/pint --dirty --format agent` after modifying PHP files |
| Fortify | `laravel/fortify` v1 — the headless authentication backend handling login, registration, OTP, and 2FA routes |
