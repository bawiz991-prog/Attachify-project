<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f2e9">
    <meta name="description" content="ATTACHIFY keeps internship placements, daily reports and supervisor feedback connected.">
    <title>{{ config('app.name', 'ATTACHIFY') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chewy&family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --attachify-ink: #193b31;
            --attachify-muted: #65746c;
            --attachify-paper: #f4f2e9;
            --attachify-white: #fffefa;
            --attachify-green: #315b48;
            --attachify-lime: #d9ec9a;
            --attachify-coral: #df765d;
            --attachify-line: #d9ddd2;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; background: var(--attachify-paper); color: var(--attachify-ink); font-family: 'DM Sans', sans-serif; }
        a { color: inherit; text-decoration: none; }
        .attachify-page { overflow: hidden; }
        .attachify-shell { width: min(1180px, calc(100% - 64px)); margin-inline: auto; }
        .attachify-site-logo { display: block; width: 145px; height: auto; }
        .attachify-footer-logo { width: 115px; }
        .attachify-header { height: 82px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(25, 59, 49, .12); }
        .attachify-brand { display: inline-flex; align-items: center; gap: 11px; font-family: 'Manrope', sans-serif; font-size: 19px; font-weight: 800; letter-spacing: 0; }
        .attachify-mark { display: grid; width: 34px; height: 34px; place-items: center; background: var(--attachify-ink); color: var(--attachify-lime); border-radius: 4px; font-size: 16px; }
        .attachify-nav { display: flex; align-items: center; gap: 33px; color: #53635b; font-size: 13px; font-weight: 600; }
        .attachify-nav a:not(.attachify-nav-cta):hover { color: var(--attachify-ink); }
        .attachify-nav-cta, .attachify-button { min-height: 45px; display: inline-flex; justify-content: center; align-items: center; gap: 12px; padding: 0 19px; border: 1px solid var(--attachify-ink); border-radius: 4px; background: var(--attachify-ink); color: white; font-size: 13px; font-weight: 700; transition: transform .18s ease, background-color .18s ease; }
        .attachify-nav-cta:hover, .attachify-button:hover { transform: translateY(-2px); background: #285142; }
        .attachify-button-light { background: transparent; color: var(--attachify-ink); }
        .attachify-button-light:hover { background: rgba(25, 59, 49, .06); }
        .attachify-arrow { font-size: 17px; line-height: 1; }

        .attachify-hero { display: grid; grid-template-columns: .92fr 1.08fr; gap: 54px; align-items: center; padding: 72px 0 66px; }
        .attachify-kicker { display: flex; align-items: center; gap: 9px; color: #597865; font-size: 10px; font-weight: 700; letter-spacing: 1.1px; text-transform: uppercase; }
        .attachify-kicker::before { content: ''; width: 22px; height: 2px; background: var(--attachify-coral); }
        .attachify-hero h1 { max-width: 570px; margin: 22px 0 18px; font-family: 'Manrope', sans-serif; font-size: clamp(42px, 5vw, 67px); font-weight: 600; letter-spacing: 0; line-height: 1.06; }
        .attachify-hero h1 span { color: #6d896b; }
        .attachify-hero-copy { max-width: 480px; color: var(--attachify-muted); font-size: 16px; line-height: 1.75; }
        .attachify-actions { display: flex; flex-wrap: wrap; gap: 11px; margin-top: 27px; }
        .attachify-note { display: flex; align-items: center; gap: 8px; margin-top: 19px; color: #68766d; font-size: 12px; }
        .attachify-note-dot { width: 7px; height: 7px; border-radius: 50%; background: #8da86f; }
        .attachify-visual { position: relative; min-height: 430px; }
        .attachify-photo { position: absolute; inset: 0 0 0 34px; overflow: hidden; border-radius: 4px; background: #c9d1c5; }
        .attachify-photo img { width: 100%; height: 100%; object-fit: cover; object-position: center; }
        .attachify-photo::after { content: ''; position: absolute; inset: 45% 0 0; background: linear-gradient(transparent, rgba(20, 43, 34, .56)); }
        .attachify-photo-caption { position: absolute; z-index: 1; right: 23px; bottom: 22px; left: 24px; color: white; }
        .attachify-photo-caption small { display: block; margin-bottom: 7px; color: #e1edc0; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        .attachify-photo-caption strong { max-width: 340px; display: block; font-family: 'Manrope', sans-serif; font-size: 19px; font-weight: 600; line-height: 1.35; }
        .attachify-progress { position: absolute; z-index: 2; top: 33px; left: 0; width: 188px; padding: 17px 17px 16px; border: 1px solid rgba(25, 59, 49, .1); border-radius: 4px; background: var(--attachify-white); box-shadow: 0 10px 35px rgba(31, 51, 37, .11); }
        .attachify-progress-label { color: #718076; font-size: 9px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; }
        .attachify-progress-title { margin-top: 8px; font-family: 'Manrope', sans-serif; font-size: 14px; font-weight: 700; }
        .attachify-progress-bar { height: 5px; margin-top: 13px; overflow: hidden; border-radius: 10px; background: #e9ece4; }
        .attachify-progress-bar span { display: block; width: 42%; height: 100%; border-radius: inherit; background: #8ca96d; }
        .attachify-progress-foot { display: flex; justify-content: space-between; margin-top: 7px; color: #829087; font-size: 10px; }
        .attachify-stamp { position: absolute; right: -16px; top: 0; width: 47px; height: 47px; display: grid; place-items: center; border-radius: 50%; background: var(--attachify-coral); color: white; font-family: 'Manrope', sans-serif; font-size: 17px; font-weight: 700; transform: rotate(9deg); }

        .attachify-process { padding: 35px 0 38px; border-top: 1px solid var(--attachify-line); border-bottom: 1px solid var(--attachify-line); }
        .attachify-process-heading { display: flex; align-items: end; justify-content: space-between; gap: 24px; margin-bottom: 24px; }
        .attachify-process-heading h2, .attachify-supervisors h2 { margin: 0; font-family: 'Manrope', sans-serif; font-size: 24px; font-weight: 700; letter-spacing: 0; }
        .attachify-process-heading p { max-width: 420px; margin: 0; color: var(--attachify-muted); font-size: 12px; line-height: 1.6; }
        .attachify-steps { display: grid; grid-template-columns: repeat(5, 1fr); }
        .attachify-step { min-height: 119px; padding: 15px 20px 8px 0; border-top: 2px solid #bdc8bb; }
        .attachify-step + .attachify-step { padding-left: 18px; border-left: 1px solid var(--attachify-line); }
        .attachify-step-number { color: #71836f; font-size: 10px; font-weight: 700; letter-spacing: .6px; }
        .attachify-step h3 { margin: 11px 0 6px; font-size: 13px; font-weight: 700; }
        .attachify-step p { max-width: 190px; margin: 0; color: var(--attachify-muted); font-size: 11px; line-height: 1.55; }
        .attachify-step-native { border-top-color: var(--attachify-coral); }
        .attachify-step-native .attachify-step-number { color: #b85d48; }

        .attachify-supervisors { display: grid; grid-template-columns: .8fr 1.2fr; gap: 70px; padding: 67px 0 74px; }
        .attachify-supervisors h2 { max-width: 390px; margin: 13px 0 12px; font-size: 31px; line-height: 1.2; }
        .attachify-supervisors-intro { max-width: 365px; color: var(--attachify-muted); font-size: 13px; line-height: 1.7; }
        .attachify-supervisor-list { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-content: center; }
        .attachify-supervisor { padding-top: 16px; border-top: 1px solid #bdc8bb; }
        .attachify-supervisor-index { color: #b56855; font-size: 10px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; }
        .attachify-supervisor h3 { margin: 11px 0 7px; font-family: 'Manrope', sans-serif; font-size: 17px; font-weight: 700; }
        .attachify-supervisor p { margin: 0; color: var(--attachify-muted); font-size: 12px; line-height: 1.7; }
        .attachify-meeting-tag { display: inline-block; margin: 12px 5px 0 0; padding: 5px 8px; border: 1px solid #d0d8ca; border-radius: 3px; color: #526c5b; font-size: 10px; font-weight: 600; }

        @keyframes attachify-enter { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .attachify-hero-copy, .attachify-visual { animation: attachify-enter .65s ease both; }
        .attachify-visual { animation-delay: .12s; }

        @media (max-width: 760px) {
            .attachify-shell { width: min(100% - 36px, 560px); }
            .attachify-header { height: 70px; }
            .attachify-nav { gap: 15px; }
            .attachify-nav > a:not(.attachify-nav-cta) { display: none; }
            .attachify-nav-cta { min-height: 39px; padding-inline: 12px; font-size: 11px; }
            .attachify-brand { font-size: 17px; }
            .attachify-hero { grid-template-columns: 1fr; gap: 35px; padding: 48px 0 45px; }
            .attachify-hero h1 { max-width: 500px; margin-top: 17px; font-size: clamp(42px, 12vw, 58px); }
            .attachify-hero-copy { font-size: 14px; }
            .attachify-visual { min-height: 330px; }
            .attachify-photo { left: 22px; }
            .attachify-progress { top: 24px; width: 166px; padding: 14px; }
            .attachify-stamp { right: -7px; width: 39px; height: 39px; }
            .attachify-process { padding: 30px 0; }
            .attachify-process-heading { display: block; }
            .attachify-process-heading h2 { margin-bottom: 9px; font-size: 21px; }
            .attachify-steps { grid-template-columns: 1fr 1fr; row-gap: 17px; }
            .attachify-step { min-height: 108px; padding-right: 14px; }
            .attachify-step + .attachify-step { padding-left: 13px; }
            .attachify-step:nth-child(odd) { padding-left: 0; border-left: 0; }
            .attachify-step:last-child { grid-column: span 2; min-height: 90px; }
            .attachify-supervisors { grid-template-columns: 1fr; gap: 27px; padding: 48px 0; }
            .attachify-supervisors h2 { font-size: 27px; }
            .attachify-supervisor-list { gap: 20px; }
        }

        @media (max-width: 420px) {
            .attachify-actions { flex-direction: column; align-items: stretch; }
            .attachify-actions .attachify-button { width: 100%; }
            .attachify-visual { min-height: 280px; }
            .attachify-photo-caption strong { font-size: 16px; }
            .attachify-supervisor-list { grid-template-columns: 1fr; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>
<div class="attachify-page">
    <header class="attachify-shell attachify-header">
        <a class="attachify-brand" href="{{ url('/') }}" aria-label="ATTACHIFY home">
            <x-attachify-logo class="attachify-site-logo" />
        </a>
        <nav class="attachify-nav" aria-label="Main navigation">
            <a href="#process">The process</a>
            <a href="#supervisors">For supervisors</a>
            @auth
                <a class="attachify-nav-cta" href="{{ route('dashboard') }}">Open workspace <span class="attachify-arrow" aria-hidden="true">&#8599;</span></a>
            @else
                <a href="{{ route('login') }}">Log in</a>
                <a class="attachify-nav-cta" href="{{ route('register') }}">Create account <span class="attachify-arrow" aria-hidden="true">&#8599;</span></a>
            @endauth
        </nav>
    </header>

    <main>
        <section class="attachify-shell attachify-hero" aria-labelledby="hero-title">
            <div class="attachify-hero-copy">
                <div class="attachify-kicker">Industrial training, connected</div>
                <h1 id="hero-title">Your placement.<br><span>In focus.</span></h1>
                <p class="attachify-hero-copy">One shared space for students and supervisors to follow an internship from the acceptance letter to the final evaluation.</p>
                <div class="attachify-actions">
                    @guest
                        <a class="attachify-button" href="{{ route('register') }}">Start as a student <span class="attachify-arrow" aria-hidden="true">&#8594;</span></a>
                        <a class="attachify-button attachify-button-light" href="#process">See how it works</a>
                    @else
                        <a class="attachify-button" href="{{ route('dashboard') }}">Go to your workspace <span class="attachify-arrow" aria-hidden="true">&#8594;</span></a>
                        <a class="attachify-button attachify-button-light" href="#process">View the process</a>
                    @endguest
                </div>
                <div class="attachify-note"><span class="attachify-note-dot" aria-hidden="true"></span>Register with your school student email</div>
            </div>

            <div class="attachify-visual" aria-label="Students and supervisors working together">
                <div class="attachify-photo">
                    <img src="https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=1100&q=85" alt="Colleagues sharing ideas around a table">
                    <div class="attachify-photo-caption">
                        <small>From first day to final review</small>
                        <strong>Good work deserves a clear record.</strong>
                    </div>
                </div>
                <div class="attachify-progress" aria-label="Example placement progress">
                    <div class="attachify-progress-label">Placement setup</div>
                    <div class="attachify-progress-title">Build your record</div>
                    <div class="attachify-progress-bar"><span></span></div>
                    <div class="attachify-progress-foot"><span>Getting started</span><span>01 / 04</span></div>
                </div>
                <span class="attachify-stamp" aria-hidden="true">A</span>
            </div>
        </section>

        <section class="attachify-shell attachify-process" id="process" aria-labelledby="process-title">
            <div class="attachify-process-heading">
                <h2 id="process-title">A familiar process, better connected.</h2>
                <p>Your faculty’s placement-letter process stays as it is. ATTACHIFY picks up when the company confirms your placement.</p>
            </div>
            <div class="attachify-steps">
                <article class="attachify-step attachify-step-native">
                    <div class="attachify-step-number">BEFORE ATTACHIFY</div>
                    <h3>Request your letter</h3>
                    <p>Write to your faculty and get the dean’s signature through the existing process.</p>
                </article>
                <article class="attachify-step">
                    <div class="attachify-step-number">01</div>
                    <h3>Confirm placement</h3>
                    <p>Upload the acceptance letter from your company.</p>
                </article>
                <article class="attachify-step">
                    <div class="attachify-step-number">02</div>
                    <h3>Set your schedule</h3>
                    <p>Register the days and hours you’ll work during attachment.</p>
                </article>
                <article class="attachify-step">
                    <div class="attachify-step-number">03</div>
                    <h3>Record each day</h3>
                    <p>Write daily reports for the workdays in your schedule.</p>
                </article>
                <article class="attachify-step">
                    <div class="attachify-step-number">04</div>
                    <h3>Review progress</h3>
                    <p>Share your progress with both supervisors for feedback.</p>
                </article>
            </div>
        </section>

        <section class="attachify-shell attachify-supervisors" id="supervisors" aria-labelledby="supervisors-title">
            <div>
                <div class="attachify-kicker">Shared supervision</div>
                <h2 id="supervisors-title">Two supervisors.<br>One student journey.</h2>
                <p class="attachify-supervisors-intro">Students connect the supervisors assigned through their faculty and company. Each can follow progress and leave feedback on submitted reports.</p>
            </div>
            <div class="attachify-supervisor-list">
                <article class="attachify-supervisor">
                    <div class="attachify-supervisor-index">At the workplace</div>
                    <h3>Field supervisor</h3>
                    <p>The student helps their workplace supervisor join using the supervisor’s email, then links that account to their student profile.</p>
                </article>
                <article class="attachify-supervisor">
                    <div class="attachify-supervisor-index">From the faculty</div>
                    <h3>Academic supervisor</h3>
                    <p>Faculty-assigned supervisors register with school email. Students add the supervisor listed for them by the faculty.</p>
                    <span class="attachify-meeting-tag">Microsoft Teams</span>
                    <span class="attachify-meeting-tag">In person</span>
                </article>
            </div>
        </section>
    </main>

    <x-attachify-footer />
</div>
</body>
</html>