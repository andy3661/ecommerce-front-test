<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Pedido</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #007bff; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .order-details { background: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .item { border-bottom: 1px solid #eee; padding: 10px 0; }
        .total { font-weight: bold; font-size: 1.2em; color: #007bff; }
        .footer { text-align: center; padding: 20px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>¡Gracias por tu pedido!</h1>
        </div>
        
        <div class="content">
            <p>Hola {{ $user->name }},</p>
            
            <p>Hemos recibido tu pedido y está siendo procesado. Aquí tienes los detalles:</p>
            
            <div class="order-details">
                <h3>Pedido #{{ $order->id }}</h3>
                <p><strong>Fecha:</strong> {{ $order->created_at->format('d/m/Y H:i') }}</p>
                <p><strong>Estado:</strong> {{ ucfirst($order->status) }}</p>
                
                <h4>Productos:</h4>
                @foreach($orderItems as $item)
                <div class="item">
                    <strong>{{ $item->product->name }}</strong><br>
                    Cantidad: {{ $item->quantity }}<br>
                    Precio unitario: ${{ number_format($item->price, 2) }}<br>
                    Subtotal: ${{ number_format($item->quantity * $item->price, 2) }}
                </div>
                @endforeach
                
                <div class="total">
                    <p>Total: ${{ number_format($total, 2) }}</p>
                </div>
            </div>
            
            <p>Te enviaremos actualizaciones sobre el estado de tu pedido por email.</p>
            
            <p>¡Gracias por confiar en nosotros!</p>
        </div>
        
        <div class="footer">
            <p>{{ config('app.name') }}</p>
            <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>
        </div>
    </div>
</body>
</html>