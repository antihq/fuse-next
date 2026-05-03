<x-layouts::app :title="__('Confirm password')">
    <div>
        <div class="flex items-center gap-3">
            <flux:heading class="whitespace-nowrap">{{ __('Confirm password') }}</flux:heading>
            <flux:separator />
        </div>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <x-auth-session-status :status="session('status')" />

                <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-8">
                    @csrf

                    <flux:input
                        size="sm"
                        name="password"
                        :label="__('Password')"
                        type="password"
                        required
                        autocomplete="current-password"
                        :placeholder="__('Password')"
                        viewable
                    />

                    <div class="flex items-center justify-end">
                        <flux:button size="sm" variant="primary" type="submit" data-test="confirm-password-button">
                            {{ __('Confirm') }}
                        </flux:button>
                    </div>
                </form>
            </div>

            <div class="text-sm/6 space-y-3">
                <p>This is a secure area of the application. Confirm your password before continuing.</p>
            </div>
        </div>
    </div>
</x-layouts::app>
