<?php

namespace App\Events;

use App\Models\ProductExchange;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductExchangeCreated
{
    use Dispatchable, SerializesModels;

    public $exchange;

    /**
     * Create a new event instance.
     */
    public function __construct(ProductExchange $exchange)
    {
        $this->exchange = $exchange;
    }
}
