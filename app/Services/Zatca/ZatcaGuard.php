<?php

namespace App\Services\Zatca;

use App\Models\ZatcaDocument;
use Illuminate\Support\Facades\Schema;
use Saleh7\Zatca\CertificateBuilder;
use Saleh7\Zatca\ZatcaAPI;
use Throwable;

/**
 * أبلغ المستندُ هيئةَ الزكاة والضريبة والجمارك وقُبل؟
 *
 * سؤالٌ يطرحه كلّ مسارٍ يُلغي مستنداً أو يمحو أثره: ما قبلته الهيئة قائمٌ
 * عندها مهما جرى في دفاترنا، فلا يُصحَّح إلا بمستندٍ يبلغها هو أيضاً.
 *
 * في أيّ بيئةٍ كانت لا المفعَّلة وحدها: ما قُبل في البوابة الفعلية يبقى
 * مقبولاً وإن حُوِّلت البيئة بعده. وما قُبل في المنصّة التجريبية لا يُعتدّ
 * به — لا أثر نظامياً له.
 *
 * Usage:
 *   ZatcaGuard::isAccepted(ZatcaDocument::TYPE_INVOICE, $invoice->id);
 */
class ZatcaGuard
{
    /**
     * أحزمة الهيئة (`saleh7/php-zatca-xml`) مثبَّتةٌ على هذا الخادم؟
     *
     * الفاتورة تُحفَظ وتُقيَّد سواءٌ بلغت الهيئةَ أم لا؛ فغيابُ الحزمة على
     * خادمٍ لم يُشغَّل عليه `composer install` يُسجَّل ويُقال للمستخدم تنبيهاً،
     * ولا يُسقط حفظاً اكتمل.
     */
    public static function packageInstalled(): bool
    {
        return class_exists(ZatcaAPI::class)
            && class_exists(CertificateBuilder::class);
    }

    /** @throws ZatcaException حين تغيب الحزمة */
    public static function assertPackageInstalled(): void
    {
        if (! self::packageInstalled()) {
            throw new ZatcaException(__('zatca.errors.package_missing'));
        }
    }

    public static function isAccepted(string $type, int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        try {
            if (! Schema::hasTable('zatca_documents')) {
                return false;
            }

            return ZatcaDocument::query()
                ->where('document_type', $type)
                ->where('document_id', $id)
                ->whereIn('status', ZatcaDocument::ACCEPTED)
                ->where('environment', '!=', 'sandbox')
                ->exists();
        } catch (Throwable $e) {
            // قاعدةٌ لم تُرحَّل بعد: لا يُمنَع إلغاءٌ بسبب جدولٍ غائب
            return false;
        }
    }
}
