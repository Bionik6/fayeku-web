<x-mail::message>
# Nouvelle demande cabinet

**{{ $lead->firm }}** souhaite rejoindre Fayeku Compta.

**Contact:** {{ $lead->full_name }}

**Email:** {{ $lead->email }}

**Téléphone:** {{ $lead->phone }} ({{ $lead->country_code }})

**Région:** {{ $lead->region }}

**Portefeuille déclaré:** {{ $lead->portfolio_size }}

**Message :**
{{ $lead->message }}

---

*Reçu le {{ $lead->created_at->translatedFormat('d F Y à H\hi') }}.*

Pour activer ce cabinet, en attendant l'UI admin :

```
$lead = \App\Models\Compta\AccountantLead::find('{{ $lead->id }}');
app(\App\Services\Compta\AccountantLeadActivator::class)->activate($lead);
```

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
