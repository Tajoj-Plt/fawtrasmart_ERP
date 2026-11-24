<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إرسالٌ واحد إلى هيئة الزكاة والضريبة والجمارك — حلقةٌ في سلسلتها.
 *
 * المستند الواحد قد يحمل أكثر من صفّ: رُفض فصُحِّح فأُعيد. والأخير هو
 * المعتبَر، والمرفوض يبقى لأنّ عدّاده وبصمته دخلا السلسلة.
 *
 * Usage:
 *   ZatcaDocument::latestFor('invoice', $invoice->id);
 */
class ZatcaDocument extends Model
{
    protected $table = 'zatca_documents';

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_CREDIT_NOTE = 'credit_note';

    /** وُقِّع وحُفظ ولم يبلغ الهيئة بعد — يُعاد إرساله كما هو */
    public const STATUS_PENDING = 'pending';

    /** مبسّطة أُبلغت */
    public const STATUS_REPORTED = 'reported';

    /** ضريبية اعتُمدت */
    public const STATUS_CLEARED = 'cleared';

    /** رفضتها الهيئة: لا يُعاد إرسالها، يُصحَّح المستند ويُرسَل من جديد */
    public const STATUS_REJECTED = 'rejected';

    /** تعذّر الاتصال بعد استنفاد المحاولات */
    public const STATUS_FAILED = 'failed';

    public const ACCEPTED = [self::STATUS_REPORTED, self::STATUS_CLEARED];

    /** بعدها يتوقّف الإرسال التلقائي وتبقى إعادة المحاولة يدوية */
    public const MAX_ATTEMPTS = 10;

    protected $fillable = [
        'document_type', 'document_id', 'document_number',
        'environment', 'profile', 'kind',
        'uuid', 'icv', 'invoice_hash', 'previous_hash',
        'qr', 'signed_xml', 'cleared_xml',
        'status', 'http_status', 'warnings', 'errors', 'last_error',
        'attempts', 'last_attempt_at', 'submitted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'warnings' => 'array',
            'errors' => 'array',
            'last_attempt_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /** آخر إرسالٍ لمستند في بيئةٍ بعينها، أو في أيّ بيئة. */
    public static function latestFor(string $type, int $id, ?string $environment = null): ?self
    {
        return static::query()
            ->where('document_type', $type)
            ->where('document_id', $id)
            ->when($environment, fn ($q) => $q->where('environment', $environment))
            ->orderByDesc('id')
            ->first();
    }

    public function isAccepted(): bool
    {
        return in_array($this->status, self::ACCEPTED, true);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canRetry(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_FAILED], true);
    }

    /** النسخة المعتمَدة من الهيئة إن وُجدت، وإلا ما وقّعناه. */
    public function finalXml(): string
    {
        return filled($this->cleared_xml) ? (string) $this->cleared_xml : (string) $this->signed_xml;
    }

    public function tone(): string
    {
        return match ($this->status) {
            self::STATUS_REPORTED, self::STATUS_CLEARED => empty($this->warnings) ? 'success' : 'warning',
            self::STATUS_PENDING => 'info',
            default => 'danger',
        };
    }

    public function document()
    {
        return $this->document_type === self::TYPE_CREDIT_NOTE
            ? $this->belongsTo(CreditNotification::class, 'document_id')
            : $this->belongsTo(Invoice::class, 'document_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
