{{-- اسمُ الموظف رابطاً إلى ملفّه لمن يملك `employees.show` — @param int $empId, string $empName --}}
@if (($canShowEmployee ?? false) && $empId)
    <a href="{{ route('employee.show', $empId) }}" target="_blank" rel="noopener">{{ $empName }}</a>
@else
    {{ $empName }}
@endif
