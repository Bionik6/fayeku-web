@props([
    'name',
])

@php
    $icons = [
        'dashboard' => 'templates/duotone-icons-library/User Interface/SVG/dashboard.svg',
        'clients' => 'templates/duotone-icons-library/User & People/SVG/users-01.svg',
        'export' => 'templates/duotone-icons-library/File & Folder/SVG/file-export-01.svg',
        'commissions' => 'templates/duotone-icons-library/Finance & Payment/SVG/wallet-01.svg',
        'invitations' => 'templates/duotone-icons-library/Communication /SVG/mail-plus.svg',
        'settings' => 'templates/duotone-icons-library/User Interface/SVG/gear-01.svg',
        'support' => 'templates/duotone-icons-library/Communication /SVG/message-chat-square.svg',
        'logout' => 'templates/duotone-icons-library/User Interface/SVG/log-out-05.svg',
        'logout-modal' => 'templates/duotone-icons-library/User Interface/SVG/log-out-04.svg',
        'search' => 'templates/duotone-icons-library/User Interface/SVG/search-01.svg',
        'bell' => 'templates/duotone-icons-library/Alerts, Spinner & Toggle/SVG/bell-notification-01.svg',
        'menu' => 'templates/duotone-icons-library/User Interface/SVG/menu-bar-01.svg',
        'close' => 'templates/duotone-icons-library/Building/SVG/building-x-mark.svg',
    ];

    $path = $icons[$name] ?? null;
    $svg = '';

    if ($path && file_exists(base_path($path))) {
        $svg = file_get_contents(base_path($path));
        $svg = str_replace(
            ['fill="#121A26"', 'fill="black"'],
            'fill="currentColor"',
            $svg,
        );
        $svg = preg_replace('/<svg\b([^>]*?)\s*class="[^"]*"([^>]*)>/', '<svg$1$2>', $svg, 1);
        $svg = preg_replace(
            '/<svg\b([^>]*)>/',
            '<svg$1 class="'.e($attributes->get('class', 'size-5')).'" aria-hidden="true">',
            $svg,
            1,
        );
    }
@endphp

@if ($svg !== '')
    {!! $svg !!}
@endif
