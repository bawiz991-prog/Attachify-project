<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo"><x-authentication-card-logo /></x-slot>
        <p class="attachify-eyebrow">ATTACHIFY / SUPPORT</p>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">Contact us</h1>
        <p class="mt-2 text-sm text-gray-600">Send a message to the support team. Signed-in accounts will receive replies in their notifications.</p>
        @if (session('status'))
            <div role="status" class="mt-4 rounded bg-green-50 p-3 text-sm text-green-900">{{ session('status') }}</div>
        @endif
        <x-validation-errors class="mt-4" />
        <form method="POST" action="{{ route('contact.store') }}" class="mt-5 space-y-4">
            @csrf
            <div><x-label for="name" value="Your name" /><x-input id="name" name="name" class="mt-1 block w-full" :value="old('name', auth()->user()?->name)" required maxlength="255" /></div>
            <div><x-label for="email" value="Email address" /><x-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', auth()->user()?->email)" required maxlength="255" /></div>
            <div><x-label for="subject" value="Subject" /><x-input id="subject" name="subject" class="mt-1 block w-full" :value="old('subject')" required maxlength="255" /></div>
            <div><x-label for="message" value="How can we help?" /><textarea id="message" name="message" rows="5" required minlength="10" maxlength="5000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('message') }}</textarea></div>
            <x-button>Send message</x-button>
        </form>
    </x-authentication-card>
</x-guest-layout>
