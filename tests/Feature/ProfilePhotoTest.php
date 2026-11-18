<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('authenticated users can upload and replace their profile photo', function () {
    Storage::fake('public');
    $user = User::factory()->create(['role' => 'student']);
    $makePhoto = fn (string $name) => UploadedFile::fake()->createWithContent(
        $name,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC')
    );

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Profile picture')
        ->assertDontSee(route('profile.photo.update'));

    $this->actingAs($user)->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Profile picture')
        ->assertSee(route('profile.photo.update'));

    $this->from(route('profile.show'))->post(route('profile.photo.update'), [
        'photo' => $makePhoto('profile.png'),
    ])->assertRedirect(route('profile.show'))
        ->assertSessionHas('photo_status');

    $firstPhoto = $user->fresh()->profile_photo_path;
    Storage::disk('public')->assertExists($firstPhoto);
    $this->get(route('profile.show'))
        ->assertOk()
        ->assertSee(Storage::disk('public')->url($firstPhoto));

    $this->from(route('profile.show'))->post(route('profile.photo.update'), [
        'photo' => $makePhoto('replacement.png'),
    ])->assertRedirect(route('profile.show'));

    $replacementPhoto = $user->fresh()->profile_photo_path;

    expect($replacementPhoto)->not->toBe($firstPhoto);
    Storage::disk('public')->assertMissing($firstPhoto);
    Storage::disk('public')->assertExists($replacementPhoto);
});
