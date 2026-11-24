@extends('client::master')

{{-- شاشة «أوضاع العملاء» — نسخة أبسط من «حالات العملاء»: لا سقف على
     العدد، ولا عمود «دورها بالمؤشرات» (لا صلة لها بحالة العميل التشغيلية
     أصلاً). انظر docs/CRM_Customer_Status_Gap_Analysis_Plan.md القسم 3. --}}

@section('title')
    إدارة أوضاع العملاء
@stop

@section('content')
    @include('client::partials.settings-theme')

    <div class="content-body cl-scope">
        @include('client::components.page-head', [
            'title' => 'إدارة أوضاع العملاء',
            'icon' => 'hourglass',
            'sub' => 'وسمٌ إضافي على العميل (متابعة، تحت المراجعة، ...) — لا يؤثّر على حالته التشغيلية.',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'إعدادات العملاء' => route('clients.setting'),
                'أوضاع العملاء' => null,
            ],
            'actions' => '<a href="'.route('clients.setting').'" class="btn btn-outline-secondary"><i class="feather icon-arrow-right"></i> رجوع</a>',
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('client::components.form-errors')

        <div class="card">
            <div class="card-content">
                <div class="card-body">
                    @foreach ($stages as $stage)
                        <form id="stage-edit-{{ $stage->id }}" action="{{ route('clients.registrationStages.update', $stage->id) }}" method="POST"></form>
                        <form id="stage-delete-{{ $stage->id }}" action="{{ route('clients.registrationStages.delete', $stage->id) }}" method="POST"
                              onsubmit="return confirm('حذف هذا الوضع؟')">
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
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($stages as $stage)
                                    @php $formId = 'stage-edit-'.$stage->id; @endphp
                                    <tr>
                                        <td>
                                            <input type="hidden" name="_token" value="{{ csrf_token() }}" form="{{ $formId }}">
                                            <input type="hidden" name="_method" value="PUT" form="{{ $formId }}">
                                            <input type="text" form="{{ $formId }}" class="form-control" name="name" value="{{ $stage->name }}" required>
                                        </td>
                                        <td>
                                            <input type="color" form="{{ $formId }}" class="form-control form-control-color" name="color" value="{{ $stage->color ?: '#ff9f43' }}" title="لون الوضع">
                                        </td>
                                        <td class="d-flex gap-1">
                                            <button type="submit" form="{{ $formId }}" class="btn btn-primary btn-sm">
                                                <i class="feather icon-save"></i> حفظ
                                            </button>
                                            @if ($stage->is_deletable)
                                                <button type="submit" form="stage-delete-{{ $stage->id }}" class="btn btn-danger btn-sm">
                                                    <i class="feather icon-trash"></i> حذف
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-danger btn-sm" disabled title="مستعملة حالياً — لا يمكن حذفها">
                                                    <i class="feather icon-lock"></i> محميّة
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">لا توجد أوضاع متابعة بعد — أضف واحدة أدناه.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-plus"></i> إضافة وضع جديد</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('clients.registrationStages.store') }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-6 col-12 form-group">
                        <label for="new_stage_name">الاسم</label>
                        <input type="text" class="form-control" id="new_stage_name" name="name" required>
                    </div>
                    <div class="col-md-3 col-12 form-group">
                        <label for="new_stage_color">اللون</label>
                        <input type="color" class="form-control form-control-color" id="new_stage_color" name="color" value="#ff9f43">
                    </div>
                    <div class="col-md-3 col-12 form-group">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="feather icon-plus"></i> إضافة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
