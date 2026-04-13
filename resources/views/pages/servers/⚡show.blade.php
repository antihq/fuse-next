<?php

use App\Models\Server;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Server Details')] class extends Component
{
    public Server $server;

    public function mount(Server $server): void
    {
        $this->server = $server;

        $team = Auth::user()->currentTeam;

        $this->authorize('view', [$team, $this->server]);
    }

    public function getProvisioningCommandProperty(): string
    {
        $url = URL::signedRoute('servers.provision-script', ['server' => $this->server]);

        return "wget --no-verbose -O - {$url} | bash";
    }

    public function getTeamProperty()
    {
        return Auth::user()->currentTeam;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Server Details') }}</flux:heading>

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

    <flux:heading>{{ $server->name }}</flux:heading>
    <flux:subheading>{{ $server->ip_address }}</flux:subheading>

    <div class="mt-8 space-y-6">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Server Information') }}</flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</flux:text>
                    <flux:text class="mt-1">{{ $server->name }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('IP Address') }}</flux:text>
                    <flux:text class="mt-1">{{ $server->ip_address }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</flux:text>
                    <div class="mt-1">
                        <flux:badge :color="$server->status->color()">{{ $server->status->label() }}</flux:badge>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Provisioning Command') }}</flux:heading>
            <flux:subheading class="mb-4">{{ __('SSH to your server as root and run this command to authorize our SSH key') }}</flux:subheading>

            <div class="relative">
                <flux:input
                    :value="$this->provisioningCommand"
                    readonly
                    class="font-mono text-sm"
                />
                <flux:button
                    x-data="{ copied: false }"
                    @click="
                        navigator.clipboard.writeText({{ $this->provisioningCommand }});
                        copied = true;
                        setTimeout(() => copied = false, 2000);
                    "
                    size="sm"
                    variant="ghost"
                    class="absolute right-2 top-1/2 -translate-y-1/2"
                >
                    <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"></span>
                </flux:button>
            </div>
        </div>
    </div>
</section>


