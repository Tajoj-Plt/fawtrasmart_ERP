<?php

namespace Modules\Pos\Services\Sale;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\CompiledProducts;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PermissionSource;
use App\Models\PosSession;
use App\Models\PosSessionDetail;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use Illuminate\Support\Facades\DB;
use Modules\Pos\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosSettings;
use Modules\Pos\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Pos\Http\Controllers\Concerns\UsesDocumentCurrency;
use Modules\Pos\Models\PosAuditLog;
use Modules\Pos\Models\PosOrderPayment;
use Modules\Pos\Services\Accounting\PosJournalPoster;

/**
 * إلغاءُ فاتورة بيع — الفعلُ الذي كانت صلاحيتُه بلا تنفيذ.
 *
 * ═════════════════════ لماذا لم يكن موجوداً؟ ═════════════════════
 *
 * لأنّ الوسيلةَ الوحيدة لتصحيح فاتورةٍ خاطئة كانت **المرتجع**. وهذا يعمل
 * محاسبياً لكنه يكذب على التقارير: بيعٌ لم يقع أصلاً — ضغطةٌ خاطئة، أو
 * صنفٌ مسحوبٌ مرّتين، أو عميلٌ عدل عن الشراء قبل أن يغادر — يظهر مبيعاتٍ
 * ثم مرتجعاً. فيتضخّم «إجمالي المبيعات» و«إجمالي المرتجعات» معاً بمبلغٍ
 * لم يتحرّك، وتُقرأ نسبةُ المرتجعات على أنها مشكلةُ جودةٍ أو بائع.
 *
 * ═════════════════════ الإلغاءُ غيرُ المرتجع ═════════════════════
 *
 *   **المرتجع** ← بيعٌ وقع ثم رُدَّت بضاعتُه. مستندٌ جديد، وقيدٌ جديد،
 *                 ورقمٌ جديد. تاريخُ الاثنين يبقى.
 *
 *   **الإلغاء**  ← بيعٌ **لم يقع**. الفاتورةُ تُعلَّم ملغاة، وأثرُها يُعكَس
 *                 بقيدٍ مضادّ، وتخرج من كل تقرير.
 *
 * ═════════════════════ ولا يُحذف شيء ═════════════════════
 *
 * الفاتورةُ الملغاة تبقى بحالتها ورقمها: رقمُ المستند استُهلك، والإيصالُ
 * قد يكون طُبع وخرج من يد الكاشير. وحذفُها يُحدث ثغرةً في التسلسل لا
 * يفسّرها شيء — وهو أوّلُ ما يبحث عنه المدقّق.
 *
 * وقيدُ العكس قيدٌ ثانٍ لا محوٌ للأوّل: الدفترُ لا يُمحى منه، يُقيَّد فيه
 * ما يعكس.
 */
class VoidSaleService
{
    // `ResolvesAccountingAccounts` تقرأ `posSettings()` — فتلزم السِّمتان معاً
    use ResolvesAccountingAccounts, ResolvesPosSettings;
    use RespectsAccountingPeriods;
    use UsesDocumentCurrency;

