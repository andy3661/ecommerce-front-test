<?php

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public $product;
    public $currentStock;
    public $threshold;
    public $adminUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Product $product, int $currentStock, int $threshold)
    {
        $this->product = $product;
        $this->currentStock = $currentStock;
        $this->threshold = $threshold;
        $this->adminUrl = config('app.admin_url', config('app.url') . '/admin') . '/products/' . $product->id;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚠️ Alerta de Stock Bajo - {$this->product->name}",
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
            view: 'emails.low-stock-alert',
            with: [
                'product' => $this->product,
                'currentStock' => $this->currentStock,
                'threshold' => $this->threshold,
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