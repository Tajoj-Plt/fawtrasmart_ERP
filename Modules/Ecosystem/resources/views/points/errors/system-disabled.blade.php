@extends('ecosystem::master')

@section('content')
<div class="content-body">
    <div class="row justify-content-center mt-5">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-0">
                <div class="card-body text-center py-5">
                    <!-- أيقونة التعطيل -->
                    <div class="mb-3">
                        <div style="width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #ff6b6b, #ee5a24); display: inline-flex; align-items: center; justify-content: center;">
                            <i class="feather icon-power" style="font-size: 48px; color: #fff;"></i>
                        </div>
                    </div>

                    <h2 class="text-danger mb-3">نظام النقاط معطّل</h2>
                    <p class="text-muted mb-4" style="font-size: 1.1rem;">
                        نظام النقاط والمكافآت غير مفعّل حالياً. جميع العمليات المتعلقة بالنقاط متوقفة.
                        <br>
                        يمكنك إعادة تفعيل النظام من صفحة الإعدادات العامة.
                    </p>

                    <div class="alert alert-light border mb-4" style="display: inline-block;">
                        <div class="d-flex align-items-center">
                            <i class="feather icon-info text-info mr-2" style="font-size: 20px;"></i>
                            <span>للتفعيل: اذهب إلى الإعدادات العامة ← قم بتفعيل "نظام النقاط الرئيسي"</span>
                        </div>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('ecosystem.points.general-settings.index') }}" class="btn btn-primary btn-lg px-5">
                            <i class="feather icon-settings mr-1"></i>
                            الإعدادات العامة
                        </a>
                        <a href="{{ route('ecosystem.points.reports.points') }}" class="btn btn-outline-secondary btn-lg px-4 ml-2">
                            <i class="feather icon-bar-chart-2 mr-1"></i>
                            التقارير
                        </a>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('ecosystem.dashboard') }}" class="text-muted">
                            <i class="feather icon-arrow-right mr-1"></i>
                            العودة للوحة التحكم
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
