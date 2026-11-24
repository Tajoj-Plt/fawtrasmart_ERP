@extends('hr::master')

@section('title', 'سجّل حضوري')

@push('styles')
<style>
    /*
        شاشةٌ تُستعمل بيدٍ واحدةٍ واقفاً في الشارع: عمودٌ واحد، وهدفُ لمسٍ
        كبير، وحالةٌ تُقرأ من مسافةِ ذراع. لا جداولَ ولا تبويبات.
    */
    .fa-wrap { max-width: 520px; margin: 0 auto; padding-bottom: 24px; }

    .fa-clock { text-align:center; padding:18px 0 8px; }
    .fa-clock__time { font-size:3rem; font-weight:800; line-height:1; letter-spacing:1px;
        color: var(--app-text, #111827); font-variant-numeric: tabular-nums; }
    .fa-clock__date { color: var(--app-muted, #6b7280); font-size:.85rem; margin-top:6px; }

    .fa-office { text-align: center; margin: 8px 0; }
    .fa-office video { width: 100%; max-height: 320px; border-radius: 10px; background: #000; object-fit: cover; }
    .fa-office__hint { font-size: .85rem; color: var(--app-muted, #6b7280); margin: 6px 0; }
    .fa-ways { display: flex; flex-direction: column; gap: 6px; margin-bottom: 6px; }
    .fa-way { font-size: .86rem; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .fa-way i { color: var(--app-primary, #7367f0); }
    .fa-log__where { display: block; color: var(--app-muted, #6b7280); font-size: .75rem; }
    .fa-why__title { font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
    .fa-why__title i { color: var(--app-primary, #7367f0); font-size: 1.2rem; }
    .fa-why__text { color: var(--app-muted, #6b7280); font-size: .88rem; line-height: 1.8; margin-bottom: 8px; }
    .fa-why__step { display: flex; gap: 8px; align-items: flex-start; font-size: .86rem; padding: 8px 10px; border-radius: 8px;
        background: color-mix(in srgb, var(--app-primary, #7367f0) 6%, transparent); margin-top: 6px; line-height: 1.7; }
    .fa-why__step i { margin-top: 4px; color: var(--app-primary, #7367f0); }
    .fa-why--bad { border-color: color-mix(in srgb, var(--app-danger, #ea5455) 45%, transparent); }
    .fa-why--bad .fa-why__title i, .fa-why__step--bad i { color: var(--app-danger, #ea5455); }
    .fa-why__step--bad { background: color-mix(in srgb, var(--app-danger, #ea5455) 8%, transparent); }
    .fa-card { background: var(--app-surface, #fff); border:1px solid var(--app-border, #e5e7eb);
        border-radius:14px; padding:16px; margin-bottom:12px; }

    .fa-id { display:flex; align-items:center; gap:12px; }
    .fa-id__avatar { width:52px; height:52px; border-radius:50%; object-fit:cover; background: var(--app-body-bg, #f3f4f6); }
    .fa-id__name { font-weight:700; font-size:1.05rem; }
    .fa-id__meta { font-size:.8rem; color: var(--app-muted, #6b7280); }

    .fa-shift { display:flex; justify-content:space-around; text-align:center; }
    .fa-shift__item span { display:block; font-size:.72rem; color: var(--app-muted, #6b7280); }
    .fa-shift__item strong { font-size:1.05rem; font-variant-numeric: tabular-nums; }

    /* حالةُ النطاق — اللونُ هو الرسالة */
    .fa-geo { border-width:2px; transition:.2s; }
    .fa-geo--ok      { border-color: var(--app-success, #16a34a); background: color-mix(in srgb, var(--app-success, #16a34a) 8%, transparent); }
    .fa-geo--far     { border-color: var(--app-danger, #dc2626);  background: color-mix(in srgb, var(--app-danger, #dc2626) 8%, transparent); }
    .fa-geo--waiting { border-color: var(--app-warning, #d97706); background: color-mix(in srgb, var(--app-warning, #d97706) 8%, transparent); }
    .fa-geo__head { display:flex; align-items:center; gap:8px; font-weight:700; }
    .fa-geo__dot { width:12px; height:12px; border-radius:50%; background: currentColor; flex:none; }
    .fa-geo--ok .fa-geo__head      { color: var(--app-success, #16a34a); }
    .fa-geo--far .fa-geo__head     { color: var(--app-danger, #dc2626); }
    .fa-geo--waiting .fa-geo__head { color: var(--app-warning, #d97706); }
    .fa-geo__detail { font-size:.8rem; color: var(--app-muted, #6b7280); margin-top:8px; line-height:1.7; }
    .fa-geo__bar { height:6px; border-radius:3px; background: var(--app-border, #e5e7eb); margin-top:10px; overflow:hidden; }
    .fa-geo__bar i { display:block; height:100%; width:0; background: currentColor; transition: width .3s; }

    /* الزرُّ الوحيد */
    .fa-action { width:100%; border:none; border-radius:14px; padding:20px; font-size:1.25rem; font-weight:800;
        color:#fff; display:flex; align-items:center; justify-content:center; gap:10px; transition:.15s; }
    .fa-action--in  { background: var(--app-success, #16a34a); }
    .fa-action--out { background: var(--app-warning, #d97706); }
    .fa-action:disabled { background: var(--app-border, #d1d5db); color: var(--app-muted, #6b7280); cursor:not-allowed; }
    .fa-action:not(:disabled):active { transform: scale(.98); }
    .fa-action__hint { display:block; text-align:center; font-size:.76rem; color: var(--app-muted, #6b7280); margin-top:8px; }

    /* خطُّ اليوم */
    .fa-timeline { display:flex; }
    .fa-timeline__step { flex:1; text-align:center; position:relative; }
    .fa-timeline__step::before { content:''; position:absolute; top:13px; right:50%; left:-50%; height:2px; background: var(--app-border, #e5e7eb); }
    .fa-timeline__step:first-child::before { display:none; }
    .fa-timeline__dot { width:28px; height:28px; border-radius:50%; margin:0 auto 6px; position:relative; z-index:1;
        background: var(--app-border, #e5e7eb); color: var(--app-muted, #6b7280);
        display:flex; align-items:center; justify-content:center; font-size:.8rem; }
    .fa-timeline__step.done .fa-timeline__dot { background: var(--app-success, #16a34a); color:#fff; }
    .fa-timeline__label { font-size:.72rem; color: var(--app-muted, #6b7280); }
    .fa-timeline__value { font-weight:700; font-variant-numeric: tabular-nums; }

    /* سجلُّ الأختام — الشفافيةُ تُغني عن مكالمةٍ مع الموارد البشرية */
    .fa-log { font-size:.78rem; }
    .fa-log__row { display:flex; justify-content:space-between; gap:8px; padding:7px 0; border-bottom:1px dashed var(--app-border, #e5e7eb); }
    .fa-log__row:last-child { border-bottom:none; }
    .fa-log__row.rejected { color: var(--app-danger, #b91c1c); }

    .fa-notice { font-size:.8rem; padding:10px 12px; border-radius:10px; margin-bottom:12px; }
</style>
@endpush

@section('content')
<div class="fa-wrap">

    <div class="fa-clock">
        <div class="fa-clock__time" id="fa-time">—:—:—</div>
        <div class="fa-clock__date" id="fa-date"></div>
    </div>

    {{-- الهويّة: الموظفُ يرى باسم من يُسجَّل هذا الختم قبل أن يضغط --}}
    <div class="fa-card fa-id">
        {{-- `photo_url` من الموديل: كان المسارُ هنا `storage/` ولا صورةَ
             موظفٍ فيها — الرفعُ إلى `assets/uploads/employee/`. --}}
        <img class="fa-id__avatar"
             src="{{ $employee->photo_url ?? asset('images/default-avatar.svg') }}"
             alt="">
        <div>
            <div class="fa-id__name">{{ $employee->full_name }}</div>
            <div class="fa-id__meta">
                {{ $employee->department->name ?? 'بلا قسم' }}
                @if($employee->branch) · {{ $employee->branch->name }} @endif
            </div>
        </div>
    </div>

    @if(! $activeDate->isToday())
        {{-- الوردية الليلية: الختمُ التالي يُسنَد إلى سجلّ الأمس لا إلى اليوم --}}
        <div class="fa-notice alert alert-warning m-0 mb-3">
            <i class="feather icon-moon"></i>
            نوبتُك مفتوحةٌ من <strong>{{ $activeDate->format('Y-m-d') }}</strong> —
            انصرافُك سيُسجَّل عليها.
        </div>
    @endif

    @if($day['is_holiday'] || $day['is_weekly_off'] || $day['leave'])
        <div class="fa-notice alert alert-info m-0 mb-3">
            @if($day['is_holiday'])
                اليوم عطلةٌ رسمية{{ $day['holiday_name'] ? ' («'.$day['holiday_name'].'»)' : '' }} — لا يُسجَّل فيها حضور.
            @elseif($day['leave'])
                لديك إجازةٌ معتمدة تشمل اليوم.
            @else
                اليوم عطلتُك الأسبوعية — لا يُسجَّل فيها حضور.
            @endif
        </div>
    @endif

    {{-- الوردية: المرجعُ الذي يُقاس عليه التأخير --}}
    @php
        $isFlexible = ($day['config']['work_mode'] ?? 'shift') === 'flexible';
        $breakAllowed = (int) ($day['config']['break_allowed_minutes'] ?? 60);
        $onBreak = (bool) $attendance?->break_started_at;
    @endphp
    @if($isFlexible)
    <div class="fa-card fa-shift">
        <div class="fa-shift__item">
            <span>نوع الدوام</span>
            <strong>مرن</strong>
        </div>
        <div class="fa-shift__item">
            <span>الساعات المطلوبة</span>
            <strong>{{ rtrim(rtrim((string) ($day['config']['work_hours_per_day'] ?? 8), '0'), '.') }} ساعة</strong>
        </div>
        <div class="fa-shift__item">
            <span>الاستراحة المسموحة</span>
            <strong>{{ $breakAllowed }} د</strong>
        </div>
    </div>
    @else
    <div class="fa-card fa-shift">
        <div class="fa-shift__item">
            <span>بداية الوردية</span>
            <strong>{{ $day['expected_check_in']?->format('H:i') ?? '—' }}</strong>
        </div>
        <div class="fa-shift__item">
            <span>نهاية الوردية</span>
            <strong>{{ $day['expected_check_out']?->format('H:i') ?? '—' }}</strong>
        </div>
        <div class="fa-shift__item">
            <span>وقت تسجيل الحضور</span>
            <strong>{{ $window['start']?->format('H:i') ?? '—' }} – {{ $window['end']?->format('H:i') ?? '—' }}</strong>
        </div>
        <div class="fa-shift__item">
            <span>السماحية</span>
            <strong>{{ (int) ($day['config']['late_grace_minutes'] ?? 0) }} د</strong>
        </div>
    </div>
    @endif

    {{-- طريقةُ التسجيل المحدَّدة للموظف.
         موظفُ البطاقة لا يرى زرَّ تسجيل هنا — فالبطاقةُ تقول له صراحةً لماذا، وأين يسجّل،
         وماذا يفعل إن لم يستطع. كان العنوانُ «تسجيلك بالبطاقة» وحده، فيظنّ الموظفُ أن الصفحة معطّلة. --}}
    @php
        $cardProblem = $channel === 'field' ? null : (! $employee->barcode
            ? 'لم تُصدر لك بطاقة حضور بعد.'
            : ($employee->barcode_enabled === false ? 'بطاقة حضورك موقوفة.' : null));
    @endphp
    <div class="fa-card fa-why {{ $cardProblem ? 'fa-why--bad' : '' }}">
        <div class="fa-why__title">
            <i class="feather icon-{{ $channel === 'branch' ? 'info' : 'check-square' }}"></i>
            {{ $channel === 'branch' ? 'لماذا لا يظهر لك زر تسجيل الحضور هنا؟' : 'طرق تسجيل حضورك' }}
        </div>
        <div class="fa-ways" data-hint-keep>
            @if($channel !== 'field')
                <span class="fa-way"><i class="feather icon-tablet"></i> بالبطاقة على جهاز الفرع:
                    <b>{{ $myKiosks->isNotEmpty() ? $myKiosks->pluck('name')->implode('، ') : 'أي جهاز في الفرع' }}</b></span>
            @endif
            @if($channel !== 'branch')
                <span class="fa-way"><i class="feather icon-smartphone"></i> من الجوال بموقعك:
                    <b>{{ $fence ? 'داخل «'.$fence->name.'» ('.$fence->radius_meters.' م)' : 'من أي مكان ويُحفظ موقعك' }}</b></span>
            @endif
            @if($officeScan)
                <span class="fa-way"><i class="feather icon-maximize"></i> بمسح «رمز المكتب» بجوالك: <b>الرمز على شاشة الجهاز أو الملصق في المكتب</b></span>
            @endif
        </div>

        {{-- «رمز المكتب»: الموظفُ يمسح رمزَ المكتب بكاميرا جواله فيُسجَّل حضورُه أو انصرافُه --}}
        @if($officeScan)
            <button type="button" class="btn btn-primary w-100 my-1" id="fa-office-btn">
                <i class="feather icon-maximize"></i> امسح رمز المكتب
            </button>
            <div class="fa-office" id="fa-office" hidden>
                <video id="fa-office-video" playsinline muted></video>
                <div class="fa-office__hint">وجّه الكاميرا إلى رمز المكتب</div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="fa-office-close">إغلاق</button>
            </div>
            <script src="https://unpkg.com/@zxing/library@0.21.3/umd/index.min.js"></script>
            <script>
                (function () {
                    const btn = document.getElementById('fa-office-btn');
                    const box = document.getElementById('fa-office');
                    const video = document.getElementById('fa-office-video');
                    let reader = null, busy = false;

                    // الموقعُ يُرسل إن أمكن — الرمز المطبوع لا يُقبل بدونه، ورمز الشاشة لا يحتاجه
                    const locate = () => new Promise(resolve => {
                        if (! navigator.geolocation) return resolve(null);
                        navigator.geolocation.getCurrentPosition(
                            p => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: Math.round(p.coords.accuracy) }),
                            () => resolve(null), { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 });
                    });

                    /*
                        مكانُ المكتب — يُفحَص **قبل** فتح الكاميرا.

                        كان الزرُّ يفتح الكاميرا أوّلاً ثم يُقرأ الرمزُ ثم
                        يُطلب الموقعُ ثم يردّ الخادمُ «أنت خارج النطاق»: كاميرا
                        تُفتح ومسحٌ يُجرى لتنتهي إلى ردٍّ كان معلوماً قبلها.
                        فالفحصُ أوّلاً، ومن كان خارج الموقع يُقال له ولا تُفتح
                        له الكاميرا.
                    */
                    const officeFence = @json($branchFencePayload ?? null);

                    function metresBetween(lat1, lon1, lat2, lon2) {
                        const R = 6371000, toRad = d => d * Math.PI / 180;
                        const dLat = toRad(lat2 - lat1), dLon = toRad(lon2 - lon1);
                        const a = Math.sin(dLat / 2) ** 2
                            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
                        return Math.round(R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
                    }

                    /**
                     * `null` = امضِ وافتح الكاميرا. وإلّا فكائنُ رسالةٍ تُعرَض
                     * ولا تُفتح. ويُعاد الموقعُ ليُرسَل مع الرمز فلا يُطلب مرّتين.
                     */
                    async function officeLocationGate() {
                        // بلا مكانٍ مضبوطٍ لهذا الموظف لا دائرةَ تُقاس — السلوكُ كما كان
                        if (! officeFence || officeFence.latitude === null || officeFence.longitude === null) {
                            return { ok: true, position: null };
                        }

                        btn.disabled = true;
                        const original = btn.innerHTML;
                        btn.innerHTML = '<i class="feather icon-navigation"></i> جارٍ تحديد موقعك…';

                        const position = await locate();

                        btn.innerHTML = original;
                        btn.disabled = false;

                        if (! position) {
                            return { ok: false, icon: 'warning', title: 'الموقع مطلوب',
                                text: 'فعّل خدمة الموقع في جوالك — لا يُقبل التسجيل قبل التأكّد من أنك داخل «' + officeFence.name + '».' };
                        }

                        if (officeFence.max_accuracy_meters > 0 && position.accuracy > officeFence.max_accuracy_meters) {
                            return { ok: false, icon: 'warning', title: 'دقّة الموقع غير كافية',
                                text: 'قراءةُ جوالك ±' + position.accuracy + ' متر والمسموح ±'
                                    + officeFence.max_accuracy_meters + ' متر. انتقل إلى مكانٍ مكشوف وأعد المحاولة.' };
                        }

                        const distance = metresBetween(
                            position.latitude, position.longitude,
                            officeFence.latitude, officeFence.longitude
                        );

                        if (distance > officeFence.radius_meters) {
                            return { ok: false, icon: 'error', title: 'أنت خارج الموقع',
                                text: 'تبعد ' + distance.toLocaleString('ar-EG') + ' متراً عن «' + officeFence.name
                                    + '» والمسموح ' + officeFence.radius_meters + ' متر — اقترب '
                                    + (distance - officeFence.radius_meters).toLocaleString('ar-EG') + ' متراً ثم أعد المحاولة.' };
                        }

                        return { ok: true, position: position };
                    }
                    const stop = () => { try { reader && reader.reset(); } catch (e) {} box.hidden = true; btn.disabled = false; };
                    const say = (icon, title, text) => window.Swal ? Swal.fire({ icon, title, text, confirmButtonText: 'حسناً' }) : alert(title + ' — ' + (text || ''));

                    async function submit(code, knownPosition) {
                        if (busy) return; busy = true; stop();
                        const isPrinted = (code.split('|')[2] || '').startsWith('P');
                        // الموقعُ المقروءُ قبل فتح الكاميرا يكفي — لا يُطلب مرّتين
                        const position = knownPosition || await locate();
                        if (isPrinted && ! position) { busy = false; return say('warning', 'الموقع مطلوب', 'فعّل الموقع في جوالك — الرمز المطبوع لا يُقبل إلا وأنت داخل المكتب.'); }
                        try {
                            const r = await fetch(@json(route('hr.field-attendance.office_scan')), {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                                body: JSON.stringify(Object.assign({ code }, position || {})),
                            });
                            const data = await r.json();
                            if (data.success) {
                                await say('success', data.punch_type === 'out' ? 'تم تسجيل انصرافك' : 'تم تسجيل حضورك', (data.kiosk ? 'على «' + data.kiosk + '» — ' : '') + (data.message || ''));
                                location.reload();
                            } else {
                                say('error', 'لم يُسجَّل', data.message || 'تعذّر التسجيل');
                            }
                        } catch (e) {
                            say('error', 'خطأ في الاتصال', 'حاول مجدداً.');
                        }
                        busy = false;
                    }

                    btn.addEventListener('click', async function () {
                        if (! window.ZXing) return say('error', 'تعذّر فتح الكاميرا', 'حدّث الصفحة وحاول مجدداً.');

                        // الموقعُ أوّلاً: خارج المكان لا كاميرا
                        const gate = await officeLocationGate();
                        if (! gate.ok) {
                            return say(gate.icon, gate.title, gate.text);
                        }

                        box.hidden = false; btn.disabled = true;
                        reader = new ZXing.BrowserQRCodeReader();
                        reader.decodeFromConstraints({ video: { facingMode: 'environment' } }, video, function (result) {
                            if (result) submit(result.getText(), gate.position);
                        }).catch(function () { stop(); say('error', 'تعذّر فتح الكاميرا', 'اسمح للمتصفح باستخدام الكاميرا ثم أعد المحاولة.'); });
                    });
                    document.getElementById('fa-office-close').addEventListener('click', stop);
                })();
            </script>
        @endif
    @if($channel !== 'field')
            @if($channel === 'branch')
                <p class="fa-why__text">
                    طريقة تسجيلك التي حددتها الموارد البشرية هي <strong>البطاقة على جهاز الفرع</strong>،
                    لذلك تسجّل حضورك وانصرافك بمسح بطاقتك على الجهاز — لا من هذه الصفحة.
                    هنا تتابع يومك وسجل محاولاتك فقط.
                </p>
            @endif

            @if($cardProblem)
                <div class="fa-why__step fa-why__step--bad">
                    <i class="feather icon-alert-triangle"></i>
                    <span>{{ $cardProblem }} راجع الموارد البشرية لإصدار بطاقة أو تفعيلها، وإلى أن تُصدر اطلب منهم تسجيل حضورك بطلب تصحيح.</span>
                </div>
            @elseif($myKiosks->isNotEmpty())
                {{-- أجهزتُه المربوطة — هي وحدها تقبل بطاقته --}}
                <div class="fa-why__step">
                    <i class="feather icon-tablet"></i>
                    <span>
                        امسح بطاقتك (رقم {{ $employee->barcode }}) على
                        {{ $myKiosks->count() > 1 ? 'أحد هذه الأجهزة' : 'هذا الجهاز' }} فقط — غيره يرفض بطاقتك:
                        <ul class="mb-0 mt-25 ps-3">
                            @foreach($myKiosks as $k)
                                <li><strong>{{ $k->name }}</strong>{{ $k->geofence ? ' — '.$k->geofence->name : ($k->branch ? ' — '.$k->branch->name : '') }}</li>
                            @endforeach
                        </ul>
                    </span>
                </div>
            @else
                <div class="fa-why__step">
                    <i class="feather icon-tablet"></i>
                    <span>امسح بطاقتك (رقم {{ $employee->barcode }}) على أي جهاز تسجيل في الفرع عند الحضور والانصراف.</span>
                </div>
            @endif

            @if($channel === 'branch')
                <div class="fa-why__step">
                    <i class="feather icon-map"></i>
                    <span>تعمل خارج الفرع (عند العملاء أو في الميدان)؟ اطلب من الموارد البشرية تغيير طريقة تسجيلك إلى «ميداني» أو «الاثنين»، فيظهر لك هنا زر التسجيل من الجوال.</span>
                </div>
                <div class="fa-why__step">
                    <i class="feather icon-edit-3"></i>
                    <span>نسيت المسح أو تعطّل الجهاز؟ قدّم <strong>طلب تصحيح حضور</strong> من أسفل الصفحة.</span>
                </div>
            @else
                <div class="fa-why__step">
                    <i class="feather icon-smartphone"></i>
                    <span>وإن كنت خارج الفرع يمكنك التسجيل من جوالك بالزر أدناه.</span>
                </div>
            @endif
    @endif
    </div>

    {{-- سببُ تعطّل زرّ الحضور الآن (خارج الوقت، يومٌ مصحَّح) — ظاهرٌ لا تلميحٌ صغير تحت زرٍّ رمادي.
         العطلةُ والإجازة لهما شريطُهما أعلاه --}}
    @if($channel !== 'branch' && $checkInBlock && ! ($day['is_holiday'] || $day['is_weekly_off'] || $day['leave']))
        <div class="fa-card fa-why fa-why--bad">
            <div class="fa-why__title"><i class="feather icon-clock"></i> لماذا لا تستطيع تسجيل الحضور الآن؟</div>
            <p class="fa-why__text mb-0">{{ $checkInBlock }}</p>
        </div>
    @endif

    @if($channel !== 'branch')
    {{-- حالةُ النطاق: تُحدَّث حيّاً، فيعرف الموظفُ قبل الضغط أيُقبل أم يُردّ --}}
    <div class="fa-card fa-geo fa-geo--waiting" id="fa-geo">
        <div class="fa-geo__head">
            <span class="fa-geo__dot"></span>
            <span id="fa-geo-title">جارٍ تحديد موقعك…</span>
        </div>
        <div class="fa-geo__detail" id="fa-geo-detail">
            @if($fence)
                يُقبل تسجيلك داخل: {{ $fence->name }} — مسافة {{ $fence->radius_meters }} متر
            @else
                يُقبل تسجيلك من أي مكان، ويُحفظ موقعك ليراه مديرك.
            @endif
        </div>
        <div class="fa-geo__bar"><i id="fa-geo-bar"></i></div>
    </div>

    {{-- الزرُّ الواحد: حالتُه مشتقّةٌ من سجل اليوم لا من اختيار الموظف --}}
    <div class="fa-card">
        <button id="fa-action" class="fa-action fa-action--in" disabled>
            <i class="feather icon-log-in"></i><span id="fa-action-text">تسجيل حضور</span>
        </button>
        <small class="fa-action__hint" id="fa-action-hint">في انتظار إشارة الموقع…</small>
    </div>

    {{-- الاستراحة: أثناء الدوام فقط — لا تُحسب من الدوام، والزائدُ عن المسموح نقص --}}
    <div class="fa-card" id="fa-break" @if(! ($attendance?->check_in_time && ! $attendance?->check_out_time)) hidden @endif>
        <div class="d-flex align-items-center justify-content-between" style="gap:10px">
            <div>
                <div style="font-weight:700"><i class="feather icon-coffee"></i> الاستراحة</div>
                <div class="text-muted" style="font-size:.85rem" id="fa-break-info">
                    استخدمت {{ (int) ($attendance?->break_minutes ?? 0) }} من {{ $breakAllowed }} دقيقة
                </div>
                <div class="text-warning" style="font-size:.85rem" id="fa-break-state">
                    {{ $onBreak ? 'أنت في استراحة منذ '.$attendance->break_started_at->format('H:i') : '' }}
                </div>
            </div>
            <button type="button" id="fa-break-btn" class="btn {{ $onBreak ? 'btn-warning' : 'btn-outline-warning' }}" data-action="{{ $onBreak ? 'end' : 'start' }}">
                {{ $onBreak ? 'إنهاء الاستراحة' : 'بدء استراحة' }}
            </button>
        </div>
    </div>
    @endif

    {{-- خطُّ اليوم --}}
    <div class="fa-card fa-timeline">
        <div class="fa-timeline__step {{ $attendance?->check_in_time ? 'done' : '' }}" id="fa-step-in">
            <div class="fa-timeline__dot"><i class="feather icon-log-in"></i></div>
            <div class="fa-timeline__label">الحضور</div>
            <div class="fa-timeline__value" id="fa-in-value">{{ $attendance?->check_in_time ? substr($attendance->check_in_time, 0, 5) : '—' }}</div>
        </div>
        <div class="fa-timeline__step {{ $attendance?->check_out_time ? 'done' : '' }}" id="fa-step-out">
            <div class="fa-timeline__dot"><i class="feather icon-log-out"></i></div>
            <div class="fa-timeline__label">الانصراف</div>
            <div class="fa-timeline__value" id="fa-out-value">{{ $attendance?->check_out_time ? substr($attendance->check_out_time, 0, 5) : '—' }}</div>
        </div>
    </div>

    {{-- سجلُّ محاولاتي: المقبولةُ والمردودة معاً.
         الموظفُ الذي رُدَّ تسجيلُه أمس يرى السببَ بنفسه بدل أن يسأل. --}}
    <div class="fa-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong style="font-size:.9rem;">آخر محاولاتي</strong>
            @if(hasPermission('attendance_corrections.create'))
                <a href="{{ route('hr.attendance-corrections.create') }}" class="btn btn-outline-secondary btn-sm">طلب تصحيح</a>
            @endif
        </div>

        <div class="fa-log">
            @forelse($recentPunches as $punch)
                <div class="fa-log__row {{ $punch->result === 'rejected' ? 'rejected' : '' }}">
                    <span>
                        {{ $punch->punched_at->format('m-d H:i') }}
                        · {{ ['in' => 'حضور', 'out' => 'انصراف', 'rest_in' => 'بدء استراحة', 'rest_out' => 'عودة من استراحة'][$punch->punch_type] ?? $punch->punch_type }}
                        <small class="fa-log__where">
                            @if($punch->channel === 'kiosk')
                                <i class="feather icon-tablet"></i> جهاز الفرع{{ $punch->kiosk ? ' «'.$punch->kiosk->name.'»' : '' }} · {{ $punch->methodLabel() }}
                            @elseif($punch->channel === 'field')
                                <i class="feather icon-smartphone"></i> الجوال{{ $punch->geofence ? ' — داخل «'.$punch->geofence->name.'»' : ($punch->latitude ? ' — موقع محفوظ' : '') }}
                            @else
                                <i class="feather icon-edit-3"></i> {{ $punch->channelLabel() }}
                            @endif
                        </small>
                    </span>
                    <span>
                        @if($punch->result === 'rejected')
                            {{ $punch->rejection_reason }}
                        @elseif($punch->adjustment_code === 'capped_to_shift_end')
                            {{-- الموظف يرى القصَّ في سجلّه، لا في قسيمة آخر الشهر --}}
                            اعتُمد {{ substr($punch->applied_time, 0, 5) }} (نهاية الوردية)
                        @else
                            مقبول{{ $punch->distance_meters !== null ? ' — '.$punch->distance_meters.' م' : '' }}
                        @endif
                    </span>
                </div>
            @empty
                <p class="text-muted text-center m-0">لا توجد محاولاتٌ سابقة</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const routes = {
        status:   '{{ route("hr.field-attendance.status") }}',
        checkIn:  '{{ route("hr.field-attendance.checkin") }}',
        checkOut: '{{ route("hr.field-attendance.checkout") }}',
        breakToggle: '{{ route("hr.field-attendance.break") }}',
    };

    const el = {
        time: document.getElementById('fa-time'),
        date: document.getElementById('fa-date'),
        geo: document.getElementById('fa-geo'),
        geoTitle: document.getElementById('fa-geo-title'),
        geoDetail: document.getElementById('fa-geo-detail'),
        geoBar: document.getElementById('fa-geo-bar'),
        action: document.getElementById('fa-action'),
        actionText: document.getElementById('fa-action-text'),
        actionHint: document.getElementById('fa-action-hint'),
    };

    let fence = @json($fencePayload);

    let position = null;   // آخر قراءةٍ صالحة
    let mode = @json($initialMode);
    let busy = false;

    /* ───── الساعة: من ساعة الخادم لا من ساعة الجهاز ───── */
    let serverOffsetMs = 0;

    function renderClock() {
        const now = new Date(Date.now() + serverOffsetMs);
        el.time.textContent = now.toLocaleTimeString('ar-EG', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        el.date.textContent = now.toLocaleDateString('ar-EG', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }
    renderClock();
    setInterval(renderClock, 1000);

    // موظفُ الفرع: الشاشةُ للمتابعة فقط — لا موقعَ ولا زرّ تسجيل
    if (! el.action) { return; }

    /* ───── المسافة: نفسُ صيغة Haversine التي يحسب بها الخادم ───── */
    function distanceMeters(lat1, lon1, lat2, lon2) {
        const R = 6371000, toRad = d => d * Math.PI / 180;
        const dLat = toRad(lat2 - lat1), dLon = toRad(lon2 - lon1);
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
        return Math.round(R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
    }

    /*
        watchPosition لا getCurrentPosition: الموظفُ يمشي نحو المرفق،
        والشاشةُ يجب أن تُخبره متى دخل النطاق — لا أن تُجمّد قراءةً
        التقطتها عند فتح الصفحة ثم تردّ ضغطتَه بعد دقيقتين.
    */
    if (navigator.geolocation) {
        navigator.geolocation.watchPosition(
            pos => {
                position = {
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: Math.round(pos.coords.accuracy),
                };
                renderGeo();
            },
            err => {
                position = null;
                renderGeo(err.code === err.PERMISSION_DENIED
                    ? 'إذنُ الموقع مرفوض — فعّله من إعدادات المتصفح لتتمكّن من التسجيل.'
                    : 'تعذّر تحديد موقعك. تأكّد من تفعيل خدمة الموقع.');
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
        );
    } else {
        renderGeo('هذا الجهاز لا يدعم تحديد الموقع.');
    }

    function renderGeo(errorText) {
        el.geo.classList.remove('fa-geo--ok', 'fa-geo--far', 'fa-geo--waiting');

        if (errorText) {
            el.geo.classList.add('fa-geo--far');
            el.geoTitle.textContent = 'الموقع غير متاح';
            el.geoDetail.textContent = errorText;
            el.geoBar.style.width = '0';
            return refreshAction('لا يمكن التسجيل بدون موقع');
        }

        if (! position) {
            el.geo.classList.add('fa-geo--waiting');
            el.geoTitle.textContent = 'جارٍ تحديد موقعك…';
            return refreshAction('في انتظار إشارة الموقع…');
        }

        // لا نطاقَ مضبوط: يُسجَّل الموقعُ للتوثيق ولا يُقيَّد الختم
        if (! fence || ! fence.requires_geo || fence.latitude === null) {
            el.geo.classList.add('fa-geo--ok');
            el.geoTitle.textContent = 'الموقع جاهز';
            el.geoDetail.textContent = `يُقبل تسجيلك من أي مكان — دقّة موقعك ±${position.accuracy} متر، ويُحفظ ليراه مديرك.`;
            el.geoBar.style.width = '100%';
            return refreshAction(null);
        }

        const distance = distanceMeters(position.latitude, position.longitude, fence.latitude, fence.longitude);
        const inside = distance <= fence.radius_meters;
        const accurate = ! fence.max_accuracy_meters || position.accuracy <= fence.max_accuracy_meters;

        el.geoBar.style.width = Math.max(4, Math.min(100, (1 - distance / (fence.radius_meters * 3)) * 100)) + '%';
        el.geoDetail.innerHTML =
            `المرفق: <strong>${fence.name}</strong> — النطاق ${fence.radius_meters} متر<br>` +
            `تبعد <strong>${distance.toLocaleString('ar-EG')}</strong> متراً · دقّة القراءة ±${position.accuracy} م`;

        if (! accurate) {
            el.geo.classList.add('fa-geo--waiting');
            el.geoTitle.textContent = 'دقّةُ الموقع غير كافية';
            return refreshAction(`المطلوب دقّة ±${fence.max_accuracy_meters} م — انتقل إلى مكانٍ مكشوف`);
        }

        if (! inside) {
            el.geo.classList.add('fa-geo--far');
            el.geoTitle.textContent = 'خارج نطاق المرفق';
            return refreshAction(`اقترب ${(distance - fence.radius_meters).toLocaleString('ar-EG')} متراً لتتمكّن من التسجيل`);
        }

        el.geo.classList.add('fa-geo--ok');
        el.geoTitle.textContent = 'داخل نطاق المرفق';
        refreshAction(null);
    }

    /*
        الزرُّ يُعطَّل ما لم يكن الختمُ مقبولاً — لا يُترك مُفعَّلاً ليُردّ
        من الخادم. والخادمُ يفحص من جديدٍ على أي حال: هذه راحةُ مستخدمٍ
        لا حراسة.
    */
    // سببٌ من الخادم يمنع الحضور الآن (خارج الوقت، عطلة، يوم مصحَّح) — يغلب إشارةَ الموقع
    const checkInBlock = @json($checkInBlock);

    function refreshAction(blockReason) {
        if (mode === 'in' && checkInBlock) {
            blockReason = checkInBlock;
        }
        if (mode === 'done') {
            el.action.disabled = true;
            el.action.className = 'fa-action';
            el.actionText.textContent = 'اكتمل يومُك';
            el.actionHint.textContent = 'سُجِّل حضورُك وانصرافُك لهذا اليوم';
            return;
        }

        el.action.className = 'fa-action fa-action--' + mode;
        el.actionText.textContent = mode === 'in' ? 'تسجيل حضور' : 'تسجيل انصراف';
        el.action.disabled = Boolean(blockReason) || busy;
        el.actionHint.textContent = blockReason || (mode === 'in'
            ? 'اضغط لتسجيل حضورك الآن'
            : 'اضغط لتسجيل انصرافك الآن');
    }

    refreshAction('في انتظار إشارة الموقع…');

    el.action.addEventListener('click', async function () {
        if (busy || mode === 'done') return;

        busy = true;
        refreshAction('جارٍ الإرسال…');

        Swal.fire({ title: 'جارٍ التسجيل…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            const response = await fetch(mode === 'in' ? routes.checkIn : routes.checkOut, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({
                    latitude: position?.latitude,
                    longitude: position?.longitude,
                    accuracy: position?.accuracy,
                }),
            });

            const data = await response.json();
            Swal.close();

            if (! data.success) {
                const stages = {
                    employee: 'ملف الموظف', shift: 'الوردية', location: 'الموقع الجغرافي',
                    day: 'سجل اليوم', leave: 'الإجازات', photos: 'صور التسجيل',
                };
                Swal.fire({
                    icon: 'error',
                    title: data.stage ? `رُدّ عند: ${stages[data.stage] || data.stage}` : 'تعذّر التسجيل',
                    text: data.message,
                    confirmButtonText: 'حسناً',
                });
                busy = false;
                return renderGeo();
            }

            applyAccepted(data);
        } catch (e) {
            Swal.close();
            Swal.fire({ icon: 'error', title: 'خطأ في الاتصال', text: 'تعذّر الوصول إلى الخادم — حاول مجدداً.' });
            busy = false;
            renderGeo();
        }
    });

    const breakBtn = document.getElementById('fa-break-btn');
    breakBtn?.addEventListener('click', async function () {
        const action = breakBtn.dataset.action;
        breakBtn.disabled = true;
        try {
            const response = await fetch(routes.breakToggle, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ action, latitude: position?.latitude, longitude: position?.longitude, accuracy: position?.accuracy }),
            });
            const data = await response.json();
            if (! data.success) {
                Swal.fire({ icon: 'error', title: 'تعذّر', text: data.message, confirmButtonText: 'حسناً' });
                return;
            }
            const d = data.data || {};
            const onBreak = !! d.on_break;
            breakBtn.dataset.action = onBreak ? 'end' : 'start';
            breakBtn.textContent = onBreak ? 'إنهاء الاستراحة' : 'بدء استراحة';
            breakBtn.className = 'btn ' + (onBreak ? 'btn-warning' : 'btn-outline-warning');
            document.getElementById('fa-break-info').textContent = `استخدمت ${d.break_minutes ?? 0} من ${d.break_allowed_minutes ?? 0} دقيقة`;
            document.getElementById('fa-break-state').textContent = onBreak ? `أنت في استراحة منذ ${d.break_started_at}` : '';
            Swal.fire({
                icon: (data.warnings || []).length ? 'warning' : 'success',
                title: data.message,
                text: (data.warnings || []).join(' '),
                timer: (data.warnings || []).length ? undefined : 1800,
                showConfirmButton: !! (data.warnings || []).length,
            });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'خطأ في الاتصال', text: 'حاول مجدداً.' });
        } finally {
            breakBtn.disabled = false;
        }
    });

    function applyAccepted(data) {
        const d = data.data || {};

        if (mode === 'in') {
            document.getElementById('fa-in-value').textContent = (d.check_in_time || '').substring(0, 5);
            document.getElementById('fa-step-in').classList.add('done');
            mode = 'out';
            document.getElementById('fa-break')?.removeAttribute('hidden');
        } else {
            document.getElementById('fa-out-value').textContent = (d.check_out_time || '').substring(0, 5);
            document.getElementById('fa-step-out').classList.add('done');
            mode = 'done';
            document.getElementById('fa-break')?.setAttribute('hidden', '');
        }

        let html = `<div class="text-right">
            <strong>الوقت:</strong> ${d.check_out_time || d.check_in_time}<br>
            <strong>الحالة:</strong> ${d.status || '—'}${d.late_minutes ? ` (تأخير ${d.late_minutes} دقيقة)` : ''}<br>
            ${d.location ? `<strong>الموقع:</strong> ${d.location} — ${d.distance_meters ?? '—'} م<br>` : ''}
            ${d.working_hours ? `<strong>ساعات العمل:</strong> ${d.working_hours}` : ''}
        </div>`;

        if (data.warnings && data.warnings.length) {
            html += '<div class="alert alert-warning text-right mt-2 mb-0" style="font-size:.8rem;">'
                + data.warnings.map(w => `• ${w}`).join('<br>') + '</div>';
        }

        Swal.fire({ icon: 'success', title: data.message, html, confirmButtonText: 'تمام' });

        busy = false;
        renderGeo();
    }

    /*
        مزامنةٌ دورية: قد يُسجَّل انصرافُ الموظف من جهةٍ أخرى (تصحيحٌ
        معتمد، أو جهازُ فرعٍ زاره)، فتبقى هذه الشاشةُ تعرض زرّاً لا معنى له.
    */
    async function syncStatus() {
        try {
            const data = await (await fetch(routes.status, { headers: { 'Accept': 'application/json' } })).json();
            if (! data.success) return;

            const banner = document.getElementById('fa-overnight');
            if (banner) banner.style.display = data.is_overnight_shift ? '' : 'none';

            const serverNow = new Date(`${data.date}T${data.server_time}`);
            if (! isNaN(serverNow)) serverOffsetMs = serverNow.getTime() - Date.now();

            fence = data.geofence;

            const nextMode = data.today.check_out_time ? 'done' : (data.today.check_in_time ? 'out' : 'in');
            if (nextMode !== mode) {
                mode = nextMode;
                document.getElementById('fa-in-value').textContent = (data.today.check_in_time || '').substring(0, 5) || '—';
                document.getElementById('fa-out-value').textContent = (data.today.check_out_time || '').substring(0, 5) || '—';
                document.getElementById('fa-step-in').classList.toggle('done', Boolean(data.today.check_in_time));
                document.getElementById('fa-step-out').classList.toggle('done', Boolean(data.today.check_out_time));
                renderGeo();
            }
        } catch (e) { /* الشبكةُ الميدانية متقطّعة — التعذُّر هنا لا يُبلَّغ */ }
    }

    syncStatus();
    setInterval(syncStatus, 60000);
});
</script>
@endsection
