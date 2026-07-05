<?php

namespace App\Mail;

use App\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConfirmDemoRequest extends Mailable
{
    use Queueable, SerializesModels;

    public string $verifyUrl;

    public function __construct(public DemoRequest $demo)
    {
        $this->verifyUrl = rtrim(config('app.frontend_url'), '/') . '/verify-email/' . $demo->email_token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmez votre demande StockPilot',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.confirm-demo-request',
        );
    }
}
