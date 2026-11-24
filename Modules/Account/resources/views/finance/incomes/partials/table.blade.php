{{--
    جدول سندات القبض — يُحمَّل مع الصفحة ومع نتائج البحث الحيّ معاً.

    كان يحمل ترميز DataTables كاملاً (`dataTables_wrapper`, `dataTables_length`,
    `sorting_asc`, `aria-controls`) بلا أي مكتبة DataTables على الصفحة: أصنافٌ
    لا تفعل شيئاً وسماتُ وصولٍ تَعِد بفرزٍ لا وجود له — قارئ الشاشة يقول
    «مرتَّب تصاعدياً» والعمود غير قابل للفرز أصلاً. ومُنتقي عدد السجلّات
    صار مكوّن `stock::components.per-page` المشترك.

    ورمز العملة كان صورة `Saudi_Riyal.svg` أو نصّ العملة بحسب حقلٍ مخزَّن،
    فلا يتبع «العملات» في الإعدادات. صار `@money` يقرأ عملة السند نفسه.
--}}

@if ($incomes->count() > 0)
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-list"></i> سندات القبض
            <span class="stk-count">
                {{ method_exists($incomes, 'total') ? $incomes->total() : $incomes->count() }}
            </span>
        </h6>

        @include('stock::components.per-page', [
            'id' => 'perPage',
            'current' => request('per_page', 10),
            'onchange' => 'if (window.loadData) { window.loadData(1, parseInt(this.value)); }',
        ])
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0" id="incomesTable">
            <thead>
                <tr>
                    <th>الحساب</th>
                    <th>التاريخ</th>
                    <th>الوصف</th>
                    <th class="text-center">الإجمالي</th>
                    <th>الفرع</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center" style="width:90px">إجراءات</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($incomes as $income)
                    @php
                        $states = [
                            1 => ['stk-state--on', 'نشط'],
                            2 => ['stk-state--neutral', 'متوقف'],
                            3 => ['stk-state--off', 'غير نشط'],
                        ];

                        [$stateClass, $stateLabel] = $states[$income->status] ?? ['stk-state--neutral', 'غير معروفة'];

                        // $canEdit يُحسب في IncomesController::getData() — القائمة
                        // مقيَّدة بالفعل بنطاق المستخدم، فتكفي صلاحية account.incomes.edit
                    @endphp

                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-hash"></i></span>
                                <span class="stk-media__body">
                                    <span class="stk-media__title">
                                        {{ $income->account->name ?? 'غير محدد' }}
                                        <x-taxable-badge :on="$income->is_taxable" />
                                    </span>
                                    <span class="stk-media__sub">
                                        {{ $income->incomes_category->name ?? 'غير مصنّف' }}
                                    </span>
                                </span>
                            </div>
                        </td>

                        <td>
                            <span class="stk-num">{{ $income->date ?? '—' }}</span>
                            <span class="stk-media__sub d-block">
                                بواسطة {{ $income->user->name ?? 'غير محدد' }}
                            </span>
                        </td>

                        <td>{{ $income->description ?: '—' }}</td>

                        <td class="text-center">
                            {{--
                                الإجمالي بعد الضريبة هو ما يُصرَف فعلاً — والعمود
                                كان يعرض المبلغ قبلها، فيختلف عمّا في السند وعمّا
                                خرج من الخزينة. والأصلُ يبقى تحته لمن يقارن.
                            --}}
                            <strong>@money($income->total_amount ?: $income->amount ?? 0, $income)</strong>
                            @if (($income->total_amount ?? 0) > ($income->amount ?? 0))
                                <span class="stk-media__sub d-block">
                                    الأصل @money($income->amount ?? 0, $income)
                                    · ضريبة @money(($income->total_amount - $income->amount), $income)
                                </span>
                            @endif
                        </td>

                        <td>{{ $income->branch->name ?? ($income->user->branch->name ?? 'غير محدد') }}</td>

                        <td class="text-center">
                            <span class="stk-state {{ $stateClass }}">{{ $stateLabel }}</span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown stk-actions">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown"
                                    aria-expanded="false" aria-label="إجراءات">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('incomes.show', $income->id) }}">
                                        <i class="feather icon-eye"></i> عرض
                                    </a>

                                    @if ($canEdit)
                                        <a class="dropdown-item" href="{{ route('incomes.edit', $income->id) }}">
                                            <i class="feather icon-edit-2"></i> تعديل
                                        </a>
                                    @endif

                                    <div class="dropdown-divider"></div>

                                    <a class="dropdown-item"
                                        href="{{ route('incomes.print', [$income->id, 'normal']) }}">
                                        <i class="feather icon-printer"></i> طباعة عادية
                                    </a>
                                    <a class="dropdown-item"
                                        href="{{ route('incomes.print', [$income->id, 'thermal']) }}">
                                        <i class="feather icon-printer"></i> طباعة حرارية
                                    </a>

                                    @if ($canEdit)
                                        <div class="dropdown-divider"></div>
                                        <button type="button" class="dropdown-item text-danger"
                                            onclick="confirmCancel({{ $income->id }})">
                                            <i class="feather icon-x-circle"></i> إلغاء السند
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('account::finance.incomes.partials.pagination', ['incomes' => $incomes])
@else
    <div class="stk-empty">
        <span class="stk-empty__icon"><i class="feather icon-file-plus"></i></span>
        <h6 class="stk-empty__title">لا سندات قبض مطابقة</h6>
        <p class="stk-empty__hint">
            جرّب توسيع الفلاتر أو أعِد تعيينها — أو سجّل سند قبضٍ جديداً.
        </p>

        @can('account.incomes.create')
            <a href="{{ route('incomes.create') }}" class="btn btn-primary mt-1">
                <i class="feather icon-plus"></i> سند قبض جديد
            </a>
        @endcan
    </div>
@endif
