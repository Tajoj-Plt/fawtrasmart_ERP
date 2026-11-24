{{--
    نافذةُ تأكيد الحذف لسجلٍّ في الهيكل التنظيمي.

      modalId   معرّف النافذة (يُطابق `data-target` في زرّ الحذف)
      heading   عنوان النافذة
      action    رابط الحذف (DELETE)
      itemName  اسم السجل
      usage     ['التسمية' => العدد] — ما يمنع الحذف (اختياري؛ في القائمة يُفحص عند الإرسال)
      subject   «القسم» / «المسمى الوظيفي» …
--}}
@php
    $deleteUsage = (isset($usage) && is_array($usage)) ? $usage : [];
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content text-right">
            <div class="modal-header">
                <h5 class="modal-title">{{ $heading }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                @if ($deleteUsage !== [])
                    <p class="mb-1 text-danger">
                        {{ $subject }} <strong>{{ $itemName }}</strong> مستعمَلٌ فلا يُحذف:
                    </p>
                    <ul class="mb-1">
                        @foreach ($deleteUsage as $usageLabel => $usageCount)
                            <li>{{ $usageLabel }}: <span class="stk-num">{{ $usageCount }}</span></li>
                        @endforeach
                    </ul>
                    <p class="stk-empty__hint mb-0">عطّله بدلاً من ذلك — يختفي من الاختيار ويبقى أثرُه — أو انقل ما يتبعه أوّلاً.</p>
                @else
                    <p class="mb-1">سيُحذف {{ $subject }} <strong>{{ $itemName }}</strong> نهائياً.</p>
                    <p class="stk-empty__hint mb-0">يُرفض الحذف إن كان مستعمَلاً في ملفّ موظفٍ أو عقدٍ أو غيرهما — فالحذفُ في قاعدة البيانات يسري على ما يتبعه.</p>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">إلغاء</button>
                @if ($deleteUsage === [])
                    <form action="{{ $action }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger"><i class="feather icon-trash-2"></i> حذف نهائي</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
