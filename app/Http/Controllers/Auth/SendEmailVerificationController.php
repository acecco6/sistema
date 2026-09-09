<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SendEmailVerificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) return $this->successResponse(['email_verified' => true], 'El email ya está verificado.');
        $request->user()->sendEmailVerificationNotification();
        return $this->successResponse(null, 'Email de verificación reenviado.');
    }
}
