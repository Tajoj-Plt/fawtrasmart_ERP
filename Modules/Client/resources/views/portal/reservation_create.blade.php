@extends('client::portal.layout')

{{--
    حجزُ العميل موعداً بنفسه — ثلاث خطوات: الخدمة ثمّ اليوم ثمّ الوقت.

    **الأوقاتُ تأتي من الخادم لا من التقويم**: المتاحُ يحسبه حارسُ التوفّر
    نفسُه الذي يحكم حفظَ الموظّف (الدوام والعطل والتداخل والحدّ اليومي
    ومهلة الحجز المسبق). فما يظهر هنا يُقبل هناك.

    ولا منتقيَ موظّفٍ للعميل: يختار وقتاً، والموظّفُ يُشتقّ منه — جدولُ
    موظفينا ليس مما يُعرض للعميل.
--}}

@section('title', 'احجز موعداً')
@section('subtitle', 'اختر الخدمة واليوم والوقت المناسب لك')

@section('actions')
    <a href="{{ route('clients.portal.reservations.index') }}" class="cp-btn cp-btn--ghost cp-btn--sm">
        <i class="fa fa-arrow-right"></i> حجوزاتي
    </a>
@endsection

@section('content')
    @if ($services->isEmpty())
        <div class="cp-card">
            <div class="cp-card__body">
                <div class="cp-empty">
                    <i class="fa fa-concierge-bell"></i>
                    <p>لا توجد خدماتٌ متاحةٌ للحجز حالياً. تواصل معنا لحجز موعدك.</p>
                </div>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('clients.portal.reservations.store') }}" id="cpBookForm">
            @csrf

            <input type="hidden" name="start_time" id="cpStart" value="{{ old('start_time') }}">
            {{-- الموظّفُ يأتي مع الوقت من نداء الأوقات المتاحة، ويُعاد التحقّق
                 منه في الخادم: حقلٌ مخفيٌّ محرَّفٌ يُردّ بخطأ توفّر لا يُحجَز به --}}
            <input type="hidden" name="employee_id" id="cpEmployee" value="{{ old('employee_id') }}">

            <div class="cp-card">
                <div class="cp-card__head">
                    <h2 class="cp-card__title"><i class="fa fa-concierge-bell"></i>١) الخدمة واليوم</h2>
                </div>

                <div class="cp-card__body">
                    <div class="cp-grid">
                        <div class="cp-field">
                            <label for="product_id">الخدمة</label>
                            <select name="product_id" id="product_id" required>
                                <option value="">— اختر الخدمة —</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}" @selected(old('product_id') == $service->id)>
                                        {{ $service->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="cp-field">
                            <label for="reservation_date">اليوم</label>
                            <input type="date" name="reservation_date" id="reservation_date"
                                   min="{{ $minDate }}" max="{{ $maxDate }}"
                                   value="{{ old('reservation_date', $minDate) }}" required>
                            <span class="cp-field__hint">تُعرض الأوقات المتاحة بعد اختيار الخدمة واليوم.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cp-card">
                <div class="cp-card__head">
                    <h2 class="cp-card__title"><i class="fa fa-clock"></i>٢) الوقت المتاح</h2>
                    <span class="cp-muted" id="cpSlotsNote" style="font-size:.8rem"></span>
                </div>

                <div class="cp-card__body">
                    <div id="cpSlots" class="cp-slots">
                        <p class="cp-muted" style="margin:0">اختر الخدمة واليوم أولاً.</p>
                    </div>
                </div>
            </div>

            <div class="cp-card">
                <div class="cp-card__head">
                    <h2 class="cp-card__title"><i class="fa fa-comment"></i>٣) ملاحظة (اختياري)</h2>
                </div>

                <div class="cp-card__body">
                    <div class="cp-field">
                        <label for="notes">هل تودّ إخبارنا بشيء عن موعدك؟</label>
                        <textarea name="notes" id="notes" rows="2" maxlength="500">{{ old('notes') }}</textarea>
                    </div>

                    <p class="cp-muted" style="font-size:.85rem">
                        سيُسجَّل طلبك <strong>بانتظار التأكيد</strong>، ونؤكّده لك بعد مراجعته.
                    </p>

                    <button type="submit" class="cp-btn cp-btn--primary" id="cpSubmit" disabled>
                        <i class="fa fa-check"></i> أرسل طلب الحجز
                    </button>
                </div>
            </div>
        </form>
    @endif
@endsection

