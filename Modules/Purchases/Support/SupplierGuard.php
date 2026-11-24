<?php

namespace Modules\Purchases\Support;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Modules\Purchases\Services\PurchaseSettings;

/**
 * منع المستندات على مورد موقوف — حين يُفعَّل الخيار.
 *
 * شاشة الموردين تُوقف المورد بـ`status = 0` وتعرضه «موقوف» في جدولها، لكن
 * لا شيء في النظام كان يقرأ ذلك عند حفظ مستند: يُوقَف المورد ثم تُحفَظ له
 * فاتورة شراء في اليوم نفسه. فالإيقاف كان وسماً في الشاشة لا حكماً.
 *
 * الخيار مطفأ افتراضياً: منشآت توقف مورّداً ريثما تُراجع حسابه وهي مستمرّة
 * في استلام ما تعاقدت عليه، ففرضُ المنع عليها كان سيوقف عملها بلا طلب.
 */
class SupplierGuard
{
    /**
     * يرمي استثناءً إن كان المورد موقوفاً والخيار مفعَّلاً.
     *
     * يُستدعى بعد التحقّق من وجود المورد مباشرةً، فيلتقط الحفظ داخل
     * المعاملة قبل أن يُكتب سطر واحد.
     *
     * @throws \Exception
     */
    public static function assertActive(?Supplier $supplier): void
    {
        if (! $supplier || ! PurchaseSettings::enabled('supplier_block_inactive')) {
            return;
        }

        // العمود قد يكون null في سجلّات قديمة سبقت إضافته — والغياب ليس
        // إيقافاً، فلا يُمنع به مستند
        if ($supplier->status === null || (int) $supplier->status === 1) {
            return;
        }

        throw new \Exception(__('purchase_settings.messages.supplier_inactive', ['name' => $supplier->name]));
    }

    /**
     * ما يمنع حذف المورّد — قائمةُ أسبابٍ فارغةٌ تعني أن الحذف آمن.
     *
     * **لماذا؟** كانت `destroy()` تفحص الصلاحية ثم تحذف مباشرةً، فمورّدٌ
     * له عشرون فاتورةً وقيودها ومدفوعاتها يُمحى بنقرة. ولا مفتاح أجنبي
     * يمنع ذلك: `purchase_invoices.supplier_id` بلا قيدٍ مرجعي، فتبقى
     * الفواتير يتيمةً تشير إلى مورّدٍ لا وجود له — ويظهر أثرها في
     * التقارير باسمٍ فارغ، ويبقى حسابه في شجرة الحسابات برصيدٍ لا صاحب له.
     *
     * الحذف ليس السبيل لإخفاء مورّد لم يعد يُتعامل معه: لذلك `status = 0`
     * الذي تقرأه `assertActive()` فيمنع عليه المستندات الجديدة ويُبقي
     * تاريخه سليماً.
     *
     * @return array<int, string> أسباب المنع، للعرض على المستخدم
     */
    public static function deletionBlockers(Supplier $supplier): array
    {
        $blockers = [];

        $documents = DB::table('purchase_invoices')->where('supplier_id', $supplier->id)->count();
        if ($documents > 0) {
            $blockers[] = 'له '.$documents.' مستند شراء (فواتير أو مرتجعات أو أوامر)';
        }

        $payments = DB::table('payments_process')->where('supplier_id', $supplier->id)->count();
        if ($payments > 0) {
            $blockers[] = 'له '.$payments.' عملية سداد';
        }

        $entries = DB::table('journal_entry_details as d')
            ->join('accounts as a', 'a.id', '=', 'd.account_id')
            ->where('a.supplier_id', $supplier->id)
            ->count();
        if ($entries > 0) {
            $blockers[] = 'له '.$entries.' سطر في القيود المحاسبية';
        }

        $balance = (float) DB::table('accounts')->whereNotIn('type_accont', ['client_wallet', 'supplier_wallet'])->where('supplier_id', $supplier->id)->sum('balance');
        if (abs($balance) > 0.009) {
            $blockers[] = 'رصيد حسابه غير صفري ('.number_format($balance, 2).' ر.س)';
        }

        return $blockers;
    }

    /**
     * يرمي استثناءً إن كان للمورّد أثرٌ يمنع حذفه.
     *
     * @throws \Exception
     */
    public static function assertDeletable(Supplier $supplier): void
    {
        $blockers = self::deletionBlockers($supplier);

        if ($blockers === []) {
            return;
        }

        throw new \Exception(
            'لا يمكن حذف المورّد «'.($supplier->trade_name ?: $supplier->first_name).'» لأن '.
            implode('، و', $blockers).
            '. أوقفه بدل حذفه ليبقى تاريخه سليماً في التقارير.'
        );
    }

    /**
     * يرمي استثناءً إن كان للمورّد رصيدٌ غير صفري — بأي اتجاه — فيمنع إيقافه.
     *
     * **لماذا؟** مورّدٌ له علينا (دفعةٌ لم تُسدَّد بعد) أو لنا عليه (دفعةٌ
     * مقدَّمة لم تُستَرَدّ) يختفي عن قوائم الاختيار حين يُوقَف، فيغيب دَينه
     * عن المتابعة اليومية بينما يبقى قائماً في الدفاتر.
     *
     * @throws \Exception
     */
    public static function assertDeactivatable(Supplier $supplier): void
    {
        $balance = (float) DB::table('accounts')->whereNotIn('type_accont', ['client_wallet', 'supplier_wallet'])->where('supplier_id', $supplier->id)->sum('balance');

        if (abs($balance) <= 0.009) {
            return;
        }

        $direction = $balance > 0 ? 'له علينا' : 'لنا عليه';

        throw new \Exception(
            'لا يمكن إيقاف المورّد «'.($supplier->trade_name ?: $supplier->first_name).'» لأن رصيد حسابه غير صفري ('.
            number_format(abs($balance), 2).' ر.س '.$direction.'). سوِّ الرصيد أولاً ثم أوقفه.'
        );
    }
}
