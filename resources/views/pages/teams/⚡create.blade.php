<?php

use App\Actions\Teams\CreateTeam;
use App\Rules\TeamName;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create team')] class extends Component {
    public string $name = '';

    public function createTeam(CreateTeam $createTeam): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', new TeamName],
        ]);

        $team = $createTeam->handle(Auth::user(), $validated['name']);

        Flux::toast(variant: 'success', text: __('Team created.'));

        $this->redirectRoute('teams.edit', ['team' => $team->slug], navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center gap-3">
        <flux:heading class="whitespace-nowrap">{{ __('Create team') }}</flux:heading>
        <flux:separator />
    </div>

    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
            <form wire:submit="createTeam" class="space-y-8">
                <flux:input size="sm" wire:model="name" :label="__('Team name')" type="text" required autofocus data-test="create-team-name" />

                <flux:button size="sm" variant="primary" color="emerald" icon:trailing="arrow-right" type="submit" data-test="create-team-submit">
                    {{ __('Create team') }}
                </flux:button>
            </form>
        </div>

        <div class="text-sm/6 space-y-3">
            <p>Give your team a name to get started. After creating the team, you can invite members and manage roles.</p>
        </div>
    </div>

    <div class="flex items-center mt-8">
        <flux:button size="sm" :href="route('teams.index')" wire:navigate icon="arrow-left" class="rounded-full!">
            {{ __('Return to Teams') }}
        </flux:button>
        <flux:separator class="ml-3" />
    </div>
</div>
