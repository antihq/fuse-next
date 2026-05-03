<?php

use App\Actions\Sites\CreateSite;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add Site')] class extends Component
{
    #[Locked]
    public int $serverId;

    public string $domain = '';

    public string $repository = '';

    public string $phpVersion = '8.5';

    public function mount(Server $server): void
    {
        $this->serverId = $server->id;

        $team = Auth::user()->currentTeam;

        $this->authorize('viewAny', [$team, $server]);
    }

    public function create(): void
    {
        $team = Auth::user()->currentTeam;

        $this->authorize('create', [Site::class, $team]);

        $validated = $this->validate([
            'domain' => ['required', 'string'],
            'repository' => ['required', 'regex:#^(https?://|git@)[^\s]+$#'],
            'phpVersion' => ['required', 'in:8.2,8.3,8.4,8.5'],
        ]);

        $site = (new CreateSite)->handle($this->server, $validated['domain'], $validated['repository'], $validated['phpVersion']);

        $this->redirectRoute('sites.show', [$team->slug, $this->server, $site], navigate: true);
    }

    #[Computed]
    public function server(): Server
    {
        return Server::findOrFail($this->serverId);
    }

    public function getTeamProperty()
    {
        return Auth::user()->currentTeam;
    }
}; ?>

<div class="space-y-8">
    <div>
        <div class="flex items-center gap-3">
            <flux:heading class="whitespace-nowrap">{{ __('Add a new site') }}</flux:heading>
            <flux:separator />
        </div>
        <p class="max-w-prose text-sm/6 mt-1">
            {{ __('Point a domain to this server and connect a Git repository. We\'ll clone it, install dependencies, build assets, configure Caddy, and run migrations.') }}
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <form wire:submit="create" class="space-y-8">
            <flux:input
                size="sm"
                label="{{ __('Domain') }}"
                wire:model="domain"
                placeholder="example.com"
                required
                autofocus
                data-test="add-site-domain"
            />

            <flux:input
                size="sm"
                label="{{ __('GitHub Repository') }}"
                wire:model="repository"
                placeholder="https://github.com/user/repo.git"
                required
                data-test="add-site-repository"
            />

            <flux:select
                size="sm"
                label="{{ __('PHP Version') }}"
                wire:model="phpVersion"
                required
                data-test="add-site-php-version"
            >
                <flux:select.option value="8.5">PHP 8.5</flux:select.option>
                <flux:select.option value="8.4">PHP 8.4</flux:select.option>
                <flux:select.option value="8.3">PHP 8.3</flux:select.option>
                <flux:select.option value="8.2">PHP 8.2</flux:select.option>
            </flux:select>

            <flux:button type="submit" variant="primary" data-test="add-site-submit" color="blue" icon:trailing="arrow-right" size="sm">
                {{ __('Add site') }}
            </flux:button>
        </form>

        <div>
            <flux:heading>{{ __('Before you begin') }}</flux:heading>
            <div class="w-full rounded-lg ring-1 ring-zinc-800/15 shadow-xs dark:ring-white/20 px-3 mt-3">
                <x-description.list>
                    <x-description.term>{{ __('Domain') }}</x-description.term>
                    <x-description.details>{{ __('Point your DNS A record to this server\'s IP address') }}</x-description.details>

                    <x-description.term>{{ __('Repository') }}</x-description.term>
                    <x-description.details>{{ __('HTTPS or SSH URL of a Git repository to clone') }}</x-description.details>

                    <x-description.term>{{ __('PHP Version') }}</x-description.term>
                    <x-description.details>{{ __('Select the PHP runtime for this site (8.2–8.5)') }}</x-description.details>
                </x-description.list>
            </div>
            <p class="mt-3 text-sm/6">
                {{ __('After adding, you\'ll get a one-line script to run on the server. It clones the repo, installs Composer and npm dependencies, runs migrations, builds frontend assets, and configures Caddy with a TLS certificate.') }}
            </p>
        </div>
    </div>

    <div class="flex items-center mt-8">
        <flux:button size="sm" :href="route('servers.show', [$this->team->slug, $this->server])" wire:navigate icon="arrow-left" class="rounded-full!">
            {{ __('Return to Server') }}
        </flux:button>
        <flux:separator class="ml-3" />
    </div>
</div>
