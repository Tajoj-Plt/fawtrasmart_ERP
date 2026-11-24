<?php

namespace App\Services\Clients;

use App\Models\CreditNotification;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Visit;
use App\Models\VisitCompetitorObservation;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * بطاقة «ملاحظة الزيارة» — تبويبٌ واحد يجمع الملاحظة ورصد المنافسين في
 * شاشة العميل (`partials/tabs/visit-note-card`) وفي إدارة علاقات العملاء
 * (`crm_shared_scripts_details` ← `CRMController::getClientDetails()`).
 *
 * **بطاقةٌ واحدة لكل زيارة، لا لكل ملاحظة.** الفاتورة وسند القبض والمرتجع
 * والإشعار الدائن يكتب كلٌّ منها ملاحظةً تلقائية (`type = invoice`) في
 * `client_relations`؛ فكانت الزيارة الواحدة تظهر أربع بطاقات أو خمساً
 * تكرّر الموظف والتاريخ والعهدة. تُضمّ الآن كما تعرضها مساحة عمل الزيارة:
 * ملاحظةٌ واحدة وتحتها «مستندات هذه الزيارة».
 *
 * مفتاح الضمّ:
 *  1. زيارة الملاحظة — `visit_id` في رصد المنافسين المربوط بها، وإلّا النافذة
 *     الزمنية نفسها التي تستعملها مساحة العمل (`hasNoteSince()`)؛
 *  2. وإلّا الموظف نفسه في اليوم نفسه — كثيرٌ من الزيارات القديمة بلا
 *     `started_at` فلا نافذة لها، ومستندات يومها ما زالت تتكرّر.
 *
 * البطاقة مفتاحها معرّف **الملاحظة القائدة**: أوّل ملاحظةٍ يدوية في المجموعة
 * (هي ما يُعدَّل ويُطبع ويُحذف)، وإلّا أحدث ملاحظة. بقيّة الملاحظات لا بطاقة
 * لها — المُصيِّر يمرّ على {@see leadNotes()} لا على الملاحظات كلّها.
 *
 * رصدٌ بلا `client_relation_id` (سُجّل قبل الملاحظة) يُضمّ لبطاقة زيارته.
 *
 * خمسة استعلامات للصفحة كلّها مهما كان عدد الملاحظات.
 */
