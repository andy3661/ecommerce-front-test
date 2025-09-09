<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Producto Agotado</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .alert-box { background: white; padding: 20px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #dc3545; }
        .product-info { background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .stock-info { font-size: 1.2em; font-weight: bold; color: #721c24; }
        .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .urgent { background: #dc3545; }
        .footer { text-align: center; padding: 20px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Producto Agotado</h1>
        </div>
        
        <div class="content">
            <div class="alert-box">
                <h3>Producto Sin Stock Disponible</h3>
                
                <p><strong>URGENTE:</strong> El siguiente producto se ha quedado completamente sin stock:</p>
                
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
                        <p>📦 Stock Actual: 0 unidades</p>
                        <p>❌ Estado: AGOTADO</p>
                    </div>
                    
                    @if($product->price)
                    <p><strong>Precio:</strong> ${{ number_format($product->price, 2) }}</p>
                    @endif
                    
                    @if($product->status === 'active')
                    <p><strong>⚠️ El producto sigue activo en la tienda</strong> - Los clientes pueden intentar comprarlo</p>
                    @endif
                </div>
                
                <div style="background: #721c24; color: white; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <strong>🔥 ACCIÓN INMEDIATA REQUERIDA:</strong>
                    <ul style="margin: 10px 0;">
                        <li><strong>Desactivar el producto</strong> para evitar nuevos pedidos</li>
                        <li><strong>Contactar proveedores</strong> para reabastecimiento urgente</li>
                        <li><strong>Revisar pedidos pendientes</strong> que incluyan este producto</li>
                        <li><strong>Notificar al equipo de ventas</strong> sobre la situación</li>
                        <li><strong>Considerar productos alternativos</strong> para recomendar a clientes</li>
                    </ul>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{ $adminUrl }}" class="btn urgent">Gestionar Producto URGENTE</a>
                </div>
                
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <strong>💡 Recomendaciones:</strong>
                    <ul style="margin: 10px 0;">
                        <li>Configurar alertas de stock bajo para evitar esta situación</li>
                        <li>Revisar la demanda histórica del producto</li>
                        <li>Establecer stock de seguridad adecuado</li>
                        <li>Implementar reabastecimiento automático si es posible</li>
                    </ul>
                </div>
            </div>
            
            <p><strong>Nota:</strong> Esta alerta se envía automáticamente cuando un producto se queda sin stock. Es crítico tomar acción inmediata para minimizar el impacto en las ventas y la experiencia del cliente.</p>
        </div>
        
        <div class="footer">
            <p>{{ config('app.name') }} - Sistema de Gestión de Inventario</p>
            <p>Alerta crítica generada el {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>
</body>
</html>