<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .reset-box { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; text-align: center; }
        .btn { display: inline-block; padding: 15px 30px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .footer { text-align: center; padding: 20px; color: #666; }
        .token { background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Restablecer Contraseña</h1>
        </div>
        
        <div class="content">
            <div class="reset-box">
                <h2>Hola {{ $user->name }},</h2>
                
                <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en {{ config('app.name') }}.</p>
                
                <p>Haz clic en el siguiente botón para crear una nueva contraseña:</p>
                
                <a href="{{ $resetUrl }}" class="btn">Restablecer Contraseña</a>
                
                <div class="warning">
                    <strong>⚠️ Importante:</strong>
                    <ul style="text-align: left; margin: 10px 0;">
                        <li>Este enlace expirará en {{ $expireMinutes }} minutos</li>
                        <li>Solo puedes usar este enlace una vez</li>
                        <li>Si no solicitaste este cambio, ignora este email</li>
                    </ul>
                </div>
                
                <p><strong>Si el botón no funciona, copia y pega este enlace en tu navegador:</strong></p>
                <div class="token">{{ $resetUrl }}</div>
                
                <p style="margin-top: 30px;">Si no solicitaste restablecer tu contraseña, puedes ignorar este email de forma segura. Tu contraseña actual seguirá siendo válida.</p>
            </div>
        </div>
        
        <div class="footer">
            <p>{{ config('app.name') }}</p>
            <p>Por tu seguridad, nunca compartas este enlace con nadie.</p>
        </div>
    </div>
</body>
</html>