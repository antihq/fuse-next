<x-layouts::app :title="__('Forgot password')">
    <div>
        <div class="flex items-center gap-3">
            <flux:heading class="whitespace-nowrap">{{ __('Forgot password') }}</flux:heading>
            <flux:separator />
        </div>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <x-auth-session-status :status="session('status')" />

                <form method="POST" action="{{ route('password.email') }}" class="space-y-8">
                    @csrf

                    <flux:input
                        size="sm"
                        name="email"
                        :label="__('Email address')"
                        type="email"
                        required
                        autofocus
                        placeholder="email@example.com"
                    />

                    <div class="flex items-center justify-end">
                        <flux:button size="sm" variant="primary" type="submit" data-test="email-password-reset-link-button">
                            {{ __('Email password reset link') }}
                        </flux:button>
                    </div>
                </form>

                <div class="mt-4 space-x-1 rtl:space-x-reverse text-sm/6 text-zinc-600 dark:text-zinc-400">
                    <span>{{ __('Or, return to') }}</span>
                    <flux:link :href="route('login')" wire:navigate>{{ __('log in') }}</flux:link>
                </div>
            </div>

            <div class="text-sm/6 space-y-3">
                <p>Enter the email address associated with your account. We'll send a link to reset your password.</p>
                <p>The link expires after 60 minutes. If you don't receive the email, check your spam folder or try again.</p>
            </div>
        </div>
    </div>
</x-layouts::app>
