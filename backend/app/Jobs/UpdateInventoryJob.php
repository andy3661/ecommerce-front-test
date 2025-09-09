<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Mail\LowStockAlertMail;
use App\Mail\OutOfStockAlertMail;

class UpdateInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $productId,
        public int $quantityChange,
        public string $reason = 'manual_update',
        public ?int $orderId = null
    ) {
        $this->onQueue('inventory');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            DB::transaction(function () {
                $product = Product::lockForUpdate()->findOrFail($this->productId);
                $previousStock = $product->stock_quantity;
                $newStock = max(0, $previousStock + $this->quantityChange);
                
                Log::info('Updating product inventory', [
                    'product_id' => $this->productId,
                    'previous_stock' => $previousStock,
                    'quantity_change' => $this->quantityChange,
                    'new_stock' => $newStock,
                    'reason' => $this->reason,
                    'order_id' => $this->orderId
                ]);

                $product->update([
                    'stock_quantity' => $newStock,
                    'updated_at' => now()
                ]);

                // Check for low stock alert
                $lowStockThreshold = $product->low_stock_threshold ?? 10;
                if ($newStock <= $lowStockThreshold && $newStock > 0) {
                    $adminEmails = config('mail.admin_emails', [config('mail.from.address')]);
                    foreach ($adminEmails as $email) {
                        Mail::to($email)->send(new LowStockAlertMail($product, $newStock, $lowStockThreshold));
                    }
                }

                // Check for out of stock
                if ($newStock === 0 && $previousStock > 0) {
                    $adminEmails = config('mail.admin_emails', [config('mail.from.address')]);
                    foreach ($adminEmails as $email) {
                        Mail::to($email)->send(new OutOfStockAlertMail($product));
                    }
                }

                Log::info('Product inventory updated successfully', [
                    'product_id' => $this->productId,
                    'new_stock' => $newStock
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Failed to update product inventory', [
                'product_id' => $this->productId,
                'quantity_change' => $this->quantityChange,
                'reason' => $this->reason,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Send low stock alert to administrators
     */
    private function sendLowStockAlert(Product $product, int $currentStock): void
    {
        try {
            $adminUsers = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['admin', 'inventory_manager']);
            })->get();

            foreach ($adminUsers as $admin) {
                Mail::send('emails.low-stock-alert', [
                    'product' => $product,
                    'currentStock' => $currentStock,
                    'threshold' => $product->low_stock_threshold ?? 10,
                    'adminUrl' => config('app.admin_url') . '/products/' . $product->id
                ], function ($message) use ($admin, $product) {
                    $message->to($admin->email, $admin->name)
                        ->subject('Alerta de Stock Bajo - ' . $product->name)
                        ->from(config('mail.from.address'), config('mail.from.name'));
                });
            }

            Log::info('Low stock alert sent', [
                'product_id' => $product->id,
                'current_stock' => $currentStock,
                'admin_count' => $adminUsers->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send low stock alert', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send out of stock alert to administrators
     */
    private function sendOutOfStockAlert(Product $product): void
    {
        try {
            $adminUsers = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['admin', 'inventory_manager']);
            })->get();

            foreach ($adminUsers as $admin) {
                Mail::send('emails.out-of-stock-alert', [
                    'product' => $product,
                    'adminUrl' => config('app.admin_url') . '/products/' . $product->id
                ], function ($message) use ($admin, $product) {
                    $message->to($admin->email, $admin->name)
                        ->subject('Producto Agotado - ' . $product->name)
                        ->from(config('mail.from.address'), config('mail.from.name'));
                });
            }

            Log::info('Out of stock alert sent', [
                'product_id' => $product->id,
                'admin_count' => $adminUsers->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send out of stock alert', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Inventory update job failed permanently', [
            'product_id' => $this->productId,
            'quantity_change' => $this->quantityChange,
            'reason' => $this->reason,
            'error' => $exception->getMessage()
        ]);
    }
}