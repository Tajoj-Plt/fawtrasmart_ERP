<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>التاريخ</th>
                <th>الوصف</th>
                <th>مدين</th>
                <th>دائن</th>
                <th>الرصيد</th>
            </tr>
        </thead>
        <tbody>
            @if(isset($data) && count($data) > 0)
                @foreach($data as $entry)
                <tr>
                    <td>{{ $entry['date'] ?? '' }}</td>
                    <td>{{ $entry['description'] ?? '' }}</td>
                    <td>{{ number_format($entry['debit'] ?? 0, 2) }}</td>
                    <td>{{ number_format($entry['credit'] ?? 0, 2) }}</td>
                    <td>{{ number_format($entry['balance'] ?? 0, 2) }}</td>
                </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="5" class="text-center">لا توجد بيانات للعرض</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>

@if(isset($entries) && $entries->hasPages())
<div class="pagination-container">
    {{ $entries->links() }}
</div>
@endif