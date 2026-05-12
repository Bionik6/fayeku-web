<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title ?? null])
    </head>
    <body>
        <div class="min-h-screen overflow-x-hidden bg-[#024D4D]">
            {{ $slot }}
        </div>
        @livewireScripts
        @fluxScripts
    </body>
</html>
