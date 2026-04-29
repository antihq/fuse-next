<?php

use App\Enums\SiteStatus;
use App\Models\Server;
use App\Models\Site;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Computed;
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

        $this->authorize('view', [Site::class, $team, $this->site]);
    }

    #[Computed]
    public function deployScriptCommand(): string
    {
        $url = URL::signedRoute('sites.deploy-script', ['site' => $this->site]);

        return "wget --no-verbose -O - {$url} | bash";
    }

    #[Computed]
    public function redeployScriptCommand(): string
    {
        $url = URL::signedRoute('sites.redeploy-script', ['site' => $this->site]);

        return "wget --no-verbose -O - {$url} | bash";
    }

    #[Computed]
    public function destroyScriptCommand(): string
    {
        $url = URL::signedRoute('sites.destroy-script', ['site' => $this->site]);

        return "wget --no-verbose -O - {$url} | bash";
    }

    #[Computed]
    public function shouldPoll(): bool
    {
        return $this->site->status === SiteStatus::Deploying
            || $this->site->status === SiteStatus::Deleting;
    }

    #[Computed]
    public function team()
    {
        return Auth::user()->currentTeam;
    }

    public function markDeployed(): void
    {
        $this->authorize('update', [Site::class, $this->team, $this->site]);

        $this->site->status = SiteStatus::Deployed;
        $this->site->save();
    }

    public function initiateDelete(): void
    {
        $this->authorize('delete', [Site::class, $this->team, $this->site]);

        $this->site->status = SiteStatus::Deleting;
        $this->site->save();
    }

    public function markDeleted(): void
    {
        $this->authorize('delete', [Site::class, $this->team, $this->site]);

        $this->site->delete();

        Flux::toast(variant: 'success', text: 'Site deleted.');
        $this->redirect(route('sites.index', ['current_team' => $this->team->slug, $this->server]), navigate: true);
    }

    public function refreshSite(): void
    {
        $this->site->refresh();
    }
}; ?>

