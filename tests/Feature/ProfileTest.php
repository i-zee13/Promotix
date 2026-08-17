<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_avatar_can_be_uploaded_and_served_without_public_storage_symlink(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureTwoFactorPassed::class,
            \App\Http\Middleware\EnsureOnboardingComplete::class,
            \App\Http\Middleware\EnsureProtectionAccess::class,
        ]);
        $user = User::factory()->create();

        $upload = $this
            ->actingAs($user)
            ->post(route('profile.avatar.update'), [
                'avatar' => UploadedFile::fake()->image('avatar.jpg', 100, 100),
            ], [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ]);

        $upload
            ->assertOk()
            ->assertJsonPath('has_custom_avatar', true);

        $path = (string) $user->refresh()->avatar_path;
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString(
            route('profile.avatar.show', ['user' => $user], false),
            (string) $upload->json('avatar_url')
        );

        $this
            ->get(route('profile.avatar.show', ['user' => $user]))
            ->assertOk();
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
