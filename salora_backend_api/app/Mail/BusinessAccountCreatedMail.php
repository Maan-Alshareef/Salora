<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessAccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $temporaryPassword,
        public readonly string $role,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'تم قبول طلبك وإنشاء حساب العمل في Salora');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.business-account-created',
            with: [
                'name' => $this->name,
                'email' => $this->email,
                'temporaryPassword' => $this->temporaryPassword,
                'role' => $this->role,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
