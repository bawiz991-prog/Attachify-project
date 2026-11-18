<x-app-layout>
    <x-slot name="header">
        <div class="attachify-workspace-heading">
            <div>
                <p class="attachify-eyebrow">SIMS / COURSE COORDINATION</p>
                <h1>{{ auth()->user()->course }}</h1>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <span class="attachify-role-label">Coordinator</span>
            </div>
        </div>
    </x-slot>

    <div class="attachify-workspace space-y-6">
        @if (session('status'))
            <div class="attachify-flash" role="status">{{ session('status') }}</div>
        @endif
        @if ($notifications->isNotEmpty())
            <section class="attachify-panel" aria-label="Recent notifications">
                <h2 class="font-semibold">New updates</h2>
                @foreach ($notifications as $notification)
                    <p class="mt-2 text-sm"><strong>{{ $notification->data['title'] ?? 'SIMS update' }}:</strong> {{ $notification->data['message'] ?? '' }}</p>
                @endforeach
            </section>
        @endif

        <section class="attachify-panel">
            <p class="attachify-eyebrow">STUDENT ASSESSMENTS</p>
            <h2 class="mt-2 text-lg font-semibold">Placements in your program</h2>
            <div class="mt-4 space-y-4">
                @forelse ($placements as $placement)
                    <article class="rounded border border-gray-200 p-4">
                        <h3 class="font-semibold">{{ $placement->student->name }} · {{ $placement->company_name }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ $placement->student->registration_number }} · {{ $placement->student->student_number }}</p>
                        <p class="mt-1 text-sm text-gray-600">Field supervisor: {{ $placement->fieldSupervisor?->name ?? 'Not assigned' }} · Academic supervisor: {{ $placement->academicSupervisor?->name ?? 'Not assigned' }}</p>

                        <details class="mt-4">
                            <summary class="cursor-pointer text-sm font-semibold text-green-900">Recent daily activity marks</summary>
                            <div class="mt-3 space-y-2">
                                @forelse ($placement->dailyReports as $report)
                                    <div class="rounded bg-gray-50 p-3 text-sm">
                                        <p class="font-semibold">{{ $report->report_date->format('M j, Y') }} · {{ str_replace('_', ' ', ucfirst($report->status)) }}</p>
                                        @foreach ($report->assessments as $dailyAssessment)
                                            <p class="mt-1">{{ $dailyAssessment->supervisor->name }}: {{ $dailyAssessment->rating }}/5 — {{ $dailyAssessment->overall_feedback }}</p>
                                        @endforeach
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-600">No daily reports have been submitted.</p>
                                @endforelse
                            </div>
                        </details>

                        <div class="mt-4 space-y-3">
                            @forelse ($placement->finalAssessments as $assessment)
                                <div class="rounded bg-gray-50 p-3">
                                    <p class="font-semibold">{{ str_replace('_', ' ', ucfirst($assessment->supervisor_role)) }} final assessment</p>
                                    <p class="mt-1 text-sm">Overall: {{ $assessment->overall_score }}/100 · {{ $assessment->feedback }}</p>
                                    @if ($assessment->faculty_report_path)
                                        <a class="mt-2 inline-block text-sm font-semibold text-green-800 underline" href="{{ route('placements.faculty-report', $placement) }}">View academic report</a>
                                    @endif
                                    @if ($assessment->supervisor_role === 'academic_supervisor')
                                        <p class="mt-2 text-xs text-gray-600">{{ $assessment->coordinator_forwarded_at ? 'Forwarded to faculty '.$assessment->coordinator_forwarded_at->format('M j, Y') : 'Awaiting faculty forwarding' }}</p>
                                        @unless ($assessment->coordinator_forwarded_at)
                                            <form method="POST" action="{{ route('coordinator.assessments.forward', $assessment) }}" class="mt-2">
                                                @csrf
                                                <button class="rounded bg-green-800 px-3 py-2 text-xs font-semibold text-white">Forward assessment to faculty</button>
                                            </form>
                                        @endunless
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-gray-600">Final supervisor assessments have not been submitted yet.</p>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-gray-600">No students are assigned to your program yet.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $placements->links() }}</div>
        </section>
    </div>
</x-app-layout>
