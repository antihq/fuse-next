<?php

use App\Models\Team;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new class extends Component
{
    public Team $team;

    public string $deleteName = '';

    public function mount(Team $team): void
    {
        $this->team = $team;
    }

    public function getDeleteConfirmLabelProperty(): string
    {
        return __('Type ":name" to confirm', ['name' => $this->team->name]);
    }

    public function deleteTeam(): void
    {
        Gate::authorize('delete', $this->team);

        $validated = $this->validate([
            'deleteName' => ['required', 'string'],
        ]);

        if ($validated['deleteName'] !== $this->team->name) {
            $this->addError('deleteName', __('The team name does not match.'));

            return;
        }

        $user = Auth::user();

        $fallbackTeam = $user->isCurrentTeam($this->team)
            ? $user->fallbackTeam($this->team)
            : null;

        DB::transaction(function () use ($user) {
            User::where('current_team_id', $this->team->id)
                ->where('id', '!=', $user->id)
                ->each(fn (User $affectedUser) => $affectedUser->switchTeam($affectedUser->personalTeam()));

            $this->team->invitations()->delete();
            $this->team->memberships()->delete();
            $this->team->delete();
        });

        if ($fallbackTeam) {
            $user->switchTeam($fallbackTeam);
        }

        Flux::toast(variant: 'success', text: __('Team deleted.'));

        $this->redirectRoute('teams.index', navigate: true);
    }

}; ?>

<div>
    <div class="flex items-center gap-3">
        <flux:heading class="whitespace-nowrap">{{ __('Delete team') }}</flux:heading>
        <flux:separator />
    </div>

    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-700 dark:border-red-200/10 dark:bg-red-900/20 dark:text-red-100 mb-8">
                <p class="font-medium">{{ __('Warning') }}</p>
                <p class="text-sm mt-1">{{ __('This action cannot be undone. This will permanently delete the team ":name" and remove all members.', ['name' => $team->name]) }}</p>
            </div>

            <form wire:submit="deleteTeam" class="space-y-8">
                <flux:input size="sm" wire:model="deleteName" :label="$this->deleteConfirmLabel" required data-test="delete-team-name" />

                <div class="flex items-center gap-4">
                    <flux:button size="sm" variant="danger" type="submit" data-test="delete-team-confirm">
                        {{ __('Delete team') }}
                    </flux:button>
                    <flux:link size="sm" :href="route('teams.edit', $team->slug)" wire:navigate>{{ __('Cancel') }}</flux:link>
                </div>
            </form>
        </div>

        <div class="text-sm/6 space-y-3">
            <p>All servers and sites belonging to this team will remain on their servers but will no longer be manageable through Fuse.</p>
            <p>All team members will be removed. Members who have this team as their current team will be switched to their personal team.</p>
        </div>
    </div>
</div>
