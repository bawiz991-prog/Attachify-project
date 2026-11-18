<section class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
    <img
        class="size-14 shrink-0 rounded-full object-cover ring-2 ring-green-800"
        src="{{ auth()->user()->profile_photo_url }}"
        alt="{{ auth()->user()->name }}'s profile photo"
    >

    <div class="min-w-0">
        <p class="text-sm font-semibold text-gray-800">Profile picture</p>
        @if (session('photo_status'))
            <p class="text-xs text-green-800" role="status">{{ session('photo_status') }}</p>
        @else
            <p class="text-xs text-gray-500">Choose a JPG or PNG (max 1 MB).</p>
        @endif

        <form method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data" class="mt-2">
            @csrf
            <label for="profile-photo-{{ auth()->id() }}" class="sr-only">Choose a profile picture</label>
            <input
                id="profile-photo-{{ auth()->id() }}"
                type="file"
                name="photo"
                accept=".jpg,.jpeg,.png"
                required
                class="block w-full max-w-56 text-xs text-gray-600 file:mr-2 file:rounded file:border-0 file:bg-gray-100 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-gray-700"
            >
            @error('photo')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            <button type="submit" class="mt-2 rounded bg-green-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-900">
                Save picture
            </button>
        </form>
    </div>
</section>
