<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Jetstream\Jetstream;

class ProfilePhotoController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless(Jetstream::managesProfilePhotos(), 404);

        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ]);

        $request->user()->updateProfilePhoto($validated['photo']);

        return back()->with('photo_status', 'Your profile photo has been updated.');
    }
}
