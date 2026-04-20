<?php

use App\Actions\Servers\CreateServer;
use App\Models\Server;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add Server')] class extends Component
{
    public string $ipAddress = '';

    public function create(): void
    {
        $team = Auth::user()->currentTeam;

        $this->authorize('create', [Server::class, $team]);

        $validated = $this->validate([
            'ipAddress' => ['required', 'ip'],
        ]);

        $server = (new CreateServer)->handle($team, $validated['ipAddress']);

        $this->redirectRoute('servers.show', [$team->slug, $server], navigate: true);
    }

    public function getTeamProperty()
    {
        return Auth::user()->currentTeam;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Add Server') }}</flux:heading>

    <div class="mb-6">
        <flux:button
            :href="route('servers.index', [$this->team->slug])"
            variant="ghost"
            size="sm"
            icon="arrow-left"
            wire:navigate
        >
            {{ __('Back to servers') }}
        </flux:button>
    </div>

    <flux:heading>{{ __('Add Server') }}</flux:heading>
    <flux:subheading>{{ $this->team->name }}</flux:subheading>

    <div class="mt-8 max-w-2xl">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-6">{{ __('Server Details') }}</flux:heading>

            <div class="space-y-6">
                <flux:input
                    label="{{ __('IP Address') }}"
                    wire:model="ipAddress"
                    placeholder="192.168.1.1"
                    required
                    autofocus
                    data-test="add-server-ip"
                />
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Enter the IP address of your VPS server') }}</p>
            </div>

            <div class="mt-6">
                <flux:button wire:click="create" class="w-full" data-test="add-server-submit">
                    <span wire:loading.remove>{{ __('Add server') }}</span>
                    <span wire:loading>{{ __('Adding...') }}</span>
                </flux:button>
            </div>
        </div>
    </div>
</section>
