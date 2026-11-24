<?php

namespace App\Listeners;

use App\Events\ProductExchangeCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogProductExchange implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ProductExchangeCreated $event): void
    {
        // تسجيل في السجل
        \Log::info('تبديل منتج جديد', [
            'exchange_id' => $event->exchange->id,
            'product_id' => $event->exchange->product_id,
            'customer_id' => $event->exchange->customer_id,
            'employee_id' => $event->exchange->employee_id,
            'quantity_returned' => $event->exchange->quantity_returned,
            'quantity_provided' => $event->exchange->quantity_provided,
            'reason' => $event->exchange->reason,
        ]);
    }
}
