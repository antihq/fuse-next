<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-100 dark:bg-zinc-950 antialiased py-2">
        <flux:header class="flex items-center px-6">
            <div class="mx-auto w-full h-full [:where(&)]:max-w-4xl flex flex-wrap items-center px-4">
                <flux:spacer />

                <a href="/" class="inline-flex items-stretch font-serif text-xl/8 text-zinc-950 dark:text-white" wire:navigate>{{ config('app.name') }}</a>

                <flux:spacer />

                <flux:navbar class="w-full justify-between grid grid-cols-3">
                    <div class="flex">
                        <flux:navbar.item :href="route('dashboard')" :current="request()->routeIs('dashboard')" class="data-current:after:rounded-full" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:navbar.item>
                    </div>
                    <div class="flex justify-center">
                        <flux:navbar.item :href="route('servers.index')" :current="request()->routeIs('servers.*')" class="data-current:after:rounded-full" wire:navigate>
                            {{ __('Servers') }}
                        </flux:navbar.item>
                    </div>
                    <div class="flex justify-end">
                        <x-desktop-user-menu class="data-current:after:rounded-full" :showTeam="true" />
                    </div>
                </flux:navbar>
            </div>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
