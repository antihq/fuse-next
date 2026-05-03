<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Rules\TeamName;
use App\Support\TeamPermissions;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public Team $teamModel;

    public string $teamName = '';

    public array $members = [];

    public array $invitations = [];

    public array $availableRoles = [];

    public int $currentUserId = 0;

    public function mount(Team $team): void
    {
        $this->teamModel = $team;
        $this->teamName = $team->name;
        $this->currentUserId = (int) Auth::id();

        $this->populateTeamData();
    }

    public function updateTeam(): void
    {
        Gate::authorize('update', $this->teamModel);

        $validated = $this->validate([
            'teamName' => ['required', 'string', 'max:255', new TeamName],
        ]);

        $team = DB::transaction(function () use ($validated) {
            $team = Team::whereKey($this->teamModel->id)->lockForUpdate()->firstOrFail();

            $team->update(['name' => $validated['teamName']]);

            return $team;
        });

        $this->teamModel = $team;

        $this->populateTeamData();

        Flux::toast(variant: 'success', text: __('Team updated.'));

        $this->redirectRoute('teams.edit', ['team' => $this->teamModel->fresh()->slug], navigate: true);
    }

    public function updateMember(int $userId, string $role): void
    {
        Gate::authorize('updateMember', $this->teamModel);

        $validated = Validator::make(['role' => $role], [
            'role' => ['required', 'string', Rule::enum(TeamRole::class)],
        ])->validate();

        $this->teamModel->memberships()
            ->where('user_id', $userId)
            ->firstOrFail()
            ->update(['role' => TeamRole::from($validated['role'])]);

        $this->populateTeamData();

        Flux::toast(variant: 'success', text: __('Member role updated.'));
    }

    public function removeMember(int $userId): void
    {
        Gate::authorize('removeMember', $this->teamModel);

        $user = \App\Models\User::findOrFail($userId);

        $this->teamModel->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($user->isCurrentTeam($this->teamModel)) {
            $user->switchTeam($user->personalTeam());
        }

        Flux::toast(variant: 'success', text: __('Member removed.'));

        $this->redirectRoute('teams.edit', ['team' => $this->teamModel->slug], navigate: true);
    }

    public function cancelInvitation(string $code): void
    {
        Gate::authorize('cancelInvitation', $this->teamModel);

        $invitation = $this->teamModel->invitations()->where('code', $code)->firstOrFail();

        $invitation->delete();

        Flux::toast(variant: 'success', text: __('Invitation cancelled.'));

        $this->populateTeamData();
    }

    private function populateTeamData(): void
    {
        $team = $this->teamModel->fresh();

        $this->members = $team->members()->get()->map(fn ($member) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->pivot->role->value,
            'role_label' => $member->pivot->role?->label(),
        ])->toArray();

        $this->invitations = $team->invitations()
            ->whereNull('accepted_at')
            ->get()
            ->map(fn ($invitation) => [
                'code' => $invitation->code,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'sent' => $invitation->created_at->format('M j, Y'),
            ])->toArray();

        $this->availableRoles = TeamRole::assignable();
    }

    public function render()
    {
        $teamName = $this->teamModel->name;

        $title = $this->permissions->canUpdateTeam
            ? __('Edit :name', ['name' => $teamName])
            : __('View :name', ['name' => $teamName]);

        return $this->view()->title($title);
    }

    public function getPermissionsProperty(): TeamPermissions
    {
        return Auth::user()->toTeamPermissions($this->teamModel);
    }
}; ?>

