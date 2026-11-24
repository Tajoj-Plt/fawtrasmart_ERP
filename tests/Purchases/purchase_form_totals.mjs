/**
 * فوتر إجماليات مستندات الشراء — يجب أن يطابق ما يحفظه الخادم.
 *
 * يُشغَّل السكربت الحقيقي (`public/assets/js/purchase-orders-form.js`) على
 * نسخةٍ مصغَّرة من ترميز `partials/form.blade.php` داخل jsdom، فما يُختبَر
 * هو الشيفرة التي تعمل في المتصفّح لا محاكاةٌ لها.
 *
 *   node tests/Purchases/purchase_form_totals.mjs
 *
 * العطلُ الذي يغطّيه — من لقطة المستخدم (بند ١٠٠٠×٢٫٠٠ بضريبة ١٥٪، خصم
 * عامّ ٢٠، شحن ١٠٠):
 *   • ضريبةُ الشحن (١٥) لم تكن تظهر في الفوتر ولا تدخل الإجمالي.
 *   • الخصمُ العامّ لم يكن يُنقص وعاءَ الضريبة، فتظهر ٣٠٠ بدل ٢٩٧.
 * فكان الفوتر يقول ٢٣٨٠ والخادم يحفظ ٢٣٩٢.
 */

import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

let pass = 0;
let fail = 0;

const check = (label, expected, actual) => {
    const ok = Math.abs(Number(expected) - Number(actual)) < 0.005;
    ok ? pass++ : fail++;
    console.log(ok ? `  [ok]   ${label}: ${actual}` : `  [FAIL] ${label}: expected ${expected}, got ${actual}`);
};

/** صفُّ بندٍ واحد + تبويبة التسوية + صندوق الإجماليات — أسماءُ الحقول كما في القالب */
/** صفُّ بندٍ واحد + تبويبة التسوية + صندوق الإجماليات.

    الحقولُ تُلتقط بـ`data-field` لا بـ`name` (انظر `field()` في السكربت)،
    و`#tableBody` و`#rowTemplate` شرطُ تشغيله: بدونهما يعود باكراً. */
const html = `<!doctype html><html><body>
<form id="purchaseOrderForm">
  <!-- الجسمُ يبدأ فارغاً: التهيئة تُنشئ البندَ الوحيد من القالب أدناه،
       فلا يجتمع صفٌّ ثابتٌ وآخرُ مستنسَخ -->
  <table id="productsTable"><tbody id="tableBody"></tbody></table>

  <!-- التهيئة تستنسخ القالب لبندٍ أوّل، فلا بدّ أن يحمل حقولَ الصفّ وزرّ الحذف -->
  <template id="rowTemplate"><tr class="po-row">
      <td class="po-row__index"></td>
      <td><select data-field="product_id"><option value="" selected></option></select>
          <select class="po-unit"><option value="" selected></option></select></td>
      <td><input data-field="quantity" value="1000"></td>
      <td><input data-field="unit_price" value="2.00"></td>
      <td><input data-field="discount" value="0">
          <select data-field="discount_type"><option value="amount" selected>amount</option></select></td>
      <td><select data-field="tax_1_id">
            <option value="" data-rate="0" data-included="0"></option>
            <option value="2" data-rate="15" data-included="0" selected>VAT</option>
          </select></td>
      <td><select class="po-tax2" data-field="tax_2_id">
            <option value="" data-rate="0" data-included="0" selected></option>
          </select></td>
      <td class="po-row__total"></td>
      <td><button type="button" class="po-row__remove"></button></td>
  </tr></template>

  <!-- الزرّان يُربطان بلا حارس في السكربت، فغيابُهما يرمي قبل أوّل حساب -->
  <button type="button" id="addRowBtn"></button>
  <button type="button" id="cancelBtn"></button>

  <input class="po-settle" name="discount_amount" value="20">
  <select class="po-settle" name="discount_type"><option value="amount" selected>amount</option></select>
  <input class="po-settle" name="shipping_cost" id="shipping_cost" value="100">
  <select class="po-settle" name="tax_id" id="tax_id">
    <option value="" data-rate="0"></option>
    <option value="2" data-rate="15" selected>VAT</option>
  </select>
  <input class="po-settle" name="adjustment_value" value="0">
  <select class="po-settle" name="adjustment_type"><option value="discount" selected>discount</option></select>

  <div class="po-totals">
    <div class="po-totals__row"><span data-total="subtotal"></span></div>
    <div class="po-totals__row d-none" data-total-row="discount"><span data-total="discount"></span></div>
    <div class="po-totals__row d-none" data-total-row="shipping"><span data-total="shipping"></span></div>
    <div class="po-totals__row d-none" data-total-row="tax"><span data-total="tax"></span></div>
    <div class="po-totals__row d-none" data-total-row="shipping_tax"><span data-total="shipping_tax"></span></div>
    <div class="po-totals__row"><span data-total="grand"></span></div>
  </div>
</form></body></html>`;

