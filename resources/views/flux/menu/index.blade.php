@blaze(fold: true)

@php
$classes = Flux::classes()
    ->add('[:where(&)]:min-w-52 p-2')
    ->add('rounded-xl shadow-lg')
    ->add('border border-slate-200')
    ->add('bg-white')
    ->add('focus:outline-hidden')
    ;
@endphp

<ui-menu
    {{ $attributes->class($classes) }}
    popover="manual"
    data-flux-menu
>
    {{ $slot }}
</ui-menu>
