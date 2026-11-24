<?php

namespace Modules\Integration\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Integration\Models\IntegrationOtp;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $code,
        public string $purpose,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->purpose === IntegrationOtp::PURPOSE_PASSWORD_RESET
            ? 'رمز استعادة كلمة المرور'
            : 'رمز توثيق البريد الإلكتروني';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'integration::emails.otp');
    }
}