@section('styles')
    <style>
        .cp-slots { display: flex; flex-wrap: wrap; gap: .5rem; }
        .cp-slot {
            border: 1px solid var(--cp-line); border-radius: var(--cp-r);
            background: var(--cp-surface); color: var(--cp-text);
            padding: .45rem .8rem; font-family: inherit; font-size: .85rem; cursor: pointer;
        }
        .cp-slot:hover { border-color: var(--cp-primary); }
        .cp-slot.is-active {
            border-color: var(--cp-primary); color: var(--cp-primary);
            background: var(--cp-primary-soft); font-weight: 600;
        }
    </style>
@endsection

@section('scripts')
    <script>
        (function () {
            var service = document.getElementById('product_id');
            var date = document.getElementById('reservation_date');
            var box = document.getElementById('cpSlots');
            var note = document.getElementById('cpSlotsNote');
            var start = document.getElementById('cpStart');
            var employee = document.getElementById('cpEmployee');
            var submit = document.getElementById('cpSubmit');

            if (!service || !date) {
                return;
            }

            function say(text) {
                box.innerHTML = '';
                var p = document.createElement('p');
                p.className = 'cp-muted';
                p.style.margin = '0';
                p.textContent = text;
                box.appendChild(p);
            }

            function reset() {
                start.value = '';
                employee.value = '';
                submit.disabled = true;
            }

            /* عددُ الأيام التي قفزناها بحثاً عن وقت — يُصفَّر كلّما اختار
               المستخدم يوماً أو خدمةً بنفسه، فلا نقفز من فوق اختياره. */
            var hops = 0;

            function load(chained) {
                if (!chained) { hops = 0; }

                reset();

                if (!service.value || !date.value) {
                    say('اختر الخدمة واليوم أولاً.');
                    note.textContent = '';
                    return;
                }

                say('جارٍ البحث عن الأوقات المتاحة…');

                var url = '{{ route('clients.portal.reservations.slots') }}'
                    + '?product_id=' + encodeURIComponent(service.value)
                    + '&date=' + encodeURIComponent(date.value);

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (!data.success) {
                            say(data.message || 'تعذّر جلب الأوقات.');
                            return;
                        }

                        if (!data.slots.length) {
                            /* يومٌ بلا أوقات لا يُترك للمستخدم ليجرّب بنفسه:
                               اليومُ الافتراضي هو اليوم، وغالباً ما يكون دوامُه
                               قد مضى — فينصرف الزبون ظانّاً أن الحجز لا يعمل.
                               نتقدّم إلى أقرب يومٍ فيه وقت (حتى ١٤ يوماً). */
                            if (hops < 14) {
                                hops++;
                                /* `toISOString()` يحوّل إلى UTC: على توقيتٍ
                                   موجب يعود ناتجُه إلى اليوم نفسِه، فيظلّ
                                   البحثُ واقفاً على اليوم ثمّ يستسلم قائلاً
                                   «لا أوقات». التكوينُ من أجزاء التاريخ المحلّية. */
                                var next = new Date(date.value + 'T12:00:00');
                                next.setDate(next.getDate() + 1);
                                date.value = next.getFullYear()
                                    + '-' + String(next.getMonth() + 1).padStart(2, '0')
                                    + '-' + String(next.getDate()).padStart(2, '0');
                                say('لا أوقات في ذلك اليوم… نبحث عن أقرب موعد متاح.');
                                load(true);
                                return;
                            }

                            say('لا أوقات متاحة خلال الأسبوعين القادمين — تواصل معنا لحجز موعدك.');
                            note.textContent = '';
                            return;
                        }

                        if (hops > 0) {
                            note.textContent = 'أقرب يوم متاح: ' + date.value;
                        }

                        note.textContent = 'مدة الخدمة ' + data.duration + ' دقيقة';
                        box.innerHTML = '';

                        data.slots.forEach(function (slot) {
                            var button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'cp-slot';
                            button.dir = 'ltr';
                            button.textContent = slot.start + ' – ' + slot.end;
                            button.addEventListener('click', function () {
                                box.querySelectorAll('.cp-slot').forEach(function (other) {
                                    other.classList.toggle('is-active', other === button);
                                });

                                start.value = slot.start;
                                employee.value = slot.employee_id;
                                submit.disabled = false;
                            });
                            box.appendChild(button);
                        });
                    })
                    .catch(function () { say('تعذّر الاتصال. حاول مرّةً أخرى.'); });
            }

            service.addEventListener('change', function () { load(false); });
            date.addEventListener('change', function () { load(false); });

            if (service.value && date.value) {
                load(false);
            }
        })();
    </script>
@endsection
