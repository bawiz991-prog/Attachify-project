<x-app-layout>
    <x-slot name="header">
        <div class="attachify-workspace-heading">
            <div>
                <p class="attachify-eyebrow">ATTACHIFY / PLACEMENT PORTAL</p>
                <h1>Set up your placement</h1>
            </div>
            <a class="attachify-back-link" href="{{ route('dashboard') }}">&#8592; Back to dashboard</a>
        </div>
    </x-slot>

    <div class="attachify-workspace attachify-form-page">
        <div class="attachify-form-intro">
            <p>Once your company has accepted your application, add the placement details and your agreed work pattern here.</p>
            <div class="attachify-native-reminder"><strong>Faculty letter process stays the same.</strong> Request your placement letter from the faculty and get the dean’s signature through the normal process before uploading your company acceptance.</div>
        </div>

        <form method="POST" action="{{ route('placements.store') }}" enctype="multipart/form-data" class="attachify-panel attachify-placement-form">
            @csrf
            <section class="attachify-form-section">
                <div class="attachify-form-section-title"><span>01</span><div><h2>Company details</h2><p>Where will you complete your internship?</p></div></div>
                <div class="attachify-form-fields">
                    <div class="attachify-field">
                        <label for="company_name">Company or organization</label>
                        <input id="company_name" name="company_name" value="{{ old('company_name') }}" autocomplete="organization" required>
                        @error('company_name') <small class="attachify-field-error">{{ $message }}</small> @enderror
                    </div>
                    <div class="attachify-field">
                        <label for="company_address">Workplace address <span>Optional</span></label>
                        <textarea id="company_address" name="company_address" rows="2">{{ old('company_address') }}</textarea>
                        @error('company_address') <small class="attachify-field-error">{{ $message }}</small> @enderror
                    </div>
                    <div class="attachify-field">
                        <label for="acceptance_document">Company acceptance letter</label>
                        <input id="acceptance_document" name="acceptance_document" type="file" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small>PDF or image, up to 10 MB. Stored privately with your placement.</small>
                        @error('acceptance_document') <small class="attachify-field-error">{{ $message }}</small> @enderror
                    </div>
                </div>
            </section>

            <section class="attachify-form-section">
                <div class="attachify-form-section-title"><span>02</span><div><h2>Internship period</h2><p>Set the first and last day of your placement.</p></div></div>
                <div class="attachify-form-fields attachify-date-fields">
                    <div class="attachify-field"><label for="start_date">Start date</label><input id="start_date" name="start_date" type="date" value="{{ old('start_date') }}" required>@error('start_date') <small class="attachify-field-error">{{ $message }}</small> @enderror</div>
                    <div class="attachify-field"><label for="end_date">End date</label><input id="end_date" name="end_date" type="date" value="{{ old('end_date') }}" required>@error('end_date') <small class="attachify-field-error">{{ $message }}</small> @enderror</div>
                </div>
            </section>

            <section class="attachify-form-section">
                <div class="attachify-form-section-title"><span>03</span><div><h2>Weekly work schedule</h2><p>Select your workdays and the hours you have agreed.</p></div></div>
                <div>
                    <fieldset class="attachify-day-picker">
                        <legend>Workdays</legend>
                        @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $day => $label)
                            <label><input type="checkbox" name="workdays[]" value="{{ $day }}" @checked(in_array((string) $day, old('workdays', []), true))><span>{{ $label }}</span></label>
                        @endforeach
                    </fieldset>
                    @error('workdays') <small class="attachify-field-error">{{ $message }}</small> @enderror
                    @error('workdays.*') <small class="attachify-field-error">{{ $message }}</small> @enderror
                    <div class="attachify-form-fields attachify-date-fields attachify-time-fields">
                        <div class="attachify-field"><label for="start_time">Start time</label><input id="start_time" name="start_time" type="time" value="{{ old('start_time', '08:00') }}" required>@error('start_time') <small class="attachify-field-error">{{ $message }}</small> @enderror</div>
                        <div class="attachify-field"><label for="end_time">End time</label><input id="end_time" name="end_time" type="time" value="{{ old('end_time', '17:00') }}" required>@error('end_time') <small class="attachify-field-error">{{ $message }}</small> @enderror</div>
                    </div>
                </div>
            </section>

            <div class="attachify-form-submit"><p>Your schedule determines which days can have daily reports.</p><button class="attachify-primary-action" type="submit">Save placement <span aria-hidden="true">&#8594;</span></button></div>
        </form>
    </div>
</x-app-layout>