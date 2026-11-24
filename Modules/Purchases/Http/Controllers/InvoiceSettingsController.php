<?php

namespace Modules\Purchases\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PurchaseInvoiceSetting;
use App\Models\Template;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

class InvoiceSettingsController extends Controller
{
    /*
     * لم يكن هذا الكنترولر محروساً بأي صلاحية إطلاقاً رغم أن مجموعة
     * `settings` في كتالوج `PurchasesPermissions` تُشير إليه صراحةً (إلى
     * جانب PurchaseSettingsController). المجموعة تملك صلاحيتين فقط
     * (عرض/تعديل)، فقوالب المستندات تخضع لهما: العرض والمعاينة `view`،
     * وإنشاء/تعديل/حفظ القالب `edit`.
     */
    public function __construct()
    {
        $this->middleware('permission:purchases.settings.view', ['only' => ['bill_designs', 'preview']]);
        $this->middleware('permission:purchases.settings.edit', ['only' => ['create', 'store', 'edit', 'update_template']]);
    }

    private function getSettings()
    {
        return PurchaseInvoiceSetting::ordered()->get();
    }

    public function create()
    {
        $settings = $this->getSettings();

        return view('purchases::purchases.templates.create', compact('settings'));
    }

    // --- Template Management Methods ---

    public function bill_designs()
    {
        $settings = $this->getSettings();
        // Fetch templates specifically for purchase invoices
        $templates = Template::where('type', 'purchase_invoice')->get();

        // If no templates exist, create a default one (optional, but good for UX)
        if ($templates->isEmpty()) {
            $defaultContent = $this->getDefaultTemplateContent();
            Template::create([
                'name' => 'القالب الافتراضي',
                'type' => 'purchase_invoice',
                'content' => json_encode([
                    'layout' => 'classic',
                    'primary_color' => '#007bff',
                    'font_family' => 'Tajawal',
                    'show_logo' => true,
                    'show_vat' => true,
                    'header_text' => 'فاتورة شراء',
                    'footer_text' => 'شكراً لتعاملكم معنا',
                ]),
                'default_content' => json_encode([
                    'layout' => 'classic',
                    'primary_color' => '#007bff',
                    'font_family' => 'Tajawal',
                    'show_logo' => true,
                    'show_vat' => true,
                    'header_text' => 'فاتورة شراء',
                    'footer_text' => 'شكراً لتعاملكم معنا',
                ]),
                'type' => 'purchase_invoice',
                'is_default' => true,
            ]);
            $templates = Template::where('type', 'purchase_invoice')->get();
        }

        return view('purchases::purchases.templates.index', compact('templates', 'settings'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'layout' => 'required|in:classic,modern,simple,thermal',
        ]);

        $layoutFile = module_path('Purchases', "resources/views/purchases/templates/layouts/{$request->layout}.blade.php");

        if (! file_exists($layoutFile)) {
            return redirect()->back()->with('error', 'ملف التنسيق غير موجود');
        }

        $content = file_get_contents($layoutFile);

        Template::create([
            'name' => $request->name,
            'type' => 'purchase_invoice',
            'content' => $content,
            'default_content' => $content,
            'is_default' => false,
        ]);

        return redirect()->route('purchases.invoice_settings.bill_designs')
            ->with('success', 'تم إنشاء القالب بنجاح');
    }

    public function edit(Template $template)
    {
        $settings = $this->getSettings();

        return view('purchases::purchases.templates.edit', compact('template', 'settings'));
    }

