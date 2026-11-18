<x-app-layout>
    <x-slot name="header">
        <div class="attachify-workspace-heading">
            <div>
                <p class="attachify-eyebrow">SIMS / SYSTEM ADMINISTRATION</p>
                <h1>REVIEW AND OVERSEE THE SIMS SYSTEM</h1>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <span class="attachify-role-label">Administrator</span>
            </div>
        </div>
    </x-slot>

    <div class="attachify-workspace space-y-6">
        @if (session('status'))
            <div class="attachify-flash" role="status">{{ session('status') }}</div>
        @endif

        <section class="attachify-metrics" aria-label="Pending reviews">
            <article class="attachify-metric"><span>ACCOUNTS NEEDING REVIEW</span><strong>{{ $pendingUsers }}</strong><small>Student and supervisor verification</small></article>
            <article class="attachify-metric"><span>PLACEMENTS NEEDING REVIEW</span><strong>{{ $pendingPlacements }}</strong><small>Internship and acceptance PDF letters</small></article>
            <article class="attachify-metric"><span>SUPPORT REQUESTS</span><strong>{{ $openSupportRequests }}</strong><small>Open messages from users</small></article>
        </section>

        <section class="attachify-panel">
            <p class="attachify-eyebrow">PROGRAM DIRECTORY</p>
            <h2 class="mt-2 text-lg font-semibold">Programs, faculties and coordinators</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach ($programs as $program)
                    <article class="rounded border border-gray-200 p-4">
                        <h3 class="font-semibold">{{ $program->name }}</h3>
                        <p class="text-sm text-gray-600">Coordinator: {{ $program->coordinator?->name ?? 'Not assigned' }}</p>
                        <form method="POST" action="{{ route('admin.programs.faculty', $program) }}" class="mt-3 flex flex-wrap gap-2">
                            @csrf
                            @method('PUT')
                            <label class="sr-only" for="faculty-{{ $program->id }}">Faculty for {{ $program->name }}</label>
                            <select id="faculty-{{ $program->id }}" name="faculty" class="min-w-48 flex-1 rounded border-gray-300 text-sm">
                                <option value="">Unassigned</option>
                                @foreach ($faculties as $faculty)
                                    <option value="{{ $faculty }}" @selected($program->faculty === $faculty)>{{ $faculty }}</option>
                                @endforeach
                            </select>
                            <button class="rounded bg-green-800 px-3 py-2 text-xs font-semibold text-white">Save faculty</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="attachify-panel">
            <p class="attachify-eyebrow">FACULTY PLACEMENT OVERVIEW</p>
            <h2 class="mt-2 text-lg font-semibold">Browse internship submissions by faculty</h2>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.dashboard') }}" class="rounded border px-3 py-2 text-sm font-semibold {{ $selectedFaculty ? 'border-gray-300 text-gray-700' : 'border-green-800 bg-green-800 text-white' }}">All faculties</a>
                @foreach ($faculties as $faculty)
                    <a href="{{ route('admin.dashboard', ['faculty' => $faculty]) }}" class="rounded border px-3 py-2 text-sm font-semibold {{ $selectedFaculty === $faculty ? 'border-green-800 bg-green-800 text-white' : 'border-gray-300 text-gray-700' }}">
                        {{ $faculty }} <span class="ml-1">{{ $facultyCounts[$faculty] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="attachify-panel">
            <p class="attachify-eyebrow">SYSTEM ANNOUNCEMENT</p>
            <h2 class="mt-2 text-lg font-semibold">Notify approved users</h2>
            <form method="POST" action="{{ route('admin.announcements.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                @csrf
                <label class="grid gap-1 text-sm font-semibold">Audience
                    <select name="audience" required class="rounded border-gray-300 text-sm">
                        <option value="all">All approved users</option><option value="students">Students</option><option value="supervisors">Supervisors</option><option value="faculty">Faculty staff (handlers and deans)</option>
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-semibold">Title
                    <input name="title" maxlength="120" required class="rounded border-gray-300 text-sm">
                </label>
                <label class="grid gap-1 text-sm font-semibold md:col-span-2">Message
                    <textarea name="message" rows="3" maxlength="3000" required class="rounded border-gray-300 text-sm"></textarea>
                </label>
                <button class="attachify-primary-action justify-self-start">Send announcement</button>
            </form>
        </section>

        <section class="attachify-panel">
            <p class="attachify-eyebrow">ACCOUNT MANAGEMENT</p>
            <h2 class="mt-2 text-lg font-semibold">Student accounts, supervisors and faculty staff</h2>
            <div class="mt-4 space-y-4">
                @forelse ($users as $user)
                    <article class="rounded border border-gray-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">{{ $user->name }}</h3>
                                <p class="text-sm text-gray-600">{{ $user->email }} · {{ str_replace('_', ' ', ucfirst($user->role)) }} · {{ ucfirst($user->verification_status) }} · {{ $user->hasVerifiedEmail() ? 'Email active' : 'Email not activated' }} · {{ $user->is_active ? 'Account active' : 'Account deactivated' }}</p>
                                @if ($user->role === 'student')
                                    <p class="mt-1 text-sm text-gray-600">Course: {{ $user->course ?: 'Not provided' }} · Faculty: {{ $user->faculty ?: 'Not provided' }} · Registration no.: {{ $user->registration_number ?: 'Not provided' }} · Student no.: {{ $user->student_number ?: 'Not provided' }}</p>
                                @endif
                                @if ($user->verification_notes)
                                    <p class="mt-1 text-sm text-gray-600">Previous review: {{ $user->verification_notes }}</p>
                                @endif
                            </div>
                            @if ($user->role === 'student' && $user->verification_status !== 'approved')
                                <p class="text-sm text-gray-600">Student details are reviewed by the selected faculty.</p>
                            @elseif ($user->verification_status !== 'approved')
                                <form method="POST" action="{{ route('admin.users.review', $user) }}" class="grid min-w-64 gap-2">
                                    @csrf
                                    <label class="grid gap-1 text-xs font-semibold">Review note
                                        <input name="notes" minlength="3" maxlength="2000" required placeholder="Explain the verification decision" class="rounded border-gray-300 text-sm">
                                    </label>
                                    <div class="flex gap-2">
                                        <button name="decision" value="approved" class="rounded bg-green-800 px-3 py-2 text-xs font-semibold text-white">Approve</button>
                                        <button name="decision" value="rejected" class="rounded border border-red-300 px-3 py-2 text-xs font-semibold text-red-800">Request changes</button>
                                    </div>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.users.status', $user) }}" onsubmit="return confirm('{{ $user->is_active ? 'Deactivate' : 'Reactivate' }} this account? {{ $user->is_active ? 'The user will lose access, but account records and history will be kept.' : '' }}')" class="self-start">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $user->is_active ? '0' : '1' }}">
                                <button class="rounded border {{ $user->is_active ? 'border-red-300 text-red-800' : 'border-green-300 text-green-800' }} px-3 py-2 text-xs font-semibold">
                                    {{ $user->is_active ? 'Deactivate account' : 'Reactivate account' }}
                                </button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-gray-600">No accounts are registered yet.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $users->links() }}</div>
        </section>

        <section class="attachify-panel">
            <p class="attachify-eyebrow">PLACEMENT OVERSIGHT</p>
            <h2 class="mt-2 text-lg font-semibold">Student placements, reports and assessments</h2>
            <div class="mt-4 space-y-5">
                @forelse ($placements as $placement)
                    <article class="rounded border border-gray-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">{{ $placement->student->name }} · {{ $placement->company_name }}</h3>
                                <p class="text-sm text-gray-600">{{ $placement->student->email }} · {{ $placement->start_date?->format('M j, Y') }} – {{ $placement->end_date?->format('M j, Y') }}</p>
                                <p class="mt-1 text-sm text-gray-600">Faculty: {{ $placement->faculty_name ?? $placement->student->program?->faculty ?? 'Unassigned' }} · Review status: {{ str_replace('_', ' ', ucfirst($placement->faculty_review_status ?? $placement->document_status)) }}</p>
                                <p class="mt-1 text-sm text-gray-600">Student verification: {{ ucfirst($placement->student->verification_status) }} · Student confirmed: {{ $placement->student_confirmed_at?->format('M j, Y g:i A') ?? 'Not yet' }}</p>
                                @if ($placement->faculty_verification_request)<p class="mt-1 text-sm text-gray-600"><strong>Faculty request:</strong> {{ $placement->faculty_verification_request }}</p>@endif
                                @if ($placement->student_verification_response)<p class="mt-1 text-sm text-gray-600"><strong>Student response:</strong> {{ $placement->student_verification_response }}</p>@endif
                                @if ($placement->facultyHandler)<p class="mt-1 text-sm text-gray-600">Handler: {{ $placement->facultyHandler->name }}</p>@endif
                                <p class="mt-1 text-sm text-gray-600">Field supervisor: {{ $placement->fieldSupervisor?->name ?? 'Not connected' }} · Academic supervisor: {{ $placement->academicSupervisor?->name ?? 'Not connected' }}</p>
                                <div class="mt-2 flex flex-wrap gap-3 text-sm">
                                    @if ($placement->placement_document_path)<a class="font-semibold text-green-800 underline" href="{{ route('admin.placements.document', [$placement, 'placement']) }}">View faculty placement letter (PDF)</a>@endif
                                    @if ($placement->acceptance_document_path)<a class="font-semibold text-green-800 underline" href="{{ route('admin.placements.document', [$placement, 'acceptance']) }}">View company acceptance letter (PDF)</a>@endif
                                </div>
                                @if ($placement->document_review_notes)
                                    <p class="mt-2 text-sm text-gray-600">Previous document review: {{ $placement->document_review_notes }}</p>
                                @endif
                            </div>
                            @if ($placement->faculty_review_status)
                                <p class="max-w-64 text-sm text-gray-700">The faculty dean owns the final review. Admin oversight is view and edit only.</p>
                            @elseif ($placement->document_status !== 'approved' && $placement->student_confirmed_at)
                                <form method="POST" action="{{ route('admin.placements.review', $placement) }}" class="grid min-w-64 gap-2">
                                    @csrf
                                    <label class="grid gap-1 text-xs font-semibold">Review note
                                        <input name="notes" minlength="3" maxlength="2000" required placeholder="Confirm both PDFs match the organization" class="rounded border-gray-300 text-sm">
                                    </label>
                                    <label class="flex items-start gap-2 text-xs text-gray-700">
                                        <input name="documents_match" type="checkbox" value="1" class="mt-0.5 rounded border-gray-300">
                                        I checked both PDFs and confirmed the organization details match.
                                    </label>
                                    <div class="flex gap-2">
                                        <button name="decision" value="approved" class="rounded bg-green-800 px-3 py-2 text-xs font-semibold text-white">Approve documents</button>
                                        <button name="decision" value="rejected" class="rounded border border-red-300 px-3 py-2 text-xs font-semibold text-red-800">Request changes</button>
                                    </div>
                                </form>
                            @elseif (! $placement->faculty_review_status && $placement->document_status !== 'approved')
                                <p class="max-w-64 text-sm text-amber-800">Final approval is locked until the student confirms this internship place.</p>
                            @endif
                            @if ($placement->faculty_review_status)
                                <details class="mt-4 w-full">
                                    <summary class="cursor-pointer text-sm font-semibold text-green-900">Edit placement details</summary>
                                    <form method="POST" action="{{ route('admin.placements.update', $placement) }}" class="mt-3 grid gap-3 rounded border border-gray-200 p-3 md:grid-cols-2">
                                        @csrf
                                        @method('PUT')
                                        <label class="grid gap-1 text-xs font-semibold">Company
                                            <input name="company_name" value="{{ $placement->company_name }}" required maxlength="255" class="rounded border-gray-300 text-sm">
                                        </label>
                                        <label class="grid gap-1 text-xs font-semibold">Address
                                            <input name="company_address" value="{{ $placement->company_address }}" maxlength="2000" class="rounded border-gray-300 text-sm">
                                        </label>
                                        <label class="grid gap-1 text-xs font-semibold">Start date
                                            <input name="start_date" type="date" value="{{ $placement->start_date?->toDateString() }}" required class="rounded border-gray-300 text-sm">
                                        </label>
                                        <label class="grid gap-1 text-xs font-semibold">End date
                                            <input name="end_date" type="date" value="{{ $placement->end_date?->toDateString() }}" required class="rounded border-gray-300 text-sm">
                                        </label>
                                        <fieldset class="md:col-span-2">
                                            <legend class="text-xs font-semibold">Workdays</legend>
                                            <div class="mt-2 flex flex-wrap gap-3">
                                                @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $day => $label)
                                                    <label class="flex items-center gap-1 text-sm"><input type="checkbox" name="workdays[]" value="{{ $day }}" @checked($placement->schedules->contains('day_of_week', $day)) class="rounded border-gray-300"><span>{{ $label }}</span></label>
                                                @endforeach
                                            </div>
                                        </fieldset>
                                        <label class="grid gap-1 text-xs font-semibold">Work starts
                                            <input name="start_time" type="time" value="{{ $placement->schedules->first()?->start_time ?? '08:00' }}" required class="rounded border-gray-300 text-sm">
                                        </label>
                                        <label class="grid gap-1 text-xs font-semibold">Work ends
                                            <input name="end_time" type="time" value="{{ $placement->schedules->first()?->end_time ?? '17:00' }}" required class="rounded border-gray-300 text-sm">
                                        </label>
                                        <button class="attachify-primary-action justify-self-start md:col-span-2">Save changes</button>
                                    </form>
                                </details>
                            @endif
                            @if ($placement->student->course)
                                <form method="POST" action="{{ route('admin.placements.academic-supervisor', $placement) }}" class="grid min-w-64 gap-2">
                                    @csrf
                                    <label class="grid gap-1 text-xs font-semibold">Academic supervisor · {{ $placement->student->course }}
                                        <select name="academic_supervisor_id" required class="rounded border-gray-300 text-sm">
                                            <option value="">Select an activated supervisor</option>
                                            @foreach ($academicSupervisors->where('course', $placement->student->course) as $supervisor)
                                                <option value="{{ $supervisor->id }}" @selected($placement->academic_supervisor_id === $supervisor->id)>{{ $supervisor->name }} ({{ $supervisor->academicSupervisions()->whereHas('student', fn ($query) => $query->where('course', $placement->student->course))->whereDate('end_date', '>=', today())->count() }}/10)</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    @error('academic_supervisor_id')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                                    <button class="rounded bg-green-800 px-3 py-2 text-xs font-semibold text-white">Assign academic supervisor</button>
                                </form>
                            @endif
                        </div>
                        <details class="mt-4">
                            <summary class="cursor-pointer text-sm font-semibold text-green-900">Review {{ $placement->dailyReports->count() }} daily reports and supervisor feedback</summary>
                            <div class="mt-3 space-y-3">
                                @foreach ($placement->dailyReports as $report)
                                    <div class="rounded bg-gray-50 p-3 text-sm">
                                        <p class="font-semibold">{{ $report->report_date->format('M j, Y') }} · {{ $report->status }}</p>
                                        <p class="mt-1"><strong>Activity:</strong> {{ $report->activities }}</p>
                                        @if ($report->challenges)<p class="mt-1"><strong>Challenges:</strong> {{ $report->challenges }}</p>@endif
                                        @if ($report->skills_acquired)<p class="mt-1"><strong>Skills:</strong> {{ $report->skills_acquired }}</p>@endif
                                        @if ($report->lessons_learned)<p class="mt-1"><strong>Lessons:</strong> {{ $report->lessons_learned }}</p>@endif
                                        @foreach ($report->comments as $comment)
                                            <p class="mt-2"><strong>{{ $comment->author?->name ?? 'Supervisor' }} comment:</strong> {{ $comment->body }}</p>
                                        @endforeach
                                        @foreach ($report->assessments as $assessment)
                                            <p class="mt-2"><strong>{{ $assessment->supervisor->name }} assessment:</strong> {{ $assessment->rating }}/5 — {{ $assessment->overall_feedback }}</p>
                                        @endforeach
                                    </div>
                                @endforeach
                                @foreach ($placement->finalAssessments as $assessment)
                                    <p class="text-sm"><strong>{{ $assessment->supervisor->name }} final assessment:</strong> {{ $assessment->overall_score }}/100 — {{ $assessment->feedback }}</p>
                                @endforeach
                                @foreach ($placement->studentFinalReports as $finalReport)
                                    <div class="mt-2 rounded border border-gray-200 p-3 text-sm">
                                        <p><strong>Student final report:</strong> {{ str_replace('_', ' ', ucfirst($finalReport->status)) }} · {{ $finalReport->submitted_at->format('M j, Y') }}</p>
                                        @if ($finalReport->reviewer_notes)<p class="mt-1">Examiner feedback: {{ $finalReport->reviewer_notes }}</p>@endif
                                        <a class="mt-1 inline-block font-semibold text-green-800 underline" href="{{ route('final-reports.download', $finalReport) }}">Download final report</a>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    </article>
                @empty
                    <p class="text-sm text-gray-600">No placements are registered yet.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $placements->links() }}</div>
        </section>

        <section class="attachify-panel">
            <p class="attachify-eyebrow">HELP DESK</p>
            <h2 class="mt-2 text-lg font-semibold">Contact requests</h2>
            <div class="mt-4 space-y-4">
                @forelse ($supportRequests as $supportRequest)
                    <article class="rounded border border-gray-200 p-4">
                        <p class="font-semibold">{{ $supportRequest->subject }} · {{ ucfirst($supportRequest->status) }}</p>
                        <p class="text-sm text-gray-600">{{ $supportRequest->name }} ({{ $supportRequest->email }})</p>
                        <p class="mt-2 whitespace-pre-line text-sm">{{ $supportRequest->message }}</p>
                        <form method="POST" action="{{ route('admin.support.reply', $supportRequest) }}" class="mt-3 grid gap-2">
                            @csrf
                            <textarea name="admin_reply" rows="2" required maxlength="3000" placeholder="Reply to the user" class="rounded border-gray-300 text-sm">{{ $supportRequest->admin_reply }}</textarea>
                            <div class="flex items-center gap-3">
                                <select name="status" class="rounded border-gray-300 text-sm"><option value="open" @selected($supportRequest->status === 'open')>Open</option><option value="resolved" @selected($supportRequest->status === 'resolved')>Resolved</option></select>
                                <button class="rounded bg-green-800 px-3 py-2 text-xs font-semibold text-white">Save reply</button>
                            </div>
                        </form>
                    </article>
                @empty
                    <p class="text-sm text-gray-600">There are no support requests yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
