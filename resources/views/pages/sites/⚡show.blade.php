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

    public function getDestroyScriptCommandProperty(): string
    {
        $url = URL::signedRoute('sites.destroy-script', ['site' => $this->site]);

        return "wget --no-verbose -O - {$url} | bash";
    }

    public function getSetupMysqlScriptCommandProperty(): string
    {
        $url = URL::signedRoute('sites.setup-mysql-script', ['site' => $this->site]);

        return "wget --no-verbose -O - {$url} | bash";
    }

    public function markDeployed(): void
    {
        $this->authorize('update', [$this->team, $this->site]);

        $this->site->status = SiteStatus::Deployed;
        $this->site->save();
    }

    public function initiateDelete(): void
    {
        $this->authorize('update', [$this->team, $this->site]);

        $this->site->status = SiteStatus::Deleting;
        $this->site->save();
    }

    public function markDeleted(): void
    {
        $this->authorize('update', [$this->team, $this->site]);

        $this->site->delete();

        $this->redirectRoute('sites.index', [$this->team->slug, $this->server]);
    }

    public function refreshSite(): void
    {
        $this->site->refresh();
    }

    public function getShouldPollProperty(): bool
    {
        return $this->site->status === SiteStatus::Deploying
            || $this->site->status === SiteStatus::Deleting;
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

        @if(!$site->mysql_database)
            <flux:separator variant="subtle" />

            <div class="py-3 space-y-3">
                <flux:heading>{{ __('Setup MySQL Database') }}</flux:heading>
                <flux:subheading>{{ __('Optionally switch from SQLite to MySQL. This cannot be undone.') }}</flux:subheading>

                <flux:input
                    :value="$this->setupMysqlScriptCommand"
                    readonly
                    copyable
                    class="font-mono text-sm"
                />
            </div>
        @endif

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

    @if($site->status === SiteStatus::Deleting)
        <flux:separator variant="subtle" />

        <div class="py-3 space-y-3">
            <flux:heading>{{ __('Remove Site') }}</flux:heading>
            <flux:subheading>{{ __('Run this command on your server to remove the site') }}</flux:subheading>

            <flux:input
                :value="$this->destroyScriptCommand"
                readonly
                copyable
                class="font-mono text-sm"
            />

            <flux:button wire:click="markDeleted" variant="outline" class="w-full">
                {{ __('Mark as Deleted') }}
            </flux:button>
        </div>
    @endif

    @if(in_array($site->status, [SiteStatus::Pending, SiteStatus::Deployed, SiteStatus::Failed]))
        <flux:separator variant="subtle" />

        <div class="py-3">
            <flux:button
                wire:click="initiateDelete"
                wire:confirm="Are you sure you want to delete {{ $site->domain }}?"
                variant="danger"
                class="w-full"
            >
                {{ __('Delete Site') }}
            </flux:button>
        </div>
    @endif
</div>
