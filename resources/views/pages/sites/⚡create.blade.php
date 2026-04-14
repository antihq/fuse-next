<?php

use App\Actions\Sites\CreateSite;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add Site')] class extends Component
{
    #[Locked]
    public int $serverId;

    public Server $server;

    public string $domain = '';

    public string $repository = '';

    public function mount(Server $server): void
    {
        $this->serverId = $server->id;
        $this->server = $server;

        $team = Auth::user()->currentTeam;

        $this->authorize('viewAny', [$team, $this->server]);
    }

    public function create(): void
    {
        $team = Auth::user()->currentTeam;

        $this->authorize('create', [Site::class, $team]);

        $validated = $this->validate([
            'domain' => ['required', 'string'],
            'repository' => ['required', 'url'],
        ]);

        $site = (new CreateSite)->handle($this->server, $validated['domain'], $validated['repository']);

        $this->redirectRoute('sites.show', [$team->slug, $this->server, $site]);
    }

    public function getTeamProperty()
    {
        return Auth::user()->currentTeam;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Add Site') }}</flux:heading>

    <div class="mb-6">
        <flux:button
            :href="route('sites.index', [$this->team->slug, $this->server])"
            variant="ghost"
            size="sm"
            icon="arrow-left"
            wire:navigate
        >
            {{ __('Back to sites') }}
        </flux:button>
    </div>

    <flux:heading>{{ __('Add Site') }}</flux:heading>
    <flux:subheading>{{ $server->name }}</flux:subheading>

    <div class="mt-8 max-w-2xl">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-6">{{ __('Site Details') }}</flux:heading>

            <div class="space-y-6">
                <flux:input
                    label="{{ __('Domain') }}"
                    wire:model="domain"
                    placeholder="example.com"
                    required
                />
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('The domain name for your site (e.g., example.com)') }}</p>

                <flux:input
                    label="{{ __('GitHub Repository') }}"
                    wire:model="repository"
                    placeholder="https://github.com/user/repo.git"
                    required
                />
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('The GitHub repository URL to deploy') }}</p>
            </div>

            <div class="mt-6">
                <flux:button wire:click="create" class="w-full">
                    <span wire:loading.remove>{{ __('Create site') }}</span>
                    <span wire:loading>{{ __('Creating...') }}</span>
                </flux:button>
            </div>
        </div>
    </div>
</section>
