{{--
    نموذج التكلفة غير المباشرة — تُشغّله شاشتا الإنشاء والتعديل معاً.

    ── ما تغيّر ────────────────────────────────────────────────────────

    كانت الشاشة تقبل كلَّ شيءٍ باليد. صارت شاشةَ **اختيار** والنظامُ يحسب:

      • الحساب         حساباتُ المصروفات وحدها.
      • القيود         قيودُ الحساب المختار في الفترة وحدها، تُجلب تلقائياً.
      • مبلغ القيد     من القيد نفسِه (صافي حركته على الحساب) — لا يُكتب
                       ولا يُعدَّل.
      • مرّة واحدة      قيدٌ دخل توزيعاً آخر لا يظهر هنا.
      • الأوامر        أوامرُ الفترة غير الملغاة وحدها — واختيارُها اختياريّ.
      • بلا تحديد      القيودُ والأوامر تُعرض غير محدَّدة؛ المستخدمُ يحدّد.
      • التوزيع        بحسب الكمية أو التكلفة — يحسبه النظام، ويُحفظ كلُّ
                       أمرٍ مختار.

    والخادم يعيد الحساب كلَّه عند الحفظ
    ([\Modules\Manufacturing\Http\Controllers\IndirectCostsController::buildAllocation])؛
    ما في الشاشة معاينةٌ فورية لا مصدر.

    المتغيّرات: $action، $submitLabel، $accounts، $indirectCost (عند التعديل)
--}}

@php
    $indirectCost = $indirectCost ?? null;
    $isEdit = (bool) $indirectCost;
    $savedItems = $isEdit ? ($indirectCost->indirectCostItems ?? collect()) : collect();

    // المختارُ ابتداءً: ما في الطلب المردود بخطأ، ثمّ المحفوظ
    $selectedEntries = collect(old('restriction_id', $savedItems->whereNotNull('restriction_id')->pluck('restriction_id')->all()))
        ->map(fn ($id) => (int) $id)->values();
    $selectedOrders = collect(old('manufacturing_order_id', $savedItems->whereNotNull('manufacturing_order_id')->pluck('manufacturing_order_id')->all()))
        ->map(fn ($id) => (int) $id)->values();

    // لا تحديدَ تلقائيّاً: القيودُ تُعرض بلا تحديد والمستخدمُ يختار منها
    $autoSelect = false;

    $fromDate = old('from_date', $isEdit ? \Illuminate\Support\Carbon::parse($indirectCost->from_date)->format('Y-m-d') : now()->startOfMonth()->format('Y-m-d'));
    $toDate = old('to_date', $isEdit ? \Illuminate\Support\Carbon::parse($indirectCost->to_date)->format('Y-m-d') : now()->endOfMonth()->format('Y-m-d'));
@endphp

