<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Fuse')] class extends Component
{
    public function getIsAuthedProperty()
    {
        return Auth::check();
    }

    public function getTeamProperty()
    {
        return Auth::user()->currentTeam;
    }
}; ?>

<div class="space-y-8">
    <div>
        <div class="flex items-center gap-3">
            <flux:heading class="whitespace-nowrap">Deploy Laravel to production</flux:heading>
            <flux:separator />
        </div>
        <div class="mt-1 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-8">
            <div>
                <p class="text-sm/6 max-w-2xl">
                    Fuse provisions VPS servers and deploys Laravel applications. You run one-liner commands on your own server over SSH. No agent, no daemon, no API connection from Fuse to your infrastructure. You copy a <x-code>wget</x-code> command, paste it into your terminal, and the server is ready.
                </p>
            </div>

            <div class="w-full rounded-lg ring-1 ring-zinc-800/15 shadow-xs dark:ring-white/20 px-3">
                <x-description.list>
                    <x-description.term>Requirement</x-description.term>
                    <x-description.details>Fresh Ubuntu 24.04 VPS</x-description.details>

                    <x-description.term>Access</x-description.term>
                    <x-description.details>Root SSH</x-description.details>

                    <x-description.term>DNS</x-description.term>
                    <x-description.details>A record pointing to server IP</x-description.details>

                    <x-description.term>Repository</x-description.term>
                    <x-description.details>Git HTTPS or SSH URL</x-description.details>

                    <x-description.term>Agent</x-description.term>
                    <x-description.details>None &mdash; signed URLs only</x-description.details>

                    <x-description.term>Cost</x-description.term>
                    <x-description.details>Free</x:description.details>
                </x-description.list>
            </div>
        </div>
    </div>

    <div>
        <div class="flex items-center gap-3">
            <flux:heading class="text-nowrap">How it works</flux:heading>
            <flux:separator />
        </div>

        <div class="mt-8 space-y-8">
            <div class="space-y-3">
                <p class="text-sm font-medium">1. Register server</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                    <p class="text-sm/6">
                        Provide the server's public IP address. Fuse generates a signed provisioning script URL. SSH into your server as root and run the command.
                    </p>
                    <div class="space-y-3">
                        <flux:input size="sm" value="wget -qO- https://fuse.example.com/servers/.../full-provision-script | bash" readonly variant="filled" copyable class="font-mono" />
                        <p class="text-sm/6">
                            Installs Caddy, PHP 8.2&ndash;8.5, Composer, Node.js, Supervisor. Creates a <x-code>fuse</x-code> user. Authorizes your SSH keys. ~2 min on fresh Ubuntu 24.04.
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <p class="text-sm font-medium">2. Add site</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                    <p class="text-sm/6">
                        Point your domain's DNS A record to the server IP. In Fuse, provide the domain, Git repository URL, and PHP version.
                    </p>
                    <div class="space-y-3">
                        <flux:input size="sm" value="wget -qO- https://fuse.example.com/sites/.../deploy-script | bash" readonly variant="filled" copyable class="font-mono" />
                        <p class="text-sm/6">
                            Clones the repo, installs Composer/npm deps, runs migrations, builds assets, configures Caddy with automatic TLS, health check. Reports status back via signed callback.
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <p class="text-sm font-medium">3. Ship</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                    <p class="text-sm/6">
                        Your site is live. Redeploy with a new one-liner when you push changes. Enable queue workers. Edit environment variables directly on the server. Fuse tracks deployment status but never connects to your server on its own &mdash; every action is initiated by you, from your terminal.
                    </p>
                    <div class="space-y-3">
                        <flux:input size="sm" value="ssh fuse@your-server-ip" readonly variant="filled" copyable class="font-mono" />
                        <p class="text-sm/6">
                            After deploying, additional commands are available: <x-code>redeploy-script</x-code>, <x-code>queue-supervisor-script</x-code>, <x-code>destroy-script</x-code>. Each is a signed URL generated on demand. Edit <x-code>.env</x-code> directly, then <x-code>php artisan config:cache</x-code>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="flex items-center gap-3">
            <flux:heading class="text-nowrap">Stack</flux:heading>
            <flux:separator />
        </div>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-8">
            <div class="w-full rounded-lg ring-1 ring-zinc-800/15 shadow-xs dark:ring-white/20 px-3">
                <x-description.list>
                    <x-description.term>Web server</x-description.term>
                    <x-description.details>Caddy &mdash; automatic TLS, no certbot</x-description.details>

                    <x-description.term>PHP runtime</x-description.term>
                    <x-description.details>PHP-FPM 8.2, 8.3, 8.4, or 8.5</x-description.details>

                    <x-description.term>Database</x-description.term>
                    <x-description.details>SQLite only &mdash; no MySQL, no Postgres</x-description.details>

                    <x-description.term>Queue</x-description.term>
                    <x-description.details>SQLite driver, Supervisor daemon</x-description.details>

                    <x-description.term>Cache</x-description.term>
                    <x-description.details>File cache &mdash; no Redis</x-description.details>

                    <x-description.term>Process manager</x-description.term>
                    <x-description.details>Supervisor</x-description.details>

                    <x-description.term>OS</x-description.term>
                    <x-description.details>Ubuntu 24.04 LTS</x-description.details>

                    <x-description.term>Node.js</x-description.term>
                    <x-description.details>Installed via provision script</x-description.details>
                </x-description.list>
            </div>

            <div class="text-sm/6 space-y-3">
                <p>SQLite handles tens of thousands of daily users for most Laravel applications. No separate database server to tune, replicate, or back up. The file is on disk.</p>
                <p>No Redis, no MySQL, no containers, no orchestration. Caddy handles TLS automatically. Supervisor keeps queue workers alive.</p>
                <p>Most web apps never outgrow this stack. When they do, you'll know &mdash; and you can migrate on your own terms.</p>
                <p>Provisioned directory structure: <x-code>/home/fuse/{domain}/</x-code> for each site, <x-code>/etc/caddy/sites.caddy</x-code> for web server config, <x-code>/etc/supervisor/conf.d/{domain}-worker.conf</x-code> for queue daemons.</p>
            </div>
        </div>
    </div>

    <div>
        <div class="flex items-center gap-3">
            <flux:heading class="text-nowrap">Approach</flux:heading>
            <flux:separator />
        </div>

        <div class="mt-4 w-full rounded-lg ring-1 ring-zinc-800/15 shadow-xs dark:ring-white/20 px-3">
            <x-description.list>
                <x-description.term>Signed URLs, not agents</x-description.term>
                <x-description.details>Fuse generates time-limited, cryptographically signed URLs for scripts. Nothing runs on your server unless you invoke it.</x-description.details>

                <x-description.term>One-liner commands</x-description.term>
                <x-description.details>Every operation &mdash; provision, deploy, redeploy, queue supervisor, destroy &mdash; is a single <x-code class="whitespace-nowrap">wget | bash</x-code> you copy and paste into SSH.</x-description.details>

                <x-description.term>Your server, your access</x-description.term>
                <x-description.details>SSH in anytime. No proxy connections, no tunnels. The server is yours.</x-description.details>

                <x-description.term>Multi-team</x-description.term>
                <x-description.details>Teams with owner, admin, and member roles. Invite collaborators. Each team manages its own servers and sites.</x:description.details>

                <x-description.term>Free</x-description.term>
                <x-description.details>No billing. No usage limits.</x-description.details>
            </x-description.list>
        </div>
    </div>

    <div>
        @if($this->isAuthed)
            <flux:button :href="route('dashboard', $this->team->slug)" wire:navigate variant="primary" size="sm">
                Go to dashboard &rarr;
            </flux:button>
        @else
            <flux:button href="{{ route('register') }}" wire:navigate variant="primary" size="sm" color="emerald" icon:trailing="arrow-right">
                Start deploying
            </flux:button>
        @endif
    </div>
</div>
