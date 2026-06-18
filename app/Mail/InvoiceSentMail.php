<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class InvoiceSentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $pdfPath
    ) {}

    public function build()
    {
        return $this
            ->subject('Invoice - ' . $this->invoice->project_name)
            ->view('emails.invoices.sent')
            ->attach($this->pdfPath, [
                'as' => $this->invoice->invoice_no . '.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}