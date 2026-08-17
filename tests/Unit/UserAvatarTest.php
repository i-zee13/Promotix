<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    public function test_prefers_uploaded_avatar_over_google(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/1/photo.jpg', 'image');

        $user = new User([
            'avatar_path' => 'avatars/1/photo.jpg',
            'google_avatar_url' => 'https://lh3.googleusercontent.com/a/example',
        ]);
        $user->id = 1;

        $this->assertStringContainsString('/profile/avatar/1', (string) $user->avatarUrl());
    }

    public function test_falls_back_to_google_avatar(): void
    {
        $user = new User([
            'avatar_path' => null,
            'google_avatar_url' => 'https://lh3.googleusercontent.com/a/example',
        ]);

        $this->assertSame('https://lh3.googleusercontent.com/a/example', $user->avatarUrl());
    }

    public function test_returns_null_when_no_avatar(): void
    {
        $user = new User([
            'avatar_path' => null,
            'google_avatar_url' => null,
            'name' => 'Ali',
        ]);

        $this->assertNull($user->avatarUrl());
        $this->assertSame('A', $user->avatarInitial());
    }
}
