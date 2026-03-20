#!/usr/bin/env bash
# =============================================================================
# Fayeku — Module Scaffolding Script
# Usage: chmod +x scaffold.sh && ./scaffold.sh
# Run from the Laravel project root (where artisan lives).
#
# This script:
#   1. Creates the full modules/ directory structure
#   2. Writes all PHP stubs (providers, traits, interfaces, models, services…)
#   3. Calls scaffold_patch.php to patch composer.json, bootstrap files, config
#   4. Runs composer dump-autoload
#
# Safe to re-run: existing files are never overwritten.
# =============================================================================
set -euo pipefail

ROOT="$(pwd)"
MODULES="$ROOT/modules"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()    { echo -e "${CYAN}[info]${NC}  $1"; }
success() { echo -e "${GREEN}[ok]${NC}    $1"; }
warn()    { echo -e "${YELLOW}[warn]${NC}  $1"; }
fail()    { echo -e "${RED}[fail]${NC}  $1"; exit 1; }

[[ -f "$ROOT/artisan" ]] || fail "Run from the Laravel project root (artisan not found)."
[[ -f "$ROOT/scaffold_patch.php" ]] || fail "scaffold_patch.php not found in project root."

# Write a file only if it does not yet exist
write() {
    local path="$1"; local content="$2"
    mkdir -p "$(dirname "$path")"
    if [[ ! -f "$path" ]]; then
        printf '%s' "$content" > "$path"
        success "created  $path"
    else
        warn "skipped  $path"
    fi
}

# Create empty directory with a .gitkeep
mdir() { mkdir -p "$1"; touch "$1/.gitkeep" 2>/dev/null || true; }

info "Starting Fayeku scaffolding…"
echo ""

# =============================================================================
# SHARED
# =============================================================================
info "=== Shared ==="

mdir "$MODULES/Shared/Models"
mdir "$MODULES/Shared/Services"
mdir "$MODULES/Shared/Interfaces"
mdir "$MODULES/Shared/Traits"
mdir "$MODULES/Shared/Middleware"
mdir "$MODULES/Shared/Enums"
mdir "$MODULES/Shared/Exceptions"
mdir "$MODULES/Shared/Providers"
mdir "$MODULES/Shared/config"
mdir "$MODULES/Shared/database/migrations"
mdir "$MODULES/Shared/database/seeders"

# ---------- Traits ----------
write "$MODULES/Shared/Traits/HasUlid.php" '<?php

namespace Modules\Shared\Traits;

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

    public function getIncrementing(): bool  { return false; }
    public function getKeyType(): string     { return '\''string'\''; }
}
'

# ---------- Interfaces ----------
write "$MODULES/Shared/Interfaces/SmsProviderInterface.php" '<?php

namespace Modules\Shared\Interfaces;

interface SmsProviderInterface
{
    public function send(string $phone, string $message): bool;
}
'

write "$MODULES/Shared/Interfaces/WhatsAppProviderInterface.php" '<?php

namespace Modules\Shared\Interfaces;

interface WhatsAppProviderInterface
{
    public function send(string $phone, string $message): bool;
}
'

write "$MODULES/Shared/Interfaces/PdfGeneratorInterface.php" '<?php

namespace Modules\Shared\Interfaces;

interface PdfGeneratorInterface
{
    public function generate(string $view, array $data): string;
}
'

write "$MODULES/Shared/Interfaces/PayoutInterface.php" '<?php

namespace Modules\Shared\Interfaces;

interface PayoutInterface
{
    public function send(string $phone, int $amountFcfa, string $reference): bool;
}
'

write "$MODULES/Shared/Interfaces/EmailReminderInterface.php" '<?php

namespace Modules\Shared\Interfaces;

interface EmailReminderInterface
{
    public function send(string $email, string $subject, string $body): bool;
}
'

# ---------- Enums ----------
write "$MODULES/Shared/Enums/QuotaType.php" '<?php

namespace Modules\Shared\Enums;

enum QuotaType: string
{
    case Reminders = '\''reminders'\'';
    case Users     = '\''users'\'';
    case Clients   = '\''clients'\'';
    case StorageMb = '\''storage_mb'\'';
}
'

# ---------- Exceptions ----------
write "$MODULES/Shared/Exceptions/QuotaExceededException.php" '<?php

namespace Modules\Shared\Exceptions;

use Exception;

class QuotaExceededException extends Exception
{
    public function __construct(string $quotaType)
    {
        parent::__construct("Quota exceeded for: {$quotaType}");
    }
}
'

# ---------- Middleware ----------
write "$MODULES/Shared/Middleware/EnsureProfileType.php" '<?php

namespace Modules\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        if (auth()->check() && auth()->user()->profile_type !== $type) {
            abort(403, '\''Access denied for this profile type.'\'');
        }
        return $next($request);
    }
}
'

write "$MODULES/Shared/Middleware/EnsurePhoneVerified.php" '<?php

namespace Modules\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && is_null(auth()->user()->phone_verified_at)) {
            if ($request->expectsJson()) {
                return response()->json(['\''message'\'' => '\''Phone not verified.'\''], 403);
            }
            return redirect()->route('\''auth.otp'\'');
        }
        return $next($request);
    }
}
'

# ---------- Models ----------
write "$MODULES/Shared/Models/User.php" '<?php

namespace Modules\Shared\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Shared\Traits\HasUlid;

class User extends Authenticatable
{
    use HasApiTokens, HasUlid, Notifiable;

    protected $fillable = [
        '\''first_name'\'', '\''last_name'\'', '\''phone'\'',
        '\''password'\'', '\''profile_type'\'', '\''country_code'\'', '\''is_active'\'',
    ];

    protected $hidden = ['\''password'\'', '\''remember_token'\''];

    protected $casts = [
        '\''phone_verified_at'\'' => '\''datetime'\'',
        '\''is_active'\''         => '\''boolean'\'',
        '\''password'\''          => '\''hashed'\'',
    ];

    public function companies(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Auth\Models\Company::class,
            '\''company_user'\'', '\''user_id'\'', '\''company_id'\''
        )->withPivot('\''role'\'')->withTimestamps();
    }
}
'

write "$MODULES/Shared/Models/Country.php" '<?php

namespace Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Traits\HasUlid;

class Country extends Model
{
    use HasUlid;

    protected $fillable = ['\''code'\'', '\''name'\'', '\''phone_prefix'\'', '\''is_active'\''];
    protected $casts    = ['\''is_active'\'' => '\''boolean'\''];
}
'

# ---------- Services ----------
write "$MODULES/Shared/Services/OtpService.php" '<?php

namespace Modules\Shared\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Shared\Interfaces\SmsProviderInterface;

class OtpService
{
    public function __construct(private SmsProviderInterface $sms) {}

    public function generate(string $phone): string
    {
        $code = (string) random_int(100000, 999999);

        DB::table('\''otp_codes'\'')->insert([
            '\''id'\''         => (string) Str::ulid(),
            '\''phone'\''      => $phone,
            '\''code'\''       => hash('\''sha256'\'', $code),
            '\''expires_at'\'' => now()->addMinutes((int) config('\''fayeku.otp_expiry_minutes'\'', 10)),
            '\''attempts'\''   => 0,
            '\''created_at'\'' => now(),
            '\''updated_at'\'' => now(),
        ]);

        $this->sms->send($phone, "Votre code Fayeku : {$code}");

        return $code;
    }

