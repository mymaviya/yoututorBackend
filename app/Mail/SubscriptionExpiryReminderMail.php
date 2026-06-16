<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiryReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public Subscription $subscription;
    public int $daysRemaining;

    public function __construct(
        Subscription $subscription,
        int $daysRemaining
    ) {
        $this->subscription = $subscription;
        $this->daysRemaining = $daysRemaining;
    }

    public function build()
    {
        $subject = match (true) {
            $this->daysRemaining <= 0 =>
                'Your YouTutor Subscription Has Expired',

            $this->daysRemaining === 1 =>
                'Your YouTutor Subscription Expires Tomorrow',

            default =>
                "Your YouTutor Subscription Expires in {$this->daysRemaining} Days",
        };

        return $this
            ->subject($subject)
            ->view('emails.subscription-expiry-reminder');
    }
}