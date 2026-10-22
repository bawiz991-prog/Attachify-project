<x-app-layout>
    <x-slot name="header">
        <div class="attachify-workspace-heading">
            <div>
                <p class="attachify-eyebrow">ATTACHIFY / SYSTEM ADMINISTRATION</p>
                <h1>Review and oversee the placement system</h1>
            </div>
            <span class="attachify-role-label">Administrator</span>
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
            <p class="attachify-eyebrow">SYSTEM ANNOUNCEMENT</p>
            <h2 class="mt-2 text-lg font-semibold">Notify approved users</h2>
            <form method="POST" action="{{ route('admin.announcements.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                @csrf
                <label class="grid gap-1 text-sm font-semibold">Audience
                    <select name="audience" required class="rounded border-gray-300 text-sm">
                        <option value="all">All approved users</option><option value="students">Students</option><option value="supervisors">Supervisors</option>
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
            <p class="attachify-eyebrow">ACCOUNT VERIFICATION</p>
            <h2 class="mt-2 text-lg font-semibold">Registered students and supervisors</h2>
            <div class="mt-4 space-y-4">
                @forelse ($users as $user)
                    <article class="rounded border border-gray-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">{{ $user->name }}</h3>
                                <p class="text-sm text-gray-600">{{ $user->email }} · {{ str_replace('_', ' ', ucfirst($user->role)) }} · {{ ucfirst($user->verification_status) }}</p>
                                @if ($user->role === 'student')
                                    <p class="mt-1 text-sm text-gray-600">Course: {{ $user->course ?: 'Not provided' }} · Registration no.: {{ $user->registration_number ?: 'Not provided' }} · Student no.: {{ $user->student_number ?: 'Not provided' }}</p>
                                @endif
                                @if ($user->verification_notes)
                                    <p class="mt-1 text-sm text-gray-600">Previous review: {{ $user->verification_notes }}</p>
                                @endif
                            </div>
                            @if ($user->verification_status !== 'approved')
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
                                <p class="mt-1 text-sm text-gray-600">Student verification: {{ ucfirst($placement->student->verification_status) }} · Documents: {{ ucfirst($placement->document_status) }}</p>
                                <p class="mt-1 text-sm text-gray-600">Field supervisor: {{ $placement->fieldSupervisor?->name ?? 'Not connected' }} · Academic supervisor: {{ $placement->academicSupervisor?->name ?? 'Not connected' }}</p>
                                <div class="mt-2 flex flex-wrap gap-3 text-sm">
                                    <a class="font-semibold text-green-800 underline" href="{{ route('admin.placements.document', [$placement, 'placement']) }}">View faculty placement letter (PDF)</a>
                                    <a class="font-semibold text-green-800 underline" href="{{ route('admin.placements.document', [$placement, 'acceptance']) }}">View company acceptance letter (PDF)</a>
                                </div>
                                @if ($placement->document_review_notes)
                                    <p class="mt-2 text-sm text-gray-600">Previous document review: {{ $placement->document_review_notes }}</p>
                                @endif
                            </div>
                            @if ($placement->document_status !== 'approved')
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