    /**
     * إلغاءُ فاتورةٍ وعكسُ أثرها كاملاً.
     *
     * @throws \RuntimeException
     */
    public function void(Invoice $invoice, string $reason, ?PosSession $session = null): Invoice
    {
        $this->assertVoidable($invoice);

        if ($block = $this->accountingPeriodBlock(now())) {
            throw new \RuntimeException($block);
        }

        return DB::transaction(function () use ($invoice, $reason, $session) {
            $session ??= $invoice->session_id ? PosSession::find($invoice->session_id) : null;

            $this->restoreStock($invoice);
            $this->reverseJournals($invoice, $session);
            $this->reverseSessionTransaction($invoice, $session);

            // الدفعاتُ تُعلَّم مستردّة ولا تُحذف — أثرُ ما استلمه الكاشير يبقى
            PosOrderPayment::where('invoice_id', $invoice->id)
                ->update(['status' => PosOrderPayment::STATUS_REFUNDED]);

            /*
                `voided_at` لا `status`.

                جدولُ `invoices` **لا يحمل عمود `status` إطلاقاً** — وقد
                كانت الشيفرة تستعلم عنه في أربعة مواضع، فترمي
                `Unknown column` في كل نداء.
            */
            $invoice->update([
                'voided_at' => now(),
                'voided_by' => auth()->id(),
                'void_reason' => $reason,
                'payment_status' => 4,
                'is_paid' => false,
                'due_value' => 0,
                'remaining_amount' => 0,
            ]);

            PosAuditLog::record(PosAuditLog::ACTION_VOID_INVOICE, [
                'session_id' => $invoice->session_id,
                'invoice_id' => $invoice->id,
                'device_id' => $session?->device_id,
                'approved_by' => auth()->id(),
                'amount' => $invoice->grand_total,
                'reason' => $reason,
                'before' => ['payment_status' => $invoice->getOriginal('payment_status')],
                'after' => ['voided_at' => now()->toDateTimeString()],
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * موانعُ الإلغاء.
     *
     * ═════════ لماذا لا تُلغى فاتورةٌ لها مرتجع؟ ═════════
     *
     * لأنّ المرتجعَ أعاد بعضَ بضاعتها إلى المخزون بالفعل. والإلغاءُ يعيدها
     * كلَّها — فتُضاف الكميةُ المرتجعة مرّتين، ويزيد المخزونُ عمّا بِيع.
     * والصوابُ حينئذٍ إتمامُ المرتجع لا الإلغاء.
     *
     * @throws \RuntimeException
     */
    private function assertVoidable(Invoice $invoice): void
    {
        if ($invoice->type !== 'pos') {
            throw new \RuntimeException('الإلغاء من هذه الشاشة لفواتير نقطة البيع وحدها.');
        }

        if ($invoice->isVoided()) {
            throw new \RuntimeException('هذه الفاتورة ملغاة بالفعل.');
        }

        /* ما بلغ هيئة الزكاة والضريبة والجمارك وقُبل لا يُلغى: الإلغاء يمحو
           أثره في دفاترنا ويبقى قائماً عندها، فيختلف ما صرّحنا به عمّا في
           الدفاتر. تصحيحه مرتجعٌ يبلغها إشعاراً دائناً. */
        if (\App\Services\Zatca\ZatcaGuard::isAccepted(\App\Models\ZatcaDocument::TYPE_INVOICE, (int) $invoice->id)) {
            throw new \RuntimeException(__('zatca.errors.void_accepted'));
        }

        $hasReturns = Invoice::where('reference_number', $invoice->id)
            ->where('type', 'returned')
            ->exists();

        if ($hasReturns) {
            throw new \RuntimeException(
                'لا يمكن إلغاء فاتورةٍ لها مرتجع — أُعيد بعضُ بضاعتها للمخزون فعلاً. أكمل المرتجع بدل الإلغاء.'
            );
        }

        /*
            الجلسةُ المقفلة عُدَّ درجُها وسُلِّمت عهدتُها.

            وإلغاءُ فاتورةٍ فيها يُغيّر «إجمالي المبيعات» بعد أن وُقّع على
            الفرق — فيصير المحفوظُ لا يساوي المحسوب، ولا يُعرف أيُّهما الصواب.
        */
        if ($invoice->session_id) {
            $status = PosSession::where('id', $invoice->session_id)->value('status');

            if (in_array($status, [PosSession::STATUS_CLOSED, PosSession::STATUS_PENDING_REVIEW], true)) {
                throw new \RuntimeException(
                    'لا يمكن إلغاء فاتورةٍ من جلسةٍ أُقفلت. استعمل المرتجع.'
                );
            }
        }
    }

    /**
     * إعادةُ البضاعة إلى المخزون — بأنواع المنتجات الثلاثة.
     */
    private function restoreStock(Invoice $invoice): void
    {
        $permissionSource = PermissionSource::firstOrCreate(['name' => 'إلغاء فاتورة نقطة بيع']);

        foreach (InvoiceItem::where('invoice_id', $invoice->id)->get() as $item) {
            $product = Product::find($item->product_id);

            if (! $product) {
                continue;
            }

            // الكميةُ بالوحدة الأساسية كما جُمّدت في البند
            $quantity = (float) ($item->quantity_in_base_unit ?: $item->quantity);

            $isInstant = $product->type === 'compiled' && $product->compile_type === 'Instant';

            if (! $isInstant) {
                $this->increaseStock($item->product_id, $quantity, $item->store_house_id, $invoice, $permissionSource, $item);
            }

            // المجمَّعُ بنوعيه يُعيد مكوّناته — كما في المرتجع
            if ($product->type === 'compiled') {
                foreach (CompiledProducts::where('compile_id', $product->id)->get() as $component) {
                    $componentQty = (float) $component->qyt * $quantity;

                    if ($componentQty > 0) {
                        $this->increaseStock($component->product_id, $componentQty, $item->store_house_id, $invoice, $permissionSource, $item);
                    }
                }
            }
        }
    }

    private function increaseStock($productId, float $quantity, $storeHouseId, Invoice $invoice, $permissionSource, $item): void
    {
        $details = ProductDetails::firstOrCreate(
            ['store_house_id' => $storeHouseId, 'product_id' => $productId],
            ['quantity' => 0]
        );

        $before = (float) DB::table('product_details')->where('product_id', $productId)->sum('quantity');

        $details->increment('quantity', $quantity);

        $permit = WarehousePermits::create([
            'permission_type' => $permissionSource->id,
            'permission_date' => now(),
            'number' => $invoice->id,
            'grand_total' => $invoice->grand_total,
            'store_houses_id' => $storeHouseId,
            'created_by' => auth()->id(),
        ]);

        WarehousePermitsProducts::create([
            'quantity' => $quantity,
            'total' => $item->total ?? 0,
            'unit_price' => $item->unit_price ?? 0,
            'product_id' => $productId,
            'stock_before' => $before,
            'stock_after' => $before + $quantity,
            'warehouse_permits_id' => $permit->id,
        ]);
    }

    /**
     * قيودٌ مضادّة لكل قيدٍ وُلد من الفاتورة.
     *
     * تُعكَس السطورُ كما هي — مدينُها دائنٌ ودائنُها مدين — فلا يُعاد بناءُ
     * منطق القيد الأصلي ولا يُخمَّن ما كان فيه. وأيُّ قيدٍ أُضيف مستقبلاً
     * (عمولة، ولاء، ضريبةٌ ثانية) يُعكَس معها بلا سطرٍ يُكتب هنا.
     */
    private function reverseJournals(Invoice $invoice, ?PosSession $session): void
    {
        $entries = \App\Models\JournalEntry::where('invoice_id', $invoice->id)
            ->where('reference_number', 'not like', '%-VOID')
            ->get();

        foreach ($entries as $entry) {
            $poster = PosJournalPoster::make([
                'reference_number' => $entry->reference_number.'-VOID',
                'date' => now(),
                'description' => 'عكس قيد ملغى — '.$entry->description,
                'currency' => $this->documentCurrency($invoice),
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'branch_id' => $entry->branch_id ?? $session?->branch_id,
                'accounting_period_id' => $this->currentAccountingPeriodId(),
                'reference_type' => ReferenceType::SALES_RETURN,
                'linking_type' => LinkingType::SINGLE,
            ]);

            foreach (\App\Models\JournalEntryDetail::where('journal_entry_id', $entry->id)->get() as $line) {
                // العكسُ: ما كان مديناً يصير دائناً والعكس
                $poster->line(
                    (int) $line->account_id,
                    (float) $line->credit,
                    (float) $line->debit,
                    'عكس: '.$line->description
                );
            }

            $poster->post();
        }
    }

    /**
     * عكسُ أثر الفاتورة في تفاصيل الجلسة.
     *
     * صفٌّ مضادّ لا حذفُ الأصل: الجلسةُ سجلٌّ زمني لما جرى على الدرج،
     * وحذفُ سطرٍ منها يُخفي أن بيعاً وقع ثم أُلغي.
     */
    private function reverseSessionTransaction(Invoice $invoice, ?PosSession $session): void
    {
        if (! $session) {
            return;
        }

        $original = PosSessionDetail::where('session_id', $session->id)
            ->where('reference_number', $invoice->code)
            ->where('transaction_type', 'sale')
            ->first();

        if (! $original) {
            return;
        }

        PosSessionDetail::create([
            'session_id' => $session->id,
            'transaction_type' => 'void',
            'reference_number' => $invoice->code,
            'amount' => -(float) $original->amount,
            'payment_method' => $original->payment_method,
            'cash_amount' => -(float) $original->cash_amount,
            'card_amount' => -(float) $original->card_amount,
            'description' => 'إلغاء بيع — فاتورة '.$invoice->code,
            'transaction_time' => now(),
        ]);
    }
}
