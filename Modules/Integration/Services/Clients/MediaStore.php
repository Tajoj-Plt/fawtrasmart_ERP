<?php

namespace Modules\Integration\Services\Clients;

use App\Support\OperationLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * حفظ صور التطبيق — نفس مجلّدات الويب ونفس ضغطه، كي تظهر الصورة المرفوعة
 * من الجوال في شاشات الموقع بلا فرق:
 *
 *   • مرفقات الملاحظة      → public/assets/uploads/notes           (اسم الملف فقط)
 *   • صور رصد المنافسين    → public/assets/uploads/visit_competitors (اسم الملف فقط)
 *   • التواقيع             → public/assets/uploads/visit_signatures  (اسم الملف فقط)
 *   • صورة مسؤول العميل    → public/uploads/client_notes            (مسارٌ نسبيّ كامل)
 *
 * الضغط نسخةُ `compressAndSaveImage()` في كنترولرات العملاء: أقصى بُعدٍ
 * 1920px، وجودةٌ تنزل كلّما كبر الملف.
 */
class MediaStore
{
    public const NOTES = 'assets/uploads/notes';

    public const COMPETITORS = 'assets/uploads/visit_competitors';

    public const SIGNATURES = 'assets/uploads/visit_signatures';

    public const CONTACTS = 'uploads/client_notes';

    /** صورةٌ مضغوطة في المجلّد المطلوب — يعيد اسم الملف وحده. */
    public static function storePhoto(UploadedFile $file, string $folder): string
    {
        $destination = public_path($folder);
        if (! is_dir($destination)) {
            @mkdir($destination, 0755, true);
        }

        if (in_array($file->getMimeType(), ['image/jpeg', 'image/jpg', 'image/png'], true)) {
            $filename = time().'_'.Str::random(10).'.jpg';

            if (self::compress($file->getRealPath(), $destination.'/'.$filename, self::qualityFor($file->getSize()))) {
                return $filename;
            }
        }

        $filename = time().'_'.Str::random(10).'.'.strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $file->move($destination, $filename);

        return $filename;
    }

    /** توقيعٌ مرسَلٌ صورةَ data-URL — يعيد اسم الملف أو null إن لم يكن صورة. */
    public static function storeSignature(?string $dataUrl, string $who): ?string
    {
        if (! $dataUrl || ! Str::startsWith($dataUrl, 'data:image')) {
            return null;
        }

        [, $encoded] = explode(',', $dataUrl, 2) + [null, null];
        $binary = $encoded ? base64_decode($encoded, true) : false;

        // صورةٌ حقيقية فقط — لا يُكتب إلى القرص نصٌّ متنكّر في data-URL
        if ($binary === false || @getimagesizefromstring($binary) === false) {
            return null;
        }

        $destination = public_path(self::SIGNATURES);
        if (! is_dir($destination)) {
            @mkdir($destination, 0755, true);
        }

        $filename = 'sig_'.$who.'_'.time().'_'.Str::random(6).'.png';
        file_put_contents($destination.'/'.$filename, $binary);

        return $filename;
    }

    /** يحذف ملفاً من مجلّدٍ معروف — بلا خروجٍ منه بمسارٍ مصنوع. */
    public static function delete(string $folder, ?string $filename): void
    {
        if (! $filename) {
            return;
        }

        $path = public_path($folder.'/'.basename($filename));
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function url(string $folder, ?string $filename): ?string
    {
        return $filename ? asset($folder.'/'.$filename) : null;
    }

    private static function qualityFor(int $bytes): int
    {
        return match (true) {
            $bytes > 10 * 1024 * 1024 => 40,
            $bytes > 5 * 1024 * 1024 => 50,
            $bytes > 2 * 1024 * 1024 => 60,
            default => 75,
        };
    }

    /** ضغط الصورة وتحجيمها — نسخة `ClientNoteController::compressAndSaveImage()`. */
    public static function compress(string $source, string $destination, int $quality = 70): bool
    {
        try {
            $info = @getimagesize($source);
            if (! $info) {
                return false;
            }

            [$width, $height] = $info;

            $image = match ($info['mime']) {
                'image/png' => @imagecreatefrompng($source),
                'image/gif' => @imagecreatefromgif($source),
                default => @imagecreatefromjpeg($source),
            };

            if (! $image) {
                return false;
            }

            $max = 1920;
            if ($width > $max || $height > $max) {
                $ratio = min($max / $width, $max / $height);
                $resized = imagecreatetruecolor((int) ($width * $ratio), (int) ($height * $ratio));
                imagecopyresampled($resized, $image, 0, 0, 0, 0, (int) ($width * $ratio), (int) ($height * $ratio), $width, $height);
                imagedestroy($image);
                $image = $resized;
            }

            $saved = imagejpeg($image, $destination, $quality);
            imagedestroy($image);

            return $saved;
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Integration', 'compress_image');

            return false;
        }
    }
}
