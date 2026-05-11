<x-mail::message>
# Nouveau contact marketing

**Nom :** {{ $payload['full_name'] }}

@if (! empty($payload['company']))
**Entreprise :** {{ $payload['company'] }}
@endif

**Email :** {{ $payload['email'] }}

@if (! empty($payload['phone']))
**Téléphone :** {{ $payload['phone'] }}
@endif

**Profil :** {{ ['pme' => 'PME', 'expert' => 'Expert-comptable', 'autre' => 'Autre'][$payload['profile']] ?? $payload['profile'] }}

**Message :**

{{ $payload['message'] }}

---

*Reçu le {{ now()->translatedFormat('d F Y à H\hi') }}.*

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
