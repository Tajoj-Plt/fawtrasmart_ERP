{{--
    شبكة تفاصيل المستند — نفس شكل تبويب «التفاصيل» في شاشة طلب الشراء.

    الحقل الفارغ يُعرض «غير محدَّد» بدل أن يخرج فراغاً بلا معنى.

    @param array<int, array{icon: string, label: string, value: mixed}> $details
--}}
<div class="po-details">
    @foreach ($details as $detail)
        <div>
            <div class="po-detail__label">
                <i class="fas {{ $detail['icon'] }}"></i>{{ $detail['label'] }}
            </div>
            <div class="po-detail__value">
                {{ filled($detail['value']) ? $detail['value'] : __('purchase_cycle.empty.unknown') }}
            </div>
        </div>
    @endforeach
</div>
