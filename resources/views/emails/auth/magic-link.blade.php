<x-mail::message>
# Bonjour {{ $firstName ?: 'cher utilisateur' }},

Voici votre lien de connexion à Fayeku. Cliquez sur le bouton ci-dessous pour vous connecter directement, sans saisir votre mot de passe.

<x-mail::button :url="$magicUrl">
Me connecter
</x-mail::button>

Ce lien est valable pendant **{{ $expiresInMinutes }} minutes** et ne peut être utilisé qu'une seule fois.

Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :

{{ $magicUrl }}

Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.

À très vite,<br>
L'équipe {{ config('app.name') }}
</x-mail::message>
