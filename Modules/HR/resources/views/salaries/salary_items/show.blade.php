@extends('hr::master')

@section('title')
    بند الراتب: {{ $salaryItem->name }}
@stop

    @php
        $isAddition = (int) $salaryItem->type === \App\Models\SalaryItem::TYPE_ADDITION;
        $isActive = (int) $salaryItem->status === \App\Models\SalaryItem::STATUS_ACTIVE;
        $isFormula = (int) $salaryItem->salary_item_value === \App\Models\SalaryItem::VALUE_TYPE_CALCULATION_FORMULA;
        $canEdit = hasPermission('salary_items.edit');
        $canDestroy = hasPermission('salary_items.destroy');
        $inUse = ($usage['contracts'] ?? 0) + ($usage['slips'] ?? 0) > 0;
    @endphp

    @section('page-actions')
        {{-- الترتيب الموحّد لشاشات العرض: [تعديل] [المزيد ▾ … حذف] --}}
        @if ($canEdit)
            <a href="{{ route('SalaryItems.edit', $salaryItem->id) }}" class="btn btn-primary">
                <i class="fa fa-edit"></i> تعديل
            </a>
        @endif
        @if ($canEdit || $canDestroy)
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-h"></i> المزيد
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @if ($canEdit)
                    <li>
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#modal_TOGGLE_STATUS">
                            @if ($isActive)
                                <i class="fa fa-pause-circle me-2 text-warning"></i>تعطيل البند
                            @else
                                <i class="fa fa-play-circle me-2 text-success"></i>تنشيط البند
                            @endif
                        </a>
                    </li>
                    @endif
                    @if ($canEdit && $canDestroy)
                    <li><hr class="dropdown-divider"></li>
                    @endif
                    @if ($canDestroy)
                    <li>
                        <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $salaryItem->id }}">
                            <i class="fa fa-trash me-2"></i>حذف البند
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        @endif
    @endsection

