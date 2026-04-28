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

<div>
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('servers.index', ['current_team' => $this->team->slug])" wire:navigate>
            {{ __('Servers') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Connect Server') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="mt-4">
        <flux:heading size="lg">{{ __('Connect a new server') }}</flux:heading>
        <flux:text class="mt-1 max-w-prose">
            {{ __("Enter the public IP address of your VPS. We'll provision everything — Caddy, PHP, queues, and more.") }}
        </flux:text>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8">
        <form wire:submit="create" class="space-y-6">
            <flux:input
                label="{{ __('IP Address') }}"
                wire:model="ipAddress"
                placeholder="203.0.113.42"
                required
                autofocus
                data-test="add-server-ip"
            />

            <flux:button type="submit" variant="primary" data-test="add-server-submit" color="blue" icon:trailing="arrow-right">
                {{ __('Connect server') }}
            </flux:button>
        </form>

        <div>
            <flux:heading>{{ __('Before you begin') }}</flux:heading>
            <div class="w-full rounded-lg ring-1 ring-zinc-950/5 shadow-xs dark:ring-white/10 px-3 border-l-4 border-zinc-800/15 dark:border-white/20 mt-4">
                <flux:table class="whitespace-normal!">
                    <flux:table.rows>
                        <flux:table.row>
                            <flux:table.cell variant="strong">{{ __('Fresh install of Ubuntu 24.04 LTS') }}</flux:table.cell>
                            <flux:table.cell>{{ __('(or latest LTS)') }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell variant="strong">{{ __('Root SSH access') }}</flux:table.cell>
                            <flux:table.cell>{{ __('Make sure you can SSH in as root') }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell variant="strong">{{ __('Public IP address') }}</flux:table.cell>
                            <flux:table.cell>{{ __('Your VPS must be reachable from the internet') }}</flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </div>
            <flux:text class="mt-4">
                {{ __('After connecting, you\'ll get a one-line script to run that installs Caddy, PHP, Composer, Node.js, and everything else needed to deploy Laravel apps.') }}
            </flux:text>
        </div>
    </div>
</div>
