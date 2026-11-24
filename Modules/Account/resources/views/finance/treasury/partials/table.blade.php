{{--
    جدول الخزائن والحسابات البنكية.

    كان ملفوفاً بـ `dataTables_wrapper` و`DataTables_Table_0_wrapper` بلا أي
    مكتبة DataTables على الصفحة — غلافان لا يفعلان شيئاً. وكان يرسم لكل
    صفٍّ نافذةَ حذفٍ كاملة داخل `<tbody>`: عنصر `<div>` بين `<tr>` و`<tr>`،
    وهو ترميزٌ غير صالح ينقله المتصفّح خارج الجدول من تلقائه. صارت نافذةً
    واحدة تُملأ من بيانات الزرّ المضغوط.

    والرصيد كان `number_format(...)` بلا رمز عملة أصلاً — رقمٌ عارٍ لا يُقرأ.
--}}

@if ($treasuries->count() > 0)
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-briefcase"></i> الخزائن والحسابات البنكية
            <span class="stk-count">
                {{ method_exists($treasuries, 'total') ? $treasuries->total() : $treasuries->count() }}
            </span>
        </h6>

        @include('stock::components.per-page', [
            'onchange' => "window.location.href='"
                . route('treasury.index') . '?' . http_build_query(request()->except('per_page', 'page'))
                . "&per_page=' + this.value",
        ])
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0" id="treasuriesTable">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>الوصف</th>
                    <th class="text-center">الرصيد</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center" style="width:90px">إجراءات</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($treasuries as $treasury)
                    @php
                        $isBank = $treasury->type_accont != 0;
                        $isActive = $treasury->is_active == 1;
                    @endphp

                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb">
                                    <i class="feather icon-{{ $isBank ? 'credit-card' : 'archive' }}"></i>
                                </span>
                                <span class="stk-media__body">
                                    <span class="stk-media__title">{{ $treasury->name }}</span>
                                    <span class="stk-media__sub">{{ $isBank ? 'حساب بنكي' : 'خزينة' }}</span>
                                </span>
                            </div>
                        </td>

                        <td>{{ $treasury->description ?: '—' }}</td>

                        <td class="text-center">
                            <strong>@money($treasury->balance ?? 0)</strong>
                        </td>

                        <td class="text-center">
                            <span class="stk-state {{ $isActive ? 'stk-state--on' : 'stk-state--off' }}">
                                {{ $isActive ? 'نشط' : 'غير نشط' }}
                            </span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown stk-actions">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown"
                                    aria-expanded="false" aria-label="إجراءات">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('treasury.show', $treasury->id) }}">
                                        <i class="feather icon-eye"></i> عرض
                                    </a>

                                    {{--
                                        كان الشرطُ `! auth()->user()->isEmployee()`:
                                        أيُّ مستخدمٍ حسابُه مربوطٌ بسجلّ موظف لا
                                        يرى تعديلاً ولا تحويلاً ولا تفعيلاً ولا
                                        حذفاً — والمديرُ والمالكُ حساباتُهم
                                        مربوطةٌ بسجلّات موظفين كلُّها، فلم يكن
                                        أحدٌ يقدر على تعديل خزينة. البوّابةُ
                                        صلاحيةٌ كما في `TreasuryController`، لا
                                        ارتباطُ المستخدم بموظف.
                                    --}}
                                    @if (!$isBank)
                                        @permission('account.treasury.edit')
                                            <a class="dropdown-item" href="{{ route('treasury.edit', $treasury->id) }}">
                                                <i class="feather icon-edit-2"></i> تعديل
                                            </a>
                                        @endpermission
                                    @else
                                        {{-- الحسابُ البنكيّ يُعدَّل بشاشته، وهي تصل بمعرّف صفّ الخزينة --}}
                                        @permission('account.treasury.bank_edit')
                                            @if (!empty($bankTreasuryIds[$treasury->id]))
                                                <a class="dropdown-item"
                                                    href="{{ route('treasury.edit_account_bank', $bankTreasuryIds[$treasury->id]) }}">
                                                    <i class="feather icon-edit-2"></i> تعديل
                                                </a>
                                            @endif
                                        @endpermission
                                    @endif

                                    @permission('account.treasury_transfers.create')
                                        <a class="dropdown-item"
                                            href="{{ route('treasury.transferCreate', $treasury->id) }}">
                                            <i class="feather icon-repeat"></i> تحويل
                                        </a>
                                    @endpermission

                                    @permission('account.treasury.toggle_status')
                                        <a class="dropdown-item"
                                            href="{{ route('treasury.updateStatus', $treasury->id) }}">
                                            <i class="feather icon-{{ $isActive ? 'slash' : 'check' }}"></i>
                                            {{ $isActive ? 'تعطيل' : 'تفعيل' }}
                                        </a>
                                    @endpermission

                                    @permission('account.treasury.delete')
                                        <div class="dropdown-divider"></div>

                                        <button type="button" class="dropdown-item text-danger"
                                            data-bs-toggle="modal" data-bs-target="#deleteTreasuryModal"
                                            data-treasury-name="{{ $treasury->name }}"
                                            data-treasury-action="{{ route('treasury.destroy', $treasury->id) }}">
                                            <i class="feather icon-trash-2"></i> حذف
                                        </button>
                                    @endpermission
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('account::finance.treasury.partials.pagination', ['treasuries' => $treasuries])

    {{-- نافذة حذفٍ واحدة لكل الصفوف — تُملأ من سمات الزرّ المضغوط --}}
    <div class="modal fade" id="deleteTreasuryModal" tabindex="-1" aria-labelledby="deleteTreasuryModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteTreasuryModalLabel">حذف خزينة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-0">
                        ستُحذف الخزينة <strong data-treasury-name>—</strong> وكل ارتباطها بالحركات. لا رجوع عن هذا.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <form method="POST" data-treasury-form>
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="feather icon-trash-2"></i> تأكيد الحذف
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('deleteTreasuryModal').addEventListener('show.bs.modal', function(event) {
            var trigger = event.relatedTarget;
            if (!trigger) return;

            this.querySelector('[data-treasury-name]').textContent = trigger.dataset.treasuryName || '—';
            this.querySelector('[data-treasury-form]').action = trigger.dataset.treasuryAction || '#';
        });
    </script>
@else
    <div class="stk-empty">
        <span class="stk-empty__icon"><i class="feather icon-briefcase"></i></span>
        <h6 class="stk-empty__title">لا خزائن مطابقة</h6>
        <p class="stk-empty__hint">
            جرّب توسيع الفلاتر أو أعِد تعيينها — أو أضِف خزينةً جديدة.
        </p>

        <a href="{{ route('treasury.create') }}" class="btn btn-primary mt-1">
            <i class="feather icon-plus"></i> خزينة جديدة
        </a>
    </div>
@endif
