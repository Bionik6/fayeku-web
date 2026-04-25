<?php

use App\Services\Shared\WhatsAppBusinessProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function makeWhatsAppProvider(): WhatsAppBusinessProvider
{
    return new WhatsAppBusinessProvider(
        baseUrl: 'https://graph.facebook.com',
        apiVersion: 'v21.0',
        phoneNumberId: '1234567890',
        accessToken: 'test-access-token',
        defaultLanguage: 'fr',
    );
}

test('sendTemplate poste un payload avec variables nommees et bouton URL', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.xxx']]], 200),
    ]);

    $ok = makeWhatsAppProvider()->sendTemplate(
        '+221771112233',
        'fayeku_reminder_invoice_due_auto_p15',
        [
            'client_name' => 'Dakar Pharma',
            'company_name' => 'Sow BTP',
            'invoice_number' => 'FAC-001',
            'invoice_amount' => '250 000 FCFA',
            'due_date' => '15 avril 2026',
            'sender_signature' => 'Ibrahima Ciss, Manager Sow BTP',
        ],
        urlButtonParameter: 'hg59skf/pdf',
    );

    expect($ok)->toBeTrue();

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $components = $body['template']['components'];

        return $request->url() === 'https://graph.facebook.com/v21.0/1234567890/messages'
            && $request->hasHeader('Authorization', 'Bearer test-access-token')
            && $body['to'] === '221771112233'
            && $body['type'] === 'template'
            && $body['template']['name'] === 'fayeku_reminder_invoice_due_auto_p15'
            && $body['template']['language']['code'] === 'fr'
            && $components[0]['type'] === 'body'
            && count($components[0]['parameters']) === 6
            && $components[0]['parameters'][0] === ['type' => 'text', 'text' => 'Dakar Pharma', 'parameter_name' => 'client_name']
            && $components[0]['parameters'][2] === ['type' => 'text', 'text' => 'FAC-001', 'parameter_name' => 'invoice_number']
            && $components[1]['type'] === 'button'
            && $components[1]['sub_type'] === 'url'
            && $components[1]['index'] === '0'
            && $components[1]['parameters'][0] === ['type' => 'text', 'text' => 'hg59skf/pdf'];
    });
});

test('sendTemplate accepte aussi des variables positionnelles', function () {
    Http::fake(['graph.facebook.com/*' => Http::response([], 200)]);

    makeWhatsAppProvider()->sendTemplate(
        '+221771112233',
        'hello_world',
        ['Jean'],
    );

    Http::assertSent(function (Request $request) {
        $param = $request->data()['template']['components'][0]['parameters'][0];

        return $param === ['type' => 'text', 'text' => 'Jean'];
    });
});

test('send poste un texte simple via Meta', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.yyy']]], 200),
    ]);

    $ok = makeWhatsAppProvider()->send('+221770000000', 'Hello');

    expect($ok)->toBeTrue();

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $body['type'] === 'text'
            && $body['text']['body'] === 'Hello'
            && $body['to'] === '221770000000';
    });
});

test('sendTemplate retourne false et log lorsque Meta repond une erreur', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid template']], 400),
    ]);

    $ok = makeWhatsAppProvider()->sendTemplate('+221771112233', 'inexistant');

    expect($ok)->toBeFalse();
});

test('send retourne false sans crasher quand Meta est injoignable (SSL, timeout, DNS)', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 60: SSL certificate problem');
    });

    $ok = makeWhatsAppProvider()->send('+221770000000', 'Hello');

    expect($ok)->toBeFalse();
});