<div @if($this->shouldPoll) wire:poll.5s="refreshSite" @endif class="space-y-8">
    <div>
        <div class="flex items-center gap-3">
            <flux:heading>{{ $site->domain }}</flux:heading>
            <flux:separator />
        </div>
        <flux:badge :color="$site->status->color()" size="sm" class="uppercase tracking-widest mt-1 font-mono">{{ $site->status->label() }}</flux:badge>
        <p class="text-sm/6 mt-1 max-w-prose">{{ $site->repository }}</p>
    </div>

    @if($site->status === SiteStatus::Pending)
        <div>
            <div class="flex items-center gap-3">
                <flux:heading class="text-nowrap">Deploy your site</flux:heading>
                <flux:separator />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-8 mt-4">
                <div class="text-sm/6 space-y-3">
                    <p>SSH into your server and run the command below. It will clone the repository, install dependencies, and configure Caddy.</p>
                    <p>When deployment finishes, your site will be marked as deployed automatically.</p>
                </div>

                <div class="space-y-8">
                    <div class="space-y-3">
                        <flux:input
                            size="sm"
                            :value="$this->deployScriptCommand"
                            readonly
                            copyable
                            class="font-mono"
                        />

                        <p class="text-sm/6 max-w-prose">
                            The script reports back when it's done. If it doesn't, you can mark the site as deployed manually.
                        </p>
                    </div>

                    <flux:button size="sm" wire:click="markDeployed" icon:trailing="arrow-right" variant="primary" color="emerald">
                        Mark as deployed
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    @if($site->status === SiteStatus::Deploying)
        <flux:separator />

        <div class="py-3 space-y-3">
            <flux:heading>Deploying your site</flux:heading>
            <flux:subheading>This may take a few minutes.</flux:subheading>

            <flux:text wire:loading>
                Cloning repository, installing dependencies, and configuring Caddy...
            </flux:text>

            <flux:text class="text-xs">
                The script reports back when it's done. If it doesn't, you can mark the site as deployed manually.
            </flux:text>

            <flux:button wire:click="markDeployed" size="sm">
                Mark as deployed
            </flux:button>
        </div>
    @endif

    @if($site->status === SiteStatus::Failed)
        <flux:separator />

        <div class="py-3 space-y-3">
            <flux:heading>Deployment failed</flux:heading>

            <flux:callout color="red">
                <flux:callout.text>
                    The deploy script encountered an error. You can try running the command again or mark the site as deployed manually.
                </flux:callout.text>
            </flux:callout>

            <flux:input
                size="sm"
                :value="$this->deployScriptCommand"
                readonly
                copyable
                class="font-mono"
            />

            <flux:text class="text-xs">
                The script reports back when it's done. If it doesn't, you can mark the site as deployed manually.
            </flux:text>

            <flux:button wire:click="markDeployed" variant="ghost" size="sm">
                Mark as deployed
            </flux:button>
        </div>
    @endif

    @if($site->status === SiteStatus::Deployed)
        <div>
            <div class="flex items-center gap-3">
                <flux:heading class="text-nowrap">Connect to your site</flux:heading>
                <flux:separator />
            </div>

            <div class="mt-1 text-sm space-y-3">
                <p class="max-w-prose">
                    SSH into the server to manage your Laravel site.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                    <flux:input size="sm" :value="'ssh fuse@' . $server->ip_address" readonly copyable class="font-mono" />
                </div>
            </div>
        </div>

        <div>
            <div class="flex items-center gap-3">
                <flux:heading class="text-nowrap">Environment Variables</flux:heading>
                <flux:separator />
            </div>

            <div class="mt-1 text-sm space-y-3">
                <p class="max-w-prose">
                    The deploy script copies <code>.env.example</code> and sets <code>APP_ENV=production</code> and <code>APP_DEBUG=false</code>. Edit your <code>.env</code> file to add database credentials, mail settings, API keys, and other configuration.
                </p>

                <div class="space-y-3">
                    <flux:input
                        size="sm"
                        :value="'nano /home/fuse/' . $site->domain . '/.env'"
                        readonly
                        copyable
                        class="font-mono"
                    />

                    <flux:input
                        size="sm"
                        :value="'cd /home/fuse/' . $site->domain . ' && php artisan config:cache'"
                        readonly
                        copyable
                        class="font-mono"
                    />
                </div>
            </div>
        </div>

        <div>
            <div class="flex items-center gap-3">
                <flux:heading class="text-nowrap">Redeploy site</flux:heading>
                <flux:separator />
            </div>

            <div class="mt-1 text-sm space-y-3">
                <p class="max-w-prose">
                    Run this command to redeploy your site with the latest changes from the repository.
                </p>

                <flux:input
                    size="sm"
                    :value="$this->redeployScriptCommand"
                    readonly
                    copyable
                    class="font-mono"
                />
            </div>
        </div>
    @endif

    @if($site->status === SiteStatus::Deleting)
        <flux:separator />

        <div class="py-3 space-y-3">
            <flux:heading>Remove site</flux:heading>
            <flux:subheading>Run this command on your server to remove the site.</flux:subheading>

            <flux:input
                size="sm"
                :value="$this->destroyScriptCommand"
                readonly
                copyable
                class="font-mono"
            />

            <flux:button size="sm" wire:click="markDeleted" variant="ghost">
                Mark as deleted
            </flux:button>
        </div>
    @endif

    @if(in_array($site->status, [SiteStatus::Pending, SiteStatus::Deployed, SiteStatus::Failed]))
        <div>
            <div class="flex items-center">
                <flux:heading class="text-nowrap">Danger Zone</flux:heading>
                <flux:separator class="ml-3" />
                <flux:button
                    wire:click="initiateDelete"
                    wire:confirm="Are you sure you want to delete {{ $site->domain }}?"
                    variant="danger"
                    size="sm"
                    icon:trailing="arrow-right"
                    class="rounded-full!"
                >
                    Delete site
                </flux:button>
            </div>
            <p class="text-sm/6 mt-1 max-w-prose">Removes this site from Fuse. The files on the server are not touched — run the remove command to clean those up.</p>
        </div>
    @endif
</div>
