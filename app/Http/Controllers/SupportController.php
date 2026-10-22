<?php

namespace App\Http\Controllers;

use App\Models\SupportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function help(): View
    {
        return view('support.help');
    }

    public function contact(): View
    {
        return view('support.contact');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        SupportRequest::create([
            ...$validated,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('status', 'Your message has been sent to the Attachify support team.');
    }
}
