<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-900 antialiased">
        <flux:header class="border-b border-zinc-950/10 dark:border-white/10 bg-white dark:bg-zinc-900">
            <flux:brand href="#" logo="/logo.svg" logo:dark="/dark-mode-logo.svg" />

            <flux:spacer />

            <flux:navbar class="-mb-px">
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>
                <flux:navbar.item icon="server" :href="route('servers.index')" :current="request()->routeIs('servers.*')" wire:navigate>
                    {{ __('Servers') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <x-desktop-user-menu :showTeam="true" />
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
