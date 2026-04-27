@props(['showTeam' => true])

<flux:dropdown position="bottom" align="end">
    <flux:navbar.item>Account</flux:navbar.item>

    <flux:menu class="min-w-56">
        @if($showTeam)
            <livewire:team-switcher />
            <flux:menu.separator />
        @endif

        <flux:menu.item :href="route('profile.edit')" wire:navigate>
            {{ __('Settings') }}
        </flux:menu.item>

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
