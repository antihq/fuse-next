<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use App\Rules\UniqueTeamInvitation;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Invite team member')] class extends Component {
    public Team $team;

    public string $inviteEmail = '';

    public string $inviteRole = 'member';

    public function mount(Team $team): void
    {
        $this->team = $team;
    }

    public function createInvitation(): void
    {
        Gate::authorize('inviteMember', $this->team);

        $validated = $this->validate([
            'inviteEmail' => ['required', 'string', 'email', 'max:255', new UniqueTeamInvitation($this->team)],
            'inviteRole' => ['required', 'string', Rule::enum(TeamRole::class)],
        ]);

        $invitation = $this->team->invitations()->create([
            'email' => $validated['inviteEmail'],
            'role' => TeamRole::from($validated['inviteRole']),
            'invited_by' => Auth::id(),
            'expires_at' => now()->addDays(3),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));

        Flux::toast(variant: 'success', text: __('Invitation sent.'));

        $this->redirectRoute('teams.edit', ['team' => $this->team->slug], navigate: true);
    }

    public function getAvailableRolesProperty(): array
    {
        return TeamRole::assignable();
    }
}; ?>

<div>
    <div class="flex items-center gap-3">
        <flux:heading class="whitespace-nowrap">{{ __('Invite team member') }}</flux:heading>
        <flux:separator />
    </div>

    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
            <form wire:submit="createInvitation" class="space-y-8">
                <flux:input size="sm" wire:model="inviteEmail" type="email" :label="__('Email address')" required data-test="invite-email" />

                <flux:select size="sm" wire:model="inviteRole" :label="__('Role')" data-test="invite-role">
                    @foreach ($this->availableRoles as $role)
                        <flux:select.option value="{{ $role['value'] }}">{{ $role['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex items-center gap-4">
                    <flux:button size="sm" variant="primary" type="submit" data-test="invite-submit">{{ __('Send invitation') }}</flux:button>
                    <flux:link size="sm" :href="route('teams.edit', $team->slug)" wire:navigate>{{ __('Cancel') }}</flux:link>
                </div>
            </form>
        </div>

        <div class="text-sm/6 space-y-3">
            <p>Send an invitation to join this team. The invitee will receive an email with a link to accept.</p>
            <p>Invitations expire after 3 days.</p>
        </div>
    </div>
</div>
