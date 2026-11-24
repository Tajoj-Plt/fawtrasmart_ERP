{{--
    جسمُ شاشة العرض للمسمّيات والمستويات والأنواع — هويّةٌ وتفاصيل يساراً،
    والموظفون وسجلُّ النشاط يميناً.

      record      السجل (حقول: id, name, description, status, created_at, updated_at)
      icon        أيقونة feather بلا البادئة
      noun        «مسمى» / «مستوى» / «نوع» — لسطر «… رقم #»
      extraDefs   ['التسمية' => HTML آمن] صفوفٌ إضافية في التفاصيل (اختياري)
      usage       ['التسمية' => العدد]
      members     موظفو النطاق (LengthAwarePaginator)
      logs        سجلّ النشاط مجمّعاً باليوم
      hideColumn  يُمرَّر إلى جدول الموظفين (اختياري)
      usageHint   سطرٌ تحت «مستعمَل في»
--}}
@php
    $showActive = (int) $record->status === 0;
@endphp
<div class="row">
    {{-- ══════════ بطاقة الهوية والتفاصيل ══════════ --}}
    <div class="col-lg-4 col-12">
        <div class="card overflow-hidden">
            <div class="card-body text-center">
                <span class="stk-page-head__icon mx-auto mb-1" aria-hidden="true">
                    <i class="feather icon-{{ $icon }}"></i>
                </span>
                <h4 class="mb-25">{{ $record->name }}</h4>
                <p class="stk-help mt-0 mb-1">{{ $noun }} رقم #{{ $record->id }}</p>
                <span class="stk-state stk-state--{{ $showActive ? 'on' : 'off' }}">{{ $showActive ? 'نشط' : 'غير نشط' }}</span>
            </div>
            <div class="stk-metrics" style="margin: 0; border-width: 1px 0 0; border-radius: 0; box-shadow: none">
                <div class="stk-metric">
                    <span class="stk-metric__label">موظفون في نطاقك</span>
                    <span class="stk-metric__value">{{ $members->total() }}</span>
                </div>
                <div class="stk-metric">
                    <span class="stk-metric__label">على مستوى المنشأة</span>
                    <span class="stk-metric__value">{{ $usage['الموظفون'] ?? 0 }}</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-file-text"></i> التفاصيل</h4>
            </div>
            <div class="card-body">
                <dl class="stk-def">
                    @foreach (($extraDefs ?? []) as $defLabel => $defHtml)
                        <div class="stk-def__row">
                            <dt>{{ $defLabel }}</dt>
                            <dd>{!! $defHtml !!}</dd>
                        </div>
                    @endforeach
                    <div class="stk-def__row">
                        <dt>الوصف</dt>
                        <dd>{{ $record->description ?: '—' }}</dd>
                    </div>
                    <div class="stk-def__row">
                        <dt>أُنشئ / آخر تعديل</dt>
                        <dd class="stk-num">
                            {{ optional($record->created_at)->format('Y-m-d H:i') ?? '—' }}
                            /
                            {{ optional($record->updated_at)->format('Y-m-d H:i') ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        @if ($usage !== [])
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="feather icon-link"></i> مستعمَل في</h4>
                </div>
                <div class="card-body">
                    <dl class="stk-def">
                        @foreach ($usage as $usageLabel => $usageCount)
                            <div class="stk-def__row">
                                <dt>{{ $usageLabel }}</dt>
                                <dd class="stk-num">{{ $usageCount }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="stk-help mb-0">{{ $usageHint ?? 'لذلك لا يُحذف — عطّله إن لم يعد مستعمَلاً. (أعدادٌ على مستوى المنشأة، تشمل المؤرشفين.)' }}</p>
                </div>
            </div>
        @endif
    </div>

    {{-- ══════════ الموظفون والسجل ══════════ --}}
    <div class="col-lg-8 col-12">
        <div class="card stk-table-card" id="employees">
            <div class="card-body pb-0">
                <ul class="nav nav-tabs stk-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#tab-employees" role="tab" aria-controls="tab-employees" aria-selected="true">
                            <i class="feather icon-users"></i> الموظفون
                            <span class="stk-count">{{ $members->total() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-activity" role="tab" aria-controls="tab-activity" aria-selected="false">
                            <i class="feather icon-activity"></i> سجل النشاطات
                            <span class="stk-count">{{ $logs->flatten()->count() }}</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="tab-content">
                <div class="tab-pane active" id="tab-employees" role="tabpanel">
                    @include('hr::organizational_structure.partials._members', [
                        'members' => $members,
                        'hideColumn' => $hideColumn ?? null,
                        'emptyHint' => $membersEmptyHint ?? null,
                    ])
                </div>

                <div class="tab-pane" id="tab-activity" role="tabpanel">
                    <div class="card-body">
                        @include('hr::organizational_structure.partials._activity', ['logs' => $logs])
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
