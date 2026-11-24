{{-- جدول حسابات الأصول الثابتة من شجرة الحسابات + أرصدتها --}}
@php $rowsList = $assets ?? collect(); @endphp
<table class="table table-hover table-striped mb-0">
    <thead>
        <tr>
            <th style="width: 40px;">#</th>
            <th style="width: 90px;">الكود</th>
            <th>اسم الحساب</th>
            <th>التصنيف (الأب)</th>
            <th class="text-left">الرصيد الافتتاحي</th>
            <th class="text-left text-success">إجمالي مدين</th>
            <th class="text-left text-danger">إجمالي دائن</th>
            <th class="text-left">حركة الفترة</th>
            <th class="text-left">الرصيد الحالي</th>
            <th class="text-center">الحالة</th>
            <th class="text-center">الحركة</th>
        </tr>
    </thead>
    <tbody class="assetsTableBody">
        @forelse($rowsList as $index => $row)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td><span class="badge bg-light text-dark">{{ $row['code'] ?? '-' }}</span></td>
                <td>
                    <strong>{{ $row['name'] }}</strong>
                </td>
                <td>
                    <span class="text-primary">{{ $row['parent_name'] ?? '-' }}</span>
                </td>
                <td class="text-left">{{ number_format($row['opening_balance'], 2) }}</td>
                <td class="text-left text-success">{{ number_format($row['total_debit'], 2) }}</td>
                <td class="text-left text-danger">{{ number_format($row['total_credit'], 2) }}</td>
                <td class="text-left {{ $row['period_movement'] >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($row['period_movement'], 2) }}
                </td>
                <td class="text-left fw-bold {{ $row['closing_balance'] >= 0 ? 'text-primary' : 'text-danger' }}">
                    {{ number_format($row['closing_balance'], 2) }}
                </td>
                <td class="text-center">
                    @if($row['is_active'])
                        <span class="badge bg-success">نشط</span>
                    @else
                        <span class="badge bg-secondary">غير نشط</span>
                    @endif
                </td>
                <td class="text-center">
                    @if($row['has_movement'])
                        <span class="badge bg-info">فيه حركة</span>
                    @else
                        <span class="badge bg-light text-dark">بدون حركة</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="11" class="text-center text-muted py-3">
                    <i class="feather icon-inbox font-large-1 d-block mb-1"></i>
                    لا توجد حسابات أصول مطابقة
                </td>
            </tr>
        @endforelse
    </tbody>
    @if($rowsList->count() > 0)
        <tfoot>
            <tr class="font-weight-bold" style="background:#f8f9fa;">
                <td colspan="4" class="text-left">الإجمالي</td>
                <td class="text-left">{{ number_format($rowsList->sum('opening_balance'), 2) }}</td>
                <td class="text-left text-success">{{ number_format($rowsList->sum('total_debit'), 2) }}</td>
                <td class="text-left text-danger">{{ number_format($rowsList->sum('total_credit'), 2) }}</td>
                <td class="text-left">{{ number_format($rowsList->sum('period_movement'), 2) }}</td>
                <td class="text-left text-primary">{{ number_format($rowsList->sum('closing_balance'), 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    @endif
</table>
