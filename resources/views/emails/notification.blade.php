@php
    // Convertit le format WhatsApp (*gras*, _italique_, \n) en HTML inline pour
    // que le rendu email soit propre sans dépendre de la passe Markdown.
    $escaped = e($body);
    $html = preg_replace(
        ['/\*([^\*\n]+)\*/', '/_([^_\n]+)_/'],
        ['<strong>$1</strong>', '<em>$1</em>'],
        $escaped,
    );
    $html = nl2br($html);
@endphp
<x-mail::message>
{!! $html !!}

@if ($ctaUrl)
<x-mail::button :url="$ctaUrl">
{{ $ctaLabel ?? 'Voir le document' }}
</x-mail::button>
@endif

</x-mail::message>
