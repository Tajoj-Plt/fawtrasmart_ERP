{{--
    مودالا شاشة الحجز: الإلغاء، وإعادة الجدولة.

    لا قبضَ ولا ردَّ ولا رسمَ إلغاء — المالُ على فاتورة المبيعات.

    **المعرّفاتُ فريدةٌ في الصفحة** (بادئة `rsv`): مودالان بمعرّفٍ واحد يجعلان
    Bootstrap يفتح الأولَ دائماً فيُرسَل نموذجٌ غيرُ الذي ظنّه المستخدم.
    و`data-rsv-once` يعطّل زرّ الإرسال بعد النقرة الأولى.
--}}

{{-- ═════════════ إلغاء الحجز ═════════════ --}}
<div class="modal fade" id="rsvCancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('Reservations.cancel', $reservation->id) }}" class="modal-content" data-rsv-once>
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-x-circle text-danger"></i> {{ __('reservations.ui.cancel_title', ['code' => $reservation->code]) }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('reservations.actions.close') }}"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">{{ __('reservations.attributes.cancel_reason') }} <span class="text-danger">*</span></label>
                <textarea name="reason" rows="2" class="form-control" required maxlength="500"></textarea>
                <span class="rsv-hint">{{ __('reservations.hints.cancel_reason') }}</span>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('reservations.actions.back') }}</button>
                <button type="submit" class="btn btn-danger">{{ __('reservations.actions.confirm_cancel') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- ═════════════ إعادة الجدولة ═════════════ --}}
<div class="modal fade" id="rsvRescheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="{{ route('Reservations.reschedule', $reservation->id) }}" class="modal-content" data-rsv-once>
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-calendar text-warning"></i> {{ __('reservations.ui.reschedule_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('reservations.actions.close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('reservations.attributes.new_date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="reservation_date" id="rsvResDate" class="form-control" required
                               value="{{ $reservation->reservation_date?->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('reservations.attributes.employee_id') }}</label>
                        <div class="rsv-select-add">
                            {{--
                                الخيارُ الأوّل «الموظّفُ الحالي» بقيمةٍ فارغة: الخادمُ يقرأ الفارغَ
                                «كما هو». كانت القائمةُ بلا هذا الخيار، فإن غاب موظّفُ الحجز عن
                                المؤهَّلين (أو عن نطاق المستخدم) اختار المتصفّحُ أوّلَ اسمٍ صامتاً
                                ونُقل الحجزُ إلى موظّفٍ آخر بلا قصد.
                            --}}
                            <select name="employee_id" id="rsvResEmployee" class="form-select">
                                <option value="" selected>{{ __('reservations.more.keep_employee', ['name' => $reservation->employee?->full_name ?: '—']) }}</option>
                                @foreach ($rescheduleEmployees as $employee)
                                    @continue($employee->id == $reservation->employee_id)
                                    <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                            @include('client::reservations.partials.select-add', ['route' => 'employee.create', 'permission' => 'employees.create', 'title' => __('reservations.actions.quick_add_employee')])
                            @include('client::reservations.partials.select-add', ['route' => 'shift_management.create', 'permission' => 'shifts.create', 'title' => __('reservations.actions.quick_add_shift'), 'icon' => 'clock'])
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('reservations.columns.from') }} <span class="text-danger">*</span></label>
                        <input type="time" name="start_time" id="rsvResStart" class="form-control" required
                               value="{{ substr((string) $reservation->start_time, 0, 5) }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('reservations.columns.to') }}</label>
                        <input type="time" name="end_time" id="rsvResEnd" class="form-control"
                               value="{{ substr((string) $reservation->end_time, 0, 5) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('reservations.ui.available_slots') }}</label>
                        <div id="rsvResSlots" class="rsv-slots">
                            <span class="rsv-muted">{{ __('reservations.hints.pick_date_for_slots') }}</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('reservations.attributes.reason') }}</label>
                        <input type="text" name="note" class="form-control" maxlength="500">
                    </div>
                </div>
                <p class="rsv-hint mt-3 mb-0">
                    <i class="feather icon-info"></i> {{ __('reservations.hints.reschedule') }}
                    @if ($reservation->reschedule_count)
                        {{ __('reservations.hints.reschedule_count', ['n' => $reservation->reschedule_count]) }}
                    @endif
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('reservations.actions.back') }}</button>
                <button type="submit" class="btn btn-warning">{{ __('reservations.actions.move') }}</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            // ─────────── تأكيدٌ ثمّ إرسالٌ واحد ───────────
            document.querySelectorAll('form[data-rsv-once]').forEach(form => {
                form.addEventListener('submit', event => {
                    const question = form.dataset.rsvConfirm;

                    if (question && !window.confirm(question)) {
                        event.preventDefault();
                        return;
                    }

                    if (form.dataset.rsvSent) {
                        event.preventDefault();
                        return;
                    }

                    form.dataset.rsvSent = '1';
                    form.querySelectorAll('button[type="submit"]').forEach(b => { b.disabled = true; });
                });
            });

            // ─────────── إعادة الجدولة: الأوقاتُ الشاغرة من وردية الموظّف ───────────
            const date = document.getElementById('rsvResDate');
            const employee = document.getElementById('rsvResEmployee');
            const start = document.getElementById('rsvResStart');
            const end = document.getElementById('rsvResEnd');
            const slots = document.getElementById('rsvResSlots');

            if (!date || !slots) return;

            const url = @json(route('Reservations.slots'));
            const fixed = {
                product_id: @json($reservation->product_id),
                service_id: @json($reservation->product_id ? null : $reservation->service_id),
                ignore_id: @json($reservation->id),
            };
            const text = {
                loading: @json(__('reservations.ui.loading')),
                none: @json(__('reservations.ui.no_slots')),
                failed: @json(__('reservations.ui.slots_failed')),
            };
            let token = 0;

            const load = () => {
                if (!date.value) return;

                const current = ++token;
                slots.innerHTML = '<span class="rsv-muted">' + text.loading + '</span>';

                const params = new URLSearchParams({ date: date.value });
                Object.entries(fixed).forEach(([k, v]) => { if (v) params.set(k, v); });
                // الفارغُ = الموظّفُ الحالي — فالفتراتُ من ورديته هو
                const chosen = (employee && employee.value) || @json($reservation->employee_id);
                if (chosen) params.set('employee_id', chosen);

                fetch(url + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(data => {
                        if (current !== token) return;
                        if (!data.slots || !data.slots.length) {
                            slots.innerHTML = '<span class="rsv-muted">' + text.none + '</span>';
                            return;
                        }
                        slots.innerHTML = data.slots.map(s =>
                            `<button type="button" class="rsv-slot" data-start="${s.start}" data-end="${s.end}">${s.start}</button>`
                        ).join('');
                    })
                    .catch(() => {
                        if (current !== token) return;
                        slots.innerHTML = '<span class="rsv-muted text-danger">' + text.failed + '</span>';
                    });
            };

            slots.addEventListener('click', event => {
                const button = event.target.closest('.rsv-slot');
                if (!button) return;
                slots.querySelectorAll('.rsv-slot').forEach(b => b.classList.remove('is-active'));
                button.classList.add('is-active');
                start.value = button.dataset.start;
                end.value = button.dataset.end;
            });

            date.addEventListener('change', load);
            if (employee) employee.addEventListener('change', load);

            const modal = document.getElementById('rsvRescheduleModal');
            if (modal) modal.addEventListener('shown.bs.modal', load);
        })();
    </script>
@endpush
