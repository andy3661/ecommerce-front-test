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
use App\Mail\OrderConfirmationMail;

class SendOrderConfirmationEmail implements ShouldQueue
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
        public User $user
    ) {
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Sending order confirmation email', [
                'order_id' => $this->order->id,
                'user_email' => $this->order->user->email
            ]);

            Mail::to($this->order->user->email)
                ->send(new OrderConfirmationMail($this->order));
            
            Log::info('Order confirmation email sent successfully', [
                'order_id' => $this->order->id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email', [
                'order_id' => $this->order->id,
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
        Log::error('Order confirmation email job failed permanently', [
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'error' => $exception->getMessage()
        ]);
    }
}