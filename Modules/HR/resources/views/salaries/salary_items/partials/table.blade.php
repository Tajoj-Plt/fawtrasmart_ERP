{{--
    جدولُ بنود الرواتب — يُعرض أوّلَ مرّة ويُستبدل عبر AJAX (`SalaryItemsController::index`).

    الأعمدة: البند (اسمُه ووصفُه ورقمُه) · النوع · القيمة (مبلغ أو صيغة) ·
    الحساب · الخضوع (تأمينات / نهاية خدمة) · الاستعمال (عقود / قسائم) ·
    الحالة · الإجراءات.
--}}
@php
    $canShow = hasPermission('salary_items.show');
    $canCreate = hasPermission('salary_items.create');
    $canEdit = hasPermission('salary_items.edit');
    $canDestroy = hasPermission('salary_items.destroy');
    $filtered = collect(request()->only(['name', 'status', 'type', 'is_insurable']))
        ->filter(fn ($v) => $v !== null && $v !== '')
        ->isNotEmpty();
@endphp

<div class="card stk-table-card">
    @if (isset($salaryItems) && $salaryItems->count() > 0)
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-list"></i> تعريفات البنود
                <span class="stk-count">{{ $salaryItems->total() }}</span>
            </h6>
            @include('stock::components.per-page', [
                'id' => 'salaryItemsPerPage',
                'current' => request('per_page', 10),
            ])
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0" id="items-table">
                <thead>
                    <tr>
                        <th>البند</th>
                        <th class="text-center">النوع</th>
                        <th>القيمة</th>
                        <th>الحساب</th>
                        <th class="text-center">
                            الخضوع
                            <i class="feather icon-help-circle stk-tip" title="تأمينات: يدخل الأجرَ الخاضعَ للتأمينات الاجتماعية عند الصرف. نهاية خدمة: يدخل أجرَ احتساب المكافأة."></i>
                        </th>
                        <th class="text-center">
                            الاستعمال
                            <i class="feather icon-help-circle stk-tip" title="عدد العقود والقسائم التي تحمل نسخةً من هذا البند (بالاسم والنوع)"></i>
                        </th>
                        <th class="text-center">الحالة</th>
                        <th class="text-center" style="width: 70px">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($salaryItems as $salaryItem)
                        @php
                            $isAddition = (int) $salaryItem->type === \App\Models\SalaryItem::TYPE_ADDITION;
                            $isActive = (int) $salaryItem->status === \App\Models\SalaryItem::STATUS_ACTIVE;
                            $isFormula = (int) $salaryItem->salary_item_value === \App\Models\SalaryItem::VALUE_TYPE_CALCULATION_FORMULA;
                            $contractsUsage = (int) ($salaryItem->contracts_usage ?? 0);
                            $slipsUsage = (int) ($salaryItem->slips_usage ?? 0);
                        @endphp
                        <tr>
                            <td>
                                <div class="stk-media">
                                    <span class="stk-media__thumb">
                                        <i class="feather icon-{{ $isAddition ? 'plus-circle' : 'minus-circle' }}"></i>
                                    </span>
                                    <span class="stk-media__body">
                                        <span class="stk-media__title">
                                            @if ($canShow)
                                                <a href="{{ route('SalaryItems.show', $salaryItem->id) }}">{{ $salaryItem->name }}</a>
                                            @else
                                                {{ $salaryItem->name }}
                                            @endif
                                        </span>
                                        <span class="stk-media__sub">
                                            #{{ $salaryItem->id }}
                                            @if ($salaryItem->description)
                                                · {{ \Illuminate\Support\Str::limit($salaryItem->description, 60) }}
                                            @endif
                                        </span>
                                    </span>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="stk-state stk-state--{{ $isAddition ? 'on' : 'warn' }}">
                                    {{ $isAddition ? 'مستحق' : 'مستقطع' }}
                                </span>
                            </td>
                            <td>
                                @if ($isFormula)
                                    <code dir="ltr">{{ $salaryItem->calculation_formula ?: '—' }}</code>
                                    <small class="stk-help mt-0">صيغة حسابية</small>
                                @elseif ($salaryItem->amount !== null)
                                    <span class="stk-num">{{ \App\Support\CurrencySettings::format($salaryItem->amount) }}</span>
                                    @if ($salaryItem->reference_value)
                                        <small class="stk-help mt-0">قيمة مرجعية</small>
                                    @endif
                                @else
                                    <span class="text-muted">يُحدَّد في العقد</span>
                                @endif
                            </td>
                            <td>
                                @if ($salaryItem->Account)
                                    <span class="stk-num">{{ $salaryItem->Account->code }}</span>
                                    <small class="stk-help mt-0">{{ $salaryItem->Account->name }}</small>
                                @else
                                    <span class="text-muted">الافتراضي</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($isAddition)
                                    <span class="stk-state stk-state--{{ $salaryItem->is_insurable ? 'neutral' : 'off' }}" title="التأمينات الاجتماعية">
                                        <i class="feather icon-shield"></i> {{ $salaryItem->is_insurable ? 'تأمينات' : 'لا تأمينات' }}
                                    </span>
                                    @if ($salaryItem->in_eos_wage)
                                        <span class="stk-state stk-state--neutral mt-25" title="يدخل أجر نهاية الخدمة">
                                            <i class="feather icon-award"></i> نهاية خدمة
                                        </span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center stk-num">
                                @if ($contractsUsage || $slipsUsage)
                                    <span title="عقود">{{ $contractsUsage }} <small class="text-muted">عقد</small></span>
                                    ·
                                    <span title="قسائم">{{ $slipsUsage }} <small class="text-muted">قسيمة</small></span>
                                @else
                                    <span class="text-muted">غير مستعمل</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="stk-state stk-state--{{ $isActive ? 'on' : 'off' }}">
                                    {{ $isActive ? 'نشط' : 'غير نشط' }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if ($canShow || $canEdit || $canDestroy)
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="dropdown" data-bs-display="static"
                                        aria-expanded="false" aria-label="خيارات">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if ($canShow)
                                        <a class="dropdown-item" href="{{ route('SalaryItems.show', $salaryItem->id) }}">
                                            <i class="fa fa-eye me-2 text-primary"></i>عرض
                                        </a>
                                        @endif
                                        @if ($canEdit)
                                            <a class="dropdown-item" href="{{ route('SalaryItems.edit', $salaryItem->id) }}">
                                                <i class="fa fa-edit me-2 text-success"></i>تعديل
                                            </a>
                                        @endif
                                        @if ($canDestroy)
                                            @if ($canShow || $canEdit)<div class="dropdown-divider"></div>@endif
                                            <a class="dropdown-item text-danger" href="#"
                                                data-toggle="modal" data-target="#modal_DELETE{{ $salaryItem->id }}">
                                                <i class="fa fa-trash me-2"></i>حذف
                                            </a>
                                        @endif
                                    </div>
                                </div>
                                @endif

                                @if ($canDestroy)
                                    <div class="modal fade" id="modal_DELETE{{ $salaryItem->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content text-right">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">حذف بند الراتب</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="mb-1">سيُحذف تعريف البند <strong>{{ $salaryItem->name }}</strong>.</p>
                                                    @if ($contractsUsage)
                                                        <p class="stk-empty__hint mb-0 text-danger">
                                                            البند مستعمَلٌ في {{ $contractsUsage }} عقد — سيُرفض الحذف؛ عطّله بدلاً من ذلك.
                                                        </p>
                                                    @else
                                                        <p class="stk-empty__hint mb-0">نُسَخُ البند في القسائم السابقة تبقى كما هي.</p>
                                                    @endif
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                                                    <form action="{{ route('SalaryItems.destroy', $salaryItem->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger">
                                                            <i class="feather icon-trash-2"></i> حذف
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($salaryItems->hasPages())
            <div class="stk-table-foot">
                <p class="stk-table-foot__count">
                    عرض {{ $salaryItems->firstItem() }}–{{ $salaryItems->lastItem() }} من {{ $salaryItems->total() }}
                </p>
                {{ $salaryItems->links() }}
            </div>
        @endif
    @else
        <div class="card-body">
            <div class="stk-empty">
                <div class="stk-empty__icon"><i class="feather icon-list"></i></div>
                <h5 class="stk-empty__title">لا توجد بنود رواتب</h5>
                <p class="stk-empty__hint">
                    @if ($filtered)
                        لم يطابق البحث أيّ بند — جرّب معايير أوسع
                    @else
                        عرّف الاستحقاقات والاستقطاعات هنا مرّةً، ثم اخترها في العقود والقسائم
                    @endif
                </p>
                @if (! $filtered && $canCreate)
                    <a href="{{ route('SalaryItems.create') }}" class="btn btn-primary mt-1">
                        <i class="feather icon-plus"></i> بند راتب جديد
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
