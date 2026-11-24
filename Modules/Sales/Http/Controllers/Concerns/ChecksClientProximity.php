<?php

namespace Modules\Sales\Http\Controllers\Concerns;

use App\Helpers\ClientSettingsHelper;
use App\Models\Location;
use Illuminate\Http\Request;

/**
 * التحقّق من قرب الموظّف من العميل قبل إصدار مستند — نقطةُ القرار الواحدة
 * لشاشات الفاتورة والمرتجع والإشعار الدائن.
 *
 * كان المنطقُ منسوخاً ثلاثَ مرّات (بنفس التعليقات وبنفس الرسالة الحرفية)،
 * وفي كلّ نسخةٍ خللان:
 *
 * **الأوّل: صلاحيةُ «من أيّ موقع» ميّتة عملياً.** كان لهذه الميزة اسمان في
 * جدول `permissions`: القديم `sales_*_from_any_location` والجديد
 * `sales.*.bypass_location` (كتالوج `SalesPermissions`)، ولا يقرأ أحدٌ
 * الاسمَ القديم في الشيفرة — فمن فعّله في الشاشة رأى الحظرَ كما هو. وقد
 * انقرض الاسمُ القديم مع بقيّة أسماء `job_roles`، فلم يبقَ إلا الجديد.
 *
 * **والثاني: النطاق كان `0.3` مكتوباً في الشيفرة.** وهو نفسه الإعداد
 * `visit_proximity_radius_km` في «إعدادات وحدات العملاء» ← الزيارات،
 * ولموظّفٍ بعينه تجاوزٌ خاصّ به (`users.visit_proximity_radius_km`) —
 * تقرؤهما وحدةُ العملاء في كل شاشة زيارة وملاحظة. فكان مديرٌ يوسّع النطاق
 * إلى كيلومترين فتتّسع الزياراتُ وتبقى الفواتيرُ على ثلاثمئة متر.
 *
 * @see \App\Helpers\ClientSettingsHelper::visitProximityRadiusKmFor()
 */
trait ChecksClientProximity
{
    /**
     * سببُ منع إصدار المستند لبُعد الموظّف عن العميل، أو `null` إن كان
     * مسموحاً.
     *
     * تُحدَّث إحداثيّاتُ الموظّف في الطريق (كما كانت): الشاشةُ ترسلها مع
     * كلّ حفظ، وهي آخرُ ما نعرفه عن موقعه.
     *
     * @param  string  $bypassPermission  الاسم في كتالوج `SalesPermissions`
     */
    protected function clientProximityBlock(
        Request $request,
        $clientId,
        string $bypassPermission
    ): ?string {
        $user = auth()->user();

        // غيرُ الموظّف لا يُقيَّد بموقع: الحسابُ الإداريّ يعمل من مكتبه
        if (! $user || ! $user->isEmployee()) {
            return null;
        }

        if (hasPermission($bypassPermission)) {
            return null;
        }

        if (! $clientId) {
            return null;
        }

        // صفُّ GPS الموظف نفسه — بلا `whereNull` كان يُحدِّث صفَّ ملاحظةٍ سابقة لعميلٍ آخر
        $employeeLocation = Location::where('employee_id', $user->id)->whereNull('client_id')->latest()->first();

        if (! $employeeLocation) {
            $employeeLocation = Location::create([
                'employee_id' => $user->id,
                'latitude' => $request->current_latitude,
                'longitude' => $request->current_longitude,
            ]);
        } elseif ($request->current_latitude !== null && $request->current_longitude !== null) {
            $employeeLocation->update([
                'latitude' => $request->current_latitude,
                'longitude' => $request->current_longitude,
            ]);
        }

        $clientLocation = Location::where('client_id', $clientId)->clientPoint()->latest()->first();

        /* صفٌّ بإحداثيّاتٍ فارغة أو خارج المدى كصفٍّ غير موجود: كانت
           `deg2rad(null)` تُعطي صفراً، فتُحسَب المسافةُ من نقطة الأصل في
           خليج غينيا — آلافُ الكيلومترات — ويُمنع الموظف برسالةِ بُعدٍ لا
           تدلّه على أنّ الناقص هو موقع العميل. */
        if (! $clientLocation || ! $this->hasUsableCoordinates($clientLocation)) {
            return 'لم يتم تسجيل موقع العميل! الرجاء التواصل مع الإدارة.';
        }

        if (! $employeeLocation || ! $this->hasUsableCoordinates($employeeLocation)) {
            return 'تعذّر تحديد موقعك الحالي. فعّل خدمة الموقع في المتصفّح وأعد المحاولة.';
        }

        $distance = $this->haversineKm(
            $employeeLocation->latitude,
            $employeeLocation->longitude,
            $clientLocation->latitude,
            $clientLocation->longitude
        );

        $maxDistance = ClientSettingsHelper::visitProximityRadiusKmFor($user);

        if ($distance <= $maxDistance) {
            return null;
        }

        return 'يجب أن تكون ضمن نطاق '.$maxDistance.' كيلومتر من العميل! المسافة الحالية: '.round($distance, 2).' كم';
    }

    /** إحداثيّاتٌ صالحةٌ للحساب: موجودةٌ وداخل مدى خطّي الطول والعرض. */
    protected function hasUsableCoordinates($location): bool
    {
        if ($location->latitude === null || $location->longitude === null) {
            return false;
        }

        return abs((float) $location->latitude) <= 90
            && abs((float) $location->longitude) <= 180;
    }

    /** المسافة بين نقطتين بالكيلومتر — نفس صيغة `ClientController`. */
    protected function haversineKm($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // كم

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($lonDelta / 2) * sin($lonDelta / 2);

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
