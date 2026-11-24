{{--
    نموذجُ الحجز — واحدٌ للإنشاء والتعديل، بخطواتٍ مرقّمة تُقرأ من أعلى لأسفل.

    ══════════ ما كان ══════════

    أربعُ بطاقات فيها سبعةَ عشرَ حقلاً مكشوفةً دفعةً واحدة، وثلاثُ طرقٍ لضبط
    الوقت (من/إلى، المدّة، الفترات)، وتلميحاتٌ تشرح القيود المحاسبية لموظّف
    الاستقبال. فقال المستخدم: «معقدة في الإدخال».

    ══════════ ما صار ══════════

    ١ العميل ← ٢ الخدمة والموظّف ← ٣ الموعد (يومٌ ثم وقتٌ متاحٌ بنقرة) ←
    ٤ الدفع (اختياري، مطويٌّ خلف مفتاح). والنهايةُ والمدّةُ تُحسبان وتُعرضان
    نصّاً، ويُفتح تعديلُهما بزرّ. والسعرُ اليدويّ والخصمُ والضريبةُ والجهةُ
    والحالةُ في «خيارات إضافية» مطويّة — أغلبُ الحجوزات لا تلمسها.
    وملخّصٌ جانبيٌّ ثابتٌ يعرض ما اختير والإجمالي، وفيه زرُّ الحفظ.

    **الأسماءُ والمعرّفاتُ لم تتغيّر** (`client_id`، `rsv-date`، `data-sum`…):
    المتحكّمُ والتحقّقُ وسكربتُ التسعير يقرؤون ما كانوا يقرؤون. والحقلُ
    المطويُّ يُرسَل كغيره — `<details>` لا يعطّل ما فيه.

    `$reservation` موجودٌ في التعديل ومعدومٌ في الإنشاء، وعليه تُبنى القيم.
--}}

@php
    $editing = isset($reservation);
    // حجزٌ جديد من خانةٍ فارغة في التقويم يصل بـ`?employee_id=&reservation_date=&start_time=`
    $old = fn ($key, $default = null) => old($key, $editing ? ($reservation->{$key} ?? $default) : request()->query($key, $default));

    // «خيارات إضافية» تُفتح إن كان فيها ما يخصّ هذا الحجز: خطأٌ، أو سعرٌ يدويّ، أو خصم
    $moreOpen = $errors->hasAny(['status', 'unit_price', 'discount_value', 'discount_type', 'tax_id'])
        || filled(old('unit_price'))
        || (float) $old('discount_value', 0) > 0;

    $timeOpen = $errors->hasAny(['end_time', 'duration']);

    $startValue = $editing ? substr((string) $reservation->start_time, 0, 5) : old('start_time', request()->query('start_time', '09:00'));
    $endValue = $editing ? substr((string) $reservation->end_time, 0, 5) : old('end_time');
@endphp

