<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestPasswordResetOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function requestOtp(RequestPasswordResetOtpRequest $request, PasswordResetOtpService $service): JsonResponse
    {
        $service->requestOtp($request->validated('username'));

        return response()->json([
            'message' => 'Jika username valid, kode pemulihan telah dikirim.',
        ]);
    }

    public function reset(ResetPasswordRequest $request, PasswordResetOtpService $service): JsonResponse
    {
        $attributes = $request->validated();
        $service->resetPassword(
            $attributes['username'],
            $attributes['otp'],
            $attributes['password'],
        );

        return response()->json([
            'message' => 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru.',
        ]);
    }
}
