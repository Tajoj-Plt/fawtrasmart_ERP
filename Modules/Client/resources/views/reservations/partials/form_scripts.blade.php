<script>
    /*
        سلوكُ نموذج الحجز.

        ثلاثةُ مبادئ:

        1. **الخادمُ يحسب، والشاشةُ تعرض.** التسعيرُ يُطلَب من
           `Reservations.quote` ولا يُحسب هنا — فلا يفترق المعروضُ عمّا
           يُحفظ، ولا يُخزَّن رقمٌ قادم من المتصفّح.

        2. **الفتراتُ المتاحة من الحارس نفسِه** الذي يفحص الحفظ
           (`Reservations.slots`) — فلا تعرض الشاشةُ فترةً يرفضها الخادم.

        3. **لا jQuery جديد ولا Bootstrap جديد.** كلاهما محمَّلٌ في القالب
           الأمّ، وإعادةُ تحميل jQuery تُفرغ ما سجّلته الإضافاتُ عليه فتموت
           select2 وDataTables صامتتَين.

        والملخّصُ الجانبيّ يُعاد رسمُه عند كلّ تغيير — فيرى الموظّفُ ما سيُحفظ
        قبل أن يضغط «حفظ».
    */
    (function () {
        const urls = {
            slots: @json(route('Reservations.slots')),
            serviceEmployees: @json(route('Reservations.serviceEmployees')),
            quote: @json(route('Reservations.quote')),
            clients: @json(route('Reservations.clients')),
        };

        const text = {
            noEmployee: @json(__('reservations.form.no_employee')),
            loading: @json(__('reservations.ui.loading')),
            noSlots: @json(__('reservations.ui.no_slots')),
            slotsFailed: @json(__('reservations.ui.slots_failed')),
            clientPh: @json(__('reservations.form.client_ph')),
            anyEmployee: @json(__('reservations.form.any_employee')),
            shift: 'وردية الموظّف',
            shiftNone: 'هذا الموظّف ليس له وردية في الموارد البشرية — لا يمكن الحجز له.',
            shiftOff: 'يوم راحة في وردية الموظّف — اختر تاريخاً آخر.',
            holiday: 'عطلة رسمية',
            pickEmployee: 'اختر الموظّف أولاً لتظهر أوقات ورديته.',
        };

        /*
            الحجزُ الجاري تعديلُه لا يتعارض مع نفسه.

            و`$editing` معرَّفٌ في `partials/form` وحدَه — وهذا الملفّ يُضمَّن
            في مقطع `scripts` المنفصل، فلا يراه. فالفحصُ على `$reservation`.
        */
        const ignoreId = @json(isset($reservation) ? $reservation->id : null);

        const $ = window.jQuery;
        const el = id => document.getElementById(id);

        /*
            كلُّ `<select>` في `.rsv-scope` يلبسه select2 (`partials/select_scripts`)،
            وselect2 4.0 يُعلن الاختيارَ بـ`$(select).trigger('change')` — حدثُ jQuery
            لا يصل إلى `addEventListener`. فكان اختيارُ الخدمة لا يجلب مدّةً ولا سعراً
            ولا موظفين ولا يظهر في الملخّص. الربطُ بـjQuery يسمع الحدثين معاً.
        */
        const listen = (node, events, handler) => {
            if ($) {
                $(node).on(events, handler);
            } else {
                events.split(' ').forEach(e => node.addEventListener(e, handler));
            }
        };

        // تغييرُ القيمة من الشيفرة لا يُحدِّث ما يعرضه select2 — يُنبَّه بلا إطلاق `change` العام
        const refreshSelect = node => {
            if ($ && $(node).data('select2')) $(node).trigger('change.select2');
        };

        const client = el('rsv-client');
        const date = el('rsv-date');
        const start = el('rsv-start');
        const end = el('rsv-end');
        const duration = el('rsv-duration');
        const service = el('rsv-service');
        const employee = el('rsv-employee');
        const price = el('rsv-price');
        const discount = el('rsv-discount');
        const discountType = el('rsv-discount-type');
        const tax = el('rsv-tax');
        const slots = el('rsv-slots');

        // ─────────── select2 على منتقي العميل وحدَه ───────────
        //
        // لا `$('select').select2()` عامّاً: المسحُ العامّ يلتقط الحاويةَ
        // التي أنشأها select2 نفسُه فيهيّئها ثانيةً، فتظهر قائمةٌ فارغة
        // فوق حقلٍ ممتلئ.
        // والعميلُ المقفل (`?client_id=`) حقلٌ مخفيّ — لا مُنتقٍ يُهيّأ
        if ($ && $.fn.select2 && client && client.tagName === 'SELECT') {
            $('#rsv-client').select2({
                dir: document.documentElement.dir || 'rtl',
                width: '100%',
                placeholder: text.clientPh,
                allowClear: true,
                ajax: {
                    url: urls.clients,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term }),
                    processResults: data => ({ results: data.results }),
                },
                minimumInputLength: 0,
            });
        }

        // ─────────── الملخّص الجانبي ───────────
        const selectedText = select => {
            // حقلٌ مخفيّ (العميلُ المقفل) يحمل اسمَه في `data-name`
            if (select && select.tagName !== 'SELECT') return select.value ? (select.dataset.name || '').trim() : '';
            const option = select && select.selectedOptions[0];
            if (!option || !option.value) return '';
            return (option.dataset.name || option.textContent).trim();
        };

        const renderSummary = () => {
            el('sum-client').textContent = selectedText(client) || '—';
            el('sum-service').textContent = selectedText(service) || '—';
            el('sum-employee').textContent = selectedText(employee) || text.anyEmployee;

            // التاريخُ والوقتان أرقامٌ لاتينية — الحقلُ `dir=ltr` وإلّا قلبها اتجاهُ الصفحة («09:30–09:00»)
            const when = [date.value, start.value && (start.value + (end.value ? ' – ' + end.value : ''))]
                .filter(Boolean).join('  ·  ');
            el('sum-when').textContent = when || '—';

            el('rsv-end-label').textContent = end.value || '—';
            el('rsv-duration-label').textContent = duration.value || '—';
        };

        // ─────────── أدوات الوقت ───────────
        const toMinutes = value => {
            if (!value) return null;
            const [h, m] = value.split(':').map(Number);
            return (h * 60) + m;
        };

        const toTime = minutes => {
            const clamped = Math.max(0, Math.min(minutes, 23 * 60 + 59));
            return String(Math.floor(clamped / 60)).padStart(2, '0') + ':'
                + String(clamped % 60).padStart(2, '0');
        };

        /* وقتُ النهاية يتبع البدايةَ والمدّة — فلا يُطلب من المستخدم حسابُه */
        const syncEnd = () => {
            const from = toMinutes(start.value);
            const mins = parseInt(duration.value || '0', 10);
            if (from === null || !mins) return;
            end.value = toTime(from + mins);
        };

        /* والمدّةُ تتبع الوقتين حين يضبطهما بيده */
        const syncDuration = () => {
            const from = toMinutes(start.value);
            const to = toMinutes(end.value);
            if (from === null || to === null || to <= from) return;
            duration.value = to - from;
        };

        // «تغيير المدّة» يكشف الحقلين المحسوبين
        el('rsv-time-toggle').addEventListener('click', () => {
            const panel = el('rsv-time-manual');
            panel.hidden = !panel.hidden;
            if (!panel.hidden) duration.focus();
        });

        // ─────────── الأوقات المتاحة ───────────
        let slotsToken = 0;

        const markActiveSlot = () => {
            slots.querySelectorAll('.rsv-slot').forEach(b =>
                b.classList.toggle('is-active', b.dataset.start === start.value));
        };

        /*
            ورديةُ الموظّف: تُعرض تحت اختياره، وتقصر خانةَ البداية على نافذتها،
            ولا وقتَ يُعرض خارجها — الأوقاتُ من الحارس الذي يقرأ الوردية نفسَها.
        */
        const shiftBox = el('rsv-shift');
        const renderShift = shift => {
            if (!shiftBox) return;
            start.removeAttribute('min');
            start.removeAttribute('max');

            if (!shift) {
                shiftBox.hidden = true;
                return;
            }

            let msg;
            let tone = 'ok';
            if (!shift.has_shift) {
                msg = text.shiftNone; tone = 'bad';
            } else if (shift.holiday) {
                msg = text.holiday + ': ' + shift.holiday; tone = 'bad';
            } else if (!shift.working) {
                msg = (shift.name ? shift.name + ' — ' : '') + text.shiftOff; tone = 'bad';
            } else {
                msg = text.shift + (shift.name ? ' «' + shift.name + '»' : '') + ': ' + shift.start + ' – ' + shift.end;
                start.min = shift.start;
                start.max = shift.end;
            }

            el('rsv-shift-text').textContent = msg;
            shiftBox.dataset.tone = tone;
            shiftBox.hidden = false;
        };

        const loadSlots = () => {
            if (!date.value) return;

            if (!employee.value) {
                renderShift(null);
                slots.innerHTML = '<span class="rsv-muted">' + text.pickEmployee + '</span>';
                return;
            }

            const token = ++slotsToken;
            slots.innerHTML = '<span class="rsv-muted">' + text.loading + '</span>';

            const params = new URLSearchParams({ date: date.value });
            if (service.value) params.set('product_id', service.value);
            if (employee.value) params.set('employee_id', employee.value);
            if (ignoreId) params.set('ignore_id', ignoreId);

            fetch(urls.slots + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    // ردٌّ متأخّر من طلبٍ سابق لا يدهس نتيجةَ الأحدث
                    if (token !== slotsToken) return;

                    renderShift(data.shift || null);

                    if (!data.slots || !data.slots.length) {
                        slots.innerHTML = '<span class="rsv-muted">' + text.noSlots + '</span>';
                        return;
                    }

                    slots.innerHTML = data.slots.map(s =>
                        `<button type="button" class="rsv-slot" data-start="${s.start}" data-end="${s.end}">${s.start}</button>`
                    ).join('');
                    markActiveSlot();

                    // حجزٌ جديد ووقتُه خارج الوردية: يُقترح أوّلُ وقتٍ شاغر فيها
                    const sh = data.shift;
                    const outside = sh && sh.working && (!start.value || start.value < sh.start || start.value >= sh.end);
                    if (!ignoreId && outside) {
                        start.value = data.slots[0].start;
                        end.value = data.slots[0].end;
                        syncDuration();
                        markActiveSlot();
                        renderSummary();
                    }
                })
                .catch(() => {
                    if (token !== slotsToken) return;
                    slots.innerHTML = '<span class="rsv-muted text-danger">' + text.slotsFailed + '</span>';
                });
        };

        slots.addEventListener('click', event => {
            const button = event.target.closest('.rsv-slot');
            if (!button) return;

            start.value = button.dataset.start;
            end.value = button.dataset.end;
            syncDuration();
            markActiveSlot();
            renderSummary();
        });

        // ─────────── الخدمة: المدّة والسعر والموظفون ───────────
        const loadService = (applyDefaults) => {
            const params = new URLSearchParams();
            if (service.value) params.set('product_id', service.value);

            fetch(urls.serviceEmployees + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    const previous = employee.value;
                    const previousOption = employee.selectedOptions[0];
                    const previousName = previousOption ? previousOption.textContent : '';

                    employee.innerHTML = '';
                    employee.append(new Option(text.noEmployee, ''));
                    (data.employees || []).forEach(e => employee.append(new Option(e.name, e.id)));

                    // اختيارُ المستخدم يبقى إن كان الموظّف مؤهَّلاً للخدمة الجديدة
                    if (previous && employee.querySelector(`option[value="${previous}"]`)) {
                        employee.value = previous;
                    } else if (previous && !applyDefaults) {
                        // فتحُ التعديل لا يمسح موظّفَ الحجز المحفوظ — الحفظُ يفحص أهليّتَه ويسمّي السبب
                        employee.append(new Option(previousName, previous, true, true));
                    }
                    refreshSelect(employee);

                    if (applyDefaults && data.service) {
                        duration.value = data.service.duration;
                        // السعرُ لا يُدهَس إن كتبه المستخدم بيده — أمّا ما عبّأته خدمةٌ سابقة فيُستبدل
                        if (!price.value || price.dataset.auto === '1') {
                            price.value = data.service.price;
                            price.dataset.auto = '1';
                        }
                        if (data.service.tax_id && (!tax.value || tax.dataset.auto === '1')) {
                            tax.value = data.service.tax_id;
                            tax.dataset.auto = '1';
                            refreshSelect(tax);
                        }
                        syncEnd();
                    }

                    renderSummary();
                    refreshQuote();
                    loadSlots();
                });
        };

        // ─────────── التسعير الحيّ ───────────
        let quoteTimer = null;

        const refreshQuote = () => {
            clearTimeout(quoteTimer);

            // تأخيرٌ قصير: الكتابةُ في خانة السعر تُطلق حدثاً لكل حرف
            quoteTimer = setTimeout(() => {
                const params = new URLSearchParams({
                    discount_value: discount.value || 0,
                    discount_type: discountType.value,
                });
                if (service.value) params.set('product_id', service.value);
                if (price.value !== '') params.set('unit_price', price.value);
                // تُرسَل ولو فارغة: «بلا ضريبة» اختيارٌ صريح، والحفظُ يقرؤها كذلك
                params.set('tax_id', tax.value);

                fetch(urls.quote + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(data => {
                        const fmt = n => Number(n || 0).toLocaleString('en-US', {
                            minimumFractionDigits: 2, maximumFractionDigits: 2,
                        });

                        document.querySelectorAll('[data-sum]').forEach(node => {
                            node.textContent = fmt(data[node.dataset.sum]);
                        });

                        // سطورُ المال تختفي عند الصفر — الغيابُ أوضح من صفرٍ معروض
                        const toggle = (row, value) => {
                            const node = document.querySelector(`[data-row="${row}"]`);
                            if (node) node.style.display = Number(value || 0) > 0 ? '' : 'none';
                        };

                        toggle('discount', data.discount_amount);
                        toggle('tax', data.tax_amount);
                    });
            }, 250);
        };

        // ─────────── الربط ───────────
        date.addEventListener('change', () => { loadSlots(); renderSummary(); });
        listen(employee, 'change', () => { loadSlots(); renderSummary(); });
        listen(service, 'change', () => { loadService(true); renderSummary(); });

        start.addEventListener('change', () => { syncEnd(); markActiveSlot(); renderSummary(); });
        duration.addEventListener('change', () => { syncEnd(); renderSummary(); });
        end.addEventListener('change', () => { syncDuration(); renderSummary(); });

        // select2 يُطلق تغييرَه بـjQuery — والمستمعُ الأصيل لا يسمعه
        if ($) {
            $(client).on('change', renderSummary);
        } else {
            client.addEventListener('change', renderSummary);
        }

        // ما يكتبه المستخدم بيده لا تدهسه الخدمةُ التالية
        // (select2 يُعلن اختيارَ اليد بـ`select2:select`؛ وضبطُ الشيفرة لا يُطلقه)
        [price, tax].forEach(node => listen(node, 'input select2:select select2:clear', () => {
            node.dataset.auto = '0';
        }));

        [price, discount].forEach(node => node.addEventListener('input', refreshQuote));
        [discountType, tax].forEach(node => listen(node, 'change', refreshQuote));

        // الحالة الابتدائية — النهايةُ محسوبةٌ إن لم تُحفظ
        if (!end.value) syncEnd();

        // سعرُ حجزٍ محفوظ يساوي سعرَ خدمته = عبّأته الخدمة، فتغييرُ الخدمة في التعديل يستبدله
        const savedServicePrice = service.selectedOptions[0] && service.selectedOptions[0].dataset.price;
        if (price.value !== '' && savedServicePrice !== undefined && Number(price.value) === Number(savedServicePrice)) {
            price.dataset.auto = '1';
        }
        renderSummary();
        loadService(false);
        refreshQuote();
        loadSlots();
    })();
</script>