    public function verify(string $phone, string $code): bool
    {
        $record = DB::table('\''otp_codes'\'')
            ->where('\''phone'\'', $phone)
            ->whereNull('\''used_at'\'')
            ->where('\''expires_at'\'', '\''>='\'', now())
            ->where('\''attempts'\'', '\''<'\'', config('\''fayeku.otp_max_attempts'\'', 3))
            ->latest('\''created_at'\'')
            ->first();

        if (! $record) {
            return false;
        }

        if (! hash_equals($record->code, hash('\''sha256'\'', $code))) {
            DB::table('\''otp_codes'\'')->where('\''id'\'', $record->id)->increment('\''attempts'\'');
            return false;
        }

        DB::table('\''otp_codes'\'')->where('\''id'\'', $record->id)
            ->update(['\''used_at'\'' => now(), '\''updated_at'\'' => now()]);

        return true;
    }
}
'

write "$MODULES/Shared/Services/QuotaService.php" '<?php

namespace Modules\Shared\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Auth\Models\Company;
use Modules\Shared\Enums\QuotaType;
use Modules\Shared\Exceptions\QuotaExceededException;

class QuotaService
{
    public function authorize(Company $company, string|QuotaType $type, int $amount = 1): void
    {
        $t = $type instanceof QuotaType ? $type->value : $type;
        if ($this->isUnlimited($company, $t)) { return; }
        if ($this->available($company, $t) < $amount) {
            throw new QuotaExceededException($t);
        }
    }

    public function consume(Company $company, string|QuotaType $type, int $amount = 1): void
    {
        $t      = $type instanceof QuotaType ? $type->value : $type;
        $period = $this->isMonthly($t) ? now()->startOfMonth()->toDateString() : null;

        DB::table('\''quota_usage'\'')->upsert(
            ['\''id'\'' => (string) Str::ulid(), '\''company_id'\'' => $company->id,
             '\''quota_type'\'' => $t, '\''period_start'\'' => $period,
             '\''used'\'' => $amount, '\''created_at'\'' => now(), '\''updated_at'\'' => now()],
            ['\''company_id'\'', '\''quota_type'\'', '\''period_start'\''],
            ['\''used'\'' => DB::raw("quota_usage.used + {$amount}"), '\''updated_at'\'' => now()]
        );
    }

    public function available(Company $company, string|QuotaType $type): int
    {
        $t       = $type instanceof QuotaType ? $type->value : $type;
        $limit   = $this->planLimit($company, $t);
        $used    = $this->currentUsage($company, $t);
        $addons  = $this->addonCredits($company, $t);
        return ($limit - $used) + $addons;
    }

    public function isUnlimited(Company $company, string|QuotaType $type): bool
    {
        $t = $type instanceof QuotaType ? $type->value : $type;
        return $this->planLimit($company, $t) === -1;
    }

    private function planLimit(Company $company, string $t): int
    {
        $plan = DB::table('\''plan_definitions'\'')
            ->where('\''slug'\'', $company->subscription?->plan_slug)
            ->first();
        if (! $plan) { return 0; }
        return match ($t) {
            '\''reminders'\''  => $plan->reminders_per_month,
            '\''users'\''      => $plan->max_users,
            '\''clients'\''    => $plan->max_clients,
            '\''storage_mb'\'' => $plan->max_storage_mb,
            default        => 0,
        };
    }

    private function currentUsage(Company $company, string $t): int
    {
        $period = $this->isMonthly($t) ? now()->startOfMonth()->toDateString() : null;
        $q = DB::table('\''quota_usage'\'')
            ->where('\''company_id'\'', $company->id)->where('\''quota_type'\'', $t);
        $period ? $q->where('\''period_start'\'', $period) : $q->whereNull('\''period_start'\'');
        return (int) $q->value('\''used'\'');
    }

    private function addonCredits(Company $company, string $t): int
    {
        return (int) DB::table('\''addon_purchases'\'')
            ->where('\''company_id'\'', $company->id)->where('\''addon_type'\'', $t)
            ->where('\''credits_remaining'\'', '\''>\'\'', 0)
            ->where(fn($q) => $q->whereNull('\''expires_at'\'')->orWhere('\''expires_at'\'', '\''>\'\'', now()))
            ->sum('\''credits_remaining'\'');
    }

    private function isMonthly(string $t): bool
    {
        return $t === QuotaType::Reminders->value;
    }
}
'

# ---------- Config ----------
write "$MODULES/Shared/config/fayeku.php" '<?php

return [
    '\''otp_expiry_minutes'\'' => (int) env('\''OTP_EXPIRY_MINUTES'\'', 10),
    '\''otp_max_attempts'\''   => (int) env('\''OTP_MAX_ATTEMPTS'\'', 3),
    '\''fne_api_url'\''        => env('\''FNE_API_URL'\'', '\''\'\''),
    '\''fne_test_url'\''       => env('\''FNE_TEST_URL'\'', '\''http://54.247.95.108/ws'\''),
    '\''countries'\'' => [
        '\''SN'\'' => ['\''name'\'' => '\''Sénégal'\'',       '\''prefix'\'' => '\''+221'\''],
        '\''CI'\'' => ['\''name'\'' => "Côte d'\''Ivoire", '\''prefix'\'' => '\''+225'\''],
    ],
];
'

# ---------- Provider ----------
write "$MODULES/Shared/Providers/SharedServiceProvider.php" '<?php

namespace Modules\Shared\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Shared\Services\OtpService;
use Modules\Shared\Services\QuotaService;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OtpService::class);
        $this->app->singleton(QuotaService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '\''/../database/migrations'\'');
        $this->mergeConfigFrom(__DIR__ . '\''/../config/fayeku.php'\'', '\''fayeku'\'');
    }
}
'

# =============================================================================
# AUTH
# =============================================================================
info "=== Auth ==="

mdir "$MODULES/Auth/Http/Controllers"
mdir "$MODULES/Auth/Http/Requests"
mdir "$MODULES/Auth/Livewire"
mdir "$MODULES/Auth/Models"
mdir "$MODULES/Auth/Services"
mdir "$MODULES/Auth/Providers"
mdir "$MODULES/Auth/database/migrations"
mdir "$MODULES/Auth/routes"
mdir "$MODULES/Auth/resources/views"
mdir "$MODULES/Auth/tests/Feature"
mdir "$MODULES/Auth/tests/Unit"

write "$MODULES/Auth/Models/Company.php" '<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Shared\Traits\HasUlid;

class Company extends Model
{
    use HasUlid;

    protected $fillable = ['\''name'\'', '\''type'\'', '\''plan'\'', '\''country_code'\'', '\''phone'\''];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Shared\Models\User::class,
            '\''company_user'\'', '\''company_id'\'', '\''user_id'\''
        )->withPivot('\''role'\'')->withTimestamps();
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function managedSmes(): HasMany
    {
        return $this->hasMany(AccountantCompany::class, '\''accountant_firm_id'\'')->whereNull('\''ended_at'\'');
    }

    public function activeAccountants(): HasMany
    {
        return $this->hasMany(AccountantCompany::class, '\''sme_company_id'\'')->whereNull('\''ended_at'\'');
    }
}
'

