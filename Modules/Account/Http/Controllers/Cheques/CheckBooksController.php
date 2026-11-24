<?php

namespace Modules\Account\Http\Controllers\Cheques;

use App\Constants\SystemAccountIds;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChequeBookRequest;
use App\Models\Account;
use App\Models\ChequeBook;
use App\Support\AccountingPeriodFilter;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Modules\Account\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class CheckBooksController extends Controller
{
    use ScopesToEmployeeHierarchy;

    public function __construct()
    {
        // صلاحيات دفتر الشيكات — الكتالوج الجديد account.cheque_books.*
        $this->middleware('permission.role:account.cheque_books.view', ['only' => ['index']]);
        $this->middleware('permission.role:account.cheque_books.show', ['only' => ['show']]);
        $this->middleware('permission.role:account.cheque_books.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:account.cheque_books.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:account.cheque_books.delete', ['only' => ['delete']]);
    }

    /**
     * قائمة دفاتر الشيكات.
     *
     * كانت الشاشة تحمل نموذج فلترةٍ كاملاً — بحثٌ ومدًى زمني و«بحث متقدم»
     * بأربعة حقول — و`<form action="#">` بينما الدالّة تُرجع كل الصفوف بلا
     * قراءة معاملٍ واحد. أي أنّ الفلترة كانت واجهةً بلا خلفها شيء: يضغط
     * المستخدم «بحث» فتُعاد الصفحة كما هي.
     *
     * فُعِّلت الحقول التي لها معنى، وحُذف ما لا يقابله عمود (قسم، فرع).
     */
    public function index(Request $request)
    {
        // من أنشأ الدفتر هو أو من يديرهم — نفس نطاق سندات الصرف والقبض
        // (ScopesToEmployeeHierarchy)، بحسب التسلسل الإداري لا دورٍ ثابت.
        // وسياقُ الفرع من الشريط العلوي — المستند يخصّ الفرع الذي أُنشئ فيه
        $check_books = $this->scopeToCreator(ChequeBook::query())
            ->forBranch($this->headerBranchNode())
            ->with('bank')
            ->when($request->filled('keywords'), function ($query) use ($request) {
                $keyword = $request->keywords;

                $query->where(function ($q) use ($keyword) {
                    $q->where('cheque_book_number', 'like', "%{$keyword}%")
                        ->orWhereHas('bank', fn ($b) => $b->where('name', 'like', "%{$keyword}%"));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('currency'), fn ($q) => $q->where('currency', $request->currency))
            ->tap(fn ($q) => AccountingPeriodFilter::apply($q, AccountingPeriodFilter::resolve($request)))
            ->orderByDesc('id')
            ->get();

        $period = AccountingPeriodFilter::resolve($request);

        return view('account::cheques.check_books.index', compact('check_books', 'period'));
    }

    public function create()
    {
        $bank_accounts = Account::where('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT))
            ->where('is_active', 1)
            ->get();

        // توليد رقم دفتر الشيكات التلقائي
        $cheque_book_number = $this->generateChequeBookNumber();

        return view('account::cheques.check_books.create', compact('bank_accounts', 'cheque_book_number'));
    }

    /**
     * توليد رقم دفتر شيكات فريد
     * Format: CB-YYYYMMDD-XXXX
     */
    private function generateChequeBookNumber(): string
    {
        $prefix = 'CB';
        $date = now()->format('Ymd');

        // الحصول على آخر رقم لهذا اليوم
        $lastBook = ChequeBook::where('cheque_book_number', 'like', "{$prefix}-{$date}-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastBook) {
            // استخراج الرقم التسلسلي من آخر دفتر
            $parts = explode('-', $lastBook->cheque_book_number);
            $sequence = isset($parts[2]) ? (int) $parts[2] + 1 : 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    public function store(ChequeBookRequest $request)
    {
        // Number of checks = (end serial number - start serial number) + 1
        $cheque_book = new ChequeBook;
        $cheque_book->bank_id = $request->bank_id;
        // استخدام الرقم المولد تلقائياً أو توليد جديد إذا لم يتم تمريره
        $cheque_book->cheque_book_number = $request->cheque_book_number ?: $this->generateChequeBookNumber();
        $cheque_book->currency = $request->currency;
        $cheque_book->start_serial_number = $request->start_serial_number;
        $cheque_book->end_serial_number = $request->end_serial_number;
        $cheque_book->status = $request->status;
        $cheque_book->notes = $request->notes;
        $cheque_book->created_by = auth()->id();

        $cheque_book->save();

        /*
            كانت عمليات هذا الملفّ الثلاث — إنشاء وتعديل وحذف — بلا أثرٍ في
            أي سجلّ. دفترُ شيكاتٍ يُحذف ولا يُعرف من حذفه ولا متى، وهو ما لا
            يصحّ في وحدةٍ ماليّة.
        */
        OperationLog::record('created', 'Account.CheckBooks', $cheque_book,
            'أنشأ دفتر شيكات رقم '.$cheque_book->cheque_book_number
            .' (المسلسل '.$cheque_book->start_serial_number.'–'.$cheque_book->end_serial_number.')');

        return redirect()->route('check_books.index')->with(['success' => 'تم اضافة دفتر الشيكات بنجاح']);
    }

    public function show($id)
    {
        $checkbook = ChequeBook::with(['bank', 'cheques'])->findOrFail($id);

        $this->authorizeCreatorScope($checkbook->created_by);

        return view('account::cheques.check_books.show', compact('checkbook'));
    }

    public function edit($id)
    {
        $bank_accounts = Account::where('parent_id', AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT))
            ->where('is_active', 1)
            ->get();
        $checkbook = ChequeBook::findOrFail($id);

        $this->authorizeCreatorScope($checkbook->created_by);

        return view('account::cheques.check_books.edit', compact('checkbook', 'bank_accounts'));
    }

    public function update(ChequeBookRequest $request, $id)
    {
        $cheque_book = ChequeBook::findOrFail($id);

        $this->authorizeCreatorScope($cheque_book->created_by);

        $cheque_book->bank_id = $request->bank_id;
        $cheque_book->cheque_book_number = $request->cheque_book_number;
        $cheque_book->currency = $request->currency;
        $cheque_book->start_serial_number = $request->start_serial_number;
        $cheque_book->end_serial_number = $request->end_serial_number;
        $cheque_book->status = $request->status;
        $cheque_book->notes = $request->notes;

        $changes = OperationLog::diff($cheque_book, [
            'bank_id', 'cheque_book_number', 'currency',
            'start_serial_number', 'end_serial_number', 'status',
        ]);

        $cheque_book->update();

        OperationLog::record('updated', 'Account.CheckBooks', $cheque_book,
            'عدّل دفتر الشيكات رقم '.$cheque_book->cheque_book_number, $changes);

        return redirect()->route('check_books.index')->with(['success' => 'تم تعديل دفتر الشيكات بنجاح']);
    }

    public function delete($id)
    {
        $cheque_book = ChequeBook::find($id);

        // نقرةٌ ثانية أو صفحةٌ قديمة — رسالةٌ مفهومة لا صفحة ٤٠٤ عارية
        if (! $cheque_book) {
            return redirect()->route('check_books.index')->with('error', 'دفتر الشيكات هذا غير موجود — ربما حُذف مسبقاً.');
        }

        $this->authorizeCreatorScope($cheque_book->created_by);

        if ($cheque_book->cheques()->count() > 0) {
            return redirect()->route('check_books.index')->with(['error' => 'لا يمكن حذف دفتر الشيكات لانه مستخدم بالفعل !!']);
        }
        $number = $cheque_book->cheque_book_number;
        $bookId = $cheque_book->id;

        $cheque_book->delete();

        OperationLog::record('deleted', 'Account.CheckBooks', $bookId,
            'حذف دفتر الشيكات رقم '.$number);

        // كانت رسالة النجاح تُبلَّغ بمفتاح `error`، فتخرج بلون الخطأ
        return redirect()->route('check_books.index')->with(['success' => 'تم حذف دفتر الشيكات بنجاح']);
    }
}
