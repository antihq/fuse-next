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

<form wire:submit="create" class="max-w-xl mx-auto">
    <flux:input
        label="{{ __('Domain') }}"
        wire:model="domain"
        placeholder="example.com"
        required
        autofocus
        data-test="add-site-domain"
    />

    <div class="mt-4">
        <flux:input
            label="{{ __('GitHub Repository') }}"
            wire:model="repository"
            placeholder="https://github.com/user/repo.git"
            required
            data-test="add-site-repository"
        />
    </div>

    <div class="mt-4 flex">
        <flux:spacer />
        <flux:button type="submit" variant="primary" data-test="add-site-submit">
            {{ __('Add site') }}
        </flux:button>
    </div>
</form>
