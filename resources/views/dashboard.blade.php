<x-app-layout>
    <x-slot name="header">
        <div class="attachify-workspace-heading">
            <div>
                <p class="attachify-eyebrow">SIMS / STUDENT WORKSPACE</p>
                <h1>Your attachment, one day at a time.</h1>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <span class="attachify-role-label">Student</span>
            </div>
        </div>
    </x-slot>

    <div class="attachify-workspace">
        @if (session('status'))
            <div class="attachify-flash" role="status">{{ session('status') }}</div>
        @endif
        @if (! auth()->user()->isApproved())
            <div class="attachify-flash" role="status">
                Your student details are {{ auth()->user()->verification_status }}. Placement tools stay locked until your faculty verifies your course, faculty, registration number, and student number.
                @if (auth()->user()->verification_notes) <span class="block mt-1">Administrator note: {{ auth()->user()->verification_notes }}</span> @endif
                <a class="ml-2 font-semibold underline" href="{{ route('contact') }}">Contact support</a>
            </div>
        @endif
        <details class="attachify-panel mb-5" id="student-details" @if (! auth()->user()->course || ! auth()->user()->faculty) open @endif>
            <summary class="cursor-pointer font-semibold">Review or update your student identifiers</summary>
            <p class="mt-2 text-sm text-gray-600">Select the course and faculty you are enrolled in. Your faculty will verify your details before placement tools are enabled.</p>
            <form method="POST" action="{{ route('student.identity.update') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                @csrf
                @method('PUT')
                <label class="grid gap-1 text-xs font-semibold">Course / programme
                    <select name="course" required class="rounded border-gray-300 text-sm">
                        <option value="">Select your course</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->name }}" @selected(old('course', auth()->user()->course) === $program->name)>{{ $program->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-1 text-xs font-semibold">Faculty
                    <select name="faculty" required class="rounded border-gray-300 text-sm">
                        <option value="">Select your faculty</option>
                        @foreach ($faculties as $faculty)
                            <option value="{{ $faculty }}" @selected(old('faculty', auth()->user()->faculty ?: $program?->faculty) === $faculty)>{{ $faculty }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-1 text-xs font-semibold">Registration number
                    <input name="registration_number" value="{{ old('registration_number', auth()->user()->registration_number) }}" required maxlength="100" class="rounded border-gray-300 text-sm">
                </label>
                <label class="grid gap-1 text-xs font-semibold">Student number
                    <input name="student_number" value="{{ old('student_number', auth()->user()->student_number) }}" required maxlength="100" class="rounded border-gray-300 text-sm">
                </label>
                @foreach (['course', 'faculty', 'registration_number', 'student_number'] as $field)
                    @error($field)<p class="text-sm text-red-700 md:col-span-4">{{ $message }}</p>@enderror
                @endforeach
                <button class="attachify-primary-action justify-self-start md:col-span-4">Save student details</button>
            </form>
        </details>
        @if ($notifications->isNotEmpty())
            <section class="attachify-panel mb-5" aria-label="Recent notifications">
                <div class="flex items-center justify-between gap-3"><h2 class="font-semibold">New updates</h2><a class="text-sm font-semibold text-green-800 underline" href="{{ route('notifications.index') }}">All notifications</a></div>
                @foreach ($notifications as $notification)
                    <p class="mt-2 text-sm"><strong>{{ $notification->data['title'] ?? 'SIMS update' }}:</strong> {{ $notification->data['message'] ?? '' }}</p>
                @endforeach
            </section>
        @endif

        <section class="attachify-welcome-row">
            <div>
                <p class="attachify-eyebrow">YOUR FIELDWORK, IN ONE PLACE</p>
                <h2>Good {{ now()->hour < 12 ? 'morning' : 'day' }}, {{ Str::of(auth()->user()->name)->before(' ') }}.</h2>
                <p>Keep your placement details, work schedule and daily progress up to date.</p>
                <p class="mt-1 text-sm">Program: {{ $program?->name ?? 'Not assigned' }} · Faculty: {{ auth()->user()->faculty ?? 'Not selected' }}</p>
            </div>
            @if ($placement)
                @if ($placement->document_status === 'approved')
                    <a class="attachify-primary-action" href="#daily-report">Write today’s report <span aria-hidden="true">&#8594;</span></a>
                @else
                    <span class="attachify-role-label">{{ str_replace('_', ' ', ucfirst($placement->faculty_review_status ?? $placement->document_status)) }}</span>
                @endif
            @elseif (auth()->user()->isApproved())
                <a class="attachify-primary-action" href="#placement-registration">Register internship details <span aria-hidden="true">&#8594;</span></a>
            @else
                <a class="attachify-primary-action" href="#student-details">Review student details <span aria-hidden="true">&#8594;</span></a>
            @endif
        </section>

        <section class="attachify-metrics" aria-label="Placement summary">
            <article class="attachify-metric">
                <span>PLACEMENT</span>
                <strong>{{ $placement ? str_replace('_', ' ', ucfirst($placement->faculty_review_status ?? $placement->document_status)) : 'Not registered' }}</strong>
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
                        @if ($placement->faculty_review_status)
                            <p class="attachify-panel-copy">Your submission is with {{ $placement->faculty_name }}. Faculty handlers review it, ask you to verify the information, and the dean makes the final decision.</p>
                            <p class="mt-2 text-sm"><strong>Company acceptance letter:</strong> {{ $placement->acceptance_document_path ? 'Uploaded' : 'Missing' }} · <strong>Faculty application letter:</strong> {{ $placement->placement_document_path ? 'Uploaded' : 'Missing' }}</p>
                            @if ($placement->faculty_verification_request)
                                <div class="mt-3 rounded border border-amber-300 bg-amber-50 p-3">
                                    <p class="text-sm"><strong>Faculty verification request:</strong> {{ $placement->faculty_verification_request }}</p>
                                </div>
                            @endif
                            @if ($placement->faculty_review_status === 'verification_requested')
                                <form method="POST" action="{{ route('placements.verification-response', $placement) }}" class="mt-4 grid gap-3 rounded border border-gray-200 p-4">
                                    @csrf
                                    <label class="grid gap-1 text-xs font-semibold">Your response
                                        <textarea name="response" rows="3" required minlength="3" maxlength="2000" class="rounded border-gray-300 text-sm">{{ old('response') }}</textarea>
                                    </label>
                                    @error('response')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                                    <div class="flex flex-wrap gap-2">
                                        <button name="decision" value="confirmed" class="rounded bg-green-800 px-3 py-2 text-sm font-semibold text-white">Confirm these details</button>
                                        <button name="decision" value="correction_requested" class="rounded border border-amber-500 px-3 py-2 text-sm font-semibold text-amber-900">Request a correction</button>
                                    </div>
                                </form>
                            @elseif ($placement->faculty_review_status === 'correction_requested')
                                <p class="mt-3 text-sm text-amber-900">Your correction request was sent to the faculty. You will be asked to verify the updated details.</p>
                            @elseif ($placement->faculty_review_status === 'student_confirmed')
                                <p class="mt-3 text-sm text-green-800">You confirmed these details. The faculty dean is completing the final review.</p>
                            @elseif ($placement->faculty_review_status === 'approved')
                                <p class="mt-3 text-sm text-green-800">Your faculty dean approved this internship. Daily reporting is now available.</p>
                            @elseif ($placement->faculty_review_status === 'rejected')
                                <p class="mt-3 text-sm text-red-800">The faculty dean did not approve this internship. {{ $placement->document_review_notes }}</p>
                            @else
                                <p class="mt-3 text-sm text-gray-700">Your faculty has received your submission. You will be notified when they need you to verify it.</p>
                            @endif
                        @else
                            @if ($placement->document_status !== 'approved')
                                <p class="attachify-panel-copy">An administrator must review both PDFs and confirm the organization details match before daily reports are enabled. {{ $placement->document_review_notes }}</p>
                            @endif
                            @if (! $placement->acceptance_document_path || ! $placement->placement_document_path)
                            <form method="POST" action="{{ route('placements.documents.store', $placement) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 rounded border border-gray-200 p-4">
                                @csrf
                                <p class="text-sm font-semibold">Upload the signed placement documents for final review</p>
                                <label class="grid gap-1 text-xs font-semibold">Company acceptance letter (PDF)
                                    <input type="file" name="acceptance_document" accept="application/pdf,.pdf" required class="rounded border-gray-300 text-sm">
                                </label>
                                @error('acceptance_document')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                                <label class="grid gap-1 text-xs font-semibold">Faculty placement letter (PDF)
                                    <input type="file" name="placement_document" accept="application/pdf,.pdf" required class="rounded border-gray-300 text-sm">
                                </label>
                                @error('placement_document')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                                <button class="attachify-primary-action justify-self-start">Upload both documents</button>
                            </form>
                            @endif
                            @if (! $placement->student_confirmed_at && $placement->document_status !== 'approved')
                            <form method="POST" action="{{ route('placements.confirm', $placement) }}" class="mt-4 rounded border border-amber-300 bg-amber-50 p-4">
                                @csrf
                                <p class="text-sm text-gray-800">Confirm that <strong>{{ $placement->company_name }}</strong> is the internship place you accepted. An administrator will make the final approval after this confirmation.</p>
                                <button class="mt-3 rounded bg-green-800 px-4 py-2 text-sm font-semibold text-white">Confirm internship place</button>
                            </form>
                            @elseif ($placement->student_confirmed_at && $placement->document_status !== 'approved')
                            <p class="mt-4 text-sm text-green-800">Internship place confirmed. Waiting for final administrator approval.</p>
                            @endif
                        @endif
                        <div class="attachify-supervisor-strip">
                            <div><span class="attachify-avatar">F</span><p><small>FIELD SUPERVISOR</small><strong>{{ $placement->fieldSupervisor?->name ?? 'Not connected yet' }}</strong></p></div>
                            <div><span class="attachify-avatar attachify-avatar-academic">A</span><p><small>ACADEMIC SUPERVISOR</small><strong>{{ $placement->academicSupervisor?->name ?? 'Not connected yet' }}</strong></p></div>
                        </div>
                        @if ($placement->fieldSupervisor)
                            <p class="attachify-panel-copy">Connected account: {{ $placement->fieldSupervisor->email }}</p>
                        @elseif ($placement->field_supervisor_email)
                            <p class="attachify-panel-copy">Your faculty has received {{ $placement->field_supervisor_email }} and will send the field-supervisor invitation.</p>
                        @else
                            <form method="POST" action="{{ route('placements.field-supervisor', $placement) }}" class="attachify-report-form attachify-connect-form">
                                @csrf
                                <label for="field_supervisor_email">Field supervisor's email address</label>
                                <input id="field_supervisor_email" name="field_supervisor_email" type="email" value="{{ old('field_supervisor_email') }}" placeholder="supervisor@company.com" autocomplete="email" required>
                                @error('field_supervisor_email') <p class="attachify-field-error">{{ $message }}</p> @enderror
                                <small class="text-xs text-gray-500">Your faculty will email a one-time invitation to this address.</small>
                                <button class="attachify-primary-action" type="submit">Send email to faculty <span aria-hidden="true">&#8594;</span></button>
                            </form>
                        @endif
                        @if ($placement->academicSupervisor)
                            <p class="attachify-panel-copy">Academic supervisor: {{ $placement->academicSupervisor->email }}</p>
                        @else
                            <p class="attachify-panel-copy">Your faculty administrator will assign an academic supervisor from your program.</p>
                        @endif
                    @else
                        @if (auth()->user()->isApproved())
                            @if ($program && in_array(auth()->user()->faculty, config('faculties'), true))
                                <p class="attachify-panel-copy">Submit your internship details and supporting letters to {{ auth()->user()->faculty }}. Your faculty will review the information and ask you to verify it before the dean makes a final decision.</p>
                                <form id="placement-registration" method="POST" action="{{ route('placements.register') }}" enctype="multipart/form-data" class="mt-4 grid gap-3 rounded border border-gray-200 p-4 md:grid-cols-2">
                                    @csrf
                                    <label class="grid gap-1 text-xs font-semibold">Company / organization
                                        <input name="company_name" value="{{ old('company_name') }}" required maxlength="255" class="rounded border-gray-300 text-sm">
                                    </label>
                                    <label class="grid gap-1 text-xs font-semibold">Workplace address
                                        <input name="company_address" value="{{ old('company_address') }}" maxlength="2000" class="rounded border-gray-300 text-sm">
                                    </label>
                                    <label class="grid gap-1 text-xs font-semibold">Internship start date
                                        <input name="start_date" type="date" value="{{ old('start_date') }}" required class="rounded border-gray-300 text-sm">
                                    </label>
                                    <label class="grid gap-1 text-xs font-semibold">Internship end date
                                        <input name="end_date" type="date" value="{{ old('end_date') }}" required class="rounded border-gray-300 text-sm">
                                    </label>
                                    <fieldset class="md:col-span-2">
                                        <legend class="text-xs font-semibold">Agreed workdays</legend>
                                        <div class="mt-2 flex flex-wrap gap-3">
                                            @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $day => $label)
                                                <label class="flex items-center gap-1 text-sm"><input type="checkbox" name="workdays[]" value="{{ $day }}" @checked(in_array($day, old('workdays', [1, 2, 3, 4, 5]))) class="rounded border-gray-300"><span>{{ $label }}</span></label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    <label class="grid gap-1 text-xs font-semibold">Work starts
                                        <input name="start_time" type="time" value="{{ old('start_time', '08:00') }}" required class="rounded border-gray-300 text-sm">
                                    </label>
                                    <label class="grid gap-1 text-xs font-semibold">Work ends
                                        <input name="end_time" type="time" value="{{ old('end_time', '17:00') }}" required class="rounded border-gray-300 text-sm">
                                    </label>
                                    <label class="grid gap-1 text-xs font-semibold">Company acceptance / offer letter (PDF)
                                        <input type="file" name="acceptance_document" accept="application/pdf,.pdf" required class="rounded border-gray-300 text-sm">
                                    </label>
                                    <label class="grid gap-1 text-xs font-semibold">Faculty application letter (PDF)
                                        <input type="file" name="placement_document" accept="application/pdf,.pdf" required class="rounded border-gray-300 text-sm">
                                    </label>
                                    @foreach (['company_name', 'company_address', 'start_date', 'end_date', 'workdays', 'start_time', 'end_time', 'acceptance_document', 'placement_document'] as $field)
                                        @error($field)<p class="text-sm text-red-700 md:col-span-2">{{ $message }}</p>@enderror
                                    @endforeach
                                    <button class="attachify-primary-action justify-self-start md:col-span-2">Submit to faculty</button>
                                </form>
                            @else
                                <p class="attachify-panel-copy">Choose your course and faculty in your student details, then wait for faculty approval before registering your internship.</p>
                            @endif
                        @else
                            <p class="attachify-panel-copy">Placement setup will be available after your faculty verifies your student details.</p>
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
                        <div class="attachify-empty-state"><span class="attachify-empty-symbol" aria-hidden="true">i</span><p>{{ $placement->faculty_review_status ? 'Daily activities open after your faculty dean confirms your placement.' : 'Daily reporting opens after an administrator approves your student account and placement documents.' }}</p></div>
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
                    <div class="attachify-meeting-options"><span>Google Meet</span><span>In person</span></div>
                    @if ($placement?->meetings->isNotEmpty())
                        <div class="mt-4 space-y-3">
                            @foreach ($placement->meetings as $meeting)
                                <article class="rounded border border-white/20 p-3 text-sm">
                                    <strong>{{ $meeting->starts_at->format('D, M j, Y g:i A') }}</strong>
                                    <p>{{ $meeting->mode === 'google_meet' ? 'Google Meet' : 'In person' }} · {{ $meeting->notes }}</p>
                                    @if ($meeting->teams_url)<a href="{{ $meeting->teams_url }}" target="_blank" rel="noopener noreferrer" class="underline">Join Google Meet</a>@endif
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
                    @if ($placement)
                        <div class="mt-5 border-t border-white/20 pt-4">
                            <p class="font-semibold">Final report</p>
                            @if ($placement->final_report_template_path && today()->greaterThanOrEqualTo($placement->end_date->copy()->subDays(7)) && today()->lessThanOrEqualTo($placement->end_date))
                                <a href="{{ route('placements.final-report-template.download', $placement) }}" class="mt-2 inline-block font-semibold underline">Download final report template (PDF)</a>
                                @php($latestFinalReport = $placement->studentFinalReports->first())
                                @if ($latestFinalReport)
                                    <p class="mt-2 text-sm">Latest submission: {{ str_replace('_', ' ', ucfirst($latestFinalReport->status)) }}</p>
                                    @if ($latestFinalReport->reviewer_notes)<p class="mt-1 text-sm">Examiner feedback: {{ $latestFinalReport->reviewer_notes }}</p>@endif
                                    @if ($latestFinalReport->status === 'approved')
                                        <a href="{{ route('final-reports.download', $latestFinalReport) }}" class="mt-2 inline-block font-semibold underline">Download approved report</a>
                                    @endif
                                @endif
                                @if (! $latestFinalReport || $latestFinalReport->status === 'revision_requested')
                                    <form method="POST" action="{{ route('placements.final-report.store', $placement) }}" enctype="multipart/form-data" class="mt-3 grid gap-2">
                                        @csrf
                                        <label class="grid gap-1 text-xs font-semibold">Submit your final report as PDF
                                            <input type="file" name="final_report" accept="application/pdf,.pdf" required class="rounded border-white/30 text-sm">
                                        </label>
                                        <button class="rounded bg-white px-3 py-2 text-sm font-semibold text-green-900">Submit final report</button>
                                    </form>
                                @endif
                            @elseif ($placement->report_template_sent_at)
                                <p class="mt-2 text-sm">Your template will become available one week before the internship ends.</p>
                            @else
                                <p class="mt-2 text-sm">Your academic supervisor will send the template after the online assessment.</p>
                            @endif
                        </div>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
