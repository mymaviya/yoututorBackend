<?php

namespace App\Mail;

use App\Models\Proposal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProposalSentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Proposal $proposal,
        public string $pdfPath
    ) {}

    public function build()
    {
        return $this
            ->subject('Project Proposal - ' . $this->proposal->project_name)
            ->view('emails.proposals.sent')
            ->attach($this->pdfPath, [
                'as' => $this->proposal->proposal_no . '.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}