@extends('client::master')

@section('title')
    خط السير — الزيارات الجارية
@stop

@section('content')
    {{--
        ══════════════════════════════════════════════════════════════════
        شاشة الزيارات الجارية والمنسية — على نظام تصميم العملاء
        ══════════════════════════════════════════════════════════════════

        كانت الشاشة تكتب مظهرها بيدها: مجموعةُ متغيّرات `--itin-*` خاصّة
        بها، وترويسةٌ وتبويباتٌ وبطاقاتٌ إحصائية وحالةٌ فارغة مرسومة سطراً
        سطراً في هذا الملف — أي نظامُ تصميمٍ ثانٍ داخل وحدة العملاء يعيش
        إلى جانب `cl-` ولا يتبعه. فتغييرُ لونٍ أو استدارةٍ في «مظهر النظام»
        كان يسري على كلّ شاشات الوحدة إلّا هذه.

        الآن: الترويسةُ والإحصائياتُ والتبويباتُ والشاراتُ والأزرارُ والحالةُ
        الفارغة كلُّها من `client::partials.settings-theme` (البادئة `cl-`)،
        ولم يبقَ في هذا الملف من أنماطٍ إلّا شبكةُ بطاقات الزيارة — مكوِّنٌ
        لا نظير له في نظام التصميم — وهي مكتوبةٌ بوسوم `--cl-*`/`--app-*`
        نفسِها لا بألوانٍ مثبَّتة.

        التخطيط كما هو عمداً (بطاقةٌ لكلّ زيارة في شبكة): المطلوب توحيدُ
        الإطار لا إعادةُ بناء الشاشة. راجع الملاحظة نفسَها في شاشة
        «الإقفال اليومي» بوحدة المبيعات.
    --}}

    @include('client::partials.settings-theme')

    <div class="cl-scope">
        <style>
            /* ═══════════════ شبكةُ بطاقات الزيارة ═══════════════

               الوحيدُ الذي لا مقابل له في `cl-`: بطاقةٌ لكلّ زيارةٍ مفتوحة،
               بشريطٍ علويٍّ يقول بلمحةٍ أمنسيّةٌ هي أم جارية. كلُّ قيمةٍ هنا
               رمزٌ من نظام التصميم — لا لون ولا استدارة مثبَّتة. */

            .vd-grid {
                display: grid;
                gap: var(--cl-4);
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            }

            .vd-card {
                display: flex;
                flex-direction: column;
                background: var(--app-surface, #fff);
                border: 1px solid var(--cl-line);
                border-radius: var(--cl-r-lg);
                box-shadow: var(--cl-e2);
                overflow: hidden;
                transition: box-shadow .16s ease, transform .16s ease;
            }

            .vd-card:hover { box-shadow: var(--cl-e3); transform: translateY(-2px); }

            /* الشريط العلوي: اللون وحده لا يكفي، فالشارة في الرأس تقول
               الحالة بالنصّ كذلك. */
            .vd-card::before {
                content: '';
                display: block;
                height: 3px;
                background: var(--app-primary, #7367f0);
            }
            .vd-card.is-forgotten::before { background: var(--app-danger, #ea5455); }
            .vd-card.is-ongoing::before   { background: var(--app-success, #28c76f); }

            .vd-card__head {
                display: flex; align-items: flex-start; justify-content: space-between;
                gap: var(--cl-3);
                padding: var(--cl-4);
                border-bottom: 1px solid var(--cl-line-soft);
            }

            .vd-card__body { padding: var(--cl-4); flex: 1 1 auto; }

            /* سطرُ وقت: أيقونة ونصّ على أرضيةٍ خفيفة */
            .vd-time {
                display: flex; align-items: center; gap: var(--cl-2);
                padding: var(--cl-2) var(--cl-3);
                border-radius: var(--cl-r);
                background: var(--cl-tint);
                border: 1px solid var(--cl-line-soft);
                font-size: .82rem; color: var(--app-text, #4b4b4b);
                margin-bottom: var(--cl-2);
            }
            .vd-time:last-child { margin-bottom: 0; }
            .vd-time i { color: var(--app-primary, #7367f0); flex: none; }
            .vd-time strong { font-variant-numeric: tabular-nums; }

            /* حقائقُ الزيارة: عنوانٌ صغير فوق قيمته، اثنتان في السطر */
            .vd-facts {
                display: grid; grid-template-columns: 1fr 1fr;
                gap: var(--cl-3);
                margin: var(--cl-4) 0 0;
            }
            .vd-fact { min-width: 0; }
            .vd-fact dt {
                font-size: .72rem; font-weight: 700; letter-spacing: .01em;
                color: var(--app-muted, #6e6b7b);
                margin: 0 0 .15rem; text-transform: none;
            }
            .vd-fact dd {
                margin: 0; font-size: .84rem; font-weight: 600;
                color: var(--app-text, #4b4b4b);
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            }

            /* العرض والاتجاه مثبَّتان صراحةً: كان تذييل البطاقة وسمَ `<footer>`
               فالتقطته أنماطُ تذييل الصفحة العامّة في بعض المظاهر، فضاق إلى
               عمودٍ نحيل تتكدّس فيه الأزرار وتُقصّ. */
            .vd-card__foot {
                display: flex; flex-direction: row; flex-wrap: wrap; gap: var(--cl-2);
                align-self: stretch; width: 100%; box-sizing: border-box;
                margin: 0; position: static;
                padding: var(--cl-3) var(--cl-4);
                border-top: 1px solid var(--cl-line-soft);
                background: var(--cl-tint);
            }
            .vd-card__foot .btn { flex: 1 1 0; min-width: 96px; white-space: nowrap; }

            /* نوافذُ التأكيد على أرضية الوحدة نفسِها */
            .cl-scope .modal-content {
                border: 1px solid var(--cl-line);
                border-radius: var(--cl-r-lg);
                box-shadow: var(--cl-e4);
            }
            .cl-scope .modal-header, .cl-scope .modal-footer {
                border-color: var(--cl-line-soft);
                padding: var(--cl-4) var(--cl-5);
            }
            .cl-scope .modal-header { background: var(--cl-tint); }
            .cl-scope .modal-title { font-size: .95rem; font-weight: 700; color: var(--app-text, #4b4b4b); }
            .cl-scope .modal-body { padding: var(--cl-5); }
        </style>

        {{-- ═══════════════ ترويسة الصفحة ═══════════════ --}}
        <div class="cl-page-head">
            <div class="cl-page-head__titles">
                <span class="cl-page-head__icon" aria-hidden="true"><i class="fas fa-route"></i></span>
                <div style="min-width:0">
                    <h2 class="cl-page-head__title">خط السير</h2>
                    <p class="cl-page-head__sub">متابعةُ الزيارات الجارية وإنهاؤها</p>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('clients.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">إدارة العملاء</a></li>
                        <li class="breadcrumb-item active" aria-current="page">خط السير</li>
                    </ol>
                </div>
            </div>

            @if ($canViewAll)
                {{-- نطاقُ البيانات رابطان لا زرّان: لكلٍّ عنوانُه
                     (`?scope=all`) فيُحفَظ في المفضّلة ويُفتح في تبويب. --}}
                <div class="cl-page-head__actions">
                    <div class="btn-group btn-group-sm" role="group" aria-label="نطاق العرض">
                        <a href="?scope=own" class="btn btn-outline-primary {{ $scope === 'own' ? 'active' : '' }}">
                            <i class="fas fa-user"></i> زياراتي
                        </a>
                        <a href="?scope=all" class="btn btn-outline-primary {{ $scope === 'all' ? 'active' : '' }}">
                            <i class="fas fa-users"></i> كل الموظفين
                        </a>
                    </div>
                </div>
            @endif
        </div>

        @include('client::components.guide', [
            'key' => 'clients.itinerary-dashboard',
            'open' => false,
            'title' => 'ما هذه الشاشة؟',
            'lead' => 'كلُّ زيارةٍ فُتحت مساحةُ عملها ولم تُغلق بعد، والمندوب لا يزال ضمن مهلتها، تظهر هنا.',
            'blocks' => [
                'الإنهاء والإلغاء' => '«إنهاء» يُغلق الزيارة بنتيجةٍ وملاحظة فتُحتسب زيارةً تمّت. «إلغاء» يُلغيها بسببٍ مكتوب فلا تُحتسب. زيارتُك تحتاج صلاحيةَ إنهاء زياراتك، وزيارةُ غيرك تحتاج صلاحيةَ الإنهاء القسري.',
                'نطاق العرض' => 'مَن يملك رؤيةَ زيارات الجميع تفتح له الشاشةُ على «كل الموظفين»، ويستطيع حصرَها في زياراته هو. ومَن لا يملكها يرى زياراته وحدها.',
            ],
        ])

        @php
            /* المدّة تأتي محسوبةً من المتحكّم (`$elapsedSeconds`) — راجع
               `Visit::getElapsedSecondsAttribute()`: حسابُها داخل القالب
               يُخرج رقماً يتبع المنطقة الزمنية وقتَ التصيير. وهذه صياغتُها
               فقط: دقائقُ تحت الساعة، ثمّ ساعات، ثمّ أيام. */
            $visitAgeLabel = function (?int $seconds) {
                if ($seconds === null) {
                    return 'لم تبدأ بعد';
                }

                if ($seconds < 3600) {
                    return 'منذ ' . max(1, intdiv($seconds, 60)) . ' دقيقة';
                }

                if ($seconds < 86400) {
                    $hours = intdiv($seconds, 3600);
                    $minutes = intdiv($seconds % 3600, 60);

                    return 'منذ ' . $hours . ' ساعة' . ($minutes ? ' و' . $minutes . ' دقيقة' : '');
                }

                return 'منذ ' . intdiv($seconds, 86400) . ' يوم';
            };
        @endphp

        {{-- ═══════════════ بطاقات الزيارات ═══════════════ --}}
        <div class="card">
            <div class="cl-table-head">
                <h3 class="cl-table-head__title">
                    <i class="fas fa-clipboard-list"></i>
                    الزيارات الجارية
                    <span class="cl-count">{{ $stats['ongoing'] ?? 0 }}</span>
                </h3>
            </div>

            <div class="card-body">
                @if ($ongoingVisits && $ongoingVisits->count() > 0)
                    <div class="vd-grid" id="visits-grid">
                        @foreach ($ongoingVisits as $visit)
                            @php
                                $isForgotten = false;
                                $isOwnVisit = (int) $visit->employee_id === (int) $currentUserId;

                                /* عينُ شرطَي `closeForgottenVisit()` و`cancelForgottenVisit()`:
                                   زيارتي تحتاج «_own»، وزيارةُ غيري تحتاج «force». */
                                $canCloseThis = $isOwnVisit ? $canCloseOwn : $canForceClose;
                                $canCancelThis = $canCancelVisit && ($isOwnVisit || $canCancelOthers);

                                $elapsed = $elapsedSeconds[$visit->id] ?? 0;
                                $startedAgoText = $visitAgeLabel($visit->started_at ? $elapsed : null);
                            @endphp

                            <article class="vd-card {{ $isForgotten ? 'is-forgotten' : 'is-ongoing' }}"
                                     data-category="{{ $isForgotten ? 'forgotten' : 'ongoing' }}">

                                <div class="vd-card__head">
                                    <div class="cl-media">
                                        <span class="cl-media__thumb"><i class="fas fa-store"></i></span>
                                        <div class="cl-media__body">
                                            <span class="cl-media__title">{{ $visit->client->trade_name ?? 'عميل غير معروف' }}</span>
                                            <span class="cl-media__sub">
                                                <i class="fas fa-user-tie"></i>
                                                {{ $visit->employee->name ?? 'موظف غير معروف' }}
                                            </span>
                                        </div>
                                    </div>

                                    <span class="cl-state {{ $isForgotten ? 'cl-state--danger' : 'cl-state--on' }}">
                                        {{ $isForgotten ? 'منسية' : 'جارية' }}
                                    </span>
                                </div>

                                <div class="vd-card__body">
                                    <div class="vd-time">
                                        <i class="fas fa-clock"></i>
                                        <span>
                                            بدأت: <strong>{{ $visit->started_at ? $visit->started_at->format('H:i') : '--' }}</strong>
                                            ({{ $startedAgoText }})
                                        </span>
                                    </div>

                                    @if ($visit->departure_time)
                                        <div class="vd-time">
                                            <i class="fas fa-sign-out-alt"></i>
                                            <span>انتهت: <strong>{{ $visit->departure_time->format('H:i') }}</strong></span>
                                        </div>
                                    @endif

                                    <dl class="vd-facts">
                                        <div class="vd-fact">
                                            <dt>المرحلة</dt>
                                            <dd>
                                                @switch($visit->stage)
                                                    @case('arrived') وصل @break
                                                    @case('in_progress') جاري @break
                                                    @case('paused') متوقف @break
                                                    @case('completed') مكتمل @break
                                                    @case('cancelled') ملغى @break
                                                    @default {{ $visit->stage }}
                                                @endswitch
                                            </dd>
                                        </div>

                                        <div class="vd-fact">
                                            <dt>التاريخ</dt>
                                            <dd class="cl-num">{{ $visit->visit_date->format('d/m/Y') }}</dd>
                                        </div>

                                        @if ($visit->outcome)
                                            <div class="vd-fact">
                                                <dt>النتيجة</dt>
                                                <dd>
                                                    @switch($visit->outcome)
                                                        @case('successful') ناجحة @break
                                                        @case('failed') فاشلة @break
                                                        @case('partial') جزئية @break
                                                        @default {{ $visit->outcome }}
                                                    @endswitch
                                                </dd>
                                            </div>
                                        @endif

                                        @if ($visit->outcome_notes)
                                            <div class="vd-fact">
                                                <dt>ملاحظات</dt>
                                                <dd title="{{ $visit->outcome_notes }}">{{ Str::limit($visit->outcome_notes, 30) }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                </div>

                                <div class="vd-card__foot">
                                    {{-- الجارية تُنهى من هنا كما تُنهى المنسية: زيارةٌ
                                         مفتوحةٌ هي مفتوحة، سواءٌ بدأت قبل ساعةٍ أو أمس.
                                         الحقُّ لكلِّ بطاقةٍ بحسب صاحبها لا للشاشة كلّها. --}}
                                    @if ($canCloseThis)
                                        <button type="button"
                                                class="btn btn-sm btn-primary"
                                                onclick="closeVisitModal({{ $visit->id }}, '{{ $visit->client->trade_name ?? 'عميل' }}')"
                                                title="{{ $isForgotten ? 'إنهاء هذه الزيارة المنسية' : 'إنهاء هذه الزيارة الجارية' }}">
                                            <i class="fas fa-check"></i> إنهاء
                                        </button>
                                    @endif

                                    @if ($canCancelThis)
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="cancelVisitModal({{ $visit->id }}, '{{ $visit->client->trade_name ?? 'عميل' }}')"
                                                title="إلغاء هذه الزيارة">
                                            <i class="fas fa-times"></i> إلغاء
                                        </button>
                                    @endif

                                    <a href="{{ route('clients.show', $visit->client_id) }}"
                                       class="btn btn-sm btn-outline-secondary"
                                       title="عرض تفاصيل العميل">
                                        <i class="fas fa-external-link-alt"></i> العميل
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="cl-empty">
                        <div class="cl-empty__icon"><i class="fas fa-check-circle"></i></div>
                        <div class="cl-empty__title">لا توجد زيارات جارية</div>
                        <p class="cl-empty__hint">كلُّ الزيارات مُغلقة — لا شيء ينتظر الإنهاء.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ═══════════════ نافذة إنهاء الزيارة ═══════════════ --}}
        <div class="modal fade" id="closeVisitModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-check-circle"></i> إنهاء الزيارة</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <form id="closeVisitForm" onsubmit="submitCloseVisit(event)">
                        <div class="modal-body">
                            <p>هل تريد إنهاء الزيارة عند <strong id="closeVisitClient"></strong>؟</p>

                            <div class="mb-3">
                                <label class="form-label">النتيجة</label>
                                <select name="outcome" class="form-control">
                                    <option value="">-- بدون نتيجة --</option>
                                    <option value="successful">ناجحة</option>
                                    <option value="failed">فاشلة</option>
                                    <option value="partial">جزئية</option>
                                </select>
                                <span class="cl-help">تُحتسب في تقارير نتائج الزيارات. اتركها فارغة وتُسجَّل «ناجحة».</span>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">ملاحظات</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="أضف ملاحظة..."></textarea>
                                <span class="cl-help">اختيارية — تُحفظ مع الزيارة وتظهر في ملفّ العميل.</span>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">تراجع</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> إنهاء الزيارة</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ═══════════════ نافذة إلغاء الزيارة ═══════════════ --}}
        <div class="modal fade" id="cancelVisitModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-times-circle"></i> إلغاء الزيارة</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <form id="cancelVisitForm" onsubmit="submitCancelVisit(event)">
                        <div class="modal-body">
                            <p>هل تريد إلغاء الزيارة عند <strong id="cancelVisitClient"></strong>؟</p>

                            <div class="mb-3">
                                <label class="form-label cl-req">السبب</label>
                                <textarea name="cancel_reason" class="form-control" rows="4" placeholder="أدخل سبب الإلغاء..." required></textarea>
                                <span class="cl-help">يُحفظ في سجلّ النشاط — الزيارةُ الملغاة لا تُحتسب زيارةً تمّت.</span>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">تراجع</button>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> إلغاء الزيارة</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentVisitId = null;

        /* المساران يُبنيان بـ`route()`: كانا مكتوبَين بخطٍّ جذريٍّ يدوي
           (`/forgotten-visits/…`) والمسار الحقيقي تحت بادئة الوحدة
           (`clients/clients_management/…`) — فكلّ ضغطةٍ كانت 404، ثمّ
           `response.json()` تتعثّر في صفحة الخطأ فتُظهر «حدث خطأ». */
        const CLOSE_VISIT_URL = @json(route('forgotten-visits.close', ['visit' => '__id__']));
        const CANCEL_VISIT_URL = @json(route('forgotten-visits.cancel', ['visit' => '__id__']));

        /* الرسائل بـ`toastr` — وهو محمَّلٌ في `client::master` ومستعمَلٌ في
           بقيّة شاشات الوحدة — لا بـ`alert()` الذي يوقف الصفحة بنافذة
           المتصفّح على غير مظهر النظام. و`alert` يبقى بديلاً إن غاب. */
        function vdNotify(type, message) {
            if (window.toastr && typeof window.toastr[type] === 'function') {
                window.toastr[type](message);
                return;
            }

            alert(message);
        }

        // ═══════════ إنهاء الزيارة ═══════════
        function closeVisitModal(visitId, clientName) {
            currentVisitId = visitId;
            document.getElementById('closeVisitClient').textContent = clientName;
            const modal = new bootstrap.Modal(document.getElementById('closeVisitModal'));
            modal.show();
        }

        function submitCloseVisit(event) {
            event.preventDefault();

            const outcome = document.querySelector('select[name="outcome"]').value || 'successful';
            const notes = document.querySelector('textarea[name="notes"]').value;

            fetch(CLOSE_VISIT_URL.replace('__id__', currentVisitId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({ outcome, notes })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    vdNotify('success', 'تم إنهاء الزيارة بنجاح');
                    location.reload();
                } else {
                    vdNotify('error', 'خطأ: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                vdNotify('error', 'حدث خطأ: ' + error.message);
            });
        }

        // ═══════════ إلغاء الزيارة ═══════════
        function cancelVisitModal(visitId, clientName) {
            currentVisitId = visitId;
            document.getElementById('cancelVisitClient').textContent = clientName;
            const modal = new bootstrap.Modal(document.getElementById('cancelVisitModal'));
            modal.show();
        }

        function submitCancelVisit(event) {
            event.preventDefault();

            const reason = document.querySelector('textarea[name="cancel_reason"]').value;

            if (!reason.trim()) {
                vdNotify('warning', 'يجب إدخال سبب الإلغاء');
                return;
            }

            fetch(CANCEL_VISIT_URL.replace('__id__', currentVisitId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({ cancel_reason: reason })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    vdNotify('success', 'تم إلغاء الزيارة بنجاح');
                    location.reload();
                } else {
                    vdNotify('error', 'خطأ: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                vdNotify('error', 'حدث خطأ: ' + error.message);
            });
        }
    </script>
@stop
