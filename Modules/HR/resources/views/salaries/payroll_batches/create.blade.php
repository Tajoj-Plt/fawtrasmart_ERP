@extends('hr::master')

@section('title', 'تجميع صرفات')

@section('content')
    @include('stock::components.page-head', [
        'title' => 'تجميع صرفات معلّقة',
        'icon' => 'layers',
        'crumbs' => [
            'مسير الصرفات' => route('hr.payroll-batches.index'),
            'تجميع' => null,
        ],
    ])

    <div class="content-body">
        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @include('hr::components.guide', [
            'key' => 'hr-payroll-batches.create',
            'title' => 'ما الذي يترتّب على التجميع والاعتماد؟',
            'lead' => 'المسيرُ تصريحٌ بالصرف لا صرف: يجمع صرفاتٍ معلّقة ليُعتمد خروجُ نقدها دفعةً واحدة.',
            'blocks' => [
                'المراحل' => [
                    'قبل الاعتماد تبقى الصرفاتُ «بانتظار الاعتماد» ولا تُصرف.',
                    'بعد الاعتماد تصير «معتمدة — بانتظار الصرف»، وتُصرف يدوياً أو تلقائياً في يومها فتصير «مدفوعة».',
                ],
                'ما لا يُجمع' => [
                    'الصرفاتُ الموقوفة والمدفوعة والملغاة، وما هو ضمن مسيرٍ آخر، وما هو خارج نطاق موظفيك.',
                ],
            ],
        ])

        <form method="POST" action="{{ route('hr.payroll-batches.store') }}">
            @csrf
            <div class="card">
                <div class="card-body">
                    <div class="stk-section">
                        <div class="stk-section__head">
                            <i class="feather icon-file-text"></i>
                            <h6 class="stk-section__title">بيانات المسير</h6>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label for="name">اسم المسير</label>
                                <input type="text" id="name" name="name" maxlength="191"
                                       class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="اختياري — يُولَّد إن تُرك">
                                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label for="payroll_id">مسير الرواتب
                                    <i class="feather icon-help-circle stk-tip" title="اختياري: لربط المسير بمسيرٍ رواتب بعينه. يمكنك أيضاً تصفية القائمة أدناه به."></i>
                                </label>
                                <select name="payroll_id" id="payroll_id" class="form-control select2 @error('payroll_id') is-invalid @enderror">
                                    <option value="">—</option>
                                    @foreach ($payrolls as $payroll)
                                        <option value="{{ $payroll->id }}" @selected((string) old('payroll_id', request('payroll_id')) === (string) $payroll->id)>{{ $payroll->name }}</option>
                                    @endforeach
                                </select>
                                @error('payroll_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label for="notes">ملاحظات</label>
                                <input type="text" id="notes" name="notes" maxlength="500"
                                       class="form-control @error('notes') is-invalid @enderror" value="{{ old('notes') }}">
                                @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="approve_now" name="approve_now" value="1" {{ old('approve_now') ? 'checked' : '' }}>
                            <label class="custom-control-label" for="approve_now">اعتماد فوري بعد التجميع — تصير الصرفاتُ «بانتظار الصرف» مباشرة</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-list"></i> الصرفات المعلّقة غير المجمّعة
                        <span class="stk-count">{{ $runs->count() }}</span>
                    </h6>
                    @if ($runs->isNotEmpty())
                        <label class="mb-0 small"><input type="checkbox" id="toggle_all" checked> تحديد الكل</label>
                    @endif
                </div>
                @if ($runs->isEmpty())
                    <div class="card-body">
                        <div class="stk-empty">
                            <div class="stk-empty__icon"><i class="feather icon-inbox"></i></div>
                            <h5 class="stk-empty__title">لا صرفات متاحة للتجميع</h5>
                            <p class="stk-empty__hint">كلُّ الصرفات المعلّقة ضمن مسيرات، أو موقوفة، أو خارج نطاقك.</p>
                        </div>
                    </div>
                @else
                    @error('run_ids')<div class="alert alert-danger m-2">{{ $message }}</div>@enderror
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px"></th>
                                    <th>السجل</th>
                                    <th>الموظف</th>
                                    <th>الفترة</th>
                                    <th>يوم الصرف</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($runs as $run)
                                    <tr>
                                        <td><input type="checkbox" class="run-pick" name="run_ids[]" value="{{ $run->id }}" @checked(in_array($run->id, old('run_ids', $runs->pluck('id')->all())))></td>
                                        <td class="stk-strong">{{ $run->code ?: '#'.$run->id }}</td>
                                        <td>{{ trim(($run->employee->first_name ?? '').' '.($run->employee->middle_name ?? '')) ?: '—' }}</td>
                                        <td class="stk-num">{{ optional($run->period_from)->format('Y-m-d') }} → {{ optional($run->period_to)->format('Y-m-d') }}</td>
                                        <td class="stk-num">{{ optional($run->scheduled_date)->format('Y-m-d') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="stk-form-foot">
                        <a href="{{ route('hr.payroll-batches.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                        <button class="btn btn-primary"><i class="feather icon-save"></i> حفظ المسير</button>
                    </div>
                @endif
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            $('#toggle_all').on('change', function () {
                $('.run-pick').prop('checked', this.checked);
            });
        });
    </script>
@endpush
