<?php

/**
 * Fayeku — scaffold_patch.php
 *
 * Patches the existing Laravel files that scaffold.sh cannot safely touch
 * due to bash quoting limitations:
 *   - composer.json          → adds Modules\ autoload
 *   - bootstrap/providers.php → registers top-level module providers
 *   - bootstrap/app.php       → registers middleware aliases
 *   - config/auth.php         → points Eloquent user model to Modules\Shared\Models\User
 *   - .env                    → appends missing Fayeku-specific variables
 *
 * Usage (called automatically by scaffold.sh):
 *   php scaffold_patch.php /path/to/laravel/root
 */
$root = rtrim($argv[1] ?? getcwd(), '/');

$green = "\033[0;32m";
$yellow = "\033[1;33m";
$red = "\033[0;31m";
$nc = "\033[0m";

function ok(string $msg): void
{
    global $green, $nc;
    echo "{$green}[ok]{$nc}    {$msg}\n";
}
function warn(string $msg): void
{
    global $yellow, $nc;
    echo "{$yellow}[warn]{$nc}  {$msg}\n";
}
function fail(string $msg): void
{
    global $red, $nc;
    echo "{$red}[fail]{$nc}  {$msg}\n";
    exit(1);
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. composer.json — add Modules\ PSR-4 autoload
// ─────────────────────────────────────────────────────────────────────────────
$composerFile = "{$root}/composer.json";
if (! file_exists($composerFile)) {
    fail("composer.json not found at {$composerFile}");
}

$composer = json_decode(file_get_contents($composerFile), true);

if (isset($composer['autoload']['psr-4']['Modules\\'])) {
    warn('composer.json already has Modules\\ autoload — skipped.');
} else {
    $composer['autoload']['psr-4']['Modules\\'] = 'modules/';
    file_put_contents(
        $composerFile,
        json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
    );
    ok('composer.json — added Modules\\ => modules/');
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. bootstrap/providers.php — register top-level module providers
// ─────────────────────────────────────────────────────────────────────────────
$providersFile = "{$root}/bootstrap/providers.php";
if (! file_exists($providersFile)) {
    fail('bootstrap/providers.php not found.');
}

$providersContent = file_get_contents($providersFile);

$toRegister = [
    'Modules\\Shared\\Providers\\SharedServiceProvider::class',
    'Modules\\Auth\\Providers\\AuthModuleServiceProvider::class',
    'Modules\\PME\\Providers\\PmeModuleServiceProvider::class',
    'Modules\\Compta\\Providers\\ComptaModuleServiceProvider::class',
];

foreach ($toRegister as $provider) {
    if (str_contains($providersContent, $provider)) {
        warn("providers.php — already registered: {$provider}");
    } else {
        // Insert before the last ]; in the file
        $providersContent = preg_replace(
            '/(\];)\s*$/',
            "    {$provider},\n$1",
            $providersContent
        );
        ok("providers.php — registered: {$provider}");
    }
}

file_put_contents($providersFile, $providersContent);

// ─────────────────────────────────────────────────────────────────────────────
// 3. bootstrap/app.php — add middleware aliases
// ─────────────────────────────────────────────────────────────────────────────
$appFile = "{$root}/bootstrap/app.php";
if (! file_exists($appFile)) {
    fail('bootstrap/app.php not found.');
}

$appContent = file_get_contents($appFile);

if (str_contains($appContent, 'EnsureProfileType')) {
    warn('bootstrap/app.php — middleware aliases already registered — skipped.');
} else {
    $middlewareBlock = "\n    ->withMiddleware(function (\\Illuminate\\Foundation\\Configuration\\Middleware \$middleware) {\n";
    $middlewareBlock .= "        \$middleware->alias([\n";
    $middlewareBlock .= "            'profile'        => \\Modules\\Shared\\Middleware\\EnsureProfileType::class,\n";
    $middlewareBlock .= "            'verified.phone' => \\Modules\\Shared\\Middleware\\EnsurePhoneVerified::class,\n";
    $middlewareBlock .= "        ]);\n";
    $middlewareBlock .= '    })';

    // Insert the withMiddleware() call just before ->create()
    $appContent = str_replace(
        '->create()',
        $middlewareBlock."\n    ->create()",
        $appContent
    );

    file_put_contents($appFile, $appContent);
    ok('bootstrap/app.php — middleware aliases added.');
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. config/auth.php — point to Modules\Shared\Models\User
// ─────────────────────────────────────────────────────────────────────────────
$authConfigFile = "{$root}/config/auth.php";
if (! file_exists($authConfigFile)) {
    warn('config/auth.php not found — skipping.');
} else {
    $authContent = file_get_contents($authConfigFile);

    if (str_contains($authContent, 'Modules\\Shared\\Models\\User')) {
        warn('config/auth.php — already points to Modules\\Shared\\Models\\User — skipped.');
    } else {
        $authContent = str_replace(
            'App\\Models\\User',
            'Modules\\Shared\\Models\\User',
            $authContent
        );
        file_put_contents($authConfigFile, $authContent);
        ok('config/auth.php — updated to Modules\\Shared\\Models\\User.');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. .env — append missing Fayeku variables
// ─────────────────────────────────────────────────────────────────────────────
$envFile = "{$root}/.env";
if (! file_exists($envFile)) {
    warn('.env not found — skipping env patching.');
} else {
    $envContent = file_get_contents($envFile);

    $vars = [
        'OTP_EXPIRY_MINUTES' => '10',
        'OTP_MAX_ATTEMPTS' => '3',
        'SMS_PROVIDER' => 'orange',
        'ORANGE_SMS_API_KEY' => '',
        'ORANGE_SMS_SENDER_ID' => 'Fayeku',
        'WHATSAPP_PROVIDER' => '360dialog',
        'WHATSAPP_API_KEY' => '',
        'WHATSAPP_PHONE_NUMBER_ID' => '',
        'FNE_API_KEY' => '',
        'FNE_API_URL' => '',
        'FNE_TEST_URL' => 'http://54.247.95.108/ws',
    ];

    $toAppend = [];
    foreach ($vars as $key => $default) {
        if (preg_match("/^{$key}=/m", $envContent)) {
            warn(".env — {$key} already set — skipped.");
        } else {
            $toAppend[] = "{$key}={$default}";
            ok(".env — added {$key}");
        }
    }

    if (! empty($toAppend)) {
        $envContent = rtrim($envContent)."\n\n# Fayeku\n".implode("\n", $toAppend)."\n";
        file_put_contents($envFile, $envContent);
    }
}

echo "\n";
ok('All patches applied.');
