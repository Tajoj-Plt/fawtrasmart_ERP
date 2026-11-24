@extends('taskmanager::master')

@section('title')
    سبرنت جديد
@stop

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">سبرنت جديد</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('sprints.index') }}">السبرنتات</a></li>
                            <li class="breadcrumb-item active">جديد</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
        <div class="row">
            <div class="col-12 col-lg-8 offset-lg-2">
                <div class="card">
                    <div class="card-header"><h4 class="card-title">بيانات السبرنت</h4></div>
                    <div class="card-body">
                        <form id="sprint-form">
                            @csrf
                            <div class="form-group">
                                <label>المشروع <span class="text-danger">*</span></label>
                                <select name="project_id" class="form-control select2" required>
                                    <option value="">اختر المشروع</option>
                                    @foreach ($projects as $project)
                                        @if ($project->management_type !== 'manual')
                                            <option value="{{ $project->id }}">{{ $project->title }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">السبرنتات متاحة فقط لمشاريع سكرم</small>
                            </div>

                            <div class="form-group">
                                <label>اسم السبرنت <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="مثال: سبرنت 1 - إعداد الدفع" required>
                            </div>

                            <div class="form-group">
                                <label>الهدف</label>
                                <textarea name="goal" class="form-control" rows="3" placeholder="ما الذي يسعى هذا السبرنت لتحقيقه؟"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>تاريخ البداية <span class="text-danger">*</span></label>
                                        <input type="date" name="start_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>تاريخ النهاية <span class="text-danger">*</span></label>
                                        <input type="date" name="end_date" class="form-control" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>الحالة</label>
                                <select name="status" class="form-control select2">
                                    <option value="planned" selected>مخطط له</option>
                                    <option value="active">نشط</option>
                                </select>
                            </div>

                            <hr>
                            <div class="text-end">
                                <a href="{{ route('sprints.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                                <button type="submit" class="btn btn-primary"><i class="feather icon-save"></i> حفظ</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function () {
    $('.select2').select2();

    $('#sprint-form').on('submit', function (e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<i class="feather icon-loader"></i> جاري الحفظ...');

        $.ajax({
            url: '{{ route("sprints.store") }}',
            method: 'POST',
            data: $(this).serialize(),
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
        }).done(function (response) {
            if (response.success) {
                Swal.fire('تم!', response.message, 'success').then(() => {
                    window.location.href = '{{ route("sprints.index") }}';
                });
            } else {
                Swal.fire('خطأ!', response.message || 'حدث خطأ', 'error');
            }
        }).fail(function (xhr) {
            if (xhr.status === 422 && xhr.responseJSON.errors) {
                const errors = Object.values(xhr.responseJSON.errors).map(e => e[0]).join('<br>');
                Swal.fire({title: 'خطأ في البيانات', html: errors, icon: 'error'});
            } else {
                Swal.fire('خطأ!', 'حدث خطأ أثناء إنشاء السبرنت', 'error');
            }
        }).always(function () {
            btn.prop('disabled', false).html('<i class="feather icon-save"></i> حفظ');
        });
    });
});
</script>
@endsection
