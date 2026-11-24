{{-- 
    عرض أيقونة الإنجاز - يدعم كلاً من Font Awesome class names والرموز التعبيرية (Emoji)
    
    Parameters:
    - $icon: قيمة الأيقونة (fa-star أو 🏆)
    - $size: حجم الأيقونة (اختياري، افتراضي: 1.5rem)
    - $default: الأيقونة الافتراضية (اختياري، افتراضي: 🏆)
--}}
@php
    $iconVal = $icon ?? ($default ?? '🏆');
    $iconSize = $size ?? '1.5rem';
    $isFontAwesome = is_string($iconVal) && (
        str_starts_with($iconVal, 'fa-') || 
        str_starts_with($iconVal, 'fas ') || 
        str_starts_with($iconVal, 'far ') || 
        str_starts_with($iconVal, 'fab ') ||
        str_starts_with($iconVal, 'fa ')
    );
@endphp
@if($isFontAwesome)
<i class="fa {{ $iconVal }}" style="font-size: {{ $iconSize }};"></i>
@else
<span style="font-size: {{ $iconSize }}; line-height: 1;">{!! $iconVal !!}</span>
@endif
