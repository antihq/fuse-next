<dl
    {{ $attributes->except('class') }}
    @class([
        $attributes->get('class'),
        'grid grid-cols-1 sm:grid-cols-[min(50%,--spacing(32))_auto] text-sm/6',
    ])
>
    {{ $slot }}
</dl>
