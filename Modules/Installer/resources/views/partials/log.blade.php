{{-- سجلّ التنفيذ — مشترك بين شاشتَي النجاح والتعثّر --}}
<table>
    <thead>
    <tr>
        <th>المرحلة</th>
        <th>النتيجة</th>
        <th>التفصيل</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($log as $entry)
        <tr>
            <td style="width: 170px;"><strong>{{ $entry['title'] }}</strong></td>
            <td style="width: 90px;">
                <span class="pill {{ $entry['ok'] ? 'ok' : 'bad' }}">{{ $entry['ok'] ? 'تم' : 'تعثّر' }}</span>
            </td>
            <td>
                {{ $entry['message'] }}

                @if (! empty($entry['details']))
                    <details>
                        <summary>عرض التفاصيل ({{ count($entry['details']) }})</summary>
                        <table style="margin-top: 8px;">
                            @foreach ($entry['details'] as $detail)
                                <tr>
                                    <td style="width: 240px;">{{ $detail['seeder'] }}</td>
                                    <td style="width: 80px;">
                                        <span class="pill {{ $detail['ok'] ? 'ok' : ($detail['skipped'] ? 'warn' : 'bad') }}">
                                            {{ $detail['ok'] ? 'تم' : ($detail['skipped'] ? 'تُخطِّي' : 'فشل') }}
                                        </span>
                                    </td>
                                    <td class="hint">{{ $detail['message'] }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </details>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
