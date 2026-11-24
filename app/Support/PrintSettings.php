<?php

namespace App\Support;

use App\Models\AccountSetting;
use App\Models\DocumentTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * إعدادات الطباعة والمستندات كما ضُبطت في شاشتَي «قوالب المستندات»
 * و«معلومات الحساب».
 *
 * كانت كل شاشة تسرد قوالبها الخاصة مكتوبةً في القالب، فلا يظهر القالب الذي
 * صمّمه المسؤول في الإعدادات ولا يُحترم خياره بين الطباعة عبر المتصفّح
 * وتنزيل PDF. هذه نقطة القراءة الموحّدة لأي قسم يطبع مستنداً:
 *
 *     PrintSettings::templatesFor('purchase_order')   // قوالب القسم الفعّالة
 *     PrintSettings::defaultTemplateFor('purchase_order')
 *     PrintSettings::method()                          // browser | pdf
 */
class PrintSettings
{
    /** طريقة الطباعة المفضَّلة للمستخدم الحالي */
    public static function method(): string
    {
        return self::accountValue('printing_method', 'browser');
    }

    public static function prefersPdf(): bool
    {
        return self::method() === 'pdf';
    }

    /**
     * قوالب نوع مستند بعينه — الفعّالة فقط، وفرعُ المستخدم أو العامّة.
     *
     * الافتراضي أولاً ثم بالاسم، فترتيب القائمة في الشاشة ثابت ومفهوم.
     *
     * @return Collection<int, DocumentTemplate>
     */
    public static function templatesFor(string $documentType): Collection
    {
        try {
            return DocumentTemplate::query()
                ->where('document_type', $documentType)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('branch_id');

                    if ($branchId = Auth::user()->branch_id ?? null) {
                        $query->orWhere('branch_id', $branchId);
                    }
                })
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();
        } catch (\Throwable $e) {
            // جدول القوالب غير مهيّأ: تبقى الشاشة على قوالبها المدمجة
            return collect();
        }
    }

    /** القالب الافتراضي للنوع — أو أول قالب فعّال، أو null */
    public static function defaultTemplateFor(string $documentType): ?DocumentTemplate
    {
        $templates = self::templatesFor($documentType);

        return $templates->firstWhere('is_default', true) ?? $templates->first();
    }

    /** ختم المنشأة المرفوع في إعدادات الحساب */
    public static function stampUrl(): ?string
    {
        return self::accountAsset('stamp');
    }

    /** التوقيع المرفوع في إعدادات الحساب */
    public static function signatureUrl(): ?string
    {
        return self::accountAsset('signature');
    }

    /** الشعار المرفوع في إعدادات الحساب */
    public static function logoUrl(): ?string
    {
        return self::accountAsset('attachments');
    }

    protected static function accountAsset(string $column): ?string
    {
        $path = self::accountValue($column, null);

        return $path ? asset('storage/'.$path) : null;
    }

    /**
     * @return mixed
     */
    protected static function accountValue(string $column, $default)
    {
        if (! Auth::check()) {
            return $default;
        }

        try {
            return AccountSetting::where('user_id', Auth::id())->value($column) ?: $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
