<footer class="attachify-footer">
    <div class="attachify-footer-inner">
        <a href="{{ url('/') }}" aria-label="ATTACHIFY home">
            <x-attachify-logo class="attachify-footer-logo" />
        </a>
        <p>© {{ now()->year }} ATTACHIFY. Every workday, accounted for.</p>
        @auth
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('help') }}">Help</a>
                <a href="{{ route('contact') }}">Contact us</a>
                <a href="{{ route('dashboard') }}">Return to your workspace</a>
            </div>
        @else
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('help') }}">Help</a>
                <a href="{{ route('contact') }}">Contact us</a>
                <a href="{{ route('login') }}">Sign in to your account</a>
            </div>
        @endauth
    </div>
</footer>