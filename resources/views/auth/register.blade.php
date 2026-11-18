<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="space-y-4 text-sm text-gray-700">
            <h1 class="text-xl font-semibold text-gray-900">SIMS accounts are provisioned by an administrator</h1>
            <p>Students, academic supervisors, and coordinators should contact their faculty or a SIMS administrator to request an account.</p>
            <p>Field supervisors receive an invitation link from an administrator.</p>
            <a class="inline-flex rounded bg-green-900 px-4 py-2 font-semibold text-white" href="{{ route('login') }}">
                Sign in
            </a>
        </div>
    </x-authentication-card>
</x-guest-layout>
