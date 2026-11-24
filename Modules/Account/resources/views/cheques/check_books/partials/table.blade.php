{{--
    جدول دفاتر الشيكات.

    كان يحمل ترميز DataTables كاملاً بلا مكتبة DataTables على الصفحة، ونافذة
    حذفٍ لكل صفّ بسمات بوتستراب 4 (`data-toggle`, `data-target`,
    `data-dismiss`) — والصفحة تُحمّل بوتستراب 5 الذي يقرأ `data-bs-*`. أي أنّ
    **زرّ الحذف لم يكن يفتح شيئاً**: يُنقر فلا يحدث شيء ولا رسالة.

    وعمود «الشيكات المستخدمة» كان يطبع `العدد/0` — صفرٌ ثابت مكتوب، لا
    يُحصى من الشيكات الصادرة فعلاً من الدفتر.
--}}

@if ($check_books->count() > 0)
    <div class="stk-table-head">
        <h6 class="stk-table-head__title">
            <i class="feather icon-list"></i> الدفاتر
            <span class="stk-count">{{ $check_books->count() }}</span>
        </h6>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead>
                <tr>
                    <th>رقم الدفتر</th>
                    <th>البنك</th>
                    <th class="text-center">المدى المصرفي</th>
                    <th class="text-center">الأوراق</th>
                    <th class="text-center">العملة</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center" style="width:90px">إجراءات</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($check_books as $check_book)
                    @php
                        $leaves = max(0, ($check_book->end_serial_number - $check_book->start_serial_number) + 1);
                    @endphp

                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-book"></i></span>
                                <span class="stk-media__body">
                                    <span class="stk-media__title stk-num">{{ $check_book->cheque_book_number }}</span>
                                    @if ($check_book->notes)
                                        <span class="stk-media__sub">{{ $check_book->notes }}</span>
                                    @endif
                                </span>
                            </div>
                        </td>

                        <td>{{ $check_book->bank->name ?? 'غير محدد' }}</td>

                        <td class="text-center stk-num">
                            {{ $check_book->start_serial_number }} — {{ $check_book->end_serial_number }}
                        </td>

                        <td class="text-center stk-num">{{ number_format($leaves) }}</td>

                        <td class="text-center stk-num">{{ $check_book->currency }}</td>

                        <td class="text-center">
                            @if ($check_book->status == 1)
                                <span class="stk-state stk-state--on">نشط</span>
                            @else
                                <span class="stk-state stk-state--off">غير نشط</span>
                            @endif
                        </td>

                        <td class="text-center">
                            <div class="dropdown stk-actions">
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false" aria-label="إجراءات">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('check_books.show', $check_book->id) }}">
                                        <i class="feather icon-eye"></i> عرض
                                    </a>

                                    @can('account.cheque_books.edit')
                                        <a class="dropdown-item" href="{{ route('check_books.edit', $check_book->id) }}">
                                            <i class="feather icon-edit-2"></i> تعديل
                                        </a>
                                    @endcan

                                    @can('account.cheque_books.delete')
                                        <div class="dropdown-divider"></div>

                                        <form method="POST" action="{{ route('check_books.delete', $check_book->id) }}" class="d-inline"
                                            onsubmit="if (!confirm('حذف دفتر الشيكات «{{ $check_book->cheque_book_number }}»؟ لا رجوع عن هذا.')) return false; this.querySelector('button').disabled = true;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="feather icon-trash-2"></i> حذف
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="stk-table-foot">
        <span class="stk-table-foot__count">
            إجمالي الأوراق:
            <strong class="stk-num">
                {{ number_format($check_books->sum(fn ($b) => max(0, ($b->end_serial_number - $b->start_serial_number) + 1))) }}
            </strong>
        </span>
    </div>
@else
    <div class="stk-empty">
        <span class="stk-empty__icon"><i class="feather icon-book"></i></span>
        <h6 class="stk-empty__title">لا دفاتر شيكات مطابقة</h6>
        <p class="stk-empty__hint">
            @if (request()->hasAny(['keywords', 'status', 'currency', 'from_date', 'to_date']))
                لا نتائج لهذه الفلاتر — جرّب توسيعها أو أعِد تعيينها.
            @else
                سجّل دفتر الشيكات بمداه المصرفي أولاً، فمنه تُصرَف الشيكات وتُرقَّم.
            @endif
        </p>

        @can('account.cheque_books.create')
            <a href="{{ route('check_books.create') }}" class="btn btn-primary mt-1">
                <i class="feather icon-plus"></i> دفتر جديد
            </a>
        @endcan
    </div>
@endif
