@extends('onlinestore::master')

@section('title', 'سجل بريد #' . $emailLog->log_id)

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if ($emailLog->status->value != 'sent' && hasPermission('online_store.email_logs.resend'))
                <form action="{{ route('email_logs.resend', $emailLog) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-info">
                        <i class="feather icon-refresh-cw"></i> إعادة الإرسال
                    </button>
                </form>
            @endif
            <a href="{{ route('email_logs.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> العودة
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'سجل بريد #' . $emailLog->log_id,
            'icon' => 'mail',
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'سجلات البريد' => route('email_logs.index'),
                '#' . $emailLog->log_id => null,
            ],
        ])

        <div class="row">
            {{-- معلومات السجل --}}
            <div class="col-md-4">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-info"></i> معلومات السجل</h6>
                        <span class="stk-state {{ $emailLog->status->value == 'sent' ? 'stk-state--on' : ($emailLog->status->value == 'failed' ? 'stk-state--off' : 'stk-state--warn') }}">
                            {{ $emailLog->status->label() }}
                        </span>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>رقم السجل</dt>
                                <dd class="stk-num">#{{ $emailLog->log_id }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>القالب</dt>
                                <dd>
                                    @if ($emailLog->template)
                                        @if (hasPermission('online_store.email_templates.view'))
                                            <a href="{{ route('email_templates.show', $emailLog->template) }}">
                                                {{ $emailLog->template->template_name }}
                                            </a>
                                        @else
                                            {{ $emailLog->template->template_name }}
                                        @endif
                                        <br>
                                        <code class="small">{{ $emailLog->template->template_code }}</code>
                                    @else
                                        <span class="text-muted">قالب محذوف</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>المستلم</dt>
                                <dd>
                                    {{ $emailLog->recipient_email }}
                                    <br>
                                    <span class="badge badge-light-secondary">{{ $emailLog->recipient_type->label() }}</span>
                                </dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>تاريخ الإنشاء</dt>
                                <dd>{{ $emailLog->created_at->format('Y-m-d H:i:s') }}</dd>
                            </div>
                            @if ($emailLog->sent_at)
                                <div class="stk-def__row">
                                    <dt>تاريخ الإرسال</dt>
                                    <dd>{{ $emailLog->sent_at->format('Y-m-d H:i:s') }}</dd>
                                </div>
                            @endif
                        </dl>

                        @if ($emailLog->error_message)
                            <div class="mt-3">
                                <strong>رسالة الخطأ:</strong>
                                <div class="alert alert-danger mt-1">
                                    <small>{{ $emailLog->error_message }}</small>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- الإجراءات --}}
                @if (hasPermission(['online_store.email_logs.resend', 'online_store.email_logs.delete']))
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-tool"></i> الإجراءات</h6>
                        </div>
                        <div class="card-body">
                            @if ($emailLog->status->value != 'sent' && hasPermission('online_store.email_logs.resend'))
                                <form action="{{ route('email_logs.resend', $emailLog) }}" method="POST" class="mb-2">
                                    @csrf
                                    <button type="submit" class="btn btn-info btn-block">
                                        <i class="feather icon-refresh-cw"></i> إعادة الإرسال
                                    </button>
                                </form>
                            @endif

                            @if (hasPermission('online_store.email_logs.delete'))
                                <form action="{{ route('email_logs.destroy', $emailLog) }}" method="POST"
                                      onsubmit="return confirm('هل أنت متأكد من حذف هذا السجل؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-block">
                                        <i class="feather icon-trash-2"></i> حذف السجل
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- البيانات الوصفية --}}
                @if ($emailLog->metadata)
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-code"></i> بيانات إضافية</h6>
                        </div>
                        <div class="card-body">
                            <pre class="bg-light p-2 rounded small">{{ json_encode($emailLog->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    </div>
                @endif
            </div>

            {{-- محتوى البريد --}}
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-light">
                        <div class="d-flex flex-wrap">
                            <div class="mr-4 mb-1">
                                <strong>إلى:</strong> {{ $emailLog->recipient_email }}
                            </div>
                        </div>
                        <h5 class="mt-2 mb-0">
                            <strong>الموضوع:</strong> {{ $emailLog->subject }}
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        {{-- عرض المحتوى --}}
                        @if ($emailLog->email_content)
                            @if (Str::startsWith(trim($emailLog->email_content), '<!DOCTYPE') || Str::startsWith(trim($emailLog->email_content), '<html'))
                                <iframe srcdoc="{{ $emailLog->email_content }}"
                                        style="width: 100%; height: 600px; border: none;"></iframe>
                            @else
                                <div class="p-3">
                                    <pre class="mb-0" style="white-space: pre-wrap;">{{ $emailLog->email_content }}</pre>
                                </div>
                            @endif
                        @else
                            <div class="stk-empty">
                                <span class="stk-empty__icon"><i class="feather icon-file-text"></i></span>
                                <p class="stk-empty__hint mb-0">لا يوجد محتوى</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
