<x-layouts::app :title="__('Email verification')">
    <div>
        <div class="flex items-center gap-3">
            <flux:heading class="whitespace-nowrap">{{ __('Email verification') }}</flux:heading>
            <flux:separator />
        </div>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="space-y-4">
                <p class="text-sm/6">
                    {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
                </p>

                @if (session('status') == 'verification-link-sent')
                    <p class="text-sm/6 font-medium !dark:text-green-400 !text-green-600">
                        {{ __('A new verification link has been sent to the email address you provided during registration.') }}
                    </p>
                @endif

                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <flux:button size="sm" type="submit" variant="primary">
                            {{ __('Resend verification email') }}
                        </flux:button>
                    </form>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:button size="sm" variant="ghost" type="submit" data-test="logout-button">
                            {{ __('Log out') }}
                        </flux:button>
                    </form>
                </div>
            </div>

            <div class="text-sm/6 space-y-3">
                <p>Check your inbox and spam folder. The verification link expires after 60 minutes.</p>
                <p>If the link has expired, use the resend button to get a new one.</p>
            </div>
        </div>
    </div>
</x-layouts::app>
