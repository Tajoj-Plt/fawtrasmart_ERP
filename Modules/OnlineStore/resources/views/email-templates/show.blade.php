@extends('onlinestore::master')

@section('title', 'قالب: ' . $emailTemplate->template_name)

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.email_templates.edit'))
                <a href="{{ route('email_templates.edit', $emailTemplate) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
            <a href="{{ route('email_templates.preview', $emailTemplate) }}" class="btn btn-outline-info" target="_blank">
                <i class="feather icon-monitor"></i> معاينة
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => $emailTemplate->template_name,
            'icon' => 'mail',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'قوالب البريد' => route('email_templates.index'),
                $emailTemplate->template_name => null,
            ],
        ])

        <div class="row">
            {{-- المعلومات الأساسية --}}
            <div class="col-md-8">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-info"></i> معلومات القالب</h6>
                        <span class="stk-state {{ $emailTemplate->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                            {{ $emailTemplate->is_active ? 'مفعل' : 'معطل' }}
                        </span>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>كود القالب</dt>
                                <dd><code>{{ $emailTemplate->template_code }}</code></dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>التصنيف</dt>
                                <dd><span class="badge badge-light-primary">{{ $emailTemplate->template_category?->label() ?? $emailTemplate->template_category }}</span></dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>اللغة</dt>
                                <dd>{{ $emailTemplate->language == 'ar' ? 'العربية' : 'English' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ الإنشاء</dt>
                                <dd>{{ $emailTemplate->created_at->format('Y-m-d H:i') }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>موضوع البريد</dt>
                                <dd>{{ $emailTemplate->subject }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>المتغيرات المستخدمة</dt>
                                <dd>
                                    @if ($emailTemplate->variables)
                                        @php
                                            $vars = is_array($emailTemplate->variables) ? $emailTemplate->variables : json_decode($emailTemplate->variables, true);
                                        @endphp
                                        @foreach ($vars ?? [] as $var)
                                            <code class="mr-1 mb-1 d-inline-block">@{{ '{{ ' . $var . ' }}' }}</code>
                                        @endforeach
                                    @else
                                        <span class="text-muted">لا توجد متغيرات</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- معاينة المحتوى --}}
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-code"></i> محتوى البريد HTML</h6>
                    </div>
                    <div class="card-body p-0">
                        <iframe srcdoc="{{ $emailTemplate->html_content }}"
                                style="width: 100%; height: 500px; border: none;"></iframe>
                    </div>
                </div>

                @if ($emailTemplate->text_content)
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> المحتوى النصي</h6>
                        </div>
                        <div class="card-body">
                            <pre class="bg-light p-3 rounded">{{ $emailTemplate->text_content }}</pre>
                        </div>
                    </div>
                @endif
            </div>

            {{-- الإحصائيات والإجراءات --}}
            <div class="col-md-4">
                {{-- الإحصائيات --}}
                <div class="stk-metrics mb-3">
                    <div class="stk-metric">
                        <span class="stk-metric__icon"><i class="feather icon-check-circle"></i></span>
                        <span class="stk-metric__label">تم الإرسال</span>
                        <span class="stk-metric__value">{{ $stats['total_sent'] }}</span>
                    </div>
                    <div class="stk-metric">
                        <span class="stk-metric__icon"><i class="feather icon-x-circle"></i></span>
                        <span class="stk-metric__label">فشل</span>
                        <span class="stk-metric__value">{{ $stats['total_failed'] }}</span>
                    </div>
                </div>

                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-clock"></i> آخر إرسال</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-0">
                            {{ $stats['last_sent'] ? $stats['last_sent']->diffForHumans() : 'لم يُرسل بعد' }}
                        </p>
                    </div>
                </div>

                {{-- إرسال تجريبي --}}
                @if (hasPermission('online_store.email_templates.send_test'))
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-send"></i> إرسال تجريبي</h6>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('email_templates.send-test', $emailTemplate) }}" method="POST">
                                @csrf
                                <div class="form-group">
                                    <input type="email" name="test_email" class="form-control"
                                           placeholder="أدخل بريدك الإلكتروني" required>
                                </div>
                                <button type="submit" class="btn btn-outline-primary btn-block">
                                    <i class="feather icon-send"></i> إرسال نسخة تجريبية
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- آخر السجلات --}}
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-list"></i> آخر عمليات الإرسال</h6>
                    </div>
                    <div class="card-body p-0">
                        @if ($recentLogs->count())
                            <div class="list-group list-group-flush">
                                @foreach ($recentLogs as $log)
                                    @if (hasPermission('online_store.email_logs.view'))
                                        <a href="{{ route('email_logs.show', $log) }}" class="list-group-item list-group-item-action">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">{{ $log->recipient_email }}</small>
                                                <span class="stk-state {{ $log->status->value == 'sent' ? 'stk-state--on' : 'stk-state--off' }}">
                                                    {{ $log->status->label() }}
                                                </span>
                                            </div>
                                            <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                                        </a>
                                    @else
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">{{ $log->recipient_email }}</small>
                                                <span class="stk-state {{ $log->status->value == 'sent' ? 'stk-state--on' : 'stk-state--off' }}">
                                                    {{ $log->status->label() }}
                                                </span>
                                            </div>
                                            <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="stk-empty">
                                <p class="stk-empty__hint mb-0">لا توجد سجلات بعد</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- الإجراءات --}}
                @if (hasPermission('online_store.email_templates.delete'))
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-tool"></i> إجراءات</h6>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('email_templates.destroy', $emailTemplate) }}" method="POST"
                                  onsubmit="return confirm('هل أنت متأكد من حذف هذا القالب؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-block">
                                    <i class="feather icon-trash-2"></i> حذف القالب
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
