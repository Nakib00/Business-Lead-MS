<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;

class CustomVerifyEmail extends Notification
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // 1. Generate the verification URL manually to ensure it hits your API correctly
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->from('info@hub.desklago.com', 'Hub.desklago')
            ->subject('Welcome to Hub.desklago! Verify Your Email')
            ->view('emails.verify', [
                'verificationUrl' => $verificationUrl,
                'user' => $notifiable
            ]);
    }

    // Helper to generate the URL with ID and Hash
    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
