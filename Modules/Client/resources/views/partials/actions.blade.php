<div class="btn-group">
    <div class="dropdown">
        <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm"
            type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></button>
        <div class="dropdown-menu">
            <a class="dropdown-item" href="{{ route('clients.show', $client->id) }}">
                <i class="fa fa-eye me-2 text-primary"></i>عرض
            </a>
            @if (hasPermission('clients_edit_data'))
                <a class="dropdown-item" href="{{ route('clients.edit', $client->id) }}">
                    <i class="fa fa-pencil-alt me-2 text-success"></i>تعديل
                </a>
            @endif
            <a class="dropdown-item" href="{{ route('clients.send_info', $client->id) }}">
                <i class="fa fa-pencil-alt me-2 text-success"></i> إرسال بيانات الدخول
            </a>
            <a class="dropdown-item" href="{{ route('clients.edit', $client->id) }}">
                <i class="fa fa-copy me-2 text-info"></i>نسخ
            </a>
            @if (hasPermission('clients_delete'))
                <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#modal_DELETE{{ $client->id }}">
                    <i class="fa fa-trash-alt me-2"></i>حذف
                </a>
            @endif
            <!-- Excel Export Option -->
            @php
                /*
                 * صفّ التصدير يُجمَّع هنا لا داخل `@json([...])`.
                 *
                 * مُحلّل وسائط Blade يوازن الأقواس الهلالية `()` وحدها ولا
                 * يرى المربّعة `[]`. فمصفوفةٌ متعدّدة الأسطر داخل `@json(`
                 * وفيها `optional($x)->name` تُقطَع عند أوّل موضعٍ تتوازن
                 * فيه الهلالية، فيخرج `json_encode([` بلا `]` تقابله ويسقط
                 * الطلب بخطأ 500. المتغيّر الواحد يمرّ سليماً.
                 */
                $exportRow = [
                    'id' => $client->id,
                    'code' => $client->code,
                    'trade_name' => $client->trade_name,
                    'frist_name' => $client->frist_name,
                    'phone' => $client->phone,
                    'branch' => optional($client->branch)->name,
                    'category' => optional($client->categoriesClient)->name,
                    'created_at' => optional($client->created_at)->format('Y-m-d'),
                ];
            @endphp

            <a class="dropdown-item export-single-client" href="#"
               data-client='@json($exportRow, JSON_UNESCAPED_UNICODE)'>
                <i class="fa fa-file-excel me-2 text-success"></i>تصدير اكسل
            </a>
            <a class="dropdown-item" href="{{ route('clients.edit', $client->id) }}">
                <i class="fa fa-file-invoice me-2 text-warning"></i>كشف حساب
            </a>
        </div>
    </div>
</div>