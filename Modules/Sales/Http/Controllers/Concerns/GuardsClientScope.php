<?php

namespace Modules\Sales\Http\Controllers\Concerns;

/**
 * ردُّ مستندٍ يُنسَب إلى عميلٍ خارج نطاق مُنشِئه.
 *
 * ══════════ لماذا حارسٌ فوق المُنتقي ══════════
 *
 * قوائمُ العميل في شاشات الإنشاء مقصورةٌ على النطاق
 * ({@see \App\Services\Clients\ClientVisibilityScope})، والمُنتقي **وعدٌ لا
 * حارس**: `client_id` يصل من النموذج ويُكتب في المستند كما هو، فيكفي
 * تبديلُ القيمة في المتصفّح لفتح فاتورةٍ أو عرضٍ أو اشتراكٍ دوريّ على
 * عميلٍ لا يراه المستخدم في «إدارة العملاء» — ومن ذلك المستند يقرأ رصيدَ
 * العميل وحدَّه الائتمانيّ وفواتيرَه. وهي القاعدةُ نفسُها التي تلتزمها
 * {@see \App\Support\DataScope::clampEmployee()} في مُنتقي مسؤول المبيعات.
 *
 * ══════════ ولماذا على الموظّف وحدَه ══════════
 *
 * من لا سجلَّ موظفٍ له لا محورَ لتقييده — استثناءُ `DataScope` نفسُه —
 * وتقييدُه هنا يردّ حفظَ مسؤول النظام بلا سبب. وهو أيضاً شرطُ الحارس
 * القائم في `CreditNotificationController::settlementPreview()`، فلا
 * يفترق حارسان لسؤالٍ واحد.
 *
 * ══════════ وعميلُ المستند المحفوظ ══════════
 *
 * يبقى مقبولاً عند التعديل ولو خرج عن النطاق بعد حفظه (أُسنِد لغيره، أو
 * بُدّل فرعُ الشريط): المستندُ معروضٌ له وصلاحيةُ تعديله محروسةٌ قبل هذا،
 * وردُّه هنا يُقفل عليه مستنداً يملكه بلا وسيلةٍ لإتمامه.
 *
 * يستعمله من عنده `scopedClientsForSelect()` — وهي التي تعرف كيف تُبنى
 * قائمةُ تلك الشاشة (أعمدتها وعلاقاتها)، فلا تُكتب هنا نسخةٌ ثانية.
 */
trait GuardsClientScope
{
    /**
     * سببُ الردّ — أو `null` إن كان العميل مقبولاً.
     *
     * @param  mixed  $clientId  العميل المُرسَل من النموذج
     * @param  mixed  $currentClientId  عميلُ المستند المحفوظ — للتعديل
     */
    protected function clientOutOfScopeBlock($clientId, $currentClientId = null): ?string
    {
        $user = auth()->user();
        $clientId = (int) $clientId;

        if (! $clientId || ! $user || ! $user->isEmployee()) {
            return null;
        }

        if ($currentClientId && $clientId === (int) $currentClientId) {
            return null;
        }

        return $this->scopedClientsForSelect($currentClientId)->contains('id', $clientId)
            ? null
            : __('sales_invoice.client_out_of_scope');
    }
}
