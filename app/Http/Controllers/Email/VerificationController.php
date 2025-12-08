<?php

namespace App\Http\Controllers\Email;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use App\Models\User;
use App\Traits\ApiResponseTrait;

class VerificationController extends Controller
{
    use ApiResponseTrait;

    public function verify(Request $request)
    {
        $user = User::find($request->route('id'));

        // 1. Validations (Invalid User / Bad Signature)
        if (!$user || !hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            // Optional: Redirect to an error page on your frontend instead of JSON
            return redirect('https://hub.desklago.com/auth/login?error=invalid_link');
        }

        if (!$request->hasValidSignature()) {
            return redirect('https://hub.desklago.com/auth/login?error=expired_link');
        }

        // 2. Already Verified? Redirect immediately
        if ($user->hasVerifiedEmail()) {
            return redirect('https://hub.desklago.com/auth/login?status=already_verified');
        }

        // 3. Mark Verified
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        // 4. SUCCESS: Redirect to your frontend Login page
        // I added a query param ?verified=1 so your frontend can show a popup "Success!"
        return redirect('https://hub.desklago.com/auth/login?verified=1');
    }

    public function resendVerificationEmail(Request $request)
    {
        try {
            $request->validate(['email' => 'required|email']);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return $this->errorResponse('User not found.', 404);
            }

            if ($user->hasVerifiedEmail()) {
                return $this->errorResponse('Email already verified.', 400);
            }

            // This triggers the default Laravel email notification
            $user->sendEmailVerificationNotification();

            return $this->successResponse(null, 'Verification link sent!', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send email: ' . $e->getMessage(), 500);
        }
    }
}
