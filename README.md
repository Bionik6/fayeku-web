# Fayeku

Fayeku is a B2B SaaS platform for African SMEs and accounting firms, focused first on Senegal and Ivory Coast. It combines invoicing, debt collection, treasury visibility, accountant collaboration, and fiscal compliance in one Laravel application.

The platform serves two product surfaces:

- `Fayeku PME` for SME owners and their teams
- `Fayeku Compta` for accountants and accounting firms

## Core Product Rules

These are foundational business constraints in this repository:

- Authentication is `phone number + password` only
- OTP by SMS is required at registration only, never at login
- Registration country support is limited to Senegal (`+221`) and Ivory Coast (`+225`)
- Phone numbers are stored in E.164 format
- Monetary amounts are stored as integer FCFA values only
- Payment reminders support WhatsApp, SMS, and email
- Reminder quotas apply to the total number of sends across all channels
- Ivory Coast FNE compliance is live and mandatory
- Senegal DGID compliance is planned and the app must stay integration-ready

## Tech Stack

- Laravel 13
- PHP 8.5 target architecture
- Livewire 4
- Flux UI 2
- Tailwind CSS 4
- Alpine.js 3
- Laravel Fortify
- Laravel Sanctum
- PostgreSQL 16
- Redis for cache
- Database queue driver via PostgreSQL `jobs` table
- Pest 4 for testing

## Architecture

Fayeku is built as a modular monolith. Business code lives in [`modules/`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/modules), not just under [`app/`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/app).

Top-level module groups:

- [`modules/Shared`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/modules/Shared): shared models, services, interfaces, middleware, config
- [`modules/Auth`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/modules/Auth): registration, login, OTP verification, core account/company setup
- [`modules/PME`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/modules/PME): SME-facing domains such as invoicing, clients, collection, and treasury
- [`modules/Compta`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/modules/Compta): accountant-facing domains such as portfolio, export, partnership, and compliance

Module providers are registered in [`bootstrap/providers.php`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/bootstrap/providers.php).

### Module Boundaries

- Shared code belongs in `modules/Shared`
- Cross-module side effects should use events
- Cross-module reads should use public services, not direct model imports
- `PME` and `Compta` may depend on `Shared`
- `PME` and `Compta` must not depend on each other directly
- Authorization is explicit through Laravel Policies, not global tenant scopes

## Repository Structure

Common entry points:

- [`bootstrap/providers.php`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/bootstrap/providers.php)
- [`composer.json`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/composer.json)
- [`routes/web.php`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/routes/web.php)
- [`routes/api.php`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/routes/api.php)
- [`modules/Shared/config/fayeku.php`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/modules/Shared/config/fayeku.php)
- [`AGENTS.md`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/AGENTS.md)

## Local Setup

### Requirements

- PHP
- Composer
- Node.js and npm
- PostgreSQL
- Redis

### Install

```bash
composer setup
```

The built-in setup script will:

- install PHP dependencies
- create `.env` from `.env.example` if needed
- generate the app key
- run migrations
- install frontend dependencies
- build frontend assets

If you prefer to run things manually:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
npm install
npm run build
```

## Running The App

For the usual local development workflow:

```bash
composer dev
```

This starts:

- the Laravel app server
- the queue listener
- Laravel Pail
- the Vite dev server

## Testing And Quality

Run the full project checks with:

```bash
composer test
```

Useful commands:

```bash
composer lint
composer lint:check
php artisan test
```

## Data Model Notes

The application uses ULIDs as primary keys and enforces ownership through foreign keys plus policies.

Key concepts:

- `users` are people
- `companies` are SME businesses or accounting firms
- `company_user` handles team membership
- `accountant_companies` links accounting firms to SMEs over time
- `clients` are owned per SME company, not globally shared

There is no separate tenant table. Data access is controlled by explicit ownership chains and policy checks.

## Provider Abstractions

Several integrations are intentionally abstracted behind interfaces so providers can change without rewriting domain logic:

- SMS delivery
- WhatsApp delivery
- email reminders
- PDF generation
- payout processing

See [`modules/Shared/Interfaces`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/modules/Shared/Interfaces) for the shared contracts.

## Contributor Notes

- Read [`AGENTS.md`](/Users/iciss/Data/Business/Fayeku/Developments/fayeku-web/AGENTS.md) before making architectural changes
- Keep new domain code inside the correct module
- Do not introduce direct `PME` to `Compta` dependencies
- Use policies for authorization on every user-accessible model
- Use integer FCFA amounts only
- Do not change dependency versions without explicit approval

## Current Focus Areas

The repository already includes scaffolding and domain code for:

- Auth and OTP registration flow
- Invoicing and quotes
- SME client management
- Multi-channel reminders and quota handling
- Treasury services
- Accountant portfolio and exports
- Partnership invitation linking
- Fiscal compliance connectors for FNE and DGID readiness
