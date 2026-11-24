@extends('purchases::master')

@section('title', 'تعديل قالب الفاتورة')

@section('css')
<link href="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/theme/monokai.min.css" rel="stylesheet">
<style>
    .editor-container {
        border: 1px solid #ddd;
        border-radius: 5px;
        overflow: hidden;
    }
    .CodeMirror {
        height: 600px;
        font-size: 14px;
        direction: ltr;
        text-align: left;
    }
    .preview-frame {
        width: 100%;
        min-height: 600px;
        border: 1px solid #ddd;
        border-radius: 5px;
        background: white;
        padding: 20px;
    }
    .var-btn {
        margin: 3px;
        font-size: 11px;
        white-space: nowrap;
    }
    .section-title {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 8px 15px;
        border-radius: 5px;
        margin-bottom: 10px;
        font-size: 13px;
    }
    .nav-pills .nav-link {
        border-radius: 20px;
        margin: 0 5px;
        padding: 8px 20px;
    }
    .nav-pills .nav-link.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .preview-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 400px;
        background: #f8f9fa;
    }
    .preview-error {
        padding: 20px;
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 5px;
        margin: 20px;
    }
    .quick-section-card {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 10px;
        transition: all 0.3s ease;
    }
    .quick-section-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .quick-section-card .card-header {
        cursor: pointer;
        padding: 10px 15px;
        background: #f8f9fa;
        border-radius: 8px 8px 0 0;
    }
    .quick-section-card .card-body {
        padding: 10px 15px;
    }
    .live-preview-btn {
        position: fixed;
        bottom: 20px;
        left: 20px;
        z-index: 1000;
        padding: 15px 30px;
        font-size: 16px;
        border-radius: 50px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
</style>
@endsection

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded shadow-sm">
                <div>
                    <h4 class="mb-0"><i class="fas fa-edit text-primary"></i> تعديل قالب: {{ $template->name }}</h4>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-success" id="saveBtn">
                        <i class="fas fa-save"></i> حفظ التعديلات
                    </button>
                    <button type="button" class="btn btn-info" id="previewBtn">
                        <i class="fas fa-eye"></i> معاينة
                    </button>
                    <a href="{{ route('purchases.invoice_settings.bill_designs') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> عودة
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <ul class="nav nav-pills justify-content-center mb-3" id="editorTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="visual-tab" data-toggle="tab" href="#visual" role="tab">
                <i class="fas fa-paint-brush"></i> التعديل المرئي
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="code-tab" data-toggle="tab" href="#code" role="tab">
                <i class="fas fa-code"></i> تعديل الكود
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="preview-tab" data-toggle="tab" href="#preview" role="tab">
                <i class="fas fa-eye"></i> المعاينة المباشرة
            </a>
        </li>
    </ul>

    <form id="templateForm" action="{{ route('purchases.invoice_settings.templates.update', $template->id) }}" method="POST">
        @csrf
        @method('PUT')
        <input type="hidden" name="name" id="templateName" value="{{ $template->name }}">
        <input type="hidden" name="content" id="templateContent">

        <div class="tab-content" id="editorTabsContent">
            <!-- Visual Editor Tab -->
            <div class="tab-pane fade show active" id="visual" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-edit"></i> محرر الأقسام
                            </div>
                            <div class="card-body">
                                <!-- Template Name -->
                                <div class="form-group mb-4">
                                    <label><strong><i class="fas fa-tag"></i> اسم القالب</strong></label>
                                    <input type="text" class="form-control form-control-lg" id="visualTemplateName" value="{{ $template->name }}" placeholder="أدخل اسم القالب">
                                </div>

                                <!-- Header Section -->
                                <div class="quick-section-card">
                                    <div class="card-header" data-toggle="collapse" data-target="#headerSection">
                                        <i class="fas fa-chevron-down float-left"></i>
                                        <strong><i class="fas fa-heading text-primary"></i> رأس الفاتورة (Header)</strong>
                                    </div>
                                    <div class="collapse show" id="headerSection">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>عنوان الفاتورة</label>
                                                        <input type="text" class="form-control template-field" data-field="header_title" value="فاتورة شراء">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>اللون الرئيسي</label>
                                                        <input type="color" class="form-control template-field" data-field="primary_color" value="#007bff" style="height: 40px;">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showLogo" checked data-target=".logo-section">
                                                <label class="form-check-label" for="showLogo">إظهار شعار الشركة</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Company Info Section -->
                                <div class="quick-section-card">
                                    <div class="card-header" data-toggle="collapse" data-target="#companySection">
                                        <i class="fas fa-chevron-down float-left"></i>
                                        <strong><i class="fas fa-building text-info"></i> معلومات الشركة</strong>
                                    </div>
                                    <div class="collapse show" id="companySection">
                                        <div class="card-body">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showCompanyName" checked data-target=".company-name">
                                                <label class="form-check-label" for="showCompanyName">اسم الشركة</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showCompanyAddress" checked data-target=".company-address">
                                                <label class="form-check-label" for="showCompanyAddress">عنوان الشركة</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showCompanyPhone" checked data-target=".company-phone">
                                                <label class="form-check-label" for="showCompanyPhone">هاتف الشركة</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showCompanyVat" checked data-target=".company-vat">
                                                <label class="form-check-label" for="showCompanyVat">الرقم الضريبي للشركة</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Supplier Info Section -->
                                <div class="quick-section-card">
                                    <div class="card-header" data-toggle="collapse" data-target="#supplierSection">
                                        <i class="fas fa-chevron-down float-left"></i>
                                        <strong><i class="fas fa-user-tie text-success"></i> معلومات المورد</strong>
                                    </div>
                                    <div class="collapse show" id="supplierSection">
                                        <div class="card-body">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showSupplierName" checked data-target=".supplier-name">
                                                <label class="form-check-label" for="showSupplierName">اسم المورد</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showSupplierAddress" checked data-target=".supplier-address">
                                                <label class="form-check-label" for="showSupplierAddress">عنوان المورد</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showSupplierPhone" checked data-target=".supplier-phone">
                                                <label class="form-check-label" for="showSupplierPhone">هاتف المورد</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showSupplierVat" checked data-target=".supplier-vat">
                                                <label class="form-check-label" for="showSupplierVat">الرقم الضريبي للمورد</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Invoice Details Section -->
                                <div class="quick-section-card">
                                    <div class="card-header" data-toggle="collapse" data-target="#invoiceSection">
                                        <i class="fas fa-chevron-down float-left"></i>
                                        <strong><i class="fas fa-file-invoice text-warning"></i> تفاصيل الفاتورة</strong>
                                    </div>
                                    <div class="collapse show" id="invoiceSection">
                                        <div class="card-body">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showInvoiceNumber" checked data-target=".invoice-number">
                                                <label class="form-check-label" for="showInvoiceNumber">رقم الفاتورة</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showInvoiceDate" checked data-target=".invoice-date">
                                                <label class="form-check-label" for="showInvoiceDate">تاريخ الفاتورة</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showDueDate" checked data-target=".due-date">
                                                <label class="form-check-label" for="showDueDate">تاريخ الاستحقاق</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showPaymentStatus" checked data-target=".payment-status">
                                                <label class="form-check-label" for="showPaymentStatus">حالة الدفع</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Items Table Section -->
                                <div class="quick-section-card">
                                    <div class="card-header" data-toggle="collapse" data-target="#itemsSection">
                                        <i class="fas fa-chevron-down float-left"></i>
                                        <strong><i class="fas fa-table text-danger"></i> جدول المنتجات</strong>
                                    </div>
                                    <div class="collapse show" id="itemsSection">
                                        <div class="card-body">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showProductName" checked data-target=".product-name">
                                                <label class="form-check-label" for="showProductName">اسم المنتج</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showQuantity" checked data-target=".quantity">
                                                <label class="form-check-label" for="showQuantity">الكمية</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showUnitPrice" checked data-target=".unit-price">
                                                <label class="form-check-label" for="showUnitPrice">سعر الوحدة</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showItemTotal" checked data-target=".item-total">
                                                <label class="form-check-label" for="showItemTotal">إجمالي الصنف</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showItemTax" checked data-target=".item-tax">
                                                <label class="form-check-label" for="showItemTax">الضريبة لكل صنف</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Totals Section -->
                                <div class="quick-section-card">
                                    <div class="card-header" data-toggle="collapse" data-target="#totalsSection">
                                        <i class="fas fa-chevron-down float-left"></i>
                                        <strong><i class="fas fa-calculator text-purple"></i> المجاميع</strong>
                                    </div>
                                    <div class="collapse show" id="totalsSection">
                                        <div class="card-body">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showSubTotal" checked data-target=".sub-total">
                                                <label class="form-check-label" for="showSubTotal">المجموع الفرعي</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showTotalTax" checked data-target=".total-tax">
                                                <label class="form-check-label" for="showTotalTax">إجمالي الضريبة</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showDiscount" checked data-target=".discount">
                                                <label class="form-check-label" for="showDiscount">الخصم</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showGrandTotal" checked data-target=".grand-total">
                                                <label class="form-check-label" for="showGrandTotal">الإجمالي الكلي</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer Section -->
                                <div class="quick-section-card">
                                    <div class="card-header" data-toggle="collapse" data-target="#footerSection">
                                        <i class="fas fa-chevron-down float-left"></i>
                                        <strong><i class="fas fa-shoe-prints text-secondary"></i> ذيل الفاتورة (Footer)</strong>
                                    </div>
                                    <div class="collapse show" id="footerSection">
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label>نص الشكر</label>
                                                <input type="text" class="form-control template-field" data-field="footer_text" value="شكراً لتعاملكم معنا">
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showNotes" checked data-target=".notes">
                                                <label class="form-check-label" for="showNotes">الملاحظات</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showQRCode" checked data-target=".qr-code">
                                                <label class="form-check-label" for="showQRCode">رمز QR</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input template-toggle" type="checkbox" id="showSignature" checked data-target=".signature">
                                                <label class="form-check-label" for="showSignature">التوقيع والختم</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Side Panel - Variables -->
                    <div class="col-md-4">
                        <div class="card sticky-top" style="top: 20px;">
                            <div class="card-header bg-dark text-white">
                                <i class="fas fa-code"></i> المتغيرات الجاهزة
                            </div>
                            <div class="card-body" style="max-height: 500px; overflow-y: auto;">

                                <div class="section-title"><i class="fas fa-building"></i> الشركة</div>
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-primary btn-sm var-btn" data-var="@{{ $company->name }}">اسم الشركة</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm var-btn" data-var="@{{ $company->address }}">العنوان</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm var-btn" data-var="@{{ $company->phone }}">الهاتف</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm var-btn" data-var="@{{ $company->vat_number }}">الرقم الضريبي</button>
                                </div>

                                <div class="section-title"><i class="fas fa-user-tie"></i> المورد</div>
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-success btn-sm var-btn" data-var="@{{ $invoice->supplier->trade_name }}">اسم المورد</button>
                                    <button type="button" class="btn btn-outline-success btn-sm var-btn" data-var="@{{ $invoice->supplier->name }}">الاسم</button>
                                    <button type="button" class="btn btn-outline-success btn-sm var-btn" data-var="@{{ $invoice->supplier->address }}">العنوان</button>
                                    <button type="button" class="btn btn-outline-success btn-sm var-btn" data-var="@{{ $invoice->supplier->phone }}">الهاتف</button>
                                    <button type="button" class="btn btn-outline-success btn-sm var-btn" data-var="@{{ $invoice->supplier->email }}">البريد</button>
                                    <button type="button" class="btn btn-outline-success btn-sm var-btn" data-var="@{{ $invoice->supplier->vat_number }}">الرقم الضريبي</button>
                                </div>

                                <div class="section-title"><i class="fas fa-file-invoice"></i> الفاتورة</div>
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-warning btn-sm var-btn" data-var="@{{ $invoice->invoice_number }}">رقم الفاتورة</button>
                                    <button type="button" class="btn btn-outline-warning btn-sm var-btn" data-var="@{{ $invoice->invoice_date->format('Y-m-d') }}">التاريخ</button>
                                    <button type="button" class="btn btn-outline-warning btn-sm var-btn" data-var="@{{ $invoice->due_date->format('Y-m-d') }}">تاريخ الاستحقاق</button>
                                    <button type="button" class="btn btn-outline-warning btn-sm var-btn" data-var="@{{ $invoice->payment_status }}">حالة الدفع</button>
                                    <button type="button" class="btn btn-outline-warning btn-sm var-btn" data-var="@{{ $invoice->notes }}">الملاحظات</button>
                                </div>

                                <div class="section-title"><i class="fas fa-calculator"></i> المبالغ</div>
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-danger btn-sm var-btn" data-var="@{{ number_format($invoice->sub_total, 2) }}">المجموع الفرعي</button>
                                    <button type="button" class="btn btn-outline-danger btn-sm var-btn" data-var="@{{ number_format($invoice->total_tax, 2) }}">الضريبة</button>
                                    <button type="button" class="btn btn-outline-danger btn-sm var-btn" data-var="@{{ number_format($invoice->discount, 2) }}">الخصم</button>
                                    <button type="button" class="btn btn-outline-danger btn-sm var-btn" data-var="@{{ number_format($invoice->grand_total, 2) }}">الإجمالي</button>
                                </div>

                                <div class="section-title"><i class="fas fa-boxes"></i> المنتجات (داخل @@foreach)</div>
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-info btn-sm var-btn" data-var="@{{ $item->product_name }}">اسم المنتج</button>
                                    <button type="button" class="btn btn-outline-info btn-sm var-btn" data-var="@{{ $item->quantity }}">الكمية</button>
                                    <button type="button" class="btn btn-outline-info btn-sm var-btn" data-var="@{{ number_format($item->unit_price, 2) }}">السعر</button>
                                    <button type="button" class="btn btn-outline-info btn-sm var-btn" data-var="@{{ number_format($item->total, 2) }}">الإجمالي</button>
                                    <button type="button" class="btn btn-outline-info btn-sm var-btn" data-var="@{{ number_format($item->tax, 2) }}">الضريبة</button>
                                </div>

                                <div class="alert alert-info small mt-3">
                                    <i class="fas fa-lightbulb"></i> <strong>نصيحة:</strong> اضغط على أي متغير لنسخه، ثم الصقه في محرر الكود.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Code Editor Tab -->
            <div class="tab-pane fade" id="code" role="tabpanel">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-code"></i> محرر الكود (HTML/Blade)</span>
                                <div>
                                    <button type="button" class="btn btn-sm btn-warning" id="formatCode">
                                        <i class="fas fa-indent"></i> تنسيق الكود
                                    </button>
                                    <button type="button" class="btn btn-sm btn-info" id="resetCode">
                                        <i class="fas fa-undo"></i> استعادة الأصلي
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="editor-container">
                                    <textarea id="codeEditor">{!! htmlspecialchars($template->content) !!}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Tab -->
            <div class="tab-pane fade" id="preview" role="tabpanel">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-eye"></i> المعاينة المباشرة</span>
                                <div>
                                    <button type="button" class="btn btn-sm btn-light" id="refreshPreview">
                                        <i class="fas fa-sync-alt"></i> تحديث المعاينة
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning" id="printPreview">
                                        <i class="fas fa-print"></i> طباعة
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="previewArea" class="preview-frame">
                                    <div class="preview-loading">
                                        <div class="text-center">
                                            <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                                            <p class="text-muted">اضغط على "تحديث المعاينة" لعرض الفاتورة</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Floating Preview Button -->
<button type="button" class="btn btn-primary live-preview-btn" id="floatingPreviewBtn">
    <i class="fas fa-eye"></i> معاينة سريعة
</button>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-eye"></i> معاينة الفاتورة</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div id="modalPreviewArea" class="p-4" style="background: #f5f5f5; min-height: 500px;">
                    <!-- Preview content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" id="modalPrintBtn">
                    <i class="fas fa-print"></i> طباعة
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<!-- Notification Toast -->
<div id="notification" class="position-fixed" style="top: 20px; right: 20px; z-index: 9999; display: none;">
    <div class="alert alert-success alert-dismissible fade show shadow" role="alert">
        <i class="fas fa-check-circle"></i> <span id="notification-text"></span>
        <button type="button" class="close" onclick="hideNotification()">
            <span>&times;</span>
        </button>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/css/css.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/addon/edit/closetag.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/addon/fold/foldcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/addon/fold/foldgutter.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/addon/fold/xml-fold.min.js"></script>
<script>
    // Store original content for reset
    const originalContent = @json($template->content);
    const defaultContent = @json($template->default_content ?? $template->content);

    // Initialize CodeMirror Editor
    let codeEditor = CodeMirror.fromTextArea(document.getElementById('codeEditor'), {
        mode: 'htmlmixed',
        theme: 'monokai',
        lineNumbers: true,
        lineWrapping: true,
        autoCloseTags: true,
        matchBrackets: true,
        indentUnit: 4,
        tabSize: 4,
        foldGutter: true,
        gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"]
    });

    // Set initial content
    codeEditor.setValue(originalContent);

    // Sync template name
    document.getElementById('visualTemplateName').addEventListener('input', function() {
        document.getElementById('templateName').value = this.value;
    });

    // Copy variable to clipboard
    function copyVar(varText) {
        navigator.clipboard.writeText(varText).then(() => {
            showNotification('تم نسخ المتغير بنجاح!');
        });
    }

    /* الرمز يُقرأ من `data-var` لا من وسيطٍ داخل `onclick`.

       رموز هذه اللوحة نصٌّ يُنسَخ إلى محرّر القالب، لا تعبيراتٌ تُقيَّم —
       فـ`$company` و`$invoice` و`$item` غير معرَّفة في هذه الشاشة أصلاً.
       وبعضها يحوي علامات اقتباس مفردة تُنهي نصّ JS داخل `onclick`، فكانت
       مهروبةً هروباً لا يفهمه PHP فيسقط الملف كلُّه بخطأ صياغة قبل أن
       يُعرض. صارت الرموز مهروبةً لبليد نفسه، وتُقرأ من سمة `data-var`
       التي يحدّها `"` فتحتمل المفردة بلا هروب. */
    document.addEventListener('click', function (event) {
        const button = event.target.closest('.var-btn[data-var]');

        if (button) copyVar(button.dataset.var);
    });

    // Show notification
    function showNotification(message, type = 'success') {
        const notification = document.getElementById('notification');
        const notificationText = document.getElementById('notification-text');
        const alertDiv = notification.querySelector('.alert');

        alertDiv.className = `alert alert-${type} alert-dismissible fade show shadow`;
        notificationText.textContent = message;
        notification.style.display = 'block';

        setTimeout(hideNotification, 3000);
    }

    function hideNotification() {
        document.getElementById('notification').style.display = 'none';
    }

    // Get current content from editor
    function getCurrentContent() {
        return codeEditor.getValue();
    }

    // Preview function
    function loadPreview(targetElement) {
        const content = getCurrentContent();
        targetElement.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x text-primary"></i><br><br>جاري تحميل المعاينة...</div>';

        fetch('{{ route("purchases.invoice_settings.template.preview") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                content: content,
                _token: '{{ csrf_token() }}'
            })
        })
        .then(async response => {
            const data = await response.json();
            if (data.html) {
                targetElement.innerHTML = '<div class="bg-white p-4 shadow-sm" style="border-radius: 10px;">' + data.html + '</div>';
            } else if (data.error) {
                throw new Error(data.error);
            } else {
                throw new Error('لم يتم استلام بيانات المعاينة');
            }
        })
        .catch(error => {
            console.error('Preview error:', error);
            targetElement.innerHTML = `
                <div class="preview-error">
                    <h5><i class="fas fa-exclamation-triangle text-danger"></i> خطأ في المعاينة</h5>
                    <p class="mb-2">${error.message}</p>
                    <small class="text-muted">تأكد من صحة كود القالب (Blade syntax)</small>
                </div>
            `;
        });
    }

    // Preview button in header
    document.getElementById('previewBtn').addEventListener('click', function() {
        $('#previewModal').modal('show');
        loadPreview(document.getElementById('modalPreviewArea'));
    });

    // Floating preview button
    document.getElementById('floatingPreviewBtn').addEventListener('click', function() {
        $('#previewModal').modal('show');
        loadPreview(document.getElementById('modalPreviewArea'));
    });

    // Refresh preview in tab
    document.getElementById('refreshPreview').addEventListener('click', function() {
        loadPreview(document.getElementById('previewArea'));
    });

    // When preview tab is shown, load preview
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        if (e.target.id === 'preview-tab') {
            loadPreview(document.getElementById('previewArea'));
        }
        if (e.target.id === 'code-tab') {
            setTimeout(() => codeEditor.refresh(), 100);
        }
    });

    // Save button
    document.getElementById('saveBtn').addEventListener('click', function() {
        const content = getCurrentContent();
        const name = document.getElementById('visualTemplateName').value || document.getElementById('templateName').value;

        document.getElementById('templateContent').value = content;
        document.getElementById('templateName').value = name;

        // Show saving notification
        showNotification('جاري الحفظ...', 'info');

        // Submit form
        document.getElementById('templateForm').submit();
    });

    // Reset code to original
    document.getElementById('resetCode').addEventListener('click', function() {
        if (confirm('هل أنت متأكد من استعادة الكود الأصلي؟ سيتم فقدان جميع التغييرات.')) {
            codeEditor.setValue(defaultContent || originalContent);
            showNotification('تم استعادة الكود الأصلي');
        }
    });

    // Format code (basic formatting)
    document.getElementById('formatCode').addEventListener('click', function() {
        let content = codeEditor.getValue();
        // Simple formatting - you might want to use a proper HTML formatter library
        content = content.replace(/></g, '>\n<');
        codeEditor.setValue(content);
        showNotification('تم تنسيق الكود');
    });

    // Print functions
    document.getElementById('printPreview').addEventListener('click', printInvoice);
    document.getElementById('modalPrintBtn').addEventListener('click', printInvoice);

    function printInvoice() {
        const previewContent = document.getElementById('modalPreviewArea').innerHTML || document.getElementById('previewArea').innerHTML;
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html dir="rtl" lang="ar">
            <head>
                <meta charset="UTF-8">
                <title>طباعة الفاتورة</title>
                <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
                <style>
                    body { font-family: 'Tajawal', Arial, sans-serif; direction: rtl; padding: 20px; }
                    @media print { body { padding: 0; } }
                </style>
            </head>
            <body>${previewContent}</body>
            </html>
        `);
        printWindow.document.close();
        setTimeout(() => {
            printWindow.print();
        }, 500);
    }

    // Auto-save reminder
    let hasChanges = false;
    codeEditor.on('change', function() {
        hasChanges = true;
    });

    window.addEventListener('beforeunload', function(e) {
        if (hasChanges) {
            e.preventDefault();
            e.returnValue = 'لديك تغييرات غير محفوظة. هل أنت متأكد من المغادرة؟';
        }
    });

    // Reset hasChanges on form submit
    document.getElementById('templateForm').addEventListener('submit', function() {
        hasChanges = false;
    });
</script>
@endsection
