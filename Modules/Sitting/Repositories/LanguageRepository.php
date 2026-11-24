<?php

namespace Modules\Sitting\Repositories;

use App\Models\SystemLanguage;
use Illuminate\Support\Facades\Cache;

class LanguageRepository
{
    /**
     * Get all active languages
     */
    public function getActiveLanguages()
    {
        return Cache::remember('active_system_languages', 3600, function () {
            return SystemLanguage::active()->ordered()->get();
        });
    }

    /**
     * Get the default language
     */
    public function getDefaultLanguage()
    {
        return Cache::remember('default_system_language', 3600, function () {
            return SystemLanguage::where('is_default', true)->first();
        });
    }

    /**
     * أكواد اللغات المفعّلة — تُستخدم لتصفية مبدّل اللغة في الواجهة.
     */
    public function getActiveCodes(): array
    {
        $cached = Cache::get('active_system_language_codes');

        if ($cached !== null) {
            return $cached;
        }

        $codes = SystemLanguage::active()
            ->pluck('code')
            ->map(fn ($code) => (string) $code)
            ->all();

        // النتيجة الفارغة لا تُخزَّن حتى لا يتجمّد جدول لم يُزرع بعد على "لا لغات"
        if (! empty($codes)) {
            Cache::put('active_system_language_codes', $codes, 3600);
        }

        return $codes;
    }

    /**
     * Find a language by ID
     */
    public function find($id)
    {
        return SystemLanguage::findOrFail($id);
    }

    /**
     * قائمة مفلترة ومرقّمة لشاشة الإدارة.
     *
     * @param  array{search?:string|null,status?:string|null,direction?:string|null}  $filters
     */
    public function paginateFiltered(array $filters = [], int $perPage = 12)
    {
        $query = SystemLanguage::query()->search($filters['search'] ?? null);

        // الحالة تُرسل كـ 1/0 من الشاشة القديمة و active/inactive من النظام المرجعي
        $status = $filters['status'] ?? null;
        if ($status !== null && $status !== '') {
            $isActive = in_array($status, ['1', 1, 'active', true], true);
            $query->where('is_active', $isActive);
        }

        if (! empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        return $query->ordered()->paginate(\Modules\Sitting\Support\PageSize::get((int) $perPage));
    }

    /**
     * إحصائيات الترويسة.
     */
    public function stats(): array
    {
        return [
            'total' => SystemLanguage::count(),
            'active' => SystemLanguage::where('is_active', true)->count(),
            'inactive' => SystemLanguage::where('is_active', false)->count(),
            'rtl' => SystemLanguage::where('direction', 'rtl')->count(),
        ];
    }

    /**
     * Create a new language
     */
    public function create(array $data)
    {
        $language = SystemLanguage::create($data);
        $this->clearCache();

        return $language;
    }

    /**
     * Update an existing language
     */
    public function update($id, array $data)
    {
        $language = $this->find($id);
        $language->update($data);
        $this->clearCache();

        return $language;
    }

    /**
     * Delete a language
     */
    public function delete($id)
    {
        $language = $this->find($id);
        $language->delete();
        $this->clearCache();

        return true;
    }

    /**
     * Set a language as default
     */
    public function setAsDefault($id)
    {
        SystemLanguage::query()->update(['is_default' => false]);
        $language = $this->find($id);
        $language->update(['is_default' => true]);
        $this->clearCache();

        return $language;
    }

    /**
     * إلغاء صفة الافتراضية عن كل اللغات عدا المستثناة.
     */
    public function unsetDefaultExcept($exceptId = null): void
    {
        $query = SystemLanguage::where('is_default', true);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
        $this->clearCache();
    }

    /**
     * Clear language cache
     */
    public function clearCache()
    {
        Cache::forget('active_system_languages');
        Cache::forget('default_system_language');
        Cache::forget('active_system_language_codes');
    }
}
