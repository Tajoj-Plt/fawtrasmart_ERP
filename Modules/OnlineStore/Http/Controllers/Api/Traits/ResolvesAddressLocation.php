<?php

namespace Modules\OnlineStore\Http\Controllers\Api\Traits;

use App\Models\City;
use App\Models\Country;
use App\Services\OnlineStoreSettingsService;

/**
 * مطابقة الدولة والمدينة القادمتين من خريطة Google (نصٌّ حرّ) مع جداول المتجر.
 *
 * الواجهة لا تُلزم العميل باختيار الدولة والمدينة من قائمة: الخريطة تعيد
 * `country` و`city` نصاً — بالعربية أو الإنجليزية، أو رمز ISO للدولة — فيُطابَقان
 * هنا مع `countries`/`cities` (الاسم بعد التطبيع، الرمز، أو الاسم الإنجليزي
 * المعروف). المطابقةُ تحفظ المعرّفين؛ وغيابُها يُبقي النصَّ كما جاء ولا يفشل.
 * المعرّفان القديمان (`country_id`/`city_id`) يبقيان مقبولين ويتقدّمان على النص.
 */
trait ResolvesAddressLocation
{
    /** اسمٌ إنجليزيّ شائع ← رمز ISO للدولة. */
    private const COUNTRY_ALIASES = [
        'saudi arabia' => 'SA', 'ksa' => 'SA', 'kingdom of saudi arabia' => 'SA',
        'united arab emirates' => 'AE', 'uae' => 'AE', 'kuwait' => 'KW', 'bahrain' => 'BH',
        'oman' => 'OM', 'qatar' => 'QA', 'egypt' => 'EG', 'jordan' => 'JO', 'lebanon' => 'LB',
        'iraq' => 'IQ', 'syria' => 'SY', 'yemen' => 'YE', 'sudan' => 'SD', 'morocco' => 'MA',
        'algeria' => 'DZ', 'tunisia' => 'TN', 'libya' => 'LY', 'palestine' => 'PS', 'turkey' => 'TR',
        'türkiye' => 'TR', 'united states' => 'US', 'united kingdom' => 'GB',
    ];

    /** اسمٌ إنجليزيّ لأشهر المدن ← اسمها العربي في الجدول. */
    private const CITY_ALIASES = [
        'riyadh' => 'الرياض', 'jeddah' => 'جدة', 'jiddah' => 'جدة', 'mecca' => 'مكة المكرمة',
        'makkah' => 'مكة المكرمة', 'medina' => 'المدينة المنورة', 'madinah' => 'المدينة المنورة',
        'dammam' => 'الدمام', 'khobar' => 'الخبر', 'al khobar' => 'الخبر', 'dhahran' => 'الظهران',
        'taif' => 'الطائف', 'tabuk' => 'تبوك', 'abha' => 'أبها', 'buraydah' => 'بريدة',
        'khamis mushait' => 'خميس مشيط', 'hail' => 'حائل', 'jazan' => 'جازان', 'jizan' => 'جازان',
        'najran' => 'نجران', 'jubail' => 'الجبيل', 'yanbu' => 'ينبع', 'hofuf' => 'الهفوف',
        'dubai' => 'دبي', 'abu dhabi' => 'أبوظبي', 'sharjah' => 'الشارقة', 'doha' => 'الدوحة',
        'kuwait city' => 'مدينة الكويت', 'manama' => 'المنامة', 'muscat' => 'مسقط', 'cairo' => 'القاهرة',
        'amman' => 'عمان',
    ];

    /** تطبيعٌ للمقارنة: حروف صغيرة، توحيد الألف والتاء المربوطة والياء، حذف التشكيل و«ال» التعريف. */
    protected function normalizeLocationName(?string $value): string
    {
        $v = mb_strtolower(trim((string) $value));
        $v = preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u', '', $v) ?? $v;
        $v = strtr($v, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);
        $v = preg_replace('/\b(province|governorate|region|city|منطقه|محافظه|مدينه)\b/u', ' ', $v) ?? $v;
        $v = preg_replace('/\s+/u', ' ', trim($v)) ?? $v;

        return preg_replace('/^ال/u', '', $v) ?? $v;
    }

    /**
     * @param  array{country?: mixed, city?: mixed, country_id?: mixed, city_id?: mixed}  $input
     * @return array{country: ?string, country_id: ?int, city: ?string, city_id: ?int}
     */
    protected function resolveAddressLocation(array $input): array
    {
        $countryText = $this->cleanLocationText($input['country'] ?? null);
        $cityText = $this->cleanLocationText($input['city'] ?? null);

        $country = null;
        $city = null;

        // المعرّفان القديمان أولاً
        if (! empty($input['country_id']) && is_numeric($input['country_id'])) {
            $country = Country::where('status', 1)->find((int) $input['country_id']);
        }

        if (! empty($input['city_id']) && is_numeric($input['city_id'])) {
            $city = City::where('status', 1)->find((int) $input['city_id']);
        }

        if (! $country && $countryText !== null) {
            $country = $this->matchCountry($countryText);
        }

        if (! $city && $cityText !== null) {
            $city = $this->matchCity($cityText, $country?->id);
        }

        // مدينةٌ طابقت جدولنا تُحدّد دولتَها إن لم تُعرف
        if ($city && ! $country) {
            $country = Country::find($city->country_id);
        }

        // مدينةٌ من دولةٍ غير المختارة ⇒ لا تطابق (يبقى النص)
        if ($city && $country && (int) $city->country_id !== (int) $country->id) {
            $city = null;
        }

        // بلا دولة: دولةُ المتجر الافتراضية (إعداد المتجر ← الدولة المعلَّمة افتراضية ← السعودية)
        if (! $country && $countryText === null) {
            $code = OnlineStoreSettingsService::getMapsSettings()['default_country_code'];
            $country = Country::where('code', $code)->where('status', 1)->first();
        }

        return [
            'country' => $country?->name ?? $countryText ?? 'السعودية',
            'country_id' => $country?->id,
            'city' => $city?->name ?? $cityText,
            'city_id' => $city?->id,
        ];
    }

    private function cleanLocationText($value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, 100);
    }

    private function matchCountry(string $text): ?Country
    {
        $countries = Country::where('status', 1)->get(['id', 'name', 'code']);
        $upper = strtoupper($text);

        // رمز ISO (SA) أو الرمز الثلاثي الشائع (SAU)
        $byCode = $countries->first(fn ($c) => strtoupper((string) $c->code) === $upper);
        if ($byCode) {
            return $byCode;
        }

        $alias = self::COUNTRY_ALIASES[mb_strtolower($text)] ?? null;
        if ($alias) {
            $byAlias = $countries->first(fn ($c) => strtoupper((string) $c->code) === $alias);
            if ($byAlias) {
                return $byAlias;
            }
        }

        $needle = $this->normalizeLocationName($text);

        return $countries->first(fn ($c) => $this->normalizeLocationName($c->name) === $needle);
    }

    private function matchCity(string $text, ?int $countryId): ?City
    {
        $query = City::where('status', 1);
        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        $cities = $query->get(['id', 'country_id', 'name']);

        $text = self::CITY_ALIASES[mb_strtolower($text)] ?? $text;
        $needle = $this->normalizeLocationName($text);

        $matches = $cities->filter(fn ($c) => $this->normalizeLocationName($c->name) === $needle);

        // بلا دولة معروفة: لا نخمّن بين مدينتين بالاسم نفسه في دولتين
        return $matches->count() === 1 || ($countryId && $matches->isNotEmpty()) ? $matches->first() : null;
    }
}
