<?php

use App\Support\UserTeam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Teams')] class extends Component {
    /**
     * @return Collection<int, UserTeam>
     */
    public function getTeamsProperty()
    {
        return Auth::user()->toUserTeams(includeCurrent: true);
    }
}; ?>

<div>
    <div class="flex items-center gap-3">
        <flux:heading class="whitespace-nowrap">{{ __('Teams') }}</flux:heading>
        <flux:separator />
    </div>

    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
            <div class="flex items-center justify-end">
                <flux:button size="sm" variant="primary" icon="plus" :href="route('teams.create')" wire:navigate data-test="teams-new-team-button">
                    {{ __('New team') }}
                </flux:button>
            </div>

            <div class="mt-6 space-y-3">
                @forelse ($this->teams as $team)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="team-row">
                        <div class="flex items-center gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ $team->name }}</span>
                                    @if ($team->isPersonal)
                                        <flux:badge color="zinc">{{ __('Personal') }}</flux:badge>
                                    @endif
                                </div>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $team->roleLabel }}</flux:text>
                            </div>
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="$team->role === 'member' ? __('View team') : __('Edit team')">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    :icon="$team->role === 'member' ? 'eye' : 'pencil'"
                                    :href="route('teams.edit', $team->slug)"
                                    wire:navigate
                                    :data-test="$team->role === 'member' ? 'team-view-button' : 'team-edit-button'"
                                />
                            </flux:tooltip>
                        </div>
                    </div>
                @empty
                    <flux:text class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                        {{ __('You don\'t belong to any teams yet.') }}
                    </flux:text>
                @endforelse
            </div>
        </div>

        <div class="text-sm/6 space-y-3">
            <p>Manage your teams and team memberships. Each team manages its own servers and sites.</p>
            <p>Teams have owner, admin, and member roles. Owners can invite new members and manage team settings.</p>
        </div>
    </div>
</div>
