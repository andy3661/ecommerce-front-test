<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerta de Stock Bajo</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #ffc107; color: #212529; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .alert-box { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #ffc107; }
        .product-info { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .stock-info { font-size: 1.2em; font-weight: bold; color: #856404; }
        .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .footer { text-align: center; padding: 20px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Alerta de Stock Bajo</h1>
        </div>
        
        <div class="content">
            <div class="alert-box">
                <h3>Producto con Stock Bajo Detectado</h3>
                
                <p>Se ha detectado que el siguiente producto tiene un nivel de stock por debajo del umbral establecido:</p>
                
                <div class="product-info">
                    <h4>{{ $product->name }}</h4>
                    <p><strong>SKU:</strong> {{ $product->sku ?? 'N/A' }}</p>
                    <p><strong>Categoría:</strong> 
                        @if($product->categories->count() > 0)
                            {{ $product->categories->pluck('name')->join(', ') }}
                        @else
                            Sin categoría
                        @endif
                    </p>
                    
                    <div class="stock-info">
                        <p>📦 Stock Actual: {{ $currentStock }} unidades</p>
                        <p>🚨 Umbral Mínimo: {{ $threshold }} unidades</p>
                    </div>
                    
                    @if($product->price)
                    <p><strong>Precio:</strong> ${{ number_format($product->price, 2) }}</p>
                    @endif
                </div>
                
                <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <strong>🔔 Acción Requerida:</strong>
                    <ul style="margin: 10px 0;">
                        <li>Revisar el inventario del producto</li>
                        <li>Contactar a proveedores si es necesario</li>
                        <li>Considerar actualizar el stock o pausar las ventas</li>
                        <li>Verificar si hay pedidos pendientes que puedan afectar el stock</li>
                    </ul>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{ $adminUrl }}" class="btn">Gestionar Producto</a>
                </div>
            </div>
            
            <p><strong>Nota:</strong> Esta alerta se envía automáticamente cuando el stock de un producto cae por debajo del umbral configurado. Es importante tomar acción rápidamente para evitar quedarse sin stock.</p>
        </div>
        
        <div class="footer">
            <p>{{ config('app.name') }} - Sistema de Gestión de Inventario</p>
            <p>Alerta generada automáticamente el {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>
</body>
</html>