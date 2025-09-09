<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $user;
    public $newStatus;
    public $previousStatus;
    public $statusMessage;
    public $trackingInfo;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order, string $newStatus, ?string $previousStatus = null, ?string $trackingInfo = null)
    {
        $this->order = $order;
        $this->user = $order->user;
        $this->newStatus = $newStatus;
        $this->previousStatus = $previousStatus;
        $this->trackingInfo = $trackingInfo;
        $this->statusMessage = $this->getStatusMessage($newStatus);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $statusText = $this->getStatusText($this->newStatus);
        
        return new Envelope(
            subject: "Actualización de Pedido #{$this->order->id} - {$statusText}",
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
            view: 'emails.order-status-update',
            with: [
                'order' => $this->order,
                'user' => $this->user,
                'userName' => $this->user->first_name ?? $this->user->name ?? 'Cliente',
                'newStatus' => $this->newStatus,
                'previousStatus' => $this->previousStatus,
                'statusMessage' => $this->statusMessage,
                'statusText' => $this->getStatusText($this->newStatus),
                'statusColor' => $this->getStatusColor($this->newStatus),
                'trackingInfo' => $this->trackingInfo,
                'trackingNumber' => $this->order->tracking_number,
                'trackingUrl' => $this->order->tracking_number ? 
                    route('orders.track', $this->order->tracking_number) : null,
                'orderUrl' => config('app.frontend_url', config('app.url')) . '/orders/' . $this->order->id,
                'supportEmail' => config('mail.support.address', config('mail.from.address')),
                'appName' => config('app.name'),
                'updateTime' => now()->format('d/m/Y H:i'),
                'estimatedDelivery' => $this->order->estimated_delivery_date ? 
                    $this->order->estimated_delivery_date->format('d/m/Y') : null
            ]
        );
    }

    /**
     * Get status message based on status
     */
    private function getStatusMessage(string $status): string
    {
        return match($status) {
            'pending' => 'Tu pedido ha sido recibido y está siendo procesado.',
            'confirmed' => 'Tu pedido ha sido confirmado y está siendo preparado.',
            'processing' => 'Tu pedido está siendo preparado para el envío.',
            'shipped' => 'Tu pedido ha sido enviado y está en camino.',
            'delivered' => '¡Tu pedido ha sido entregado exitosamente!',
            'cancelled' => 'Tu pedido ha sido cancelado.',
            'refunded' => 'Tu pedido ha sido reembolsado.',
            default => 'El estado de tu pedido ha sido actualizado.'
        };
    }

    /**
     * Get status text for display
     */
    private function getStatusText(string $status): string
    {
        return match($status) {
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmado',
            'processing' => 'Procesando',
            'shipped' => 'Enviado',
            'delivered' => 'Entregado',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            default => ucfirst($status)
        };
    }

    /**
     * Get status color for styling
     */
    private function getStatusColor(string $status): string
    {
        return match($status) {
            'pending' => '#ffc107',
            'confirmed' => '#17a2b8',
            'processing' => '#007bff',
            'shipped' => '#fd7e14',
            'delivered' => '#28a745',
            'cancelled' => '#dc3545',
            'refunded' => '#6c757d',
            default => '#6c757d'
        };
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