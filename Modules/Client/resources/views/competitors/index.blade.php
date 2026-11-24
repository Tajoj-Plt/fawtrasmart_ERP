@extends('client::master')

@section('title')
    المنافسون
@stop

@section('content')
    @include('client::partials.settings-theme')
    @include('components.hint-tips')

    {{-- قوائم إجراءات الصفوف تخرج من قصّ البطاقة والجدول --}}
    @include('client::partials.table-menu')

    @php
        $canAddCompetitor = hasPermission('clients_competitors_add');
        $canEditCompetitor = hasPermission('clients_competitors_edit');
        $canDeleteCompetitor = hasPermission('clients_competitors_delete');
        // «المرصودون فقط» افتراضُ الشاشة متى اختير فرع — لا يُعدّ فلتراً إلّا حين يخالف الافتراض
        $observedDefault = $headerBranch !== null;
        $activeFilters = ($search !== '' ? 1 : 0) + ($status !== '' ? 1 : 0) + ($observed !== $observedDefault ? 1 : 0);
        // عمودُ الأرصاد وبلاطتُها على فرع الشريط العلويّ متى اختير فرعٌ غير الرئيسيّ
        $observationsScope = $headerBranch ? 'في «'.$headerBranch->name.'» وما تحته' : null;
    @endphp

    {{-- هيكل «إدارة العملاء» نفسه: ترويسة ← إرشاد ← إحصاءات ← شريط إجراءات
         ← بطاقة بحث ← بطاقة جدول. الإضافة والتعديل في نافذتين بدل بطاقةٍ جانبية. --}}
    <div class="cl-scope" dir="rtl">
        @include('client::components.page-head', [
            'title' => 'المنافسون',
            'icon' => 'target',
            'sub' => 'قائمة الشركات المنافسة التي يختار منها المندوب داخل ملاحظة الزيارة'
                .($observationsScope ? ' — الأرصاد '.$observationsScope.' (من الشريط العلوي)' : ''),
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'المنافسون' => null,
            ],
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        @include('client::components.guide', [
            'key' => 'competitors',
            'open' => false,
            'title' => 'ما هذه الشاشة، ولمَ قائمةٌ لا كتابةٌ حرّة؟',
            'lead' => 'قائمة الشركات المنافسة التي يختار منها المندوب حين يرصد منافساً داخل ملاحظة الزيارة.',
            'blocks' => [
                'لماذا قائمة لا كتابة حرّة' => [
                    'كان المندوب يكتب اسم المنافس بيده في كل رصد، فيصير «بيبسي» و«Pepsi» و«بيبسى» ثلاثةَ منافسين في أي تقرير، ولا يُجمع حضورُ منافسٍ واحدٍ عبر العملاء.',
                    'عرّف الاسم هنا مرّةً، ويختاره كلُّ مندوبٍ من القائمة نفسها.',
                ],
                'التعطيل بديلُ الحذف' => [
                    'منافسٌ له أرصادٌ محفوظة لا يُحذف: حذفُه يُفرِّغ اسمَه من تقارير سابقة، فزرُّ الحذف يُقفل تلقائياً عندها.',
                    '«تعطيل» يُخفيه من قائمة اختيار المندوب وتبقى أرصاده مقروءةً — وهو ما تريده لمنافسٍ خرج من السوق.',
                ],
                'أين تظهر هذه القائمة' => [
                    'في مودال «إضافة ملاحظة» داخل مساحة عمل الزيارة، قسم «رصد المنافسين».',
                    'والقسم نفسه يظهر أو يختفي من: إعدادات وحدات العملاء ← الزيارات ← «رصد المنافسين داخل ملاحظة الزيارة».',
                ],
            ],
            'facts' => [
                'عمود «الأرصاد» هو عدد المرّات التي رُصد فيها هذا المنافس في ملاحظات الزيارات.',
                'تغيير الاسم هنا لا يغيّر الاسم المحفوظ مع أرصادٍ سابقة — تلك لقطةٌ تاريخية.',
            ],
        ])

        {{-- ══════════ الإحصاءات — على الكتالوج كلّه لا على نتيجة البحث ══════════ --}}
        <div class="cl-stats">
            <div class="cl-stat">
                <span class="cl-stat__icon"><i class="feather icon-target"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['total'], 0) }}</p>
                    <p class="cl-stat__label">{{ $observed && $observationsScope ? 'منافسون مرصودون '.$observationsScope : 'إجمالي المنافسين' }}</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--success"><i class="fas fa-check-circle"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['active'], 0) }}</p>
                    <p class="cl-stat__label">مفعَّلون</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--danger"><i class="fas fa-eye-slash"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['inactive'], 0) }}</p>
                    <p class="cl-stat__label">معطَّلون</p>
                </div>
            </div>
            <div class="cl-stat">
                <span class="cl-stat__icon cl-stat__icon--info"><i class="fas fa-binoculars"></i></span>
                <div class="cl-stat__body">
                    <p class="cl-stat__value">{{ \App\Support\LanguageSettings::formatNumber($stats['observations'], 0) }}</p>
                    <p class="cl-stat__label">لهم أرصاد{{ $observationsScope ? ' '.$observationsScope : '' }}</p>
                </div>
            </div>
        </div>

        {{-- ══════════ شريط الإجراءات ══════════ --}}
        @if ($canAddCompetitor)
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-end align-items-center gap-2">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompetitorModal">
                            <i class="fas fa-plus me-1"></i> إضافة منافس
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- ══════════ البحث والتصفية ══════════ --}}
        <div class="card cl-filters">
            <div class="card-body">
                <div class="cl-filters__head">
                    <div class="cl-filters__title">
                        <i class="feather icon-sliders"></i>
                        <span>بحث وتصفية المنافسين</span>
                    </div>
                </div>

                <form method="GET" action="{{ route('clients.competitors.index') }}">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">اسم المنافس</label>
                            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="بحث بالاسم...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">الحالة</label>
                            <select name="status" class="form-control">
                                <option value="">— كل الحالات —</option>
                                <option value="active" @selected($status === 'active')>مفعَّل</option>
                                <option value="inactive" @selected($status === 'inactive')>معطَّل</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check mb-2">
                                {{-- المخفيُّ يُرسل «0» حين يُلغى التأشير — وإلّا عاد الافتراضُ (المرصودون فقط) مع كلّ بحث --}}
                                <input type="hidden" name="observed" value="0">
                                <input class="form-check-input" type="checkbox" name="observed" value="1" id="observedOnly" @checked($observed)>
                                <label class="form-check-label" for="observedOnly">
                                    المرصودون فقط{{ $observationsScope ? ' '.$observationsScope : '' }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="cl-filters__foot">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> تطبيق البحث
                        </button>
                        <a href="{{ route('clients.competitors.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i> إعادة تعيين
                        </a>
                        @if ($activeFilters > 0)
                            <small class="text-muted ms-auto">
                                <i class="fa fa-info-circle me-1"></i> لديك {{ $activeFilters }} فلتر مفعّل حالياً
                            </small>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ النتائج ══════════ --}}
        <div class="card cl-table-card">
            <div class="cl-table-head">
                <h6 class="cl-table-head__title">
                    <i class="fas fa-list"></i>
                    المنافسون
                    <span class="cl-count">{{ \App\Support\LanguageSettings::formatNumber($competitors->total(), 0) }} منافس</span>
                </h6>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>المنافس</th>
                                <th>ملاحظة</th>
                                <th class="text-center">الأرصاد{!! $observationsScope ? '<small class="d-block text-muted fw-normal">'.e($observationsScope).'</small>' : '' !!}</th>
                                <th class="text-center">الحالة</th>
                                @if ($canEditCompetitor || $canDeleteCompetitor)
                                    <th class="text-center">إجراءات</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($competitors as $competitor)
                                <tr class="{{ $competitor->is_active ? '' : 'text-muted' }}">
                                    <td>
                                        <div class="cl-media">
                                            <span class="cl-media__thumb"><i class="feather icon-target"></i></span>
                                            <div class="cl-media__body">
                                                <strong class="cl-media__title">{{ $competitor->name }}</strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small">{{ $competitor->notes ?: '—' }}</td>
                                    <td class="text-center num">
                                        <span class="cl-state cl-state--neutral">{{ \App\Support\LanguageSettings::formatNumber($competitor->branch_observations_count, 0) }}</span>
                                        @if ($observationsScope && $competitor->observations_count !== $competitor->branch_observations_count)
                                            <small class="d-block text-muted">من {{ \App\Support\LanguageSettings::formatNumber($competitor->observations_count, 0) }} في كل الفروع</small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($competitor->is_active)
                                            <span class="cl-state cl-state--on">مفعَّل</span>
                                        @else
                                            <span class="cl-state cl-state--off">معطَّل</span>
                                        @endif
                                    </td>
                                    @if ($canEditCompetitor || $canDeleteCompetitor)
                                        <td class="text-center">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                                    data-bs-toggle="dropdown" data-bs-display="static"
                                                    aria-expanded="false" aria-label="خيارات">
                                                    <i class="fa fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    @if ($canEditCompetitor)
                                                        {{-- `data-` لا `@json` داخل `onclick="…"`: علامات `"` التي
                                                             يطبعها `@json` تُغلق السمة فلا يعمل الزرّ. --}}
                                                        <a class="dropdown-item" href="javascript:void(0);"
                                                            data-url="{{ route('clients.competitors.update', $competitor->id) }}"
                                                            data-name="{{ $competitor->name }}"
                                                            data-notes="{{ $competitor->notes }}"
                                                            onclick="clEditCompetitor(this.dataset.url, this.dataset.name, this.dataset.notes)">
                                                            <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                        </a>
                                                        <form method="POST" action="{{ route('clients.competitors.toggle', $competitor->id) }}">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item"
                                                                title="{{ $competitor->is_active ? 'يختفي من اختيار المندوب وتبقى أرصاده' : '' }}">
                                                                @if ($competitor->is_active)
                                                                    <i class="fa fa-eye-slash me-2 text-warning"></i>تعطيل
                                                                @else
                                                                    <i class="fa fa-eye me-2 text-primary"></i>تفعيل
                                                                @endif
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @if ($canDeleteCompetitor)
                                                        <div class="dropdown-divider"></div>
                                                        @if ($competitor->observations_count === 0)
                                                            <form method="POST" action="{{ route('clients.competitors.destroy', $competitor->id) }}"
                                                                onsubmit="return confirm('حذف «{{ $competitor->name }}» نهائياً؟')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="dropdown-item text-danger">
                                                                    <i class="fa fa-trash me-2"></i>حذف
                                                                </button>
                                                            </form>
                                                        @else
                                                            <span class="dropdown-item disabled" title="له أرصادٌ محفوظة — عطّله بدل حذفه">
                                                                <i class="fa fa-lock me-2"></i>حذف (له أرصاد)
                                                            </span>
                                                        @endif
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="cl-empty">
                                            <span class="cl-empty__icon"><i class="feather icon-target"></i></span>
                                            @if ($activeFilters > 0)
                                                <p class="cl-empty__title">لا منافسين مطابقين</p>
                                                <p class="cl-empty__hint">غيّر معايير البحث أو أعِد تعيينها.</p>
                                            @elseif ($observed && $observationsScope)
                                                <p class="cl-empty__title">لم يُرصد منافسٌ {{ $observationsScope }}</p>
                                                <p class="cl-empty__hint">
                                                    <a href="{{ route('clients.competitors.index', ['observed' => 0]) }}">اعرض قائمة المنافسين كلَّها</a> لإضافة منافسٍ أو تعديله.
                                                </p>
                                            @else
                                                <p class="cl-empty__title">لا يوجد منافسون بعد</p>
                                                <p class="cl-empty__hint">أضِف أوّل منافس ليظهر في قائمة اختيار المندوب.</p>
                                                @if ($canAddCompetitor)
                                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCompetitorModal">
                                                        <i class="fas fa-plus me-1"></i>إضافة منافس
                                                    </button>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($competitors->hasPages())
                <div class="cl-table-foot">
                    <p class="cl-table-foot__count">
                        عرض {{ $competitors->firstItem() }} إلى {{ $competitors->lastItem() }} من {{ $competitors->total() }} منافس
                    </p>
                    {{ $competitors->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>

        @if ($canAddCompetitor)
            <div class="modal fade" id="addCompetitorModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('clients.competitors.store') }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-plus me-1"></i> إضافة منافس</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-2">
                                    <label class="form-label">اسم المنافس <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required
                                        placeholder="مثال: شركة الأغذية الوطنية">
                                    <small class="cl-help">الاسم التجاري كما يعرفه المندوبون، لا اسم المنتج. اكتبه بصيغةٍ واحدة — هو ما يُجمع عليه التقرير.</small>
                                    @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">ملاحظة</label>
                                    <input type="text" name="notes" class="form-control" value="{{ old('notes') }}"
                                        placeholder="اختياري — ما يميّزه أو نطاق نشاطه">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        @if ($canEditCompetitor)
            <div class="modal fade" id="editCompetitorModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" id="editCompetitorForm">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-edit me-1"></i> تعديل منافس</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-2">
                                    <label class="form-label">اسم المنافس <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="editCompetitorName" class="form-control" required>
                                    <small class="cl-help">تغيير الاسم هنا يغيّره في قائمة الاختيار فقط — أرصادُه المحفوظة تحتفظ بالاسم الذي رُصدت به.</small>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">ملاحظة</label>
                                    <input type="text" name="notes" id="editCompetitorNotes" class="form-control">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('scripts')
    <script>
        /* الرابط يأتي جاهزاً من `route()` في الصفّ نفسه لا يُركَّب هنا:
           مسارات هذه الوحدة تحمل بادئة اللغة، وتركيبُها يدوياً يُنتج مساراً
           بلا لغةٍ يُعاد توجيهه أو يُرفض. */
        function clEditCompetitor(actionUrl, name, notes) {
            const form = document.getElementById('editCompetitorForm');
            form.action = actionUrl;
            document.getElementById('editCompetitorName').value = name || '';
            document.getElementById('editCompetitorNotes').value = notes || '';
            new bootstrap.Modal(document.getElementById('editCompetitorModal')).show();
        }

        @if ($canAddCompetitor && $errors->has('name') && old('_method') === null)
            /* خطأ تحقّقٍ في الإضافة: النافذة كانت مغلقة بعد إعادة التوجيه — تُفتح لتظهر الرسالة */
            document.addEventListener('DOMContentLoaded', function () {
                new bootstrap.Modal(document.getElementById('addCompetitorModal')).show();
            });
        @endif
    </script>
@endsection
