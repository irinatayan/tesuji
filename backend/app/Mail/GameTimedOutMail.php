<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Game;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class GameTimedOutMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Game $game,
        public readonly User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Game timed out — Tesuji');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.game-timed-out');
    }

    public function attachments(): array
    {
        return [];
    }
}
