<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Bienvenido a ' . config('app.name') . '!',
            from: config('mail.from.address'),
            replyTo: config('mail.from.address')
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: [
                'user' => $this->user,
                'userName' => $this->user->first_name ?? $this->user->name ?? 'Cliente',
                'appName' => config('app.name'),
                'shopUrl' => config('app.frontend_url', config('app.url')),
                'loginUrl' => config('app.frontend_url', config('app.url')) . '/login',
                'profileUrl' => config('app.frontend_url', config('app.url')) . '/profile',
                'supportEmail' => config('mail.support.address', config('mail.from.address')),
                'benefits' => [
                    'Acceso exclusivo a ofertas especiales',
                    'Envío gratuito en pedidos superiores a $50.000',
                    'Programa de puntos y recompensas',
                    'Soporte prioritario al cliente',
                    'Notificaciones de nuevos productos'
                ]
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}