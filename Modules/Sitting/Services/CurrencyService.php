<?php

namespace Modules\Sitting\Services;

use App\Models\Log as ModelsLog;
use App\Models\SystemCurrency;
use App\Services\CurrencyUsage;
use Illuminate\Support\Facades\DB;
use Modules\Sitting\Repositories\CurrencyRepository;

class CurrencyService
{
    protected $currencyRepository;

    protected CurrencyUsage $usage;

    public function __construct(CurrencyRepository $currencyRepository, CurrencyUsage $usage)
    {
        $this->currencyRepository = $currencyRepository;
        $this->usage = $usage;
    }

    /**
     * هل العملة الافتراضية الحالية مقفلة بحركات مسجَّلة؟
     *
     * القفل يمنع تبديل الافتراضية إلى عملة أخرى: الحركات القديمة لا يُعاد
     * تسعيرها، فتصبح أرقامها محسوبة بعملة لم تُحرَّر بها.
     */
    public function defaultIsLocked(): bool
    {
        $current = SystemCurrency::where('is_default', true)->first();

        return $current !== null && $this->usage->isUsed($current->code, true);
    }

    /**
     * وصف الحركات التي أقفلت العملة الافتراضية — للعرض في الواجهة.
     */
    public function defaultUsageSummary(): string
    {
        $current = SystemCurrency::where('is_default', true)->first();

        return $current === null ? '' : $this->usage->describe($current->code, true);
    }

    /**
     * يجعل عملةً أخرى هي الافتراضية، ما لم تكن الحالية مستعملة.
     */
    public function assertDefaultChangeable(string $newCode): void
    {
        $current = SystemCurrency::where('is_default', true)->first();

        if ($current === null || strcasecmp($current->code, $newCode) === 0) {
            return; // لا تبديل فعلياً
        }

        if (! $this->usage->isUsed($current->code, true)) {
            return;
        }

        throw new \Exception(__('settings.messages.currency_default_locked', [
            'currency' => $current->name,
            'usage' => $this->usage->describe($current->code, true),
        ]));
    }

    /**
     * Store a new currency with transaction and logging
     */
    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $currency = $this->currencyRepository->create($data);

            ModelsLog::create([
                'type' => 'setting',
                'type_log' => 'log',
                'description' => 'تم إضافة عملة جديدة للنظام: ' . $currency->name,
                'created_by' => auth()->id(),
            ]);

            return $currency;
        });
    }

    /**
     * Update an existing currency with transaction and logging
     */
    public function update($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $currency = $this->currencyRepository->find($id);

            // تغيير رمز عملة مستعملة يفصل الحركات القديمة عن تعريفها،
            // وعلى الافتراضية يعادل تبديلها بعملة أخرى من الباب الخلفي.
            if (isset($data['code']) && strcasecmp($currency->code, $data['code']) !== 0
                && $this->usage->isUsed($currency->code, (bool) $currency->is_default)) {
                throw new \Exception(__('settings.messages.currency_code_locked', [
                    'currency' => $currency->name,
                    'usage' => $this->usage->describe($currency->code, (bool) $currency->is_default),
                ]));
            }

            $currency = $this->currencyRepository->update($id, $data);

            ModelsLog::create([
                'type' => 'setting',
                'type_log' => 'log',
                'description' => 'تم تعديل بيانات العملة: ' . $currency->name,
                'created_by' => auth()->id(),
            ]);

            return $currency;
        });
    }

    /**
     * Delete a currency safely
     */
    public function delete($id)
    {
        return DB::transaction(function () use ($id) {
            $currency = $this->currencyRepository->find($id);

            if ($currency->is_default) {
                throw new \Exception(__('settings.messages.currency_default_undeletable'));
            }

            if ($this->usage->isUsed($currency->code, false)) {
                throw new \Exception(__('settings.messages.currency_used_undeletable', [
                    'currency' => $currency->name,
                    'usage' => $this->usage->describe($currency->code, false),
                ]));
            }

            $this->currencyRepository->delete($id);

            ModelsLog::create([
                'type' => 'setting',
                'type_log' => 'log',
                'description' => 'تم حذف العملة من النظام: ' . $currency->name,
                'created_by' => auth()->id(),
            ]);

            return true;
        });
    }

    /**
     * Toggle active status
     */
    public function toggleActive($id)
    {
        $currency = $this->currencyRepository->find($id);
        
        if ($currency->is_default && $currency->is_active) {
            throw new \Exception(__('settings.messages.currency_default_undisablable'));
        }

        return $this->currencyRepository->update($id, ['is_active' => !$currency->is_active]);
    }
}