const dom = new JSDOM(html, { runScripts: 'outside-only', pretendToBeVisual: true });
const { window } = dom;

window.purchaseOrderForm = {
    settlement: true,
    payment: false,
    refund: false,
    prices: true,
    taxIdField: 'tax_1_id',
    secondTaxIdField: 'tax_2_id',
    currency: { code: 'SAR', symbol: 'SAR', decimals: 2, thousands: ',', decimal: '.' },
    rows: [{}],   // بندٌ واحد: يمنع التهيئة من إضافة صفٍّ ثانٍ فوق الصفّ الثابت
    units: {},
    i18n: {},
};

window.eval(readFileSync('public/assets/js/purchase-orders-form.js', 'utf8'));

const numberOf = (key) => {
    const el = window.document.querySelector(`[data-total="${key}"]`);
    return parseFloat((el?.textContent ?? '').replace(/[^\d.-]/g, '')) || 0;
};
const rowVisible = (key) =>
    !window.document.querySelector(`[data-total-row="${key}"]`)?.classList.contains('d-none');

console.log('\n═══ أرقام لقطة المستخدم: بند 1000×2.00 ضريبة 15٪، خصم عامّ 20، شحن 100 ═══\n');

for (const key of ['subtotal', 'discount', 'shipping', 'tax', 'shipping_tax', 'grand']) {
    console.log(`    ${key.padEnd(14)} ${numberOf(key)}`);
}
console.log('');

// القيم المرجعية من PurchaseDocumentTotals::compute() على نفس المدخلات
check('المجموع قبل الضريبة', 2000, numberOf('subtotal'));
check('إجمالي الخصم', 20, numberOf('discount'));
check('الشحن', 100, numberOf('shipping'));
check('ضريبة البنود بعد الخصم العامّ', 297, numberOf('tax'));
check('ضريبة الشحن', 15, numberOf('shipping_tax'));
check('الإجمالي النهائي', 2392, numberOf('grand'));
check('سطر ضريبة الشحن ظاهر', 1, rowVisible('shipping_tax') ? 1 : 0);

// بلا شحن: لا سطرَ ضريبة شحن ولا أثر على الإجمالي
window.document.getElementById('shipping_cost').value = '0';
window.document.getElementById('shipping_cost').dispatchEvent(new window.Event('input'));

console.log('\n═══ بعد تصفير الشحن ═══\n');
check('ضريبة الشحن صفر', 0, numberOf('shipping_tax'));
check('سطر ضريبة الشحن مخفيّ', 0, rowVisible('shipping_tax') ? 1 : 0);
check('الإجمالي = 2000 − 20 + 297', 2277, numberOf('grand'));

// «اختر الضريبة» فارغاً ⇒ تتبع ضريبةَ البنود (15٪) كما يقرؤها الخادم
window.document.getElementById('shipping_cost').value = '100';
window.document.getElementById('tax_id').value = '';
window.document.getElementById('tax_id').dispatchEvent(new window.Event('change'));

console.log('\n═══ منتقي ضريبة الشحن فارغ ⇒ كما البنود ═══\n');
check('ضريبة الشحن تتبع البنود', 15, numberOf('shipping_tax'));

console.log('\n───────────────────────────────');
console.log(`  نجح ${pass}   أخفق ${fail}`);
console.log('───────────────────────────────\n');

process.exit(fail === 0 ? 0 : 1);
