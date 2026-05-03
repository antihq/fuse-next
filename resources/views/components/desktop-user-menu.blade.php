@props(['showTeam' => true])

<flux:dropdown position="bottom" align="end">
    <flux:navbar.item>Account</flux:navbar.item>

    <flux:menu class="min-w-56">
        <flux:menu.heading>{{ __('Account') }}</flux:menu.heading>
        <flux:menu.item :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:menu.item>
        <flux:menu.item :href="route('security.edit')" wire:navigate>{{ __('Security') }}</flux:menu.item>
        <flux:menu.item :href="route('ssh-keys.index')" wire:navigate>{{ __('SSH Keys') }}</flux:menu.item>
        <flux:menu.item :href="route('appearance.edit')" wire:navigate>{{ __('Appearance') }}</flux:menu.item>

        <flux:menu.separator />

        @if(auth()->user()->currentTeam)
            <flux:menu.item :href="route('teams.edit', auth()->user()->currentTeam->slug)" wire:navigate>{{ __('Team Settings') }}</flux:menu.item>
        @endif
        <flux:menu.item :href="route('teams.index')" wire:navigate>{{ __('Manage Teams') }}</flux:menu.item>

        @if($showTeam)
            <flux:menu.separator />
            <livewire:team-switcher />
        @endif

        <flux:menu.separator />

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item
                as="button"
                type="submit"
                class="w-full cursor-pointer"
                data-test="logout-button"
            >
                {{ __('Log out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
