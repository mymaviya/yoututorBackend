<?php

namespace App\Mail;

use App\Models\SubscriptionRenewal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RenewalSuccessfulMail extends Mailable
{
    use Queueable, SerializesModels;

    public SubscriptionRenewal $renewal;

    public function __construct(
        SubscriptionRenewal $renewal
    ) {
        $this->renewal = $renewal;
    }

    public function build()
    {
        return $this
            ->subject('Subscription Renewal Successful')
            ->view('emails.renewal-successful');
    }
}