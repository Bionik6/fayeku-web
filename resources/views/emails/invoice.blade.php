<x-mail::message>
{!! nl2br(e($messageBody)) !!}

<x-mail::button :url="config('app.url')">
{{ __('Voir sur Fayeku') }}
</x-mail::button>

{{ $companyName }}
</x-mail::message>