write "$MODULES/Auth/Models/AccountantCompany.php" '<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Traits\HasUlid;

class AccountantCompany extends Model
{
    use HasUlid;

    protected $fillable = [
        '\''accountant_firm_id'\'', '\''sme_company_id'\'',
        '\''started_at'\'', '\''ended_at'\'', '\''ended_reason'\'',
    ];

    protected $casts = ['\''started_at'\'' => '\''datetime'\'', '\''ended_at'\'' => '\''datetime'\''];

    public function accountantFirm(): BelongsTo
    {
        return $this->belongsTo(Company::class, '\''accountant_firm_id'\'');
    }

    public function smeCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, '\''sme_company_id'\'');
    }
}
'

write "$MODULES/Auth/Models/Subscription.php" '<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Traits\HasUlid;

class Subscription extends Model
{
    use HasUlid;

    protected $fillable = [
        '\''company_id'\'', '\''plan_slug'\'', '\''price_paid'\'', '\''billing_cycle'\'', '\''status'\'',
        '\''trial_ends_at'\'', '\''current_period_start'\'', '\''current_period_end'\'',
        '\''cancelled_at'\'', '\''invited_by_firm_id'\'',
    ];

    protected $casts = [
        '\''price_paid'\''           => '\''integer'\'',
        '\''trial_ends_at'\''        => '\''datetime'\'',
        '\''current_period_start'\'' => '\''datetime'\'',
        '\''current_period_end'\''   => '\''datetime'\'',
        '\''cancelled_at'\''         => '\''datetime'\'',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
'

write "$MODULES/Auth/Services/AuthService.php" '<?php

namespace Modules\Auth\Services;

use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\Company;
use Modules\Auth\Models\Subscription;
use Modules\Shared\Models\User;
use Modules\Shared\Services\OtpService;

class AuthService
{
    public function __construct(private OtpService $otpService) {}

    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $prefix = config("fayeku.countries.{$data['\''country_code'\'']}.prefix", '\'\'\'');
            $phone  = $prefix . ltrim($data['\''phone'\''], '\''0'\'');

            $user = User::create([
                '\''first_name'\''   => $data['\''first_name'\''],
                '\''last_name'\''    => $data['\''last_name'\''],
                '\''phone'\''        => $phone,
                '\''password'\''     => $data['\''password'\''],
                '\''profile_type'\'' => $data['\''profile_type'\''],
                '\''country_code'\'' => $data['\''country_code'\''],
            ]);

            $type    = $data['\''profile_type'\''] === '\''sme'\'' ? '\''sme'\'' : '\''accountant_firm'\'';
            $company = Company::create([
                '\''name'\''         => $data['\''company_name'\''],
                '\''type'\''         => $type,
                '\''country_code'\'' => $data['\''country_code'\''],
                '\''plan'\''         => '\''basique'\'',
            ]);

            $company->users()->attach($user->id, ['\''role'\'' => '\''owner'\'']);

            Subscription::create([
                '\''company_id'\''           => $company->id,
                '\''plan_slug'\''            => '\''basique'\'',
                '\''price_paid'\''           => 0,
                '\''billing_cycle'\''        => '\''trial'\'',
                '\''status'\''               => '\''trial'\'',
                '\''trial_ends_at'\''        => now()->addDays(60),
                '\''current_period_start'\'' => now(),
                '\''current_period_end'\''   => now()->addDays(60),
            ]);

            $this->otpService->generate($phone);

            return $user;
        });
    }
}
'

for ctrl in RegisterController LoginController LogoutController OtpController; do
write "$MODULES/Auth/Http/Controllers/${ctrl}.php" "<?php

namespace Modules\\Auth\\Http\\Controllers;

use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\Request;

class ${ctrl} extends Controller
{
    // TODO: implement
}
"
done

for req in RegisterRequest LoginRequest VerifyOtpRequest; do
write "$MODULES/Auth/Http/Requests/${req}.php" "<?php

namespace Modules\\Auth\\Http\\Requests;

use Illuminate\\Foundation\\Http\\FormRequest;

class ${req} extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array    { return []; }
}
"
done

write "$MODULES/Auth/routes/web.php" '<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\RegisterController;
use Modules\Auth\Http\Controllers\LoginController;
use Modules\Auth\Http\Controllers\LogoutController;
use Modules\Auth\Http\Controllers\OtpController;

Route::middleware('\''guest'\'')->group(function () {
    Route::get('\''/register'\'',       [RegisterController::class, '\''show'\''])->name('\''auth.register'\'');
    Route::post('\''/register'\'',      [RegisterController::class, '\''store'\''])->name('\''auth.register.submit'\'');
    Route::get('\''/login'\'',          [LoginController::class,    '\''show'\''])->name('\''auth.login'\'');
    Route::post('\''/login'\'',         [LoginController::class,    '\''store'\''])->name('\''auth.login.submit'\'');
    Route::get('\''/otp'\'',            [OtpController::class,      '\''show'\''])->name('\''auth.otp'\'');
    Route::post('\''/otp'\'',           [OtpController::class,      '\''verify'\''])->name('\''auth.otp.verify'\'');
    Route::post('\''/otp/resend'\'',    [OtpController::class,      '\''resend'\''])->name('\''auth.otp.resend'\'');
});

Route::middleware('\''auth'\'')->post('\''/logout'\'', [LogoutController::class, '\''destroy'\''])->name('\''auth.logout'\'');
'

write "$MODULES/Auth/routes/api.php" '<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\RegisterController;
use Modules\Auth\Http\Controllers\LoginController;
use Modules\Auth\Http\Controllers\LogoutController;
use Modules\Auth\Http\Controllers\OtpController;

Route::prefix('\''api/auth'\'')->group(function () {
    Route::post('\''/register'\'',    [RegisterController::class, '\''store'\'']);
    Route::post('\''/login'\'',       [LoginController::class,    '\''store'\'']);
    Route::post('\''/otp/verify'\'',  [OtpController::class,      '\''verify'\'']);
    Route::post('\''/otp/resend'\'',  [OtpController::class,      '\''resend'\'']);
    Route::middleware('\''auth:sanctum'\'')->post('\''/logout'\'', [LogoutController::class, '\''destroy'\'']);
});
'

write "$MODULES/Auth/Providers/AuthModuleServiceProvider.php" '<?php

namespace Modules\Auth\Providers;

use Illuminate\Support\ServiceProvider;

class AuthModuleServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '\''/../routes/web.php'\'');
        $this->loadRoutesFrom(__DIR__ . '\''/../routes/api.php'\'');
        $this->loadMigrationsFrom(__DIR__ . '\''/../database/migrations'\'');
        $this->loadViewsFrom(__DIR__ . '\''/../resources/views'\'', '\''auth'\'');
    }
}
'

# =============================================================================
# PME — Invoicing
# =============================================================================
info "=== PME / Invoicing ==="