class VisitNoteCards
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    /** الملاحظات التلقائية التي تكتبها المستندات — نصّها يكرّر بيانات المستند */
    private const DOCUMENT_NOTE_TYPE = 'invoice';

    /** `process` الذي يكتبه `InvoiceTransferController::announce()` لملاحظات التسليم والاستلام */
    private const TRANSFER_PROCESSES = [
        'تسليم رصيد', 'تسليم فاتورة', 'طلب استلام فاتورة', 'استلام رصيد',
        'استلام فاتورة', 'رفض فاتورة', 'إلغاء تحويل فاتورة',
    ];

    private const PROCESS_LABELS =['visit_note' => 'ملاحظة زيارة', 'collection_note' => 'ملاحظة تحصيل'];

    /**
     * @return array<int, array> بطاقةٌ لكل زيارة، مفتاحها معرّف الملاحظة القائدة،
     *                           مرتّبةً بترتيب أحدث ملاحظةٍ فيها داخل `$notes`
     */
    public static function build(EloquentCollection $notes, int $clientId): array
    {
        if ($notes->isEmpty()) {
            return [];
        }

        $notes->loadMissing(['employee', 'clientEmployee', 'competitorObservations.competitor']);

        $times = $notes->pluck('created_at')->filter();
        $from = $times->isNotEmpty() ? $times->min()->copy()->subDay() : null;
        $to = $times->isNotEmpty() ? $times->max()->copy()->addDay() : null;

        $linkedVisitIds = $notes->flatMap(fn ($n) => $n->competitorObservations->pluck('visit_id'))
            ->filter()->unique()->values();

        $visits = ($from || $linkedVisitIds->isNotEmpty())
            ? Visit::where('client_id', $clientId)
                ->where(function ($q) use ($from, $to, $linkedVisitIds) {
                    if ($from) {
                        $q->whereBetween('started_at', [$from, $to]);
                    }
                    if ($linkedVisitIds->isNotEmpty()) {
                        $q->orWhereIn('id', $linkedVisitIds);
                    }
                })
                ->get(['id', 'employee_id', 'visit_number', 'stage', 'started_at', 'departure_time'])
            : collect();

        $unlinkedByVisit = $visits->isEmpty()
            ? collect()
            : VisitCompetitorObservation::with('competitor:id,name')
                ->whereIn('visit_id', $visits->pluck('id'))
                ->whereNull('client_relation_id')
                ->latest('id')
                ->get()
                ->groupBy('visit_id');

        $directInvoiceIds = $notes->map(fn ($n) => $n->invoice_id ?: $n->clientEmployee?->invoice_id)
            ->filter()->unique()->values();

        // المرتجع يصل بمعرّفه الصريح وحده؛ نافذة الزيارة لفواتير البيع فقط
        $invoices = ($from || $directInvoiceIds->isNotEmpty())
            ? Invoice::where('client_id', $clientId)
                ->where(function ($q) use ($from, $to, $directInvoiceIds) {
                    if ($from) {
                        $q->where(fn ($w) => $w->whereBetween('created_at', [$from, $to])
                            ->where(fn ($t) => $t->whereNull('type')->orWhere('type', '!=', 'returned')));
                    }
                    if ($directInvoiceIds->isNotEmpty()) {
                        $q->orWhereIn('id', $directInvoiceIds);
                    }
                })
                ->orderBy('created_at')
                ->get(['id', 'code', 'type', 'grand_total', 'created_by', 'created_at'])
            : collect();

        $receiptIds = $notes->map(fn ($n) => self::extra($n, 'receipt_id'))->filter()->unique()->values();
        $receipts = $receiptIds->isEmpty()
            ? collect()
            : Receipt::whereIn('id', $receiptIds)->get(['id', 'code', 'amount', 'created_at'])->keyBy('id');

        $creditIds = $notes->map(fn ($n) => self::extra($n, 'credit_notification_id'))->filter()->unique()->values();
        $credits = $creditIds->isEmpty()
            ? collect()
            : CreditNotification::whereIn('id', $creditIds)->get(['id', 'credit_number', 'grand_total', 'created_at'])->keyBy('id');

        $groups = [];
        $groupVisits = [];
        foreach ($notes as $note) {
            $visit = self::visitFor($note, $visits);
            // الموظف جزءٌ من المفتاح دائماً: مندوبٌ ثانٍ دخل الزيارة نفسها
            // ورصد عهدة المنافسين وصورهم تُحسب لملاحظته هو، ولا تُدمج في بطاقة غيره
            $key = $visit
                ? 'v'.$visit->id.'|e'.(int) $note->employee_id
                : 'd'.(int) $note->employee_id.'|'.(self::noteMoment($note)?->toDateString() ?? 'n'.$note->id);
            $groups[$key][] = $note;
            $groupVisits[$key] ??= $visit;
        }

        /* لا يُدمج إلّا ملاحظاتُ المستندات. كلُّ ملاحظةٍ مكتوبة بطاقةٌ وحدها:
           تسليمُ رصيدٍ ثم رفضُه ثم استلامُه ثلاثُ أحداثٍ لا حدثٌ واحد، وكانت
           تُضمّ في بطاقةٍ نصُّها مسرودٌ بـ« • ». ملاحظاتُ المستندات تبقى مع
           ملاحظة الزيارة (أوّل مكتوبةٍ ليست ملاحظةَ تحويل)، وإلّا في بطاقةٍ لها. */
        $position = $notes->values()->pluck('id')->flip();
        $moment = fn ($n) => self::noteMoment($n)?->getTimestamp() ?? 0;
        $units = [];
        foreach ($groups as $key => $groupMembers) {
            $groupMembers = collect($groupMembers);
            $isDocument = fn ($n) => $n->type === self::DOCUMENT_NOTE_TYPE;
            $anchor = $groupMembers->reject($isDocument)->sortBy($moment)
                ->first(fn ($n) => ! in_array($n->process, self::TRANSFER_PROCESSES, true));

            [$main, $alone] = $groupMembers->partition(
                fn ($n) => $isDocument($n) || ($anchor && $n->id === $anchor->id)
            );
            if ($main->isNotEmpty()) {
                $units[] = ['members' => $main->values(), 'visit' => $groupVisits[$key], 'main' => true];
            }
            foreach ($alone as $note) {
                $units[] = ['members' => collect([$note]), 'visit' => $groupVisits[$key], 'main' => false];
            }
        }
        usort($units, fn ($a, $b) => $a['members']->min(fn ($n) => $position[$n->id])
            <=> $b['members']->min(fn ($n) => $position[$n->id]));

        $cards = [];
        $order = 0;
        foreach ($units as $unit) {
            $members = $unit['members'];
            $statusVisit = $unit['visit'];
            // مستنداتُ نافذة الزيارة ورصدُها غيرُ المربوط لبطاقة الزيارة وحدها
            $visit = $unit['main'] ? $statusVisit : null;
            // الأقدم أولاً: ملاحظة الزيارة تسبق مستنداتها، والمستندات بترتيب صدورها
            $chronological = $members->sortBy(fn ($n) => self::noteMoment($n)?->getTimestamp() ?? 0)->values();
            $written = $chronological->reject(fn ($n) => $n->type === self::DOCUMENT_NOTE_TYPE);
            $lead = $written->first() ?? $members->first();

            $observations = $members->flatMap(fn ($n) => $n->competitorObservations)
                ->concat($visit
                    ? ($unlinkedByVisit[$visit->id] ?? collect())->where('employee_id', $lead->employee_id)
                    : collect())
                ->unique('id');

            $files = $chronological->flatMap(fn ($n) => self::attachmentFiles($n->attachments))->unique()->values();
            $isImage = fn ($f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
            $images = $files->filter($isImage)->map(fn ($f) => asset('assets/uploads/notes/'.$f))->values();
            $otherFiles = $files->reject($isImage)
                ->map(fn ($f) => ['name' => basename($f), 'url' => asset('assets/uploads/notes/'.$f)])
                ->values();

            // كلُّ رصدٍ بعهدته وصورته — لا يُسقَط منافسٌ تكرّر في ملاحظتين: عهدته
            // وصورته تختلفان من ملاحظةٍ إلى أخرى ولو كان المنافس نفسه
            $competitors = $observations->whereNotNull('competitor_id')->sortBy('id')
                ->map(fn ($o) => [
                    'name' => $o->display_name,
                    'asset_count' => (int) $o->asset_count,
                    'asset_label' => self::pieces((int) $o->asset_count),
                    'photo_url' => $o->photo_path ? asset('assets/uploads/visit_competitors/'.$o->photo_path) : null,
                ])
                ->values()
                ->all();

            /* صورةُ «لا يوجد منافس»: المندوب يصوّر الرفّ إثباتاً على أنه لا
               استاند لمنافسٍ عند العميل (`no_competitors_photo` في
               `VisitWorkspaceController::saveCompetitorObservations()`، تُحفظ
               على صفٍّ `competitor_id = null`). كانت تسقط لأن البطاقة تقرأ
               الرصد المرتبط بمنافسٍ وحده، فلا يبقى من الإثبات إلا كلمة
               «لا يوجد منافس». */
            $noCompetitorPhotos = $observations->whereNull('competitor_id')->sortBy('id')
                ->pluck('photo_path')
                ->filter()
                ->map(fn ($path) => asset('assets/uploads/visit_competitors/'.$path))
                ->values()
                ->all();

            // العهدة بعد آخر حركة: كل ملاحظة تلقائية تكتب الرصيد الجاري
            $custodyNote = $members->first(fn ($n) => $n->deposit_count !== null);
            $at = self::noteMoment($chronological->first());
            $process = $lead->process ?: null;

            $cards[$lead->id] = [
                'id' => $lead->id,
                'order' => $order++,
                'employee_id' => $lead->employee_id,
                'employee' => $lead->employee->name ?? 'غير معروف',
                'process' => $process ? (self::PROCESS_LABELS[$process] ?? $process) : null,
                'site_type' => $written->first(fn ($n) => $n->site_type)?->site_type_text,
                'description' => $written->pluck('description')->map(fn ($d) => trim((string) $d))
                    ->filter()->unique()->implode(' • '),
                'entries' => $members->count(),
                'note_ids' => $chronological->pluck('id')->all(),
                'date_label' => $at?->copy()->locale('ar')->translatedFormat('j F Y'),
                'timestamp' => $at?->getTimestamp(),
                'time_label' => $at ? $at->format('h:i').' '.($at->format('A') === 'AM' ? 'ص' : 'م') : null,
                'visit' => self::visitStatus($statusVisit),
                'documents' => self::documentsFor($chronological, $visit, $invoices, $receipts, $credits),
                'competitors' => $competitors,
                // صفُّ «لا يوجد منافس» شاهدٌ على أن الرصد تمّ ولم يوجد أحد —
                // غيرُ «لم يُرصد» أصلاً
                'no_competitor' => $competitors === [] && $observations->isNotEmpty(),
                'no_competitor_photos' => $noCompetitorPhotos,
                'custody' => [
                    'count' => $custodyNote ? (int) $custodyNote->deposit_count : null,
                    'label' => self::pieces((int) $custodyNote?->deposit_count),
                    'photo_url' => $images->first(),
                ],
                'images' => $images->all(),
                'files' => $otherFiles->all(),
            ];
        }

        return $cards;
    }

    /**
     * الملاحظات القائدة بترتيب البطاقات — ما يمرّ عليه المُصيِّر ويُرقَّم.
     */
    public static function leadNotes(EloquentCollection $notes, array $cards): EloquentCollection
    {
        $byId = $notes->keyBy('id');

        return new EloquentCollection(
            collect(array_keys($cards))->map(fn ($id) => $byId[$id] ?? null)->filter()->values()->all()
        );
    }

    /**
     * «قطعة / قطعتان / قطع» بحسب العدد. لا `trans_choice`: صيغتا العربية فيه
     * تُظهران المفرد لأغلب الأعداد.
     */
    public static function pieces(int $count): string
    {
        $mod100 = $count % 100;

        return match (true) {
            $count === 2 => 'قطعتان',
            $mod100 >= 3 && $mod100 <= 10 => 'قطع',
            default => 'قطعة',
        };
    }

    private static function visitFor($note, Collection $visits): ?Visit
    {
        $linked = $note->competitorObservations->pluck('visit_id')->filter()->first();
        if ($linked && ($visit = $visits->firstWhere('id', $linked))) {
            return $visit;
        }

        $at = $note->created_at;
        if (! $at) {
            return null;
        }

        return $visits
            ->filter(fn ($v) => (int) $v->employee_id === (int) $note->employee_id
                && $v->started_at
                && $v->started_at->lte($at)
                && ($v->departure_time ? $at->lte($v->departure_time) : $v->started_at->isSameDay($at)))
            ->sortByDesc('started_at')
            ->first();
    }

    /**
     * كانت تقرأ `$visit->stage ?: (...)`: عمود `stage` افتراضُه `'arrived'` في
     * القاعدة (لا `null`)، فزيارةٌ غادَرَت عبر المغادرة التلقائية
     * (`ProcessAutoDepartures`) أو «إخفاء من الخريطة» — كلاهما يكتب
     * `departure_time` بلا تقديم `stage` — يبقى `stage` عندها `arrived` إلى
     * الأبد، فالمطابقة تسقط في `default` وتُعرض «جارية» ولو انتهت الزيارة
     * منذ أيام. `Visit::isCompleted()` هي الحارس الصحيح: تقبل `departure_time`
     * وحده انتهاءً، بصرف النظر عن قيمة `stage`.
     */
    private static function visitStatus(?Visit $visit): ?array
    {
        if (! $visit) {
            return null;
        }

        if ($visit->stage === Visit::STAGE_CANCELLED) {
            return ['label' => 'ملغاة', 'tone' => 'cancel', 'icon' => 'fa-times-circle', 'number' => $visit->visit_number];
        }

        if ($visit->isCompleted()) {
            return ['label' => 'تمت الزيارة', 'tone' => 'done', 'icon' => 'fa-check-circle', 'number' => $visit->visit_number];
        }

        return ['label' => 'جارية', 'tone' => 'live', 'icon' => 'fa-clock', 'number' => $visit->visit_number];
    }

    /**
     * مستندات البطاقة بترتيب صدورها: ما ربطته ملاحظاتها صراحةً (فاتورة، مرتجع،
     * سند قبض، إشعار دائن)، ثم فواتير مندوب الزيارة داخل نافذتها — نفس نافذة
     * `VisitWorkspaceController::visitDocuments()` — لزياراتٍ لم تكتب ملاحظة
     * تلقائية. `tone` = `in` للبيع والتحصيل، `out` لما يُنقص حقّنا.
     *
     * **المستند من يوم الملاحظة وحده** ({@see sameDayAsNote()}): بلا هذا الشرط
     * كان `client_employee_notes.invoice_id` — فاتورةُ الزيارة التي سُجّل فيها
     * مسؤولُ العميل أوّلَ مرّة — يظهر في **كل** ملاحظةٍ تشير إلى ذلك المسؤول
     * بعدها؛ فعميلٌ لم يُصدَر له مستندٌ في زيارته يُعرض له أوّلُ مستندٍ صدر له
     * قبل أشهر. الملاحظة بلا مستندٍ في يومها تُظهر «لم يتم إنشاء مستند».
     *
     * @return array<int, array{kind: string, icon: string, tone: string, number: string, amount: ?float, url: string}>
     */
    private static function documentsFor(Collection $members, ?Visit $visit, Collection $invoices, Collection $receipts, Collection $credits): array
    {
        $docs = [];
        $addInvoice = function ($invoice) use (&$docs) {
            $returned = $invoice->type === 'returned';
            $docs['i'.$invoice->id] = [
                'kind' => $returned ? 'مرتجع مبيعات' : 'فاتورة بيع',
                'icon' => $returned ? 'fa-undo' : 'fa-file-invoice',
                'tone' => $returned ? 'out' : 'in',
                'number' => $invoice->code ?: (string) $invoice->id,
                'amount' => (float) $invoice->grand_total,
                'url' => route($returned ? 'ReturnIInvoices.show' : 'invoices.show', $invoice->id),
                'at' => $invoice->created_at,
            ];
        };

        foreach ($members as $note) {
            // فاتورةُ الملاحظة نفسها رابطٌ صريحٌ كتبه المستند عند إصداره فيُقبل
            // كما هو؛ أمّا فاتورةُ **مسؤول العميل** فمشتركةٌ بين كل ملاحظاته
            // فلا تُقبل إلّا في يومها
            $invoice = ($note->invoice_id ? $invoices->firstWhere('id', (int) $note->invoice_id) : null)
                ?: self::contactInvoice($note, $invoices);
            if ($invoice) {
                $addInvoice($invoice);
            }
            if (($receipt = $receipts[(int) self::extra($note, 'receipt_id')] ?? null) && self::sameDayAsNote($receipt->created_at, $note)) {
                $docs['r'.$receipt->id] = [
                    'kind' => 'سند قبض', 'icon' => 'fa-money-bill-wave', 'tone' => 'in',
                    'number' => $receipt->code ?: (string) $receipt->id,
                    'amount' => (float) $receipt->amount,
                    'url' => route('incomes.show', $receipt->id),
                    'at' => $receipt->created_at,
                ];
            }
            if (($credit = $credits[(int) self::extra($note, 'credit_notification_id')] ?? null) && self::sameDayAsNote($credit->created_at, $note)) {
                $docs['c'.$credit->id] = [
                    'kind' => 'إشعار دائن', 'icon' => 'fa-file-invoice-dollar', 'tone' => 'out',
                    'number' => $credit->credit_number ?: (string) $credit->id,
                    'amount' => (float) $credit->grand_total,
                    'url' => route('CreditNotes.show', $credit->id),
                    'at' => $credit->created_at,
                ];
            }
        }

        if ($visit && $visit->started_at) {
            $end = $visit->departure_time ?: $visit->started_at->copy()->endOfDay();
            $invoices->filter(fn ($inv) => $inv->type !== 'returned'
                && (int) $inv->created_by === (int) $visit->employee_id
                && $inv->created_at
                && $inv->created_at->between($visit->started_at, $end))
                ->each(fn ($inv) => isset($docs['i'.$inv->id]) || $addInvoice($inv));
        }

        return collect($docs)
            ->sortBy(fn ($d) => $d['at']?->getTimestamp() ?? 0)
            ->map(fn ($d) => collect($d)->except('at')->all())
            ->values()
            ->all();
    }

    /**
     * فاتورةُ مسؤول العميل (`client_employee_notes.invoice_id`) — تُقبل في يوم
     * الملاحظة وحده.
     */
    private static function contactInvoice($note, Collection $invoices): mixed
    {
        $id = $note->clientEmployee?->invoice_id;
        $invoice = $id ? $invoices->firstWhere('id', (int) $id) : null;

        return $invoice && self::sameDayAsNote($invoice->created_at, $note) ? $invoice : null;
    }

    /**
     * هل صدر هذا المستند في يوم الملاحظة؟
     *
     * يومان مقبولان: لحظةُ إنشاء الملاحظة (وهي لحظةُ إصدار المستند نفسه حين
     * تكون الملاحظة تلقائية) و{@see noteMoment()} — التاريخُ المكتوب في
     * الملاحظة حين يُسجَّل بأثرٍ رجعي. مستندٌ خارجهما ليس مستندَ هذه الملاحظة.
     */
    private static function sameDayAsNote(?Carbon $documentAt, $note): bool
    {
        if (! $documentAt) {
            return false;
        }

        $day = $documentAt->toDateString();

        return $day === $note->created_at?->toDateString()
            || $day === self::noteMoment($note)?->toDateString();
    }

    /**
     * مفتاحٌ من `additional_data`. الملاحظات القديمة خزّنته نصّاً مُرمَّزاً
     * مرّتين فيصل سلسلةً لا مصفوفة — لا مستند فيها أصلاً.
     */
    private static function extra($note, string $key): ?int
    {
        $data = $note->additional_data;

        return is_array($data) && ! empty($data[$key]) ? (int) $data[$key] : null;
    }

    /** تاريخ الملاحظة ووقتها كما كُتبا فيها، وإلّا لحظةُ إنشائها. */
    private static function noteMoment($note): ?Carbon
    {
        if ($note->date) {
            try {
                return Carbon::parse(trim($note->date.' '.($note->time ?: $note->created_at?->format('H:i:s'))));
            } catch (\Throwable) {
                // تاريخٌ مكتوبٌ بصيغةٍ غير مفهومة — لحظة الإنشاء أصدق من لا شيء
            }
        }

        return $note->created_at;
    }

    private static function attachmentFiles($attachments): array
    {
        if (is_array($attachments)) {
            return array_values(array_filter($attachments, 'is_string'));
        }
        if (! is_string($attachments) || $attachments === '') {
            return [];
        }

        $decoded = json_decode($attachments, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [$attachments];
    }
}