<form id="indirectCostForm" action="{{ $action }}" method="POST">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- ══════════ البيانات الأساسية ══════════ --}}
    <div class="card">
        <div class="card-header">
            <h4 class="card-title"><i class="feather icon-info"></i> بيانات التكلفة</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="account_id" class="stk-req">حساب المصروف</label>
                        <select id="account_id" name="account_id" required class="form-control select2">
                            <option value="">اختر الحساب…</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}"
                                    {{ (string) old('account_id', $indirectCost->account_id ?? '') === (string) $account->id ? 'selected' : '' }}>
                                    {{ $account->code ? $account->code . ' — ' : '' }}{{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                        <span class="stk-help">حسابات المصروفات فقط</span>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="from_date" class="stk-req">التاريخ من</label>
                        <input type="date" id="from_date" name="from_date" required class="form-control"
                               value="{{ $fromDate }}">
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label for="to_date" class="stk-req">التاريخ إلى</label>
                        <input type="date" id="to_date" name="to_date" required class="form-control"
                               value="{{ $toDate }}">
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-group">
                        <label>نوع التوزيع</label>
                        <div class="stk-picks">
                            @foreach ([1 => 'بحسب الكمية', 2 => 'بحسب التكلفة'] as $value => $label)
                                <label class="stk-pick">
                                    <input type="radio" name="based_on" value="{{ $value }}"
                                        {{ (string) old('based_on', $indirectCost->based_on ?? 1) === (string) $value ? 'checked' : '' }}>
                                    <span class="stk-pick__box"><i class="feather icon-check"></i></span>
                                    <span class="stk-pick__label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <span class="stk-help">
                            بحسب الكمية: يُقسم المصروف على مجموع كميات الأوامر. بحسب التكلفة: على مجموع تكاليفها المباشرة.
                        </span>
                    </div>
                </div>
            </div>

            {{-- «تابع لـ» — تُختم مع المستند عند إنشائه --}}
            <div class="row">
                @include('manufacturing::partials.affiliation-field', [
                    'value' => $isEdit ? $indirectCost->branch_id : null,
                    'locked' => $isEdit,
                ])
            </div>
        </div>
    </div>

    {{-- ══════════ قيود الحساب ══════════ --}}
    <div class="card stk-table-card">
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-book"></i> قيود الحساب في الفترة
                <span class="stk-count" id="entriesCount">0</span>
            </h6>

            <div class="d-flex align-items-center" style="gap:.6rem">
                <div class="spinner-border spinner-border-sm text-primary d-none" id="lookupSpinner" role="status"></div>
                <span class="stk-balance stk-num" id="entriesSubtotal">0.00</span>
            </div>
        </div>

        <div class="table-responsive" id="entriesTableWrap" hidden>
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:44px" class="text-center">
                            <input type="checkbox" id="entriesAll" aria-label="تحديد كل القيود">
                        </th>
                        <th style="width:18%">رقم القيد</th>
                        <th style="width:14%">التاريخ</th>
                        <th>البيان</th>
                        <th class="text-center" style="width:18%">مبلغ القيد</th>
                    </tr>
                </thead>
                <tbody id="entriesRows"></tbody>
            </table>
        </div>

        <div class="card-body" id="entriesEmpty">
            <div class="stk-empty">
                <div class="stk-empty__icon"><i class="feather icon-book"></i></div>
                <h5 class="stk-empty__title" id="entriesEmptyTitle">اختر الحساب والفترة</h5>
                <p class="stk-empty__hint" id="entriesEmptyHint">تُجلب قيود الحساب المختار في الفترة تلقائياً</p>
            </div>
        </div>
    </div>

    {{-- ══════════ أوامر التصنيع ══════════ --}}
    <div class="card stk-table-card">
        <div class="stk-table-head">
            <h6 class="stk-table-head__title">
                <i class="feather icon-package"></i> أوامر التصنيع في الفترة
                <span class="stk-count" id="ordersCount">0</span>
                <span class="stk-media__sub">اختياري</span>
            </h6>
            <span class="stk-balance stk-num" id="ordersSubtotal">0.00</span>
        </div>

        <div class="table-responsive" id="ordersTableWrap" hidden>
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:44px" class="text-center">
                            <input type="checkbox" id="ordersAll" aria-label="تحديد كل الأوامر">
                        </th>
                        <th>أمر التصنيع</th>
                        <th style="width:18%">المنتج</th>
                        <th class="text-center" style="width:11%">الحالة</th>
                        <th class="text-center" style="width:11%">الكمية</th>
                        <th class="text-center" style="width:14%">التكلفة المباشرة</th>
                        <th class="text-center" style="width:14%">نصيب الأمر</th>
                    </tr>
                </thead>
                <tbody id="ordersRows"></tbody>
            </table>
        </div>

        <div class="card-body" id="ordersEmpty">
            <div class="stk-empty">
                <div class="stk-empty__icon"><i class="feather icon-package"></i></div>
                <h5 class="stk-empty__title">لا أوامر تصنيع في هذه الفترة</h5>
                <p class="stk-empty__hint">تُعرض الأوامر التي تقع فترتُها ضمن فترة التوزيع، بلا الملغاة</p>
            </div>
        </div>
    </div>

    {{-- ══════════ الإجمالي ══════════ --}}
    <div class="card">
        <div class="card-header">
            <h4 class="card-title"><i class="feather icon-bar-chart-2"></i> الإجمالي</h4>
            <span class="stk-count">{{ App\Support\CurrencySettings::code() }}</span>
        </div>
        <div class="card-body">
            <dl class="stk-def">
                <div class="stk-def__row">
                    <dt>إجمالي القيود المختارة</dt>
                    <dd class="stk-num" id="sumEntries">0.00</dd>
                </div>
                <div class="stk-def__row">
                    <dt id="baseLabel">مجموع كميات الأوامر المختارة</dt>
                    <dd class="stk-num" id="sumBase">0</dd>
                </div>
                <div class="stk-def__row">
                    <dt><strong id="rateLabel">نصيب الوحدة من المصروف</strong></dt>
                    <dd class="stk-num stk-balance" id="unitRate">0.00</dd>
                </div>
            </dl>

            <span class="stk-help">
                المبالغ يحسبها النظام من القيود ولا تُعدَّل. اختيار الأوامر هنا اختياري: إن حدّدت أوامرَ
                وُزّع المصروف عليها، وإن لم تحدّد حُسب نصيبُ الوحدة على أوامر الفترة كلِّها وقتَ اختيار
                التكلفة في أمر التصنيع — فيُحسب نصيبُه تلقائياً ويدخل تكلفتَه.
            </span>
        </div>

        <div class="stk-form-foot">
            <span class="stk-help me-auto">الحقول المعلَّمة بـ<span class="stk-req"></span> إلزامية</span>
            <a href="{{ route('manufacturing.indirectcosts.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-x"></i> إلغاء
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="feather icon-check"></i> {{ $submitLabel }}
            </button>
        </div>
    </div>
</form>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function () {
    const lookupUrl = @json(route('manufacturing.indirectcosts.getRestrictionsByDate'));
    const token = $('meta[name="csrf-token"]').attr('content');
    const exceptId = @json($isEdit ? $indirectCost->id : null);

    let selectedEntries = @json($selectedEntries);
    let selectedOrders = @json($selectedOrders);
    let autoSelect = @json($autoSelect);

    let entries = [];
    let orders = [];
    let request = null;
    let timer = null;

    const esc = value => $('<div>').text(value == null ? '' : value).html();
    const fixed = value => Number(value || 0).toFixed(2);
    const trim = value => String(Number(Number(value || 0).toFixed(4)));
    const basedOn = () => Number($('input[name="based_on"]:checked').val() || 1);

    function entryRow(entry) {
        const checked = selectedEntries.indexOf(entry.id) !== -1;

        return '<tr>'
            + '<td class="text-center"><input type="checkbox" class="js-entry" name="restriction_id[]" value="' + entry.id + '"'
            + (checked ? ' checked' : '') + '></td>'
            + '<td class="stk-num">' + esc(entry.reference_number || ('#' + entry.id)) + '</td>'
            + '<td class="stk-num">' + esc(entry.date) + '</td>'
            + '<td>' + esc(entry.description || '—') + '</td>'
            + '<td class="text-center stk-num">' + fixed(entry.amount) + '</td>'
            + '</tr>';
    }

    function orderRow(order) {
        const checked = selectedOrders.indexOf(order.id) !== -1;

        return '<tr data-order="' + order.id + '">'
            + '<td class="text-center"><input type="checkbox" class="js-order" name="manufacturing_order_id[]" value="' + order.id + '"'
            + (checked ? ' checked' : '') + '></td>'
            + '<td><span class="stk-media__title">' + esc(order.name) + '</span>'
            + ' <span class="stk-media__sub stk-num">#' + esc(order.code) + '</span></td>'
            + '<td>' + esc(order.product || '—') + '</td>'
            + '<td class="text-center">' + esc(order.status) + '</td>'
            + '<td class="text-center stk-num">' + trim(order.quantity) + '</td>'
            + '<td class="text-center stk-num">' + fixed(order.cost) + '</td>'
            + '<td class="text-center stk-num stk-balance js-share">0.00</td>'
            + '</tr>';
    }

    /* المعاينة الفورية — الصيغةُ نفسُها في الخادم (IndirectCostAllocator::distribute) */
    function recalc() {
        selectedEntries = $('.js-entry:checked').map(function () { return Number(this.value); }).get();
        selectedOrders = $('.js-order:checked').map(function () { return Number(this.value); }).get();

        const total = entries
            .filter(entry => selectedEntries.indexOf(entry.id) !== -1)
            .reduce((sum, entry) => sum + Number(entry.amount), 0);

        const byCost = basedOn() === 2;
        const chosen = orders.filter(order => selectedOrders.indexOf(order.id) !== -1);
        const base = chosen.reduce((sum, order) => sum + Math.max(0, Number(byCost ? order.cost : order.quantity)), 0);
        const rate = base > 0 ? total / base : 0;

        let allocated = 0;
        const shares = {};

        chosen.forEach(function (order) {
            shares[order.id] = Math.round(rate * Math.max(0, Number(byCost ? order.cost : order.quantity)) * 100) / 100;
            allocated += shares[order.id];
        });

        // فرقُ التقريب على آخر أمرٍ مختار
        if (chosen.length && base > 0) {
            const last = chosen[chosen.length - 1].id;
            shares[last] = Math.round((shares[last] + (total - allocated)) * 100) / 100;
        }

        $('#ordersRows tr').each(function () {
            const id = Number($(this).data('order'));
            $(this).find('.js-share').text(fixed(shares[id] || 0));
        });

        $('#entriesCount').text(selectedEntries.length + ' / ' + entries.length);
        $('#ordersCount').text(selectedOrders.length + ' / ' + orders.length);
        $('#entriesSubtotal, #sumEntries').text(fixed(total));
        $('#ordersSubtotal').text(fixed(base > 0 ? total : 0));
        $('#sumBase').text(byCost ? fixed(base) : trim(base));
        $('#baseLabel').text(byCost ? 'مجموع التكلفة المباشرة للأوامر المختارة' : 'مجموع كميات الأوامر المختارة');
        $('#rateLabel').text(byCost ? 'نصيب كل 1 من تكلفة الأمر' : 'نصيب الوحدة المنتَجة من المصروف');
        // بلا أوامر مختارة لا نصيبَ يُعرض الآن — يُحسب على أوامر الفترة عند التحميل
        $('#unitRate').text(chosen.length
            ? Number(rate.toFixed(6)).toString()
            : 'يُحسب على أوامر الفترة عند اختيار التكلفة في أمر التصنيع');

        $('#entriesAll').prop('checked', entries.length > 0 && selectedEntries.length === entries.length);
        $('#ordersAll').prop('checked', orders.length > 0 && selectedOrders.length === orders.length);
    }

    function render() {
        $('#entriesRows').html(entries.map(entryRow).join(''));
        $('#ordersRows').html(orders.map(orderRow).join(''));

        $('#entriesTableWrap').prop('hidden', entries.length === 0);
        $('#entriesEmpty').prop('hidden', entries.length > 0);
        $('#ordersTableWrap').prop('hidden', orders.length === 0);
        $('#ordersEmpty').prop('hidden', orders.length > 0);

        recalc();
    }

    /*
        حالةُ الانتظار ظاهرةٌ في البطاقة نفسِها: الردُّ قد يتأخّر ثوانيَ، وكانت
        البطاقةُ تبقى طوالَها على «لا قيود على هذا الحساب» من الطلب السابق —
        فيُظنّ أن القيود لم تُجلب.
    */
    function showLoading() {
        // إفراغُ الجدول يُفرغ التحديدَ معه — يُحفظ ليعود على ما بقي في القائمة
        const keptEntries = selectedEntries;
        const keptOrders = selectedOrders;

        entries = [];
        $('#entriesEmptyTitle').text('جارٍ جلب قيود الحساب…');
        $('#entriesEmptyHint').text('لحظات وتظهر قيود الحساب المختار في الفترة');
        $('#lookupSpinner').removeClass('d-none');
        render();

        selectedEntries = keptEntries;
        selectedOrders = keptOrders;
    }

    // تغييراتٌ متتابعة (حساب ثم تاريخ ثم تاريخ) ⟵ طلبٌ واحد بعد أن تهدأ
    function scheduleLookup() {
        clearTimeout(timer);
        if (request) request.abort();

        if ($('#account_id').val() && $('#from_date').val() && $('#to_date').val()) {
            showLoading();
        }

        timer = setTimeout(lookup, 350);
    }

    function lookup() {
        const account = $('#account_id').val();
        const from = $('#from_date').val();
        const to = $('#to_date').val();

        if (! from || ! to || from > to) {
            entries = [];
            orders = [];
            $('#entriesEmptyTitle').text(from && to ? '«التاريخ من» بعد «التاريخ إلى»' : 'اختر الحساب والفترة');
            $('#entriesEmptyHint').text(from && to
                ? 'صحّح الفترة لتُجلب قيود الحساب'
                : 'تُجلب قيود الحساب المختار في الفترة تلقائياً');
            $('#lookupSpinner').addClass('d-none');
            render();
            return;
        }

        if (request) request.abort();
        if (account) showLoading();
        $('#lookupSpinner').removeClass('d-none');

        request = $.ajax({
            url: lookupUrl,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token },
            data: { account_id: account, from_date: from, to_date: to, except_id: exceptId },
        })
            .done(function (response) {
                entries = response.restrictions || [];
                orders = response.orders || [];

                // القيودُ والأوامر تُعرض بلا تحديد — المستخدمُ هو من يحدّد.
                // ويبقى ما سبق تحديدُه إن كان ما زال في القائمة.

                $('#entriesEmptyTitle').text(account ? 'لا قيود على هذا الحساب في الفترة' : 'اختر حساب المصروف');
                $('#entriesEmptyHint').text(! account
                    ? 'تُجلب قيود الحساب المختار في الفترة تلقائياً'
                    : (response.last_entry_date
                        ? 'آخر قيد على هذا الحساب بتاريخ ' + response.last_entry_date + ' — وسّع الفترة لتشمله'
                        : 'لا حركة مدينة على الحساب في هذه الفترة، أو استُعملت قيودُه كلُّها في توزيعٍ سابق'));

                render();
            })
            .fail(function (xhr, status) {
                if (status === 'abort') return;
                $('#entriesEmptyTitle').text('تعذّر جلب القيود');
                $('#entriesEmptyHint').text('غيّر الحساب أو الفترة لإعادة المحاولة');
                render();
                Swal.fire({ icon: 'error', title: 'تعذّر جلب القيود والأوامر' });
            })
            .always(function (xhr, status) {
                // طلبٌ أُلغي لأن غيرَه خلَفه — المؤشّر يبقى للخلَف
                if (status !== 'abort') $('#lookupSpinner').addClass('d-none');
            });
    }

    $('#account_id, #from_date, #to_date').on('change', scheduleLookup);

    $('input[name="based_on"]').on('change', recalc);

    $(document).on('change', '.js-entry, .js-order', function () {
        autoSelect = false;
        recalc();
    });

    $('#entriesAll').on('change', function () {
        autoSelect = false;
        $('.js-entry').prop('checked', this.checked);
        recalc();
    });

    $('#ordersAll').on('change', function () {
        autoSelect = false;
        $('.js-order').prop('checked', this.checked);
        recalc();
    });

    $('#indirectCostForm').on('submit', function (e) {
        const errors = [];

        if (! $('#account_id').val()) errors.push('اختر حساب المصروف');
        if (! $('.js-entry:checked').length) errors.push('اختر قيداً واحداً على الأقل');

        if (errors.length) {
            e.preventDefault();
            Swal.fire({ icon: 'error', title: 'بيانات ناقصة', html: errors.join('<br>') });
        }
    });

    if ($.fn.select2) {
        $('#account_id').select2({ placeholder: 'اختر الحساب…', dir: 'rtl', width: '100%' });
    }

    lookup();
});
</script>
@endpush