mdir "$MODULES/PME/Invoicing/Http/Controllers"
mdir "$MODULES/PME/Invoicing/Http/Requests"
mdir "$MODULES/PME/Invoicing/Livewire"
mdir "$MODULES/PME/Invoicing/Models"
mdir "$MODULES/PME/Invoicing/Services"
mdir "$MODULES/PME/Invoicing/Enums"
mdir "$MODULES/PME/Invoicing/Events"
mdir "$MODULES/PME/Invoicing/Listeners"
mdir "$MODULES/PME/Invoicing/Policies"
mdir "$MODULES/PME/Invoicing/Providers"
mdir "$MODULES/PME/Invoicing/database/migrations"
mdir "$MODULES/PME/Invoicing/routes"
mdir "$MODULES/PME/Invoicing/resources/views/livewire"
mdir "$MODULES/PME/Invoicing/tests/Feature"
mdir "$MODULES/PME/Invoicing/tests/Unit"

write "$MODULES/PME/Invoicing/Enums/InvoiceStatus.php" '<?php

namespace Modules\PME\Invoicing\Enums;

enum InvoiceStatus: string
{
    case Draft               = '\''draft'\'';
    case Sent                = '\''sent'\'';
    case Certified           = '\''certified'\'';
    case CertificationFailed = '\''certification_failed'\'';
    case PartiallyPaid       = '\''partially_paid'\'';
    case Paid                = '\''paid'\'';
    case Overdue             = '\''overdue'\'';
    case Cancelled           = '\''cancelled'\'';
}
'

write "$MODULES/PME/Invoicing/Enums/QuoteStatus.php" '<?php

namespace Modules\PME\Invoicing\Enums;

enum QuoteStatus: string
{
    case Draft    = '\''draft'\'';
    case Sent     = '\''sent'\'';
    case Accepted = '\''accepted'\'';
    case Declined = '\''declined'\'';
    case Expired  = '\''expired'\'';
}
'

write "$MODULES/PME/Invoicing/Models/Invoice.php" '<?php

namespace Modules\PME\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\Shared\Traits\HasUlid;

class Invoice extends Model
{
    use HasUlid, SoftDeletes;

    protected $fillable = [
        '\''company_id'\'', '\''client_id'\'', '\''reference'\'', '\''status'\'',
        '\''issued_at'\'', '\''due_at'\'', '\''paid_at'\'',
        '\''subtotal'\'', '\''tax_amount'\'', '\''total'\'', '\''notes'\'',
        // FNE (Côte d'\''Ivoire)
        '\''fne_reference'\'', '\''fne_token'\'', '\''fne_certified_at'\'',
        '\''fne_balance_sticker'\'', '\''fne_raw_response'\'',
        // DGID (Sénégal — reserved, API not yet published)
        '\''dgid_reference'\'', '\''dgid_token'\'', '\''dgid_certified_at'\'',
    ];

    protected $casts = [
        '\''issued_at'\''           => '\''date'\'',
        '\''due_at'\''              => '\''date'\'',
        '\''paid_at'\''             => '\''datetime'\'',
        '\''fne_certified_at'\''    => '\''datetime'\'',
        '\''dgid_certified_at'\''   => '\''datetime'\'',
        '\''subtotal'\''            => '\''integer'\'',
        '\''tax_amount'\''          => '\''integer'\'',
        '\''total'\''               => '\''integer'\'',
        '\''fne_balance_sticker'\'' => '\''integer'\'',
        '\''fne_raw_response'\''    => '\''array'\'',
        '\''status'\''              => InvoiceStatus::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\Modules\PME\Clients\Models\Client::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(\Modules\PME\Collection\Models\Reminder::class);
    }
}
'

write "$MODULES/PME/Invoicing/Models/InvoiceLine.php" '<?php

namespace Modules\PME\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Traits\HasUlid;

class InvoiceLine extends Model
{
    use HasUlid;

    protected $fillable = [
        '\''invoice_id'\'', '\''description'\'', '\''quantity'\'',
        '\''unit_price'\'', '\''tax_rate'\'', '\''discount'\'', '\''total'\'',
    ];

    protected $casts = [
        '\''quantity'\''   => '\''integer'\'',
        '\''unit_price'\'' => '\''integer'\'',
        '\''tax_rate'\''   => '\''integer'\'',
        '\''discount'\''   => '\''integer'\'',
        '\''total'\''      => '\''integer'\'',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
'

for m in Quote QuoteLine; do
write "$MODULES/PME/Invoicing/Models/${m}.php" "<?php

namespace Modules\\PME\\Invoicing\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\SoftDeletes;
use Modules\\Shared\\Traits\\HasUlid;

class ${m} extends Model
{
    use HasUlid, SoftDeletes;
    // TODO: add fillable, casts, relationships
}
"
done

for s in InvoiceService QuoteService PdfService; do
write "$MODULES/PME/Invoicing/Services/${s}.php" "<?php

namespace Modules\\PME\\Invoicing\\Services;

class ${s}
{
    // TODO: implement
}
"
done

for e in InvoiceCreated InvoicePaid InvoiceMarkedOverdue QuoteAccepted; do
write "$MODULES/PME/Invoicing/Events/${e}.php" "<?php

namespace Modules\\PME\\Invoicing\\Events;

use Illuminate\\Foundation\\Events\\Dispatchable;
use Illuminate\\Queue\\SerializesModels;

class ${e}
{
    use Dispatchable, SerializesModels;
    // TODO: add constructor properties
}
"
done

write "$MODULES/PME/Invoicing/Listeners/NotifyAccountantOnNewInvoice.php" '<?php

namespace Modules\PME\Invoicing\Listeners;

use Modules\PME\Invoicing\Events\InvoiceCreated;

class NotifyAccountantOnNewInvoice
{
    public function handle(InvoiceCreated $event): void
    {
        // TODO: notify linked accountant firms
    }
}
'

write "$MODULES/PME/Invoicing/Policies/InvoicePolicy.php" '<?php

namespace Modules\PME\Invoicing\Policies;

use Modules\Auth\Models\AccountantCompany;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->companies()->where('\''companies.id'\'', $invoice->company_id)->exists()) {
            return true;
        }
        $firmIds = $user->companies()->where('\''type'\'', '\''accountant_firm'\'')->pluck('\''companies.id'\'');
        return AccountantCompany::whereIn('\''accountant_firm_id'\'', $firmIds)
            ->where('\''sme_company_id'\'', $invoice->company_id)
            ->whereNull('\''ended_at'\'')
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->companies()->where('\''type'\'', '\''sme'\'')->exists();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->companies()->where('\''companies.id'\'', $invoice->company_id)->exists();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }
}
'

write "$MODULES/PME/Invoicing/Policies/QuotePolicy.php" '<?php

namespace Modules\PME\Invoicing\Policies;

use Modules\PME\Invoicing\Models\Quote;
use Modules\Shared\Models\User;

