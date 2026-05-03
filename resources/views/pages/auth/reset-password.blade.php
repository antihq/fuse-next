<x-layouts::app :title="__('Reset password')">
    <div>
        <div class="flex items-center gap-3">
            <flux:heading class="whitespace-nowrap">{{ __('Reset password') }}</flux:heading>
            <flux:separator />
        </div>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <x-auth-session-status :status="session('status')" />

                <form method="POST" action="{{ route('password.update') }}" class="space-y-8">
                    @csrf

                    <input type="hidden" name="token" value="{{ request()->route('token') }}">

                    <flux:input
                        size="sm"
                        name="email"
                        value="{{ request('email') }}"
                        :label="__('Email')"
                        type="email"
                        required
                        autocomplete="email"
                    />

                    <flux:input
                        size="sm"
                        name="password"
                        :label="__('Password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        :placeholder="__('Password')"
                        viewable
                    />

                    <flux:input
                        size="sm"
                        name="password_confirmation"
                        :label="__('Confirm password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        :placeholder="__('Confirm password')"
                        viewable
                    />

                    <div class="flex items-center justify-end">
                        <flux:button size="sm" type="submit" variant="primary" data-test="reset-password-button">
                            {{ __('Reset password') }}
                        </flux:button>
                    </div>
                </form>
            </div>

            <div class="text-sm/6 space-y-3">
                <p>Choose a strong password. Use at least 8 characters — longer is better.</p>
                <p>After resetting, you'll be redirected to the login page.</p>
            </div>
        </div>
    </div>
</x-layouts::app>
