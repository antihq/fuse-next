<?php

use App\Actions\Servers\CreateServer;
use App\Models\Server;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Connect Server')] class extends Component
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

<form wire:submit="create" class="space-y-8">
    <div class="max-w-prose">
        <flux:heading size="lg">{{ __('Connect a new server') }}</flux:heading>
        <flux:text class="mt-1">
            {{ __('Enter the public IP address of your VPS.') }}
        </flux:text>
    </div>

    <div class="max-w-md space-y-8">
        <flux:input
            label="{{ __('IP Address') }}"
            wire:model="ipAddress"
            placeholder="203.0.113.42"
            required
            autofocus
            data-test="add-server-ip"
        />

        <div class="flex gap-3">
            <flux:spacer />
            <flux:button
                :href="route('servers.index', ['current_team' => $this->team->slug])"
                variant="ghost"
                wire:navigate
            >
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary" data-test="add-server-submit" color="blue" icon:trailing="arrow-right">
                {{ __('Connect server') }}
            </flux:button>
        </div>
    </div>
</form>
