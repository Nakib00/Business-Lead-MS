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
        //  Find user by ID from the Route parameter
        $user = User::find($request->route('id'));

        if (!$user) {
            return $this->errorResponse('Invalid user.', 404);
        }

        //  Check the signature (hash) matches
        if (!hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return $this->errorResponse('Invalid or expired URL provided.', 401);
        }

        //  Check signature validity (expiration)
        if (!$request->hasValidSignature()) {
            return $this->errorResponse('Invalid or expired URL provided.', 401);
        }

        //  Check if already verified
        if ($user->hasVerifiedEmail()) {
            return $this->errorResponse('Email already verified.', 400);
        }

        // Mark as verified
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->successResponse(null, 'Email verified successfully. You can now login.', 200);
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
