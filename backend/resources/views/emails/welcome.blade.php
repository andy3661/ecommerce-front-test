<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #28a745; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .welcome-box { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; text-align: center; }
        .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px; }
        .footer { text-align: center; padding: 20px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>¡Bienvenido a {{ config('app.name') }}!</h1>
        </div>
        
        <div class="content">
            <div class="welcome-box">
                <h2>Hola {{ $user->name }},</h2>
                
                <p>¡Gracias por registrarte en nuestra tienda online! Estamos emocionados de tenerte como parte de nuestra comunidad.</p>
                
                <p>Con tu cuenta podrás:</p>
                <ul style="text-align: left; display: inline-block;">
                    <li>Explorar nuestro catálogo completo de productos</li>
                    <li>Realizar compras de forma rápida y segura</li>
                    <li>Seguir el estado de tus pedidos</li>
                    <li>Guardar productos en tu lista de deseos</li>
                    <li>Recibir ofertas exclusivas y descuentos especiales</li>
                </ul>
                
                <div style="margin: 30px 0;">
                    <a href="{{ $shopUrl }}" class="btn">Explorar Productos</a>
                    <a href="{{ $loginUrl }}" class="btn" style="background: #28a745;">Iniciar Sesión</a>
                </div>
                
                <p>Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos. Nuestro equipo de soporte está aquí para ayudarte.</p>
            </div>
        </div>
        
        <div class="footer">
            <p>{{ config('app.name') }}</p>
            <p>¡Esperamos que disfrutes de tu experiencia de compra!</p>
        </div>
    </div>
</body>
</html>