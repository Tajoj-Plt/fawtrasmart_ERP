{{--
    حالة أمر التصنيع — تسمية ولون وأيقونة في موضع واحد.

    كانت السلسلة نفسها (`closed` / `completed` / `in_progress` / …) مكتوبة
    في الترويسة وفي القائمة وفي شاشة العرض، بأسماءٍ مختلفة أحياناً.
--}}
@php
    $map = [
        'pending' => ['جديد', 'secondary', 'circle'],
        'materials_received' => ['تم استلام المواد', 'info', 'download'],
        'in_progress' => ['قيد التنفيذ', 'warning', 'clock'],
        'completed' => ['مكتمل', 'success', 'check-circle'],
        'closed' => ['مغلق', 'secondary', 'lock'],
        'cancelled' => ['ملغي', 'danger', 'x-circle'],
        'active' => ['نشط', 'primary', 'play'],
    ];

    /*
        يستوعب الحالة كائنَ Enum أو نصّاً خاماً.

        بعد تفعيل الـcast صار `$order->status` كائناً، ودمجُه في نصّ
        يرمي «could not be converted to string». والنصّ الخام يبقى وارداً
        من صفوف قديمة كُتبت خارج ENUM العمود.
    */
    $key = $status instanceof \App\Enums\ManufacturOrderStatus ? $status->value : (string) $status;

    [$label, $tone, $icon] = $map[$key] ?? [$key ?: 'غير محدد', 'secondary', 'help-circle'];
@endphp

<span class="badge badge-light-{{ $tone }}">
    <i class="feather icon-{{ $icon }}"></i> {{ $label }}
</span>