<div class="space-y-6">
    <div>
        <div class="flex items-center gap-3">
            <flux:heading class="whitespace-nowrap">{{ $teamModel->name }}</flux:heading>
            <flux:separator />
        </div>
        <div class="flex items-center gap-2 mt-1">
            <flux:badge size="sm" color="zinc">{{ count($members) }} {{ Str::plural('member', count($members)) }}</flux:badge>
            @if ($teamModel->is_personal)
                <flux:badge size="sm" color="zinc">{{ __('Personal') }}</flux:badge>
            @endif
        </div>
    </div>

    @if ($this->permissions->canUpdateTeam)
        <form wire:submit="updateTeam">
            <flux:input size="sm" wire:model="teamName" :label="__('Team name')" required data-test="team-name-input" />
            <flux:button size="sm" variant="primary" type="submit" class="mt-3" data-test="team-save-button">
                {{ __('Save') }}
            </flux:button>
        </form>
    @endif

    <div>
        <div class="flex items-center">
            <flux:heading class="text-nowrap">{{ __('Team members') }}</flux:heading>
            <flux:separator class="ml-3" />
            @if ($this->permissions->canCreateInvitation)
                <flux:button
                    size="sm"
                    variant="primary"
                    color="emerald"
                    icon:trailing="arrow-right"
                    class="rounded-full!"
                    :href="route('teams.invite', $teamModel->slug)"
                    wire:navigate
                    data-test="invite-member-button"
                >
                    {{ __('Invite member') }}
                </flux:button>
            @endif
        </div>

        <div class="w-full rounded-lg ring-1 ring-zinc-800/15 shadow-xs dark:ring-white/20 px-3 mt-4">
            <flux:table class="whitespace-normal!">
                <flux:table.columns>
                    <flux:table.column class="w-full">{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($members as $member)
                        <flux:table.row :key="$member['id']">
                            <flux:table.cell variant="strong">
                                {{ $member['name'] }}
                                @if ($member['id'] == $currentUserId)
                                    <span class="text-zinc-400 font-normal ml-1">(you)</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $member['email'] }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($member['role'] !== 'owner' && $this->permissions->canUpdateMember)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:badge size="sm" class="cursor-pointer" data-test="member-role-trigger">{{ $member['role_label'] }}</flux:badge>
                                        <flux:menu>
                                            @foreach ($availableRoles as $role)
                                                <flux:menu.item
                                                    as="button"
                                                    type="button"
                                                    wire:click="updateMember({{ $member['id'] }}, '{{ $role['value'] }}')"
                                                    data-test="member-role-option"
                                                >
                                                    {{ $role['label'] }}
                                                </flux:menu.item>
                                            @endforeach
                                        </flux:menu>
                                    </flux:dropdown>
                                @else
                                    <flux:badge size="sm">{{ $member['role_label'] }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($member['role'] !== 'owner' && $this->permissions->canRemoveMember)
                                    <flux:button
                                        variant="danger"
                                        size="sm"
                                        wire:click="removeMember({{ $member['id'] }})"
                                        wire:confirm="{{ __('Are you sure you want to remove :name from this team?', ['name' => $member['name']]) }}"
                                        data-test="member-remove-button"
                                    >
                                        {{ __('Remove') }}
                                    </flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    @if (count($invitations) > 0)
        <div>
            <div class="flex items-center">
                <flux:heading class="text-nowrap">{{ __('Pending invitations') }}</flux:heading>
                <flux:separator class="ml-3" />
            </div>

            <div class="w-full rounded-lg ring-1 ring-zinc-800/15 shadow-xs dark:ring-white/20 px-3 mt-4">
                <flux:table class="whitespace-normal!">
                    <flux:table.columns>
                        <flux:table.column class="w-full">{{ __('Email') }}</flux:table.column>
                        <flux:table.column>{{ __('Role') }}</flux:table.column>
                        <flux:table.column>{{ __('Sent') }}</flux:table.column>
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($invitations as $invitation)
                            <flux:table.row :key="$invitation['code']">
                                <flux:table.cell variant="strong">{{ $invitation['email'] }}</flux:table.cell>
                                <flux:table.cell>{{ $invitation['role_label'] }}</flux:table.cell>
                                <flux:table.cell>{{ $invitation['sent'] }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($this->permissions->canCancelInvitation)
                                        <flux:button
                                            variant="danger"
                                            size="sm"
                                            wire:click="cancelInvitation('{{ $invitation['code'] }}')"
                                            wire:confirm="{{ __('Are you sure you want to cancel the invitation for :email?', ['email' => $invitation['email']]) }}"
                                            data-test="invitation-cancel-button"
                                        >
                                            {{ __('Cancel') }}
                                        </flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    @endif

    @if ($this->permissions->canDeleteTeam && ! $teamModel->is_personal)
        <div>
            <div class="flex items-center">
                <flux:heading class="text-nowrap">{{ __('Delete team') }}</flux:heading>
                <flux:separator class="ml-3" />
                <flux:button
                    size="sm"
                    :href="route('teams.delete', $teamModel->slug)"
                    variant="danger"
                    icon:trailing="arrow-right"
                    class="rounded-full!"
                    wire:navigate
                    data-test="delete-team-button"
                >
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    @endif

    <div class="flex items-center">
        <flux:button size="sm" :href="route('teams.index')" wire:navigate icon="arrow-left" class="rounded-full!">
            {{ __('Return to Teams') }}
        </flux:button>
        <flux:separator class="ml-3" />
    </div>
</div>
