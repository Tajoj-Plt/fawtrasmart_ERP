@extends('onlinestore::master')

@section('title', 'معاينة: ' . $emailTemplate->template_name)

@section('content')
    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('online_store.email_templates.edit'))
                <a href="{{ route('email_templates.edit', $emailTemplate) }}" class="btn btn-primary">
                    <i class="feather icon-edit-2"></i> تعديل
                </a>
            @endif
            <a href="{{ route('email_templates.show', $emailTemplate) }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-right"></i> العودة
            </a>
        @endsection

        @include('onlinestore::components.page-head', [
            'title' => 'معاينة القالب',
            'icon' => 'monitor',
            'sub' => $emailTemplate->template_name,
            'crumbs' => [
                'الرئيسية' => url('/'),
                'المتجر الإلكتروني' => route('onlinestore.department.dashboard'),
                'قوالب البريد' => route('email_templates.index'),
                $emailTemplate->template_name => route('email_templates.show', $emailTemplate),
                'معاينة' => null,
            ],
        ])

        <div class="row">
            {{-- المعاينة --}}
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-light">
                        <div class="d-flex align-items-center">
                            <div class="mr-3">
                                <span class="badge badge-secondary">من: {{ config('mail.from.address') }}</span>
                            </div>
                            <div class="mr-3">
                                <span class="badge badge-secondary">إلى: customer@example.com</span>
                            </div>
                        </div>
                        <h5 class="mt-2 mb-0"><strong>الموضوع:</strong> {{ $content['subject'] }}</h5>
                    </div>
                    <div class="card-body p-0">
                        {{-- أزرار تبديل العرض --}}
                        <div class="bg-light border-bottom p-2">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary active" id="btn-desktop">
                                    <i class="feather icon-monitor"></i> سطح المكتب
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="btn-tablet">
                                    <i class="feather icon-tablet"></i> تابلت
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="btn-mobile">
                                    <i class="feather icon-smartphone"></i> جوال
                                </button>
                            </div>
                        </div>

                        {{-- إطار المعاينة --}}
                        <div id="preview-container" style="background: #f5f5f5; padding: 20px; min-height: 600px;">
                            <iframe id="preview-frame" srcdoc="{{ $content['html'] }}"
                                    style="width: 100%; height: 600px; border: 1px solid #ddd; background: #fff; border-radius: 5px;"></iframe>
                        </div>
                    </div>
                </div>

                {{-- المحتوى النصي --}}
                @if ($content['text'])
                    <div class="card">
                        <div class="stk-table-head">
                            <h6 class="stk-table-head__title"><i class="feather icon-file-text"></i> النسخة النصية</h6>
                        </div>
                        <div class="card-body">
                            <pre class="bg-light p-3 rounded" style="white-space: pre-wrap;">{{ $content['text'] }}</pre>
                        </div>
                    </div>
                @endif
            </div>

            {{-- البيانات التجريبية --}}
            <div class="col-md-4">
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-database"></i> البيانات التجريبية</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">هذه البيانات المستخدمة في المعاينة:</p>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tbody>
                                    @foreach ($sampleData as $key => $value)
                                        <tr>
                                            <td><code>{{ $key }}</code></td>
                                            <td><small>{{ Str::limit($value, 30) }}</small></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
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
                                           placeholder="البريد الإلكتروني" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="feather icon-send"></i> إرسال
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- معلومات القالب --}}
                <div class="card">
                    <div class="stk-table-head">
                        <h6 class="stk-table-head__title"><i class="feather icon-info"></i> معلومات القالب</h6>
                    </div>
                    <div class="card-body">
                        <dl class="stk-def">
                            <div class="stk-def__row">
                                <dt>الكود</dt>
                                <dd><code>{{ $emailTemplate->template_code }}</code></dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>التصنيف</dt>
                                <dd>{{ $emailTemplate->template_category?->label() ?? $emailTemplate->template_category }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>اللغة</dt>
                                <dd>{{ $emailTemplate->language == 'ar' ? 'العربية' : 'English' }}</dd>
                            </div>
                            <div class="stk-def__row">
                                <dt>الحالة</dt>
                                <dd>
                                    <span class="stk-state {{ $emailTemplate->is_active ? 'stk-state--on' : 'stk-state--off' }}">
                                        {{ $emailTemplate->is_active ? 'مفعل' : 'معطل' }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    const frame = $('#preview-frame');
    const container = $('#preview-container');

    $('#btn-desktop').on('click', function() {
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
        frame.css('width', '100%');
    });

    $('#btn-tablet').on('click', function() {
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
        frame.css('width', '768px');
    });

    $('#btn-mobile').on('click', function() {
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
        frame.css('width', '375px');
    });
});
</script>
@endsection
