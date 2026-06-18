<?php

namespace App\Mail;

use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class QuotationSentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quotation $quotation,
        public string $pdfPath
    ) {}

    public function build()
    {
        return $this
            ->subject('Quotation - ' . $this->quotation->project_name)
            ->view('emails.quotations.sent')
            ->attach($this->pdfPath, [
                'as' => $this->quotation->quotation_no . '.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}