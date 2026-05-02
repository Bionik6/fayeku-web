<x-mail::message>
# Votre code de vérification

Voici votre code de vérification Fayeku :

<x-mail::panel>
<div style="font-size: 28px; letter-spacing: 6px; text-align: center; font-weight: 700;">{{ $code }}</div>
</x-mail::panel>

Ce code est valable pendant **{{ $expiresInMinutes }} minutes**.

Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.

À très vite,<br>
L'équipe {{ config('app.name') }}
</x-mail::message>
