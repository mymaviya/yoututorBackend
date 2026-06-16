<?php

namespace App\Mail;

use App\Models\DemoEnquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DemoEnquiryConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public DemoEnquiry $demoEnquiry;

    public function __construct(DemoEnquiry $demoEnquiry)
    {
        $this->demoEnquiry = $demoEnquiry;
    }

    public function build()
    {
        return $this
            ->subject('Your YouTutor Demo Enquiry Has Been Received')
            ->view('emails.demo-enquiry-confirmation');
    }
}