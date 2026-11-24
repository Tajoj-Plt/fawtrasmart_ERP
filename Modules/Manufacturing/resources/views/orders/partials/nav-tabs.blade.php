{{-- تبويبات أمر التصنيع --}}
<div class="stk-table-head">
    <ul class="nav nav-tabs stk-tabs" role="tablist">
        @foreach ([
            ['info', 'info', 'المعلومات'],
            ['received-materials', 'download', 'المواد المستلمة'],
            ['finished-goods', 'package', 'المنتج التام'],
            ['waste', 'trash-2', 'الهالك'],
            ['returned-materials', 'rotate-ccw', 'مرتجعات المواد'],
            ['activities', 'activity', 'سجلّ النشاط'],
        ] as [$id, $icon, $label])
            <li class="nav-item">
                <a class="nav-link {{ $loop->first ? 'active' : '' }}"
                   data-toggle="tab" data-bs-toggle="tab" href="#{{ $id }}" role="tab">
                    <i class="feather icon-{{ $icon }}"></i> {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>
</div>
