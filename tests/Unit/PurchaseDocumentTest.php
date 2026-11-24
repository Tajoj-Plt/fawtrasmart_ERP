<?php

namespace Tests\Unit;

use App\Enums\PurchaseDocumentType;
use App\Models\PurchaseInvoice;
use App\Services\Purchases\PurchaseDocumentRules as Rules;
use App\Services\Purchases\PurchasePaymentCalculator as Calc;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * قواعد مستندات المشتريات — اختبارات لا تمسّ قاعدة البيانات.
 *
 * الأنواع والقدرات وحساب الدفع منطقٌ خالص، فيُختبر بموديلات في الذاكرة
 * (`new PurchaseInvoice([...])`) بلا اتصال ولا هجرات.
 */
class PurchaseDocumentTest extends TestCase
{
    private function document(string $type, array $attributes = []): PurchaseInvoice
    {
        $document = new PurchaseInvoice;
        $document->forceFill(array_merge(['type' => $type, 'grand_total' => 1000], $attributes));

        return $document;
    }

    // ==================== النوع ====================

    #[Test]
    public function enum_values_match_the_database_column(): void
    {
        // القيم ليست اختياراً: هي ما يقبله عمود enum في الجدول
        $this->assertSame('Requested', PurchaseDocumentType::ORDER->value);
        $this->assertSame('invoice', PurchaseDocumentType::INVOICE->value);
        $this->assertSame('Return', PurchaseDocumentType::RETURN->value);
        $this->assertSame('City Notice', PurchaseDocumentType::DEBIT_NOTE->value);
    }

    #[Test]
    public function from_value_tolerates_the_casing_used_across_the_codebase(): void
    {
        // شيفرة قائمة تكتب 'return' بينما العمود 'Return'
        $this->assertSame(PurchaseDocumentType::RETURN, PurchaseDocumentType::fromValue('return'));
        $this->assertSame(PurchaseDocumentType::RETURN, PurchaseDocumentType::fromValue('Return'));
        $this->assertNull(PurchaseDocumentType::fromValue('nonsense'));
        $this->assertNull(PurchaseDocumentType::fromValue(null));
    }

    #[Test]
    public function documents_report_their_own_type(): void
    {
        $this->assertTrue($this->document('Requested')->isPurchaseOrder());
        $this->assertTrue($this->document('invoice')->isPurchaseInvoice());
        $this->assertTrue($this->document('Return')->isPurchaseReturn());
        $this->assertTrue($this->document('City Notice')->isDebitNote());

        $this->assertFalse($this->document('Requested')->isPurchaseInvoice());
    }

    // ==================== أمر الشراء ====================

    #[Test]
    public function a_purchase_order_is_not_payable_and_moves_nothing(): void
    {
        $order = $this->document('Requested');

        $this->assertFalse($order->isPayable());
        $this->assertFalse($order->affectsSupplierBalance());
        $this->assertFalse($order->affectsInventory());
        $this->assertFalse($order->requiresJournalEntry());
        $this->assertSame(0, $order->balanceDirection());
    }

    #[Test]
    public function a_purchase_order_never_reports_an_outstanding_amount(): void
    {
        // كانت الدالة القديمة تعيد grand_total فتظهر مديونية وهمية
        $order = $this->document('Requested', ['grand_total' => 5000]);

        $this->assertSame(0.0, Calc::remainingAmount($order));
        $this->assertNull(Calc::paymentStatus($order));
    }

    #[Test]
    public function update_payment_status_leaves_a_purchase_order_untouched(): void
    {
        $order = $this->document('Requested', ['payment_status' => null, 'due_value' => null]);

        $order->updatePaymentStatus();

        $this->assertNull($order->payment_status);
        $this->assertNull($order->due_value);
    }

    // ==================== فاتورة الشراء ====================

    #[Test]
    public function a_purchase_invoice_is_payable_and_moves_everything(): void
    {
        $invoice = $this->document('invoice');

        $this->assertTrue($invoice->isPayable());
        $this->assertTrue($invoice->affectsSupplierBalance());
        $this->assertTrue($invoice->affectsInventory());
        $this->assertTrue($invoice->requiresJournalEntry());
        $this->assertSame(1, $invoice->balanceDirection());
    }

    // ==================== المرتجع والإشعار المدين ====================

    #[Test]
    public function a_return_reverses_the_invoice_and_is_not_paid(): void
    {
        $return = $this->document('Return');

        $this->assertFalse($return->isPayable());
        $this->assertTrue($return->affectsSupplierBalance());
        $this->assertTrue($return->affectsInventory());
        $this->assertSame(-1, $return->balanceDirection());
        $this->assertTrue(Rules::requiresOriginal('Return'));
    }

    #[Test]
    public function a_debit_note_settles_money_without_touching_stock(): void
    {
        $note = $this->document('City Notice');

        $this->assertFalse($note->isPayable());
        $this->assertTrue($note->affectsSupplierBalance());
        $this->assertFalse($note->affectsInventory());
        $this->assertSame(-1, $note->balanceDirection());
    }

    // ==================== التحويل ====================

    #[Test]
    public function only_an_order_converts_into_an_invoice(): void
    {
        $this->assertTrue(Rules::canConvert('Requested', 'invoice'));

        $this->assertFalse(Rules::canConvert('Return', 'invoice'));
        $this->assertFalse(Rules::canConvert('invoice', 'Return'));
        $this->assertFalse(Rules::canConvert('City Notice', 'invoice'));
    }

    // ==================== نوع مجهول ====================

    #[Test]
    public function an_unknown_type_is_treated_as_inert(): void
    {
        // النوع المجهول لا يُحدث أثراً بالخطأ
        $odd = $this->document('something_else');

        $this->assertFalse($odd->isPayable());
        $this->assertFalse($odd->affectsInventory());
        $this->assertFalse($odd->requiresJournalEntry());
        $this->assertSame(0, $odd->balanceDirection());
    }

    #[Test]
    public function void_payment_statuses_are_documented(): void
    {
        // 5 = ملغية و3 = مسودة، كما توثّقها PurchasePaymentController
        $this->assertSame([3, 5], Calc::VOID_PAYMENT_STATUSES);
    }
}
