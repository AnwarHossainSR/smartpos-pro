<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\ResetPassword;
use App\Notifications\SetPasswordNotification;
use App\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class PasswordResetAndVerificationTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_forgot_password_responds_the_same_for_known_and_unknown_emails(): void
    {
        Notification::fake();
        $owner = $this->createTenant();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $owner->email])
            ->assertOk();
        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $owner->email])->json('message');

        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])
            ->assertOk()
            ->json('message');

        $this->assertSame($known, $unknown);
        Notification::assertSentTo($owner, ResetPassword::class);
    }

    public function test_full_password_reset_round_trip_revokes_old_sessions(): void
    {
        $owner = $this->createTenant();
        $oldToken = $owner->createToken('old-device')->plainTextToken;
        $token = Password::createToken($owner);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $owner->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        auth()->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', [
            'email' => $owner->email, 'password' => 'newpassword123',
        ])->assertOk();
    }

    public function test_reset_password_with_an_invalid_token_is_rejected(): void
    {
        $owner = $this->createTenant();

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $owner->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_verifying_email_with_a_valid_signed_link_marks_it_verified(): void
    {
        $owner = $this->createTenant();
        $owner->forceFill(['email_verified_at' => null])->save();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $owner->id,
            'hash' => sha1($owner->email),
        ]);

        $this->get($url)->assertRedirect();

        $this->assertNotNull($owner->fresh()->email_verified_at);
    }

    public function test_verifying_email_with_a_tampered_link_is_rejected(): void
    {
        $owner = $this->createTenant();
        $owner->forceFill(['email_verified_at' => null])->save();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $owner->id,
            'hash' => sha1('someone-else@example.test'),
        ]);

        $this->get($url)->assertForbidden();
        $this->assertNull($owner->fresh()->email_verified_at);
    }

    public function test_resend_verification_sends_a_new_notification_unless_already_verified(): void
    {
        Notification::fake();
        $owner = $this->createTenant();
        $owner->forceFill(['email_verified_at' => null])->save();

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/auth/email/resend')->assertOk();
        Notification::assertSentTo($owner, VerifyEmail::class);

        $owner->forceFill(['email_verified_at' => now()])->save();
        Notification::fake();
        $this->postJson('/api/v1/auth/email/resend')->assertOk();
        Notification::assertNothingSent();
    }

    public function test_invited_user_receives_a_set_password_link_not_a_dead_password(): void
    {
        Notification::fake();
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/users', [
            'name' => 'New Hire', 'email' => 'newhire@example.test', 'roles' => ['cashier'],
        ])->assertCreated()->assertJsonPath('data.status', 'invited');

        Notification::assertSentTo(
            User::where('email', 'newhire@example.test')->first(),
            SetPasswordNotification::class,
        );
    }

    public function test_unverified_owner_is_blocked_from_billing_sensitive_subscription_endpoints(): void
    {
        $owner = $this->createTenant();
        $owner->forceFill(['email_verified_at' => null])->save();

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/subscription/current')->assertForbidden();

        $owner->forceFill(['email_verified_at' => now()])->save();
        Sanctum::actingAs($owner->fresh());
        $this->getJson('/api/v1/subscription/current')->assertOk();
    }
}
