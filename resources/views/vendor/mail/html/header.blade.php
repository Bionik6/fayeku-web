@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if (filled($logo = config('mail.logo_url')))
<img src="{{ $logo }}" class="logo" alt="Fayeku" width="64" height="64">
@endif
<div class="header-brand">Fayeku</div>
</a>
</td>
</tr>
