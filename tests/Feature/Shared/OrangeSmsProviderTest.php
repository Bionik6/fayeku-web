<?php

use App\Services\Shared\OrangeSmsProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function makeOrangeProvider(?string $senderName = 'Fayeku'): OrangeSmsProvider
{
    return new OrangeSmsProvider(
        baseUrl: 'https://api.orange.com',
        clientId: 'client-id',
        clientSecret: 'client-secret',
        senderAddress: 'tel:+221770000000',
        senderName: $senderName,
        cache: Cache::store('array'),
    );
}

beforeEach(function () {
    Cache::store('array')->clear();
});

test('send recupere un access_token puis envoie le SMS via Orange', function () {
    Http::fake([
        'api.orange.com/oauth/v3/token' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600], 200),
        'api.orange.com/smsmessaging/*' => Http::response([], 201),
    ]);

    $ok = makeOrangeProvider()->send('+221771112233', 'Votre code Fayeku : 123456');

    expect($ok)->toBeTrue();

    Http::assertSent(function (Request $request) {
        if (str_contains($request->url(), '/oauth/v3/token')) {
            return $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret'))
                && $request['grant_type'] === 'client_credentials';
        }

        if (str_contains($request->url(), '/smsmessaging/')) {
            $body = $request->data();

            return $request->hasHeader('Authorization', 'Bearer abc123')
                && str_contains($request->url(), rawurlencode('tel:+221770000000'))
                && $body['outboundSMSMessageRequest']['address'] === 'tel:+221771112233'
                && $body['outboundSMSMessageRequest']['senderAddress'] === 'tel:+221770000000'
                && $body['outboundSMSMessageRequest']['outboundSMSTextMessage']['message'] === 'Votre code Fayeku : 123456';
        }

        return false;
    });
});

test('send reutilise le token en cache entre deux appels', function () {
    Http::fake([
        'api.orange.com/oauth/v3/token' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600], 200),
        'api.orange.com/smsmessaging/*' => Http::response([], 201),
    ]);

    $provider = makeOrangeProvider();
    $provider->send('+221771112233', 'Premier');
    $provider->send('+221771112233', 'Deuxieme');

    $tokenCalls = 0;
    Http::assertSent(function (Request $request) use (&$tokenCalls) {
        if (str_contains($request->url(), '/oauth/v3/token')) {
            $tokenCalls++;
        }

        return true;
    });

    expect($tokenCalls)->toBe(1);
});

test('senderName est omis du payload quand non configure', function () {
    Http::fake([
        'api.orange.com/oauth/v3/token' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600], 200),
        'api.orange.com/smsmessaging/*' => Http::response([], 201),
    ]);

    $ok = makeOrangeProvider(senderName: null)->send('+221771112233', 'Hello');

    expect($ok)->toBeTrue();

    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), '/smsmessaging/')) {
            return true;
        }

        $body = $request->data();

        return ! array_key_exists('senderName', $body['outboundSMSMessageRequest']);
    });
});

test('senderName est aussi omis quand la config est une chaine vide', function () {
    Http::fake([
        'api.orange.com/oauth/v3/token' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600], 200),
        'api.orange.com/smsmessaging/*' => Http::response([], 201),
    ]);

    makeOrangeProvider(senderName: '   ')->send('+221771112233', 'Hello');

    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), '/smsmessaging/')) {
            return true;
        }

        return ! array_key_exists('senderName', $request->data()['outboundSMSMessageRequest']);
    });
});

test('send renvoie false et invalide le cache du token sur un 401', function () {
    Http::fake([
        'api.orange.com/oauth/v3/token' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600], 200),
        'api.orange.com/smsmessaging/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    $ok = makeOrangeProvider()->send('+221771112233', 'Test');

    expect($ok)->toBeFalse()
        ->and(Cache::store('array')->get('orange_sms.access_token'))->toBeNull();
});
