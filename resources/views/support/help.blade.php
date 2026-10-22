<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo"><x-authentication-card-logo /></x-slot>
        <div class="space-y-5 text-sm text-gray-700">
            <div>
                <p class="attachify-eyebrow">ATTACHIFY / HELP CENTRE</p>
                <h1 class="mt-2 text-2xl font-bold text-gray-900">How can we help?</h1>
                <p class="mt-2">Use this guide for common placement and supervision tasks.</p>
            </div>
            <section><h2 class="font-semibold text-gray-900">Student account and verification</h2><p class="mt-1">Register your course, registration number, and student number accurately. An administrator reviews these details before placement tools are enabled.</p></section>
            <section><h2 class="font-semibold text-gray-900">Placement documents</h2><p class="mt-1">Upload the signed faculty placement letter and company acceptance letter as PDFs. Enter the organization name as shown on both documents. The administrator checks that the organization details match before approving daily logs.</p></section>
            <section><h2 class="font-semibold text-gray-900">Daily logs and supervisor feedback</h2><p class="mt-1">Submit a log for each scheduled workday, including your activities, challenges, skills acquired, and lessons learned. Your assigned field supervisor can leave comments on the log; only assigned supervisors can see assessment marks.</p></section>
            <section><h2 class="font-semibold text-gray-900">Meetings, reports, and messages</h2><p class="mt-1">Academic supervisors can schedule Teams or in-person reviews with the student and field supervisor. Supervisors can message each other from the assigned placement workspace. Final faculty reports are shared securely with the student.</p></section>
            <a class="inline-flex rounded bg-green-900 px-4 py-2 font-semibold text-white" href="{{ route('contact') }}">Contact support</a>
        </div>
    </x-authentication-card>
</x-guest-layout>
