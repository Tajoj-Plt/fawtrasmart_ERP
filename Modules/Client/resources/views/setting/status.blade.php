@extends('client::master')

{{-- شاشة «حالات العملاء» — كانت 500 دائماً بسبب استدعاء القالب بمساحة
     اسمٍ خاطئة (`client.setting.status` بدل `client::setting.status`)،
     وحتى بعد إصلاحها كان جافاسكربتها يرسل لروابط غير موجودة
     (`statuses.store`/`statuses.updateAll`) ولا نموذج حقيقي يغلّف الجدول.
     أعيد بناؤها بنماذج Blade عادية ترسل فعلاً لمسارات موجودة.

     الترويسة والبطاقات هنا على نفس نظام تصميم «إعدادات وحدات العملاء»
     (`cl-` في `settings-theme`) — تجانساً بصرياً بين شاشات الإعدادات، دون
     مسّ الجدول والنماذج نفسها. --}}

@section('title')
    إدارة حالات العملاء
@stop

@section('content')
    @include('client::partials.settings-theme')

    <div class="content-body cl-scope">
        @include('client::components.page-head', [
            'title' => 'إدارة حالات العملاء',
            'icon' => 'tag',
            'sub' => 'الحالات التي يمرّ بها العميل، ودورُ كلٍّ منها في مؤشرات لوحة التحكّم.',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'إعدادات العملاء' => route('clients.setting'),
                'حالات العملاء' => null,
            ],
            'actions' => '<a href="'.route('clients.setting').'" class="btn btn-outline-secondary"><i class="feather icon-arrow-right"></i> رجوع</a>',
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('client::components.form-errors')

        <div class="card">
            <div class="card-content">
                <div class="card-body">
                    {{-- نموذجا كل صفّ (تعديل/حذف) خارج الجدول عمداً: `<form>` ليس
                         محتوًى صالحاً داخل `<tr>` مباشرة، وربطُ عناصر الجدول بها
                         عبر السمة `form="…"` يتفادى تداخل النماذج ببعضها. --}}
                    @foreach ($statuses as $status)
                        <form id="status-edit-{{ $status->id }}" action="{{ route('clients.status.update', $status->id) }}" method="POST"></form>
                        <form id="status-delete-{{ $status->id }}" action="{{ route('clients.status.delete', $status->id) }}" method="POST"
                              onsubmit="return confirm('حذف هذه الحالة؟')">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endforeach

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>الاسم</th>
                                    <th>اللون</th>
                                    <th>مفتوح/مغلق</th>
                                    <th>دورها في المؤشرات</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($statuses as $status)
                                    @php $formId = 'status-edit-'.$status->id; @endphp
                                    <tr>
                                        <td>
                                            {{-- @csrf/@method لا تدعم السمة form="…"، فحقولهما المخفية
                                                 تحتاجها صراحةً كي تصل مع نموذجٍ لا هي من ذُرّيّته --}}
                                            <input type="hidden" name="_token" value="{{ csrf_token() }}" form="{{ $formId }}">
                                            <input type="hidden" name="_method" value="PUT" form="{{ $formId }}">
                                            <input type="text" form="{{ $formId }}" class="form-control" name="name" value="{{ $status->name }}" required>
                                        </td>
                                        <td>
                                            <input type="color" form="{{ $formId }}" class="form-control form-control-color" name="color" value="{{ $status->color ?: '#009688' }}" title="لون الحالة">
                                        </td>
                                        <td>
                                            <select class="form-control" form="{{ $formId }}" name="state">
                                                <option value="open" @selected($status->state)>مفتوح</option>
                                                <option value="closed" @selected(!$status->state)>مغلق</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-control" form="{{ $formId }}" name="kpi_role">
                                                <option value="" @selected(!$status->kpi_role)>بدون</option>
                                                <option value="active" @selected($status->kpi_role === 'active')>نشط</option>
                                                <option value="stopped" @selected($status->kpi_role === 'stopped')>موقوف</option>
                                            </select>
                                        </td>
                                        <td class="d-flex gap-1">
                                            <button type="submit" form="{{ $formId }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-save"></i> حفظ
                                            </button>
                                            @if ($status->is_deletable)
                                                <button type="submit" form="status-delete-{{ $status->id }}" class="btn btn-danger btn-sm">
                                                    <i class="feather icon-trash"></i> حذف
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-danger btn-sm" disabled title="حالةٌ أساسية محميّة — لا يمكن حذفها">
                                                    <i class="feather icon-lock"></i> محميّة
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">لا توجد حالات عملاء بعد — أضف واحدة أدناه.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if ($statuses->count() < 2)
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-plus"></i> إضافة حالة جديدة</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('clients.status.store') }}" method="POST" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4 col-12 form-group">
                            <label for="new_status_name">الاسم</label>
                            <input type="text" class="form-control" id="new_status_name" name="name" required>
                        </div>
                        <div class="col-md-2 col-12 form-group">
                            <label for="new_status_color">اللون</label>
                            <input type="color" class="form-control form-control-color" id="new_status_color" name="color" value="#009688">
                        </div>
                        <div class="col-md-2 col-12 form-group">
                            <label for="new_status_state">مفتوح/مغلق</label>
                            <select class="form-control" id="new_status_state" name="state">
                                <option value="open">مفتوح</option>
                                <option value="closed">مغلق</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-12 form-group">
                            <label for="new_status_kpi_role">دورها بالمؤشرات</label>
                            <select class="form-control" id="new_status_kpi_role" name="kpi_role">
                                <option value="">بدون</option>
                                <option value="active">نشط</option>
                                <option value="stopped">موقوف</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-12 form-group">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="feather icon-plus"></i> إضافة
                            </button>
                        </div>
                    </form>
                    <small class="text-muted d-block mt-2">
                        «دورها بالمؤشرات» يحدّد أيّ حالة تُحتسَب نشطة أو موقوفة في لوحة تحكّم العملاء — بدل الاعتماد على ترتيب إنشاء الحالات.
                    </small>
                </div>
            </div>
        @else
            {{-- حالة العميل ثنائيةٌ بقرار: نشط أو موقوف فقط، والصفّان أعلاه
                 محميّان من الحذف — فلا داعي لنموذج إضافةٍ يرفضه الخادم دائماً. --}}
            <div class="alert alert-info d-flex align-items-center gap-2 mb-0">
                <i class="feather icon-info"></i>
                <span>حالة العميل تُحصر عمداً في «نشط» و«موقوف» — عدّل الاسم أو اللون أو دور المؤشرات لأيٍّ منهما من الجدول أعلاه بدل إضافة حالةٍ ثالثة.</span>
            </div>
        @endif
    </div>
@endsection
