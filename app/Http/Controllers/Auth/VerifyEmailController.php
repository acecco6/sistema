<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Application\Customers\CustomerAccountLinker;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash, CustomerAccountLinker $customerAccountLinker): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403, 'Enlace de verificación inválido.');
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) event(new Verified($user));
        $customerAccountLinker->linkVerifiedUser($user->id);
        return $this->successResponse(['email_verified' => true], 'Email verificado correctamente.');
    }
}
