<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceTransfer extends Model
{
    protected $table = 'invoice_transfers';

    protected $fillable = [
        'invoice_id',
        'from_employee_id',
        'to_employee_id',
        'client_id',
        'direction',
        'kind',
        'visit_id',
        'batch',
        'status',
        'sent_at',
        'responded_at',
        'sender_notes',
        'receiver_notes',
        'invoice_amount',
        'invoice_code',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
        'invoice_amount' => 'decimal:2',
    ];

    // الحالات المتاحة
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';

    /*
     * اتجاه التحويل — `from` من تخرج الفاتورة من عهدته و`to` من تدخلها في
     * الاتجاهين، فالقبول ينقلها إلى `to` دائماً. يختلف من يردّ ومن يُلغي:
     *   send    ← صاحبُ العهدة دفعها: يردّ `to` ويُلغي `from`
     *   request ← طلبها غيرُه: يردّ صاحبُ العهدة `from` ويُلغي الطالبُ `to`
     */
    const DIRECTION_SEND = 'send';
    const DIRECTION_REQUEST = 'request';

    /*
     * ما يُسلَّم: فاتورةٌ بعينها، أو رصيدُ حساب العميل كلُّه (من الأستاذ،
     * بعد الإشعارات الدائنة والسندات والرصيد الافتتاحي) — صفُّ الرصيد بلا
     * `invoice_id`، و`invoice_amount` فيه = الرصيد يوم التسليم.
     */
    const KIND_INVOICE = 'invoice';
    const KIND_BALANCE = 'balance';

    /**
     * العلاقة مع الفاتورة
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * العلاقة مع الموظف المرسل
     */
    public function fromEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_employee_id');
    }

    /**
     * العلاقة مع الموظف المستلم
     */
    public function toEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_employee_id');
    }

    /**
     * العلاقة مع العميل
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Scope للتحويلات المعلقة
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope للتحويلات المقبولة
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    /**
     * Scope للتحويلات المرفوضة
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope للتحويلات المرسلة من موظف معين
     */
    public function scopeFromEmployee($query, $employeeId)
    {
        return $query->where('from_employee_id', $employeeId);
    }

    /**
     * Scope للتحويلات المرسلة إلى موظف معين
     */
    public function scopeToEmployee($query, $employeeId)
    {
        return $query->where('to_employee_id', $employeeId);
    }

    /**
     * التحويلات التي ينتظر ردَّها هذا المستخدم (أيّاً كانت حالتها).
     */
    public function scopeRespondableBy($query, $userId)
    {
        return $query->where(fn ($q) => $q
            ->where(fn ($send) => $send->where('direction', '!=', self::DIRECTION_REQUEST)->where('to_employee_id', $userId))
            ->orWhere(fn ($req) => $req->where('direction', self::DIRECTION_REQUEST)->where('from_employee_id', $userId)));
    }

    /**
     * التحويلات التي بدأها هذا المستخدم — أرسلها أو طلبها.
     */
    public function scopeInitiatedBy($query, $userId)
    {
        return $query->where(fn ($q) => $q
            ->where(fn ($send) => $send->where('direction', '!=', self::DIRECTION_REQUEST)->where('from_employee_id', $userId))
            ->orWhere(fn ($req) => $req->where('direction', self::DIRECTION_REQUEST)->where('to_employee_id', $userId)));
    }

    public function isBalance(): bool
    {
        return $this->kind === self::KIND_BALANCE;
    }

    /** ما يُعرض مكان رقم الفاتورة */
    public function getSubjectLabelAttribute(): string
    {
        return $this->isBalance() ? 'رصيد العميل' : ($this->invoice_code ?: ($this->invoice->code ?? '—'));
    }

    public function isRequest(): bool
    {
        return $this->direction === self::DIRECTION_REQUEST;
    }

    /** المستخدم الذي يقبل أو يرفض */
    public function responderId(): int
    {
        return (int) ($this->isRequest() ? $this->from_employee_id : $this->to_employee_id);
    }

    /** المستخدم الذي بدأ التحويل، وهو من يملك إلغاءه */
    public function initiatorId(): int
    {
        return (int) ($this->isRequest() ? $this->to_employee_id : $this->from_employee_id);
    }

    public function getDirectionTextAttribute(): string
    {
        if ($this->isBalance()) {
            return 'تسليم رصيد';
        }

        return $this->isRequest() ? 'طلب استلام' : 'تسليم';
    }

    /**
     * التحقق إذا كان التحويل معلق
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * التحقق إذا كان التحويل مقبول
     */
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * التحقق إذا كان التحويل مرفوض
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * قبول التحويل
     */
    public function accept($notes = null): bool
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->responded_at = now();
        $this->receiver_notes = $notes;
        
        return $this->save();
    }

    /**
     * رفض التحويل
     */
    public function reject($notes = null): bool
    {
        $this->status = self::STATUS_REJECTED;
        $this->responded_at = now();
        $this->receiver_notes = $notes;
        
        return $this->save();
    }

    /**
     * الحصول على لون الحالة
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_ACCEPTED => 'success',
            self::STATUS_REJECTED => 'danger',
            default => 'secondary',
        };
    }

    /**
     * الحصول على نص الحالة بالعربية
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'معلق',
            self::STATUS_ACCEPTED => 'مقبول',
            self::STATUS_REJECTED => 'مرفوض',
            default => 'غير معروف',
        };
    }
}