    public function update_template(Request $request, Template $template)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required',
        ]);

        $template->update([
            'name' => $request->name,
            'content' => $request->content,
        ]);

        return redirect()->back()->with('success', 'تم حفظ التغييرات بنجاح');
    }

    public function preview(Request $request)
    {
        try {
            $content = $request->input('content');

            // Generate Dummy Data
            $data = $this->generateDummyData();

            // Render the raw Blade content
            $html = Blade::render($content, $data);

            return response()->json(['html' => $html]);

        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.InvoiceSettings', 'preview');

            return response()->json([
                'error' => 'خطأ في المعاينة (تأكد من صحة كود القالب): '.$e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    private function generateDummyData()
    {
        // Create comprehensive dummy data structure matching a Purchase Invoice
        return [
            'invoice' => (object) [
                'id' => rand(1000, 9999),
                'invoice_date' => now(),
                'due_date' => now()->addDays(30),
                'invoice_number' => 'INV-PUR-'.rand(1000, 9999),
                'reference_number' => 'REF-'.rand(100, 999),
                'supplier' => (object) [
                    'id' => rand(1, 100),
                    'name' => 'محمد أحمد الشريف',
                    'trade_name' => 'شركة المورد المتحدة للتجارة',
                    'phone' => '0501234567',
                    'mobile' => '0551234567',
                    'email' => 'supplier@example.com',
                    'address' => 'الرياض - حي الملز - شارع الجامعة',
                    'city' => 'الرياض',
                    'country' => 'المملكة العربية السعودية',
                    'vat_number' => '300000000000003',
                    'cr_number' => '1010123456',
                    'postal_code' => '12345',
                    'website' => 'www.supplier.com',
                ],
                'items' => collect([
                    (object) [
                        'id' => 1,
                        'product_name' => 'لابتوب Dell Inspiron 15',
                        'product_code' => 'DELL-INS-15',
                        'description' => 'لابتوب ديل انسبيرون 15 بوصة',
                        'quantity' => 5,
                        'unit' => 'قطعة',
                        'unit_price' => 3500,
                        'discount' => 0,
                        'tax_rate' => 15,
                        'tax' => 2625,
                        'total' => 17500,
                        'total_with_tax' => 20125,
                    ],
                    (object) [
                        'id' => 2,
                        'product_name' => 'طابعة HP LaserJet Pro',
                        'product_code' => 'HP-LJ-PRO',
                        'description' => 'طابعة ليزر احترافية',
                        'quantity' => 3,
                        'unit' => 'قطعة',
                        'unit_price' => 1200,
                        'discount' => 100,
                        'tax_rate' => 15,
                        'tax' => 495,
                        'total' => 3500,
                        'total_with_tax' => 3995,
                    ],
                    (object) [
                        'id' => 3,
                        'product_name' => 'ماوس لاسلكي Logitech',
                        'product_code' => 'LOG-MX-M',
                        'description' => 'ماوس لاسلكي متعدد الاستخدام',
                        'quantity' => 20,
                        'unit' => 'قطعة',
                        'unit_price' => 150,
                        'discount' => 0,
                        'tax_rate' => 15,
                        'tax' => 450,
                        'total' => 3000,
                        'total_with_tax' => 3450,
                    ],
                ]),
                'sub_total' => 24000,
                'total_discount' => 100,
                'taxable_amount' => 23900,
                'total_tax' => 3570,
                'shipping_cost' => 0,
                'grand_total' => 27570,
                'amount_paid' => 10000,
                'amount_due' => 17570,
                'notes' => 'يرجى تسليم البضاعة إلى المستودع الرئيسي. الدفع خلال 30 يوم من تاريخ الاستلام.',
                'terms' => 'الضمان: سنة واحدة على جميع المنتجات الإلكترونية',
                'payment_status' => 'partial',
                'payment_method' => 'تحويل بنكي',
                'warehouse' => 'المستودع الرئيسي - الرياض',
                'created_by' => 'أحمد محمد',
                'approved_by' => 'سعيد خالد',
            ],
            'company' => (object) [
                'id' => 1,
                'name' => 'شركة التقنية المتقدمة',
                'trade_name' => 'التقنية المتقدمة',
                'address' => 'جدة - حي التحلية - برج المملكة - الدور 15',
                'city' => 'جدة',
                'country' => 'المملكة العربية السعودية',
                'phone' => '920001234',
                'mobile' => '0500001234',
                'fax' => '0126001234',
                'email' => 'info@company.com',
                'website' => 'www.company.com',
                'vat_number' => '311111111111113',
                'cr_number' => '4030123456',
                'postal_code' => '21452',
                'logo' => asset('images/logo.png'),
                'bank_name' => 'البنك الأهلي السعودي',
                'bank_account' => 'SA12345678901234567890',
                'iban' => 'SA0380000000608010167519',
            ],
            'today' => now()->format('Y-m-d'),
            'current_time' => now()->format('H:i:s'),
            'currency' => 'ريال',
            'currency_symbol' => 'ر.س',
        ];
    }

    private function getDefaultTemplateContent()
    {
        return '
        <div style="font-family: Arial, sans-serif; direction: rtl; text-align: right; border: 1px solid #ddd; padding: 20px;">
            <div style="text-align: center; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px;">
                <h1>فاتورة شراء</h1>
                <h3>{{ $company->name }}</h3>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
                <div>
                    <strong>رقم الفاتورة:</strong> {{ $invoice->invoice_number }}<br>
                    <strong>تاريخ الفاتورة:</strong> {{ $invoice->invoice_date->format("Y-m-d") }}<br>
                </div>
                <div>
                    <strong>المورد:</strong> {{ $invoice->supplier->trade_name }}<br>
                    <strong>الرقم الضريبي:</strong> {{ $invoice->supplier->vat_number }}<br>
                </div>
            </div>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <thead>
                    <tr style="background-color: #f9f9f9;">
                        <th style="border: 1px solid #ddd; padding: 10px;">المنتج</th>
                        <th style="border: 1px solid #ddd; padding: 10px;">الكمية</th>
                        <th style="border: 1px solid #ddd; padding: 10px;">سعر الوحدة</th>
                        <th style="border: 1px solid #ddd; padding: 10px;">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $item)
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 10px;">{{ $item->product_name }}</td>
                        <td style="border: 1px solid #ddd; padding: 10px;">{{ $item->quantity }}</td>
                        <td style="border: 1px solid #ddd; padding: 10px;">{{ number_format($item->unit_price, 2) }}</td>
                        <td style="border: 1px solid #ddd; padding: 10px;">{{ number_format($item->total, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="text-align: left; margin-top: 20px;">
                <p><strong>المجموع الفرعي:</strong> {{ number_format($invoice->sub_total, 2) }}</p>
                <p><strong>الضريبة:</strong> {{ number_format($invoice->total_tax, 2) }}</p>
                <h3><strong>الإجمالي الكلي:</strong> {{ number_format($invoice->grand_total, 2) }}</h3>
            </div>
        </div>';
    }
}
