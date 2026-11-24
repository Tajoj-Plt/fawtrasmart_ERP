<?php

namespace App\Services;

use App\Models\AttendanceDays;
use App\Models\AttendancePhoto;
use App\Models\AttendancePhotoType;
use App\Models\AttendanceSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * التقاطُ صور الحضور وحفظُها والتحقّقُ من اكتمالها.
 *
 * جدولُ `attendance_photos` وأنواعُ الصور وشاشةُ إدارتها كانت كلُّها موجودة،
 * ولم يكن أحدٌ يكتب صفاً واحداً فيها: لا مسار تسجيلٍ يستقبل صورة. فكان مفتاح
 * «طلب صور عند الحضور/الانصراف» وعدّادا الصور في شاشة الإعدادات بلا أثر.
 *
 * هذه الطبقة تُغلق تلك الفجوة: تتحقّق ممّا تشترطه الإعدادات وأنواع الصور
 * لمرحلةٍ بعينها، ثم تحفظ ما وصل.
 */
class AttendancePhotoRecorder
{
    public const PHASE_CHECKIN = 'checkin';

    public const PHASE_CHECKOUT = 'checkout';

    /**
     * يتحقّق من كفاية الصور الواصلة لهذه المرحلة.
     *
     * @param  array<int, array{type_id?: int|string|null, image?: string|null}>  $photos
     * @return array<int, string> رسائلُ النقص، وفراغُها يعني الاكتمال
     */
    public function validate(string $phase, array $photos, ?\App\Models\Employee $employee = null): array
    {
        $setting = AttendanceSetting::current();

        // إعدادُ الموظف يعلو على العام — كان يُقرأ في resolveConfig ويُهمَل هنا
        $required = $employee?->attendanceConfig?->photos_required ?? $setting->photos_required;

        if (! $required) {
            return [];
        }

        $errors = [];
        $supplied = collect($photos)->filter(fn ($p) => ! empty($p['image'] ?? null));

        // الأنواع المُعلَّمة «إلزامية» لهذه المرحلة
        $requiredTypes = AttendancePhotoType::query()
            ->active()
            ->where('is_required', true)
            ->forPhase($phase)
            ->get();

        foreach ($requiredTypes as $type) {
            $has = $supplied->contains(fn ($p) => (int) ($p['type_id'] ?? 0) === (int) $type->id);

            if (! $has) {
                $errors[] = 'الصورة الإلزامية مفقودة: '.$type->name;
            }
        }

        // الحدُّ الأدنى للعدد كما تضبطه شاشة الإعدادات
        $minimum = (int) ($phase === self::PHASE_CHECKIN
            ? ($setting->photos_count_checkin ?? 0)
            : ($setting->photos_count_checkout ?? 0));

        if ($minimum > 0 && $supplied->count() < $minimum) {
            $errors[] = sprintf('عدد الصور المطلوب %d، والمُرسَل %d', $minimum, $supplied->count());
        }

        return $errors;
    }

    /**
     * يحفظ الصور الواصلة ويربطها بيوم الحضور.
     *
     * @param  array<int, array{type_id?: int|string|null, image?: string|null}>  $photos
     * @return int عددُ ما حُفِظ
     */
    public function store(AttendanceDays $day, string $phase, array $photos, array $context = []): int
    {
        $saved = 0;

        foreach ($photos as $photo) {
            $raw = $photo['image'] ?? null;

            if (empty($raw)) {
                continue;
            }

            [$binary, $mime, $extension] = $this->decode($raw);

            $path = sprintf(
                'attendance_photos/%d/%s/%s.%s',
                $day->employee_id,
                $day->attendance_date instanceof \DateTimeInterface
                    ? $day->attendance_date->format('Y-m-d')
                    : (string) $day->attendance_date,
                Str::random(24),
                $extension
            );

            Storage::disk('public')->put($path, $binary);

            AttendancePhoto::create([
                'attendance_day_id' => $day->id,
                'photo_type_id' => $photo['type_id'] ?? null,
                'employee_id' => $day->employee_id,
                'phase' => $phase,
                'file_path' => $path,
                'original_name' => 'capture.'.$extension,
                'file_size' => strlen($binary),
                'mime_type' => $mime,
                'latitude' => $context['latitude'] ?? null,
                'longitude' => $context['longitude'] ?? null,
                'ip_address' => $context['ip_address'] ?? null,
            ]);

            $saved++;
        }

        return $saved;
    }

    /**
     * يفكّ data URI ويمنع أي نوعٍ غير صورة.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function decode(string $raw): array
    {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if (! preg_match('#^data:(image/[a-z+]+);base64,(.+)$#i', $raw, $matches)) {
            throw new RuntimeException('صيغة الصورة غير مفهومة');
        }

        $mime = strtolower($matches[1]);

        if (! isset($allowed[$mime])) {
            throw new RuntimeException('نوع الصورة غير مسموح: '.$mime);
        }

        $binary = base64_decode($matches[2], true);

        if ($binary === false || $binary === '') {
            throw new RuntimeException('تعذّر فكّ ترميز الصورة');
        }

        if (strlen($binary) > 5 * 1024 * 1024) {
            throw new RuntimeException('حجم الصورة يتجاوز 5 ميجابايت');
        }

        return [$binary, $mime, $allowed[$mime]];
    }
}
