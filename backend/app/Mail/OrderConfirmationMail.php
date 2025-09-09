<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->user = $order->user;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmación de Pedido #' . $this->order->id . ' - ' . config('app.name'),
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
            view: 'emails.order-confirmation',
            with: [
                'order' => $this->order,
                'user' => $this->user,
                'items' => $this->order->items()->with('product')->get(),
                'total' => $this->order->total,
                'subtotal' => $this->order->subtotal,
                'tax' => $this->order->tax ?? 0,
                'shipping' => $this->order->shipping_cost ?? 0,
                'orderDate' => $this->order->created_at->format('d/m/Y H:i'),
                'estimatedDelivery' => $this->order->estimated_delivery_date ? 
                    $this->order->estimated_delivery_date->format('d/m/Y') : 'Por confirmar',
                'trackingUrl' => $this->order->tracking_number ? 
                    route('orders.track', $this->order->tracking_number) : null,
                'shopUrl' => config('app.frontend_url', config('app.url')),
                'supportEmail' => config('mail.support.address', config('mail.from.address'))
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