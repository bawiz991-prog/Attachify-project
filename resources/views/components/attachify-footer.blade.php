<footer class="attachify-footer">
    <div class="attachify-footer-inner">
        <a href="{{ url('/') }}" aria-label="ATTACHIFY home">
            <x-attachify-logo class="attachify-footer-logo" />
        </a>
        <p>Every workday, accounted for.</p>
        @auth
            <a href="{{ route('dashboard') }}">Return to your workspace</a>
        @else
            <a href="{{ route('login') }}">Sign in to your account</a>
        @endauth
    </div>
</footer>