<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $name;
    public $email;

    public function __construct($otp, $name, $email)
    {
        $this->otp = $otp;
        $this->name = $name;
        $this->email = $email;
    }

    public function build()
    {
        return $this->subject('Your Email Verification OTP - Hotel Management System')
            ->view('emails.otp')
            ->with([
                'otp' => $this->otp,
                'name' => $this->name,
                'email' => $this->email,
            ]);
    }
}
