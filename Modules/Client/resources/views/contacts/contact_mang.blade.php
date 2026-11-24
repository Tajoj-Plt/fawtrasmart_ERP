{{--
    قائمة جهات اتصال العملاء — أُعيد بناؤها بنظام تصميم الوحدة الحقيقي
    (`.cl-scope` + `partials/settings-theme` + `components/page-head`) بدل
    نظامٍ مخترَعٍ كان يستعمل `@extends('master')` (تخطيط لا وجود له أصلاً في
    هذه الوحدة) ومتغيّرات CSS مثل `--gradient-primary` غير معرَّفةٍ إطلاقاً
    فتسقط صامتة، وسكربتاً منتهياً بوسم `</scpit>` مكسوراً يمنع تنفيذ كل
    الجافاسكربت في القسم (تصدير Excel وتحديد الكل كانا معطَّلين تماماً).

    المتحكّم (`ClientController::contacts()`) يطبّق نطاقَ «إدارة العملاء»
    نفسَه: صلاحيةُ العرض، ثمّ فرعُ الشريط العلويّ وما تحته — فكلُّ فرعٍ يرى
    جهاتِ اتصاله وحده، والفرعُ الرئيسيّ يرى المنشأة كلَّها. والصفُّ عميلٌ واحد
    يعرض هاتفه وجوّاله و«تابع لـ»، ثمّ جهاتِ الاتصال الإضافية المحفوظة في
    ملفّه (جدول `contacts`) التي تختلف عن بيانات العميل نفسه.
--}}
@extends('client::master')

@section('title')
    جهات اتصال العملاء
@stop

@section('content')
@include('client::partials.settings-theme')

{{-- قوائم إجراءات الصفوف تخرج من قصّ البطاقة والجدول --}}
@include('client::partials.table-menu')

