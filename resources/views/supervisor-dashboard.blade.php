<x-app-layout>
    <x-slot name="header">
        <div class="attachify-workspace-heading">
            <div>
                <p class="attachify-eyebrow">ATTACHIFY / SUPERVISOR WORKSPACE</p>
                <h1>{{ auth()->user()->role === 'field_supervisor' ? 'Field supervisor' : 'Academic supervisor' }}</h1>
            </div>
            <span class="attachify-role-label">Supervisor</span>
        </div>
    </x-slot>

    <div class="attachify-workspace">
        @if (! auth()->user()->isApproved())
            <div class="attachify-flash" role="status">Your supervisor account is {{ auth()->user()->verification_status }}. An administrator must approve it before student placements are shown.</div>
        @endif
        @if ($notifications->isNotEmpty())
            <section class="attachify-panel mb-5">
                <div class="flex items-center justify-between gap-3"><h2 class="font-semibold">New updates</h2><a class="text-sm font-semibold text-green-800 underline" href="{{ route('notifications.index') }}">All notifications</a></div>
                @foreach ($notifications as $notification)<p class="mt-2 text-sm"><strong>{{ $notification->data['title'] ?? 'ATTACHIFY update' }}:</strong> {{ $notification->data['message'] ?? '' }}</p>@endforeach
            </section>
        @endif
        <section class="attachify-panel">
            <p class="attachify-eyebrow">ACCOUNT READY</p>
            <h2 class="mt-2 font-semibold text-xl text-gray-800">Welcome, {{ auth()->user()->name }}.</h2>
            @if (auth()->user()->role === 'field_supervisor')
                <p class="attachify-panel-copy">Your field supervisor account is ready. Share this registered work email with your student so they can connect you to their placement.</p>
                <div class="mt-5 border-t border-gray-200 pt-4">
                    <p class="attachify-eyebrow">YOUR REGISTERED WORK EMAIL</p>
                    <p class="mt-1 text-sm font-medium text-gray-800">{{ auth()->user()->email }}</p>
                </div>
            @else
                <p class="attachify-panel-copy">Your academic supervisor account is ready. Use your school email and confirm your assigned students against the faculty’s supervision list.</p>
                <div class="mt-5 border-t border-gray-200 pt-4">
                    <p class="attachify-eyebrow">YOUR REGISTERED SCHOOL EMAIL</p>
                    <p class="mt-1 text-sm font-medium text-gray-800">{{ auth()->user()->email }}</p>
                </div>
            @endif
        </section>

        <section class="mt-8">
            <p class="attachify-eyebrow">SUPERVISION</p>
            <h2 class="mt-2 font-semibold text-lg text-gray-800">Connected students</h2>
            @if ($placements->isNotEmpty())
                <div class="attachify-supervision-list">
                    @foreach ($placements as $placement)
                        <article class="attachify-supervision-row">
                            <div class="attachify-supervision-summary">
                                <div>
                                    <h3>{{ $placement->student->name }}</h3>
                                    <p>{{ $placement->company_name }} · {{ $placement->start_date->format('M j, Y') }} to {{ $placement->end_date->format('M j, Y') }}</p>
                                    <p>{{ $placement->student->course }} · Registration {{ $placement->student->registration_number }} · Student no. {{ $placement->student->student_number }}</p>
                                </div>
                                <span>{{ $placement->dailyReports->count() }} recent reports</span>
                            </div>
                            @if ($placement->dailyReports->isNotEmpty())
                                <div class="attachify-supervision-reports">
                                    @foreach ($placement->dailyReports as $report)
                                        <article class="attachify-supervision-report">
                                            <div>
                                                <time datetime="{{ $report->report_date->toDateString() }}">{{ $report->report_date->format('D, M j') }}</time>
                                                <p><strong>Activity:</strong> {{ $report->activities }}</p>
                                                @if ($report->challenges)<p><strong>Challenges:</strong> {{ $report->challenges }}</p>@endif
                                                @if ($report->skills_acquired)<p><strong>Skills acquired:</strong> {{ $report->skills_acquired }}</p>@endif
                                                @if ($report->lessons_learned)<p><strong>Lessons learned:</strong> {{ $report->lessons_learned }}</p>@endif
                                                @foreach ($report->comments as $comment)
                                                    <p class="mt-2 border-l-2 border-green-700 pl-3"><strong>Field supervisor feedback ({{ $comment->author?->name ?? 'Supervisor' }}):</strong> {{ $comment->body }}</p>
                                                @endforeach
                                                @if (auth()->user()->role === 'field_supervisor')
                                                    <form method="POST" action="{{ route('reports.comments.store', [$placement, $report]) }}" class="mt-3 grid gap-2">
                                                        @csrf
                                                        <label for="comment-{{ $report->id }}" class="text-xs font-semibold">Comment on this daily activity</label>
                                                        <textarea id="comment-{{ $report->id }}" name="body" rows="2" required minlength="3" maxlength="3000" class="w-full rounded border-gray-300 text-sm"></textarea>
                                                        <button class="attachify-primary-action justify-self-start" type="submit">Share feedback</button>
                                                    </form>
                                                @endif
                                            </div>
                                            @if ($report->assessments->isNotEmpty())
                                                @foreach ($report->assessments as $assessment)
                                                    <div class="attachify-assessment-entry">
                                                        <div><strong>{{ $assessment->rating }} / 5</strong><time datetime="{{ $assessment->submitted_at->toDateString() }}">{{ $assessment->submitted_at->format('M j, Y') }}</time></div>
                                                        <p>{{ $assessment->overall_feedback }}</p>
                                                        <small><strong>Strengths:</strong> {{ $assessment->strengths }}</small>
                                                        @if ($assessment->areas_for_improvement)
                                                            <small><strong>For improvement:</strong> {{ $assessment->areas_for_improvement }}</small>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            @endif
                                            @unless ($report->assessments->contains('supervisor_id', auth()->id()))
                                                <form method="POST" action="{{ route('reports.assessments.store', [$placement, $report]) }}" class="attachify-assessment-form">
                                                    @csrf
                                                    <h4>Assess this submitted report</h4>
                                                    <div class="attachify-assessment-fields">
                                                        <div>
                                                            <label for="rating-{{ $report->id }}">Overall progress</label>
                                                            <select id="rating-{{ $report->id }}" name="rating" required>
                                                                <option value="">Select a rating</option>
                                                                @foreach ([1 => '1 - Needs significant support', 2 => '2 - Developing', 3 => '3 - Meeting expectations', 4 => '4 - Strong progress', 5 => '5 - Excellent progress'] as $rating => $label)
                                                                    <option value="{{ $rating }}">{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                            @error('rating') <p class="attachify-field-error">{{ $message }}</p> @enderror
                                                        </div>
                                                        <div>
                                                            <label for="strengths-{{ $report->id }}">Strengths observed</label>
                                                            <textarea id="strengths-{{ $report->id }}" name="strengths" rows="3" required></textarea>
                                                            @error('strengths') <p class="attachify-field-error">{{ $message }}</p> @enderror
                                                        </div>
                                                        <div>
                                                            <label for="improvements-{{ $report->id }}">Areas for improvement <span>Optional</span></label>
                                                            <textarea id="improvements-{{ $report->id }}" name="areas_for_improvement" rows="3"></textarea>
                                                            @error('areas_for_improvement') <p class="attachify-field-error">{{ $message }}</p> @enderror
                                                        </div>
                                                        <div>
                                                            <label for="feedback-{{ $report->id }}">Overall feedback</label>
                                                            <textarea id="feedback-{{ $report->id }}" name="overall_feedback" rows="3" required></textarea>
                                                            @error('overall_feedback') <p class="attachify-field-error">{{ $message }}</p> @enderror
                                                        </div>
                                                        @error('assessment') <p class="attachify-field-error">{{ $message }}</p> @enderror
                                                        <button class="attachify-primary-action" type="submit">Submit assessment <span aria-hidden="true">&#8594;</span></button>
                                                    </div>
                                                </form>
                                            @endunless
                                        </article>
                                    @endforeach
                                </div>
                            @else
                                <p class="attachify-panel-copy">No daily reports have been submitted yet.</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @else
                <p class="attachify-panel-copy">No students are connected yet. Ask your student to add the email address registered to your account.</p>
            @endif
        </section>

        @foreach ($placements as $placement)
            <section class="attachify-panel mt-6">
                <p class="attachify-eyebrow">PLACEMENT COLLABORATION</p>
                <h2 class="mt-2 text-lg font-semibold">{{ $placement->student->name }} · {{ $placement->company_name }}</h2>

                <div class="mt-4 grid gap-6 lg:grid-cols-2">
                    <div>
                        <h3 class="font-semibold">Supervisor messages</h3>
                        @if ($placement->supervisorMessages->isNotEmpty())
                            <div class="mt-2 max-h-64 space-y-2 overflow-y-auto rounded bg-gray-50 p-3">
                                @foreach ($placement->supervisorMessages as $message)
                                    <p class="text-sm"><strong>{{ $message->sender->name }} · {{ $message->created_at->format('M j, g:i A') }}:</strong> {{ $message->body }}</p>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-2 text-sm text-gray-600">Start a conversation with the other assigned supervisor.</p>
                        @endif
                        <form method="POST" action="{{ route('placements.messages.store', $placement) }}" class="mt-3 grid gap-2">
                            @csrf
                            <label for="message-{{ $placement->id }}" class="text-xs font-semibold">Message the other supervisor</label>
                            <textarea id="message-{{ $placement->id }}" name="body" rows="3" required minlength="2" maxlength="3000" class="rounded border-gray-300 text-sm"></textarea>
                            <button class="attachify-primary-action justify-self-start">Send message</button>
                        </form>
                    </div>

                    <div>
                        <h3 class="font-semibold">Meetings with student and field supervisor</h3>
                        @forelse ($placement->meetings as $meeting)
                            <article class="mt-2 rounded bg-gray-50 p-3 text-sm">
                                <p><strong>{{ $meeting->starts_at->format('D, M j, Y g:i A') }}</strong> · {{ $meeting->mode === 'teams' ? 'Microsoft Teams' : 'In person' }}</p>
                                <p>{{ $meeting->notes }}</p>
                                @if ($meeting->teams_url)<a href="{{ $meeting->teams_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-green-800 underline">Open Teams meeting</a>@endif
                                @if ($meeting->location)<p>{{ $meeting->location }}</p>@endif
                            </article>
                        @empty
                            <p class="mt-2 text-sm text-gray-600">No meetings have been scheduled.</p>
                        @endforelse

                        @if (auth()->user()->role === 'academic_supervisor')
                            <form method="POST" action="{{ route('placements.meetings.store', $placement) }}" class="mt-3 grid gap-2">
                                @csrf
                                <label class="grid gap-1 text-xs font-semibold">Meeting type
                                    <select name="mode" required class="rounded border-gray-300 text-sm"><option value="teams">Microsoft Teams</option><option value="in_person">In person</option></select>
                                </label>
                                <label class="grid gap-1 text-xs font-semibold">Starts
                                    <input type="datetime-local" name="starts_at" required class="rounded border-gray-300 text-sm">
                                </label>
                                <label class="grid gap-1 text-xs font-semibold">Ends
                                    <input type="datetime-local" name="ends_at" required class="rounded border-gray-300 text-sm">
                                </label>
                                <label class="grid gap-1 text-xs font-semibold">Teams meeting URL (required for Teams)
                                    <input type="url" name="teams_url" class="rounded border-gray-300 text-sm">
                                </label>
                                <label class="grid gap-1 text-xs font-semibold">In-person location (required for in person)
                                    <input name="location" maxlength="1000" class="rounded border-gray-300 text-sm">
                                </label>
                                <label class="grid gap-1 text-xs font-semibold">Notes
                                    <textarea name="notes" rows="2" maxlength="2000" class="rounded border-gray-300 text-sm"></textarea>
                                </label>
                                <button class="attachify-primary-action justify-self-start">Schedule meeting</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="mt-6 border-t border-gray-200 pt-5">
                    <h3 class="font-semibold">Final placement assessment</h3>
                    @foreach ($placement->finalAssessments as $assessment)
                        <article class="mt-2 rounded bg-gray-50 p-3 text-sm">
                            <p><strong>{{ ucfirst(str_replace('_supervisor', ' supervisor', $assessment->supervisor_role)) }} · {{ $assessment->overall_score }}/100</strong></p>
                            @if ($assessment->physical_score !== null)<p>Physical assessment: {{ $assessment->physical_score }}/100 · Teams assessment: {{ $assessment->teams_score }}/100</p>@endif
                            <p>{{ $assessment->feedback }}</p>
                            @if ($assessment->faculty_report_path)<a href="{{ route('placements.faculty-report', $placement) }}" class="font-semibold text-green-800 underline">Download faculty report</a>@endif
                        </article>
                    @endforeach

                    @unless ($placement->finalAssessments->contains('supervisor_id', auth()->id()))
                        @if (auth()->user()->role === 'academic_supervisor' || ($placement->start_date && $placement->end_date && today()->between($placement->start_date, $placement->end_date)))
                            <form method="POST" action="{{ route('placements.final-assessments.store', $placement) }}" enctype="multipart/form-data" class="mt-3 grid max-w-xl gap-3">
                                @csrf
                                <label class="grid gap-1 text-xs font-semibold">Overall mark (0–100)
                                    <input type="number" name="overall_score" min="0" max="100" step="0.01" required class="rounded border-gray-300 text-sm">
                                </label>
                                @if (auth()->user()->role === 'academic_supervisor')
                                    <label class="grid gap-1 text-xs font-semibold">Physical assessment mark (0–100)
                                        <input type="number" name="physical_score" min="0" max="100" step="0.01" required class="rounded border-gray-300 text-sm">
                                    </label>
                                    <label class="grid gap-1 text-xs font-semibold">Teams assessment mark (0–100)
                                        <input type="number" name="teams_score" min="0" max="100" step="0.01" required class="rounded border-gray-300 text-sm">
                                    </label>
                                    <label class="grid gap-1 text-xs font-semibold">Faculty assessment report (PDF)
                                        <input type="file" name="faculty_report" accept="application/pdf,.pdf" required class="rounded border-gray-300 text-sm">
                                    </label>
                                    <label class="flex items-start gap-2 text-xs text-gray-700"><input type="checkbox" name="assessment_confirmed" value="1" required class="mt-0.5 rounded border-gray-300">I completed the physical and Teams assessments and confirm these marks.</label>
                                @endif
                                <label class="grid gap-1 text-xs font-semibold">Assessment feedback
                                    <textarea name="feedback" rows="3" required minlength="10" maxlength="5000" class="rounded border-gray-300 text-sm"></textarea>
                                </label>
                                <button class="attachify-primary-action justify-self-start">Submit final assessment</button>
                            </form>
                        @else
                            <p class="mt-2 text-sm text-gray-600">Field supervisor final marks can be submitted during the placement dates.</p>
                        @endif
                    @endunless
                </div>
            </section>
        @endforeach
    </div>
</x-app-layout>