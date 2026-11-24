{{--
    أهداف العملاء — أُعيد بناؤها بنفس نظام تصميم الوحدة الفعلي
    (`.cl-scope` + `partials/settings-theme` + `components/page-head`/`guide`)
    بدل CSS مكتوبةٍ يدوياً بألوانٍ ثابتة داخل `@section('css')`.

    تلك الشاشة كانت لا تُظهر أيّ تصميمٍ مخصَّص أصلاً: قالب `client::master`
    لا يملك `@yield('css')`، بل `@stack('styles')` فقط — فقسم الـ CSS
    بأكمله كان يُطوى بصمتٍ ولا يصل الصفحة قطّ. المستخدم يرى Bootstrap
    افتراضياً غير مصمَّم، لا الشكل المقصود.

    أُضيف أيضاً: أزرار الإضافة/التعديل/الحذف/إعادة التقييم لم تكن محميّةً
    بـ`@permission` رغم أنّ المسارات نفسها محميّة — فمستخدمٌ يملك صلاحية
    العرض فقط («clients_view_all_reports»/«clients_view_own_reports») كان
    يرى أزرار كتابةٍ تُخفق فور الضغط عليها بدل أن تُخفى من الأصل.
--}}
@extends('client::master')

@section('title')
    أهداف العملاء
@stop

