<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <h1 class="text-lg font-semibold text-gray-900">Field supervisor invitation</h1>
        <p class="mt-2 text-sm text-gray-600">
            You are invited by {{ $invitation->placement->student->name }} to supervise their internship at
            {{ $invitation->placement->company_name }}.
        </p>

        <x-validation-errors class="mt-4" />

        <form method="POST" action="{{ route('field-supervisor-invitations.accept', $token) }}" class="mt-5 grid gap-4">
            @csrf
            @if ($existingUser)
                <p class="text-sm text-gray-700">This email already has a SIMS field-supervisor account. Accepting will connect it to this placement.</p>
            @else
                <label class="grid gap-1 text-sm font-semibold">Your full name
                    <input name="name" value="{{ old('name') }}" maxlength="255" required class="rounded border-gray-300 text-sm">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Choose a password
                    <input name="password" type="password" required autocomplete="new-password" class="rounded border-gray-300 text-sm">
                </label>
                <label class="grid gap-1 text-sm font-semibold">Confirm password
                    <input name="password_confirmation" type="password" required autocomplete="new-password" class="rounded border-gray-300 text-sm">
                </label>
            @endif
            <button class="attachify-primary-action justify-self-start">Accept invitation</button>
        </form>
    </x-authentication-card>
</x-guest-layout>
