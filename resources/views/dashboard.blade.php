<x-app-layout>
    <x-slot name="header">
        <div class="attachify-workspace-heading">
            <div>
                <p class="attachify-eyebrow">ATTACHIFY / STUDENT WORKSPACE</p>
                <h1>Your attachment, one day at a time.</h1>
            </div>
            <span class="attachify-role-label">Student</span>
        </div>
    </x-slot>

    <div class="attachify-workspace">
        @if (session('status'))
            <div class="attachify-flash" role="status">{{ session('status') }}</div>
        @endif

        <section class="attachify-welcome-row">
            <div>
                <p class="attachify-eyebrow">YOUR FIELDWORK, IN ONE PLACE</p>
                <h2>Good {{ now()->hour < 12 ? 'morning' : 'day' }}, {{ Str::of(auth()->user()->name)->before(' ') }}.</h2>
                <p>Keep your placement details, work schedule and daily progress up to date.</p>
            </div>
            @if ($placement)
                <a class="attachify-primary-action" href="#daily-report">Write today’s report <span aria-hidden="true">&#8594;</span></a>
            @else
                <a class="attachify-primary-action" href="{{ route('placements.create') }}">Set up your placement <span aria-hidden="true">&#8594;</span></a>
            @endif
        </section>

        <section class="attachify-metrics" aria-label="Placement summary">
            <article class="attachify-metric">
                <span>PLACEMENT</span>
                <strong>{{ $placement ? 'In progress' : 'Not set up' }}</strong>
                <small>{{ $placement?->company_name ?? 'Add your company and acceptance letter' }}</small>
            </article>
            <article class="attachify-metric">
                <span>REGISTERED DAYS</span>
                <strong>{{ $placement?->schedules->count() ?? 0 }} <small>days / week</small></strong>
                <small>{{ $placement ? $placement->schedules->map(fn ($schedule) => \Illuminate\Support\Carbon::now()->startOfWeek()->addDays($schedule->day_of_week - 1)->format('D'))->join(', ') : 'Your agreed work pattern' }}</small>
            </article>
            <article class="attachify-metric">
                <span>REPORTS SUBMITTED</span>
                <strong>{{ $placement?->daily_reports_count ?? 0 }}</strong>
                <small>Daily activity records</small>
            </article>
        </section>

        <div class="attachify-workspace-grid">
            <div class="attachify-main-column">
                <section class="attachify-panel">
                    <div class="attachify-panel-heading">
                        <div>
                            <p class="attachify-eyebrow">PLACEMENT PORTAL</p>
                            <h2>{{ $placement ? $placement->company_name : 'Set up your placement' }}</h2>
                        </div>
                        @if ($placement)
                            <span class="attachify-status"><i></i> In progress</span>
                        @endif
                    </div>

                    @if ($placement)
                        <p class="attachify-placement-address">{{ $placement->company_address ?: 'Company address not added' }}</p>
                        <div class="attachify-placement-facts">
                            <div><span>START DATE</span><strong>{{ $placement->start_date->format('M j, Y') }}</strong></div>
                            <div><span>END DATE</span><strong>{{ $placement->end_date->format('M j, Y') }}</strong></div>
                            <div><span>ACCEPTANCE LETTER</span><strong>Uploaded</strong></div>
                        </div>
                        <div class="attachify-supervisor-strip">
                            <div><span class="attachify-avatar">F</span><p><small>FIELD SUPERVISOR</small><strong>{{ $placement->fieldSupervisor?->name ?? 'Not connected yet' }}</strong></p></div>
                            <div><span class="attachify-avatar attachify-avatar-academic">A</span><p><small>ACADEMIC SUPERVISOR</small><strong>{{ $placement->academicSupervisor?->name ?? 'Not connected yet' }}</strong></p></div>
                        </div>
                        @if ($placement->fieldSupervisor)
                            <p class="attachify-panel-copy">Connected account: {{ $placement->fieldSupervisor->email }}</p>
                        @else
                            <form method="POST" action="{{ route('placements.field-supervisor', $placement) }}" class="attachify-report-form attachify-connect-form">
                                @csrf
                                <label for="field_supervisor_email">Connect your field supervisor</label>
                                <input id="field_supervisor_email" name="field_supervisor_email" type="email" value="{{ old('field_supervisor_email') }}" placeholder="supervisor@company.com" autocomplete="email" required>
                                @error('field_supervisor_email') <p class="attachify-field-error">{{ $message }}</p> @enderror
                                <small class="text-xs text-gray-500">Your supervisor must register with this work email before you connect them.</small>
                                <button class="attachify-primary-action" type="submit">Connect supervisor <span aria-hidden="true">&#8594;</span></button>
                            </form>
                        @endif
                        @if ($placement->academicSupervisor)
                            <p class="attachify-panel-copy">Academic supervisor: {{ $placement->academicSupervisor->email }}</p>
                        @else
                            <form method="POST" action="{{ route('placements.academic-supervisor', $placement) }}" class="attachify-report-form attachify-connect-form">
                                @csrf
                                <label for="academic_supervisor_email">Connect your academic supervisor</label>
                                <input id="academic_supervisor_email" name="academic_supervisor_email" type="email" value="{{ old('academic_supervisor_email') }}" placeholder="supervisor@university.edu" autocomplete="email" required>
                                @error('academic_supervisor_email') <p class="attachify-field-error">{{ $message }}</p> @enderror
                                <small class="text-xs text-gray-500">Use the school email of the supervisor assigned to you by your faculty.</small>
                                <button class="attachify-primary-action" type="submit">Connect academic supervisor <span aria-hidden="true">&#8594;</span></button>
                            </form>
                        @endif
                    @else
                        <p class="attachify-panel-copy">Start after your company has accepted you. Your faculty’s letter request and dean’s signature remain part of the existing process.</p>
                        <ol class="attachify-onboarding-steps">
                            <li><span>01</span><div><strong>Get your faculty placement letter</strong><small>Follow your faculty’s normal request and signature process.</small></div></li>
                            <li><span>02</span><div><strong>Upload your company acceptance</strong><small>Keep the acceptance letter ready as a PDF or image.</small></div></li>
                            <li><span>03</span><div><strong>Register your workdays</strong><small>Add the days and hours agreed with your company.</small></div></li>
                        </ol>
                        <a class="attachify-text-action" href="{{ route('placements.create') }}">Continue to placement setup <span aria-hidden="true">&#8594;</span></a>
                    @endif
                </section>

                <section class="attachify-panel attachify-report-panel" id="daily-report">
                    <div class="attachify-panel-heading">
                        <div>
                            <p class="attachify-eyebrow">DAILY LOG</p>
                            <h2>Recent activity</h2>
                        </div>
                        @if ($placement)
                            <span class="attachify-report-count">{{ $placement->daily_reports_count }} total</span>
                        @endif
                    </div>

                    @if ($placement)
                        <form method="POST" action="{{ route('reports.store', $placement) }}" class="attachify-report-form">
                            @csrf
                            <label for="report_date">Report date</label>
                            <input id="report_date" name="report_date" type="date" value="{{ old('report_date', now()->toDateString()) }}" min="{{ $placement->start_date->toDateString() }}" max="{{ $placement->end_date->toDateString() }}" required>
                            @error('report_date') <p class="attachify-field-error">{{ $message }}</p> @enderror
                            <label for="activities">What did you work on?</label>
                            <textarea id="activities" name="activities" rows="4" placeholder="Describe the tasks, tools or skills you worked with today..." required>{{ old('activities') }}</textarea>
                            @error('activities') <p class="attachify-field-error">{{ $message }}</p> @enderror
                            <button class="attachify-primary-action" type="submit">Submit daily report <span aria-hidden="true">&#8594;</span></button>
                        </form>
                    @else
                        <div class="attachify-empty-state">
                            <span class="attachify-empty-symbol" aria-hidden="true">&#9998;</span>
                            <p>Your daily log opens once your placement and weekly schedule are registered.</p>
                        </div>
                    @endif

                    @if ($placement && $placement->dailyReports->isNotEmpty())
                        <div class="attachify-report-list">
                            @foreach ($placement->dailyReports as $report)
                                <article class="attachify-report-item">
                                    <time datetime="{{ $report->report_date->toDateString() }}">{{ $report->report_date->format('D, M j') }}</time>
                                    <p>{{ $report->activities }}</p>
                                    <span>{{ ucfirst($report->status) }}</span>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            <aside class="attachify-side-column">
                <section class="attachify-panel attachify-schedule-panel">
                    <p class="attachify-eyebrow">YOUR WORK WEEK</p>
                    <h2>Registered schedule</h2>
                    @if ($placement && $placement->schedules->isNotEmpty())
                        <div class="attachify-schedule-list">
                            @foreach ($placement->schedules->sortBy('day_of_week') as $schedule)
                                <div><strong>{{ \Illuminate\Support\Carbon::now()->startOfWeek()->addDays($schedule->day_of_week - 1)->format('l') }}</strong><span>{{ \Illuminate\Support\Carbon::parse($schedule->start_time)->format('g:i A') }} – {{ \Illuminate\Support\Carbon::parse($schedule->end_time)->format('g:i A') }}</span></div>
                            @endforeach
                        </div>
                    @else
                        <p class="attachify-panel-copy">Your agreed days and hours will appear here after setup.</p>
                    @endif
                    <div class="attachify-schedule-note"><span aria-hidden="true">i</span> Reports can be submitted for registered workdays only.</div>
                </section>

                <section class="attachify-meeting-panel">
                    <p class="attachify-eyebrow">SUPERVISOR CHECK-IN</p>
                    <h2>Stay connected.</h2>
                    <p>Your academic supervisor can review your reports and arrange an evaluation with you.</p>
                    <div class="attachify-meeting-options"><span>Microsoft Teams</span><span>In person</span></div>
                    <small>Meeting scheduling will appear here when your supervisor is connected.</small>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
