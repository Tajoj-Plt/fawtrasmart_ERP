@extends('client::portal.layout')

{{--
    ملفّ العميل.

    ══════════════════ ثلاث طبقات، لا نموذجٌ واحد ══════════════════

    كانت الصفحة قائمةَ حقولٍ متلاصقة بلا تمييز: الاسم والحدّ الائتماني
    والرصيد الافتتاحي ورقم الكود في شبكةٍ واحدة، كأنّها كلُّها قابلةٌ
    للتعديل. وهي ليست كذلك — الخادم يقبل `CLIENT_SELF_EDITABLE` وحدها،
    وما عداها يُهمَل بصمت. فحقلٌ يبدو مفتوحاً ولا يُحفظ يَعِد بما لا يقع.

    فصارت ثلاثاً بترتيب ما يفعله الزائر:
      ١. **بطاقة الهوية** — من أنت عندنا: الاسم، الرقم، الحالة، أرقام حسابك.
         للقراءة، وهي أوّل ما تريد العينُ تأكيدَه.
      ٢. **ما تعدّله** — تواصلك وعنوانك وبياناتك النظامية، في أقسامٍ مفصولة.
      ٣. **ما تديره منشأتُنا** — الحدّ الائتماني ونحوه، معزولاً وموسوماً
         «للاطّلاع» بوضوح، لا مختلطاً بما يُكتب.

    ══════════════════ شريط الحفظ ══════════════════

    ملتصقٌ بأسفل الشاشة (`sticky`) ولا يظهر إلّا بعد أوّل تغيير: نموذجٌ
    طويلٌ على هاتفٍ يعني أن زرّ الحفظ يبعد شاشتين عن الحقل الذي غُيِّر،
    ومن لا يجده يظنّ أن لا حفظ.
--}}

@section('title', 'بياناتي')
@section('subtitle', 'راجع بياناتك عندنا وعدّل ما تحتاج تعديله')