class QuotePolicy
{
    public function view(User $user, Quote $quote): bool
    {
        return $user->companies()->where('\''companies.id'\'', $quote->company_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->companies()->where('\''type'\'', '\''sme'\'')->exists();
    }

    public function update(User $user, Quote $quote): bool
    {
        return $user->companies()->where('\''companies.id'\'', $quote->company_id)->exists();
    }
}
'

write "$MODULES/PME/Invoicing/routes/web.php" '<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['\''web'\'', '\''auth'\'', '\''verified.phone'\'', '\''profile:sme'\''])
    ->prefix('\''pme'\'')->name('\''pme.'\'')
    ->group(function () {
        Route::get('\''/invoices'\'',          '\''TODO'\'')->name('\''invoices.index'\'');
        Route::get('\''/invoices/create'\'',   '\''TODO'\'')->name('\''invoices.create'\'');
        Route::get('\''/invoices/{invoice}'\'', '\''TODO'\'')->name('\''invoices.show'\'');
        Route::get('\''/quotes'\'',             '\''TODO'\'')->name('\''quotes.index'\'');
    });
'

write "$MODULES/PME/Invoicing/routes/api.php" '<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['\''api'\'', '\''auth:sanctum'\'', '\''verified.phone'\''])
    ->prefix('\''api/pme'\'')
    ->group(function () {
        Route::apiResource('\''invoices'\'', \Modules\PME\Invoicing\Http\Controllers\InvoiceController::class);
        Route::apiResource('\''quotes'\'',   \Modules\PME\Invoicing\Http\Controllers\QuoteController::class);
    });
'

write "$MODULES/PME/Invoicing/Providers/InvoicingServiceProvider.php" '<?php

namespace Modules\PME\Invoicing\Providers;

use Illuminate\Support\ServiceProvider;

class InvoicingServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '\''/../routes/web.php'\'');
        $this->loadRoutesFrom(__DIR__ . '\''/../routes/api.php'\'');
        $this->loadMigrationsFrom(__DIR__ . '\''/../database/migrations'\'');
        $this->loadViewsFrom(__DIR__ . '\''/../resources/views'\'', '\''invoicing'\'');
    }
}
'

# =============================================================================
# PME — Clients
# =============================================================================
info "=== PME / Clients ==="

mdir "$MODULES/PME/Clients/Http/Controllers"
mdir "$MODULES/PME/Clients/Http/Requests"
mdir "$MODULES/PME/Clients/Livewire"
mdir "$MODULES/PME/Clients/Models"
mdir "$MODULES/PME/Clients/Services"
mdir "$MODULES/PME/Clients/Policies"
mdir "$MODULES/PME/Clients/Providers"
mdir "$MODULES/PME/Clients/database/migrations"
mdir "$MODULES/PME/Clients/routes"
mdir "$MODULES/PME/Clients/resources/views"
mdir "$MODULES/PME/Clients/tests/Feature"
mdir "$MODULES/PME/Clients/tests/Unit"

write "$MODULES/PME/Clients/Models/Client.php" '<?php

namespace Modules\PME\Clients\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Shared\Traits\HasUlid;

class Client extends Model
{
    use HasUlid, SoftDeletes;

    protected $fillable = [
        '\''company_id'\'', '\''name'\'', '\''phone'\'', '\''email'\'', '\''address'\'', '\''tax_id'\'',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\Company::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\Modules\PME\Invoicing\Models\Invoice::class);
    }
}
'

write "$MODULES/PME/Clients/Policies/ClientPolicy.php" '<?php

namespace Modules\PME\Clients\Policies;

use Modules\PME\Clients\Models\Client;
use Modules\Shared\Models\User;

class ClientPolicy
{
    public function view(User $user, Client $client): bool
    {
        return $user->companies()->where('\''companies.id'\'', $client->company_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->companies()->where('\''type'\'', '\''sme'\'')->exists();
    }

    public function update(User $user, Client $client): bool
    {
        return $user->companies()->where('\''companies.id'\'', $client->company_id)->exists();
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }
}
'

write "$MODULES/PME/Clients/Services/ClientService.php" '<?php

namespace Modules\PME\Clients\Services;

class ClientService
{
    // TODO: implement
}
'

write "$MODULES/PME/Clients/Providers/ClientsServiceProvider.php" '<?php

namespace Modules\PME\Clients\Providers;

use Illuminate\Support\ServiceProvider;

class ClientsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '\''/../database/migrations'\'');
        $this->loadViewsFrom(__DIR__ . '\''/../resources/views'\'', '\''clients'\'');
    }
}
'

# =============================================================================
# PME — Collection
# =============================================================================
info "=== PME / Collection ==="

mdir "$MODULES/PME/Collection/Http/Controllers"
mdir "$MODULES/PME/Collection/Http/Requests"
mdir "$MODULES/PME/Collection/Livewire"
mdir "$MODULES/PME/Collection/Models"
mdir "$MODULES/PME/Collection/Services"
mdir "$MODULES/PME/Collection/Interfaces"
mdir "$MODULES/PME/Collection/Enums"
mdir "$MODULES/PME/Collection/Jobs"
mdir "$MODULES/PME/Collection/Policies"
mdir "$MODULES/PME/Collection/Providers"
mdir "$MODULES/PME/Collection/database/migrations"
mdir "$MODULES/PME/Collection/routes"
mdir "$MODULES/PME/Collection/resources/views"
mdir "$MODULES/PME/Collection/tests/Feature"
mdir "$MODULES/PME/Collection/tests/Unit"

write "$MODULES/PME/Collection/Enums/ReminderChannel.php" '<?php

namespace Modules\PME\Collection\Enums;

enum ReminderChannel: string
{
    case WhatsApp = '\''whatsapp'\'';
    case Sms      = '\''sms'\'';
    case Email    = '\''email'\'';
}
'

write "$MODULES/PME/Collection/Enums/ReminderMode.php" '<?php

namespace Modules\PME\Collection\Enums;

enum ReminderMode: string
{
    case Auto   = '\''auto'\'';
    case Manual = '\''manual'\'';
}
'

write "$MODULES/PME/Collection/Enums/ReminderStatus.php" '<?php

namespace Modules\PME\Collection\Enums;

enum ReminderStatus: string
{
    case Pending   = '\''pending'\'';
    case Sent      = '\''sent'\'';
    case Delivered = '\''delivered'\'';
    case Failed    = '\''failed'\'';
}
'

write "$MODULES/PME/Collection/Interfaces/ReminderChannelInterface.php" '<?php

namespace Modules\PME\Collection\Interfaces;

use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Invoicing\Models\Invoice;

interface ReminderChannelInterface
{
    public function send(Invoice $invoice): Reminder;
}
'

write "$MODULES/PME/Collection/Models/Reminder.php" '<?php

namespace Modules\PME\Collection\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Enums\ReminderStatus;
use Modules\Shared\Traits\HasUlid;

class Reminder extends Model
{
    use HasUlid, SoftDeletes;

    protected $fillable = [
        '\''invoice_id'\'', '\''channel'\'', '\''status'\'', '\''sent_at'\'',
        '\''message_body'\'', '\''recipient_phone'\'', '\''recipient_email'\'',
    ];

    protected $casts = [
        '\''sent_at'\'' => '\''datetime'\'',
        '\''channel'\'' => ReminderChannel::class,
        '\''status'\''  => ReminderStatus::class,
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\Modules\PME\Invoicing\Models\Invoice::class);
    }
}
'

write "$MODULES/PME/Collection/Models/ReminderRule.php" '<?php

