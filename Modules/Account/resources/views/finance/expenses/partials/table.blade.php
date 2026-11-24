{{--
    جدول سندات الصرف — يُحمَّل مع الصفحة ومع نتائج البحث الحيّ معاً.

    كان يحمل ترميز DataTables كاملاً (`dataTables_wrapper`, `sorting_asc`,
    `aria-controls`) بلا أي مكتبة DataTables على الصفحة: أصنافٌ لا تفعل شيئاً
    وسماتُ وصولٍ تَعِد بفرزٍ لا وجود له — قارئ الشاشة يقول «مرتَّب تصاعدياً»
    والعمود غير قابل للفرز أصلاً.

    ورمز العملة كان صورة `Saudi_Riyal.svg` أو نصّ العملة بحسب حقلٍ مخزَّن،
    فلا يتبع «العملات» في الإعدادات. صار `@money` يقرأ عملة السند نفسه.
--}}

@if ($expenses->count() > 0)
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-list"></i> سندات الصرف
            <span class="stk-count">
                {{ method_exists($expenses, 'total') ? $expenses->total() : $expenses->count() }}
            </span>
        </h6>

        @include('stock::components.per-page', [
            'id' => 'perPage',
            'current' => request('per_page', 10),
            'onchange' => 'if (window.loadData) { window.loadData(1, parseInt(this.value)); }',
        ])
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0" id="expensesTable">
            <thead>
                <tr>
                    <th>التصنيف</th>
                    {{-- الحسابُ المدين في القيد — كان غائباً عن الجدول كله،
                         فسندٌ تصنيفُه مسمّى باسم حسابٍ (كـ«جاري ابو فالح»)
                         يُقرأ وكأنه نزل تصنيفاً لا حساباً --}}
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
                @foreach ($expenses as $expense)
                    @php
                        /*
                            سندُ الصرف لا دورةَ اعتمادٍ له: لا عمودَ `status`
                            في جدوله أصلاً (خلافاً لسند القبض). فكان
                            `$expense->status` فارغاً دائماً والشاشةُ تقول
                            «غير معروفة» عن كل سند.

                            والصرفُ يقع بالحفظ، فالسندُ معتمدٌ بوجوده.
                        */
                        [$stateClass, $stateLabel] = ['stk-state--on', 'معتمد'];

                        // $canEdit يُحسب في ExpensesController::getData() — القائمة
                        // مقيَّدة بالفعل بنطاق المستخدم، فتكفي صلاحية account.expenses.edit
                    @endphp

                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-tag"></i></span>
                                <span class="stk-media__body">
                                    <span class="stk-media__title">
                                        {{ $expense->expenses_category->name ?? 'غير محدد' }}
                                        <x-taxable-badge :on="$expense->is_taxable" />
                                    </span>
                                    @if ($expense->attachments)
                                        <span class="stk-media__sub">
                                            <i class="feather icon-paperclip"></i> مرفق
                                        </span>
                                    @endif
                                </span>
                            </div>
                        </td>

                        <td>
                            {{ $expense->account->name
                                ?? optional($expense->supplier)->trade_name
                                ?? '—' }}
                        </td>

                        <td>
                            <span class="stk-num">{{ $expense->date ?? '—' }}</span>
                            <span class="stk-media__sub d-block">
                                بواسطة {{ $expense->createdBy->name ?? 'غير محدد' }}
                            </span>
                        </td>

                        <td>{{ $expense->description ?: '—' }}</td>

                        <td class="text-center">
                            {{--
                                الإجمالي بعد الضريبة هو ما يُصرَف فعلاً — والعمود
                                كان يعرض المبلغ قبلها، فيختلف عمّا في السند وعمّا
                                خرج من الخزينة. والأصلُ يبقى تحته لمن يقارن.
                            --}}
                            <strong>@money($expense->total_amount ?: $expense->amount ?? 0, $expense)</strong>
                            @if (($expense->total_amount ?? 0) > ($expense->amount ?? 0))
                                <span class="stk-media__sub d-block">
                                    الأصل @money($expense->amount ?? 0, $expense)
                                    · ضريبة @money(($expense->total_amount - $expense->amount), $expense)
                                </span>
                            @endif
                        </td>

                        <td>{{ $expense->branch->name ?? ($expense->createdBy->branch->name ?? 'غير محدد') }}</td>

                        <td class="text-center">
                            <span class="stk-state {{ $stateClass }}">{{ $stateLabel }}</span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown stk-actions">
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('expenses.show', $expense->id) }}">
                                        <i class="feather icon-eye"></i> عرض
                                    </a>

                                    @if ($canEdit)
                                        <a class="dropdown-item" href="{{ route('expenses.edit', $expense->id) }}">
                                            <i class="feather icon-edit-2"></i> تعديل
                                        </a>
                                    @endif

                                    <div class="dropdown-divider"></div>

                                    <a class="dropdown-item"
                                        href="{{ route('expenses.print', [$expense->id, 'normal']) }}">
                                        <i class="feather icon-printer"></i> طباعة عادية
                                    </a>
                                    <a class="dropdown-item"
                                        href="{{ route('expenses.print', [$expense->id, 'thermal']) }}">
                                        <i class="feather icon-printer"></i> طباعة حرارية
                                    </a>

                                    @if ($canEdit)
                                        <div class="dropdown-divider"></div>
                                        <button type="button" class="dropdown-item text-danger"
                                            onclick="confirmCancel({{ $expense->id }})">
                                            <i class="feather icon-trash-2"></i> حذف
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

    @include('account::finance.expenses.partials.pagination', ['expenses' => $expenses])
@else
    <div class="stk-empty">
        <span class="stk-empty__icon"><i class="feather icon-file-minus"></i></span>
        <h6 class="stk-empty__title">لا سندات صرف مطابقة</h6>
        <p class="stk-empty__hint">
            جرّب توسيع الفلاتر أو أعِد تعيينها — أو سجّل سند صرفٍ جديداً.
        </p>

        @can('account.expenses.create')
            <a href="{{ route('expenses.create') }}" class="btn btn-primary mt-1">
                <i class="feather icon-plus"></i> سند صرف جديد
            </a>
        @endcan
    </div>
@endif
