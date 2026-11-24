{{--
    جدول العملاء — يُعاد تصييره وحده في طلبات AJAX.

    ══════════════════ من بطاقات إلى جدول ══════════════════

    كانت الشاشة تعرض بطاقةً لكل عميل: عمودان في السطر، وفي كل بطاقة ترويسةٌ
    وشبكةُ اتصال وقسمُ موقعٍ وقسمُ تواريخ ورسمٌ بيانيٌّ شهريّ بـChart.js.
    فالعميل الواحد يشغل نحو أربعمئة بكسل، وعشرون عميلاً صفحةٌ لا تنتهي —
    ولا تُقارَن سطراً بسطر ولا تُفرَز بالعين.

    صار جدولاً على قياس «جدول الموردين»
    (`supplier_management/partials/suppliers_table.blade.php`): عمودٌ رئيسيّ
    بسطرين، وشارةُ حالة، وزرُّ ثلاث نقاطٍ يفتح قائمة الإجراءات.

    **ما سقط مع البطاقات**: الرسمُ البيانيّ الشهريّ لكل عميل. صفٌّ في جدولٍ
    لا يسع رسماً، والتصنيفُ الشهريّ يبقى شارةً في عمود «التصنيف». ومعه سقط
    نداءان لمكتبتَي Chart.js من CDN كانا يُحمَّلان في كل فتحةٍ للشاشة.

    **ما بقي كما هو** لأن الشيفرة تقرؤه:
      • `.client-card` و`data-client-id` على الصفّ — يقرؤهما التصدير.
      • `.hide-from-map-link` و`.delete-client` ومعطياتُهما.
      • كتلة التصدير أسفل الملفّ — أُعيدت قراءتُها من خلايا الجدول
        بـ`data-col` بدل `:nth-child` الهشّ الذي كان يعدّ مواضع في بطاقة.

    ما يأتي من الإعدادات:
      • العملة والكسور   ← «العملات»
      • مدّة الإخفاء     ← «إعدادات وحدات العملاء» (كانت «24 ساعة» نصّاً)
      • الألوان والخط    ← «مظهر النظام» عبر `--app-*`

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $clients
--}}
@once
    @push('styles')
        <style>
            /* شارةُ نوع العقدة — نفسُ ألوان شجرة الفروع
               (`Modules/Branches/resources/views/partials/_branch_tree_node.blade.php`)
               كي يقرأ المستخدمُ «منطقة» بنفس اللون في الشاشتين */
            .cl-node-type {
                display: inline-block;
                padding: 0 .35rem;
                border-radius: .25rem;
                font-size: .7rem;
                line-height: 1.6;
                background: #e5e7eb;
                color: #374151;
                margin-inline-start: .25rem;
                vertical-align: middle;
            }

            .cl-node-type--branch { background: #dbeafe; color: #1e40af; }
            .cl-node-type--region { background: #dcfce7; color: #166534; }
            .cl-node-type--group { background: #cffafe; color: #155e75; }
            .cl-node-type--outlet { background: #fef3c7; color: #92400e; }
        </style>
    @endpush
@endonce

@php
    use App\Helpers\ClientSettingsHelper;
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;

    $unknown = 'غير محدد';
    $hideHours = ClientSettingsHelper::mapHideDurationHours();
    $canEdit = hasPermission('clients_edit_data');
    $canDelete = hasPermission('clients_delete');
    $canViewStatement = hasPermission('clients_view_statement');
    $isEmployee = auth()->user()->isEmployee();
    $today = now()->toDateString();
    // يُلزَم بالمرور بالزيارة قبل «عرض» — راجع ClientController::show()
    // ونفس الشرط هناك حرفاً بحرف: الإلزام (clients_must_visit_before_view)
    // ما لم يُعفَ منه فرداً (clients_exempt_visit_before_view)، بلا فحص دور.
    // لو اختلّ التطابق ظهر رابط «عرض» ثم أعاده المتحكّم إلى الزيارة — أو العكس.
    $mustVisit = hasPermission('clients_must_visit_before_view')
        && ! hasPermission('clients_exempt_visit_before_view');

    /* عملاءُ لهم عودةٌ مجدولة اليوم لم تُنجَز — استعلامٌ واحدٌ لكل الجدول لا
       واحدٌ لكل صفّ. راجع `EmployeeClientVisit::pendingItineraryClientIdsFor()`
       ونفس الشرط في `ClientController::show()`. */
    $pendingReturnClientIds = $mustVisit
        ? \App\Models\EmployeeClientVisit::pendingItineraryClientIdsFor((int) auth()->id())
        : collect();
@endphp

<div class="table-responsive">
    <table class="table table-hover table-sm mb-0">
        <thead>
            <tr>
                <th class="text-center" style="width: 90px;">الكود</th>
                <th>العميل</th>
                {{-- حُذف عمود «موظف العميل»: الإسنادُ متعدّدٌ ولا يحتمل صفُّ
                     الجدول قائمةً منه، ويُقرأ في شاشة العميل تحت «الموظفون
                     المعيَّنون». وقبله كان العمودُ يعرض `clients.frist_name`
                     — اسمَ جهة الاتصال لا الموظف — فيقرأ الجميع «غير محدد». --}}
                <th>الهاتف</th>
                <th>التصنيف</th>
                <th>تابع لـ</th>
                <th class="text-center">المستحقّ</th>
                <th class="text-center">الحالة</th>
                <th class="text-center">المسافة</th>
                <th class="text-center">التسجيل</th>
                <th class="text-center" style="width: 70px;">خيارات</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($clients as $client)
                @php
                    $clientData = $clientsData[$client->id] ?? null;
                    $due = $clientDueBalances[$client->id] ?? 0;
                    $currentMonth = now()->format('m');
                    $monthlyGroup =
                        $clientData['monthly_groups'][$currentMonth]['group'] ?? ($clientData['group'] ?? 'D');

                    /* الحالة المعروضة: حالةُ العميل، إلّا أن يكون المستخدمُ
                       موظفاً أبلغ مشرفَه بحالةٍ أخرى فتُعرض له حالتُه هو. */
                    $statusToShow = $client->status_client;

                    if ($isEmployee) {
                        $lastNote = $client
                            ->appointmentNotes()
                            ->where('employee_id', auth()->id())
                            ->where('process', 'إبلاغ المشرف')
                            ->whereNotNull('employee_view_status')
                            ->latest()
                            ->first();

                        if ($lastNote) {
                            $statusToShow = $statuses->find($lastNote->employee_view_status);
                        }
                    }

                    $distanceInfo = $clientDistances[$client->id] ?? null;
                    $distanceText = 'غير متاح';
                    $distanceTone = '';

                    if ($distanceInfo && ($distanceInfo['distance'] ?? null) !== null) {
                        $distanceText = number_format($distanceInfo['distance'], 1) . ' كم';
                        $distanceTone = $distanceInfo['within_range'] ? 'text-success' : 'text-danger';
                    } elseif ($distanceInfo) {
                        $distanceText = $distanceInfo['message'] ?? 'غير متاح';
                    }

                    // زيارةٌ أُتمّت اليوم لهذا العميل — تفكّ قفل «عرض» عن
                    // الموظف المُلزَم (`$mustVisit`) بعد أن يفي بالشرط، فلا
                    // يُحبَس في حلقة «سجّل زيارة» كل مرّة يعود لعميلٍ زاره
                    // بالفعل اليوم. نفس الشرط بالضبط في ClientController::show()
                    // ...ما لم تكن له عودةٌ مجدولة اليوم لم تُنجَز: عندها يبقى
                    // القفل قائماً لأنّ المطلوب زيارةٌ ثانية لا عرضُ الملفّ
                    $hasCompletedVisitToday = $mustVisit
                        && ! $pendingReturnClientIds->contains((int) $client->id)
                        && \App\Models\Visit::where('employee_id', auth()->id())
                            ->where('client_id', $client->id)
                            ->whereDate('visit_date', $today)
                            ->where('stage', \App\Models\Visit::STAGE_COMPLETED)
                            ->exists();

                    // زيارةٌ مفتوحة اليوم (بدأت أو لم تبدأ بعد) — إن وُجدت
                    // يذهب رابط «تسجيل زيارة» مباشرةً لمساحة عملها بلا مرورٍ
                    // بـ registerVisit() (لا حاجة لإنشاء زيارةٍ جديدة أصلاً)؛
                    // التسمية تبقى واحدة، الوجهة فقط تختلف حسب الحالة.
                    $activeVisit = ($mustVisit && ! $hasCompletedVisitToday)
                        ? \App\Models\Visit::where('employee_id', auth()->id())
                            ->where('client_id', $client->id)
                            ->whereDate('visit_date', $today)
                            ->whereNull('departure_time')
                            ->first()
                        : null;

                    // موقعُ العميل التنظيميّ في الشجرة ونوعُه — يُقرأ مرّتين
                    // في هذا الصف (المسار تحت الاسم، والعقدة في عمود الفرع)
                    $treeInfo = $client->branchTreeDisplayPaths();
                @endphp

                <tr class="client-card" data-client-id="{{ $client->id }}">
                    <td class="text-center num" data-col="code">
                        <span class="client-code-badge">{{ $client->code }}</span>
                    </td>

                    <td data-col="trade_name">
                        <a href="{{ route('clients.show', $client->id) }}">
                            <strong class="client-title">{{ $client->trade_name }}</strong>
                        </a>
                        {{-- مسارُ الفرع كاملاً من الجذر، لا اسمُ المنطقة وحده:
                             نفسُ ما تعرضه شاشةُ العميل تحت «مسار الفرع»
                             (`Client::branchTreeDisplayPaths()`)، فاسمٌ واحد
                             مثل «الصحافة» لا يقول تحت أيّ فرعٍ يقع. --}}
                        @php $rowBranchPath = $treeInfo['main'] ?? null; @endphp
                        @if ($rowBranchPath)
                            <br>
                            <small class="text-muted" title="{{ $rowBranchPath }}">
                                <i class="fas fa-sitemap"></i>
                                {{ $rowBranchPath }}
                            </small>
                        @endif
                    </td>

                    <td class="num" data-col="phone">{{ $client->phone ?: $unknown }}</td>

                    <td data-col="category">
                        <span class="cl-state cl-state--neutral">
                            {{ optional($client->categoriesClient)->name ?: 'غير مصنف' }}
                        </span>
                        <small class="text-muted d-block">{{ $monthlyGroup }}</small>
                    </td>

                    {{-- العمودُ يعرض **العقدة التنظيمية** التي نُسب إليها العميل
                         ونوعَها — منطقةً كانت أو فرعاً أو منفذاً — لا الفرعَ
                         المحاسبيّ وحدَه. فقائمةٌ فيها ٣٠٠ عميلٍ موزّعين على
                         مستوياتٍ مختلفة كان كلُّهم يظهرون باسم فرعٍ واحد.
                         والفرعُ المحاسبيّ يظهر تحته حين يختلف عنها. --}}
                    <td data-col="branch">
                        @if ($treeInfo['node'])
                            {{ $treeInfo['node']->name }}
                            <span class="cl-node-type cl-node-type--{{ $treeInfo['type'] }}">{{ $treeInfo['type_label'] }}</span>
                            @php
                                /* الفرعُ المحاسبيّ **يُشتقّ** من العقدة، ولا يُقرأ من
                                   `clients.branch_id` مباشرةً: صفوفٌ سابقة للفصل ما
                                   زال العمودُ فيها يحمل الورقةَ القديمة — مجموعةً أو
                                   منطقةً — فكان السطرُ يعرض «القيروان» بوصفه فرعاً
                                   محاسبياً وهو مجموعةٌ لا فرع. */
                                $accountingBranch = \App\Models\Branch::findCached(
                                    $treeInfo['node']->resolveAccountingBranchId()
                                );
                            @endphp
                            @if ($accountingBranch && (int) $accountingBranch->id !== (int) $treeInfo['node']->id)
                                <small class="text-muted d-block" title="الفرع الذي تُقيَّد عليه فواتيره وحساباته">
                                    <i class="fas fa-book"></i> {{ $accountingBranch->name }}
                                </small>
                            @endif
                        @else
                            {{ $unknown }}
                        @endif
                    </td>

                    {{-- المستحقّ بعملة النظام وكسورها، واتجاهُه بلغةٍ محاسبية —
                         الرقم وحده يقرؤه كثيرون بعكس اتجاهه --}}
                    <td class="text-center num" data-col="due">
                        {{ CurrencySettings::format(abs((float) $due)) }}
                        @if ((float) $due != 0)
                            <small class="text-muted d-block">{{ (float) $due > 0 ? 'مدين' : 'دائن' }}</small>
                        @endif
                    </td>

                    <td class="text-center" data-col="status">
                        @if ($statusToShow)
                            {{-- لونُ الحالة يأتي من صفّها في «حالات العملاء»،
                                 فلا اسمَ حالةٍ مكتوبٌ هنا ولا لونَ مثبَّت --}}
                            <span class="cl-state"
                                style="background: color-mix(in srgb, {{ $statusToShow->color ?: 'var(--app-muted)' }} 14%, transparent);
                                       color: {{ $statusToShow->color ?: 'var(--app-muted)' }};">
                                {{ $statusToShow->name }}
                            </span>
                        @else
                            <span class="cl-state cl-state--neutral">{{ $unknown }}</span>
                        @endif
                    </td>

                    <td class="text-center num {{ $distanceTone }}" data-col="distance">{{ $distanceText }}</td>

                    <td class="text-center num" data-col="created_at">
                        {{ LanguageSettings::formatDate($client->created_at) ?: '—' }}
                    </td>

                    <td class="text-center">
                        <div class="dropdown">
                            {{-- `data-bs-display="static"` يمنع Popper من تولّي
                                 الموضع، فيبقى الحسابُ لـ`client::partials.table-menu`
                                 وحده. بلا هذه السمة يسبق Popper الجزئيةَ ويكتب
                                 `transform` فوق ما وضعَته — فتُقصّ القائمة عند
                                 التمرير الأفقي للجدول. --}}
                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                data-bs-toggle="dropdown" data-bs-display="static"
                                aria-expanded="false" aria-label="خيارات">
                                <i class="fa fa-ellipsis-v"></i>
                            </button>

                            <div class="dropdown-menu dropdown-menu-end">
                                @if ($mustVisit && ! $hasCompletedVisitToday)
                                    {{-- تسميةٌ واحدة، ووجهةٌ تختلف حسب الحالة: زيارةٌ مفتوحة
                                         بالفعل → مساحة عملها مباشرة (بلا مرورٍ بـ registerVisit
                                         الذي لا يفيد هنا إذ الزيارة موجودة أصلاً)؛ لا زيارة بعد
                                         → registerVisit() تُنشئها ثم تُحوِّل لمساحة العمل. --}}
                                    {{-- `openVisitWorkspace` لا `registerVisit`: المندوب
                                         ذاهبٌ إلى الزيارة لا إلى «تسجيلها»، والرابط
                                         الوسيط لا يعرض شيئاً — يُنشئ ويُحوّل. --}}
                                    <a class="dropdown-item" href="{{ $activeVisit ? route('clients.visitWorkspace', ['client' => $client->id, 'visit' => $activeVisit->id]) : route('clients.openVisitWorkspace', $client->id) }}">
                                        <i class="fa fa-walking me-2 text-info"></i>تسجيل زيارة
                                    </a>
                                @else
                                    {{-- إمّا غير مُلزَمٍ بالزيارة أصلاً، أو أتمّها اليوم بالفعل --}}
                                    <a class="dropdown-item" href="{{ route('clients.show', $client->id) }}">
                                        <i class="fa fa-eye me-2 text-primary"></i>عرض
                                    </a>
                                @endif

                                @if ($canViewStatement)
                                    <a class="dropdown-item" href="{{ route('clients.statement', $client->id) }}">
                                        <i class="fa fa-file-invoice-dollar me-2 text-info"></i>كشف الحساب
                                    </a>
                                @endif

                                @if ($canEdit)
                                    <a class="dropdown-item" href="{{ route('clients.edit', $client->id) }}">
                                        <i class="fa fa-edit me-2 text-success"></i>تعديل
                                    </a>
                                @endif

                                @permission('clients_hide_from_map')
                                    <a class="dropdown-item text-warning hide-from-map-link" href="#"
                                        data-client-id="{{ $client->id }}"
                                        data-client-name="{{ $client->trade_name }}">
                                        <i class="fa fa-eye-slash me-2 text-warning"></i>
                                        إخفاء من الخريطة ({{ $hideHours }} ساعة)
                                    </a>
                                @endpermission

                                @if ($canDelete)
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger delete-client" href="#"
                                        data-id="{{ $client->id }}">
                                        <i class="fa fa-trash me-2"></i>حذف
                                    </a>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">
                        <div class="cl-empty">
                            <span class="cl-empty__icon"><i class="fas fa-users"></i></span>
                            <p class="cl-empty__title">لا يوجد عملاء</p>
                            <p class="cl-empty__hint">
                                لا عملاء مطابقون لمعايير البحث، أو لا عملاء ضمن نطاق صلاحيتك.
                            </p>
                            @permission('clients_add_client')
                                <a href="{{ route('clients.create') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i>إضافة عميل
                                </a>
                            @endpermission
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
    (function() {
        function downloadWorkbook(workbook, filename) {
            const wbout = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
            const blob = new Blob([wbout], { type: 'application/octet-stream' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }

        const COLS = ['id', 'code', 'trade_name', 'phone', 'branch', 'category', 'due', 'status', 'created_at'];

        function exportClientsToExcel(rows, filename = 'clients.xlsx') {
            if (!rows || rows.length === 0) {
                alert('لا توجد بيانات للتصدير');
                return;
            }

            const data = [COLS];
            rows.forEach(r => data.push(COLS.map(k => r[k] ?? '')));

            const ws = XLSX.utils.aoa_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Clients');
            downloadWorkbook(wb, filename);
        }

        /* القراءة بـ`data-col` لا بـ`:nth-child`: المواضع كانت تُعَدّ في
           بطاقةٍ، فأيّ حقلٍ يُضاف أو يُخفى بصلاحية يُزيح البقيّة ويُصدَّر
           عمودٌ مكان آخر بلا أن يُلاحَظ. */
        function readRow(tr) {
            const cell = name => tr.querySelector('[data-col="' + name + '"]');
            const text = name => (cell(name)?.textContent || '').trim().replace(/\s+/g, ' ');

            return {
                id: tr.getAttribute('data-client-id') || '',
                code: text('code'),
                trade_name: (tr.querySelector('.client-title')?.textContent || '').trim(),
                phone: text('phone'),
                branch: text('branch'),
                category: text('category'),
                due: text('due'),
                status: text('status'),
                created_at: text('created_at'),
            };
        }

        /* `#exportAllClientsBtn` لا وجودَ له في أي شاشة — مستمعٌ ميّتٌ كان
           في نسخة البطاقات أيضاً. وتصديرُ الجدول كلِّه يقوم به
           `#exportExcelBtn` في شريط الإجراءات، فلا يُربَط هنا ثالثاً.
           و`window.exportClientsTable` تبقى متاحةً لمن أراد النداءَ يدوياً. */
        window.exportClientsTable = function(filename) {
            const rows = [...document.querySelectorAll('tr.client-card')].map(readRow);
            exportClientsToExcel(rows, filename || 'clients_export_' + new Date().toISOString().slice(0, 10) + '.xlsx');
        };

        document.addEventListener('click', function(e) {
            const el = e.target.closest('.export-single-client');
            if (!el) return;
            e.preventDefault();
            try {
                exportClientsToExcel([JSON.parse(el.getAttribute('data-client'))],
                    'client_' + (el.getAttribute('data-id') || 'export') + '.xlsx');
            } catch (err) {
                console.error('Failed to export single client', err);
                alert('حدث خطأ أثناء التصدير');
            }
        });
    })();
</script>
