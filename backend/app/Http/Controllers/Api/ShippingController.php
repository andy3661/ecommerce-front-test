<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;

class ShippingController extends Controller
{
    protected ShippingService $shippingService;

    public function __construct(ShippingService $shippingService)
    {
        $this->middleware('auth:sanctum')->except(['methods', 'calculate']);
        $this->shippingService = $shippingService;
    }

    /**
     * Get available shipping methods
     */
    public function methods(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'destination_city' => 'required|string|max:100',
                'destination_state' => 'required|string|max:100',
                'destination_country' => 'required|string|size:2',
                'destination_postal_code' => 'required|string|max:20',
                'weight' => 'required|numeric|min:0.1',
                'dimensions' => 'sometimes|array',
                'dimensions.length' => 'required_with:dimensions|numeric|min:1',
                'dimensions.width' => 'required_with:dimensions|numeric|min:1',
                'dimensions.height' => 'required_with:dimensions|numeric|min:1',
                'declared_value' => 'sometimes|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $methods = $this->shippingService->getAvailableMethods($request->all());

            return response()->json([
                'success' => true,
                'data' => $methods
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get shipping methods', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get shipping methods'
            ], 500);
        }
    }

    /**
     * Calculate shipping cost
     */
    public function calculate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'carrier' => 'required|string|in:coordinadora,servientrega,interrapidisimo,tcc,fedex,dhl',
                'service_type' => 'required|string',
                'origin_city' => 'required|string|max:100',
                'origin_state' => 'required|string|max:100',
                'destination_city' => 'required|string|max:100',
                'destination_state' => 'required|string|max:100',
                'destination_country' => 'required|string|size:2',
                'destination_postal_code' => 'required|string|max:20',
                'weight' => 'required|numeric|min:0.1',
                'dimensions' => 'sometimes|array',
                'dimensions.length' => 'required_with:dimensions|numeric|min:1',
                'dimensions.width' => 'required_with:dimensions|numeric|min:1',
                'dimensions.height' => 'required_with:dimensions|numeric|min:1',
                'declared_value' => 'sometimes|numeric|min:0',
                'items' => 'sometimes|array',
                'items.*.quantity' => 'required_with:items|integer|min:1',
                'items.*.weight' => 'required_with:items|numeric|min:0.1',
                'items.*.value' => 'required_with:items|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $calculation = $this->shippingService->calculateShipping($request->all());

            return response()->json([
                'success' => true,
                'data' => $calculation
            ]);

        } catch (Exception $e) {
            Log::error('Failed to calculate shipping', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate shipping cost'
            ], 500);
        }
    }

    /**
     * Create shipping label
     */
    public function createLabel(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|exists:orders,id',
                'carrier' => 'required|string|in:coordinadora,servientrega,interrapidisimo,tcc,fedex,dhl',
                'service_type' => 'required|string',
                'sender' => 'required|array',
                'sender.name' => 'required|string|max:100',
                'sender.phone' => 'required|string|max:20',
                'sender.email' => 'required|email',
                'sender.address' => 'required|string|max:200',
                'sender.city' => 'required|string|max:100',
                'sender.state' => 'required|string|max:100',
                'sender.postal_code' => 'required|string|max:20',
                'recipient' => 'required|array',
                'recipient.name' => 'required|string|max:100',
                'recipient.phone' => 'required|string|max:20',
                'recipient.email' => 'sometimes|email',
                'recipient.address' => 'required|string|max:200',
                'recipient.city' => 'required|string|max:100',
                'recipient.state' => 'required|string|max:100',
                'recipient.postal_code' => 'required|string|max:20',
                'package' => 'required|array',
                'package.weight' => 'required|numeric|min:0.1',
                'package.dimensions' => 'required|array',
                'package.dimensions.length' => 'required|numeric|min:1',
                'package.dimensions.width' => 'required|numeric|min:1',
                'package.dimensions.height' => 'required|numeric|min:1',
                'package.declared_value' => 'required|numeric|min:0',
                'package.description' => 'required|string|max:200'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $label = $this->shippingService->createShippingLabel($request->all());

            return response()->json([
                'success' => true,
                'data' => $label
            ]);

        } catch (Exception $e) {
            Log::error('Failed to create shipping label', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create shipping label'
            ], 500);
        }
    }

    /**
     * Track shipment
     */
    public function track(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'tracking_number' => 'required|string',
                'carrier' => 'required|string|in:coordinadora,servientrega,interrapidisimo,tcc,fedex,dhl'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tracking = $this->shippingService->trackShipment(
                $request->input('tracking_number'),
                $request->input('carrier')
            );

            return response()->json([
                'success' => true,
                'data' => $tracking
            ]);

        } catch (Exception $e) {
            Log::error('Failed to track shipment', [
                'error' => $e->getMessage(),
                'tracking_number' => $request->input('tracking_number'),
                'carrier' => $request->input('carrier')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to track shipment'
            ], 500);
        }
    }

    /**
     * Get shipping zones
     */
    public function zones(): JsonResponse
    {
        try {
            $zones = $this->shippingService->getShippingZones();

            return response()->json([
                'success' => true,
                'data' => $zones
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get shipping zones', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get shipping zones'
            ], 500);
        }
    }

    /**
     * Get carrier coverage
     */
    public function coverage(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'carrier' => 'required|string|in:coordinadora,servientrega,interrapidisimo,tcc,fedex,dhl',
                'city' => 'sometimes|string|max:100',
                'state' => 'sometimes|string|max:100',
                'postal_code' => 'sometimes|string|max:20'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $coverage = $this->shippingService->getCarrierCoverage(
                $request->input('carrier'),
                $request->only(['city', 'state', 'postal_code'])
            );

            return response()->json([
                'success' => true,
                'data' => $coverage
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get carrier coverage', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get carrier coverage'
            ], 500);
        }
    }

    /**
     * Webhook for shipping updates
     */
    public function webhook(Request $request, string $carrier): JsonResponse
    {
        try {
            $result = $this->shippingService->processWebhook($carrier, $request->all());

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook processed successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to process webhook'
            ], 400);

        } catch (Exception $e) {
            Log::error('Failed to process shipping webhook', [
                'error' => $e->getMessage(),
                'carrier' => $carrier,
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed'
            ], 500);
        }
    }
}