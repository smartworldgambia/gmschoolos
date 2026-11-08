<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The message behind the platform console's "Send test email" button. Carries no data. */
class IntegrationTestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'SchoolOS test email');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>This is a test message from the SchoolOS platform console.</p>'
            .'<p>If you can read it, outgoing e-mail is working with the settings you just saved. No action is needed.</p>');
    }
}
