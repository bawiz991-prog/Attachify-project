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
        @if (! auth()->user()->isApproved())
            <div class="attachify-flash" role="status">
                Your student details are {{ auth()->user()->verification_status }}. Placement tools stay locked until an administrator verifies your course, registration number, and student number.
                @if (auth()->user()->verification_notes) <span class="block mt-1">Administrator note: {{ auth()->user()->verification_notes }}</span> @endif
                <a class="ml-2 font-semibold underline" href="{{ route('contact') }}">Contact support</a>
            </div>
        @endif
        <details class="attachify-panel mb-5" id="student-details">
            <summary class="cursor-pointer font-semibold">Review or update your student identifiers</summary>
            <form method="POST" action="{{ route('student.identity.update') }}" class="mt-4 grid gap-3 md:grid-cols-3">
                @csrf
                @method('PUT')
                <label class="grid gap-1 text-xs font-semibold">Course / programme
                    <input name="course" value="{{ old('course', auth()->user()->course) }}" required maxlength="255" class="rounded border-gray-300 text-sm">
                </label>
                <label class="grid gap-1 text-xs font-semibold">Registration number
                    <input name="registration_number" value="{{ old('registration_number', auth()->user()->registration_number) }}" required maxlength="100" class="rounded border-gray-300 text-sm">
                </label>
                <label class="grid gap-1 text-xs font-semibold">Student number
                    <input name="student_number" value="{{ old('student_number', auth()->user()->student_number) }}" required maxlength="100" class="rounded border-gray-300 text-sm">
                </label>
                <button class="attachify-primary-action justify-self-start md:col-span-3">Save student details</button>
            </form>
        </details>
        @if ($notifications->isNotEmpty())
            <section class="attachify-panel mb-5" aria-label="Recent notifications">
                <div class="flex items-center justify-between gap-3"><h2 class="font-semibold">New updates</h2><a class="text-sm font-semibold text-green-800 underline" href="{{ route('notifications.index') }}">All notifications</a></div>
                @foreach ($notifications as $notification)
                    <p class="mt-2 text-sm"><strong>{{ $notification->data['title'] ?? 'ATTACHIFY update' }}:</strong> {{ $notification->data['message'] ?? '' }}</p>
                @endforeach
            </section>
        @endif

        <section class="attachify-welcome-row">
            <div>
                <p class="attachify-eyebrow">YOUR FIELDWORK, IN ONE PLACE</p>
                <h2>Good {{ now()->hour < 12 ? 'morning' : 'day' }}, {{ Str::of(auth()->user()->name)->before(' ') }}.</h2>
                <p>Keep your placement details, work schedule and daily progress up to date.</p>
            </div>
            @if ($placement)
                <a class="attachify-primary-action" href="#daily-report">Write today’s report <span aria-hidden="true">&#8594;</span></a>
            @elseif (auth()->user()->isApproved())
                <a class="attachify-primary-action" href="{{ route('placements.create') }}">Set up your placement <span aria-hidden="true">&#8594;</span></a>
            @else
                <a class="attachify-primary-action" href="#student-details">Review student details <span aria-hidden="true">&#8594;</span></a>
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
                            <div><span>DOCUMENT REVIEW</span><strong>{{ ucfirst($placement->document_status) }}</strong></div>
                        </div>
                        @if ($placement->document_status !== 'approved')
                            <p class="attachify-panel-copy">An administrator must review both PDFs and confirm the organization details match before daily reports are enabled. {{ $placement->document_review_notes }}</p>
                        @endif
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
                        @if (auth()->user()->isApproved())
                            <p class="attachify-panel-copy">Start after your company has accepted you. Your faculty’s letter request and dean’s signature remain part of the existing process.</p>
                            <ol class="attachify-onboarding-steps">
                                <li><span>01</span><div><strong>Get your faculty placement letter</strong><small>Follow your faculty’s normal request and signature process.</small></div></li>
                                <li><span>02</span><div><strong>Upload both signed PDFs</strong><small>Use matching organization details on the faculty letter and company acceptance.</small></div></li>
                                <li><span>03</span><div><strong>Register your workdays</strong><small>Add the days and hours agreed with your company.</small></div></li>
                            </ol>
                            <a class="attachify-text-action" href="{{ route('placements.create') }}">Continue to placement setup <span aria-hidden="true">&#8594;</span></a>
                        @else
                            <p class="attachify-panel-copy">Placement setup will be available after an administrator verifies your student details.</p>
                        @endif
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

                    @if ($placement && $placement->document_status === 'approved' && auth()->user()->isApproved())
                        <form method="POST" action="{{ route('reports.store', $placement) }}" class="attachify-report-form">
                            @csrf
                            <label for="report_date">Report date</label>
                            <input id="report_date" name="report_date" type="date" value="{{ old('report_date', now()->toDateString()) }}" min="{{ $placement->start_date->toDateString() }}" max="{{ $placement->end_date->toDateString() }}" required>
                            @error('report_date') <p class="attachify-field-error">{{ $message }}</p> @enderror
                            <label for="activities">What did you work on?</label>
                            <textarea id="activities" name="activities" rows="4" placeholder="Describe the tasks, tools or skills you worked with today..." required>{{ old('activities') }}</textarea>
                            @error('activities') <p class="attachify-field-error">{{ $message }}</p> @enderror
                            <label for="challenges">Challenges faced</label>
                            <textarea id="challenges" name="challenges" rows="3" required>{{ old('challenges') }}</textarea>
                            @error('challenges') <p class="attachify-field-error">{{ $message }}</p> @enderror
                            <label for="skills_acquired">Skills acquired</label>
                            <textarea id="skills_acquired" name="skills_acquired" rows="3" required>{{ old('skills_acquired') }}</textarea>
                            @error('skills_acquired') <p class="attachify-field-error">{{ $message }}</p> @enderror
                            <label for="lessons_learned">Lessons learned</label>
                            <textarea id="lessons_learned" name="lessons_learned" rows="3" required>{{ old('lessons_learned') }}</textarea>
                            @error('lessons_learned') <p class="attachify-field-error">{{ $message }}</p> @enderror
                            <button class="attachify-primary-action" type="submit">Submit daily report <span aria-hidden="true">&#8594;</span></button>
                    @elseif ($placement)
                        <div class="attachify-empty-state"><span class="attachify-empty-symbol" aria-hidden="true">i</span><p>Daily reporting opens after an administrator approves your student account and both placement PDFs.</p></div>
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
                                    <div>
                                        <p><strong>Activity:</strong> {{ $report->activities }}</p>
                                        @if ($report->challenges)<p><strong>Challenges:</strong> {{ $report->challenges }}</p>@endif
                                        @if ($report->skills_acquired)<p><strong>Skills acquired:</strong> {{ $report->skills_acquired }}</p>@endif
                                        @if ($report->lessons_learned)<p><strong>Lessons learned:</strong> {{ $report->lessons_learned }}</p>@endif
                                        @foreach ($report->comments as $comment)
                                            <div class="mt-2 border-l-2 border-green-700 pl-3"><small>Field supervisor · {{ $comment->created_at->format('M j, Y') }}</small><p>{{ $comment->body }}</p></div>
                                        @endforeach
                                    </div>
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
                    @if ($placement?->meetings->isNotEmpty())
                        <div class="mt-4 space-y-3">
                            @foreach ($placement->meetings as $meeting)
                                <article class="rounded border border-white/20 p-3 text-sm">
                                    <strong>{{ $meeting->starts_at->format('D, M j, Y g:i A') }}</strong>
                                    <p>{{ $meeting->mode === 'teams' ? 'Microsoft Teams' : 'In person' }} · {{ $meeting->notes }}</p>
                                    @if ($meeting->teams_url)<a href="{{ $meeting->teams_url }}" target="_blank" rel="noopener noreferrer" class="underline">Join Teams meeting</a>@endif
                                    @if ($meeting->location)<p>{{ $meeting->location }}</p>@endif
                                </article>
                            @endforeach
                        </div>
                    @else
                        <small>Meeting scheduling will appear here when your academic supervisor arranges a review.</small>
                    @endif
                    @if ($placement?->finalAssessments->isNotEmpty())
                        <div class="mt-5 border-t border-white/20 pt-4">
                            <p class="font-semibold">Faculty assessment report</p>
                            <p class="mt-1 text-sm">Your academic supervisor shared the final report.</p>
                            <a href="{{ route('placements.faculty-report', $placement) }}" class="mt-2 inline-block font-semibold underline">Download faculty report (PDF)</a>
                        </div>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