@section('styles')
    <style>
        /* ══════════ بطاقة الهوية ══════════ */
        .cp-id {
            position: relative; overflow: hidden;
            background:
                radial-gradient(120% 140% at 100% 0%, color-mix(in srgb, var(--cp-primary) 22%, transparent) 0%, transparent 60%),
                linear-gradient(135deg, color-mix(in srgb, var(--cp-primary) 92%, #000) 0%, var(--cp-primary) 100%);
            /* نصٌّ محسوبٌ على اللون الأساسي في «مظهر النظام» لا أبيضَ ثابتاً */
            color: var(--cp-on-primary);
            border-radius: var(--cp-r-lg);
            padding: var(--cp-6) var(--cp-5);
            margin-bottom: var(--cp-5);
            box-shadow: var(--cp-e3);
        }

        .cp-id__top { display: flex; align-items: center; gap: var(--cp-4); flex-wrap: wrap; }

        .cp-id__avatar {
            flex: none; width: 68px; height: 68px; border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255, 255, 255, .18);
            border: 1px solid rgba(255, 255, 255, .3);
            font-size: 1.7rem; font-weight: 700;
        }

        .cp-id__name { margin: 0; font-size: 1.3rem; font-weight: 700; line-height: 1.3; }
        .cp-id__meta { margin: .3rem 0 0; font-size: .84rem; opacity: .9; display: flex; gap: 1rem; flex-wrap: wrap; }
        .cp-id__meta span { display: inline-flex; align-items: center; gap: .35rem; }

        .cp-id__facts {
            display: grid; gap: var(--cp-3);
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            margin-top: var(--cp-5); padding-top: var(--cp-5);
            border-top: 1px solid rgba(255, 255, 255, .22);
        }

        .cp-id__fact-label { margin: 0; font-size: .74rem; opacity: .8; }
        .cp-id__fact-value { margin: .15rem 0 0; font-size: 1rem; font-weight: 700; font-variant-numeric: tabular-nums; }

        /* ══════════ أقسام النموذج ══════════ */
        .cp-sec { display: flex; align-items: center; gap: var(--cp-3); margin-bottom: var(--cp-4); }

        .cp-sec__icon {
            flex: none; width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            background: var(--cp-primary-soft); color: var(--cp-primary);
        }

        .cp-sec__title { margin: 0; font-size: .95rem; font-weight: 700; }
        .cp-sec__hint { margin: 0; font-size: .76rem; color: var(--cp-muted); }

        /* ══════════ شريط الحفظ ══════════ */
        .cp-savebar {
            position: sticky; bottom: 0; z-index: 20;
            display: flex; align-items: center; gap: var(--cp-3); flex-wrap: wrap;
            margin: 0 calc(-1 * var(--cp-4)) 0;
            padding: var(--cp-4);
            background: color-mix(in srgb, var(--cp-surface) 92%, transparent);
            backdrop-filter: blur(8px);
            border-top: 1px solid var(--cp-line);
            box-shadow: 0 -6px 20px rgba(16, 24, 40, .08);
            transform: translateY(120%);
            transition: transform .2s ease;
        }

        .cp-savebar.is-on { transform: translateY(0); }
        .cp-savebar__note { flex: 1 1 auto; font-size: .82rem; color: var(--cp-muted); margin: 0; }

        /* ══════════ للاطّلاع ══════════ */
        .cp-ro {
            display: grid; gap: var(--cp-4);
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .cp-ro__item {
            padding: var(--cp-4);
            border: 1px dashed var(--cp-line);
            border-radius: var(--cp-r);
            background: color-mix(in srgb, var(--cp-text) 2%, transparent);
        }

        .cp-ro__label { margin: 0; font-size: .76rem; color: var(--cp-muted); }
        .cp-ro__value { margin: .25rem 0 0; font-size: 1rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    </style>
@endsection

@section('content')
    @php
        use App\Support\CurrencySettings;

        $fullName = trim(implode(' ', array_filter([$client->first_name, $client->last_name])));
        $initial = mb_substr(trim($client->trade_name ?: ($fullName ?: '؟')), 0, 1, 'UTF-8');

        $address = implode('، ', array_filter([
            $client->street1, $client->city, $client->region, $client->country,
        ]));
    @endphp

    {{-- ══════════ ١) من أنت عندنا ══════════ --}}
    <section class="cp-id">
        <div class="cp-id__top">
            <span class="cp-id__avatar" aria-hidden="true">{{ $initial }}</span>

            <div style="min-width:0;flex:1 1 auto">
                <h2 class="cp-id__name">{{ $client->trade_name ?: ($fullName ?: 'حسابك') }}</h2>

                <p class="cp-id__meta">
                    @if ($fullName && $client->trade_name)
                        <span><i class="fa fa-user"></i> {{ $fullName }}</span>
                    @endif

                    @if ($client->phone || $client->mobile)
                        <span><i class="fa fa-phone"></i> {{ $client->mobile ?: $client->phone }}</span>
                    @endif

                    @if ($client->email)
                        <span><i class="fa fa-envelope"></i> {{ $client->email }}</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="cp-id__facts">
            <div>
                <p class="cp-id__fact-label">رقم العميل</p>
                <p class="cp-id__fact-value">{{ $client->code ?: '—' }}</p>
            </div>

            <div>
                <p class="cp-id__fact-label">عميلٌ منذ</p>
                <p class="cp-id__fact-value">{{ optional($client->created_at)->format('Y-m-d') ?? '—' }}</p>
            </div>

            @if ($address)
                <div style="grid-column:span 2;min-width:0">
                    <p class="cp-id__fact-label">العنوان</p>
                    <p class="cp-id__fact-value" style="font-size:.9rem;font-weight:600">{{ $address }}</p>
                </div>
            @endif
        </div>
    </section>

    {{-- ══════════ ٢) ما تعدّله بنفسك ══════════ --}}
    <form action="{{ route('clients.Client_store') }}" method="POST" id="cpProfileForm">
        @csrf

        <div class="cp-card">
            <div class="cp-card__body">
                <div class="cp-sec">
                    <span class="cp-sec__icon"><i class="fa fa-address-card"></i></span>
                    <div>
                        <h3 class="cp-sec__title">بيانات التواصل</h3>
                        <p class="cp-sec__hint">كيف نصل إليك — نستعملها في الفواتير والتنبيهات.</p>
                    </div>
                </div>

                <div class="cp-grid">
                    <div class="cp-field">
                        <label for="trade_name">الاسم التجاري</label>
                        <input type="text" id="trade_name" name="trade_name"
                               value="{{ old('trade_name', $client->trade_name) }}">
                    </div>

                    <div class="cp-field">
                        <label for="first_name">الاسم الأول</label>
                        <input type="text" id="first_name" name="first_name"
                               value="{{ old('first_name', $client->first_name) }}">
                    </div>

                    <div class="cp-field">
                        <label for="last_name">اسم العائلة</label>
                        <input type="text" id="last_name" name="last_name"
                               value="{{ old('last_name', $client->last_name) }}">
                    </div>

                    <div class="cp-field">
                        <label for="mobile">الجوال</label>
                        <input type="tel" id="mobile" name="mobile" inputmode="tel"
                               value="{{ old('mobile', $client->mobile) }}">
                    </div>

                    <div class="cp-field">
                        <label for="phone">هاتف آخر</label>
                        <input type="tel" id="phone" name="phone" inputmode="tel"
                               value="{{ old('phone', $client->phone) }}">
                    </div>

                    <div class="cp-field">
                        <label for="email">البريد الإلكتروني</label>
                        <input type="email" id="email" name="email"
                               value="{{ old('email', $client->email) }}">
                        <span class="cp-field__hint">هو اسم دخولك إلى البوابة — تغييره يغيّر اسم دخولك.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="cp-card">
            <div class="cp-card__body">
                <div class="cp-sec">
                    <span class="cp-sec__icon"><i class="fa fa-map-marker-alt"></i></span>
                    <div>
                        <h3 class="cp-sec__title">العنوان</h3>
                        <p class="cp-sec__hint">عنوان التسليم والمراسلة.</p>
                    </div>
                </div>

                <div class="cp-grid">
                    <div class="cp-field">
                        <label for="street1">الشارع</label>
                        <input type="text" id="street1" name="street1" value="{{ old('street1', $client->street1) }}">
                    </div>

                    <div class="cp-field">
                        <label for="street2">تفاصيل إضافية</label>
                        <input type="text" id="street2" name="street2" value="{{ old('street2', $client->street2) }}">
                    </div>

                    <div class="cp-field">
                        <label for="city">المدينة</label>
                        <input type="text" id="city" name="city" value="{{ old('city', $client->city) }}">
                    </div>

                    <div class="cp-field">
                        <label for="region">المنطقة</label>
                        <input type="text" id="region" name="region" value="{{ old('region', $client->region) }}">
                    </div>

                    <div class="cp-field">
                        <label for="postal_code">الرمز البريدي</label>
                        <input type="text" id="postal_code" name="postal_code" inputmode="numeric"
                               value="{{ old('postal_code', $client->postal_code) }}">
                    </div>

                    <div class="cp-field">
                        <label for="country">الدولة</label>
                        <input type="text" id="country" name="country" value="{{ old('country', $client->country) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="cp-card">
            <div class="cp-card__body">
                <div class="cp-sec">
                    <span class="cp-sec__icon"><i class="fa fa-file-signature"></i></span>
                    <div>
                        <h3 class="cp-sec__title">البيانات النظامية</h3>
                        <p class="cp-sec__hint">تُطبع على فواتيرك — راجعها إن تغيّرت.</p>
                    </div>
                </div>

                <div class="cp-grid">
                    <div class="cp-field">
                        <label for="tax_number">الرقم الضريبي</label>
                        <input type="text" id="tax_number" name="tax_number" inputmode="numeric"
                               value="{{ old('tax_number', $client->tax_number) }}">
                    </div>

                    <div class="cp-field">
                        <label for="commercial_registration">السجل التجاري</label>
                        <input type="text" id="commercial_registration" name="commercial_registration" inputmode="numeric"
                               value="{{ old('commercial_registration', $client->commercial_registration) }}">
                    </div>
                </div>

                <div class="cp-field" style="margin-top:1rem">
                    <label for="notes">ملاحظات تودّ أن نعرفها</label>
                    <textarea id="notes" name="notes" rows="3">{{ old('notes', $client->notes) }}</textarea>
                </div>
            </div>
        </div>

        {{-- شريط الحفظ: مخفيٌّ حتى يقع تغييرٌ فعلي --}}
        <div class="cp-savebar cp-noprint" id="cpSaveBar">
            <p class="cp-savebar__note"><i class="fa fa-info-circle"></i> لديك تعديلات لم تُحفظ.</p>
            <button type="button" class="cp-btn cp-btn--ghost" id="cpResetBtn">تراجع</button>
            <button type="submit" class="cp-btn cp-btn--primary"><i class="fa fa-save"></i> حفظ التعديلات</button>
        </div>
    </form>

    {{-- ══════════ ٣) ما تديره منشأتُنا ══════════ --}}
    <div class="cp-card" style="margin-top:var(--cp-5)">
        <div class="cp-card__head">
            <h2 class="cp-card__title"><i class="fa fa-lock"></i>شروط حسابك</h2>
            <span class="cp-badge cp-badge--muted">للاطّلاع فقط</span>
        </div>

        <div class="cp-card__body">
            <div class="cp-alert cp-alert--info" style="margin-bottom:1.25rem">
                <i class="fa fa-info-circle"></i>
                <div>هذه الشروط نضبطها نحن. لتعديلها راجع من يتابع حسابك.</div>
            </div>

            <div class="cp-ro">
                <div class="cp-ro__item">
                    <p class="cp-ro__label">الحدّ الائتماني</p>
                    <p class="cp-ro__value">
                        {{ $client->credit_limit ? CurrencySettings::format((float) $client->credit_limit) : 'بلا حدّ' }}
                    </p>
                </div>

                <div class="cp-ro__item">
                    <p class="cp-ro__label">المدّة الائتمانية</p>
                    <p class="cp-ro__value">{{ $client->credit_period ? $client->credit_period.' يوماً' : '—' }}</p>
                </div>

                <div class="cp-ro__item">
                    <p class="cp-ro__label">العملة</p>
                    <p class="cp-ro__value">{{ $client->currency ?: CurrencySettings::code() }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            var form = document.getElementById('cpProfileForm');
            var bar = document.getElementById('cpSaveBar');
            var reset = document.getElementById('cpResetBtn');

            if (!form || !bar) {
                return;
            }

            // القيم الابتدائية تُلتقط مرّةً: مقارنةُ `defaultValue` لا تكفي بعد
            // عودةٍ بـ`old()` — المتصفّح يعيد ملء الحقول فيبدو كلُّ شيء مُغيَّراً
            var initial = new Map();

            form.querySelectorAll('input, textarea, select').forEach(function (field) {
                if (field.name && field.type !== 'hidden') {
                    initial.set(field, field.value);
                }
            });

            function dirty() {
                var changed = false;

                initial.forEach(function (value, field) {
                    if (field.value !== value) { changed = true; }
                });

                bar.classList.toggle('is-on', changed);

                return changed;
            }

            form.addEventListener('input', dirty);
            form.addEventListener('change', dirty);

            if (reset) {
                reset.addEventListener('click', function () {
                    initial.forEach(function (value, field) { field.value = value; });
                    bar.classList.remove('is-on');
                });
            }

            // مغادرةٌ بتعديلٍ غير محفوظ: تحذيرُ المتصفّح أرخص من إعادة الكتابة
            window.addEventListener('beforeunload', function (event) {
                if (bar.classList.contains('is-on')) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });

            // الإرسال ليس مغادرةً غير مقصودة
            form.addEventListener('submit', function () {
                bar.classList.remove('is-on');
            });
        })();
    </script>
@endsection
