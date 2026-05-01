<?php

use App\Support\PhoneNumber;

// ─── parse() ─────────────────────────────────────────────────────────────────

test('parse détecte le Sénégal pour un numéro +221', function () {
    $result = PhoneNumber::parse('+221771234567');

    expect($result['country_code'])->toBe('SN')
        ->and($result['local_number'])->toBe('771234567')
        ->and($result['normalized'])->toBe('+221771234567');
});

test('parse détecte le Mali pour un numéro +223', function () {
    $result = PhoneNumber::parse('+22376123456');

    expect($result['country_code'])->toBe('ML')
        ->and($result['local_number'])->toBe('76123456');
});

test('parse détecte le Burkina Faso pour un numéro +226', function () {
    $result = PhoneNumber::parse('+22670123456');

    expect($result['country_code'])->toBe('BF')
        ->and($result['local_number'])->toBe('70123456');
});

test('parse détecte la Côte d\'Ivoire pour un numéro +225', function () {
    $result = PhoneNumber::parse('+2250712345678');

    expect($result['country_code'])->toBe('CI')
        ->and($result['local_number'])->toBe('0712345678');
});

test('parse retombe sur SN quand le numéro est vide (aucun préfixe à matcher)', function () {
    $result = PhoneNumber::parse('');

    expect($result['country_code'])->toBe('SN');
});

// ─── normalize() ─────────────────────────────────────────────────────────────

test('normalize ajoute le préfixe pays sur un numéro local SN', function () {
    expect(PhoneNumber::normalize('771234567', 'SN'))->toBe('+221771234567');
});

test('normalize ajoute le préfixe pays sur un numéro local ML', function () {
    expect(PhoneNumber::normalize('76123456', 'ML'))->toBe('+22376123456');
});

test('normalize ne dédouble pas le préfixe quand l\'utilisateur le tape déjà', function () {
    expect(PhoneNumber::normalize('+22376123456', 'ML'))->toBe('+22376123456')
        ->and(PhoneNumber::normalize('22376123456', 'ML'))->toBe('+22376123456');
});

test('normalize strip le 0 initial du numéro local', function () {
    expect(PhoneNumber::normalize('0771234567', 'SN'))->toBe('+221771234567');
});

test('normalize retourne le format international si l\'utilisateur tape +', function () {
    expect(PhoneNumber::normalize('+22376123456', 'SN'))->toBe('+22376123456');
});

test('normalize utilise un préfixe vide si le pays est inconnu', function () {
    expect(PhoneNumber::normalize('771234567', 'XX'))->toBe('771234567');
});

// ─── digitsForWhatsApp() ─────────────────────────────────────────────────────

test('digitsForWhatsApp produit uniquement des chiffres internationaux pour ML', function () {
    expect(PhoneNumber::digitsForWhatsApp('76123456', 'ML'))->toBe('22376123456')
        ->and(PhoneNumber::digitsForWhatsApp('+22376123456', 'ML'))->toBe('22376123456');
});

test('digitsForWhatsApp produit uniquement des chiffres internationaux pour SN', function () {
    expect(PhoneNumber::digitsForWhatsApp('771234567', 'SN'))->toBe('221771234567');
});

test('digitsForWhatsApp gère les espaces et signes dans le numéro saisi', function () {
    expect(PhoneNumber::digitsForWhatsApp('77 123 45 67', 'SN'))->toBe('221771234567')
        ->and(PhoneNumber::digitsForWhatsApp('77.123.45.67', 'SN'))->toBe('221771234567');
});

test('REGRESSION : un numéro malien saisi avec pays ML produit wa.me/223...', function () {
    // Bug rapporté : saisie "774273273232" avec pays MLI ne produisait pas le préfixe 223.
    $digits = PhoneNumber::digitsForWhatsApp('774273273232', 'ML');

    expect($digits)->toStartWith('223');
});
