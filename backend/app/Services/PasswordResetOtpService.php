<?php

namespace App\Services;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PasswordResetOtpService
{
    private const OTP_CHARACTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private ActivityLogger $activityLogger) {}

    public function requestOtp(string $username): void
    {
        $recipient = config('password-reset.superadmin_email');

        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Alamat email pemulihan kata sandi belum dikonfigurasi.');
        }

        $user = User::query()
            ->where('username', $username)
            ->where('is_active', true)
            ->first();
        $otp = $this->generateOtp();
        $expiresAt = now()->addMinutes($this->ttlMinutes());

        Cache::put($this->challengeKey($username), [
            'otp_hash' => Hash::make($otp),
            'user_id' => $user?->id,
            'username' => $username,
            'attempts' => 0,
            'expires_at' => $expiresAt->getTimestamp(),
        ], $expiresAt);

        if (! $user instanceof User) {
            return;
        }

        try {
            Mail::to($recipient)->send(new PasswordResetOtpMail(
                $username,
                $otp,
                $this->ttlMinutes(),
            ));
        } catch (Throwable $exception) {
            Cache::forget($this->challengeKey($username));

            throw $exception;
        }
    }

    public function resetPassword(string $username, string $otp, string $password): void
    {
        Cache::lock($this->lockKey($username), 5)->block(3, function () use ($username, $otp, $password): void {
            $challenge = Cache::get($this->challengeKey($username));

            if (! is_array($challenge) || ! $this->isValidChallenge($challenge, $username)) {
                Cache::forget($this->challengeKey($username));
                $this->throwInvalidOtp();
            }

            if (! Hash::check($otp, $challenge['otp_hash'])) {
                $this->recordFailedAttempt($username, $challenge);
            }

            $userId = $challenge['user_id'];
            $user = is_int($userId)
                ? User::query()->where('is_active', true)->find($userId)
                : null;

            if (! $user instanceof User || ! hash_equals($user->username, $username)) {
                Cache::forget($this->challengeKey($username));
                $this->throwInvalidOtp();
            }

            DB::transaction(function () use ($user, $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                $user->tokens()->delete();
                $this->activityLogger->log($user, 'AUTH_PASSWORD_RESET', $user);
            });
            Cache::forget($this->challengeKey($username));
        });
    }

    /** @param array<string, mixed> $challenge */
    private function isValidChallenge(array $challenge, string $username): bool
    {
        return is_string($challenge['otp_hash'] ?? null)
            && (is_int($challenge['user_id'] ?? null) || ($challenge['user_id'] ?? null) === null)
            && is_string($challenge['username'] ?? null)
            && hash_equals($challenge['username'], $username)
            && is_int($challenge['attempts'] ?? null)
            && $challenge['attempts'] < $this->maxAttempts()
            && is_int($challenge['expires_at'] ?? null)
            && $challenge['expires_at'] > now()->getTimestamp();
    }

    /** @param array{otp_hash: string, user_id: int|null, username: string, attempts: int, expires_at: int} $challenge */
    private function recordFailedAttempt(string $username, array $challenge): never
    {
        $challenge['attempts']++;
        $remainingAttempts = $this->maxAttempts() - $challenge['attempts'];

        if ($remainingAttempts <= 0) {
            Cache::forget($this->challengeKey($username));

            throw ValidationException::withMessages([
                'otp' => 'Kode OTP salah. Batas tiga percobaan telah habis. Silakan minta kode baru.',
            ]);
        }

        Cache::put(
            $this->challengeKey($username),
            $challenge,
            $challenge['expires_at'] - now()->getTimestamp(),
        );

        throw ValidationException::withMessages([
            'otp' => "Kode OTP salah. Tersisa {$remainingAttempts} percobaan.",
        ]);
    }

    private function throwInvalidOtp(): never
    {
        throw ValidationException::withMessages([
            'otp' => 'Kode OTP tidak valid atau sudah kedaluwarsa. Silakan minta kode baru.',
        ]);
    }

    private function generateOtp(): string
    {
        $otp = '';
        $lastIndex = strlen(self::OTP_CHARACTERS) - 1;

        for ($index = 0; $index < 6; $index++) {
            $otp .= self::OTP_CHARACTERS[random_int(0, $lastIndex)];
        }

        return $otp;
    }

    private function challengeKey(string $username): string
    {
        return 'password-reset:challenge:'.hash('sha256', $username);
    }

    private function lockKey(string $username): string
    {
        return 'password-reset:lock:'.hash('sha256', $username);
    }

    private function ttlMinutes(): int
    {
        return (int) config('password-reset.otp_ttl_minutes', 10);
    }

    private function maxAttempts(): int
    {
        return (int) config('password-reset.max_attempts', 3);
    }
}
