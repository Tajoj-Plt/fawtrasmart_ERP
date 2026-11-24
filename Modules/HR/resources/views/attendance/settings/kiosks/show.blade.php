@extends('hr::master')

@section('title', $kiosk->name)

@section('page-actions')
    @permission('attendance_places.edit')
        <a href="{{ route('attendance.settings.kiosks.edit', $kiosk->id) }}" class="btn btn-primary">
            <i class="feather icon-edit"></i> <span class="d-none d-md-inline">تعديل</span>
        </a>
    @endpermission
    @permission('attendance_places.destroy')
        <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#modal_DELETE{{ $kiosk->id }}">
            <i class="feather icon-trash-2"></i> <span class="d-none d-md-inline">حذف</span>
        </button>
    @endpermission
@endsection

@section('content')
<div class="content-body">
    @include('stock::components.page-head', [
        'title' => $kiosk->name,
        'icon' => 'tablet',
        'sub' => 'جهاز تسجيل الحضور',
        'crumbs' => ['الرئيسية' => url('/'), 'أجهزة التسجيل' => route('attendance.settings.kiosks.index'), $kiosk->name => null],
    ])

    @include('layouts.alerts.error')
    @include('layouts.alerts.success')

    @php
        $silent = $kiosk->is_active && (! $kiosk->last_seen_at || $kiosk->last_seen_at->lt(now()->subHours(3)));
    @endphp

    <div class="row">
        <div class="col-lg-4 col-12">
            <div class="card overflow-hidden">
                <div class="card-body text-center">
                    <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true"><i class="feather icon-tablet"></i></span>
                    <h4 class="mb-25">{{ $kiosk->name }}</h4>
                    <p class="stk-help mt-0 mb-1">{{ $kiosk->branch->name ?? 'بلا فرع' }}</p>
                    <span class="stk-state stk-state--{{ $kiosk->is_active ? 'on' : 'off' }}">{{ $kiosk->is_active ? 'مفعّل' : 'موقوف' }}</span>
                </div>
                <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                    <div class="stk-metric">
                        <span class="stk-metric__label">مقبول اليوم</span>
                        <span class="stk-metric__value">{{ $today['accepted'] }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__label">مرفوض اليوم</span>
                        <span class="stk-metric__value">{{ $today['rejected'] }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4></div>
                <div class="card-body">
                    <dl class="stk-def">
                        <div class="stk-def__row">
                            <dt>المكان</dt>
                            <dd>
                                @if ($kiosk->geofence)
                                    <a href="{{ route('attendance.settings.geofences.show', $kiosk->geofence->id) }}">{{ $kiosk->geofence->name }}</a>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>آخر استخدام</dt>
                            <dd>
                                @if ($kiosk->last_seen_at)
                                    {{ $kiosk->last_seen_at->diffForHumans() }}
                                    <div class="stk-help stk-num">{{ $kiosk->last_seen_at->format('Y-m-d H:i') }}</div>
                                @else
                                    لم يُربط بعد
                                @endif
                            </dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>ملاحظات</dt>
                            <dd>{{ $kiosk->notes ?: '—' }}</dd>
                        </div>
                        <div class="stk-def__row">
                            <dt>أُنشئ / آخر تعديل</dt>
                            <dd class="stk-num">{{ optional($kiosk->created_at)->format('Y-m-d H:i') ?? '—' }} / {{ optional($kiosk->updated_at)->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title"><i class="feather icon-link"></i> ربط الجهاز</h4></div>
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                        <span class="stk-help">رمز الربط</span>
                        <code class="stk-num" style="font-size:1.6rem;letter-spacing:6px;font-weight:700;direction:ltr">{{ $kiosk->pairing_code }}</code>
                        @permission('attendance_places.edit')
                            <form method="POST" action="{{ route('attendance.settings.kiosks.rotate', $kiosk->id) }}" class="m-0"
                                  onsubmit="return confirm('سيتوقف الرمز الحالي فوراً، ويلزم إدخال الرمز الجديد على الجهاز. متابعة؟')">
                                @csrf
                                <button class="btn btn-sm btn-outline-warning"><i class="feather icon-refresh-cw"></i> رمز جديد</button>
                            </form>
                        @endpermission
                    </div>

                    {{-- الشاشةُ المقترنة — واحدةٌ فقط؛ لا يُقبل مسحٌ لهذا الجهاز من غيرها --}}
                    <div class="alert {{ $kiosk->device_token_hash ? 'alert-success' : 'alert-secondary' }} py-50 d-flex flex-wrap align-items-center justify-content-between" style="gap:8px">
                        <span>
                            <i class="feather icon-{{ $kiosk->device_token_hash ? 'lock' : 'unlock' }}"></i>
                            @if ($kiosk->device_token_hash)
                                مقترن بشاشة واحدة منذ {{ $kiosk->paired_at?->format('Y-m-d H:i') }}
                                <small class="d-block text-muted" style="direction:ltr;text-align:right">{{ \Illuminate\Support\Str::limit($kiosk->paired_user_agent, 90) }}</small>
                            @else
                                غير مقترن بأي شاشة — أدخل الرمز على شاشة الفرع.
                            @endif
                        </span>
                        @if ($kiosk->device_token_hash)
                            @permission('attendance_places.edit')
                                <form method="POST" action="{{ route('attendance.settings.kiosks.release', $kiosk->id) }}" class="m-0"
                                      onsubmit="return confirm('ستتوقف الشاشة الحالية عن قبول المسح حتى يُدخل الرمز على شاشة جديدة. متابعة؟')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger"><i class="feather icon-unlock"></i> فك اقتران الشاشة</button>
                                </form>
                            @endpermission
                        @endif
                    </div>

                    {{-- اشتراطُ الموقع على الجهاز — حالةٌ تُقرأ هنا لا تُبحث في شاشة التعديل --}}
                    <div class="alert alert-light border py-50 d-flex flex-wrap align-items-center justify-content-between" style="gap:8px">
                        <span>
                            <i class="feather icon-map-pin"></i>
                            اشتراط الموقع على الجهاز:
                            <strong>{{ $kiosk->require_location ? 'مشغَّل' : 'مطفأ' }}</strong>
                            <small class="d-block text-muted">
                                @if ($kiosk->require_location)
                                    لا يُقبل حضورٌ ولا انصرافٌ من هذا الجهاز إلا والإحداثيات داخل «{{ $kiosk->geofence->name ?? '—' }}».
                                @else
                                    يكفي أن يكون المسحُ من هذا الجهاز المقترن — ولا تُطلب إحداثيات (الصحيحُ للشاشة الثابتة بلا GPS).
                                @endif
                            </small>
                        </span>
                        @permission('attendance_places.edit')
                            <a href="{{ route('attendance.settings.kiosks.edit', $kiosk->id) }}" class="btn btn-sm btn-outline-primary">تغيير من تعديل الجهاز</a>
                        @endpermission
                    </div>

                    {{-- «رمز المكتب» — الموظف يمسحه بجواله --}}
                    <div class="alert alert-light border py-50 d-flex flex-wrap align-items-center justify-content-between" style="gap:8px">
                        <span>
                            <i class="feather icon-maximize"></i>
                            رمز المكتب: <strong>{{ \App\Services\Attendance\OfficeCode::MODES[$kiosk->office_qr ?? 'off'] ?? $kiosk->office_qr }}</strong>
                            @if (in_array($kiosk->office_qr, ['screen', 'both'], true))
                                <small class="d-block text-muted">يظهر على شاشة الجهاز المقترنة ويتغيّر كل 30 ثانية.</small>
                            @endif
                        </span>
                        @if (in_array($kiosk->office_qr, ['printed', 'both'], true))
                            <span class="d-flex" style="gap:6px">
                                <a href="{{ route('attendance.settings.kiosks.office_code', $kiosk->id) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="feather icon-printer"></i> طباعة الرمز</a>
                                @permission('attendance_places.edit')
                                    <form method="POST" action="{{ route('attendance.settings.kiosks.office_code.rotate', $kiosk->id) }}" class="m-0"
                                          onsubmit="return confirm('سيتوقف الملصق الحالي فوراً. متابعة؟')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning"><i class="feather icon-refresh-cw"></i> رمز مطبوع جديد</button>
                                    </form>
                                @endpermission
                            </span>
                        @elseif (($kiosk->office_qr ?? 'off') === 'off')
                            @permission('attendance_places.edit')
                                <a href="{{ route('attendance.settings.kiosks.edit', $kiosk->id) }}" class="btn btn-sm btn-outline-primary">تفعيل من تعديل الجهاز</a>
                            @endpermission
                        @endif
                    </div>

                    @if ($silent)
                        <div class="alert alert-warning py-50">
                            <i class="feather icon-alert-circle"></i>
                            {{ $kiosk->last_seen_at ? 'لم يُستخدم الجهاز منذ أكثر من 3 ساعات — تأكد أن شاشة التسجيل مفتوحة عليه.' : 'لم يُربط الجهاز بعد — اتبع الخطوات أدناه.' }}
                        </div>
                    @endif

                    <ol class="mb-0 ps-3">
                        <li>على الجهاز الموجود في الفرع، افتح «شاشة التسجيل»:
                            <code style="direction:ltr">{{ route('barcode.scan') }}</code></li>
                        <li>أدخل رمز الربط أعلاه مرة واحدة. يُقبل الرمز على <strong>شاشة واحدة فقط</strong> — لا يمكن لجوال موظف أن يصير جهاز الفرع.</li>
                        <li>من بعدها يمسح الموظفون بطاقاتهم عليه للحضور والانصراف.</li>
                        <li>إن فُقد الجهاز أو خرج من الفرع، اضغط «رمز جديد» فيتوقف الرمز القديم.</li>
                    </ol>
                </div>
            </div>

            {{-- الموظفون المربوطون بهذا الجهاز --}}
            <div class="card stk-table-card">
                <div class="stk-table-head">
                    <h6 class="stk-table-head__title">
                        <i class="feather icon-users"></i> الموظفون المربوطون بهذا الجهاز
                        <span class="stk-count">{{ $linked->count() }}</span>
                    </h6>
                </div>
                <div class="card-body pb-1">
                    <p class="text-muted mb-1" style="font-size:.85rem">
                        @if ($linked->isEmpty())
                            لا يوجد موظفون مربوطون — يقبل الجهاز بطاقة أي موظف ليس له جهاز محدد.
                        @else
                            هؤلاء لا يُقبل مسح بطاقاتهم إلا على أجهزتهم المربوطة. ويقبل الجهاز أيضاً كل موظف ليس له جهاز محدد.
                        @endif
                    </p>
                    @permission('attendance_places.edit')
                        <form method="POST" action="{{ route('attendance.settings.kiosks.employees.attach', $kiosk->id) }}" class="d-flex flex-wrap align-items-end mb-1" style="gap:8px">
                            @csrf
                            <div style="flex:1;min-width:240px">
                                <label class="form-label mb-25">ربط موظفين بهذا الجهاز</label>
                                <select name="employee_ids[]" id="kioskAddEmployees" class="form-control" multiple style="width:100%">
                                    @foreach ($candidates as $c)
                                        <option value="{{ $c->id }}">{{ $c->employee_code ?? '#'.$c->id }} — {{ $c->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="feather icon-link"></i> ربط</button>
                        </form>
                    @endpermission
                </div>
                @if ($linked->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>الموظف</th>
                                    <th>القسم / الفرع</th>
                                    <th>البطاقة</th>
                                    <th>أجهزته الأخرى</th>
                                    <th class="text-center" style="width:70px">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($linked as $emp)
                                    <tr>
                                        <td>
                                            <div class="fw-bold">{{ $emp->full_name }}</div>
                                            <small class="text-muted">{{ $emp->employee_code ?? '#'.$emp->id }}</small>
                                        </td>
                                        <td>{{ $emp->department->name ?? '—' }}<small class="text-muted d-block">{{ $emp->branch->name ?? '' }}</small></td>
                                        <td>
                                            @if ($emp->barcode)
                                                <span class="stk-state stk-state--on">صادرة</span>
                                            @else
                                                <span class="stk-state stk-state--off">لا بطاقة</span>
                                            @endif
                                        </td>
                                        <td>{{ $emp->attendanceKiosks->where('id', '!=', $kiosk->id)->pluck('name')->implode('، ') ?: '—' }}</td>
                                        <td class="text-center">
                                            @permission('attendance_places.edit')
                                                <form method="POST" action="{{ route('attendance.settings.kiosks.employees.detach', [$kiosk->id, $emp->id]) }}" class="m-0"
                                                      onsubmit="return confirm('فك الموظف من هذا الجهاز؟')">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" title="فك الربط"><i class="feather icon-x"></i></button>
                                                </form>
                                            @endpermission
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @include('hr::attendance.settings._partials.recent-punches', ['punches' => $punches])
        </div>
    </div>

    @permission('attendance_places.destroy')
        @include('hr::organizational_structure.partials._delete_modal', [
            'modalId' => 'modal_DELETE'.$kiosk->id,
            'heading' => 'حذف الجهاز',
            'action' => route('attendance.settings.kiosks.delete', $kiosk->id),
            'itemName' => $kiosk->name,
            'subject' => 'الجهاز',
            'usage' => [],
        ])
    @endpermission
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        if ($.fn.select2) {
            $('#kioskAddEmployees').select2({ dir: 'rtl', width: '100%', placeholder: 'ابحث واختر موظفاً أو أكثر', closeOnSelect: false });
        }
    });
</script>
@endpush
