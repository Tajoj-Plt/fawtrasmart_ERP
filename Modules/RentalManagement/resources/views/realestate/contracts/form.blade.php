@extends('rentalmanagement::realestate.master')

@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;
    use Modules\RentalManagement\Support\RealEstateSettings;

    $editing = $contract->exists;
    $pageTitle = $editing ? 'تعديل العقد '.$contract->code : 'إنشاء عقد جديد';
    $invalid = fn (string $field) => $errors->has($field) ? 'is-invalid' : '';
    $needsApproval = RealEstateSettings::enabled('require_contract_approval') && ! hasPermission('realestate.contracts.approve');
    $termTplData = $termTemplates->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'body' => $t->body, 'contract_type' => $t->contract_type, 'is_default' => (bool) $t->is_default])->values()->all();
    $termRows = old('terms', $terms->map(fn ($t) => ['title' => $t->title, 'body' => $t->body])->all());
@endphp

@section('title', $pageTitle)

@section('content')
    <div class="content-body">
        @include('stock::components.page-head', [
            'title' => $pageTitle,
            'icon' => 'file-text',
            'sub' => 'البيانات الأساسية ← الأطراف والوحدة ← البنود المالية ← الشروط والمستندات ← المراجعة',
            'crumbs' => ['الرئيسية' => url('/'), 'العقود' => route('realestate.contracts.index'), $editing ? $contract->code : 'عقد جديد' => null],
            'actions' => '<a href="'.($editing ? route('realestate.contracts.show', $contract->id) : route('realestate.contracts.index')).'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>',
        ])

        @include('layouts.alerts.error')
        @include('stock::components.form-errors')

        <div class="re-steps" role="tablist">
            @foreach (['البيانات الأساسية', 'الأطراف والوحدة', 'البنود المالية', 'الشروط والمستندات', 'المراجعة والاعتماد'] as $i => $label)
                <button type="button" class="re-steps__item" data-step="{{ $i }}">{{ $label }}</button>
            @endforeach
        </div>

        <form id="contractForm" method="POST" enctype="multipart/form-data" novalidate data-re-validate
              action="{{ $editing ? route('realestate.contracts.update', $contract->id) : route('realestate.contracts.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif
            <input type="hidden" name="listing_id" value="{{ old('listing_id', $contract->listing_id) }}">
            <input type="hidden" name="action" id="formAction" value="save">

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            {{-- 1 --}}
                            <div class="re-step" data-step="0">
                                <div class="row">
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group">
                                            <label>رقم العقد</label>
                                            <input type="text" class="form-control stk-num" value="{{ $editing ? $contract->code : $code }}" readonly>
                                            <small class="text-muted">من إعدادات الترقيم المتسلسل — يُثبَّت عند الحفظ.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group">
                                            <label for="contract_type" class="stk-req">نوع العقد</label>
                                            {{-- «بيع» لا يُعرض للعقود الجديدة؛ والنوع يتبع استخدام الوحدة --}}
                                            <select id="contract_type" name="contract_type" class="form-control {{ $invalid('contract_type') }}" data-required="1">
                                                @foreach ($contractTypes as $value => $meta) <option value="{{ $value }}" @selected(old('contract_type', $contract->contract_type) === $value)>{{ $meta['label'] }}</option> @endforeach
                                            </select>
                                            <small class="text-muted" id="typeHint">يُحدَّد تلقائياً من استخدام الوحدة (سكني / تجاري).</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group">
                                            <label for="signed_at">تاريخ التوقيع</label>
                                            <input type="date" id="signed_at" name="signed_at" class="form-control" value="{{ old('signed_at', $contract->signed_at?->format('Y-m-d')) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group">
                                            <label for="start_date" class="stk-req">تاريخ البداية</label>
                                            <input type="date" id="start_date" name="start_date" class="form-control {{ $invalid('start_date') }}" data-required="1" value="{{ old('start_date', $contract->start_date?->format('Y-m-d')) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group">
                                            <label for="duration_pick">المدّة</label>
                                            <select id="duration_pick" class="form-control">
                                                @foreach ([6 => '6 أشهر', 12 => 'سنة', 24 => 'سنتان', 36 => '3 سنوات', 60 => '5 سنوات'] as $months => $label) <option value="{{ $months }}">{{ $label }}</option> @endforeach
                                                <option value="">تاريخ محدّد (أكتبه بنفسي)</option>
                                            </select>
                                        </div>
                                    </div>
                                    {{-- إمّا مدّة جاهزة فيُحسب تاريخ النهاية ويُقفل، وإمّا «تاريخ محدّد» فيُكتب يدوياً — لا الاثنان معاً --}}
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group">
                                            <label for="end_date" class="stk-req">تاريخ النهاية</label>
                                            <input type="date" id="end_date" name="end_date" class="form-control {{ $invalid('end_date') }}" data-required="1" value="{{ old('end_date', $contract->end_date?->format('Y-m-d')) }}">
                                            <small class="text-muted" id="endDateHint"></small>
                                        </div>
                                    </div>
                                    {{-- عقدٌ انتهت مدّته قبل اليوم لا يُحفظ إلا تسجيلاً تاريخياً صريحاً --}}
                                    <div class="col-12 d-none" id="historicalBox">
                                        <label class="stk-switch mb-1">
                                            <input type="checkbox" id="is_historical" name="is_historical" value="1" @checked(old('is_historical', $contract->is_historical))>
                                            <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                            <span class="stk-switch__label">
                                                <span class="stk-switch__title">عقد سابق (تسجيل تاريخي)</span>
                                                <span class="stk-switch__hint">تاريخ النهاية مضى: يُسجَّل العقد «منتهياً» للتاريخ ولا يشغل الوحدة</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {{-- 2 --}}
                            <div class="re-step" data-step="1">
                                <div class="row">
                                    @php $unitBuildings = $units->map(fn ($u) => $u->building)->filter()->unique('id')->sortBy('name_ar'); @endphp
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="unit_building_filter">العقار</label>
                                            {{-- مساعدٌ لتضييق قائمة الوحدات؛ لا يُرسل مع النموذج --}}
                                            <select id="unit_building_filter" class="form-control select2">
                                                <option value="">كل العقارات</option>
                                                @foreach ($unitBuildings as $unitBuilding) <option value="{{ $unitBuilding->id }}">{{ $unitBuilding->name }}</option> @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="unit_id" class="stk-req">الوحدة</label>
                                            <select id="unit_id" name="unit_id" class="form-control select2 {{ $invalid('unit_id') }}" data-required="1">
                                                <option value="">اختر الوحدة…</option>
                                                @foreach ($units as $unit)
                                                    <option value="{{ $unit->id }}" data-building="{{ $unit->building_id }}" @selected((string) old('unit_id', $contract->unit_id) === (string) $unit->id)>{{ $unit->full_label }} — {{ $unit->occupancy_meta['label'] }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">وحدةٌ عليها عقدٌ يتقاطع مع هذه الفترة تُرفض عند الحفظ.</small>
                                        </div>
                                    </div>
                                    {{-- التجاوز قرارٌ صريح في كلّ مرّة — حملُ الصلاحية وحده لا يمرّر التداخل --}}
                                    @permission('realestate.contracts.override_overlap')
                                        <div class="col-12 mb-1">
                                            <label class="stk-switch mb-0">
                                                <input type="checkbox" name="override_overlap" value="1" @checked(old('override_overlap'))>
                                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                                <span class="stk-switch__label">
                                                    <span class="stk-switch__title">تجاوز منع التداخل (استثناء)</span>
                                                    <span class="stk-switch__hint">يسمح بعقدٍ يتقاطع مع عقدٍ قائم على الوحدة نفسها — ويُسجَّل التجاوز باسمك</span>
                                                </span>
                                            </label>
                                        </div>
                                    @endpermission
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tenant_client_id" class="stk-req">المستأجر / العميل</label>
                                            {{-- من جدول المستأجرين لا من عموم العملاء — الجديد يُسجَّل من «مستأجر جديد» --}}
                                            @php($currentTenant = (string) old('tenant_client_id', $contract->tenant_client_id ?: request('tenant_client_id')))
                                            <select name="tenant_client_id" id="tenant_client_id" class="form-control select2" data-required="1">
                                                <option value="">اختر المستأجر…</option>
                                                @foreach ($tenants as $tenant)
                                                    <option value="{{ $tenant->id }}" @selected($currentTenant === (string) $tenant->id)
                                                            data-has-tax="{{ filled($tenant->tax_number) ? 1 : 0 }}" data-id-expired="{{ $tenant->id_expired ? 1 : 0 }}" data-id-expiry="{{ $tenant->id_expiry }}">
                                                        {{ $tenant->display_name }}@if ($tenant->code) — {{ $tenant->code }}@endif @if ($tenant->mobile ?: $tenant->phone) · {{ $tenant->mobile ?: $tenant->phone }}@endif @if ($tenant->id_number) · هوية {{ $tenant->id_number }}@endif
                                                    </option>
                                                @endforeach
                                            </select>
                                            @if ($tenants->isEmpty())
                                                <small class="text-warning d-block">لا مستأجرين مسجّلين بعد — سجّل المستأجر أولاً من «مستأجر جديد».</small>
                                            @endif
                                            <div class="d-flex flex-wrap align-items-center mt-50" style="gap:.5rem">
                                                @permission('realestate.relationships.create')
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="addTenant"><i class="feather icon-user-plus"></i> مستأجر جديد</button>
                                                @endpermission
                                                @permission('realestate.relationships.edit')
                                                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="editTenant"><i class="feather icon-edit-2"></i> بيانات المستأجر</button>
                                                @endpermission
                                                @permission('realestate.relationships.view')
                                                    <a href="{{ route('realestate.tenants.index') }}" target="_blank" rel="noopener" class="small"><i class="feather icon-user-check"></i> قسم المستأجرين</a>
                                                @endpermission
                                            </div>
                                            @permission('realestate.relationships.view')
                                                {{-- هوية المستأجر وجواله كما أُدخلا في قسم المستأجرين --}}
                                                <div class="alert alert-light border mt-50 mb-0 py-50 d-none" id="tenantCard"></div>
                                            @endpermission
                                            {{-- هوية منتهية، أو مستأجر تجاري بلا رقم ضريبي: تنبيهٌ لا يمنع الحفظ --}}
                                            <div class="alert alert-warning mt-50 mb-0 py-50 d-none" id="tenantWarning" role="alert"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="owner_id">المالك / المؤجِّر</label>
                                            @include('rentalmanagement::realestate.components.owner-select', ['name' => 'owner_id', 'owners' => $owners, 'selected' => $contract->owner_id, 'placeholder' => 'يُملأ من مالك العقار…'])
                                            <div class="alert alert-warning mt-50 mb-0 py-50 d-none" id="ownerWarning" role="alert"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- 3 --}}
                            <div class="re-step" data-step="2">
                                <div class="row">
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group">
                                            <label for="annual_value" class="stk-req">القيمة السنوية ({{ $contract->currency_code }})</label>
                                            <input type="number" step="0.01" min="0" id="annual_value" name="annual_value" class="form-control {{ $invalid('annual_value') }}" data-required="1" value="{{ old('annual_value', $contract->annual_value) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group">
                                            <label for="payment_frequency" class="stk-req">دورية السداد</label>
                                            <select id="payment_frequency" name="payment_frequency" class="form-control">
                                                @foreach (V::PAYMENT_FREQUENCIES as $value => $meta) <option value="{{ $value }}" data-months="{{ $meta['months'] }}" @selected(old('payment_frequency', $contract->payment_frequency) === $value)>{{ $meta['label'] }}</option> @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group">
                                            <label for="security_deposit">التأمين ({{ $contract->currency_code }})</label>
                                            <input type="number" step="0.01" min="0" id="security_deposit" name="security_deposit" class="form-control {{ $invalid('security_deposit') }}" value="{{ old('security_deposit', $contract->security_deposit ?? 0) }}">
                                            <small class="text-muted">يُستحقّ مع بداية العقد، ويُقيَّد التزاماً لا إيراداً.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="tax_id">الضريبة</label>
                                            {{-- من «إعدادات الضريبة» في النظام: النسبة والنوع يُشتقّان في الخادم من الضريبة المختارة --}}
                                            <select id="tax_id" name="tax_id" class="form-control {{ $invalid('tax_id') }}" data-default="{{ $defaultTaxId }}">
                                                <option value="" data-rate="0" data-included="0">بدون ضريبة</option>
                                                @foreach ($taxes as $tax)
                                                    <option value="{{ $tax->id }}" data-rate="{{ (float) $tax->tax }}" data-included="{{ $tax->type === 'included' ? 1 : 0 }}" @selected((string) old('tax_id', $contract->tax_id) === (string) $tax->id)>
                                                        {{ $tax->name }} — {{ rtrim(rtrim(number_format((float) $tax->tax, 2), '0'), '.') }}% ({{ $tax->type === 'included' ? 'مشمولة في القيمة' : 'مضافة فوق القيمة' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted d-block" id="taxReason">تُحدَّد من استخدام الوحدة وتسجيل المالك في الضريبة.</small>
                                            {{-- لقطة الضريبة المختارة لمعاينة الجدول — الخادم لا يقرؤهما --}}
                                            <input type="hidden" id="tax_rate" value="{{ (float) ($contract->tax_rate ?? 0) }}">
                                            <input type="hidden" id="tax_included" value="{{ $contract->tax_included ? 1 : 0 }}">
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-1"><i class="feather icon-calendar"></i> معاينة جدول الاستحقاقات</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0"><thead><tr><th>#</th><th>النوع</th><th>الاستحقاق</th><th class="text-end">المبلغ</th></tr></thead><tbody id="schedulePreview"></tbody></table>
                                </div>
                                <small class="text-muted">معاينةٌ تقريبية — الجدول النهائي يُبنى عند الحفظ، وكسور التقريب تُحمَّل على القسط الأخير.</small>
                            </div>

                            {{-- 4 --}}
                            <div class="re-step" data-step="3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0">الشروط والبنود</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="addTerm"><i class="feather icon-plus"></i> بند مخصّص</button>
                                </div>
                                <div class="d-flex mb-1" style="gap:.5rem" id="termPicker" @if ($termTemplates->isEmpty()) hidden @endif>
                                    <select id="termTemplateSelect" class="form-control" aria-label="قالب شرط"></select>
                                    <button type="button" class="btn btn-outline-secondary text-nowrap" id="addTermTemplate"><i class="feather icon-plus-circle"></i> إضافة من القوالب</button>
                                </div>
                                @if ($termTemplates->isEmpty())
                                    @permission('realestate.settings.view')
                                        <small class="text-muted d-block mb-1">يمكن تعريف قوالب شروط جاهزة من <a href="{{ route('realestate.settings.index') }}" target="_blank" rel="noopener">الإعدادات</a>.</small>
                                    @endpermission
                                @endif
                                <div id="termsList">
                                    @foreach ($termRows as $i => $term)
                                        <div class="border rounded p-1 mb-1 term-row">
                                            <div class="d-flex" style="gap:.5rem">
                                                <input type="text" name="terms[{{ $i }}][title]" class="form-control" placeholder="عنوان البند" maxlength="191" value="{{ $term['title'] ?? '' }}">
                                                <button type="button" class="btn btn-outline-danger remove-term" aria-label="حذف البند"><i class="feather icon-x"></i></button>
                                            </div>
                                            <textarea name="terms[{{ $i }}][body]" class="form-control mt-50" rows="2" placeholder="نصّ البند">{{ $term['body'] ?? '' }}</textarea>
                                        </div>
                                    @endforeach
                                </div>
                                <p class="text-muted" id="noTerms" @if (count($termRows)) hidden @endif>لا بنود إضافية — أضف ما يلزم (الصيانة، التأجير من الباطن، الإخلاء…).</p>

                                @include('rentalmanagement::realestate.components.files', ['name' => 'attachments', 'label' => 'المستندات (العقد الموقَّع، الهويات، سندات الملكية)', 'model' => $contract])

                                <div class="form-group">
                                    <label for="notes">ملاحظات</label>
                                    <textarea id="notes" name="notes" rows="3" class="form-control" maxlength="5000">{{ old('notes', $contract->notes) }}</textarea>
                                </div>
                            </div>

                            {{-- 5 --}}
                            <div class="re-step" data-step="4">
                                <dl class="stk-def" id="reviewList"></dl>
                                <div class="alert alert-{{ $needsApproval ? 'warning' : 'info' }} mt-1 mb-0">
                                    @if ($needsApproval)
                                        سياسة القسم تتطلّب اعتماداً: «حفظ وإرسال» ينقل العقد إلى «بانتظار الاعتماد»، ولا تتغيّر حالة الوحدة حتى يُعتمد.
                                    @else
                                        «حفظ وتفعيل» يجعل العقد سارياً مباشرةً: تُحجز الوحدة ويُثبَّت جدول الاستحقاقات.
                                    @endif
                                    «حفظ كمسودة» يُبقيه قابلاً للتعديل والحذف.
                                </div>
                            </div>

                            <div class="re-step-nav">
                                <button type="button" class="btn btn-outline-secondary" data-step-prev><i class="feather icon-arrow-right"></i> السابق</button>
                                <span>
                                    <button type="button" class="btn btn-outline-primary" data-step-next>التالي <i class="feather icon-arrow-left"></i></button>
                                    <span class="d-none" data-step-last>
                                        <button type="submit" class="btn btn-outline-primary" data-action="save"><i class="feather icon-save"></i> حفظ كمسودة</button>
                                        @if (! $editing || $contract->status === 'draft')
                                            <button type="submit" class="btn btn-primary" data-action="submit"><i class="feather icon-check"></i> {{ $needsApproval ? 'حفظ وإرسال للاعتماد' : 'حفظ وتفعيل' }}</button>
                                        @endif
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- اللوحة الجانبية: ملخّص الوحدة والقيمة والمستندات المطلوبة --}}
                <div class="col-lg-4">
                    <div class="card re-summary">
                        <div class="card-header"><h4 class="card-title"><i class="feather icon-clipboard"></i> ملخّص العقد</h4></div>
                        <div class="card-body">
                            <dl class="stk-def">
                                <div class="stk-def__row"><dt>الوحدة</dt><dd id="sumUnit">—</dd></div>
                                <div class="stk-def__row"><dt>حالتها</dt><dd id="sumOccupancy">—</dd></div>
                                <div class="stk-def__row"><dt>الاستخدام</dt><dd id="sumUsage">—</dd></div>
                                <div class="stk-def__row"><dt>العملة</dt><dd class="stk-num">{{ $contract->currency_code }}</dd></div>
                                <div class="stk-def__row"><dt>الإيجار الإرشادي (سنوياً)</dt><dd class="stk-num" id="sumGuide">—</dd></div>
                                <div class="stk-def__row"><dt>المدّة</dt><dd class="stk-num" id="sumMonths">—</dd></div>
                                <div class="stk-def__row"><dt>عدد الأقساط</dt><dd class="stk-num" id="sumCount">—</dd></div>
                                <div class="stk-def__row"><dt>قيمة القسط</dt><dd class="stk-num" id="sumEach">—</dd></div>
                                <div class="stk-def__row"><dt>الضريبة</dt><dd class="stk-num" id="sumTax">—</dd></div>
                                <div class="stk-def__row"><dt><strong>إجمالي العقد</strong></dt><dd class="stk-num"><strong id="sumTotal">—</strong></dd></div>
                            </dl>
                            <div class="alert alert-warning mt-1 mb-0 d-none" id="sumWarning"></div>
                            <h6 class="mt-1">المستندات المطلوبة</h6>
                            <ul class="mb-0 ps-3 pe-3 text-muted" style="font-size:.85rem">
                                <li>هوية المستأجر أو السجل التجاري</li>
                                <li>سند الملكية أو وكالة المؤجِّر</li>
                                <li>نسخة العقد الموقَّعة</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
    @include('rentalmanagement::realestate.components.stepper-scripts')
    @include('rentalmanagement::realestate.components.unit-filter')
    <script>
        $(function () {
            reUnitFilter('#unit_building_filter', '#unit_id');
            const infoUrl = @json(route('realestate.units.info', ['id' => ':id']));
            const tenantUrl = @json(route('realestate.tenants.show', '__ID__'));
            const canSeeTenant = @json(hasPermission('realestate.relationships.view'));

            // بطاقة المستأجر المختار: الهوية والجوال من قسم المستأجرين
            function loadTenant() {
                const id = $('#tenant_client_id').val();
                $('#editTenant').toggleClass('d-none', !id);
                $('#tenantCard').addClass('d-none').empty();
                if (!id || !canSeeTenant) return;

                $.ajax({ url: tenantUrl.replace('__ID__', id), headers: { Accept: 'application/json' } }).done(function (t) {
                    const cell = (icon, label, value) => $('<span class="d-inline-block me-1 ms-1"></span>')
                        .append($('<i></i>').addClass('feather icon-' + icon), ' ' + label + ': ', $('<strong dir="ltr"></strong>').text(value || '—'));

                    $('#tenantCard').removeClass('d-none').append(
                        cell('smartphone', 'الجوال', t.mobile || t.phone),
                        cell('credit-card', t.id_type_label || 'رقم الهوية', t.id_number),
                        cell('flag', 'الجنسية', t.nationality));

                    if (!t.id_number) $('#tenantCard').append($('<small class="d-block text-warning"></small>').text('لم تُسجَّل هوية هذا المستأجر — أكملها من «بيانات المستأجر».'));
                });
            }

            $('#tenant_client_id').on('change', loadTenant);
            loadTenant();

            // تسجيل المستأجر صفحةٌ مستقلة تُفتح في تبويبٍ جديد حتى لا يضيع ما كُتب في العقد؛
            // وعند حفظها تكتب في localStorage فيلتقطه هذا النموذج ويختار المستأجر في الحقل.
            const tenantCreateUrl = @json(route('realestate.tenants.create', ['return_to' => 'contract']));
            const tenantEditUrl = @json(route('realestate.tenants.edit', ['clientId' => '__ID__', 'return_to' => 'contract']));

            $('#addTenant').on('click', () => window.open(tenantCreateUrl, '_blank'));
            $('#editTenant').on('click', () => window.open(tenantEditUrl.replace('__ID__', $('#tenant_client_id').val()), '_blank'));

            function tenantSaved(client) {
                if (!client || !client.id) return;
                const $select = $('#tenant_client_id');
                if (!$select.find('option[value="' + client.id + '"]').length) {
                    $select.append(new Option(client.label, client.id));
                } else {
                    $select.find('option[value="' + client.id + '"]').text(client.label);
                }
                $select.val(String(client.id)).trigger('change');
            }

            $(window).on('storage', function (e) {
                if (e.originalEvent.key !== 're_tenant_saved' || !e.originalEvent.newValue) return;
                try { tenantSaved(JSON.parse(e.originalEvent.newValue)); } catch (ignore) {}
            });

            const currency = @json($contract->currency_code);
            const fmt = v => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const cur = v => fmt(v) + ' ' + currency;
            const round2 = v => Math.round(v * 100) / 100;
            const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            const today = @json(now()->toDateString());

            // تاريخٌ محلّي من `YYYY-MM-DD` — `new Date(string)` يقرؤه UTC فينزاح يوماً في بعض المناطق
            function parseDate(value) {
                const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
                return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null;
            }

            // كما في الخادم: إضافة أشهرٍ بلا تجاوز نهاية الشهر
            function addMonths(date, months) {
                const d = new Date(date.getTime());
                const day = d.getDate();
                d.setDate(1);
                d.setMonth(d.getMonth() + months);
                d.setDate(Math.min(day, new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate()));
                return d;
            }

            const dayDiff = (a, b) => Math.round((b - a) / 86400000);

            /*
                مدّة العقد كما يحسبها الخادم (ContractService::termMonths): أشهرٌ
                كاملة ثم الأيام الباقية منسوبةً إلى طول شهرها — 45 يوماً ≈ شهر
                ونصف لا شهران. يومٌ زائد بعد أشهرٍ كاملة يُهمل.
            */
            function term() {
                const s = parseDate($('#start_date').val()), e = parseDate($('#end_date').val());
                if (!s || !e || e <= s) return { months: 0, whole: 0, days: 0 };

                const endEx = new Date(e.getTime());
                endEx.setDate(endEx.getDate() + 1);

                let whole = 0;
                while (addMonths(s, whole + 1) <= endEx) whole++;

                const anchor = addMonths(s, whole);
                let days = dayDiff(anchor, endEx);

                if (whole >= 1 && days <= 1) return { months: whole, whole: whole, days: 0 };

                const period = Math.max(1, dayDiff(anchor, addMonths(s, whole + 1)));
                return { months: whole + days / period, whole: whole, days: days };
            }

            function termLabel(t) {
                if (!t.months) return '—';
                if (!t.whole) return t.days + ' يوماً';
                return t.whole + ' شهراً' + (t.days ? ' و' + t.days + ' يوماً' : '');
            }

            function recalc() {
                const t = term();
                const m = t.months;
                const annual = parseFloat($('#annual_value').val()) || 0;
                const step = parseInt($('#payment_frequency option:selected').data('months'), 10) || 1;
                const rate = (parseFloat($('#tax_rate').val()) || 0) / 100;
                const included = $('#tax_included').val() === '1';
                const deposit = parseFloat($('#security_deposit').val()) || 0;
                const factor = (rate > 0 && !included) ? 1 + rate : 1;

                const net = round2(annual / 12 * m);
                const gross = round2(net * factor);
                const tax = rate > 0 ? round2(gross - gross / (1 + rate)) : 0;
                const count = m ? Math.max(1, Math.ceil(m / step - 1e-6)) : 0;
                // مدّةٌ تنقسم على الدورية: أقساطٌ متساوية. وإلا: أقساطٌ كاملة ثم أخيرٌ بنسبة الأيام الباقية
                const aligned = count && Math.abs(m - count * step) < 1e-6;
                const each = count ? (aligned ? round2(gross / count) : round2(annual / 12 * step * factor)) : 0;
                const last = count ? Math.max(0, round2(gross - each * (count - 1))) : 0;

                $('#sumMonths').text(termLabel(t));
                $('#sumCount').text(count || '—');
                $('#sumEach').text(count ? cur(count > 1 ? each : last) + (count > 1 && !aligned ? ' (الأخير ' + fmt(last) + ')' : '') : '—');
                $('#sumTax').text(rate > 0 ? cur(tax) + (included ? ' (مشمولة)' : ' (مضافة)') : 'لا ضريبة');
                $('#sumTotal').text(m ? cur(gross) + (deposit ? ' + تأمين ' + fmt(deposit) : '') : '—');

                // نهايةٌ مضت: لا يُحفظ العقد إلا تسجيلاً تاريخياً
                const past = !!$('#end_date').val() && $('#end_date').val() < today;
                $('#historicalBox').toggleClass('d-none', !past);
                if (!past) $('#is_historical').prop('checked', false);

                const $body = $('#schedulePreview').empty();
                const start = parseDate($('#start_date').val());

                if (!count || !start) {
                    return $body.append('<tr><td colspan="4" class="text-muted text-center">أدخل التواريخ والقيمة لعرض الجدول</td></tr>');
                }

                if (deposit) $body.append('<tr><td>—</td><td>تأمين</td><td class="stk-num">' + iso(start) + '</td><td class="text-end re-num">' + fmt(deposit) + '</td></tr>');

                for (let i = 0; i < count; i++) {
                    $body.append('<tr><td>' + (i + 1) + '</td><td>إيجار</td><td class="stk-num">' + iso(addMonths(start, i * step)) + '</td><td class="text-end re-num">' + fmt(i === count - 1 ? last : each) + '</td></tr>');
                }
            }

            // ═══════ استخدام الوحدة ← نوع العقد والضريبة ═══════
            // من الخادم لكلّ وحدة (usage / ownership / owner_id)، ويُحدَّث بما يعيده `units/{id}/info`
            const unitMeta = @json($unitMeta);
            const ownerVat = @json($ownerVat); // null = عمود تسجيل الضريبة لم يُضف بعد
            const lockedSale = @json($contract->contract_type === 'sale');
            const typeLabels = @json(collect($contractTypes)->map(fn ($m) => $m['label']));

            function policy() {
                const meta = unitMeta[$('#unit_id').val()];
                if (!meta) return null;

                const ownerId = $('#owner_id').val() || meta.owner_id;

                if (meta.usage !== 'commercial') {
                    return { usage: 'residential', type: 'residential_lease', taxable: false, reason: 'الوحدة سكنية — الإيجار السكني بلا ضريبة.' };
                }
                if (meta.ownership === 'own' || !ownerId) {
                    return { usage: 'commercial', type: 'commercial_lease', taxable: true, reason: 'المبنى مملوك للمنشأة وهي المؤجِّر — تُطبَّق ضريبة النظام.' };
                }
                if (ownerVat === null) {
                    return { usage: 'commercial', type: 'commercial_lease', taxable: true, reason: 'وحدة تجارية — تُطبَّق ضريبة النظام.' };
                }
                return ownerVat[ownerId]
                    ? { usage: 'commercial', type: 'commercial_lease', taxable: true, reason: 'المالك مسجَّل في الضريبة — تُطبَّق ضريبة النظام لحسابه.' }
                    : { usage: 'commercial', type: 'commercial_lease', taxable: false, reason: 'المالك غير مسجَّل في الضريبة — العقد بدون ضريبة.' };
            }

            function syncTax() {
                const $opt = $('#tax_id option:selected');
                $('#tax_rate').val($opt.data('rate') || 0);
                $('#tax_included').val($opt.data('included') ? 1 : 0);
                recalc();
            }

            /** يطبّق حكم الوحدة: يقفل نوع العقد على استخدامها، ويقفل الضريبة أو يفتحها. `preset`: اختر الافتراضي. */
            function applyPolicy(preset) {
                const p = policy();
                const $type = $('#contract_type'), $tax = $('#tax_id');

                if (!p || lockedSale) {
                    $type.find('option').prop('disabled', false);
                    $tax.find('option').prop('disabled', false);
                    $('#sumUsage').text('—');
                    return syncTax();
                }

                // القفل بتعطيل الخيارات الأخرى لا الحقل نفسه — الحقل المعطَّل لا يُرسَل
                $type.find('option').each(function () { $(this).prop('disabled', this.value !== p.type); });
                if ($type.val() !== p.type) $type.val(p.type).trigger('change');
                $('#typeHint').text('الوحدة ' + (p.usage === 'commercial' ? 'تجارية' : 'سكنية') + ' — نوع العقد «' + (typeLabels[p.type] || '') + '» ومقفل.');
                $('#sumUsage').html('<span class="badge badge-light-' + (p.usage === 'commercial' ? 'warning' : 'info') + '">' + (p.usage === 'commercial' ? 'تجاري' : 'سكني') + '</span>');

                $tax.find('option').each(function () { $(this).prop('disabled', !p.taxable && this.value !== ''); });

                if (!p.taxable) {
                    $tax.val('');
                } else if (preset && !$tax.val() && $tax.data('default')) {
                    $tax.val(String($tax.data('default')));
                }

                $('#taxReason').text(p.reason + (p.taxable && !$tax.val() ? ' لم تُختر ضريبة — سيُحفظ العقد بدون ضريبة.' : ''))
                    .toggleClass('text-warning', p.taxable && !$tax.val()).toggleClass('text-muted', !(p.taxable && !$tax.val()));

                syncTax();
                tenantWarnings();
                ownerWarning();
            }

            // مستأجر بهوية منتهية، أو مستأجر تجاري بلا رقم ضريبي
            function tenantWarnings() {
                const $opt = $('#tenant_client_id option:selected');
                const p = policy();
                const notes = [];

                if ($opt.val() && String($opt.data('id-expired')) === '1') notes.push('هوية المستأجر منتهية منذ ' + ($opt.data('id-expiry') || '') + ' — حدّثها من «بيانات المستأجر».');
                if ($opt.val() && p && p.usage === 'commercial' && String($opt.data('has-tax')) !== '1') notes.push('عقد تجاري والمستأجر بلا رقمٍ ضريبي — أكمله من ملف المستأجر لتصحّ الفاتورة الضريبية.');

                $('#tenantWarning').toggleClass('d-none', !notes.length).html(notes.map(n => '<div><i class="feather icon-alert-triangle"></i> ' + $('<i>').text(n).html() + '</div>').join(''));
            }

            // المالك على العقد غير مالك المبنى: يُقبل لكن يُنبَّه
            function ownerWarning() {
                const meta = unitMeta[$('#unit_id').val()];
                const chosen = $('#owner_id').val();
                const differs = !!(meta && meta.owner_id && chosen && String(meta.owner_id) !== String(chosen));

                $('#ownerWarning').toggleClass('d-none', !differs)
                    .html(differs ? '<i class="feather icon-alert-triangle"></i> المالك المختار غير مالك المبنى المسجَّل. يُقبل العقد (وكالة أو انتقال ملكية) ويُسجَّل التنبيه في سجلّه.' : '');
            }

            function loadUnit(prefill) {
                const id = $('#unit_id').val();

                if (!id) {
                    $('#sumUnit, #sumOccupancy, #sumGuide').text('—');
                    $('#sumWarning').addClass('d-none');
                    return applyPolicy(false);
                }

                applyPolicy(prefill);

                $.getJSON(infoUrl.replace(':id', id)).done(function (u) {
                    $('#sumUnit').text(u.label);
                    $('#sumOccupancy').html('<span class="badge badge-light-' + u.occupancy.tone + '">' + u.occupancy.label + '</span>');
                    $('#sumGuide').text(cur(u.annual_rent));
                    $('#sumWarning').toggleClass('d-none', u.occupancy.label === 'شاغرة' || @json($editing))
                        .text('الوحدة ليست شاغرة الآن — تأكّد أنّ فترة العقد لا تتقاطع مع عقدٍ قائم.');

                    // ما يعيده مسار الوحدة أحدث ممّا حُمِّل مع الصفحة
                    const meta = unitMeta[id] = unitMeta[id] || {};
                    if (u.usage_type) meta.usage = u.usage_type;
                    if (u.ownership) meta.ownership = u.ownership;
                    if ('owner_id' in u) meta.owner_id = u.owner_id;
                    if (ownerVat !== null && u.owner_id && 'owner_vat_registered' in u) ownerVat[u.owner_id] = !!u.owner_vat_registered;

                    if (prefill) {
                        if (!parseFloat($('#annual_value').val())) $('#annual_value').val(u.annual_rent);
                        if (!$('#owner_id').val() && u.owner_id) $('#owner_id').val(u.owner_id).trigger('change');
                    }

                    applyPolicy(prefill);
                });
            }

            // المدّة الجاهزة وتاريخ النهاية اليدوي لا يجتمعان: المدّة تحسب التاريخ وتقفله،
            // و«تاريخ محدّد» يفتحه للكتابة.
            function endFor(months) {
                const start = parseDate($('#start_date').val());
                if (!start) return null;
                const end = addMonths(start, months);
                end.setDate(end.getDate() - 1);
                return iso(end);
            }
            function applyDuration() {
                const months = parseInt($('#duration_pick').val(), 10);
                const $end = $('#end_date');
                if (!months) {
                    $end.prop('readonly', false).css({'pointer-events': '', 'background-color': ''});
                    $('#endDateHint').text('اكتب تاريخ النهاية بنفسك.');
                    return;
                }
                $end.prop('readonly', true).css({'pointer-events': 'none', 'background-color': '#f1f1f4'});
                $('#endDateHint').text('يُحسب من تاريخ البداية والمدّة. لتغييره اختر «تاريخ محدّد».');
                const end = endFor(months);
                if (end && $end.val() !== end) $end.val(end).trigger('change');
            }
            // عند الفتح: إن طابق التاريخ المحفوظ مدّةً جاهزة اختيرت، وإلا «تاريخ محدّد»
            (function () {
                const current = $('#end_date').val();
                let match = '';
                $('#duration_pick option').each(function () {
                    const m = parseInt(this.value, 10);
                    if (m && current && endFor(m) === current) match = this.value;
                });
                $('#duration_pick').val(current ? match : '12');
            })();
            $('#duration_pick').on('change', applyDuration);
            $('#start_date').on('change', applyDuration);
            applyDuration();

            $('#tax_id').on('change', () => applyPolicy(false));
            $('#owner_id').on('change', () => applyPolicy(true));
            $('#tenant_client_id').on('change', tenantWarnings);
            $('#unit_id').on('change', () => loadUnit(true));
            $('#start_date, #end_date, #annual_value, #payment_frequency, #security_deposit').on('input change', recalc);

            // قواعد الخطوات فوق «الحقل مطلوب» — يستدعيها معالج الخطوات عند «التالي»
            $(document).on('re:validate-step', function (e, step, $pane, fail) {
                if (step === 0) {
                    const s = $('#start_date').val(), en = $('#end_date').val();
                    if (s && en && en <= s) fail('#end_date', 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية');
                    else if (en && en < today && !$('#is_historical').is(':checked')) fail('#end_date', 'تاريخ النهاية مضى — علِّم «عقد سابق (تسجيل تاريخي)» أو صحّح التاريخ');
                }
                if (step === 2 && !((parseFloat($('#annual_value').val()) || 0) > 0)) fail('#annual_value', 'أدخل قيمةً سنوية أكبر من صفر');
            });

            // البنود
            let termIndex = {{ count($termRows) }};
            const termTemplates = @json($termTplData);
            const termEditing = @json($editing || old('terms') !== null);
            const escAttr = v => $('<div>').text(v == null ? '' : v).html().replace(/"/g, '&quot;');
            function addTermRow(title, body, tplId, auto) {
                $('#noTerms').prop('hidden', true);
                const $row = $('<div class="border rounded p-1 mb-1 term-row"><div class="d-flex" style="gap:.5rem">'
                    + '<input type="text" name="terms[' + termIndex + '][title]" class="form-control" placeholder="عنوان البند" maxlength="191" value="' + escAttr(title) + '">'
                    + '<button type="button" class="btn btn-outline-danger remove-term" aria-label="حذف البند"><i class="feather icon-x"></i></button></div>'
                    + '<textarea name="terms[' + termIndex + '][body]" class="form-control mt-50" rows="2" placeholder="نصّ البند"></textarea></div>');
                $row.find('textarea').val(body || '');
                if (tplId) { $row.attr('data-tpl', tplId); }
                if (auto) { $row.attr('data-auto', 1); }
                $('#termsList').append($row);
                termIndex++;
            }
            const termMatches = t => !t.contract_type || t.contract_type === $('#contract_type').val();
            function refreshTermPicker() {
                const $sel = $('#termTemplateSelect').empty();
                termTemplates.filter(termMatches).forEach(t => $sel.append($('<option>').val(t.id).text(t.title)));
                $('#addTermTemplate').prop('disabled', !$sel.children().length);
            }
            function applyDefaultTerms() {
                $('#termsList .term-row[data-auto]').each(function () {
                    const t = termTemplates.find(x => String(x.id) === String($(this).attr('data-tpl')));
                    if (!t || !termMatches(t)) { $(this).remove(); }
                });
                termTemplates.filter(t => t.is_default && termMatches(t)).forEach(t => {
                    if (!$('#termsList .term-row[data-tpl="' + t.id + '"]').length) { addTermRow(t.title, t.body, t.id, true); }
                });
                $('#noTerms').prop('hidden', $('#termsList .term-row').length > 0);
            }
            $('#addTerm').on('click', function () { addTermRow('', '', null, false); });
            $('#addTermTemplate').on('click', function () {
                const t = termTemplates.find(x => String(x.id) === String($('#termTemplateSelect').val()));
                if (t) { addTermRow(t.title, t.body, t.id, false); }
            });
            $('#contract_type').on('change', function () { refreshTermPicker(); if (!termEditing) { applyDefaultTerms(); } });
            refreshTermPicker();
            if (!termEditing) { applyDefaultTerms(); }
            $('#termsList').on('click', '.remove-term', function () { $(this).closest('.term-row').remove(); });

            // المراجعة تُبنى من قيم النموذج نفسها لحظةَ فتحها
            $(document).on('re:step', function (e, step) {
                if (step !== 4) return;

                const text = sel => $(sel + ' option:selected').text().trim() || '—';
                const rows = [
                    ['نوع العقد', text('#contract_type')], ['الوحدة', text('#unit_id')], ['المستأجر', text('#tenant_client_id')], ['المالك', $('#owner_id').val() ? text('#owner_id') : '—'],
                    ['الفترة', ($('#start_date').val() || '—') + ' ← ' + ($('#end_date').val() || '—') + ' (' + $('#sumMonths').text() + ')'],
                    ['القيمة السنوية', cur($('#annual_value').val())], ['دورية السداد', text('#payment_frequency') + ' — ' + $('#sumCount').text() + ' قسطاً × ' + $('#sumEach').text()],
                    ['الضريبة', ($('#tax_id').val() ? text('#tax_id') + ' — ' : '') + $('#sumTax').text()], ['التأمين', cur($('#security_deposit').val())], ['إجمالي العقد', $('#sumTotal').text()],
                    ['البنود', $('#termsList .term-row').length + ' بنداً'],
                ];

                $('#reviewList').html(rows.map(r => '<div class="stk-def__row"><dt>' + r[0] + '</dt><dd>' + $('<i>').text(r[1]).html() + '</dd></div>').join(''));
            });

            $('[data-action]').on('click', function () { $('#formAction').val($(this).data('action')); });

            // الافتراضيات (ضريبة الوحدة التجارية، مالك المبنى) لعقدٍ جديد لم يُرسَل بعد — لا تُفرض على ما اختاره المستخدم
            loadUnit(!@json($editing) && !@json(old('unit_id') !== null));
            tenantWarnings();
            recalc();
        });
    </script>
@endsection
