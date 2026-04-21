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

<div class="max-w-xl mx-auto">
    <flux:input
        label="{{ __('IP Address') }}"
        wire:model="ipAddress"
        placeholder="192.168.1.1"
        required
        autofocus
        data-test="add-server-ip"
    />

<div class="mt-4 flex">
    <flux:spacer />
    <flux:button wire:click="create" variant="primary" data-test="add-server-submit">
        {{ __('Add server') }}
    </flux:button>
</div>
</div>