@section('content')
    @include('layouts.alerts.success')
    @include('layouts.alerts.error')
    @include('client::partials.settings-theme')

    <div class="cl-scope">
        @section('page-actions')
            @permission('clients_edit_client_settings')
                <a href="{{ route('client.customer_targets.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    <span>إضافة هدف جديد</span>
                </a>
            @endpermission
        @endsection

        @include('client::components.page-head', [
            'title' => 'أهداف العملاء',
            'icon' => 'target',
            'sub' => 'شرائح تصنيف العملاء حسب قوّة السداد والتحصيل — تُستعمل في الفرز والتقارير ولوحة التحكّم',
            'crumbs' => [
                'الرئيسية' => route('dashboard'),
                'العملاء' => route('clients.index'),
                'أهداف العملاء' => null,
            ],
        ])

        @include('client::components.guide', [
            'key' => 'clients.customer_targets.index',
            'open' => false,
            'title' => 'ما هذه الشاشة؟',
            'lead' => 'كلّ هدفٍ هنا شريحة تصنيف (ذهبي، فضّي، ...) يُمنحها العميل تلقائياً حين يستوفي شروطها. النظام يحسب لكلّ عميل «معدّل التحصيل الشهري»: إجمالي ما سدّده مقسوماً على عدد أشهر العلاقة معه، فيُميّز العميل النشط المستمرّ عن الخامل الذي توقّف عن الدفع.',
            'blocks' => [
                'ترتيب فحص الأهداف (الأولوية)' => [
                    'يُفحص العميل من الهدف الأعلى أولويةً (الرقم الأكبر) إلى الأدنى، وأوّل هدفٍ يستوفي شروطه يُمنَح له فوراً.',
                    'مثال: (ذهبي = أولوية 100) و(فضّي = أولوية 80) — العميل المستحقّ لكليهما يحصل على «ذهبي» فقط.',
                ],
                'دليل أيام سداد الفواتير' => [
                    '30 يوماً: يسدّد بنفس الشهر — عميل ذهبيّ ممتاز.',
                    '60 يوماً: يسدّد خلال شهرين — عميل جيّد جداً.',
                    '90 يوماً: يسدّد على 3 أشهر — عميل عادي/متوسّط.',
                    '120 يوماً فأكثر: يدفع شهراً ويتوقّف شهراً — عميل متقطّع بحاجة متابعة.',
                ],
            ],
            'facts' => [
                '«إعادة تقييم العملاء» تعيد تصنيف كلّ عملاء المنشأة دفعةً واحدة.',
                'حذف هدفٍ لا يمسّ العملاء المصنَّفين به، فقط يزيل تصنيفهم.',
            ],
        ])

        @permission('clients_edit_client_settings')
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><i class="fas fa-sync"></i> إعادة تقييم العملاء</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('client.customer_targets.evaluateAll') }}" method="POST" id="evalForm">
                    @csrf
                    <div class="row align-items-end g-3">
                        <div class="col-md-3">
                            <label>طريقة التقييم</label>
                            <select name="evaluation_type" id="evaluation_type" class="form-control" onchange="toggleEvalInputs()">
                                <option value="date">بواسطة تاريخ (من / إلى)</option>
                                <option value="days">بواسطة عدد أيام (من أول حركة للعميل)</option>
                            </select>
                        </div>

                        <div class="col-md-3 col-sm-6 eval-date-inputs">
                            <label>من تاريخ</label>
                            <input type="date" name="date_from" class="form-control" value="{{ now()->subDays(30)->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-3 col-sm-6 eval-date-inputs">
                            <label>إلى تاريخ</label>
                            <input type="date" name="date_to" class="form-control" value="{{ now()->format('Y-m-d') }}">
                        </div>

                        <div class="col-md-6 eval-days-inputs" style="display: none;">
                            <label>عدد الأيام</label>
                            <input type="number" name="days" class="form-control" placeholder="مثال: 90 يوم من أول حركة للعميل" value="90">
                        </div>

                        <div class="col-md-3">
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="fas fa-sync"></i> إعادة تقييم العملاء
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endpermission

        <div class="card cl-table-card">
            <div class="cl-table-head">
                <h6 class="cl-table-head__title">
                    <i class="fas fa-list"></i>
                    الأهداف
                    <span class="cl-count">{{ $targets->count() }}</span>
                </h6>
            </div>

            <div class="card-body position-relative">
                @if ($targets->isEmpty())
                    <div class="cl-empty">
                        <span class="cl-empty__icon"><i class="fas fa-bullseye"></i></span>
                        <p class="cl-empty__title">لا توجد أهداف مضافة بعد</p>
                        <p class="cl-empty__hint">ابدأ بإضافة أوّل هدفٍ لتصنيف عملائك.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>الأولوية</th>
                                    <th>اللون</th>
                                    <th>اسم الهدف</th>
                                    <th>شروط الهدف</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($targets as $target)
                                <tr>
                                    <td class="cl-num">{{ $target->priority }}</td>
                                    <td>
                                        <span class="cl-state cl-state--neutral" style="background: color-mix(in srgb, {{ $target->color ?? '#7367f0' }} 16%, transparent); color: {{ $target->color ?? '#7367f0' }};">
                                            {{ $target->color ?? 'بدون' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="cl-media">
                                            <span class="cl-media__thumb" style="color: {{ $target->color ?? null }};"><i class="fas fa-bullseye"></i></span>
                                            <div class="cl-media__body">
                                                <span class="cl-media__title">{{ $target->name }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <ul class="cl-tags" style="list-style: none; margin: 0; padding: 0; flex-direction: column; align-items: flex-start; gap: .25rem;">
                                            @if($target->min_sales > 0)
                                                <li style="font-size: .8rem; color: var(--app-muted, #6e6b7b);"><i class="fas fa-shopping-cart me-1"></i> الحد الأدنى للمبيعات: <strong style="color: var(--app-text, #4b4b4b);">{{ number_format($target->min_sales) }}</strong> ريال</li>
                                            @endif
                                            @if($target->min_collections > 0)
                                                <li style="font-size: .8rem; color: var(--app-muted, #6e6b7b);"><i class="fas fa-receipt me-1"></i> الحد الأدنى للتحصيلات: <strong style="color: var(--app-text, #4b4b4b);">{{ number_format($target->min_collections) }}</strong> ريال</li>
                                            @endif
                                            @if($target->max_avg_collection_days > 0 && $target->max_avg_collection_days != 999)
                                                <li style="font-size: .8rem; color: var(--app-muted, #6e6b7b);"><i class="fas fa-calendar-day me-1"></i> الحد الأقصى لأيام التحصيل: <strong style="color: var(--app-text, #4b4b4b);">{{ $target->max_avg_collection_days }}</strong> يوم</li>
                                            @endif
                                            @if($target->min_sales == 0 && $target->min_collections == 0 && ($target->max_avg_collection_days == 0 || $target->max_avg_collection_days == 999))
                                                <li class="text-muted" style="font-size: .8rem;">يقبل أي قيمة (افتراضي)</li>
                                            @endif
                                        </ul>
                                    </td>
                                    <td>
                                        @if($target->is_active)
                                            <span class="cl-state cl-state--on">نشط</span>
                                        @else
                                            <span class="cl-state cl-state--off">غير نشط</span>
                                        @endif
                                    </td>
                                    <td>
                                        @permission('clients_edit_client_settings')
                                        <div class="cl-actions">
                                            <a href="{{ route('client.customer_targets.edit', $target->id) }}" class="btn btn-outline-primary btn-sm" title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('client.customer_targets.destroy', $target->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا الهدف؟')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="حذف">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                        @endpermission
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        function toggleEvalInputs() {
            var type = document.getElementById('evaluation_type').value;
            if (type === 'days') {
                document.querySelectorAll('.eval-date-inputs').forEach(el => el.style.display = 'none');
                document.querySelector('.eval-days-inputs').style.display = 'block';
            } else {
                document.querySelectorAll('.eval-date-inputs').forEach(el => el.style.display = 'block');
                document.querySelector('.eval-days-inputs').style.display = 'none';
            }
        }
    </script>
@endsection