namespace Modules\PME\Collection\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Traits\HasUlid;

class ReminderRule extends Model
{
    use HasUlid;

    protected $fillable = [
        '\''company_id'\'', '\''name'\'', '\''trigger_days'\'', '\''channel'\'', '\''template'\'', '\''is_active'\'',
    ];

    protected $casts = [
        '\''is_active'\''    => '\''boolean'\'',
        '\''trigger_days'\'' => '\''integer'\'',
    ];
}
'

write "$MODULES/PME/Collection/Services/ReminderService.php" '<?php

namespace Modules\PME\Collection\Services;

use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\Company;
use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Interfaces\ReminderChannelInterface;
use Modules\PME\Collection\Models\Reminder;
use Modules\PME\Invoicing\Models\Invoice;
use Modules\Shared\Services\QuotaService;

class ReminderService
{
    public function __construct(
        private QuotaService $quotaService,
        private WhatsAppReminderService $whatsApp,
        private SmsReminderService $sms,
        private EmailReminderService $email,
    ) {}

    public function send(Invoice $invoice, Company $company, ReminderChannel $channel): Reminder
    {
        $this->quotaService->authorize($company, '\''reminders'\'');

        return DB::transaction(function () use ($invoice, $company, $channel) {
            $reminder = $this->resolveChannel($channel)->send($invoice);
            $this->quotaService->consume($company, '\''reminders'\'');
            return $reminder;
        });
    }

    private function resolveChannel(ReminderChannel $channel): ReminderChannelInterface
    {
        return match ($channel) {
            ReminderChannel::WhatsApp => $this->whatsApp,
            ReminderChannel::Sms      => $this->sms,
            ReminderChannel::Email    => $this->email,
        };
    }
}
'

for svc in WhatsAppReminderService SmsReminderService EmailReminderService; do
write "$MODULES/PME/Collection/Services/${svc}.php" "<?php

namespace Modules\\PME\\Collection\\Services;

use Modules\\PME\\Collection\\Interfaces\\ReminderChannelInterface;
use Modules\\PME\\Collection\\Models\\Reminder;
use Modules\\PME\\Invoicing\\Models\\Invoice;

class ${svc} implements ReminderChannelInterface
{
    public function send(Invoice \$invoice): Reminder
    {
        // TODO: implement ${svc}
        throw new \\RuntimeException('${svc}::send() not yet implemented.');
    }
}
"
done

write "$MODULES/PME/Collection/Jobs/SendReminderJob.php" '<?php

namespace Modules\PME\Collection\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Auth\Models\Company;
use Modules\PME\Collection\Enums\ReminderChannel;
use Modules\PME\Collection\Services\ReminderService;
use Modules\PME\Invoicing\Models\Invoice;

class SendReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice         $invoice,
        public readonly Company         $company,
        public readonly ReminderChannel $channel,
    ) {}

    public function handle(ReminderService $service): void
    {
        $service->send($this->invoice, $this->company, $this->channel);
    }
}
'

write "$MODULES/PME/Collection/Policies/ReminderPolicy.php" '<?php

namespace Modules\PME\Collection\Policies;

use Modules\PME\Collection\Models\Reminder;
use Modules\Shared\Models\User;

class ReminderPolicy
{
    public function create(User $user): bool
    {
        return $user->companies()->where('\''type'\'', '\''sme'\'')->exists();
    }

    public function view(User $user, Reminder $reminder): bool
    {
        return $user->companies()
            ->where('\''companies.id'\'', $reminder->invoice->company_id)
            ->exists();
    }
}
'

write "$MODULES/PME/Collection/Providers/CollectionServiceProvider.php" '<?php

namespace Modules\PME\Collection\Providers;

use Illuminate\Support\ServiceProvider;

class CollectionServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '\''/../database/migrations'\'');
        $this->loadViewsFrom(__DIR__ . '\''/../resources/views'\'', '\''collection'\'');
    }
}
'

# =============================================================================
# PME — Treasury
# =============================================================================
info "=== PME / Treasury ==="

mdir "$MODULES/PME/Treasury/Http/Controllers"
mdir "$MODULES/PME/Treasury/Livewire"
mdir "$MODULES/PME/Treasury/Services"
mdir "$MODULES/PME/Treasury/Providers"
mdir "$MODULES/PME/Treasury/database/migrations"
mdir "$MODULES/PME/Treasury/routes"
mdir "$MODULES/PME/Treasury/resources/views"
mdir "$MODULES/PME/Treasury/tests/Feature"

for svc in TreasuryService ForecastService; do
write "$MODULES/PME/Treasury/Services/${svc}.php" "<?php

namespace Modules\\PME\\Treasury\\Services;

class ${svc}
{
    // TODO: implement
}
"
done

write "$MODULES/PME/Treasury/Providers/TreasuryServiceProvider.php" '<?php

namespace Modules\PME\Treasury\Providers;

use Illuminate\Support\ServiceProvider;

class TreasuryServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '\''/../database/migrations'\'');
        $this->loadViewsFrom(__DIR__ . '\''/../resources/views'\'', '\''treasury'\'');
    }
}
'

# ---------- PME parent provider ----------
write "$MODULES/PME/Providers/PmeModuleServiceProvider.php" '<?php

namespace Modules\PME\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\PME\Clients\Providers\ClientsServiceProvider;
use Modules\PME\Collection\Providers\CollectionServiceProvider;
use Modules\PME\Invoicing\Providers\InvoicingServiceProvider;
use Modules\PME\Treasury\Providers\TreasuryServiceProvider;

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
'

# =============================================================================
# COMPTA — Portfolio
# =============================================================================
info "=== Compta / Portfolio ==="

mdir "$MODULES/Compta/Portfolio/Http/Controllers"
mdir "$MODULES/Compta/Portfolio/Http/Requests"
mdir "$MODULES/Compta/Portfolio/Livewire"
mdir "$MODULES/Compta/Portfolio/Services"
mdir "$MODULES/Compta/Portfolio/Providers"
mdir "$MODULES/Compta/Portfolio/database/migrations"
mdir "$MODULES/Compta/Portfolio/routes"
mdir "$MODULES/Compta/Portfolio/resources/views"
mdir "$MODULES/Compta/Portfolio/tests/Feature"

write "$MODULES/Compta/Portfolio/Services/PortfolioService.php" '<?php

namespace Modules\Compta\Portfolio\Services;

use Illuminate\Support\Collection;
use Modules\Auth\Models\AccountantCompany;
use Modules\Auth\Models\Company;
use Modules\PME\Invoicing\Models\Invoice;

class PortfolioService
{
    public function activeSmeIds(Company $firm): Collection
    {
        return AccountantCompany::where('\''accountant_firm_id'\'', $firm->id)
            ->whereNull('\''ended_at'\'')
            ->pluck('\''sme_company_id'\'');
    }

    public function invoicesForFirm(Company $firm): \Illuminate\Database\Eloquent\Builder
    {
        return Invoice::whereIn('\''company_id'\'', $this->activeSmeIds($firm));
    }
}
'

write "$MODULES/Compta/Portfolio/Providers/PortfolioServiceProvider.php" '<?php

