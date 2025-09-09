<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\OrderStatusUpdateMail;

class SendOrderStatusNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Order $order,
        public User $user,
        public string $previousStatus,
        public string $newStatus
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Sending order status notification', [
                'order_id' => $this->order->id,
                'new_status' => $this->newStatus,
                'user_email' => $this->order->user->email
            ]);

            $trackingInfo = $this->order->tracking_number ? 'Número de seguimiento: ' . $this->order->tracking_number : null;

            Mail::to($this->order->user->email)
                ->send(new OrderStatusUpdateMail($this->order, $this->newStatus, $this->previousStatus, $trackingInfo));
            
            Log::info('Order status notification sent successfully', [
                'order_id' => $this->order->id,
                'new_status' => $this->newStatus
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send order status notification', [
                'order_id' => $this->order->id,
                'new_status' => $this->newStatus,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Order status notification job failed permanently', [
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'new_status' => $this->newStatus,
            'error' => $exception->getMessage()
        ]);
    }
}