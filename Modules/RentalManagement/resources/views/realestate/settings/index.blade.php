@extends('rentalmanagement::realestate.master')

@section('title', 'إعدادات إدارة العقارات')

@section('content')
    @php
        use Modules\RentalManagement\Support\RealEstateSettings;
        use Modules\RentalManagement\Support\RealEstateVocabulary as V;

        $canEdit = hasPermission('realestate.settings.edit');

        // الحسابات الناقصة تُنشأ تلقائياً عند أول قيد؛ التنبيه لمن أراد ربط حسابٍ بعينه قبل ذلك
        $postingBlocked = ! empty($values['post_payments'])
            && (empty($values['rent_revenue_account_id']) || empty($values['deposit_liability_account_id']));
        $accountTypes = ['liabilities' => 'الخصوم', 'revenues' => 'الإيرادات', 'expenses' => 'المصروفات'];

        $installedRoles = count(array_filter($roles, fn ($role) => $role['exists']));
    @endphp

    <div class="content-body">

        @section('page-actions')
            @permission('realestate.logs.view')
                <a href="{{ route('realestate.settings.logs') }}" class="btn btn-outline-secondary"><i class="feather icon-activity"></i> سجلّ النشاط</a>
            @endpermission
            @permission('realestate.settings.edit')
                <button type="submit" form="settingsForm" class="btn btn-primary"><i class="feather icon-check"></i> حفظ الإعدادات</button>
            @endpermission
        @endsection

        @include('stock::components.page-head', [
            'title' => 'إعدادات إدارة العقارات',
            'icon' => 'settings',
            'sub' => 'الربط المحاسبي وسياسات العقود والتحصيل والتشغيل، ومرافق الوحدات وأدوار القسم',
            'crumbs' => ['الرئيسية' => url('/'), 'إدارة العقارات' => route('realestate.dashboard'), 'الإعدادات' => null],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')
        @include('stock::components.form-errors')

        @unless ($canEdit)
            <div class="alert alert-info" role="alert">
                <div class="alert-body"><i class="feather icon-lock"></i> عرضٌ فقط — تعديل الإعدادات يتطلّب صلاحية «تعديل إعدادات العقارات».</div>
            </div>
        @endunless

        {{-- الشاشة تُبنى من `DEFINITIONS`/`GROUPS`: إضافة إعدادٍ هناك تُظهره هنا بلا تعديل --}}
        <form id="settingsForm" method="POST" action="{{ route('realestate.settings.update') }}" novalidate data-re-validate>
            @csrf
            @method('PUT')
            {{-- علامة تميّز `old()` العائدة من هذا النموذج عن نموذج المرافق أدناه --}}
            <input type="hidden" name="_settings" value="1">

            @foreach (RealEstateSettings::GROUPS as $groupKey => $group)
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-{{ $group['icon'] }}"></i> {{ $group['label'] }}</h4>
                    </div>
                    <div class="card-body">
                        @if ($groupKey === 'accounting')
                            {{-- فحص الحسابات: أين يذهب كل مبلغ، وهل الحساب المربوط موجود وسليم؟ --}}
                            @php
                                $badCount = collect($accountHealth)->where('status', 'bad')->count();
                                $unlinkedCount = collect($accountHealth)->where('status', 'unlinked')->count();
                                $stateMeta = ['ok' => ['success', 'سليم', 'check-circle'], 'unlinked' => ['warning', 'غير مربوط — يُنشأ عند أول قيد', 'alert-triangle'], 'bad' => ['danger', 'غير صالح', 'x-circle']];
                            @endphp
                            <div class="card border mb-2" id="accountHealth">
                                <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem">
                                    <h5 class="card-title mb-0"><i class="feather icon-shield"></i> فحص الحسابات المحاسبية
                                        @if ($badCount) <span class="badge badge-light-danger">{{ $badCount }} غير صالح</span>
                                        @elseif ($unlinkedCount) <span class="badge badge-light-warning">{{ $unlinkedCount }} غير مربوط</span>
                                        @else <span class="badge badge-light-success">كلها سليمة</span> @endif
                                    </h5>
                                    @if ($canEdit && ($badCount || $unlinkedCount))
                                        <form method="POST" action="{{ route('realestate.settings.accounts.repair') }}" class="m-0">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-primary"><i class="feather icon-tool"></i> ربط الناقص واستبدال غير الصالح</button>
                                        </form>
                                    @endif
                                </div>
                                <div class="card-body pt-0">
                                    <p class="text-muted small mb-1">كل بند أدناه هو حسابٌ في دليل الحسابات تُقيَّد عليه عمليات العقارات. «الموقع» هو مكانه في شجرة الحسابات، و«الرصيد» ما تراكم عليه حتى الآن.</p>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead><tr><th>البند</th><th>الحساب المربوط</th><th>الموقع في الشجرة</th><th class="text-end">الرصيد</th><th>الحالة</th></tr></thead>
                                            <tbody>
                                                @foreach ($accountHealth as $row)
                                                    @php [$tone, $stateLabel, $icon] = $stateMeta[$row['status']]; @endphp
                                                    <tr>
                                                        <td><strong>{{ $row['label'] }}</strong><small class="d-block text-muted">{{ $row['usage'] }}</small></td>
                                                        <td>
                                                            @if ($row['linked'])
                                                                <span class="stk-num">{{ $row['code'] }}</span> — {{ $row['name'] }}
                                                                <small class="d-block text-muted">{{ $row['type'] }}</small>
                                                            @else — @endif
                                                        </td>
                                                        <td><small>{{ $row['path'] ?: '—' }}</small></td>
                                                        <td class="text-end re-num">{{ \Modules\RentalManagement\Support\RealEstateMoney::format($row['balance']) }}</td>
                                                        <td>
                                                            <span class="badge badge-light-{{ $tone }}"><i class="feather icon-{{ $icon }}"></i> {{ $stateLabel }}</span>
                                                            @if ($row['problem']) <small class="d-block text-danger">{{ $row['problem'] }}</small> @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($groupKey === 'accounting' && $postingBlocked)
                            <div class="alert alert-warning" role="alert">
                                <div class="alert-body">
                                    <i class="feather icon-alert-triangle"></i>
                                    <strong>حساب إيرادات الإيجار أو تأمينات المستأجرين غير مربوط</strong> —
                                    سيُنشأ تلقائياً باسمه عند أول قيد. اختر حساباً هنا إن أردت الترحيل على حسابٍ موجود.
                                </div>
                            </div>
                        @endif

                        <div class="row">
                            @foreach (RealEstateSettings::DEFINITIONS as $settingKey => $definition)
                                @continue($definition['group'] !== $groupKey)

                                <div class="{{ $definition['type'] === 'bool' ? 'col-md-6' : 'col-md-4 col-sm-6' }}">
                                    <div class="form-group">
                                        @if ($definition['type'] === 'bool')
                                            <label class="stk-switch" for="{{ $settingKey }}">
                                                <span class="stk-switch__label">
                                                    <span class="stk-switch__title">{{ $definition['label'] }}</span>
                                                    @isset($definition['hint']) <span class="stk-switch__hint">{{ $definition['hint'] }}</span> @endisset
                                                </span>
                                                {{-- غياب الخانة = «مطفأ»: `save()` تعامل كلّ bool غائبٍ كـ0 --}}
                                                <input type="checkbox" id="{{ $settingKey }}" name="{{ $settingKey }}" value="1"
                                                       @checked(old('_settings') ? old($settingKey) : $values[$settingKey]) @disabled(! $canEdit)>
                                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                            </label>
                                        @else
                                            <label for="{{ $settingKey }}">{{ $definition['label'] }}</label>

                                            @if ($definition['type'] === 'account')
                                                {{--
                                                    حسابات النوع المتوقَّع فقط (لا الدليل كله في كل قائمة)، والقيمة
                                                    المحفوظة حاضرةٌ دائماً. ولا خيار «غير محدَّد» متى رُبط حساب:
                                                    الحقل الذي لم يُمسّ لا يمحو ربطه.
                                                --}}
                                                @php $current = (string) old($settingKey, $values[$settingKey]); @endphp
                                                <select id="{{ $settingKey }}" name="{{ $settingKey }}" class="form-control select2" @disabled(! $canEdit)>
                                                    @if ($current === '') <option value="">— يُنشأ تلقائياً عند أول قيد —</option> @endif
                                                    @foreach ($accountOptions[$settingKey] ?? [] as $account)
                                                        <option value="{{ $account->id }}" @selected($current === (string) $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted d-block mt-25">من حسابات {{ $accountTypes[$definition['account_type']] ?? $definition['account_type'] }}{{ empty($definition['parent']) ? ' الفرعية' : ' (يُقبل حسابٌ رئيسي)' }}.</small>
                                            @elseif ($definition['type'] === 'tax')
                                                <select id="{{ $settingKey }}" name="{{ $settingKey }}" class="form-control select2" @disabled(! $canEdit)>
                                                    <option value="">— الضريبة الشاملة الافتراضية للنظام —</option>
                                                    @foreach ($taxes as $tax)
                                                        <option value="{{ $tax->id }}" @selected((string) old($settingKey, $values[$settingKey]) === (string) $tax->id)>{{ $tax->name }} — {{ rtrim(rtrim(number_format((float) $tax->tax, 2), '0'), '.') }}%</option>
                                                    @endforeach
                                                </select>
                                            @elseif ($definition['type'] === 'select')
                                                <select id="{{ $settingKey }}" name="{{ $settingKey }}" class="form-control select2" @disabled(! $canEdit)>
                                                    <option value="">— الافتراضي —</option>
                                                    @foreach (constant(V::class.'::'.$definition['options']) as $value => $option)
                                                        <option value="{{ $value }}" @selected((string) old($settingKey, $values[$settingKey]) === (string) $value)>{{ $option['label'] ?? $option }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                {{-- `+ 0` يُسقط الأصفار الزائدة: 60.0 تُعرَض 60 --}}
                                                <input type="number" step="any" min="0" max="{{ $definition['max'] ?? 3650 }}" id="{{ $settingKey }}" name="{{ $settingKey }}" class="form-control re-num"
                                                       value="{{ old($settingKey, is_numeric($values[$settingKey]) ? $values[$settingKey] + 0 : $values[$settingKey]) }}" @disabled(! $canEdit)>
                                            @endif

                                            @isset($definition['hint']) <small class="text-muted d-block mt-25">{{ $definition['hint'] }}</small> @endisset
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </form>

        {{-- ═══════ مرافق الوحدات ═══════ --}}
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-coffee"></i> مرافق الوحدات</h4>
                <span class="stk-count">{{ $amenities->count() }}</span>
            </div>
            <div class="card-body">
                <p class="text-muted small">القائمة التي تُختار منها مرافق الوحدة في بطاقتها وفي العروض العقارية.</p>

                @if ($amenities->isEmpty())
                    @include('rentalmanagement::realestate.components.empty', ['icon' => 'coffee', 'title' => 'لا مرافق معرَّفة', 'hint' => 'أضِف المرافق المتكرّرة: موقف، مصعد، مسبح، تكييف مركزي…'])
                @else
                    <div class="stk-tags mb-1">
                        @foreach ($amenities as $amenity)
                            <span class="stk-tag">
                                @if ($amenity->icon) <i class="feather icon-{{ $amenity->icon }}" style="margin-inline-end:.3rem"></i> @endif
                                {{ $amenity->name }}
                                @permission('realestate.settings.edit')
                                    <form method="POST" action="{{ route('realestate.settings.amenities.destroy', $amenity->id) }}" class="d-inline" onsubmit="return confirm('حذف المرفق «{{ $amenity->name }}»؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link text-danger p-0" style="margin-inline-start:.35rem;line-height:1" title="حذف" aria-label="حذف {{ $amenity->name }}"><i class="feather icon-x"></i></button>
                                    </form>
                                @endpermission
                            </span>
                        @endforeach
                    </div>
                @endif

                @permission('realestate.settings.edit')
                    <form method="POST" action="{{ route('realestate.settings.amenities.store') }}" novalidate data-re-validate class="mt-1">
                        @csrf
                        <div class="row align-items-end">
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group mb-md-0">
                                    <label for="amenity_name" class="stk-req">اسم المرفق</label>
                                    <input type="text" id="amenity_name" name="name" class="form-control" data-required="1" maxlength="100" value="{{ old('name') }}" placeholder="مثال: موقف سيارات">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group mb-md-0">
                                    <label for="amenity_icon">الأيقونة (اختياري)</label>
                                    <input type="text" id="amenity_icon" name="icon" class="form-control" dir="ltr" maxlength="40" value="{{ old('icon') }}" placeholder="feather: wifi, truck, wind…">
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-12">
                                <button type="submit" class="btn btn-outline-primary"><i class="feather icon-plus"></i> إضافة مرفق</button>
                            </div>
                        </div>
                    </form>
                @endpermission
            </div>
        </div>

        {{-- ═══════ شروط العقود ═══════ --}}
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-list"></i> شروط العقود</h4>
                <span class="stk-count">{{ $termTemplates->count() }}</span>
            </div>
            <div class="card-body">
                <p class="text-muted small">قوالب تُختار عند إنشاء العقد فتُنسخ إلى بنوده (لقطة) — تعديل القالب لاحقاً لا يغيّر العقود القائمة. القالب «الافتراضي» يُضاف تلقائياً لكل عقدٍ جديد من نوعه.</p>

                @if ($termTemplates->isEmpty())
                    @include('rentalmanagement::realestate.components.empty', ['icon' => 'list', 'title' => 'لا شروط معرَّفة', 'hint' => 'أضِف الشروط المتكرّرة: الصيانة، التأجير من الباطن، الإخلاء، التجديد…'])
                @else
                    @foreach ($termTemplates as $tpl)
                        <div class="border rounded p-1 mb-1 {{ $tpl->is_active ? '' : 'bg-light' }}">
                            <div class="d-flex justify-content-between align-items-start" style="gap:.5rem">
                                <div>
                                    <strong>{{ $tpl->title }}</strong>
                                    <span class="badge badge-light-secondary">{{ $tpl->contract_type ? (V::CONTRACT_TYPES[$tpl->contract_type]['label'] ?? $tpl->contract_type) : 'كل الأنواع' }}</span>
                                    @if ($tpl->is_default) <span class="badge badge-light-primary">افتراضي</span> @endif
                                    @unless ($tpl->is_active) <span class="badge badge-light-warning">معطّل</span> @endunless
                                    <small class="text-muted">· ترتيب {{ $tpl->sort }}</small>
                                    <div class="text-muted small" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($tpl->body, 200) }}</div>
                                </div>
                                @if ($canEdit)
                                    <div class="d-flex" style="gap:.35rem">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('termEdit{{ $tpl->id }}').hidden = !document.getElementById('termEdit{{ $tpl->id }}').hidden"><i class="feather icon-edit"></i> تعديل</button>
                                        <form method="POST" action="{{ route('realestate.settings.terms.destroy', $tpl->id) }}" onsubmit="return confirm('حذف الشرط؟ لا يؤثّر ذلك على العقود القائمة.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="حذف"><i class="feather icon-trash-2"></i></button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                            @if ($canEdit)
                                <form method="POST" action="{{ route('realestate.settings.terms.update', $tpl->id) }}" id="termEdit{{ $tpl->id }}" hidden class="mt-1">
                                    @csrf
                                    @method('PUT')
                                    @include('rentalmanagement::realestate.settings._term_fields', ['tpl' => $tpl])
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="feather icon-check"></i> حفظ الشرط</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                @endif

                @if ($canEdit)
                    <form method="POST" action="{{ route('realestate.settings.terms.store') }}" class="mt-1 border-top pt-1">
                        @csrf
                        <h6>إضافة شرط</h6>
                        @include('rentalmanagement::realestate.settings._term_fields', ['tpl' => null])
                        <button type="submit" class="btn btn-outline-primary"><i class="feather icon-plus"></i> إضافة شرط</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- ═══════ الأدوار والصلاحيات ═══════ --}}
        <div class="card stk-table-card">
            <div class="stk-table-head">
                <h6 class="stk-table-head__title"><i class="feather icon-shield"></i> الأدوار والصلاحيات <span class="stk-count">{{ $installedRoles }} / {{ count($roles) }}</span></h6>
                @permission('realestate.settings.edit')
                    <form method="POST" action="{{ route('realestate.settings.roles.install') }}" onsubmit="return confirm('تثبيت الأدوار النموذجية؟ تُنشأ الأدوار الغائبة وتُضاف الصلاحيات الناقصة فقط.');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary"><i class="feather icon-download-cloud"></i> تثبيت الأدوار النموذجية</button>
                    </form>
                @endpermission
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>الدور</th>
                            <th>الوصف</th>
                            <th class="text-center">الحالة</th>
                            <th style="width:220px">صلاحيات العقارات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            @php $percent = $role['total'] ? (int) round($role['count'] / $role['total'] * 100) : 0; @endphp
                            <tr>
                                <td class="font-weight-bold">{{ $role['name'] }}</td>
                                <td class="text-muted small">{{ $role['description'] }}</td>
                                <td class="text-center">
                                    <span class="badge badge-light-{{ $role['exists'] ? 'success' : 'secondary' }}">{{ $role['exists'] ? 'موجود' : 'غير موجود' }}</span>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-between small mb-25">
                                        <span class="re-num">{{ $role['count'] }} / {{ $role['total'] }}</span>
                                        <span class="re-num text-muted">{{ $percent }}%</span>
                                    </div>
                                    <div class="re-progress"><span style="width: {{ $percent }}%"></span></div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body">
                <p class="text-muted small mb-50">
                    <i class="feather icon-info"></i>
                    التثبيت <strong>إضافةٌ فقط</strong>: يُنشئ الأدوار الغائبة ويضيف ما نقص الموجودَ منها من صلاحيات العقارات، ولا ينزع صلاحيةً ولا يمسّ دوراً آخر — فمن عدّل دوره بيده لا يخسر تعديله.
                </p>
                <p class="text-muted small mb-1">
                    <i class="feather icon-eye"></i>
                    نطاق البيانات (الفرع / الفريق / سجلّاتي فقط) تحكمه صلاحيات <code>data_scope_*</code> العامة على الدور، لا إعدادٌ في هذا القسم.
                </p>

                <div class="d-flex flex-wrap" style="gap:.5rem">
                    @if (Route::has('settings.roles.index'))
                        <a href="{{ route('settings.roles.index') }}" class="btn btn-sm btn-outline-secondary"><i class="feather icon-users"></i> إدارة الأدوار</a>
                    @endif
                    @if (Route::has('SequenceNumbering.index'))
                        <a href="{{ route('SequenceNumbering.index') }}" class="btn btn-sm btn-outline-secondary"><i class="feather icon-hash"></i> الترقيم التسلسلي</a>
                    @endif
                    @if (Route::has('CronJobs.index'))
                        <a href="{{ route('CronJobs.index') }}" class="btn btn-sm btn-outline-secondary"><i class="feather icon-clock"></i> المهام المجدولة</a>
                    @endif
                    @if (Route::has('Application.index'))
                        <a href="{{ route('Application.index') }}" class="btn btn-sm btn-outline-secondary"><i class="feather icon-grid"></i> شاشة التطبيقات</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @include('rentalmanagement::realestate.components.list-scripts')
@endsection
