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

    public function mount(Team $team): void
    {
        $this->teamModel = $team;
        $this->teamName = $team->name;

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
                'created_at' => $invitation->created_at->toISOString(),
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

<div class="space-y-8">
    <div>
        <div class="flex items-center gap-3">
            <flux:heading class="whitespace-nowrap">{{ $teamModel->name }}</flux:heading>
            <flux:separator />
        </div>
        <p class="text-sm/6 mt-1 max-w-prose">Manage your team settings, members, and invitations. Teams have owner, admin, and member roles. Owners and admins can invite members, assign roles, and manage team resources.</p>
    </div>

    @if ($this->permissions->canUpdateTeam)
        <form wire:submit="updateTeam">
            <flux:input size="sm" wire:model="teamName" :label="__('Team name')" required class="max-w-md" data-test="team-name-input" />

            <flux:button size="sm" variant="primary" color="blue" icon:trailing="arrow-right" type="submit" class="mt-3" data-test="team-save-button">
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
        <p class="text-sm/6 mt-1 max-w-prose">{{ __('Manage who has access to this team.') }}</p>

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
                            <flux:table.cell variant="strong">{{ $member['name'] }}</flux:table.cell>
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
                                        icon:trailing="arrow-right"
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
            <div class="flex items-center gap-3">
                <flux:heading class="text-nowrap">{{ __('Pending invitations') }}</flux:heading>
                <flux:separator />
            </div>
            <p class="text-sm/6 mt-1 max-w-prose">{{ __('These people have been invited but haven\'t accepted yet.') }}</p>

            <div class="space-y-3 mt-4">
                @foreach ($invitations as $invitation)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="invitation-row">
                        <div class="flex items-center gap-4">
                            <div class="flex size-10 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <flux:icon name="envelope" class="text-zinc-500" />
                            </div>
                            <div>
                                <div class="font-medium">{{ $invitation['email'] }}</div>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $invitation['role_label'] }}</flux:text>
                            </div>
                        </div>

                        @if ($this->permissions->canCancelInvitation)
                            <flux:tooltip :content="__('Cancel invitation')">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="x-mark"
                                    wire:click="cancelInvitation('{{ $invitation['code'] }}')"
                                    wire:confirm="{{ __('Are you sure you want to cancel the invitation for :email?', ['email' => $invitation['email']]) }}"
                                    data-test="invitation-cancel-button"
                                />
                            </flux:tooltip>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($this->permissions->canDeleteTeam && ! $teamModel->is_personal)
        <div>
            <div class="flex items-center">
                <flux:heading class="text-nowrap">{{ __('Danger Zone') }}</flux:heading>
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
                    {{ __('Delete team') }}
                </flux:button>
            </div>
            <p class="text-sm/6 mt-1 max-w-prose">{{ __('Permanently delete this team and remove all members.') }}</p>
        </div>
    @endif

    <div class="flex items-center">
        <flux:button size="sm" :href="route('teams.index')" wire:navigate icon="arrow-left" class="rounded-full!">
            {{ __('Return to Teams') }}
        </flux:button>
        <flux:separator class="ml-3" />
    </div>
</div>
