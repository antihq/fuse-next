<dt
    {{ $attributes->except('class') }}
    @class([
        $attributes->get('class'),
        'col-start-1 border-t border-zinc-950/5 pt-3 text-zinc-950 first:border-none sm:border-t sm:border-zinc-950/5 sm:py-3 dark:border-white/5 dark:text-white sm:dark:border-white/5',
    ])
>
    {{ $slot }}
</dt>
