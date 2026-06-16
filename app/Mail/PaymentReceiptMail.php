<?php

namespace App\Mail;

use App\Models\PaymentTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public PaymentTransaction $transaction;

    public function __construct(PaymentTransaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function build()
    {
        return $this
            ->subject('Payment Receipt - YouTutor ERP')
            ->view('emails.payment-receipt');
    }
}