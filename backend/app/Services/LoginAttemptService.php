<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class LoginAttemptService
{
    public function isLocked(string $username): bool
    {
        return (int) Cache::get($this->attemptKey($username), 0) >= $this->maxAttempts();
    }

    public function recordFailure(string $username): int
    {
        return Cache::lock($this->lockKey($username), 5)->block(3, function () use ($username): int {
            $attempts = min(
                $this->maxAttempts(),
                (int) Cache::get($this->attemptKey($username), 0) + 1,
            );

            Cache::forever($this->attemptKey($username), $attempts);

            return max(0, $this->maxAttempts() - $attempts);
        });
    }

    public function clear(string $username): void
    {
        Cache::forget($this->attemptKey($username));
    }

    private function attemptKey(string $username): string
    {
        return 'auth-login:failed-attempts:'.hash('sha256', $username);
    }

    private function lockKey(string $username): string
    {
        return 'auth-login:attempt-lock:'.hash('sha256', $username);
    }

    private function maxAttempts(): int
    {
        return (int) config('password-reset.login_max_attempts', 3);
    }
}
