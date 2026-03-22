@props([
    'name',
])

@php
    $icons = [
        'dashboard'    => 'resources/icons/dashboard.svg',
        'clients'      => 'resources/icons/users-01.svg',
        'export'       => 'resources/icons/file-export-01.svg',
        'commissions'  => 'resources/icons/wallet-01.svg',
        'invitations'  => 'resources/icons/mail-plus.svg',
        'settings'     => 'resources/icons/gear-01.svg',
        'support'      => 'resources/icons/message-chat-square.svg',
        'logout'       => 'resources/icons/log-out-05.svg',
        'logout-modal' => 'resources/icons/log-out-04.svg',
        'search'       => 'resources/icons/search-01.svg',
        'bell'         => 'resources/icons/bell-notification-01.svg',
        'menu'         => 'resources/icons/menu-bar-01.svg',
        'close'        => 'resources/icons/building-x-mark.svg',
        'invoice'      => 'resources/icons/file-01.svg',
        'user'         => 'resources/icons/user-01.svg',
        'check'        => 'resources/icons/check.svg',
        'restore'      => 'resources/icons/arrow-circle-left.svg',
        'chevron-down' => 'resources/icons/chevron-down.svg',
        'download'     => 'resources/icons/download-01.svg',
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
