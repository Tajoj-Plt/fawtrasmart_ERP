{{--
    عارضُ المرفقات — لكل نوعِ ملفٍ ما يناسبه.

    كانت الصورُ وحدَها تُعرض مصغَّرةً، وما عداها رابطٌ نصّيّ بلا دلالة: لا
    يُعرَف نوعُ الملف قبل فتحه، ولا حجمُه، ولا إن كان موجوداً أصلاً. وملفُ
    PDF — وهو أكثرُ المرفقات في السندات — يُفتح في لسانٍ جديد بلا معاينة.

    @props:
      items  مصفوفةُ المسارات (نسبيّةً إلى الجذر العام)
      label  عنوانٌ اختياري

    وكلُّ مرفقٍ يُعرض ببطاقةٍ فيها: أيقونةٌ بنوعه، واسمُه، وحجمُه، وزرَّا
    «عرض» و«تنزيل». والصورةُ تزيد معاينةً مصغّرة، وPDF معاينةً داخلَ
    الصفحة — فلا يُغادرها المستخدم ليتحقّق من إيصال.
--}}
@props([
    'items' => [],
    'label' => null,
])

@php
    $files = collect($items)
        ->filter(fn ($p) => filled($p))
        ->map(function ($path) {
            $clean = ltrim((string) $path, '/');
            $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
            $abs = public_path($clean);

            return [
                'path' => $clean,
                'url' => asset($clean),
                'name' => basename($clean),
                'ext' => $ext,
                'exists' => is_file($abs),
                'size' => is_file($abs) ? filesize($abs) : null,
                'is_image' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true),
                'is_pdf' => $ext === 'pdf',
            ];
        })
        ->values();

    /** أيقونةٌ تدلّ على النوع قبل الفتح. */
    $iconFor = function (string $ext): string {
        return match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true) => 'image',
            $ext === 'pdf' => 'file-text',
            in_array($ext, ['doc', 'docx', 'rtf', 'odt'], true) => 'file-text',
            in_array($ext, ['xls', 'xlsx', 'csv', 'ods'], true) => 'grid',
            in_array($ext, ['zip', 'rar', '7z'], true) => 'archive',
            default => 'file',
        };
    };

    /** حجمٌ مقروء — الرقمُ الخام لا يقول شيئاً للمستخدم. */
    $human = function (?int $bytes): string {
        if ($bytes === null) {
            return '';
        }
        foreach ([['GB', 1073741824], ['MB', 1048576], ['KB', 1024]] as [$unit, $step]) {
            if ($bytes >= $step) {
                return round($bytes / $step, 1).' '.$unit;
            }
        }

        return $bytes.' B';
    };
@endphp

@if ($label)
    <div class="stk-table-head"><h6 class="stk-table-head__title">
        <i class="feather icon-paperclip"></i> {{ $label }}
        <span class="stk-count">{{ $files->count() }}</span>
    </h6></div>
@endif

@if ($files->isEmpty())
    <p class="stk-help mb-0">لا مرفقات.</p>
@else
    <div class="atl">
        @foreach ($files as $i => $f)
            @php($uid = 'atl-'.md5($f['path']).'-'.$i)

            <div class="atl__item {{ $f['exists'] ? '' : 'atl__item--missing' }}">
                <div class="atl__thumb">
                    @if ($f['is_image'] && $f['exists'])
                        <img src="{{ $f['url'] }}" alt="{{ $f['name'] }}" loading="lazy">
                    @else
                        <i class="feather icon-{{ $iconFor($f['ext']) }}"></i>
                    @endif
                </div>

                <div class="atl__body">
                    <span class="atl__name" title="{{ $f['name'] }}">{{ $f['name'] }}</span>
                    <span class="atl__meta">
                        {{ strtoupper($f['ext']) ?: 'ملف' }}
                        @if ($f['size']) · {{ $human($f['size']) }} @endif
                        @unless ($f['exists'])
                            · <span class="text-danger">الملف غير موجود على الخادم</span>
                        @endunless
                    </span>

                    @if ($f['exists'])
                        <span class="atl__actions">
                            @if ($f['is_pdf'])
                                {{-- معاينةٌ داخل الصفحة: التحقّقُ من إيصالٍ لا يستحقّ مغادرتها --}}
                                <button type="button" class="atl__btn" data-atl-preview="{{ $uid }}">
                                    <i class="feather icon-eye"></i> معاينة
                                </button>
                            @endif
                            <a class="atl__btn" href="{{ $f['url'] }}" target="_blank" rel="noopener">
                                <i class="feather icon-external-link"></i> فتح
                            </a>
                            <a class="atl__btn" href="{{ $f['url'] }}" download="{{ $f['name'] }}">
                                <i class="feather icon-download"></i> تنزيل
                            </a>
                        </span>
                    @endif
                </div>
            </div>

            @if ($f['is_pdf'] && $f['exists'])
                <div class="atl__preview" id="{{ $uid }}" hidden>
                    <iframe src="{{ $f['url'] }}" title="{{ $f['name'] }}" loading="lazy"></iframe>
                </div>
            @endif
        @endforeach
    </div>

    @once
        @push('scripts')
            <script>
                // زرُّ المعاينة يطوي الإطارَ ويفتحه — والإطارُ لا يُحمَّل قبل فتحه
                document.addEventListener('click', function (e) {
                    var btn = e.target.closest('[data-atl-preview]');
                    if (!btn) return;
                    var box = document.getElementById(btn.getAttribute('data-atl-preview'));
                    if (!box) return;
                    box.hidden = !box.hidden;
                    btn.innerHTML = box.hidden
                        ? '<i class="feather icon-eye"></i> معاينة'
                        : '<i class="feather icon-eye-off"></i> إخفاء';
                });
            </script>
        @endpush
    @endonce
@endif
