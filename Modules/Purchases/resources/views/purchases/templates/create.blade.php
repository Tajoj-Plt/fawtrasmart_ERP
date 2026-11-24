@extends('purchases::master')

@section('title')
  إضافة قالب جديد
@endsection

@section('css')
<style>
    .layout-card {
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid transparent;
    }
    .layout-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .layout-card.selected {
        border-color: #28c76f;
        background-color: #f0fff4;
    }
    .layout-preview {
        height: 200px;
        background-color: #eee;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #777;
    }
</style>
@endsection

@section('content')
<div class="content-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">اختر نموذج التصميم</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('purchases.invoice_settings.templates.store') }}" method="POST">
                            @csrf
                            
                            <div class="form-group mb-4">
                                <label>اسم القالب</label>
                                <input type="text" name="name" class="form-control" placeholder="مثال: فاتورة المشتريات الرئيسية" required>
                            </div>

                            <div class="row">
                                <!-- نموذج 1: كلاسيك -->
                                <div class="col-md-3">
                                    <div class="card layout-card shadow-sm" onclick="selectLayout('classic')">
                                        <div class="card-body text-center">
                                            <div class="layout-preview mb-3 bg-light rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="fas fa-file-invoice fa-3x text-primary"></i>
                                            </div>
                                            <h5 class="text-primary">كلاسيك (Classic)</h5>
                                            <p class="text-muted small">تصميم رسمي وتقليدي مناسب لأغلب الشركات.</p>
                                            <input type="radio" name="layout" value="classic" checked>
                                        </div>
                                    </div>
                                </div>

                                <!-- نموذج 2: مودرن -->
                                <div class="col-md-3">
                                    <div class="card layout-card shadow-sm" onclick="selectLayout('modern')">
                                        <div class="card-body text-center">
                                            <div class="layout-preview mb-3 bg-light rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="fas fa-file-contract fa-3x text-success"></i>
                                            </div>
                                            <h5 class="text-success">مودرن (Modern)</h5>
                                            <p class="text-muted small">تصميم عصري مع بنية نظيفة وألوان هادئة.</p>
                                            <input type="radio" name="layout" value="modern">
                                        </div>
                                    </div>
                                </div>

                                <!-- نموذج 3: بسيط -->
                                <div class="col-md-3">
                                    <div class="card layout-card shadow-sm" onclick="selectLayout('simple')">
                                        <div class="card-body text-center">
                                            <div class="layout-preview mb-3 bg-light rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="fas fa-receipt fa-3x text-info"></i>
                                            </div>
                                            <h5 class="text-info">بسيط (Simple)</h5>
                                            <p class="text-muted small">تصميم بسيط بالأبيض والأسود.</p>
                                            <input type="radio" name="layout" value="simple">
                                        </div>
                                    </div>
                                </div>

                                <!-- نموذج 4: حراري -->
                                <div class="col-md-3">
                                    <div class="card layout-card shadow-sm" onclick="selectLayout('thermal')">
                                        <div class="card-body text-center">
                                            <div class="layout-preview mb-3 bg-light rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="fas fa-print fa-3x text-warning"></i>
                                            </div>
                                            <h5 class="text-warning">حراري (Thermal)</h5>
                                            <p class="text-muted small">مخصص للطابعات الحرارية (80mm) ونقاط البيع.</p>
                                            <input type="radio" name="layout" value="thermal">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check"></i> إنشاء وتخصيص
                                </button>
                                <a href="{{ route('purchases.invoice_settings.bill_designs') }}" class="btn btn-outline-secondary btn-lg">إلغاء</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function selectLayout(layout) {
        // Remove selected class from all
        document.querySelectorAll('.layout-card').forEach(c => c.classList.remove('selected'));
        // Select radio button
        document.querySelector(`input[name="layout"][value="${layout}"]`).checked = true;
        // Add selected class to click target (closest card)
        event.currentTarget.classList.add('selected');
    }
    
    // Select first one by default visually
    document.querySelector('.layout-card').classList.add('selected');
</script>
@endsection
