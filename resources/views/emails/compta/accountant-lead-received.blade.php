<x-mail::message>
# Bonjour {{ $lead->first_name }},

Nous avons bien reçu la demande de **{{ $lead->firm }}** pour rejoindre Fayeku Compta. Merci !

Un conseiller Fayeku vous contactera **sous 24 heures** pour valider votre accès et planifier la mise en route de votre cabinet.

En attendant, n'hésitez pas à parcourir notre site pour découvrir le programme partenaire et le pilotage des dossiers PME.

À très vite,<br>
L'équipe {{ config('app.name') }}
</x-mail::message>
