<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización de Pedido</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #17a2b8; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .status-box { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; }
        .status-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; color: white; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: #ffc107; }
        .status-confirmed { background: #28a745; }
        .status-processing { background: #007bff; }
        .status-shipped { background: #17a2b8; }
        .status-delivered { background: #28a745; }
        .status-cancelled { background: #dc3545; }
        .status-refunded { background: #6c757d; }
        .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .tracking-info { background: #e7f3ff; border-left: 4px solid #007bff; padding: 15px; margin: 15px 0; }
        .footer { text-align: center; padding: 20px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Actualización de tu Pedido</h1>
        </div>
        
        <div class="content">
            <p>Hola {{ $user->name }},</p>
            
            <div class="status-box">
                <h3>Pedido #{{ $order->id }}</h3>
                
                <p><strong>{{ $statusMessage }}</strong></p>
                
                <div style="margin: 20px 0;">
                    <span class="status-badge status-{{ $newStatus }}">{{ ucfirst($newStatus) }}</span>
                </div>
                
                @if($trackingInfo)
                <div class="tracking-info">
                    <strong>📦 {{ $trackingInfo }}</strong>
                    <p>Puedes usar este número para rastrear tu envío en la página web de la transportadora.</p>
                </div>
                @endif
                
                <div style="margin: 20px 0;">
                    <p><strong>Fecha del pedido:</strong> {{ $order->created_at->format('d/m/Y H:i') }}</p>
                    <p><strong>Total:</strong> ${{ number_format($order->total_amount, 2) }}</p>
                    
                    @if($order->shipping_address)
                    <p><strong>Dirección de envío:</strong><br>
                    {{ $order->shipping_address }}</p>
                    @endif
                </div>
                
                @if($newStatus === 'shipped')
                <p>🚚 Tu pedido está en camino. Recibirás una notificación cuando sea entregado.</p>
                @elseif($newStatus === 'delivered')
                <p>🎉 ¡Tu pedido ha sido entregado! Esperamos que disfrutes de tu compra.</p>
                @elseif($newStatus === 'cancelled')
                <p>❌ Tu pedido ha sido cancelado. Si tienes alguna pregunta, no dudes en contactarnos.</p>
                @elseif($newStatus === 'refunded')
                <p>💰 El reembolso de tu pedido ha sido procesado. El dinero debería aparecer en tu cuenta en 3-5 días hábiles.</p>
                @endif
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{ $orderUrl }}" class="btn">Ver Detalles del Pedido</a>
                </div>
            </div>
            
            <p>Si tienes alguna pregunta sobre tu pedido, no dudes en contactarnos.</p>
        </div>
        
        <div class="footer">
            <p>{{ config('app.name') }}</p>
            <p>Gracias por confiar en nosotros</p>
        </div>
    </div>
</body>
</html>