namespace Modules\Compta\Portfolio\Providers;

use Illuminate\Support\ServiceProvider;

class PortfolioServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '\''/../database/migrations'\'');
        $this->loadViewsFrom(__DIR__ . '\''/../resources/views'\'', '\''portfolio'\'');
    }
}
'

# =============================================================================
# COMPTA — Export
# =============================================================================
info "=== Compta / Export ==="

mdir "$MODULES/Compta/Export/Http/Controllers"
mdir "$MODULES/Compta/Export/Http/Requests"
mdir "$MODULES/Compta/Export/Livewire"
mdir "$MODULES/Compta/Export/Services"
mdir "$MODULES/Compta/Export/Interfaces"
mdir "$MODULES/Compta/Export/Enums"
mdir "$MODULES/Compta/Export/Jobs"
mdir "$MODULES/Compta/Export/Providers"
mdir "$MODULES/Compta/Export/database/migrations"
mdir "$MODULES/Compta/Export/routes"
mdir "$MODULES/Compta/Export/resources/views"
mdir "$MODULES/Compta/Export/tests/Feature"

write "$MODULES/Compta/Export/Enums/ExportFormat.php" '<?php

namespace Modules\Compta\Export\Enums;

enum ExportFormat: string
{
    case Sage100 = '\''sage100'\'';
    case Ebp     = '\''ebp'\'';
    case Excel   = '\''excel'\'';
}
'

write "$MODULES/Compta/Export/Interfaces/AccountingExporterInterface.php" '<?php

namespace Modules\Compta\Export\Interfaces;

use Illuminate\Support\Collection;

interface AccountingExporterInterface
{
    public function export(Collection $invoices): string;
    public function mimeType(): string;
    public function filename(string $period): string;
}
'

for svc in ExportService SageExporter EbpExporter ExcelExporter; do
write "$MODULES/Compta/Export/Services/${svc}.php" "<?php

namespace Modules\\Compta\\Export\\Services;

class ${svc}
{
    // TODO: implement
}
"
done

write "$MODULES/Compta/Export/Providers/ExportServiceProvider.php" '<?php

namespace Modules\Compta\Export\Providers;

use Illuminate\Support\ServiceProvider;

class ExportServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '\''/../database/migrations'\'');
        $this->loadViewsFrom(__DIR__ . '\''/../resources/views'\'', '\''export'\'');
    }
}
'

# =============================================================================
# COMPTA — Partnership
# =============================================================================
info "=== Compta / Partnership ==="

mdir "$MODULES/Compta/Partnership/Http/Controllers"
mdir "$MODULES/Compta/Partnership/Http/Requests"
mdir "$MODULES/Compta/Partnership/Livewire"
mdir "$MODULES/Compta/Partnership/Models"
mdir "$MODULES/Compta/Partnership/Services"
mdir "$MODULES/Compta/Partnership/Enums"
mdir "$MODULES/Compta/Partnership/Jobs"
mdir "$MODULES/Compta/Partnership/Providers"
mdir "$MODULES/Compta/Partnership/database/migrations"
mdir "$MODULES/Compta/Partnership/routes"
mdir "$MODULES/Compta/Partnership/resources/views"
mdir "$MODULES/Compta/Partnership/tests/Feature"

write "$MODULES/Compta/Partnership/Enums/PartnerTier.php" '<?php

namespace Modules\Compta\Partnership\Enums;

enum PartnerTier: string
{
    case Partner  = '\''partner'\'';
    case Gold     = '\''gold'\'';
    case Platinum = '\''platinum'\'';

    public static function fromActiveClients(int $count): self
    {
        return match (true) {
            $count >= 15 => self::Platinum,
            $count >= 5  => self::Gold,
            default      => self::Partner,
        };
    }
}
'

write "$MODULES/Compta/Partnership/Models/PartnerInvitation.php" '<?php

namespace Modules\Compta\Partnership\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Traits\HasUlid;

class PartnerInvitation extends Model
{
    use HasUlid;

    protected $fillable = [
        '\''accountant_firm_id'\'', '\''token'\'', '\''invitee_phone'\'', '\''invitee_name'\'',
        '\''recommended_plan'\'', '\''status'\'', '\''expires_at'\'', '\''accepted_at'\'', '\''sme_company_id'\'',
    ];

    protected $casts = [
        '\''expires_at'\''  => '\''datetime'\'',
        '\''accepted_at'\'' => '\''datetime'\'',
    ];

    public function accountantFirm(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\Company::class, '\''accountant_firm_id'\'');
    }
}
'

write "$MODULES/Compta/Partnership/Models/Commission.php" '<?php

namespace Modules\Compta\Partnership\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Traits\HasUlid;

class Commission extends Model
{
    use HasUlid;

    protected $fillable = [
        '\''accountant_firm_id'\'', '\''sme_company_id'\'', '\''subscription_id'\'',
        '\''amount'\'', '\''period_month'\'', '\''status'\'', '\''paid_at'\'',
    ];

    protected $casts = [
        '\''amount'\''  => '\''integer'\'',
        '\''paid_at'\'' => '\''datetime'\'',
    ];
}
'

for svc in CommissionService InvitationService; do
write "$MODULES/Compta/Partnership/Services/${svc}.php" "<?php

namespace Modules\\Compta\\Partnership\\Services;

class ${svc}
{
    // TODO: implement
}
"
done

write "$MODULES/Compta/Partnership/Providers/PartnershipServiceProvider.php" '<?php

namespace Modules\Compta\Partnership\Providers;

use Illuminate\Support\ServiceProvider;

class PartnershipServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '\''/../database/migrations'\'');
        $this->loadViewsFrom(__DIR__ . '\''/../resources/views'\'', '\''partnership'\'');
    }
}
'

# =============================================================================
# COMPTA — Compliance
# =============================================================================
info "=== Compta / Compliance ==="

mdir "$MODULES/Compta/Compliance/Http/Controllers"
mdir "$MODULES/Compta/Compliance/Services"
mdir "$MODULES/Compta/Compliance/Interfaces"
mdir "$MODULES/Compta/Compliance/Enums"
mdir "$MODULES/Compta/Compliance/DTOs"
mdir "$MODULES/Compta/Compliance/Providers"
mdir "$MODULES/Compta/Compliance/database/migrations"
mdir "$MODULES/Compta/Compliance/routes"
mdir "$MODULES/Compta/Compliance/tests/Feature"

write "$MODULES/Compta/Compliance/Enums/FiscalCountry.php" '<?php

namespace Modules\Compta\Compliance\Enums;

enum FiscalCountry: string
{
    case Senegal    = '\''SN'\'';
    case IvoryCoast = '\''CI'\'';
}
'

write "$MODULES/Compta/Compliance/Interfaces/FiscalConnectorInterface.php" '<?php

namespace Modules\Compta\Compliance\Interfaces;

use Modules\Compta\Compliance\DTOs\FiscalCertification;
use Modules\PME\Invoicing\Models\Invoice;

interface FiscalConnectorInterface
{
    public function certify(Invoice $invoice): FiscalCertification;
    public function supportsCountry(string $countryCode): bool;
}
'

write "$MODULES/Compta/Compliance/DTOs/FiscalCertification.php" '<?php

