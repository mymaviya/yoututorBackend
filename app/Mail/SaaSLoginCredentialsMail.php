<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SaaSLoginCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public Subscription $subscription;
    public string $plainPassword;

    public function __construct(User $user, Subscription $subscription, string $plainPassword)
    {
        $this->user = $user;
        $this->subscription = $subscription;
        $this->plainPassword = $plainPassword;
    }

    public function build()
    {
        return $this
            ->subject('Your YouTutor ERP Login Credentials')
            ->view('emails.saas-login-credentials');
    }
}