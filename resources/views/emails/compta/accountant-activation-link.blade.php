<x-mail::message>
# Bienvenue {{ $lead->first_name }} !

Le cabinet **{{ $lead->firm }}** est désormais activé sur Fayeku Compta.

Cliquez sur le bouton ci-dessous pour définir votre mot de passe et accéder à votre tableau de bord. Le lien est valable **7 jours**.

<x-mail::button :url="$activationUrl">
Activer mon accès
</x-mail::button>

Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :

{{ $activationUrl }}

Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.

À très vite,<br>
L'équipe {{ config('app.name') }}
</x-mail::message>