namespace Modules\Compta\Compliance\DTOs;

final class FiscalCertification
{
    public function __construct(
        public readonly string $reference,
        public readonly string $token,
        public readonly ?int   $balanceSticker,
        public readonly array  $rawResponse,
    ) {}
}
'

write "$MODULES/Compta/Compliance/DTOs/FneInvoicePayload.php" '<?php

namespace Modules\Compta\Compliance\DTOs;

use Modules\PME\Invoicing\Models\Invoice;

class FneInvoicePayload
{
    public static function fromInvoice(Invoice $invoice): array
    {
        return [
            '\''invoiceType'\''       => '\''sale'\'',
            '\''paymentMethod'\''     => '\''mobile-money'\'',
            '\''template'\''          => '\''B2B'\'',
            '\''clientNcc'\''         => $invoice->client?->tax_id,
            '\''clientCompanyName'\'' => $invoice->client?->name ?? '\'\'\'',
            '\''clientPhone'\''       => $invoice->client?->phone ?? '\'\'\'',
            '\''clientEmail'\''       => $invoice->client?->email ?? '\'\'\'',
            '\''pointOfSale'\''       => $invoice->company->name,
            '\''establishment'\''     => $invoice->company->name,
            '\''items'\''             => $invoice->lines->map(fn($l) => [
                '\''taxes'\''          => ['\''TVA'\''],
                '\''description'\''    => $l->description,
                '\''quantity'\''       => $l->quantity,
                '\''amount'\''         => $l->unit_price,
                '\''discount'\''       => $l->discount ?? 0,
                '\''measurementUnit'\'' => '\''pcs'\'',
            ])->toArray(),
        ];
    }
}
'

write "$MODULES/Compta/Compliance/Services/FneConnector.php" '<?php

namespace Modules\Compta\Compliance\Services;

use Illuminate\Support\Facades\Http;
use Modules\Compta\Compliance\DTOs\FiscalCertification;
use Modules\Compta\Compliance\DTOs\FneInvoicePayload;
use Modules\Compta\Compliance\Interfaces\FiscalConnectorInterface;
use Modules\PME\Invoicing\Models\Invoice;

class FneConnector implements FiscalConnectorInterface
{
    public function certify(Invoice $invoice): FiscalCertification
    {
        $url = rtrim(config('\''fayeku.fne_api_url'\''), '\''/\'\'');

        $response = Http::withToken(env('\''FNE_API_KEY'\''))
            ->post("{$url}/external/invoices/sign", FneInvoicePayload::fromInvoice($invoice));

        if (! $response->successful()) {
            throw new \RuntimeException('\''FNE certification failed: '\'' . $response->body());
        }

        $data = $response->json();

        return new FiscalCertification(
            reference:      $data['\''reference'\''],
            token:          $data['\''token'\''],
            balanceSticker: $data['\''balance_sticker'\''] ?? null,
            rawResponse:    $data,
        );
    }

    public function supportsCountry(string $countryCode): bool
    {
        return $countryCode === '\''CI'\'';
    }
}
'

write "$MODULES/Compta/Compliance/Services/DgidConnector.php" '<?php

namespace Modules\Compta\Compliance\Services;

use Modules\Compta\Compliance\DTOs\FiscalCertification;
use Modules\Compta\Compliance\Interfaces\FiscalConnectorInterface;
use Modules\PME\Invoicing\Models\Invoice;

class DgidConnector implements FiscalConnectorInterface
{
    public function certify(Invoice $invoice): FiscalCertification
    {
        // DGID API not yet published.
        // DO NOT invent endpoints. Update this connector when the DGID publishes their API.
        throw new \RuntimeException(
            '\''DGID API not yet available. Certification skipped for SN invoices.'\''
        );
    }

    public function supportsCountry(string $countryCode): bool
    {
        return $countryCode === '\''SN'\'';
    }
}
'

write "$MODULES/Compta/Compliance/Services/ComplianceService.php" '<?php

namespace Modules\Compta\Compliance\Services;

use Modules\Compta\Compliance\Interfaces\FiscalConnectorInterface;
use Modules\PME\Invoicing\Models\Invoice;

class ComplianceService
{
    /** @param FiscalConnectorInterface[] $connectors */
    public function __construct(private array $connectors) {}

    public function certify(Invoice $invoice): void
    {
        $country   = $invoice->company->country_code;
        $connector = collect($this->connectors)->first(
            fn($c) => $c->supportsCountry($country)
        );

        if (! $connector) {
            return; // no connector for this country — skip gracefully
        }

        try {
            $cert = $connector->certify($invoice);
            $invoice->update([
                '\''fne_reference'\''       => $cert->reference,
                '\''fne_token'\''           => $cert->token,
                '\''fne_certified_at'\''    => now(),
                '\''fne_balance_sticker'\'' => $cert->balanceSticker,
                '\''fne_raw_response'\''    => $cert->rawResponse,
                '\''status'\''              => '\''certified'\'',
            ]);
        } catch (\RuntimeException $e) {
            $invoice->update(['\''status'\'' => '\''certification_failed'\'']);
            throw $e;
        }
    }
}
'

write "$MODULES/Compta/Compliance/Providers/ComplianceServiceProvider.php" '<?php

namespace Modules\Compta\Compliance\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Compta\Compliance\Services\ComplianceService;
use Modules\Compta\Compliance\Services\DgidConnector;
use Modules\Compta\Compliance\Services\FneConnector;

class ComplianceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ComplianceService::class, fn() => new ComplianceService([
            new FneConnector(),
            new DgidConnector(),
        ]));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '\''/../database/migrations'\'');
    }
}
'

# ---------- Compta parent provider ----------
write "$MODULES/Compta/Providers/ComptaModuleServiceProvider.php" '<?php

namespace Modules\Compta\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Compta\Compliance\Providers\ComplianceServiceProvider;
use Modules\Compta\Export\Providers\ExportServiceProvider;
use Modules\Compta\Partnership\Providers\PartnershipServiceProvider;
use Modules\Compta\Portfolio\Providers\PortfolioServiceProvider;

class ComptaModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(PortfolioServiceProvider::class);
        $this->app->register(ExportServiceProvider::class);
        $this->app->register(PartnershipServiceProvider::class);
        $this->app->register(ComplianceServiceProvider::class);
    }
}
'

success "All module files created."
echo ""

# =============================================================================
# PATCH: delegate to scaffold_patch.php
# =============================================================================
info "=== Patching Laravel config files ==="
php "$ROOT/scaffold_patch.php" "$ROOT"
echo ""

# =============================================================================
# composer dump-autoload
# =============================================================================
info "=== Running composer dump-autoload ==="
composer dump-autoload --quiet && success "Autoload refreshed."
echo ""

echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}  Fayeku scaffolding complete.${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""
echo "Next steps:"
echo "  1.  php artisan queue:table && php artisan queue:failed-table"
echo "  2.  Write migrations in each module's database/migrations/"
echo "  3.  php artisan migrate"
echo "  4.  php artisan db:seed --class=Modules\\\\Shared\\\\Database\\\\Seeders\\\\PlanDefinitionSeeder"
echo "  5.  php artisan test"
echo ""
