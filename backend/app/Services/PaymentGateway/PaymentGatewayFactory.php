<?php

namespace App\Services\PaymentGateway;

use App\Services\PaymentGateway\Gateways\StripeGateway;
use App\Services\PaymentGateway\Gateways\PayPalGateway;
use App\Services\PaymentGateway\Gateways\PayUGateway;
use App\Services\PaymentGateway\Gateways\WompiGateway;
use App\Services\PaymentGateway\Gateways\MercadoPagoGateway;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    /**
     * Create a payment gateway instance
     *
     * @param string $gateway
     * @return PaymentGatewayInterface
     * @throws InvalidArgumentException
     */
    public static function create(string $gateway): PaymentGatewayInterface
    {
        return match ($gateway) {
            'stripe' => new StripeGateway(),
            'paypal' => new PayPalGateway(),
            'payu' => new PayUGateway(),
            'wompi' => new WompiGateway(),
            'mercadopago' => new MercadoPagoGateway(),
            default => throw new InvalidArgumentException("Unsupported payment gateway: {$gateway}")
        };
    }

    /**
     * Get all available gateways
     *
     * @return array
     */
    public static function getAvailableGateways(): array
    {
        return [
            'stripe' => [
                'name' => 'Stripe',
                'class' => StripeGateway::class,
                'enabled' => config('payments.stripe.enabled', false)
            ],
            'paypal' => [
                'name' => 'PayPal',
                'class' => PayPalGateway::class,
                'enabled' => config('payments.paypal.enabled', false)
            ],
            'payu' => [
                'name' => 'PayU',
                'class' => PayUGateway::class,
                'enabled' => config('payments.payu.enabled', false)
            ],
            'wompi' => [
                'name' => 'Wompi',
                'class' => WompiGateway::class,
                'enabled' => config('payments.wompi.enabled', false)
            ],
            'mercadopago' => [
                'name' => 'MercadoPago',
                'class' => MercadoPagoGateway::class,
                'enabled' => config('payments.mercadopago.enabled', false)
            ]
        ];
    }

    /**
     * Get enabled gateways
     *
     * @return array
     */
    public static function getEnabledGateways(): array
    {
        return array_filter(self::getAvailableGateways(), function ($gateway) {
            return $gateway['enabled'];
        });
    }

    /**
     * Check if a gateway is supported
     *
     * @param string $gateway
     * @return bool
     */
    public static function isSupported(string $gateway): bool
    {
        return array_key_exists($gateway, self::getAvailableGateways());
    }

    /**
     * Check if a gateway is enabled
     *
     * @param string $gateway
     * @return bool
     */
    public static function isEnabled(string $gateway): bool
    {
        $gateways = self::getAvailableGateways();
        return isset($gateways[$gateway]) && $gateways[$gateway]['enabled'];
    }
}