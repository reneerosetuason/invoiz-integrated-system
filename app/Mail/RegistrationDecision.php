<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegistrationDecision extends Mailable
{
    use Queueable, SerializesModels;

    public $approved;
    public $reason;

    public function __construct(bool $approved, string $reason = '')
    {
        $this->approved = $approved;
        $this->reason = $reason;
    }

    public function build()
    {
        return $this->subject($this->approved ? 'Registration Approved' : 'Registration Declined')
                    ->view('emails.registration-decision')
                    ->with(['approved' => $this->approved, 'reason' => $this->reason]);
    }
}
