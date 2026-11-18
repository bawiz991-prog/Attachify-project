<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-6">
            <h1 class="text-lg font-semibold text-gray-900">Set a private password</h1>
            <p class="mt-2 text-sm text-gray-600">For your security, replace the temporary password before continuing.</p>
        </div>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('account.password.update') }}">
            @csrf

            <div>
                <x-label for="password" value="{{ __('New password') }}" />
                <x-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="mt-4">
                <x-label for="password_confirmation" value="{{ __('Confirm new password') }}" />
                <x-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-button>{{ __('Update password') }}</x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