<div class="cl-scope">
    @php
        $pageActions = '<a href="'.route('clients.create').'" class="btn btn-primary">'
            .'<i class="feather icon-plus"></i> <span>إضافة عميل جديد</span></a>';
    @endphp

    @include('client::components.page-head', [
        'title' => 'جهات اتصال العملاء',
        'icon' => 'phone',
        'sub' => 'بحثٌ سريع عن بيانات التواصل — الهاتف والاسم التجاري — لكل عميل',
        'crumbs' => [
            'الرئيسية' => route('dashboard'),
            'العملاء' => route('clients.index'),
            'جهات الاتصال' => null,
        ],
        'actions' => $pageActions,
    ])

    @include('client::components.guide', [
        'key' => 'clients.contacts',
        'open' => false,
        'title' => 'ما هذه الشاشة؟',
        'lead' => 'دليل هاتفٍ سريع لكل العملاء — ابحث بالاسم أو الكود أو رقم التواصل بدل فتح ملف كل عميل على حدة.',
        'facts' => [
            'البحث الأساسي يشمل الاسم والكود وأرقام العميل وجهات اتصاله؛ البحث المتقدم يبحث في البريد والهاتف والجوال.',
            'القائمة تتبع الفرع المختار في الشريط العلوي وما تحته — بدّل الفرع هناك لترى جهات اتصال كل فرع وحده؛ الفرع الرئيسي يعرض المنشأة كلها.',
        ],
    ])

    <div class="cl-stats">
        <div class="cl-stat">
            <span class="cl-stat__icon"><i class="fas fa-address-book"></i></span>
            <div class="cl-stat__body">
                <p class="cl-stat__value">{{ isset($clients) ? number_format($clients->total()) : 0 }}</p>
                <p class="cl-stat__label">إجمالي العملاء</p>
            </div>
        </div>
        <div class="cl-stat">
            <span class="cl-stat__icon"><i class="fas fa-sitemap"></i></span>
            <div class="cl-stat__body">
                <p class="cl-stat__value" style="font-size:1rem">
                    {{ $headerNodeId && $headerBranch ? $headerBranch->name : 'كل الفروع' }}
                </p>
                <p class="cl-stat__label">
                    {{ $headerNodeId ? 'الفرع المختار في الشريط العلوي وما تحته' : 'المنشأة كلها' }}
                </p>
            </div>
        </div>
    </div>

    {{-- ===== الفلاتر ===== --}}
    <div class="card cl-filters">
        <div class="cl-filters__head">
            <h4 class="cl-filters__title"><i class="feather icon-search"></i> بحث وتصفية</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('clients.contacts') }}" id="searchForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="search">البحث الأساسي</label>
                            <input type="text" id="search" class="form-control" name="search"
                                   placeholder="الاسم، الكود، رقم الهاتف أو جهة الاتصال..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="advanced_search">البحث المتقدم</label>
                            <input type="text" id="advanced_search" class="form-control" name="advanced_search"
                                   placeholder="بريد إلكتروني، هاتف، أو جوال..." value="{{ request('advanced_search') }}">
                        </div>
                    </div>
                </div>
            </form>
            <div class="cl-filters__foot">
                <button type="submit" form="searchForm" class="btn btn-primary">
                    <i class="feather icon-search"></i> بحث
                </button>
                <a href="{{ route('clients.contacts') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-x"></i> إلغاء
                </a>
                <button type="button" class="btn btn-outline-success ms-auto" id="exportExcelBtn">
                    <i class="feather icon-download"></i> تصدير إلى Excel
                </button>
                <button type="button" class="btn btn-outline-warning d-none" id="exportSelectedBtn">
                    <i class="feather icon-download"></i> تصدير المحدَّد
                </button>
            </div>
        </div>
    </div>

    {{-- ===== الجدول ===== --}}
    <div class="card cl-table-card">
        <div class="cl-table-head">
            <h6 class="cl-table-head__title">
                <i class="fas fa-address-book"></i>
                جهات الاتصال
                @if (isset($clients))
                    <span class="cl-count">{{ $clients->total() }} عميل</span>
                @endif
            </h6>
        </div>

        <div class="card-body p-0">
            @if (isset($clients) && $clients->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="clientsTable">
                        <thead>
                            <tr>
                                <th style="width:40px">
                                    <input type="checkbox" class="form-check-input" id="select-all">
                                </th>
                                <th style="width:110px">الكود</th>
                                <th style="min-width:220px">العميل</th>
                                <th style="min-width:220px">تابع لـ</th>
                                <th style="min-width:150px">أرقام التواصل</th>
                                <th>جهات اتصال أخرى</th>
                                <th class="text-center" style="width:90px">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($clients as $client)
                                @php
                                    /* الأسماءُ والأرقام تُنظَّف للعرض — البياناتُ المستوردة فيها
                                       `" ,0503591607"` و`" ,"` وأرقامٌ هندية؛ انظر `ContactNumbers`. */
                                    $clientName = \Modules\Client\Support\ContactNumbers::name($client->display_name);
                                    $nodePath = optional($client->resolveTreeNode())->lineagePathLabel();
                                    $clientNumbers = \Modules\Client\Support\ContactNumbers::from($client->phone, $client->mobile);

                                    /* جهةُ الاتصال الأساسية تُنشأ نسخةً من العميل نفسه عند
                                       إضافته (`store()`) — فلا تُكرَّر هنا؛ تُعرض الجهاتُ
                                       التي يختلف اسمُها أو رقمُها عن العميل وحدها. */
                                    $otherContacts = $client->contacts
                                        ->map(fn ($c) => [
                                            'name' => \Modules\Client\Support\ContactNumbers::name($c->first_name, $c->last_name),
                                            'numbers' => \Modules\Client\Support\ContactNumbers::from($c->mobile, $c->phone),
                                        ])
                                        ->reject(fn ($c) => ($c['name'] === '' || $c['name'] === $clientName)
                                            && empty(array_diff($c['numbers'], $clientNumbers)))
                                        ->reject(fn ($c) => $c['name'] === '' && empty($c['numbers']));
                                @endphp
                                <tr class="client-row" data-client-id="{{ $client->id }}">
                                    <td>
                                        <input type="checkbox" class="form-check-input client-checkbox"
                                               value="{{ $client->id }}"
                                               data-client-code="{{ $client->code }}"
                                               data-client-name="{{ $clientName }}"
                                               data-client-node="{{ $nodePath }}"
                                               data-client-phone="{{ $clientNumbers[0] ?? '' }}"
                                               data-client-mobile="{{ implode(' - ', array_slice($clientNumbers, 1)) }}">
                                    </td>
                                    <td><span class="cl-count">{{ $client->code }}</span></td>
                                    <td data-col="trade_name">
                                        <div class="cl-media">
                                            <span class="cl-media__thumb"><i class="feather icon-user"></i></span>
                                            <div class="cl-media__body">
                                                <a href="{{ route('clients.show', $client->id) }}" class="cl-media__title">{{ $clientName }}</a>
                                                @if ($client->email)
                                                    <span class="cl-media__sub">{{ $client->email }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($nodePath)
                                            <small class="text-muted" title="{{ $nodePath }}">
                                                <i class="fas fa-sitemap"></i> {{ $nodePath }}
                                            </small>
                                        @else
                                            <span class="text-muted">{{ __('branches.node_filter.unassigned') }}</span>
                                        @endif
                                    </td>
                                    <td class="num">
                                        @if ($clientNumbers)
                                            <div class="cl-tags">
                                                @foreach ($clientNumbers as $number)
                                                    <a href="tel:{{ $number }}" class="cl-state cl-state--neutral" dir="ltr">
                                                        <i class="fas fa-phone"></i> {{ $number }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted">لا يوجد رقم</span>
                                        @endif
                                    </td>
                                    <td>
                                        @forelse ($otherContacts as $contact)
                                            <div class="small text-nowrap">
                                                <i class="feather icon-user text-muted"></i>
                                                {{ $contact['name'] ?: '—' }}
                                                @foreach ($contact['numbers'] as $number)
                                                    · <a href="tel:{{ $number }}" dir="ltr">{{ $number }}</a>
                                                @endforeach
                                            </div>
                                        @empty
                                            <span class="text-muted">—</span>
                                        @endforelse
                                    </td>
                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                    data-bs-toggle="dropdown" data-bs-display="static"
                                                    aria-expanded="false" aria-label="خيارات">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a class="dropdown-item" href="{{ route('clients.show', $client->id) }}">
                                                    <i class="fa fa-eye me-2 text-primary"></i>عرض التفاصيل
                                                </a>
                                                @if ($clientNumbers)
                                                    <a class="dropdown-item" href="tel:{{ $clientNumbers[0] }}">
                                                        <i class="fa fa-phone me-2 text-info"></i>اتصال
                                                    </a>
                                                @endif
                                                @if (hasPermission('clients_edit_data'))
                                                    <a class="dropdown-item" href="{{ route('clients.edit', $client->id) }}">
                                                        <i class="fa fa-edit me-2 text-success"></i>تعديل
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="cl-table-foot">
                    {{ $clients->links('client::partials.link-pagination') }}
                </div>
            @else
                <div class="cl-empty">
                    <span class="cl-empty__icon"><i class="fas fa-address-book"></i></span>
                    <p class="cl-empty__title">لا توجد بيانات</p>
                    <p class="cl-empty__hint">لم يتم العثور على أي عملاء، يرجى إضافة عملاء جدد أو تعديل معايير البحث.</p>
                    <a href="{{ route('clients.create') }}" class="btn btn-primary mt-2">
                        <i class="fas fa-plus-circle me-2"></i>إضافة عميل جديد
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
    @parent
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        (function() {
            const selectAll = document.getElementById('select-all');
            const exportSelectedBtn = document.getElementById('exportSelectedBtn');

            function checkboxes() {
                return document.querySelectorAll('.client-checkbox');
            }

            function updateExportSelectedButton() {
                const checked = document.querySelectorAll('.client-checkbox:checked');
                exportSelectedBtn.classList.toggle('d-none', checked.length === 0);
                if (checked.length > 0) {
                    exportSelectedBtn.innerHTML = '<i class="feather icon-download"></i> تصدير المحدَّد (' + checked.length + ')';
                }
            }

            selectAll?.addEventListener('change', function() {
                checkboxes().forEach(cb => cb.checked = this.checked);
                updateExportSelectedButton();
            });

            checkboxes().forEach(cb => cb.addEventListener('change', updateExportSelectedButton));

            function rowsToSheet(rows, filename) {
                const data = [['الكود', 'العميل', 'تابع لـ', 'الهاتف', 'أرقام أخرى']];
                rows.forEach(r => data.push(r));
                const ws = XLSX.utils.aoa_to_sheet(data);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'جهات الاتصال');
                ws['!cols'] = [{ wch: 12 }, { wch: 32 }, { wch: 40 }, { wch: 15 }, { wch: 15 }];
                XLSX.writeFile(wb, filename);
            }

            function rowOf(cb) {
                return [cb.dataset.clientCode, cb.dataset.clientName, cb.dataset.clientNode, cb.dataset.clientPhone, cb.dataset.clientMobile];
            }

            document.getElementById('exportExcelBtn')?.addEventListener('click', function() {
                const rows = Array.from(checkboxes()).map(rowOf);
                if (!rows.length) { alert('لا توجد بيانات للتصدير'); return; }
                rowsToSheet(rows, 'جهات_اتصال_العملاء_' + new Date().toISOString().split('T')[0] + '.xlsx');
            });

            exportSelectedBtn?.addEventListener('click', function() {
                const rows = Array.from(document.querySelectorAll('.client-checkbox:checked')).map(rowOf);
                if (!rows.length) { alert('يرجى تحديد عملاء للتصدير'); return; }
                rowsToSheet(rows, 'جهات_اتصال_محدّدة_' + new Date().toISOString().split('T')[0] + '.xlsx');
            });
        })();
    </script>
@endsection