<style>
    .rsv-step { margin-bottom: var(--rsv-3, .75rem); }
    .rsv-step__head { display: flex; align-items: center; gap: .75rem; padding: .85rem 1.1rem; border-bottom: 1px solid var(--rsv-line, #ebe9f1); }
    .rsv-step__num {
        width: 30px; height: 30px; border-radius: 50%; flex: none;
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--app-primary, #7367f0); color: #fff; font-weight: 700;
    }
    .rsv-step__title { margin: 0; font-weight: 700; font-size: 1rem; }
    .rsv-step__sub { margin: 0; color: var(--app-muted, #6e6b7b); font-size: .82rem; }
    .rsv-optional { font-weight: 400; color: var(--app-muted, #6e6b7b); font-size: .78rem; }

    .rsv-entry .rsv-slots { display: flex; flex-wrap: wrap; gap: .4rem; min-height: 38px; align-items: center; }
    .rsv-entry .rsv-slot { min-width: 72px; padding: .4rem .7rem; font-weight: 600; }

    .rsv-timeline-note { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .9rem; padding-top: 1.9rem; }
    .rsv-timeline-note strong { font-size: 1.05rem; }
    .rsv-shift-note { display: flex; align-items: center; gap: .4rem; margin-top: .5rem; padding: .45rem .7rem; border-radius: 8px; font-size: .82rem; font-weight: 600; }
    .rsv-shift-note[data-tone="ok"] { background: #e8f7ef; color: #1e7b4c; }
    .rsv-shift-note[data-tone="bad"] { background: #fdecec; color: #b42318; }
    .rsv-linkbtn { background: none; border: 0; padding: 0; color: var(--app-primary, #7367f0); font-weight: 600; font-size: .85rem; cursor: pointer; }

    .rsv-paybox { border: 1px dashed var(--rsv-line, #ebe9f1); border-radius: var(--rsv-r, 8px); padding: .9rem; margin-top: .75rem; }

    .rsv-more > summary { list-style: none; cursor: pointer; padding: .85rem 1.1rem; display: flex; align-items: center; gap: .6rem; }
    .rsv-more > summary::-webkit-details-marker { display: none; }
    .rsv-more > summary .rsv-more__chev { margin-inline-start: auto; transition: transform .15s; }
    .rsv-more[open] > summary .rsv-more__chev { transform: rotate(180deg); }
    .rsv-more[open] > summary { border-bottom: 1px solid var(--rsv-line, #ebe9f1); }

    .rsv-summary-card { position: sticky; top: 90px; }
    .rsv-sumline { display: flex; justify-content: space-between; gap: .75rem; padding: .35rem 0; font-size: .9rem; }
    .rsv-sumline > span:first-child { color: var(--app-muted, #6e6b7b); flex: none; }
    .rsv-sumline > span:last-child { text-align: end; font-weight: 600; }
    .rsv-sumtotal { display: flex; justify-content: space-between; padding-top: .6rem; margin-top: .4rem; border-top: 1px solid var(--rsv-line, #ebe9f1); font-size: 1.15rem; }
</style>

<div class="row g-3 rsv-entry">

    <div class="col-lg-8">

        {{-- ═════════════ ١. العميل ═════════════ --}}
        <section class="rsv-card rsv-step">
            <div class="rsv-step__head">
                <span class="rsv-step__num">1</span>
                <div>
                    <h6 class="rsv-step__title">{{ __('reservations.form.step_client') }}</h6>
                    <p class="rsv-step__sub">{{ __('reservations.form.step_client_sub') }}</p>
                </div>
            </div>
            <div class="rsv-card__body">
                <label class="form-label" for="rsv-client">
                    {{ __('reservations.form.client') }}
                    @if ($settings->require_client)<span class="text-danger">*</span>@else<span class="rsv-optional">({{ __('reservations.form.optional') }})</span>@endif
                </label>
                @if (! $editing && ! empty($lockedClient))
                    {{--
                        جاء من شاشة العميل (`?client_id=`): العميلُ مقفلٌ كنموذج المواعيد،
                        و«تغيير العميل» يفتح النموذجَ حرّاً. المُنتقي غائبٌ فلا يهيّئه
                        select2 على حقلٍ مخفيّ، و`data-name` يقرؤه الملخّصُ الجانبي.
                    --}}
                    <input type="hidden" name="client_id" id="rsv-client" value="{{ $lockedClient->id }}" data-name="{{ $lockedClient->display_name }}">
                    <div class="form-control d-flex justify-content-between align-items-center" style="background: var(--rsv-soft, #f8f8fb)">
                        <span>
                            <i class="feather icon-user"></i> <strong>{{ $lockedClient->display_name }}</strong>
                            @if ($lockedClient->code)<span class="rsv-muted">— {{ $lockedClient->code }}</span>@endif
                        </span>
                        <a href="{{ route('Reservations.create') }}" class="small">{{ __('reservations.more.change_client') }}</a>
                    </div>
                @else
                <div class="rsv-select-add">
                    <select name="client_id" id="rsv-client" class="form-select" @required($settings->require_client)
                            data-placeholder="{{ __('reservations.form.client_ph') }}">
                        <option value="">{{ __('reservations.form.no_client') }}</option>
                        @if ($editing && $reservation->client)
                            <option value="{{ $reservation->client_id }}" selected>{{ $reservation->client->display_name }}</option>
                        @endif
                        @foreach ($clients as $client)
                            @continue($editing && $client->id == $reservation->client_id)
                            <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->display_name }}</option>
                        @endforeach
                    </select>
                    @include('client::reservations.partials.select-add', ['route' => 'clients.create', 'permission' => 'clients_add_client', 'title' => __('reservations.actions.quick_add_client')])
                </div>
                @endif
                @error('client_id')<span class="text-danger small d-block mt-1">{{ $message }}</span>@enderror
            </div>
        </section>

        {{-- ═════════════ ٢. الخدمة والموظّف ═════════════ --}}
        <section class="rsv-card rsv-step">
            <div class="rsv-step__head">
                <span class="rsv-step__num">2</span>
                <div>
                    <h6 class="rsv-step__title">{{ __('reservations.form.step_service') }}</h6>
                    <p class="rsv-step__sub">{{ __('reservations.form.step_service_sub') }}</p>
                </div>
            </div>
            <div class="rsv-card__body">
                <div class="row g-3">
                    <div class="col-md-6">
                        {{-- الخدمةُ منتجٌ من نوع «منتج خدمي» — السعرُ والضريبةُ منه، والمدّةُ من ملفّ حجزه --}}
                        <label class="form-label" for="rsv-service">{{ __('reservations.form.service') }}</label>
                        <div class="rsv-select-add">
                            <select name="product_id" id="rsv-service" class="form-select">
                                <option value="" data-name="">{{ __('reservations.form.no_service') }}</option>
                                @if ($editing && $reservation->product_id && ! $services->contains('id', $reservation->product_id))
                                    <option value="{{ $reservation->product_id }}" data-name="{{ $reservation->service_name }}" selected>{{ $reservation->service_name }}</option>
                                @endif
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}" data-name="{{ $service->name }}" data-price="{{ (float) $service->sale_price }}" @selected($old('product_id') == $service->id)>
                                        {{ $service->name }} — {{ number_format((float) $service->sale_price, 2) }}
                                    </option>
                                @endforeach
                            </select>
                            {{-- الخدمةُ منتجٌ خدمي — فالإضافةُ من شاشة «منتج خدمي» في المخزون --}}
                            @include('client::reservations.partials.select-add', ['route' => 'products.create_services', 'permission' => 'stock.products.services', 'title' => __('reservations.actions.quick_add_service')])
                        </div>
                        @if ($services->isEmpty())
                            <span class="rsv-hint text-warning">{{ __('reservations.services.empty_hint') }}</span>
                        @endif
                    </div>

                    {{-- من له وردية في الموارد البشرية وحدَه — والقائمةُ تُحدَّث بالخدمة --}}
                    <div class="col-md-6">
                        <label class="form-label" for="rsv-employee">
                            {{ __('reservations.form.employee') }} <span class="text-danger">*</span>
                        </label>
                        <div class="rsv-select-add">
                            <select name="employee_id" id="rsv-employee" class="form-select" required>
                                <option value="">{{ __('reservations.form.no_employee') }}</option>
                                @if ($editing && $reservation->employee && ! $employees->contains('id', $reservation->employee_id))
                                    <option value="{{ $reservation->employee_id }}" selected>{{ $reservation->employee->full_name }}</option>
                                @endif
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected($old('employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                            {{-- القائمةُ لا تعرض إلّا من له وردية — فزرُّ الوردية بجانب زرّ الموظّف --}}
                            @include('client::reservations.partials.select-add', ['route' => 'employee.create', 'permission' => 'employees.create', 'title' => __('reservations.actions.quick_add_employee')])
                            @include('client::reservations.partials.select-add', ['route' => 'shift_management.create', 'permission' => 'shifts.create', 'title' => __('reservations.actions.quick_add_shift'), 'icon' => 'clock'])
                        </div>
                        @if ($employees->isEmpty())
                            <span class="rsv-hint text-warning">{{ __('reservations.form.no_shift_employees') }}</span>
                        @else
                            <span class="rsv-hint">{{ __('reservations.form.employee_hint') }}</span>
                        @endif
                        @error('employee_id')<span class="text-danger small d-block">{{ $message }}</span>@enderror

                        {{-- ورديةُ الموظّف المختار في تاريخ الحجز — الأوقاتُ أدناه منها وحدَها --}}
                        <div id="rsv-shift" class="rsv-shift-note" hidden>
                            <i class="feather icon-clock"></i>
                            <span id="rsv-shift-text"></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═════════════ ٣. الموعد ═════════════ --}}
        <section class="rsv-card rsv-step">
            <div class="rsv-step__head">
                <span class="rsv-step__num">3</span>
                <div>
                    <h6 class="rsv-step__title">{{ __('reservations.form.step_time') }}</h6>
                    <p class="rsv-step__sub">{{ __('reservations.form.step_time_sub') }}</p>
                </div>
            </div>
            <div class="rsv-card__body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="rsv-date">{{ __('reservations.form.date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="reservation_date" id="rsv-date" class="form-control" required
                               value="{{ $editing ? $reservation->reservation_date?->format('Y-m-d') : old('reservation_date', request()->query('reservation_date', now()->toDateString())) }}">
                        @if ($settings->max_advance_days)
                            <span class="rsv-hint">{{ __('reservations.form.max_advance', ['n' => $settings->max_advance_days]) }}</span>
                        @endif
                        @error('reservation_date')<span class="text-danger small d-block">{{ $message }}</span>@enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="rsv-start">{{ __('reservations.form.start') }} <span class="text-danger">*</span></label>
                        <input type="time" name="start_time" id="rsv-start" class="form-control" required value="{{ $startValue }}">
                        @error('start_time')<span class="text-danger small d-block">{{ $message }}</span>@enderror
                    </div>

                    {{-- النهايةُ والمدّةُ محسوبتان — تُعرضان نصّاً، وتُعدَّلان بزرّ --}}
                    <div class="col-md-5">
                        <div class="rsv-timeline-note">
                            <span>{{ __('reservations.form.ends') }} <strong id="rsv-end-label">{{ $endValue ?: '—' }}</strong></span>
                            <span class="rsv-muted">{{ __('reservations.form.lasts') }} <strong id="rsv-duration-label">{{ $old('duration', $settings->default_duration) }}</strong> {{ __('reservations.form.minutes') }}</span>
                            <button type="button" class="rsv-linkbtn" id="rsv-time-toggle">
                                <i class="feather icon-edit-2"></i> {{ __('reservations.form.change_duration') }}
                            </button>
                        </div>
                    </div>

                    <div class="col-12" id="rsv-time-manual" @unless ($timeOpen) hidden @endunless>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="rsv-duration">{{ __('reservations.form.duration') }}</label>
                                <input type="number" name="duration" id="rsv-duration" class="form-control" min="1" max="1440"
                                       value="{{ $old('duration', $settings->default_duration) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="rsv-end">{{ __('reservations.form.end') }}</label>
                                <input type="time" name="end_time" id="rsv-end" class="form-control" value="{{ $endValue }}">
                            </div>
                        </div>
                        @error('end_time')<span class="text-danger small d-block">{{ $message }}</span>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">{{ __('reservations.form.slots') }}</label>
                        <div id="rsv-slots" class="rsv-slots">
                            <span class="rsv-muted">{{ __('reservations.form.slots_pick_date') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═════════════ ملاحظات — لا دفعَ في الحجز: المالُ على فاتورة المبيعات ═════════════ --}}
        <section class="rsv-card rsv-step">
            <div class="rsv-card__body">
                <label class="form-label">{{ __('reservations.form.notes') }} <span class="rsv-optional">({{ __('reservations.form.optional') }})</span></label>
                <textarea name="notes" rows="2" class="form-control" placeholder="{{ __('reservations.form.notes_ph') }}">{{ $old('notes') }}</textarea>
            </div>
        </section>

        {{-- ═════════════ خيارات إضافية — مطويّة ═════════════ --}}
        <details class="rsv-card rsv-more" @if ($moreOpen) open @endif>
            <summary>
                <i class="feather icon-sliders"></i>
                <span>
                    <strong>{{ __('reservations.form.more') }}</strong>
                    <span class="rsv-muted d-block small">{{ __('reservations.form.more_sub') }}</span>
                </span>
                <i class="feather icon-chevron-down rsv-more__chev"></i>
            </summary>
            <div class="rsv-card__body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="rsv-price">{{ __('reservations.form.price') }}</label>
                        <input type="number" step="0.01" min="0" name="unit_price" id="rsv-price" class="form-control" value="{{ $old('unit_price') }}">
                        <span class="rsv-hint">{{ __('reservations.form.price_hint') }}</span>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="rsv-discount">{{ __('reservations.form.discount') }}</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" name="discount_value" id="rsv-discount" class="form-control"
                                   value="{{ $old('discount_value', 0) }}">
                            <select name="discount_type" id="rsv-discount-type" class="form-select" style="max-width: 7.5rem">
                                <option value="amount" @selected($old('discount_type', 'amount') === 'amount')>{{ __('reservations.form.amount_type') }}</option>
                                <option value="percentage" @selected($old('discount_type') === 'percentage')>{{ __('reservations.form.percent_type') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="rsv-tax">{{ __('reservations.form.tax') }}</label>
                        @php
                            /*
                                في التعديل: ضريبةُ الحجز **كما حُفظت** — و`null` تعني «بلا ضريبة» لا
                                «خذ الافتراضية». كان `$old('tax_id', $default)` يقرأ الفارغَ غياباً
                                فيُعيد إلى الحجز المعفى ضريبةَ الإعدادات مع أوّل حفظ.
                            */
                            $taxSelected = old('tax_id', $editing ? $reservation->tax_id : $settings->default_tax_id);
                        @endphp
                        <div class="rsv-select-add">
                            <select name="tax_id" id="rsv-tax" class="form-select">
                                <option value="">{{ __('reservations.form.no_tax') }}</option>
                                @foreach ($taxes as $tax)
                                    <option value="{{ $tax->id }}" @selected($taxSelected == $tax->id)>{{ $tax->name }} ({{ $tax->tax }}%)</option>
                                @endforeach
                            </select>
                            {{-- لا شاشةَ «إضافة ضريبة» مستقلّة: الضرائبُ تُضاف في جدول إعداداتها --}}
                            @include('client::reservations.partials.select-add', ['route' => 'TaxSitting.index', 'permission' => 'settings_edit_tax_settings', 'title' => __('reservations.actions.quick_add_tax')])
                        </div>
                    </div>

                    {{-- لا فرعَ يُختار: الحجزُ يُنسب إلى فرع موظّفه تلقائياً --}}

                    @unless ($editing)
                        @php
                            /*
                                `$statuses` هي حالاتُ الإنشاء وحدَها — والتأكيدُ فيها لمن يملك
                                صلاحيتَه (`ReservationStatus::storableAtCreate`). والافتراضيُّ من
                                الإعدادات إن كان بينها، وإلّا «بانتظار التأكيد».
                            */
                            $storableValues = collect($statuses)->map(fn ($s) => $s->value)->all();
                            $statusDefault = in_array($settings->default_status, $storableValues, true) ? $settings->default_status : 'pending';
                        @endphp
                        <div class="col-md-6">
                            <label class="form-label">{{ __('reservations.form.status') }}</label>
                            <select name="status" class="form-select">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected(old('status', $statusDefault) == $status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            @if (! in_array('confirmed', $storableValues, true))
                                <span class="rsv-hint">{{ __('reservations.more.confirm_needs_permission') }}</span>
                            @endif
                        </div>
                    @endunless
                </div>
            </div>
        </details>
    </div>

    {{-- ═════════════ الملخّص — ثابتٌ بجانب الخطوات، وفيه الحفظ ═════════════ --}}
    <div class="col-lg-4">
        <div class="rsv-card rsv-summary-card mb-0">
            <div class="rsv-card__head">
                <h6 class="rsv-card__title"><i class="feather icon-clipboard"></i> {{ __('reservations.form.summary') }}</h6>
                <span class="rsv-muted small">{{ $editing ? $reservation->code : ($nextCode ?? '') }}</span>
            </div>
            <div class="rsv-card__body">
                <div class="rsv-sumline"><span>{{ __('reservations.form.client') }}</span><span id="sum-client">—</span></div>
                <div class="rsv-sumline"><span>{{ __('reservations.form.service') }}</span><span id="sum-service">—</span></div>
                <div class="rsv-sumline"><span>{{ __('reservations.form.employee') }}</span><span id="sum-employee">—</span></div>
                <div class="rsv-sumline"><span>{{ __('reservations.form.when') }}</span><span id="sum-when" dir="ltr">—</span></div>

                <div id="rsv-summary" class="mt-2 pt-2" style="border-top:1px solid var(--rsv-line, #ebe9f1)">
                    <div class="rsv-sumline"><span>{{ __('reservations.form.price') }}</span><span class="rsv-money" data-sum="unit_price">0.00</span></div>
                    <div class="rsv-sumline" data-row="discount"><span>{{ __('reservations.form.discount') }}</span><span class="rsv-money text-danger" data-sum="discount_amount">0.00</span></div>
                    <div class="rsv-sumline" data-row="tax"><span>{{ __('reservations.form.tax') }}</span><span class="rsv-money" data-sum="tax_amount">0.00</span></div>
                    <div class="rsv-sumtotal"><strong>{{ __('reservations.form.total') }}</strong><strong class="rsv-money" data-sum="total">0.00</strong></div>
                </div>

                <div class="d-grid gap-2 mt-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="feather icon-check"></i> {{ $editing ? __('reservations.form.save_changes') : __('reservations.form.save') }}
                    </button>
                    <a href="{{ $editing ? route('Reservations.show', $reservation->id) : route('Reservations.index') }}" class="btn btn-outline-secondary">
                        {{ __('reservations.form.cancel') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
