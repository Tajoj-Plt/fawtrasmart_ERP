<?php

namespace Modules\OnlineStore\Http\Controllers\Api\Traits;

/**
 * Trait لتنسيق روابط الصور
 * يحول المسارات النسبية إلى روابط كاملة
 */
trait FormatsImages
{
    /**
     * تحويل مسار الصورة إلى رابط كامل
     */
    protected function formatImageUrl(?string $imagePath): ?string
    {
        if (empty($imagePath)) {
            return null;
        }

        // إذا كان الرابط كاملاً بالفعل
        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }

        // إزالة الـ slash من البداية إذا وجد
        $imagePath = ltrim($imagePath, '/');

        // إرجاع الرابط الكامل
        return url($imagePath);
    }

    /**
     * تحويل مصفوفة صور إلى روابط كاملة
     *
     * @param  mixed  $images
     */
    protected function formatImagesUrls($images): array
    {
        if (empty($images)) {
            return [];
        }

        // إذا كانت الصور نص JSON
        if (is_string($images)) {
            $images = json_decode($images, true);
            if (! is_array($images)) {
                return [$this->formatImageUrl($images)];
            }
        }

        if (! is_array($images)) {
            return [];
        }

        return array_map(fn ($img) => $this->formatImageUrl($img), $images);
    }

    /**
     * الحصول على أول صورة مع تنسيق الرابط
     *
     * @param  mixed  $images
     */
    protected function getFirstImageUrl($images): ?string
    {
        if (empty($images)) {
            return null;
        }

        if (is_string($images)) {
            $decoded = json_decode($images, true);
            if (is_array($decoded) && count($decoded) > 0) {
                return $this->formatImageUrl($decoded[0]);
            }

            return $this->formatImageUrl($images);
        }

        if (is_array($images) && count($images) > 0) {
            return $this->formatImageUrl($images[0]);
        }

        return null;
    }

    /**
     * الحصول على رابط الصورة مع دعم الحجم
     *
     * @param  string  $size  الحجم المطلوب (original, thumbnail, medium, large)
     */
    protected function getImageUrl(?string $imagePath, string $size = 'original'): ?string
    {
        if (empty($imagePath)) {
            return null;
        }

        // إذا كان الرابط كاملاً بالفعل
        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }

        // إزالة الـ slash من البداية إذا وجد
        $imagePath = ltrim($imagePath, '/');

        // إذا كان الحجم thumbnail، يمكن إنشاء مسار مختلف
        // لكن حالياً نعيد نفس الرابط
        // يمكن تعديل هذا لاحقاً لدعم أحجام مختلفة

        return url($imagePath);
    }
}
