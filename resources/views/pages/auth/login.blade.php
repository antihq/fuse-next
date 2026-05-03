<x-layouts::app :title="__('Log in')">
    <div>
        <div class="flex items-center gap-3">
            <flux:heading class="whitespace-nowrap">{{ __('Log in') }}</flux:heading>
            <flux:separator />
        </div>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <x-auth-session-status :status="session('status')" />

                <form method="POST" action="{{ route('login.store') }}" class="space-y-8">
                    @csrf

                    <flux:input
                        size="sm"
                        name="email"
                        :label="__('Email address')"
                        :value="old('email')"
                        type="email"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="email@example.com"
                    />

                    <div class="relative">
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

                        @if (Route::has('password.request'))
                            <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                                {{ __('Forgot your password?') }}
                            </flux:link>
                        @endif
                    </div>

                    <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

                    <div class="flex items-center justify-end">
                        <flux:button size="sm" variant="primary" type="submit" data-test="login-button">
                            {{ __('Log in') }}
                        </flux:button>
                    </div>
                </form>

                @if (Route::has('register'))
                    <div class="mt-4 space-x-1 rtl:space-x-reverse text-sm/6 text-zinc-600 dark:text-zinc-400">
                        <span>{{ __('Don\'t have an account?') }}</span>
                        <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
                    </div>
                @endif
            </div>

            <div class="text-sm/6 space-y-3">
                <p>Fuse provisions VPS servers and deploys Laravel applications. No agent, no daemon — you run one-liner commands on your own server over SSH.</p>
                <div class="w-full rounded-lg ring-1 ring-zinc-800/15 shadow-xs dark:ring-white/20 px-3">
                    <x-description.list>
                        <x-description.term>Requirement</x-description.term>
                        <x-description.details>Fresh Ubuntu 24.04 VPS</x-description.details>

                        <x-description.term>Access</x-description.term>
                        <x-description.details>Root SSH</x-description.details>

                        <x-description.term>DNS</x-description.term>
                        <x-description.details>A record pointing to server IP</x-description.details>

                        <x-description.term>Agent</x-description.term>
                        <x-description.details>None — signed URLs only</x-description.details>
                    </x-description.list>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
