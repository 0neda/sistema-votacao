<?php

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('an administrator can publish optional candidate photos', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'administrador']);
    $payload = electionInput();
    $payload['candidates'][0]['photo'] = UploadedFile::fake()->image('ana.jpg', 100, 100);
    $this->actingAs($admin)->post(route('elections.store'), $payload)->assertSessionHasNoErrors();
    $candidate = Candidate::orderBy('id')->firstOrFail();
    expect($candidate->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($candidate->photo_path);
});

test('unsafe photos are rejected atomically', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'administrador']);
    $payload = electionInput();
    $payload['candidates'][0]['photo'] = UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml');
    $this->actingAs($admin)->post(route('elections.store'), $payload)->assertSessionHasErrors(['candidates.0.photo']);
    $this->assertDatabaseCount('elections', 0);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('photos staged for a rejected second election are removed without affecting existing photos', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'administrador']);
    $payload = electionInput();
    $payload['candidates'][0]['photo'] = UploadedFile::fake()->image('first.jpg');
    $this->actingAs($admin)->post(route('elections.store'), $payload)->assertSessionHasNoErrors();
    $existing = Candidate::orderBy('id')->firstOrFail()->photo_path;
    $payload['candidates'][0]['photo'] = UploadedFile::fake()->image('second.jpg');
    $this->post(route('elections.store'), $payload)->assertSessionHasErrors('election');
    Storage::disk('public')->assertExists($existing);
    expect(Storage::disk('public')->allFiles())->toHaveCount(1);
});
