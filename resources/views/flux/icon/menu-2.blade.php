@php $attributes = $unescapedForwardedAttributes ?? $attributes; @endphp

@props([
    'variant' => 'outline',
])

@php
$classes = Flux::classes('shrink-0')
    ->add(match($variant) {
        'outline' => '[:where(&)]:size-6',
        'solid' => '[:where(&)]:size-6',
        'mini' => '[:where(&)]:size-5',
        'micro' => '[:where(&)]:size-4',
    });
@endphp

<svg {{ $attributes->class($classes) }} data-flux-icon aria-hidden="true" viewBox="0 0 24 24" fill="none">
    <path d="M3 6H21M3 12H11M3 18H16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
</svg>
