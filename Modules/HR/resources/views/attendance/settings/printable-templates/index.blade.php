@extends('hr::master')

@section('title', 'قوالب الطباعة')

@section('content')
    @include('hr::components.table-menu')
    @include('hr::attendance._partials.styles')


        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('hr::components.guide', [
            'key' => 'hr-attendance-templates.index',
            'title' => 'ما هذه الشاشة، وما الذي يترتّب على ما فيها؟',
            'lead' => 'هذه قوالبُ طباعة مستندات الحضور: شكلُ الورقة التي تخرج للطباعة — ترويستُها وحقولُها وترتيبُها.',
            'blocks' => [
                'ما يترتّب على القالب' => [
                    'القالبُ يغيّر شكلَ المطبوع وحدَه؛ لا يمسّ بياناتِ الحضور ولا حساباتِها.',
                    'وتغييرُه يسري على ما يُطبَع بعده، والمطبوعُ سلفاً ورقٌ خرج بشكله القديم.',
                ],
            ],
            'facts' => [
                'القالبُ يغيّر شكلَ المطبوع لا بياناتِه',
            ],
        ])


    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="stk-page-head">
        <div class="stk-page-head__titles">
            <span class="stk-page-head__icon" aria-hidden="true"><i class="feather icon-printer"></i></span>
            <div class="min-width-0">
                <h2 class="stk-page-head__title">قوالب الطباعة</h2>
                <div class="stk-page-head__sub">تنسيقات طباعة عقود العمل وقسائم الرواتب</div>
            </div>
        </div>
        <div class="stk-page-head__actions">
                <a href="{{ route('attendance.settings.printable-templates.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    <span class="d-none d-md-inline">قالب جديد</span>
                </a>
        </div>
    </div>

    @if(! $canManageTemplates)
        <div class="alert alert-info">
            <i class="feather icon-info me-2"></i>
            يمكنك إنشاء القوالب وحذفها من هنا. أمّا <strong>تصميمها</strong> فيفتح المحرِّر
            المشترك في شاشة الإعدادات العامة، ويحتاج صلاحية <code>settings_edit_general_settings</code>.
        </div>
    @endif

    @foreach($documentTypes as $type => $label)
        @php($group = $templates->where('document_type', $type))
        <div class="att-card">
            <div class="att-card-head">
                <span><i class="feather icon-file-text"></i> {{ $label }}</span>
                <span class="stk-count">{{ $group->count() }}&nbsp;قالب</span>
            </div>
            <div class="att-card-body p-0">
                @if($group->count() > 0)
                    <div class="table-responsive">
                        <table class="att-table">
                            <thead>
                                <tr>
                                    <th>اسم القالب</th>
                                    <th>مقاس الورق</th>
                                    <th>الاتجاه</th>
                                    <th>الحالة</th>
                                    <th class="text-center" style="width:150px;">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group as $template)
                                    <tr>
                                        <td>
                                            <strong>{{ $template->name }}</strong>
                                            @if($template->is_default)
                                                <span class="att-pill info ms-1">الافتراضي</span>
                                            @endif
                                        </td>
                                        <td>{{ strtoupper($template->paper_size ?? '—') }}</td>
                                        <td>{{ $template->orientation === 'landscape' ? 'عرضي' : 'طولي' }}</td>
                                        <td>
                                            @if($template->is_active)
                                                <span class="att-pill success">مفعّل</span>
                                            @else
                                                <span class="att-pill secondary">موقوف</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                                    data-bs-toggle="dropdown" data-bs-display="static"
                                                    aria-expanded="false" aria-label="خيارات">
                                                    <i class="fa fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="{{ route('document-templates.preview', $template->id) }}" target="_blank">
                                                        <i class="fa fa-eye me-2 text-primary"></i>معاينة
                                                    </a>
                                                    @if($canManageTemplates)
                                                        <a class="dropdown-item" href="{{ route('document-templates.edit', $template->id) }}"
                                                            title="يفتح المحرِّر في الإعدادات العامة">
                                                            <i class="fa fa-edit me-2 text-success"></i>تصميم القالب
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="att-empty">
                        <i class="feather icon-printer"></i>
                        <h5 class="mb-1">لا يوجد قالب لـ«{{ $label }}» بعد</h5>
                        <p class="mb-0">تُستخدم صيغة الطباعة الافتراضية للنظام حتى يُضاف قالب.</p>
                        <div class="mt-3">
                            <a href="{{ route('attendance.settings.printable-templates.create') }}" class="btn btn-primary">
                                <i class="feather icon-plus me-2"></i>أنشئ قالباً
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@endsection
