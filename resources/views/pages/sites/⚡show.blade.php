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

    public function getRedeployScriptCommandProperty(): string
    {
        $url = URL::signedRoute('sites.redeploy-script', ['site' => $this->site]);

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

<div class="max-w-xl mx-auto" @if($this->shouldPoll) wire:poll.5s="refreshSite" @endif>
    <div class="py-3">
        <div class="flex items-center gap-2">
            <flux:heading>{{ $site->domain }}</flux:heading>
            <flux:badge :color="$site->status->color()" size="sm">{{ $site->status->label() }}</flux:badge>
        </div>
        <flux:subheading>{{ $site->repository }}</flux:subheading>
    </div>

    @if($site->status === SiteStatus::Pending || $site->status === SiteStatus::Failed)
        <flux:separator variant="subtle" />

        <div class="py-3 space-y-3">
            <flux:heading>{{ __('Deploy Site') }}</flux:heading>
            <flux:subheading>{{ __('Run this command to deploy site') }}</flux:subheading>

            <flux:input
                :value="$this->deployScriptCommand"
                readonly
                copyable
                class="font-mono text-sm"
            />
        </div>
    @endif

    @if($site->status === SiteStatus::Deploying)
        <flux:separator variant="subtle" />

        <div class="py-3 space-y-3">
            <flux:heading>{{ __('Deploying') }}</flux:heading>
            <flux:subheading>{{ __('This may take a few minutes.') }}</flux:subheading>

            <flux:text wire:loading>
                {{ __('Deploying site...') }}
            </flux:text>
        </div>
    @endif

    @if($site->status === SiteStatus::Deployed)
        <flux:separator variant="subtle" />

        <div class="py-3 space-y-3">
            <flux:heading>{{ __('Site Deployed') }}</flux:heading>
            <flux:subheading>{{ __('Your site has been deployed successfully.') }}</flux:subheading>
        </div>

        <flux:separator variant="subtle" />

        <div class="py-3 space-y-3">
            <flux:heading>{{ __('Redeploy Site') }}</flux:heading>
            <flux:subheading>{{ __('Run this command to redeploy site') }}</flux:subheading>

            <flux:input
                :value="$this->redeployScriptCommand"
                readonly
                copyable
                class="font-mono text-sm"
            />
        </div>
    @endif

    @if($site->status === SiteStatus::Pending || $site->status === SiteStatus::Deploying || $site->status === SiteStatus::Failed)
        <flux:separator variant="subtle" />

        <div class="py-3 space-y-3">
            <flux:button wire:click="markDeployed" variant="outline" class="w-full">
                {{ __('Mark as Deployed') }}
            </flux:button>
        </div>
    @endif
</div>