@section('content')

    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => $salaryItem->name,
            'icon' => $isAddition ? 'plus-circle' : 'minus-circle',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'بنود الرواتب' => route('SalaryItems.index'),
                $salaryItem->name => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="row">
            {{-- ══════════ بطاقة الهوية ══════════ --}}
            <div class="col-lg-4 col-12">
                <div class="card overflow-hidden">
                    <div class="card-body text-center">
                        <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true">
                            <i class="feather icon-{{ $isAddition ? 'plus-circle' : 'minus-circle' }}"></i>
                        </span>
                        <h4 class="mb-25">{{ $salaryItem->name }}</h4>
                        <p class="stk-help mt-0 mb-1">بند رقم #{{ $salaryItem->id }}</p>
                        <span class="stk-state stk-state--{{ $isAddition ? 'on' : 'warn' }}">{{ $isAddition ? 'مستحق' : 'مستقطع' }}</span>
                        <span class="stk-state stk-state--{{ $isActive ? 'on' : 'off' }}">{{ $isActive ? 'نشط' : 'غير نشط' }}</span>
                    </div>
                    <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                        <div class="stk-metric">
                            <span class="stk-metric__label">عقود</span>
                            <span class="stk-metric__value">{{ $usage['contracts'] ?? 0 }}</span>
                        </div>
                        <div class="stk-metric">
                            <span class="stk-metric__label">قسائم</span>
                            <span class="stk-metric__value">{{ $usage['slips'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-shield"></i> الخضوع والاحتساب</h4>
                    </div>
                    <div class="card-body">
                        @if ($isAddition)
                            <dl class="stk-def">
                                <div class="stk-def__row">
                                    <dt>التأمينات الاجتماعية</dt>
                                    <dd>
                                        <span class="stk-state stk-state--{{ $salaryItem->is_insurable ? 'neutral' : 'off' }}">
                                            {{ $salaryItem->is_insurable ? 'خاضع — يدخل الأجر الخاضع' : 'غير خاضع' }}
                                        </span>
                                    </dd>
                                </div>
                                <div class="stk-def__row">
                                    <dt>أجر نهاية الخدمة</dt>
                                    <dd>
                                        <span class="stk-state stk-state--{{ $salaryItem->in_eos_wage ? 'neutral' : 'off' }}">
                                            {{ $salaryItem->in_eos_wage ? 'يدخل أجر المكافأة' : 'لا يدخل' }}
                                        </span>
                                    </dd>
                                </div>
                                @if ($salaryItem->componentType)
                                    <div class="stk-def__row">
                                        <dt>
                                            تصنيف سابق
                                            <i class="feather icon-help-circle stk-tip" title="تصنيفٌ من «كتالوج البدلات» المتقاعد — محفوظٌ للتقارير، وعلمُ الخضوع أعلاه هو المعتمَد"></i>
                                        </dt>
                                        <dd>{{ $salaryItem->componentType->name }}</dd>
                                    </div>
                                @endif
                            </dl>
                        @else
                            <p class="stk-help mb-0">الاستقطاعُ لا يدخل الأجرَ الخاضعَ للتأمينات ولا أجرَ نهاية الخدمة.</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ══════════ التفاصيل والسجل ══════════ --}}
            <div class="col-lg-8 col-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs stk-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#details" role="tab" aria-controls="details" aria-selected="true">
                                    <i class="feather icon-file-text"></i> التفاصيل
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#activity" role="tab" aria-controls="activity" aria-selected="false">
                                    <i class="feather icon-activity"></i> سجل النشاطات
                                    <span class="stk-count">{{ $logs->flatten()->count() }}</span>
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content pt-2">
                            <div class="tab-pane active" id="details" role="tabpanel">
                                <div class="stk-section">
                                    <div class="stk-section__head">
                                        <i class="feather icon-hash"></i>
                                        <h6 class="stk-section__title">القيمة</h6>
                                    </div>
                                    <dl class="stk-def">
                                        <div class="stk-def__row">
                                            <dt>طريقة الاحتساب</dt>
                                            <dd>{{ $isFormula ? 'صيغة حسابية' : 'مبلغ ثابت' }}</dd>
                                        </div>
                                        @if ($isFormula)
                                            <div class="stk-def__row">
                                                <dt>الصيغة</dt>
                                                <dd><code dir="ltr">{{ $salaryItem->calculation_formula ?: '—' }}</code></dd>
                                            </div>
                                        @else
                                            <div class="stk-def__row">
                                                <dt>المبلغ الافتراضي</dt>
                                                <dd class="stk-num">
                                                    {{ $salaryItem->amount !== null ? \App\Support\CurrencySettings::format($salaryItem->amount) : 'يُحدَّد في العقد' }}
                                                </dd>
                                            </div>
                                        @endif
                                        <div class="stk-def__row">
                                            <dt>قيمة مرجعية فقط</dt>
                                            <dd>{{ $salaryItem->reference_value ? 'نعم — للمقارنة ولا تُفرض' : 'لا' }}</dd>
                                        </div>
                                        <div class="stk-def__row">
                                            <dt>الشرط</dt>
                                            <dd>{{ $salaryItem->condition ?: '—' }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <div class="stk-section">
                                    <div class="stk-section__head">
                                        <i class="feather icon-info"></i>
                                        <h6 class="stk-section__title">الوصف والحساب</h6>
                                    </div>
                                    <dl class="stk-def">
                                        <div class="stk-def__row">
                                            <dt>الوصف</dt>
                                            <dd>{{ $salaryItem->description ?: '—' }}</dd>
                                        </div>
                                        <div class="stk-def__row">
                                            <dt>الحساب المحاسبي</dt>
                                            <dd>
                                                @if ($salaryItem->Account)
                                                    <span class="stk-num">{{ $salaryItem->Account->code }}</span> — {{ $salaryItem->Account->name }}
                                                @else
                                                    <span class="text-muted">حساب الرواتب الافتراضي</span>
                                                @endif
                                            </dd>
                                        </div>
                                        <div class="stk-def__row">
                                            <dt>أُنشئ / آخر تعديل</dt>
                                            <dd class="stk-num">
                                                {{ optional($salaryItem->created_at)->format('Y-m-d H:i') ?? '—' }}
                                                /
                                                {{ optional($salaryItem->updated_at)->format('Y-m-d H:i') ?? '—' }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            <div class="tab-pane" id="activity" role="tabpanel">
                                @if ($logs->isNotEmpty())
                                    <ul class="stk-timeline">
                                        @foreach ($logs as $date => $dayLogs)
                                            <li class="stk-timeline__day">
                                                <span>{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l، d F Y') }}</span>
                                            </li>
                                            @foreach ($dayLogs as $log)
                                                <li class="stk-timeline__item">
                                                    <span class="stk-timeline__dot stk-timeline__dot--log">
                                                        <i class="feather icon-edit-3"></i>
                                                    </span>
                                                    <div class="stk-timeline__body">
                                                        <p class="stk-timeline__head">
                                                            <strong>{{ $log->user->name ?? 'مستخدم غير معروف' }}</strong>
                                                            <span class="stk-timeline__time stk-num">
                                                                <i class="feather icon-clock"></i> {{ $log->created_at->format('H:i:s') }}
                                                            </span>
                                                        </p>
                                                        <div class="stk-timeline__text">{{ $log->description ?? 'لا يوجد وصف' }}</div>
                                                        <p class="stk-timeline__meta">
                                                            <span><i class="feather icon-map-pin"></i> {{ $log->user->branch->name ?? 'فرع غير معروف' }}</span>
                                                        </p>
                                                    </div>
                                                </li>
                                            @endforeach
                                        @endforeach
                                    </ul>
                                @else
                                    <div class="stk-empty">
                                        <div class="stk-empty__icon"><i class="feather icon-activity"></i></div>
                                        <h5 class="stk-empty__title">لا يوجد نشاط مسجَّل</h5>
                                        <p class="stk-empty__hint">يظهر هنا كلُّ إنشاءٍ وتعديلٍ وتغييرِ حالةٍ على هذا البند</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($canEdit)
        {{-- تأكيد التعطيل / التنشيط --}}
        <div class="modal fade" id="modal_TOGGLE_STATUS" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content text-right">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $isActive ? 'تعطيل' : 'تنشيط' }} البند</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-1">
                            {{ $isActive ? 'سيختفي' : 'سيظهر' }} البند <strong>{{ $salaryItem->name }}</strong>
                            {{ $isActive ? 'من' : 'في' }} قوائم الاختيار في العقود والقسائم.
                        </p>
                        <p class="stk-empty__hint mb-0">نُسَخُ البند القائمة في العقود والقسائم لا تتأثّر.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                        <form action="{{ route('SalaryItems.toggleStatus', $salaryItem->id) }}" method="POST" class="d-inline">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="btn btn-{{ $isActive ? 'warning' : 'success' }}">
                                تأكيد {{ $isActive ? 'التعطيل' : 'التنشيط' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($canDestroy)
        {{-- تأكيد الحذف --}}
        <div class="modal fade" id="modal_DELETE{{ $salaryItem->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content text-right">
                    <div class="modal-header">
                        <h5 class="modal-title">حذف بند الراتب</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        @if ($inUse)
                            <p class="mb-1 text-danger">
                                البند مستعمَلٌ في {{ $usage['contracts'] }} عقد و{{ $usage['slips'] }} قسيمة، فلا يُحذف.
                            </p>
                            <p class="stk-empty__hint mb-0">عطّله بدلاً من ذلك: يختفي من الاختيار ويبقى أثرُه.</p>
                        @else
                            <p class="mb-1">سيُحذف تعريف البند <strong>{{ $salaryItem->name }}</strong>.</p>
                            <p class="stk-empty__hint mb-0">نُسَخُه في القسائم السابقة تبقى كما هي.</p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                        @unless ($inUse)
                            <form action="{{ route('SalaryItems.destroy', $salaryItem->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="feather icon-trash-2"></i> حذف
                                </button>
                            </form>
                        @endunless
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
