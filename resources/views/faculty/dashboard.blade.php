<x-app-layout>
    <x-slot name="header">
        <div class="attachify-workspace-heading">
            <div>
                <p class="attachify-eyebrow">SIMS / FACULTY PLACEMENTS</p>
                <h1>{{ $faculty }}</h1>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <span class="attachify-role-label">{{ auth()->user()->role === 'faculty_dean' ? 'Dean' : 'Faculty handler' }}</span>
            </div>
        </div>
    </x-slot>

    <div class="attachify-workspace space-y-5">
        @if (session('status'))
            <div class="attachify-flash" role="status">{{ session('status') }}</div>
        @endif

        <section class="attachify-panel">
            <p class="attachify-eyebrow">STUDENT ACCOUNT REVIEW</p>
            <h2 class="mt-2 text-lg font-semibold">Students awaiting {{ $faculty }} verification</h2>
            <div class="mt-4 space-y-4">
                @forelse ($pendingStudents as $student)
                    <article class="rounded border border-gray-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">{{ $student->name }}</h3>
                                <p class="text-sm text-gray-600">{{ $student->email }} · {{ $student->course }}</p>
                                <p class="mt-1 text-sm text-gray-600">Registration no.: {{ $student->registration_number }} · Student no.: {{ $student->student_number }}</p>
                                @if ($student->verification_notes)
                                    <p class="mt-2 text-sm text-amber-900">Previous review: {{ $student->verification_notes }}</p>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('faculty.students.review', $student) }}" class="grid min-w-64 gap-2">
                                @csrf
                                <label class="grid gap-1 text-xs font-semibold">Review note
                                    <input name="notes" minlength="3" maxlength="2000" required placeholder="Explain the verification decision" class="rounded border-gray-300 text-sm">
                                </label>
                                @error('notes')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                                <div class="flex gap-2">
                                    <button name="decision" value="approved" class="rounded bg-green-800 px-3 py-2 text-xs font-semibold text-white">Approve details</button>
                                    <button name="decision" value="rejected" class="rounded border border-red-300 px-3 py-2 text-xs font-semibold text-red-800">Request changes</button>
                                </div>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-gray-600">No students are waiting for your faculty to verify their details.</p>
                @endforelse
            </div>
        </section>

        <section class="attachify-panel">
            <p class="attachify-eyebrow">STUDENT PLACEMENTS</p>
            <h2 class="mt-2 text-lg font-semibold">Submissions routed to your faculty</h2>
            <div class="mt-4 space-y-4">
                @forelse ($placements as $placement)
                    <article class="rounded border border-gray-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">{{ $placement->student->name }} · {{ $placement->company_name }}</h3>
                                <p class="text-sm text-gray-600">{{ $placement->student->email }} · {{ $placement->student->course }}</p>
                                <p class="mt-1 text-sm text-gray-600">{{ $placement->start_date?->format('M j, Y') }} – {{ $placement->end_date?->format('M j, Y') }} · {{ str_replace('_', ' ', ucfirst($placement->faculty_review_status)) }}</p>
                                <p class="mt-1 text-sm text-gray-600">{{ $placement->company_address ?: 'No workplace address provided' }}</p>
                                <p class="mt-1 text-sm text-gray-600">Handler: {{ $placement->facultyHandler?->name ?? 'Not assigned' }}</p>
                                @if ($placement->student_verification_response)
                                    <p class="mt-2 text-sm"><strong>Student response:</strong> {{ $placement->student_verification_response }}</p>
                                @endif
                                @if ($placement->document_review_notes)
                                    <p class="mt-2 text-sm"><strong>Dean review:</strong> {{ $placement->document_review_notes }}</p>
                                @endif
                                @if (! $placement->fieldSupervisor && $placement->field_supervisor_email)
                                    <p class="mt-2 text-sm">Field supervisor invitation address: {{ $placement->field_supervisor_email }}</p>
                                    <form method="POST" action="{{ route('faculty.placements.field-supervisor-invitation', $placement) }}" class="mt-2">
                                        @csrf
                                        <button class="rounded bg-green-800 px-3 py-2 text-xs font-semibold text-white">Send or resend invitation</button>
                                    </form>
                                @endif
                                <div class="mt-2 flex flex-wrap gap-3 text-sm">
                                    @if ($placement->acceptance_document_path)
                                        <a class="font-semibold text-green-800 underline" href="{{ route('faculty.documents', [$placement, 'acceptance']) }}">Company acceptance letter</a>
                                    @endif
                                    @if ($placement->placement_document_path)
                                        <a class="font-semibold text-green-800 underline" href="{{ route('faculty.documents', [$placement, 'placement']) }}">Faculty application letter</a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if (auth()->user()->role === 'faculty_handler' && $placement->faculty_review_status === 'correction_requested')
                            <details class="mt-4">
                                <summary class="cursor-pointer text-sm font-semibold text-green-900">Edit details requested by student</summary>
                                <form method="POST" action="{{ route('faculty.placements.update', $placement) }}" class="mt-3 grid gap-3 rounded border border-gray-200 p-3 md:grid-cols-2">
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
                                    <button class="attachify-primary-action justify-self-start md:col-span-2">Save corrections</button>
                                </form>
                            </details>
                        @endif

                        @if (auth()->user()->role === 'faculty_handler' && in_array($placement->faculty_review_status, ['submitted', 'correction_requested'], true))
                            <form method="POST" action="{{ route('faculty.placements.verification-request', $placement) }}" class="mt-4 grid gap-2 rounded border border-gray-200 p-3">
                                @csrf
                                <label class="grid gap-1 text-xs font-semibold">Ask the student to verify these details
                                    <textarea name="request_message" rows="3" required minlength="3" maxlength="2000" class="rounded border-gray-300 text-sm">{{ old('request_message') }}</textarea>
                                </label>
                                @error('request_message')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                                <button class="attachify-primary-action justify-self-start">Request student verification</button>
                            </form>
                        @elseif (auth()->user()->role === 'faculty_dean' && $placement->faculty_review_status === 'student_confirmed')
                            <form method="POST" action="{{ route('faculty.placements.dean-review', $placement) }}" class="mt-4 grid gap-2 rounded border border-gray-200 p-3">
                                @csrf
                                <label class="grid gap-1 text-xs font-semibold">Final review note
                                    <textarea name="notes" rows="3" required minlength="3" maxlength="2000" class="rounded border-gray-300 text-sm">{{ old('notes') }}</textarea>
                                </label>
                                @error('notes')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                                <div class="flex flex-wrap gap-2">
                                    <button name="decision" value="approved" class="rounded bg-green-800 px-3 py-2 text-sm font-semibold text-white">Approve internship</button>
                                    <button name="decision" value="rejected" class="rounded border border-red-300 px-3 py-2 text-sm font-semibold text-red-800">Reject internship</button>
                                </div>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-gray-600">No student internship submissions have reached this faculty yet.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $placements->links() }}</div>
        </section>
    </div>
</x-app-layout>