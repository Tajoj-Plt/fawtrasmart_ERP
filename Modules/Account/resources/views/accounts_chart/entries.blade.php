@extends('account::master')
@section('title')
  قيود الحساب
@stop

@section('content')
@include('account::components.page-head', [
    'title' => 'قيود الحساب',
    'crumbs' => [
        'الحسابات' => route('accounts_chart.view_root'),
        $account->name => null,
    ],
])

<div class="content-body">
  <div class="container-fluid">
    <div class="content-body">

      <!-- شريط الأدوات العلوي -->
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
              <span class="h5 mb-0">قيود الحساب: {{ $account->name }} ({{ $account->code }})</span>
            </div>
            <div class="d-flex gap-1">
              <a href="{{ route('accounts_chart.show_account', [$account->parent_id]) }}" class="btn btn-outline-primary">
                <i class="fa fa-sitemap me-1"></i> رجوع للشجرة
              </a>
              <a href="{{ route('journal.index') }}" class="btn btn-outline-primary">
                <i class="fas fa-calendar-alt me-1"></i> سجل القيود
              </a>
              <a href="{{ route('journal.create') }}" class="btn btn-primary waves-effect waves-light">
                <i class="fas fa-plus-circle me-1"></i> قيد جديد
              </a>
            </div>
          </div>
        </div>
      </div>

  <!-- بطاقة البحث والتصفية -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center p-2">
          <div class="d-flex gap-2">
            <span class="hide-button-text">بحث وتصفية</span>
          </div>
          <div class="d-flex align-items-center flex-wrap gap-2">
            <button class="btn btn-outline-primary btn-sm waves-effect waves-light" onclick="toggleSearchFields(this)">
              <i class="fa fa-times me-1"></i>
              <span class="hide-button-text">إخفاء</span>
            </button>
            <a href="{{ route('accounts_chart.entries', [$account->id]) }}" class="btn btn-outline-primary btn-sm waves-effect waves-light">
              <i class="fa fa-refresh me-1"></i>
              إعادة تعيين
            </a>
          </div>
        </div>
        <div class="card-body" id="entriesSearchCard">
          <form class="form" method="GET" action="{{ route('accounts_chart.entries', [$account->id]) }}">
            <div class="row g-3">
              {{-- الفترة المحاسبية: اختيارها يملأ حقلَي المدى --}}
              @include('partials.accounting-period-filter', [
                  'fromName' => 'from_date',
                  'toName' => 'to_date',
                  'colClass' => 'col-md-3',
              ])

              <div class="col-md-3">
                <label for="from_date">التاريخ من</label>
                <input type="date" id="from_date" name="from_date" class="form-control form-control-sm" value="{{ $fromDate }}">
              </div>
              <div class="col-md-3">
                <label for="to_date">التاريخ إلى</label>
                <input type="date" id="to_date" name="to_date" class="form-control form-control-sm" value="{{ $toDate }}">
              </div>
              <div class="col-md-3">
                <label for="branch_id">الفرع</label>
                <select id="branch_id" name="branch_id" class="form-control form-control-sm">
                  <option value="all">كل الفروع</option>
                  @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" {{ ($branchId==$branch->id) ? 'selected' : '' }}>{{ $branch->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary" type="submit">بحث</button>
              </div>
            </div>
          </form>
        </div>
      </div>

  <!-- بطاقة الملخص والنتائج -->
      <div class="card">
        <div class="card-body">
          <div class="row mb-2">
            <div class="col-md-3 col-6">
              <div class="p-1 border rounded bg-light">
                <small class="text-muted">إجمالي المدين</small>
                <div class="fw-bold text-primary">{{ number_format($summary['total_debit'],2) }}</div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="p-1 border rounded bg-light">
                <small class="text-muted">إجمالي الدائن</small>
                <div class="fw-bold text-danger">{{ number_format($summary['total_credit'],2) }}</div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="p-1 border rounded bg-light">
                <small class="text-muted">الرصيد</small>
                <div class="fw-bold {{ $summary['balance']>=0 ? 'text-success' : 'text-danger' }}">{{ number_format($summary['balance'],2) }}</div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <div class="p-1 border rounded bg-light">
                <small class="text-muted">عدد القيود</small>
                <div class="fw-bold">{{ $journalEntries->total() }}</div>
              </div>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 dataTable">
              <thead class="bg-light">
                <tr>
                  <th style="width:110px">التاريخ</th>
                  <th>الوصف</th>
                  <th style="width:90px">مدين</th>
                  <th style="width:90px">دائن</th>
                  <th style="width:110px">الرصيد المرحلي</th>
                  <th style="width:120px">المصدر</th>
                  <th style="width:60px">عرض</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($journalEntries->count()): ?>
                <?php $running = 0; ?>
                <?php foreach ($journalEntries as $entry): ?>
                  <?php 
                    $running += ($entry->debit - $entry->credit);
                    $j = $entry->journalEntry;
                    $rt = $j?->reference_type; 
                    $refType = (is_scalar($rt) && isset($referenceTypeNames[(string)$rt])) 
                      ? e($referenceTypeNames[(string)$rt]) 
                      : (is_scalar($rt) ? e((string)$rt) : '—');
                  ?>
                  <tr>
                    <td><?= $j?->date ?></td>
                    <td><?= $entry->description ?? ($j?->description) ?></td>
                    <td class="text-primary"><?= ($entry->debit>0) ? number_format($entry->debit,2) : '' ?></td>
                    <td class="text-danger"><?= ($entry->credit>0) ? number_format($entry->credit,2) : '' ?></td>
                    <td class="fw-bold <?= ($running>=0 ? 'text-success' : 'text-danger') ?>"><?php echo number_format($running,2); ?></td>
                    <td><?= $refType ?></td>
                    <td><a href="<?= route('journal.show', [$entry->journal_entry_id]) ?>" class="btn btn-outline-primary btn-sm"><i class="feather icon-eye"></i></a></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="7" class="text-center text-muted">لا توجد قيود لهذا الحساب ضمن نطاق التصفية.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="mt-1 d-flex justify-content-between align-items-center w-100">
            <div class="stk-table-foot__count">
              عرض {{ $journalEntries->firstItem() ?? 0 }} إلى {{ $journalEntries->lastItem() ?? 0 }} من {{ $journalEntries->total() }} نتيجة
            </div>
            <div>
              {{ $journalEntries->appends(['from_date'=>$fromDate,'to_date'=>$toDate,'branch_id'=>$branchId])->links() }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
  function toggleSearchFields(button) {
    const cardBody = document.getElementById('entriesSearchCard');
    const buttonText = button.querySelector('.hide-button-text');
    const icon = button.querySelector('i');

    if (cardBody.style.display === 'none') {
      cardBody.style.display = 'block';
      buttonText.textContent = 'إخفاء';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-times');
    } else {
      cardBody.style.display = 'none';
      buttonText.textContent = 'اظهار';
      icon.classList.remove('fa-times');
      icon.classList.add('fa-eye');
    }
  }
</script>
<style>
  .content-header-title { font-size: 1.35rem; }
  .card { margin-bottom: 0.9rem; }
  .card-body { padding: 0.9rem 1rem; }
  .dataTable th, .dataTable td { vertical-align: middle; }
  .pagination-info { font-size: .75rem; }
  .bg-light { background-color:#f8f9fa !important; }
  .p-1.border.rounded.bg-light { background:#fafafa; }
  .table-sm tbody tr:hover { background:#fdfdfd; }
  .breadcrumb { background:transparent; margin-bottom:0; }
  .breadcrumb-item + .breadcrumb-item::before { content:'>'; }
</style>
@endsection
