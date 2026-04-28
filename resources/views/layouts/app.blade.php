<x-layouts::app.header :title="$title ?? null">
    <flux:main class="mx-auto w-full [:where(&)]:max-w-4xl bg-white ring-1 ring-zinc-800/15 dark:bg-zinc-900 dark:ring-white/20 p-6! lg:p-10! shadow-xs rounded-lg">
        {{ $slot }}
    </flux:main>
</x-layouts::app.header>
