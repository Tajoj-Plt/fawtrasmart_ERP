<?php

namespace Modules\Sitting\Repositories;

use App\Models\SystemCurrency;
use Illuminate\Support\Facades\Cache;

class CurrencyRepository
{
    /**
     * Get all active currencies
     */
    public function getActiveCurrencies()
    {
        return Cache::remember('active_system_currencies', 3600, function () {
            return SystemCurrency::where('is_active', true)->orderBy('sort_order')->get();
        });
    }

    /**
     * Get the default currency
     */
    public function getDefaultCurrency()
    {
        return Cache::remember('default_system_currency', 3600, function () {
            return SystemCurrency::where('is_default', true)->first();
        });
    }

    /**
     * Find a currency by ID
     */
    public function find($id)
    {
        return SystemCurrency::findOrFail($id);
    }

    /**
     * Create a new currency
     */
    public function create(array $data)
    {
        $currency = SystemCurrency::create($data);
        $this->clearCache();
        return $currency;
    }

    /**
     * Update an existing currency
     */
    public function update($id, array $data)
    {
        $currency = $this->find($id);
        $currency->update($data);
        $this->clearCache();
        return $currency;
    }

    /**
     * Delete a currency
     */
    public function delete($id)
    {
        $currency = $this->find($id);
        $currency->delete();
        $this->clearCache();
        return true;
    }

    /**
     * Set a currency as default
     */
    public function setAsDefault($id)
    {
        SystemCurrency::query()->update(['is_default' => false]);
        $currency = $this->find($id);
        $currency->update(['is_default' => true]);
        $this->clearCache();
        return $currency;
    }

    /**
     * Clear currency cache
     */
    public function clearCache()
    {
        Cache::forget('active_system_currencies');
        Cache::forget('default_system_currency');
    }
}
