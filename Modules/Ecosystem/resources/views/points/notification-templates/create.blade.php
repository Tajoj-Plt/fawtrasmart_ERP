@extends('ecosystem::master')

@section('title', 'إضافة قالب إشعار جديد')

@section('content')
<div class="content-header row">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-left mb-0">إضافة قالب إشعار جديد</h2>
                <div class="breadcrumb-wrapper col-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('ecosystem.points.notification-templates.index') }}">قوالب الإشعارات</a></li>
                        <li class="breadcrumb-item active">إضافة جديد</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <form action="{{ route('ecosystem.points.notification-templates.store') }}" method="POST">
        @csrf

        <div class="row mb-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('ecosystem.points.notification-templates.index') }}" class="btn btn-outline-secondary">
                                <i class="feather icon-x"></i> إلغاء
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> حفظ القالب
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- نوع الحدث -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-zap"></i> نوع الحدث</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="event_type">نوع الحدث <span class="text-danger">*</span></label>
                            <select name="event_type" id="event_type" class="form-control @error('event_type') is-invalid @enderror" required>
                                <option value="">-- اختر نوع الحدث --</option>
                                @foreach($eventTypes as $key => $event)
                                    <option value="{{ $key }}" {{ old('event_type') == $key ? 'selected' : '' }}
                                            data-variables="{{ implode(',', $event['variables']) }}"
                                            data-description="{{ $event['description'] }}">
                                        {{ $event['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('event_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">اختر الحدث الذي سيُرسل عنده الإشعار تلقائياً</small>
                        </div>
                        <div id="eventDescription" class="alert alert-light-info" style="display: none;">
                            <i class="feather icon-info"></i> <span id="eventDescText"></span>
                        </div>
                        <div id="variablesHelp" class="alert alert-light-warning" style="display: none;">
                            <strong><i class="feather icon-code"></i> المتغيرات المتاحة:</strong>
                            <div id="variablesList" class="mt-1"></div>
                            <small class="text-muted">انقر على المتغير لنسخه ولصقه في العنوان أو المحتوى</small>
                        </div>
                    </div>
                </div>

                <!-- المحتوى بالعربية -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-edit-3"></i> المحتوى بالعربية</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="title_ar">العنوان بالعربية <span class="text-danger">*</span></label>
                            <input type="text" name="title_ar" id="title_ar"
                                   class="form-control @error('title_ar') is-invalid @enderror"
                                   value="{{ old('title_ar') }}" required placeholder="مثال: تم كسب {points} نقطة">
                            @error('title_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">العنوان الرئيسي للإشعار</small>
                        </div>
                        <div class="form-group">
                            <label for="body_ar">المحتوى بالعربية <span class="text-danger">*</span></label>
                            <textarea name="body_ar" id="body_ar" rows="4"
                                      class="form-control @error('body_ar') is-invalid @enderror"
                                      required placeholder="مثال: مرحباً {user_name}، تم إضافة {points} نقطة إلى رصيدك">{{ old('body_ar') }}</textarea>
                            @error('body_ar')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">نص الإشعار الكامل - استخدم المتغيرات بين {} للبيانات الديناميكية</small>
                        </div>
                    </div>
                </div>

                <!-- المحتوى بالإنجليزية (اختياري) -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-globe"></i> المحتوى بالإنجليزية (اختياري)</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="title_en">العنوان بالإنجليزية</label>
                            <input type="text" name="title_en" id="title_en"
                                   class="form-control @error('title_en') is-invalid @enderror"
                                   value="{{ old('title_en') }}" placeholder="e.g. You earned {points} points">
                            @error('title_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="body_en">المحتوى بالإنجليزية</label>
                            <textarea name="body_en" id="body_en" rows="4"
                                      class="form-control @error('body_en') is-invalid @enderror"
                                      placeholder="e.g. Hello {user_name}, {points} points have been added.">{{ old('body_en') }}</textarea>
                            @error('body_en')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- القنوات -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-send"></i> قنوات الإرسال</h4>
                    </div>
                    <div class="card-body">
                        @foreach($channels as $channel)
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" name="channels[]" value="{{ $channel->value }}"
                                       id="channel_{{ $channel->value }}"
                                       class="custom-control-input"
                                       {{ in_array($channel->value, old('channels', [])) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="channel_{{ $channel->value }}">
                                    {{ $channel->label() }}
                                </label>
                            </div>
                        @endforeach
                        @error('channels')
                            <div class="text-danger mt-1"><small>{{ $message }}</small></div>
                        @enderror
                        @error('channels.*')
                            <div class="text-danger mt-1"><small>{{ $message }}</small></div>
                        @enderror
                        <small class="text-muted d-block mt-2">حدد قنوات الإرسال المطلوبة</small>
                    </div>
                </div>

                <!-- الإعدادات -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-settings"></i> الإعدادات</h4>
                    </div>
                    <div class="card-body">
                        <div class="switch-form-group">
                            <div class="switch-row">
                                <span class="switch-label">تفعيل القالب</span>
                                <label class="ios-switch">
                                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <small class="switch-help">عند التفعيل سيتم إرسال الإشعارات تلقائياً</small>
                        </div>
                    </div>
                </div>

                <!-- نصائح وإرشادات -->
                <div class="card" id="tipsCard">
                    <div class="card-header py-75">
                        <h4 class="card-title mb-0" style="font-size: 0.95rem;"><i class="feather icon-help-circle"></i> نصائح وإرشادات</h4>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item py-75 px-1 border-0">
                                <div class="d-flex align-items-center">
                                    <span class="bullet bullet-sm bullet-primary mr-50"></span>
                                    <small>استخدم <strong>{user_name}</strong> لاسم المستخدم</small>
                                </div>
                            </li>
                            <li class="list-group-item py-75 px-1 border-0">
                                <div class="d-flex align-items-center">
                                    <span class="bullet bullet-sm bullet-success mr-50"></span>
                                    <small>المتغيرات تتغير حسب <strong>نوع الحدث</strong></small>
                                </div>
                            </li>
                            <li class="list-group-item py-75 px-1 border-0">
                                <div class="d-flex align-items-center">
                                    <span class="bullet bullet-sm bullet-warning mr-50"></span>
                                    <small>يمكنك <strong>تعطيل</strong> القالب مؤقتاً دون حذفه</small>
                                </div>
                            </li>
                            <li class="list-group-item py-75 px-1 border-0">
                                <div class="d-flex align-items-center">
                                    <span class="bullet bullet-sm bullet-danger mr-50"></span>
                                    <small>حدد <strong>قناة واحدة</strong> على الأقل للإرسال</small>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('#event_type').on('change', function() {
        var selected = $(this).find(':selected');
        var description = selected.data('description');
        var variables = selected.data('variables');

        if (description) {
            $('#eventDescText').text(description);
            $('#eventDescription').show();
        } else {
            $('#eventDescription').hide();
        }

        if (variables) {
            var varsArray = variables.split(',');
            var html = '';
            varsArray.forEach(function(v) {
                html += '<code class="mr-2 mb-1 d-inline-block" style="cursor:pointer; padding: 3px 8px; background: #fff3cd; border-radius: 4px;" onclick="copyVariable(this)">{' + v.trim() + '}</code> ';
            });
            $('#variablesList').html(html);
            $('#variablesHelp').show();
        } else {
            $('#variablesHelp').hide();
        }
    });

    // Trigger on page load if old value exists
    if ($('#event_type').val()) {
        $('#event_type').trigger('change');
    }
});

function copyVariable(el) {
    var text = $(el).text();
    navigator.clipboard.writeText(text).then(function() {
        toastr.info('تم نسخ: ' + text);
    });
}
</script>
@endsection
