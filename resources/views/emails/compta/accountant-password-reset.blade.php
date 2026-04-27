<x-mail::message>
# Bonjour {{ $firstName ?: 'Cher partenaire' }},

Nous avons reçu une demande de réinitialisation du mot de passe associé à votre compte Fayeku Compta.

Cliquez sur le bouton ci-dessous pour définir un nouveau mot de passe. Le lien est valable **{{ $expiresInMinutes }} minutes**.

<x-mail::button :url="$resetUrl">
Réinitialiser mon mot de passe
</x-mail::button>

Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :

{{ $resetUrl }}

Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email — votre mot de passe ne sera pas modifié.

À très vite,<br>
L'équipe {{ config('app.name') }}
</x-mail::message>
