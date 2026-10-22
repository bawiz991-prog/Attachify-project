<x-app-layout>
    <x-slot name="header">
        <div class="attachify-workspace-heading">
            <div><p class="attachify-eyebrow">ATTACHIFY / UPDATES</p><h1>Notifications</h1></div>
            <a href="{{ route('dashboard') }}" class="attachify-back-link">Back to workspace</a>
        </div>
    </x-slot>
    <div class="attachify-workspace">
        <div class="attachify-panel space-y-3">
            @forelse ($notifications as $notification)
                <article class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 py-4 last:border-0">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $notification->data['title'] ?? 'ATTACHIFY update' }} @unless($notification->read_at)<span class="text-xs font-medium text-green-800">New</span>@endunless</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-600">{{ $notification->data['message'] ?? '' }}</p>
                        <time class="mt-2 block text-xs text-gray-500">{{ $notification->created_at->format('M j, Y g:i A') }}</time>
                    </div>
                    @unless($notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf<button class="text-sm font-semibold text-green-800 underline">Mark as read</button></form>
                    @endunless
                </article>
            @empty
                <p class="text-sm text-gray-600">You’re all caught up. New account, report, and meeting updates will appear here.</p>
            @endforelse
            {{ $notifications->links() }}
        </div>
    </div>
</x-app-layout>
