<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpMail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const RECOVERY_EMAIL = 'recovery@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['password-reset.superadmin_email' => self::RECOVERY_EMAIL]);
    }

    public function test_existing_username_sends_six_character_otp_to_configured_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['username' => 'reset-user']);

        $this->postJson('/api/auth/password/forgot', [
            'username' => ' RESET-USER ',
        ])->assertOk()->assertExactJson([
            'message' => 'Jika username valid, kode pemulihan telah dikirim.',
        ]);

        Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) use ($user): bool {
            return $mail->hasTo(self::RECOVERY_EMAIL)
                && $mail->username === $user->username
                && preg_match('/^[A-HJ-NP-Z2-9]{6}$/', $mail->otp) === 1
                && $mail->validMinutes === 10;
        });
    }

    public function test_unknown_username_returns_the_same_response_without_sending_email(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/password/forgot', [
            'username' => 'missing-user',
        ])->assertOk()->assertExactJson([
            'message' => 'Jika username valid, kode pemulihan telah dikirim.',
        ]);

        Mail::assertNothingSent();

        $response = $this->postJson('/api/auth/password/reset', [
            'username' => 'missing-user',
            'otp' => 'AAAAAA',
            'password' => 'NewStrongPass1234',
            'password_confirmation' => 'NewStrongPass1234',
        ])->assertUnprocessable()->assertJsonValidationErrors(['otp']);

        $this->assertSame('Kode OTP salah. Tersisa 2 percobaan.', $response->json('errors.otp.0'));
    }

    public function test_valid_otp_resets_password_case_insensitively_and_can_only_be_used_once(): void
    {
        Mail::fake();
        $role = Role::create([
            'name' => 'Superadmin',
            'slug' => 'superadmin',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'username' => 'reset-success',
            'password' => 'OldStrongPass1234',
        ]);
        $user->forceFill(['role_id' => $role->id])->save();
        $user->createToken('existing-token');

        $this->postJson('/api/auth/password/forgot', [
            'username' => $user->username,
        ])->assertOk();

        /** @var PasswordResetOtpMail $mail */
        $mail = Mail::sent(PasswordResetOtpMail::class)->first();

        $this->postJson('/api/auth/password/reset', [
            'username' => $user->username,
            'otp' => strtolower($mail->otp),
            'password' => 'NewStrongPass1234',
            'password_confirmation' => 'NewStrongPass1234',
        ])->assertOk()->assertExactJson([
            'message' => 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru.',
        ]);

        $this->assertTrue(Hash::check('NewStrongPass1234', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'AUTH_PASSWORD_RESET',
            'subject_type' => 'User',
            'subject_id' => $user->id,
        ]);

        $this->postJson('/api/auth/password/reset', [
            'username' => $user->username,
            'otp' => $mail->otp,
            'password' => 'AnotherStrongPass1234',
            'password_confirmation' => 'AnotherStrongPass1234',
        ])->assertUnprocessable()->assertJsonPath(
            'errors.otp.0',
            'Kode OTP tidak valid atau sudah kedaluwarsa. Silakan minta kode baru.',
        );
    }

    public function test_three_wrong_attempts_invalidate_otp_and_require_a_new_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['username' => 'attempt-user']);

        $this->postJson('/api/auth/password/forgot', [
            'username' => $user->username,
        ])->assertOk();

        /** @var PasswordResetOtpMail $mail */
        $mail = Mail::sent(PasswordResetOtpMail::class)->first();
        $wrongOtp = $mail->otp === 'AAAAAA' ? 'BBBBBB' : 'AAAAAA';
        $payload = [
            'username' => $user->username,
            'otp' => $wrongOtp,
            'password' => 'NewStrongPass1234',
            'password_confirmation' => 'NewStrongPass1234',
        ];

        $this->postJson('/api/auth/password/reset', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.otp.0', 'Kode OTP salah. Tersisa 2 percobaan.');
        $this->postJson('/api/auth/password/reset', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.otp.0', 'Kode OTP salah. Tersisa 1 percobaan.');
        $this->postJson('/api/auth/password/reset', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.otp.0', 'Kode OTP salah. Batas tiga percobaan telah habis. Silakan minta kode baru.');

        $payload['otp'] = $mail->otp;
        $this->postJson('/api/auth/password/reset', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.otp.0', 'Kode OTP tidak valid atau sudah kedaluwarsa. Silakan minta kode baru.');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_otp_expires_after_ten_minutes(): void
    {
        Mail::fake();
        $user = User::factory()->create(['username' => 'expired-user']);

        $this->postJson('/api/auth/password/forgot', [
            'username' => $user->username,
        ])->assertOk();

        /** @var PasswordResetOtpMail $mail */
        $mail = Mail::sent(PasswordResetOtpMail::class)->first();
        $this->travel(11)->minutes();

        $this->postJson('/api/auth/password/reset', [
            'username' => $user->username,
            'otp' => $mail->otp,
            'password' => 'NewStrongPass1234',
            'password_confirmation' => 'NewStrongPass1234',
        ])->assertUnprocessable()->assertJsonPath(
            'errors.otp.0',
            'Kode OTP tidak valid atau sudah kedaluwarsa. Silakan minta kode baru.',
        );
    }

    public function test_otp_request_requires_sixty_seconds_before_resending(): void
    {
        Mail::fake();
        $user = User::factory()->create(['username' => 'resend-user']);
        $payload = ['username' => $user->username];

        $this->postJson('/api/auth/password/forgot', $payload)->assertOk();
        $this->postJson('/api/auth/password/forgot', $payload)->assertTooManyRequests();

        $this->travel(61)->seconds();
        $this->postJson('/api/auth/password/forgot', $payload)->assertOk();
        Mail::assertSentCount(2);
    }

    public function test_reset_validates_otp_and_password_shape(): void
    {
        $this->postJson('/api/auth/password/reset', [
            'username' => 'invalid username',
            'otp' => '123',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'username',
            'otp',
            'password',
        ]);
    }
}
