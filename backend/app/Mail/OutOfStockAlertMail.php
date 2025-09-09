<?php

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OutOfStockAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public $product;
    public $adminUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
        $this->adminUrl = config('app.admin_url', config('app.url') . '/admin') . '/products/' . $product->id;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🚨 URGENTE: Producto Agotado - {$this->product->name}",
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
            view: 'emails.out-of-stock-alert',
            with: [
                'product' => $this->product,
                'adminUrl' => $this->adminUrl,
                'appName' => config('app.name'),
                'alertTime' => now()->format('d/m/Y H:i')
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