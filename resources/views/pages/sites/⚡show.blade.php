<?php

use App\Enums\SiteStatus;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Site Details')] class extends Component
{
    #[Locked]
    public int $serverId;

    public Server $server;

    public Site $site;

    public function mount(Server $server, Site $site): void
    {
        $this->serverId = $server->id;
        $this->server = $server;
        $this->site = $site;

        $team = Auth::user()->currentTeam;

        $this->authorize('view', [$team, $this->site]);
    }

    public function getDeployScriptCommandProperty(): string
    {
        $url = URL::signedRoute('sites.deploy-script', ['site' => $this->site]);

        return "wget --no-verbose -O - {$url} | bash";
    }

    public function markDeployed(): void
    {
        $this->authorize('update', [$this->team, $this->site]);

        $this->site->status = SiteStatus::Deployed;
        $this->site->save();
    }

    public function refreshSite(): void
    {
        $this->site->refresh();
    }

    public function getShouldPollProperty(): bool
    {
        return $this->site->status === SiteStatus::Deploying;
    }

    public function getTeamProperty()
    {
        return Auth::user()->currentTeam;
    }
}; ?>

<section class="w-full" @if($this->shouldPoll) wire:poll.5s="refreshSite" @endif>
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Site Details') }}</flux:heading>

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

    <flux:heading>{{ $site->domain }}</flux:heading>
    <flux:subheading>{{ $site->repository }}</flux:subheading>

    <div class="mt-8 space-y-6">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Site Information') }}</flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Domain') }}</flux:text>
                    <flux:text class="mt-1">{{ $site->domain }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Repository') }}</flux:text>
                    <flux:text class="mt-1">{{ $site->repository }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</flux:text>
                    <div class="mt-1">
                        <flux:badge :color="$site->status->color()">{{ $site->status->label() }}</flux:badge>
                    </div>
                </div>
            </div>
        </div>

        @if($site->status === SiteStatus::Pending || $site->status === SiteStatus::Failed)
        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Deploy Site') }}</flux:heading>
            <flux:subheading class="mb-4">{{ __('SSH to your server as fuse and run this command to deploy the site') }}</flux:subheading>

            <div class="mb-4 relative">
                <flux:input
                    :value="$this->deployScriptCommand"
                    readonly
                    class="font-mono text-sm"
                />
                <flux:button
                    x-data="{ copied: false }"
                    @click="
                        navigator.clipboard.writeText({{ $this->deployScriptCommand }});
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
        @endif

        @if($site->status === SiteStatus::Deploying)
        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Deploying...') }}</flux:heading>
            <flux:subheading class="mb-4">{{ __('Your site is being deployed. This may take a few minutes.') }}</flux:subheading>

            <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ __('Deploying site...') }}
            </div>
        </div>
        @endif

        @if($site->status === SiteStatus::Deployed)
        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Site Deployed') }}</flux:heading>
            <flux:subheading class="mb-4">{{ __('Your site has been deployed successfully.') }}</flux:subheading>

            <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                {{ __('Deployment completed successfully') }}
            </div>
        </div>
        @endif

        @if($site->status === SiteStatus::Pending || $site->status === SiteStatus::Deploying || $site->status === SiteStatus::Failed)
        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:button wire:click="markDeployed" variant="outline" class="w-full">
                {{ __('Deployment completed? Mark as Deployed') }}
            </flux:button>
        </div>
        @endif
    </div>
</section>
