<?php

namespace App\Services\Shipping\Carriers;

use App\Services\Shipping\ShippingCarrierInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class CoordinadoraCarrier implements ShippingCarrierInterface
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $username;
    protected string $password;
    protected bool $testMode;

    public function __construct()
    {
        $this->apiUrl = config('shipping.carriers.coordinadora.api_url');
        $this->apiKey = config('shipping.carriers.coordinadora.api_key');
        $this->username = config('shipping.carriers.coordinadora.username');
        $this->password = config('shipping.carriers.coordinadora.password');
        $this->testMode = config('shipping.carriers.coordinadora.test_mode', true);
    }

    /**
     * Get carrier name
     */
    public function getName(): string
    {
        return 'Coordinadora';
    }

    /**
     * Get available shipping methods
     */
    public function getAvailableMethods(array $destination): array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->getAccessToken()
            ])->post($this->apiUrl . '/recogidas/cotizador', [
                'nit' => config('shipping.carriers.coordinadora.nit'),
                'div' => '01',
                'cuenta' => config('shipping.carriers.coordinadora.account'),
                'producto' => '0',
                'origen' => config('shipping.default_origin.city_code'),
                'destino' => $this->getCityCode($destination['destination_city'], $destination['destination_state']),
                'valoracion' => $destination['declared_value'] ?? 50000,
                'nivel_servicio' => ['1', '2', '3'], // Different service levels
                'modalidad' => ['1', '2'] // Normal and express
            ]);

            if (!$response->successful()) {
                throw new Exception('Coordinadora API error: ' . $response->body());
            }

            $data = $response->json();
            $methods = [];

            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $quote) {
                    $methods[] = [
                        'service_code' => $quote['codigo_producto'],
                        'service_name' => $quote['nombre_producto'],
                        'service_type' => $this->mapServiceType($quote['nivel_servicio']),
                        'price' => (float) $quote['flete'],
                        'estimated_days' => (int) $quote['tiempo_entrega'],
                        'description' => $quote['descripcion'] ?? '',
                        'max_weight' => 70, // kg
                        'currency' => 'COP'
                    ];
                }
            }

            return $methods;

        } catch (Exception $e) {
            Log::error('Coordinadora getAvailableMethods failed', [
                'error' => $e->getMessage(),
                'destination' => $destination
            ]);
            
            // Return default methods if API fails
            return $this->getDefaultMethods();
        }
    }

    /**
     * Calculate shipping cost
     */
    public function calculateShipping(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->getAccessToken()
            ])->post($this->apiUrl . '/recogidas/cotizador', [
                'nit' => config('shipping.carriers.coordinadora.nit'),
                'div' => '01',
                'cuenta' => config('shipping.carriers.coordinadora.account'),
                'producto' => $data['service_type'] ?? '0',
                'origen' => $this->getCityCode($data['origin_city'], $data['origin_state']),
                'destino' => $this->getCityCode($data['destination_city'], $data['destination_state']),
                'valoracion' => $data['declared_value'] ?? 50000,
                'nivel_servicio' => $this->getServiceLevel($data['service_type']),
                'modalidad' => $this->getModalidad($data['service_type']),
                'peso' => $data['weight'],
                'largo' => $data['dimensions']['length'] ?? 10,
                'ancho' => $data['dimensions']['width'] ?? 10,
                'alto' => $data['dimensions']['height'] ?? 10
            ]);

            if (!$response->successful()) {
                throw new Exception('Coordinadora API error: ' . $response->body());
            }

            $result = $response->json();

            if (!isset($result['data'][0])) {
                throw new Exception('No shipping quote available');
            }

            $quote = $result['data'][0];

            return [
                'carrier' => 'coordinadora',
                'service_type' => $data['service_type'],
                'cost' => (float) $quote['flete'],
                'estimated_days' => (int) $quote['tiempo_entrega'],
                'currency' => 'COP',
                'service_name' => $quote['nombre_producto'],
                'quote_id' => $quote['id'] ?? null,
                'raw_response' => $quote
            ];

        } catch (Exception $e) {
            Log::error('Coordinadora calculateShipping failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Create shipping label
     */
    public function createLabel(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->getAccessToken()
            ])->post($this->apiUrl . '/guias/generar', [
                'nit' => config('shipping.carriers.coordinadora.nit'),
                'div' => '01',
                'cuenta' => config('shipping.carriers.coordinadora.account'),
                'producto' => $data['service_type'],
                'origen' => $this->getCityCode($data['sender']['city'], $data['sender']['state']),
                'destino' => $this->getCityCode($data['recipient']['city'], $data['recipient']['state']),
                'tercero' => [
                    'nit' => $data['recipient']['document'] ?? '1',
                    'nombre' => $data['recipient']['name'],
                    'direccion' => $data['recipient']['address'],
                    'telefono' => $data['recipient']['phone'],
                    'email' => $data['recipient']['email'] ?? ''
                ],
                'remitente' => [
                    'nit' => config('shipping.carriers.coordinadora.sender_nit'),
                    'nombre' => $data['sender']['name'],
                    'direccion' => $data['sender']['address'],
                    'telefono' => $data['sender']['phone'],
                    'email' => $data['sender']['email']
                ],
                'detalle' => [
                    'peso' => $data['package']['weight'],
                    'largo' => $data['package']['dimensions']['length'],
                    'ancho' => $data['package']['dimensions']['width'],
                    'alto' => $data['package']['dimensions']['height'],
                    'valoracion' => $data['package']['declared_value'],
                    'descripcion' => $data['package']['description']
                ],
                'referencia' => 'ORDER_' . $data['order_id'],
                'observaciones' => $data['notes'] ?? ''
            ]);

            if (!$response->successful()) {
                throw new Exception('Coordinadora API error: ' . $response->body());
            }

            $result = $response->json();

            if (!isset($result['guia'])) {
                throw new Exception('Failed to generate shipping label');
            }

            return [
                'tracking_number' => $result['guia'],
                'label_url' => $result['url_rotulo'] ?? null,
                'cost' => $result['flete'] ?? 0,
                'estimated_delivery_date' => $result['fecha_entrega'] ?? null,
                'raw_response' => $result
            ];

        } catch (Exception $e) {
            Log::error('Coordinadora createLabel failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Track shipment
     */
    public function trackShipment(string $trackingNumber): array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->getAccessToken()
            ])->get($this->apiUrl . '/guias/tracking/' . $trackingNumber);

            if (!$response->successful()) {
                throw new Exception('Coordinadora API error: ' . $response->body());
            }

            $result = $response->json();
            $events = [];

            if (isset($result['tracking']) && is_array($result['tracking'])) {
                foreach ($result['tracking'] as $event) {
                    $events[] = [
                        'date' => $event['fecha'],
                        'time' => $event['hora'] ?? '',
                        'status' => $this->mapTrackingStatus($event['estado']),
                        'description' => $event['descripcion'],
                        'location' => $event['ciudad'] ?? ''
                    ];
                }
            }

            return [
                'tracking_number' => $trackingNumber,
                'status' => $this->mapTrackingStatus($result['estado_actual'] ?? 'unknown'),
                'events' => $events,
                'estimated_delivery' => $result['fecha_entrega_estimada'] ?? null,
                'actual_delivery' => $result['fecha_entrega_real'] ?? null,
                'raw_response' => $result
            ];

        } catch (Exception $e) {
            Log::error('Coordinadora trackShipment failed', [
                'error' => $e->getMessage(),
                'tracking_number' => $trackingNumber
            ]);
            throw $e;
        }
    }

    /**
     * Get carrier coverage
     */
    public function getCoverage(array $location = []): array
    {
        // Coordinadora covers most of Colombia
        return [
            'countries' => ['CO'],
            'states' => ['all'], // All Colombian departments
            'cities' => ['most'], // Most Colombian cities
            'postal_codes' => ['all'],
            'restrictions' => [
                'max_weight' => 70, // kg
                'max_dimensions' => ['length' => 150, 'width' => 150, 'height' => 150], // cm
                'prohibited_items' => ['weapons', 'drugs', 'hazardous_materials']
            ]
        ];
    }

    /**
     * Process webhook
     */
    public function processWebhook(array $payload): ?array
    {
        try {
            if (!isset($payload['guia']) || !isset($payload['estado'])) {
                return null;
            }

            return [
                'tracking_number' => $payload['guia'],
                'status' => $this->mapTrackingStatus($payload['estado']),
                'event' => [
                    'date' => $payload['fecha'] ?? now()->format('Y-m-d'),
                    'time' => $payload['hora'] ?? now()->format('H:i:s'),
                    'description' => $payload['descripcion'] ?? '',
                    'location' => $payload['ciudad'] ?? ''
                ]
            ];

        } catch (Exception $e) {
            Log::error('Coordinadora processWebhook failed', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            return null;
        }
    }

    /**
     * Get estimated delivery time
     */
    public function getEstimatedDeliveryTime(string $serviceType, array $destination): array
    {
        $baseDays = match ($serviceType) {
            'express' => 1,
            'standard' => 3,
            'economy' => 5,
            default => 3
        };

        return [
            'min_days' => $baseDays,
            'max_days' => $baseDays + 2,
            'description' => "{$baseDays}-" . ($baseDays + 2) . " días hábiles"
        ];
    }

    /**
     * Validate configuration
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->username) && !empty($this->password);
    }

    /**
     * Get supported services
     */
    public function getSupportedServices(): array
    {
        return [
            'express' => 'Express',
            'standard' => 'Estándar',
            'economy' => 'Económico'
        ];
    }

    /**
     * Get maximum weight
     */
    public function getMaxWeight(): float
    {
        return 70.0; // kg
    }

    /**
     * Get maximum dimensions
     */
    public function getMaxDimensions(): array
    {
        return [
            'length' => 150, // cm
            'width' => 150,  // cm
            'height' => 150  // cm
        ];
    }

    /**
     * Check if destination is supported
     */
    public function supportsDestination(array $destination): bool
    {
        return ($destination['destination_country'] ?? '') === 'CO';
    }

    /**
     * Get access token
     */
    protected function getAccessToken(): string
    {
        // Implementation would cache the token and refresh when needed
        // For now, return the API key
        return $this->apiKey;
    }

    /**
     * Get city code for Coordinadora
     */
    protected function getCityCode(string $city, string $state): string
    {
        // This would typically use a mapping table or API call
        // For now, return a default code
        return '05001'; // Medellín as default
    }

    /**
     * Map service type to Coordinadora service level
     */
    protected function getServiceLevel(string $serviceType): string
    {
        return match ($serviceType) {
            'express' => '1',
            'standard' => '2',
            'economy' => '3',
            default => '2'
        };
    }

    /**
     * Map service type to modalidad
     */
    protected function getModalidad(string $serviceType): string
    {
        return match ($serviceType) {
            'express' => '2',
            default => '1'
        };
    }

    /**
     * Map service type
     */
    protected function mapServiceType(string $nivelServicio): string
    {
        return match ($nivelServicio) {
            '1' => 'express',
            '2' => 'standard',
            '3' => 'economy',
            default => 'standard'
        };
    }

    /**
     * Map tracking status
     */
    protected function mapTrackingStatus(string $status): string
    {
        return match (strtolower($status)) {
            'recogido', 'picked_up' => 'picked_up',
            'en_transito', 'in_transit' => 'in_transit',
            'en_reparto', 'out_for_delivery' => 'out_for_delivery',
            'entregado', 'delivered' => 'delivered',
            'devuelto', 'returned' => 'returned',
            'excepcion', 'exception' => 'exception',
            default => 'in_transit'
        };
    }

    /**
     * Get default methods when API fails
     */
    protected function getDefaultMethods(): array
    {
        return [
            [
                'service_code' => 'standard',
                'service_name' => 'Estándar',
                'service_type' => 'standard',
                'price' => 15000,
                'estimated_days' => 3,
                'description' => 'Envío estándar 3-5 días',
                'max_weight' => 70,
                'currency' => 'COP'
            ],
            [
                'service_code' => 'express',
                'service_name' => 'Express',
                'service_type' => 'express',
                'price' => 25000,
                'estimated_days' => 1,
                'description' => 'Envío express 1-2 días',
                'max_weight' => 70,
                'currency' => 'COP'
            ]
        ];
    }
}