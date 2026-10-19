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
                                </div>
                                <span>{{ $placement->dailyReports->count() }} recent reports</span>
                            </div>
                            @if ($placement->dailyReports->isNotEmpty())
                                <div class="attachify-supervision-reports">
                                    @foreach ($placement->dailyReports as $report)
                                        <article class="attachify-supervision-report">
                                            <div><time datetime="{{ $report->report_date->toDateString() }}">{{ $report->report_date->format('D, M j') }}</time><p>{{ $report->activities }}</p></div>
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
                                            @else
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
                                            @endif
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
    </div>
</x-app-layout>