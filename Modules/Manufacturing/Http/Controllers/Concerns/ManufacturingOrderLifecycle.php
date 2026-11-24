<?php

namespace Modules\Manufacturing\Http\Controllers\Concerns;

use App\Enums\ManufacturOrderStatus;
use App\Enums\ReferenceType;
use App\Models\JournalEntry;
use App\Models\ManufacturOrders;
use App\Models\ManufacturOrdersItem;
use App\Models\Product;
use App\Models\StoreHouse;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Support\OperationLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Constants\ManufacturingSources;
use Modules\Manufacturing\Services\InventoryService;
use Modules\Manufacturing\Services\ManufacturingJournalService;
use Modules\Manufacturing\Services\ReservationService;

/**
 * دورة حياة أمر التصنيع — سبع عمليات بنمطٍ واحد.
 *
 * ── النمط ────────────────────────────────────────────────────────────
 *
 * كل عملية تمرّ بثمانية عناصر بالترتيب نفسه:
 *
 *   ١. الفترة المحاسبية   accountingPeriodBlock()
 *   ٢. التحقق من المدخلات validate()
 *   ٣. الفرع              authorize()          ← بعد تفعيل Policies
 *   ٤. آلة الحالات        assertCanTransitionTo()
 *   ٥. الإذن المخزني      بمصدر صحيح ورقم من الإعدادات وعملة المستند
 *   ٦. المخزون            InventoryService — ذرّي وبمتوسط مرجّح
 *   ٧. القيد              ManufacturingJournalService — توازن مؤكد
 *   ٨. السجل              OperationLog — الأنشطة والأحداث والأخطاء معاً
 *
 * ── التركيب ──────────────────────────────────────────────────────────
 *
 * في ManufacturingOrdersController:
 *
 *   ١. أضف السمة إلى سطر `use` داخل الصنف:
 *
 *        use GeneratesDocumentNumbers,
 *            ManufacturingOrderLifecycle,   // ← جديدة
 *            RecalculatesTotals,
 *            ResolvesAccountingAccounts,
 *            RespectsAccountingPeriods,
 *            SafeListing,
 *            UsesDocumentCurrency;
 *
 *   ٢. احذف من المتحكّم الدوال التي تُستبدَل هنا:
 *        receiveMaterials, returnMaterials, completeOrder, undoCompletion,
 *        cancelOrder, closeOrder, reopenOrder, destroy, processWasteMaterials,
 *        createManufacturingJournalEntries, createWarehousePermitForOrder,
 *        createMaterialsReceiveJournalEntry, createMaterialsReturnJournalEntry
 *
 *   ٣. واحذف الميتة معها:
 *        finish, finishOrder, finishOrderWithPermitCleanup,
 *        finishOrderWithSelectivePermitDeletion, deleteOldWarehousePermit,
 *        addProductDetailsForManufacturedProduct, addWasteMaterialsToProductDetails,
 *        removeProductDetailsForManufacturingOrder, getCostBredown,
 *        calculateCostFromProductDetails, calculateCostFromPurchaseHistory,
 *        calculateCostAlternativeMethod, calculateProductTotal,
 *        addWasteMaterialsToSamePermit, recordInventoryMovements,
 *        recordProductMovement, reverseInventoryMovement,
 *        deleteRelatedStockMovements, getProductStockBefore,
 *        updateProductInventory, createJournalEntry (الكتلة المعلّقة)
 *
 * ── متطلّب في الموديل ────────────────────────────────────────────────
 *
 * `App\Models\ManufacturOrders::$fillable` **ينقصه `warehouse_permit_id`**.
 * `completeOrder` القديمة تكتبه فيُهمَل صامتاً بحماية الإسناد الجماعي —
 * وهذا هو السبب الحقيقي لعجز `undoCompletion` عن إيجاد الإذن، لا ما ظننتُه
 * أولاً. أضفه:
 *
 *     protected $fillable = [ …, 'warehouse_permit_id', … ];
 *
 * وأضف الـ cast للحالة:
 *
 *     protected $casts = ['status' => \App\Enums\ManufacturOrderStatus::class];
 */
trait ManufacturingOrderLifecycle
{
    // ═════════════════════════════════════════════════════════════════
    // ١) استلام المواد الخام
    // ═════════════════════════════════════════════════════════════════

    /**
     * صرف المواد الخام من المستودع إلى الإنتاج.
     *
     * ما تغيّر عن السابق:
     *  · المصدر 17 (صرف مواد) بدل 15 (تحويل لنقطة البيع)
     *  · الرقم من إعدادات الترقيم بدل `'MFG-'.$code.'-'.time()`
     *  · الخصم ذرّي — لا فجوة بين الفحص والخصم (TOCTOU)
     *  · القيد إلزامي — كان يُبتلع فيبقى المخزون منقوصاً بلا دفتر
     *  · العملة من المستند بدل إهمالها
     */
    public function receiveMaterials(Request $request, $id)
    {
        if ($block = $this->accountingPeriodBlock(now())) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        $request->validate([
            'storehouse_id' => 'required|exists:store_houses,id',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $order = $this->lockOrder($id, ['manufacturOrdersItem']);
            $this->authorizeOrder($order, 'update');

            $status = $this->statusOf($order);
            $status->assertCanTransitionTo(ManufacturOrderStatus::MaterialsReceived);

            $warehouseId = (int) $request->storehouse_id;
            $storehouse = $this->lockedStorehouse($warehouseId, $order);
            $inv = app(InventoryService::class);

            /*
                بنود المواد الخام — **بتكلفة المخزون لا بسعر البند**.

                كان السعر يُؤخذ من `raw_unit_price`، وهو تقديرٌ يكتبه
                المستخدم في شاشة الأمر. فتُبنى بنودُ الإذن به، ويجمعها
                `sealPermit`، ويُرحَّل مجموعُها في القيد.

                والنتيجة أن المخزون والدفتر يفترقان: مادةٌ تكلفتها ٨
                وسعرُ بندها ١٢ تُخرج من المخزون قيمةَ ١٠×٨=٨٠ وتُقيَّد
                في الدفتر ١٠×١٢=١٢٠. فرقُ ٤٠ بلا مقابل، يتراكم مع كل أمر.

                القاعدة: القيمة الخارجة من المخزون = القيمة في القيد.
                فالسعرُ المعتمَد هو `InventoryService::unitCost()` —
                المتوسّط المرجّح المخزَّن لحظةَ الصرف.

                والتقديريّ لا يُمحى: يُحفظ إلى جانب الفعلي في البند
                ليُحسب انحرافُ السعر ويُعرض في التقارير.
            */
            $rawItems = $order->manufacturOrdersItem
                ->filter(fn ($i) => $i->raw_product_id && $i->raw_quantity > 0)
                ->values();

            /*
                الكميةُ بالوحدة الأساسية.

                البندُ قد يُكتب بوحدةٍ كبرى (كرتون = ١٢ حبة)، والمخزونُ
                يُمسَك بالأساسية. كان الصرفُ والحجز يأخذان `raw_quantity`
                كما هي، فمن طلب ٢ كرتون صُرفت له حبّتان. المعاملُ محفوظٌ
                مع البند (`raw_conversion_factor`) فيُضرب هنا.
            */
            $rawLines = $rawItems
                ->map(fn ($i) => [
                    'item_id' => (int) $i->id,
                    'product_id' => (int) $i->raw_product_id,
                    'quantity' => $this->baseQuantity($i),
                    'factor' => $this->conversionFactor($i),
                    'estimated_price' => (float) ($i->raw_unit_price ?? 0),
                    'unit_price' => $inv->unitCost((int) $i->raw_product_id, $warehouseId),
                ])
                ->all();

            if ($rawLines === []) {
                DB::rollBack();

                return redirect()->back()->with('error', 'لا مواد خام في هذا الأمر — أضف بنوداً قبل استلام المواد.');
            }

            /*
                الفحصُ بالمتاح لا بالرصيد الفعلي.

                غيرُ هذا الأمر قد حجز كمياتٍ لم تُصرف بعد. فالرصيدُ يقول
                ١٠٠ بينما ٩٠ منها موعودةٌ لأمرٍ آخر — والصرفُ من العشرة
                الباقية وحدها. وحجزُ هذا الأمر يُستثنى، وإلا منع نفسَه من
                أخذ ما حجزه.
            */
            // المادةُ الواحدة في أكثر من سطرٍ تُجمَع ثمّ تُفحَص مرّةً واحدة —
            // `shortages()` تجمع بالمنتج، فلا يمرّ سطران كلٌّ منهما يكفيه
            // الرصيد وحدَه ومجموعُهما يزيد عليه
            $shortages = app(ReservationService::class)
                ->shortages($rawLines, $warehouseId, (int) $order->id);

            if ($shortages !== []) {
                DB::rollBack();

                $msg = "لا يمكن سحب أكثر من الكمية الموجودة في «{$storehouse->name}»:\n\n";
                foreach ($shortages as $s) {
                    $msg .= "• {$s['name']}: المطلوب {$s['required']}، المتوفر {$s['available']}، النقص {$s['shortage']}\n";
                }

                return redirect()->back()
                    ->with('error', $msg)
                    ->with('insufficient_materials', $shortages);
            }

            $permit = $this->createPermit(
                order: $order,
                warehouseId: $warehouseId,
                type: ManufacturingSources::TYPE_ISSUE,
                source: ManufacturingSources::PERMIT_ISSUE_MATERIALS,
                section: ManufacturingSources::SECTION_PERMIT_ISSUE,
                details: $request->notes ?: 'صرف مواد خام لأمر التصنيع: '.$order->name.' من مستودع: '.$storehouse->name
            );

            foreach ($rawLines as $line) {
                $inv->addPermitLine(
                    $permit,
                    $line['product_id'],
                    $line['quantity'],
                    $line['unit_price'],
                    'issue',
                    $warehouseId
                );

                /*
                    تثبيتُ التكلفة الفعلية على البند.

                    تكلفةُ المخزون تتغيّر مع كل شراء، فقراءتُها لاحقاً
                    تعطي رقماً غير الذي صُرف به. تُجمَّد هنا لحظةَ الصرف
                    كي يبقى القيدُ والبند والتقريرُ على رقمٍ واحد.
                */
                ManufacturOrdersItem::whereKey($line['item_id'])->update([
                    // سعرُ وحدة **البند** (الأساسية × المعامل) كي يبقى
                    // الكمية × السعر = الإجمالي في شاشة الأمر
                    'raw_actual_unit_price' => round($line['unit_price'] * $line['factor'], 6),
                    'raw_actual_total' => round($line['quantity'] * $line['unit_price'], 4),
                ]);
            }

            $total = $inv->sealPermit($permit);

            /*
                الحجزُ صار حركةَ مخزون.

                لولا هذا لبقي نشطاً بعد الصرف، فتُخصم الكميةُ من المتاح
                مرّتين: مرّةً بالحركة الفعلية ومرّةً بالوعد الذي لم يُفكّ.
            */
            app(ReservationService::class)->consume($order);

            // القيد: مخزون تحت التشغيل مدين، مخزون المواد الخام دائن
            if ($total > 0) {
                app(ManufacturingJournalService::class)->post(
                    lines: [
                        [
                            'key' => ManufacturingSources::ACCOUNT_WIP,
                            'debit' => $total,
                            'description' => 'صرف مواد خام — أمر '.$order->code,
                        ],
                        // الدائنُ حسابُ المستودع الذي خرجت منه المواد — لا حسابٌ ثابت
                        $this->inventoryAccountLine($warehouseId, ManufacturingSources::ACCOUNT_RAW_MATERIALS) + [
                            'credit' => $total,
                            'description' => 'من مستودع '.$storehouse->name,
                        ],
                    ],
                    referenceId: $order->id,
                    description: 'صرف مواد خام لأمر التصنيع: '.$order->name.' (#'.$order->id.')',
                    date: now(),
                    currency: $this->documentCurrency($order),
                    clientId: $order->client_id,
                    employeeId: $order->employee_id,
                    stage: ManufacturingSources::STAGE_ISSUE,
                );
            }

            $order->update([
                'status' => ManufacturOrderStatus::MaterialsReceived,
                'storehouse_id' => $warehouseId,
                'materials_received_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            OperationLog::record(
                'materials_received',
                ManufacturingSources::LOG_ORDER,
                $order,
                'تم استلام مواد أمر التصنيع **'.$order->name.'** — إذن صرف رقم '.$permit->number
            );

            DB::commit();

            return redirect()->route('manufacturing.orders.show', $order->id)
                ->with('success', 'تم استلام المواد بنجاح — إذن الصرف رقم '.$permit->number);

        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'receiveMaterials', $id);

            return redirect()->back()->withInput()->with('error', $this->lifecycleFailure($e, 'تعذّر استلام المواد.'));
        }
    }

    // ═════════════════════════════════════════════════════════════════
    // ٢) إرجاع المواد
    // ═════════════════════════════════════════════════════════════════

    /**
     * إرجاع مواد خام غير مستهلكة إلى المستودع.
     *
     * ما تغيّر:
     *  · سقف للكمية — كان بلا حدّ أعلى فأمكن خلق مخزون من العدم
     *  · تحقق أن المنتج ضمن بنود الأمر — كان يمضي بسعر صفر لأي منتج
     *  · تراكم ما أُرجع سابقاً — كان يمكن الإرجاع مراراً
     *  · الحالة لا تعود «جديد» إلا بإرجاع كل شيء — كان الإرجاع الجزئي
     *    يفتح باب استلام ثانٍ يخصم كامل المواد مرة أخرى
     */
    public function returnMaterials(Request $request, $id)
    {
        if ($block = $this->accountingPeriodBlock(now())) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        $request->validate([
            'storehouse_id' => 'required|exists:store_houses,id',
            'return_items' => 'required|array|min:1',
            'return_items.*.product_id' => 'required|integer|exists:products,id',
            /*
                الصفُّ الفارغ أو الصفريّ مقبول — يُتخطّى.

                كانت القاعدة `required|min:0.0001` على **كل** صفّ، والشاشةُ
                تعرض المواد كلَّها بقيمةٍ ابتدائية صفر. فمن أراد إرجاع مادةٍ
                واحدة وترك الباقي رُدّ طلبُه — وبلا رسالةٍ تُعرض. الآن تُرجَع
                المادةُ التي كُتبت كميّتُها وحدها.
            */
            'return_items.*.quantity' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ], [
            'storehouse_id.required' => 'اختر المستودع الذي تُرجَع إليه المواد.',
            'return_items.required' => 'لا مواد للإرجاع.',
            'return_items.*.quantity.numeric' => 'المواد — الصف :position: الكمية المُرجَعة يجب أن تكون رقماً.',
            'return_items.*.quantity.min' => 'المواد — الصف :position: الكمية المُرجَعة لا تكون سالبة.',
        ]);

        DB::beginTransaction();

        try {
            $order = $this->lockOrder($id, ['manufacturOrdersItem']);
            $this->authorizeOrder($order, 'update');

            $status = $this->statusOf($order);

            if (! $status->canReturnMaterials()) {
                DB::rollBack();

                return redirect()->back()->with(
                    'error',
                    'لا يمكن إرجاع المواد وأمر التصنيع في حالة «'.$status->label().'».'
                );
            }

            $warehouseId = (int) $request->storehouse_id;
            $storehouse = $this->lockedStorehouse($warehouseId, $order);

            // ما صُرف فعلاً لكل مادة — بالوحدة الأساسية وبتكلفته الفعلية،
            // مجموعاً بالمنتج (المادةُ قد ترد في أكثر من سطر)
            $issued = $this->issuedByProduct($order->manufacturOrdersItem);

            // ما أُرجع سابقاً — من إذونات الإرجاع القائمة لهذا الأمر
            $returnedBefore = $this->returnedSoFar($order);

            // ── التحقق قبل أي كتابة ──
            $errors = [];
            $accepted = [];

            foreach ($request->return_items as $item) {
                $pid = (int) $item['product_id'];
                $qty = (float) ($item['quantity'] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                if (! isset($issued[$pid])) {
                    $errors[] = 'المنتج «'.$this->productName($pid).'» ليس ضمن مواد هذا الأمر.';

                    continue;
                }

                $issuedQty = (float) $issued[$pid]['quantity'];
                $already = (float) ($returnedBefore[$pid] ?? 0);
                $remaining = $issuedQty - $already;

                if ($qty > $remaining + 0.0001) {
                    $errors[] = sprintf(
                        'المنتج «%s»: المتاح للإرجاع %s فقط (صُرف %s، أُرجع سابقاً %s).',
                        $this->productName($pid),
                        rtrim(rtrim(number_format($remaining, 4, '.', ''), '0'), '.'),
                        rtrim(rtrim(number_format($issuedQty, 4, '.', ''), '0'), '.'),
                        rtrim(rtrim(number_format($already, 4, '.', ''), '0'), '.')
                    );

                    continue;
                }

                $accepted[] = [
                    'product_id' => $pid,
                    'quantity' => $qty,
                    /*
                        بالتكلفة التي خرجت بها بالضبط — لا بسعر البند.

                        كان الإرجاع يُسعَّر بـ`raw_unit_price`، وهو تقديرٌ
                        يكتبه المستخدم. فمادةٌ صُرفت بـ٨ ورجعت بـ١٢ تُدخل
                        المخزونَ قيمةً لم تخرج منه، ويُقيَّد في «تحت التشغيل»
                        دائنٌ أكبرُ من مدينه — وهو ما كان يترك رصيدَ الأمر
                        سالباً بعد الإلغاء.
                    */
                    'unit_price' => (float) $issued[$pid]['unit_cost'],
                ];
            }

            if ($errors !== []) {
                DB::rollBack();

                return redirect()->back()->withInput()
                    ->with('error', "تعذّر إرجاع المواد:\n\n• ".implode("\n• ", $errors));
            }

            if ($accepted === []) {
                DB::rollBack();

                return redirect()->back()->withInput()->with(
                    'error',
                    'لم تُحدَّد أي كمية للإرجاع — اكتب الكمية أمام المادة التي تريد إرجاعها (مادة واحدة تكفي).'
                );
            }

            // ── التنفيذ ──
            $inv = app(InventoryService::class);

            $permit = $this->createPermit(
                order: $order,
                warehouseId: $warehouseId,
                type: ManufacturingSources::TYPE_ADD,
                source: ManufacturingSources::PERMIT_RETURNED_MATERIALS,
                section: ManufacturingSources::SECTION_PERMIT_ADD,
                details: $request->notes ?: 'إرجاع مواد خام من أمر التصنيع: '.$order->name
            );

            foreach ($accepted as $line) {
                $inv->addPermitLine(
                    $permit,
                    $line['product_id'],
                    $line['quantity'],
                    $line['unit_price'],
                    'receive',
                    $warehouseId
                );
            }

            $total = $inv->sealPermit($permit);

            // القيد العكسي لصرف المواد: المواد الخام مدين، WIP دائن
            if ($total > 0) {
                app(ManufacturingJournalService::class)->post(
                    lines: [
                        $this->inventoryAccountLine($warehouseId, ManufacturingSources::ACCOUNT_RAW_MATERIALS) + [
                            'debit' => $total,
                            'description' => 'إرجاع مواد إلى '.$storehouse->name,
                        ],
                        [
                            'key' => ManufacturingSources::ACCOUNT_WIP,
                            'credit' => $total,
                            'description' => 'من أمر التصنيع '.$order->code,
                        ],
                    ],
                    referenceId: $order->id,
                    description: 'إرجاع مواد من أمر التصنيع: '.$order->name.' (#'.$order->id.')',
                    date: now(),
                    currency: $this->documentCurrency($order),
                    clientId: $order->client_id,
                    employeeId: $order->employee_id,
                    stage: ManufacturingSources::STAGE_RETURN,
                );
            }

            // ── الحالة: لا تعود «جديد» إلا بإرجاع كل شيء ──
            $stillOut = false;

            foreach ($issued as $pid => $item) {
                $returnedNow = collect($accepted)->where('product_id', (int) $pid)->sum('quantity');
                $outstanding = (float) $item['quantity']
                    - (float) ($returnedBefore[$pid] ?? 0)
                    - (float) $returnedNow;

                if ($outstanding > 0.0001) {
                    $stillOut = true;
                    break;
                }
            }

            if ($stillOut) {
                $order->update(['updated_by' => auth()->id()]);
                $note = 'إرجاع جزئي — الأمر باقٍ في حالته.';
            } else {
                $order->update([
                    'status' => ManufacturOrderStatus::Pending,
                    'materials_received_at' => null,
                    'updated_by' => auth()->id(),
                ]);

                /*
                    دورةُ الصرف أُقفلت: صُرف ثمّ أُرجع كلُّه.

                    أذونُ هذه الدورة تُوسَم «مُسوّاة» كي لا تُحتسب على
                    استلامٍ لاحق: بدونها يرى الاستلامُ الثاني أن موادَّه
                    «أُرجعت كلُّها سلفاً» فلا يُرجَع منه شيء، ويُطرح مرتجعُ
                    الدورة الأولى من تكلفة الثانية.
                */
                $this->settleMaterialPermits($order);

                // التكلفةُ الفعلية المثبَّتة على البنود تخصّ الصرفَ المُرجَع
                ManufacturOrdersItem::where('manufactur_order_id', $order->id)
                    ->whereNotNull('raw_product_id')
                    ->update(['raw_actual_unit_price' => null, 'raw_actual_total' => null]);

                $note = 'أُرجعت كل المواد — عاد الأمر إلى «جديد».';
            }

            OperationLog::record(
                'materials_returned',
                ManufacturingSources::LOG_ORDER,
                $order,
                'تم إرجاع مواد من أمر التصنيع **'.$order->name.'** — إذن رقم '.$permit->number.'. '.$note
            );

            // حالةُ خطّة الإنتاج تتبع أوامرَها — لا أثرَ لأمرٍ لم يولَّد من خطّة
            \App\Models\ProductionPlan::syncStatusForOrder($order);

            DB::commit();

            return redirect()->route('manufacturing.orders.show', $order->id)
                ->with('success', 'تم إرجاع المواد بنجاح. '.$note);

        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'returnMaterials', $id);

            return redirect()->back()->withInput()->with('error', $this->lifecycleFailure($e, 'تعذّر إرجاع المواد.'));
        }
    }

    // ═════════════════════════════════════════════════════════════════
    // ٣) إتمام أمر التصنيع — القلب
    // ═════════════════════════════════════════════════════════════════

    /**
     * إنتاج المنتج التام وإدخاله المستودع، بستة قيود يُقفل سادسها WIP.
     *
     * ما تغيّر:
     *  · `\Log::` بدل `Log::` — كان يُسقط الدالة كلها بخطأ 500 دائم
     *  · المصدر 3 (منتج رئيسي) و4 (هالك) بدل 15 (نقطة البيع)
     *  · التكلفة من البنود لا من `last_total_cost` القادم من الواجهة
     *  · **قيد الانحراف** — بدونه لا يصفر WIP أبداً
     *  · متوسط مرجّح بدل الكتابة فوق `unit_price`
     *  · `actual_quantity` يُحفظ — كان يضيع
     *  · `warehouse_permit_id` يُحفظ فعلاً (بعد إضافته إلى $fillable)
     */
    public function completeOrder(Request $request, $id)
    {
        if ($block = $this->accountingPeriodBlock(now())) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        $request->validate([
            'storehouse_id' => 'required|exists:store_houses,id',
            'waste_storehouse_id' => 'nullable|exists:store_houses,id',
            'actual_quantity' => 'nullable|numeric|min:0.0001',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $order = $this->lockOrder($id, ['manufacturOrdersItem']);
            $this->authorizeOrder($order, 'update');

            $status = $this->statusOf($order);
            $status->assertCanTransitionTo(ManufacturOrderStatus::Completed);

            if ((float) $order->quantity <= 0) {
                DB::rollBack();

                return redirect()->back()->with('error', 'كمية أمر التصنيع صفر — لا يمكن احتساب تكلفة الوحدة.');
            }

            $inv = app(InventoryService::class);
            $journal = app(ManufacturingJournalService::class);
            $currency = $this->documentCurrency($order);

            $warehouseId = (int) $request->storehouse_id;
            $wasteWarehouseId = (int) ($request->waste_storehouse_id ?: $warehouseId);
            $actualQty = (float) ($request->actual_quantity ?: $order->quantity);

            /*
                إعداد «السماح بتجاوز الكمية المطلوبة» — يُطبَّق هنا.

                كان المفتاحُ يُحفظ في الإعدادات العامّة ولا يقرؤه شيء:
                مفعَّلاً أو معطَّلاً، يُقبل إنتاجٌ بأيّ كمية. الآن: معطَّلاً
                يُرفض إتمامُ الأمر بكميةٍ فعليةٍ تزيد على كميته المطلوبة.
            */
            if ($actualQty > (float) $order->quantity + 1e-9 && ! $this->quantityExceedAllowed()) {
                DB::rollBack();

                return redirect()->back()->withInput()->with(
                    'error',
                    'الكمية الفعلية ('.($actualQty + 0).') أكبر من كمية الأمر المطلوبة ('.((float) $order->quantity + 0).'). '
                    .'تجاوز الكمية غير مسموح — فعّل «السماح بتجاوز الكمية المطلوبة» من إعدادات التصنيع العامّة، أو صحّح الكمية.'
                );
            }

            // ── التكاليف من البنود المحفوظة، لا من الواجهة ──
            $items = $order->manufacturOrdersItem;

            /*
                المواد المستخدمة فعلاً = المصروفة − المُرجَعة.

                `rawCostOf` تجمع ما صُرف، وما أُرجع قبل الإنهاء عاد إلى
                المستودع بإذنٍ وقيد. كان المرتجعُ لا يُطرح هنا، فتُحسب
                تكلفةُ المنتج على موادَّ لم تدخل فيه — منتجٌ بتكلفةٍ منفوخة
                يقابلها «انحرافٌ مواتٍ» كاذب.
            */
            $rawCost = $this->money(max(0.0, $this->rawCostOf($items) - $this->returnedValue($order)));
            $expensesCost = $this->money($items->sum('expenses_total'));
            $laborCost = $this->money($items->sum('manu_total'));
            $wipCharged = $this->money($rawCost + $expensesCost + $laborCost);

            if ($wipCharged <= 0) {
                DB::rollBack();

                return redirect()->back()->with(
                    'error',
                    'تكلفة أمر التصنيع صفر — راجع بنود المواد والمصروفات والتشغيل قبل الإتمام.'
                );
            }

            /*
                تكلفةُ الوحدة فعليةٌ: ما حُمِّل على الأمر — ناقصاً قيمةَ
                الهالك الذي يدخل المخزون بقيمته — مقسوماً على ما أُنتج فعلاً.

                كانت `المحمَّل ÷ الكمية المخطَّطة` بلا طرح الهالك: فمن خطّط
                ١٠٠ وأنتج ٩٨ دخلت الـ٩٨ بتكلفة ١٠٠ وحدة ناقصةً اثنتين،
                والفرقُ يذهب «انحرافاً». والقاعدةُ الآن هي قاعدةُ شاشة الأمر
                وقائمة المواد نفسُها: مواد + مصروفات + تشغيل − هالك.
            */
            $wasteLines = $items->filter(fn ($i) => $i->end_life_product_id && $i->end_life_quantity > 0);
            $plannedWaste = $this->money($wasteLines->sum(
                fn ($w) => (float) $w->end_life_quantity * (float) ($w->end_life_unit_price ?? 0)
            ));

            $netCost = $this->money(max(0.0, $wipCharged - $plannedWaste));
            $unitCost = $this->unitPrice($this->safeDivide($netCost, $actualQty));
            $finishedValue = $netCost;

            // ── إذن المنتج التام ──
            $permit = $this->createPermit(
                order: $order,
                warehouseId: $warehouseId,
                type: ManufacturingSources::TYPE_ADD,
                source: ManufacturingSources::PERMIT_FINISHED_PRODUCT,
                section: ManufacturingSources::SECTION_PERMIT_ADD,
                details: $request->notes ?: 'إضافة منتج تام من أمر التصنيع: '.$order->name
            );

            $inv->addPermitLine($permit, (int) $order->product_id, $actualQty, $unitCost, 'receive', $warehouseId);
            $inv->sealPermit($permit);

            // ── إذن الهالك (منفصل: مصدره ومستودعه قد يختلفان) ──
            $wasteValue = 0.0;
            $wastePermit = null;

            if ($wasteLines->isNotEmpty()) {
                $wastePermit = $this->createPermit(
                    order: $order,
                    warehouseId: $wasteWarehouseId,
                    type: ManufacturingSources::TYPE_ADD,
                    source: ManufacturingSources::PERMIT_WASTE,
                    section: ManufacturingSources::SECTION_PERMIT_ADD,
                    details: 'إضافة مواد هالكة من أمر التصنيع: '.$order->name
                );

                foreach ($wasteLines as $w) {
                    $inv->addPermitLine(
                        $wastePermit,
                        (int) $w->end_life_product_id,
                        (float) $w->end_life_quantity,
                        (float) ($w->end_life_unit_price ?? 0),
                        'receive',
                        $wasteWarehouseId
                    );
                }

                $wasteValue = $inv->sealPermit($wastePermit);
            }

            /*
                قيدٌ لكل حدثٍ — لا قيدٌ مركَّبٌ واحد.

                كان الإتمامُ يُرحَّل قيداً واحداً من اثني عشر سطراً: تحميلُ
                المصروفات والتشغيل، وتسليمُ المنتج، والهالك، والانحراف كلُّها
                معاً — فلا يُقرأ منه ما كلّف الأمرَ ولا ما دخل المستودع. الآن:

                    ١) تكاليف أمر التصنيع      تحت التشغيل ⟵ المصروفات والتشغيل
                    ٢) تسليم المنتج التام       مستودع الاستلام ⟵ تحت التشغيل
                    ٣) الهالك                  مستودع الهالك ⟵ تحت التشغيل
                    ٤) انحراف الإنتاج (إن وُجد)

                وكلُّها بوسم مرحلة الإتمام، فالتراجعُ يعكسها جميعاً.
            */
            $entryTitles = [
                'cost' => 'تكاليف أمر التصنيع: ',
                'finished' => 'تسليم المنتج التام إلى المستودع — أمر التصنيع: ',
                'waste' => 'إدخال المواد الهالكة إلى المستودع — أمر التصنيع: ',
                'variance' => 'انحراف إنتاج — أمر التصنيع: ',
            ];

            $grouped = collect($this->manufacturingJournalLines(
                $order, $rawCost, $expensesCost, $laborCost,
                $wipCharged, $finishedValue, $wasteValue,
                $warehouseId, $wasteWarehouseId
            ))->groupBy(fn ($line) => $line['group'] ?? 'cost');

            foreach ($entryTitles as $group => $title) {
                $entryLines = $this->mergeEntryLines($grouped->get($group, collect())->all());

                if ($entryLines === []) {
                    continue;
                }

                $journal->post(
                    lines: $entryLines,
                    referenceId: $order->id,
                    description: $title.$order->name.' (#'.$order->id.')',
                    date: now(),
                    currency: $currency,
                    clientId: $order->client_id,
                    employeeId: $order->employee_id,
                    stage: ManufacturingSources::STAGE_COMPLETE,
                );
            }

            // ── التحقق أن WIP أُقفل فعلاً ──
            $residue = $journal->wipResidueFor($order->id);

            if (abs($residue) > 0.01) {
                throw new \RuntimeException(sprintf(
                    'حساب مخزون تحت التشغيل لم يُقفل — تبقّى %s. راجع ربط حساب انحراف الإنتاج.',
                    number_format($residue, 2)
                ));
            }

            $order->update([
                'status' => ManufacturOrderStatus::Completed,
                'completed_at' => now(),
                'actual_quantity' => $actualQty,
                'warehouse_permit_id' => $permit->id,
                'main_warehouse_id' => $warehouseId,
                'waste_warehouse_id' => $wasteLines->isNotEmpty() ? $wasteWarehouseId : $order->waste_warehouse_id,
                // التكلفةُ الفعلية الصافية — مصدرُ التقارير الواحد لتكلفة الأمر
                'last_total_cost' => $wipCharged,
                'finish_notes' => $request->notes,
                'updated_by' => auth()->id(),
            ]);

            OperationLog::record(
                'completed',
                ManufacturingSources::LOG_ORDER,
                $order,
                sprintf(
                    'تم إتمام أمر التصنيع **%s** — %s وحدة بتكلفة %s، إذن رقم %s.%s',
                    $order->name,
                    rtrim(rtrim(number_format($actualQty, 4, '.', ''), '0'), '.'),
                    number_format($finishedValue, 2),
                    $permit->number,
                    $wastePermit ? ' وهالك بإذن '.$wastePermit->number.'.' : ''
                )
            );

            // حالةُ خطّة الإنتاج تتبع أوامرَها — لا أثرَ لأمرٍ لم يولَّد من خطّة
            \App\Models\ProductionPlan::syncStatusForOrder($order);

            DB::commit();

            return redirect()->route('manufacturing.orders.show', $order->id)
                ->with('success', 'تم إتمام أمر التصنيع وإدخال المنتج للمستودع — إذن رقم '.$permit->number);

        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'completeOrder', $id);

            return redirect()->back()->withInput()->with('error', $this->lifecycleFailure($e, 'تعذّر إتمام أمر التصنيع.'));
        }
    }

    /**
     * يضمّ سطورَ القيد الواحد: سطرٌ لكل حسابٍ في كل جهة.
     *
     * قيدُ «تكاليف الأمر» كان يكتب «مخزون تحت التشغيل» مديناً مرّةً لكل
     * بندِ مصروفٍ ومرّةً للتشغيل — الحسابُ نفسُه يتكرّر في القيد بلا معنى
     * يُقرأ. الآن: مدينٌ واحدٌ بمجموع ما حُمِّل، ودائنٌ واحدٌ لكل حساب.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    protected function mergeEntryLines(array $lines): array
    {
        $merged = [];

        foreach ($lines as $line) {
            unset($line['group']);

            $side = (float) ($line['debit'] ?? 0) > 0 ? 'debit' : 'credit';
            $account = isset($line['account_id']) ? 'id:'.$line['account_id'] : 'key:'.($line['key'] ?? '');
            $slot = $side.'|'.$account;

            if (! isset($merged[$slot])) {
                $merged[$slot] = $line;

                continue;
            }

            $merged[$slot][$side] = $this->money((float) $merged[$slot][$side] + (float) $line[$side]);

            // وصفان مختلفان لحسابٍ واحد ⇒ وصفٌ جامع
            if (($merged[$slot]['description'] ?? null) !== ($line['description'] ?? null)) {
                $merged[$slot]['description'] = $side === 'debit' ? 'تكاليف محمَّلة على الأمر' : 'تحميل على أمر التصنيع';
            }
        }

        // المدينُ أوّلاً ثمّ الدائن — ترتيبُ القيد المألوف
        uksort($merged, fn ($a, $b) => strcmp(explode('|', $b)[0], explode('|', $a)[0]));

        return array_values($merged);
    }

    /** أيُسمح بإنتاجٍ يزيد على كمية الأمر؟ — إعدادات التصنيع العامّة. */
    protected function quantityExceedAllowed(): bool
    {
        return (bool) optional(\App\Models\ManufacturingGeneralSetting::first())->quantity_exceeded;
    }

    /**
     * سطور القيود الستة.
     *
     * الأول والثاني والثالث يُدينون WIP بالتكاليف، والرابع والخامس
     * يُقرضانه بما دخل المخزون، والسادس يستوعب الفرق فيُقفله.
     *
     *     الانحراف = رصيد WIP في الدفتر + أثر هذا القيد عليه
     *
     * موجباً كان أو سالباً — تُقلب أطرافه تبعاً لإشارته.
     *
     * `$expensesCost` و`$wipCharged` محفوظان في التوقيع لأن المتحكّم
     * يمرّرهما ولأنهما يوثّقان أصل التكلفة، لكنّ الانحراف لم يعد يُحسب
     * منهما — انظر التعليق عند البند (٦).
     */
    protected function manufacturingJournalLines(
        ManufacturOrders $order,
        float $rawCost,
        float $expensesCost,
        float $laborCost,
        float $wipCharged,
        float $finishedValue,
        float $wasteValue,
        ?int $finishedWarehouseId = null,
        ?int $wasteWarehouseId = null
    ): array {
        $lines = [];

        // (١) المواد الخام — لا تُرحَّل هنا إن كانت رُحِّلت عند الاستلام.
        //     نتحقّق فعلياً بدل الافتراض: أمرٌ أُنشئ قبل هذا الإصلاح قد
        //     يكون استلم مواده بلا قيد.
        if ($rawCost > 0 && ! $this->rawMaterialsAlreadyPosted($order)) {
            $lines[] = ['key' => ManufacturingSources::ACCOUNT_WIP, 'debit' => $rawCost, 'description' => 'مواد خام'];
            $lines[] = ($order->storehouse_id
                ? $this->inventoryAccountLine((int) $order->storehouse_id, ManufacturingSources::ACCOUNT_RAW_MATERIALS)
                : ['key' => ManufacturingSources::ACCOUNT_RAW_MATERIALS]) + ['credit' => $rawCost];
        }

        // (٢) المصروفات — كلٌّ إلى حسابه المختار في البند
        foreach ($order->manufacturOrdersItem as $item) {
            $amount = $this->money($item->expenses_total ?? 0);

            if ($amount <= 0 || ! $item->expenses_account_id) {
                continue;
            }

            $lines[] = [
                'key' => ManufacturingSources::ACCOUNT_WIP,
                'debit' => $amount,
                'description' => 'مصروف: '.($item->expenses_description ?: 'تصنيع'),
            ];
            $lines[] = [
                'account_id' => (int) $item->expenses_account_id,
                'credit' => $amount,
                'description' => 'تحميل على أمر التصنيع '.$order->code,
            ];
        }

        // (٣) تكلفة التشغيل — كلُّ جزءٍ إلى حسابه المختار في المحطّة
        if ($laborCost > 0) {
            $lines[] = ['key' => ManufacturingSources::ACCOUNT_WIP, 'debit' => $laborCost, 'description' => 'تكلفة تشغيل المحطات'];

            foreach ($this->labourCreditLines($order) as $credit) {
                $lines[] = $credit;
            }
        }

        // ما سبق كلُّه «تكاليف الأمر» — يُرحَّل قيداً مستقلاً
        $costEnd = count($lines);

        // (٤) المنتج التام
        if ($finishedValue > 0) {
            // المدينُ حسابُ المستودع الذي دخله المنتج — كما تُقيَّد كلُّ إضافةٍ مخزنية
            $lines[] = ($finishedWarehouseId
                ? $this->inventoryAccountLine($finishedWarehouseId, ManufacturingSources::ACCOUNT_FINISHED_GOODS)
                : ['key' => ManufacturingSources::ACCOUNT_FINISHED_GOODS]) + [
                    'debit' => $finishedValue,
                    'description' => 'منتج تام — '.$this->productName((int) $order->product_id),
                ];
            $lines[] = ['key' => ManufacturingSources::ACCOUNT_WIP, 'credit' => $finishedValue];
        }

        $finishedEnd = count($lines);

        // (٥) الهالك
        if ($wasteValue > 0) {
            $lines[] = ($wasteWarehouseId
                ? $this->inventoryAccountLine($wasteWarehouseId, ManufacturingSources::ACCOUNT_WASTE)
                : ['key' => ManufacturingSources::ACCOUNT_WASTE]) + ['debit' => $wasteValue, 'description' => 'مواد هالكة'];
            $lines[] = ['key' => ManufacturingSources::ACCOUNT_WIP, 'credit' => $wasteValue];
        }

        $wasteEnd = count($lines);

        // (٦) الانحراف — يُقفل ما تبقّى في «مخزون تحت التشغيل»
        //
        // ⚠️ لا يُحسب من الوسائط.
        //
        // كان يُحسب هكذا:
        //
        //     $charged = rawMaterialsAlreadyPosted($order)
        //         ? $expensesCost + $laborCost
        //         : $wipCharged;
        //     $variance = $charged - $finishedValue - $wasteValue;
        //
        // وهو خطأ في المسار الطبيعي: المواد الخام تُرحَّل عند **الاستلام**
        // في قيدٍ سابق، فتستقرّ 300 مديناً في WIP. ثم يستبعدها هذا السطر
        // من الحساب، فيخرج الانحراف مواتياً بمقدار 230 بدل 70 غير المواتي،
        // ويبقى في WIP 300 عالقة — فيرفع حارس `wipResidueFor` في وجه
        // **كل** إتمام ويسقط الطلب باستثناء.
        //
        // المصدر الصادق الوحيد هو دفتر الأستاذ: ما استقرّ في WIP لهذا
        // الأمر + ما يضيفه هذا القيد إليه. يصحّ في المسارين معاً — سواء
        // رُحِّلت المواد عند الاستلام أو هنا.
        $priorResidue = app(ManufacturingJournalService::class)->wipResidueFor((int) $order->id);

        $wipInThisEntry = 0.0;

        foreach ($lines as $line) {
            if (($line['key'] ?? null) !== ManufacturingSources::ACCOUNT_WIP) {
                continue;
            }

            $wipInThisEntry += (float) ($line['debit'] ?? 0) - (float) ($line['credit'] ?? 0);
        }

        // موجب ⇒ بقي في WIP مدينٌ لم يقابله إنتاج ⇒ انحراف غير مواتٍ
        $variance = $this->money($priorResidue + $wipInThisEntry);

        if (abs($variance) > 0.01) {
            if ($variance > 0) {
                // حُمِّل على WIP أكثر مما دخل المخزون ⇒ انحراف غير مواتٍ
                $lines[] = [
                    'key' => ManufacturingSources::ACCOUNT_PRODUCTION_VARIANCE,
                    'debit' => $variance,
                    'description' => 'انحراف إنتاج غير مواتٍ — أمر '.$order->code,
                ];
                $lines[] = ['key' => ManufacturingSources::ACCOUNT_WIP, 'credit' => $variance];
            } else {
                $lines[] = ['key' => ManufacturingSources::ACCOUNT_WIP, 'debit' => abs($variance)];
                $lines[] = [
                    'key' => ManufacturingSources::ACCOUNT_PRODUCTION_VARIANCE,
                    'credit' => abs($variance),
                    'description' => 'انحراف إنتاج موات — أمر '.$order->code,
                ];
            }
        }

        // وسمُ كل سطرٍ بقيده — يقرؤه `completeOrder` فيُرحّل كلَّ مجموعةٍ قيداً
        foreach ($lines as $index => $line) {
            $lines[$index]['group'] = match (true) {
                $index < $costEnd => 'cost',
                $index < $finishedEnd => 'finished',
                $index < $wasteEnd => 'waste',
                default => 'variance',
            };
        }

        return $lines;
    }

    /**
     * الطرفُ الدائن لتكلفة التشغيل — موزَّعاً على حسابات المحطّة.
     *
     * كانت تكلفةُ التشغيل كلُّها تُقيَّد دائنةً على «أجور العمالة»، والحساباتُ
     * التي يختارها المستخدم في شاشة المحطّة (الأجور، كلُّ مصروف، الأصل) لا
     * يقرؤها قيدٌ واحد. الآن يُقسَم إجماليُّ كل عمليةٍ بنسبة مكوّنات تكلفة
     * ساعة محطّتها:
     *
     *     الأجور      ⟵ حساب الأجور
     *     كلّ مصروف   ⟵ حسابه
     *     إهلاك الساعة ⟵ حساب الأصل   (قيمة الأصل ÷ عمره بالساعات)
     *
     * وما لا حسابَ له في المحطّة — أو محطّةٌ بلا مكوّنات — يذهب إلى حساب
     * «تكاليف التشغيل والتصنيع» كما كان، فلا يتعطّل الإتمام على محطّةٍ
     * لم تُستكمل بياناتُها.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function labourCreditLines(ManufacturOrders $order): array
    {
        $byAccount = [];   // معرّف الحساب ⟵ مبلغ
        $fallback = 0.0;

        $operations = $order->manufacturOrdersItem->filter(fn ($i) => $i->workstation_id && (float) $i->manu_total > 0);
        $stations = \App\Models\WorkStations::with('stationsCosts')
            ->whereIn('id', $operations->pluck('workstation_id')->unique()->all())
            ->get()
            ->keyBy('id');

        foreach ($operations as $operation) {
            $amount = $this->money($operation->manu_total);
            $station = $stations->get($operation->workstation_id);

            // مكوّناتُ تكلفة الساعة: [حساب|null، قيمة]
            $components = [];

            if ($station) {
                if ((float) $station->cost_wages > 0) {
                    $components[] = [$station->account_wages ?: null, (float) $station->cost_wages];
                }

                foreach ($station->stationsCosts as $cost) {
                    if ((float) $cost->cost_expenses > 0) {
                        $components[] = [$cost->account_expenses ?: null, (float) $cost->cost_expenses];
                    }
                }

                $life = (float) ($station->asset_life_hours ?? 0);

                // الإهلاكُ يدخل حين يكون «الإهلاك التلقائي» مفعَّلاً على المحطّة
                if ($station->automatic_depreciation && (float) $station->cost_origin > 0 && $life > 0) {
                    $components[] = [$station->origin ?: null, (float) $station->cost_origin / $life];
                }
            }

            $hourly = array_sum(array_column($components, 1));

            if ($hourly <= 0) {
                $fallback += $amount;

                continue;
            }

            $assigned = 0.0;
            $last = count($components) - 1;

            foreach ($components as $index => [$accountId, $value]) {
                // الأخيرُ يأخذ الباقي — كي يساوي المجموعُ إجماليَّ العملية بالهللة
                $share = $index === $last
                    ? $this->money($amount - $assigned)
                    : $this->money($amount * $value / $hourly);

                $assigned += $share;

                if ($share <= 0) {
                    continue;
                }

                if ($accountId && \App\Models\Account::whereKey($accountId)->exists()) {
                    $byAccount[(int) $accountId] = ($byAccount[(int) $accountId] ?? 0) + $share;
                } else {
                    $fallback += $share;
                }
            }
        }

        $lines = [];

        foreach ($byAccount as $accountId => $amount) {
            $lines[] = [
                'account_id' => $accountId,
                'credit' => $this->money($amount),
                'description' => 'تحميل تشغيل على أمر التصنيع '.$order->code,
            ];
        }

        if ($this->money($fallback) > 0) {
            $lines[] = ['key' => ManufacturingSources::ACCOUNT_OVERHEAD, 'credit' => $this->money($fallback)];
        }

        return $lines;
    }

    /**
     * ما أُرجع من كل منتجٍ في هذا الأمر حتى الآن — مجموعاً بالمنتج.
     *
     * استُخرج من `returnMaterials` ليستعمله `cancelOrder` أيضاً: كان
     * الإلغاء يُرجع **كامل** كمية كل بند بلا نظرٍ إلى ما أُرجع قبله،
     * فمن صرف ١٠ وأرجع ٤ يدوياً ثم ألغى الأمر أرجع ١٠ أخرى — أي ١٤
     * من ١٠. أربعُ وحداتٍ تُخلق من العدم في كل مرّة.
     *
     * @return array<int, float> معرّف المنتج ⟵ الكمية المُرجَعة
     */
    protected function returnedSoFar(ManufacturOrders $order): array
    {
        return WarehousePermitsProducts::query()
            ->whereIn('warehouse_permits_id', $this->openMaterialPermitIds($order, ManufacturingSources::PERMIT_RETURNED_MATERIALS))
            ->selectRaw('product_id, SUM(quantity) AS qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id')
            ->map(fn ($q) => (float) $q)
            ->all();
    }

    /** قيمةُ ما أُرجع من مواد الأمر في دورة الصرف القائمة — بتكلفته الفعلية. */
    protected function returnedValue(ManufacturOrders $order): float
    {
        return round((float) WarehousePermitsProducts::query()
            ->whereIn('warehouse_permits_id', $this->openMaterialPermitIds($order, ManufacturingSources::PERMIT_RETURNED_MATERIALS))
            ->sum('total'), 2);
    }

    /**
     * أذونُ الأمر من مصدرٍ بعينه في دورة الصرف **القائمة** — استعلاماً فرعياً.
     *
     * المُسوّاة (`settled`) دورةٌ صُرفت ثمّ أُرجعت كلُّها، والمعكوسة
     * (`reversed`) إتمامٌ تُراجِع عنه: كلتاهما تاريخٌ لا رصيد.
     */
    protected function openMaterialPermitIds(ManufacturOrders $order, int $source)
    {
        return WarehousePermits::query()
            ->where('reference_type', ReferenceType::MANUFACTURING_ORDER->value)
            ->where('reference_id', $order->id)
            ->where('permission_source_id', $source)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['settled', 'reversed']);
            })
            ->select('id');
    }

    /** يَسِم أذونَ الصرف والإرجاع القائمة «مُسوّاة» — انظر `returnMaterials`. */
    protected function settleMaterialPermits(ManufacturOrders $order): void
    {
        WarehousePermits::query()
            ->where('reference_type', ReferenceType::MANUFACTURING_ORDER->value)
            ->where('reference_id', $order->id)
            ->whereIn('permission_source_id', [
                ManufacturingSources::PERMIT_ISSUE_MATERIALS,
                ManufacturingSources::PERMIT_RETURNED_MATERIALS,
            ])
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['settled', 'reversed']);
            })
            ->update(['status' => 'settled']);
    }

    /**
     * طرفُ المخزون في القيد — حسابُ المستودع نفسِه.
     *
     * وحدةُ المخزون والمشتريات تُقيِّد كلَّ حركةٍ على **حساب مستودعها** (لكل
     * مستودعٍ حسابٌ تحت «المخزون»): استلامُ البضاعة يُدين حسابَ مستودعه.
     * وكان التصنيعُ يُقيِّد على حساباتٍ ثابتة («مخزون المواد الخام»…) أيّاً
     * كان المستودع — فمادةٌ دخلت «المستودع الرئيسي» بقيدٍ على حسابه تخرج
     * منه بقيدٍ على حسابٍ آخر: حسابُ المستودع لا ينقص أبداً، والحسابُ الثابت
     * يذهب سالباً.
     *
     * فحيثُ للمستودع حسابٌ يُقيَّد عليه؛ وحيث لا حسابَ له يبقى الحسابُ
     * العامّ من «الحسابات المحاسبية» كما كان.
     *
     * @return array{account_id: int}|array{key: string}
     */
    protected function inventoryAccountLine(int $warehouseId, string $fallbackKey): array
    {
        static $cache = [];

        if (! array_key_exists($warehouseId, $cache)) {
            $cache[$warehouseId] = \App\Models\Account::query()
                ->where('storehouse_id', $warehouseId)
                ->whereNull('client_id')
                ->whereNull('supplier_id')
                ->orderBy('id')
                ->value('id');
        }

        return $cache[$warehouseId]
            ? ['account_id' => (int) $cache[$warehouseId]]
            : ['key' => $fallbackKey];
    }

    /** معاملُ تحويل وحدة البند إلى الأساسية — ١ حين لا وحدة. */
    protected function conversionFactor($item): float
    {
        $factor = (float) ($item->raw_conversion_factor ?? 1);

        return $factor > 0 ? $factor : 1.0;
    }

    /** كميةُ البند بالوحدة الأساسية — ما يتحرّك به المخزون فعلاً. */
    protected function baseQuantity($item): float
    {
        return round((float) $item->raw_quantity * $this->conversionFactor($item), 4);
    }

    /**
     * ما صُرف لكل مادة — مجموعاً بالمنتج، بالأساسية وبالتكلفة الفعلية.
     *
     * @return array<int, array{quantity: float, total: float, unit_cost: float}>
     */
    protected function issuedByProduct($items): array
    {
        $issued = [];

        foreach ($items as $item) {
            if (! $item->raw_product_id || (float) $item->raw_quantity <= 0) {
                continue;
            }

            $pid = (int) $item->raw_product_id;
            $quantity = $this->baseQuantity($item);
            $total = $item->raw_actual_total !== null
                ? (float) $item->raw_actual_total
                : (float) ($item->raw_total ?? 0);

            $issued[$pid]['quantity'] = ($issued[$pid]['quantity'] ?? 0) + $quantity;
            $issued[$pid]['total'] = ($issued[$pid]['total'] ?? 0) + $total;
        }

        foreach ($issued as $pid => $row) {
            $issued[$pid]['unit_cost'] = $row['quantity'] > 0 ? round($row['total'] / $row['quantity'], 4) : 0.0;
        }

        return $issued;
    }

    /**
     * رسالةُ فشل عمليةٍ في دورة الأمر — تقول السبب.
     *
     * الاستثناءاتُ التي ترميها الوحدة نفسُها (`RuntimeException` من
     * [InventoryService] والقيود، `DomainException` من آلة الحالات) مكتوبةٌ
     * للمستخدم بالعربية: «رصيد غير كافٍ للمادة…»، «حساب … غير مربوط».
     * كانت تُطوى كلُّها خلف «تعذّر استلام المواد» فلا يعرف ما يصحّح.
     * وأخطاءُ قاعدة البيانات تبقى مطويّةً — نصُّها في «سجلّ الأخطاء».
     */
    protected function lifecycleFailure(\Throwable $e, string $fallback): string
    {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return $fallback.' '.collect($e->errors())->flatten()->implode(' ');
        }

        $internal = $e instanceof \PDOException
            || $e instanceof \Illuminate\Database\QueryException
            || $e instanceof \ErrorException
            || $e instanceof \Error;

        if (! $internal && ($e instanceof \RuntimeException || $e instanceof \DomainException || $e instanceof \LogicException)) {
            return $fallback.' '.$e->getMessage();
        }

        return $this->safeError($e, $fallback.' سُجّل الخطأ في «سجلّ الأخطاء».');
    }

    /**
     * تكلفةُ المواد الخام للأمر — الفعليةُ متى وُجدت.
     *
     * البندُ الذي صُرفت مادتُه يحمل `raw_actual_total`: ما خرج من
     * المخزون فعلاً بتكلفته المخزَّنة. وهو الرقم الذي يجب أن يُحمَّل
     * على WIP، وإلا حُمِّل التقديريُّ على حسابٍ خرج منه الفعليّ —
     * فيبقى الفرقُ عالقاً ويظهر انحرافاً كاذباً.
     *
     * و`null` تعني «لم يُصرَف بعد» لا «صفر»، فيُقرأ التقديريّ حينئذٍ:
     * أمرٌ يُتَمّ بلا استلامِ موادٍ سابق — أو أمرٌ صُرف قبل هذا التغيير
     * — يبقى محسوباً كما كان.
     */
    protected function rawCostOf($items): float
    {
        return (float) $items->sum(
            fn ($item) => $item->raw_actual_total !== null
                ? (float) $item->raw_actual_total
                : (float) ($item->raw_total ?? 0)
        );
    }

    /** هل رُحِّل صرف المواد الخام لهذا الأمر عند الاستلام؟ */
    protected function rawMaterialsAlreadyPosted(ManufacturOrders $order): bool
    {
        /*
            التقييد بنوع المرجع لازم لا زائد.

            `warehouse_permits.reference_id` يحمل معرّفات مستندات من أنواع
            شتّى. وقائمة المواد كانت تكتب إذناً بـ`reference_id = رقمها`
            و`permission_source_id = 17` — وهو المصدر نفسه الذي يعنيه أمر
            التصنيع بـ«صرف مواد».

            فوصفةٌ رقمها 5 كانت تجعل أمر التصنيع رقم 5 يظنّ أن مواده
            رُحِّلت سلفاً، فيُسقط `Dr WIP / Cr مواد خام` من قيد الإتمام،
            ويستوعب حسابُ الانحراف تكلفةَ المواد كلّها.
        */
        // الدورةُ القائمة وحدها — صرفٌ أُرجع كلُّه ليس صرفاً قائماً
        return WarehousePermits::whereIn(
            'id',
            $this->openMaterialPermitIds($order, ManufacturingSources::PERMIT_ISSUE_MATERIALS)
        )->exists();
    }

    // ═════════════════════════════════════════════════════════════════
    // ٤) التراجع عن الإتمام
    // ═════════════════════════════════════════════════════════════════

    /**
     * عكس الإتمام: يُخرج المنتج والهالك من المخزون ويعكس القيود.
     *
     * ما تغيّر:
     *  · العكس بـ `reference_id` لا بالوصف — كان البحث النصّي يلتقط
     *    القيود العكسية نفسها فيضاعفها، ويقبل `%` من اسم الأمر فيعكس
     *    الأستاذ كله
     *  · عكس مخزني كامل عبر `reversePermit` بدل حذف الإذن وترك أثره
     */
    public function undoCompletion($id)
    {
        if ($block = $this->accountingPeriodBlock(now())) {
            return redirect()->back()->with('error', $block);
        }

        DB::beginTransaction();

        try {
            $order = $this->lockOrder($id);
            $this->authorizeOrder($order, 'update');

            $status = $this->statusOf($order);

            if (! $status->canUndoCompletion()) {
                DB::rollBack();

                return redirect()->back()->with(
                    'error',
                    'لا يمكن التراجع وأمر التصنيع في حالة «'.$status->label().'».'
                );
            }

            $inv = app(InventoryService::class);

            // كل إذونات الإضافة الناتجة عن الإتمام: المنتج التام والهالك
            $permits = WarehousePermits::where('reference_type', ReferenceType::MANUFACTURING_ORDER->value)
                ->where('reference_id', $order->id)
                ->whereIn('permission_source_id', [
                    ManufacturingSources::PERMIT_FINISHED_PRODUCT,
                    ManufacturingSources::PERMIT_WASTE,
                ])
                /*
                    آخرُ إتمامٍ وحده — أذونُ ما عُكس من قبلُ تُستثنى.

                    كان الاستعلام بلا شرطِ حالة: فمن أنهى وتراجع ثمّ أنهى
                    وتراجع، أعاد التراجعُ الثاني صرفَ إذنِ الإتمام الأوّل
                    المعكوسِ أصلاً — كميةٌ تخرج من المخزون مرّتين.
                */
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'reversed');
                })
                ->get();

            if ($permits->isEmpty()) {
                DB::rollBack();

                return redirect()->back()->with(
                    'error',
                    'لا إذونات إضافة لهذا الأمر — لا شيء يُعكس. راجع سجلّ الأمر.'
                );
            }

            foreach ($permits as $permit) {
                // يرمي إن لم يكفِ الرصيد — والمنتج قد يكون بيع بعضه.
                // و`restoreCost`: يُخرَج ما دخل **بتكلفته**، فيعود متوسطُ
                // المخزون إلى ما كان قبل هذا الإتمام
                $inv->reversePermit($permit, restoreCost: true);

                $permit->update([
                    'status' => 'reversed',
                    'details' => $permit->details."\n[معكوس بتاريخ ".now()->format('Y-m-d H:i').' — تراجع عن الإتمام]',
                ]);
            }

            $reversed = app(ManufacturingJournalService::class)
                /*
                    قيودُ الإتمام وحدها تُعكَس.

                    كانت تُعكَس **كل** قيود الأمر — ومنها قيدُ صرف المواد
                    المُرحَّل في مرحلةٍ سابقة. وإذنُ الصرف لا يُعكَس هنا
                    (تُعكس أذونُ المنتج التام والهالك فقط)، فتبقى المواد
                    مستهلَكةً في المخزون ويعود ثمنُها إلى الدفتر.

                    والأسوأ أن `rawMaterialsAlreadyPosted()` تفحص **الإذن**
                    لا القيد، والإذنُ باقٍ. فإتمامٌ ثانٍ يُسقط سطرَ «مواد
                    خام» ظنّاً أنه مُرحَّل وقد عُكس — فيبتلع حسابُ الانحراف
                    تكلفةَ المواد كاملةً ويظهر انحرافٌ مواتٍ كاذب بحجمها.

                    الأمرُ يعود إلى «تم استلام المواد»، فيجب أن يبقى في
                    WIP ما صُرف عليه فعلاً.
                */
                ->reverseFor(
                    $order->id,
                    'تراجع عن إتمام أمر التصنيع',
                    stages: [ManufacturingSources::STAGE_COMPLETE],
                );

            $order->update([
                'status' => ManufacturOrderStatus::MaterialsReceived,
                'completed_at' => null,
                'warehouse_permit_id' => null,
                'actual_quantity' => null,
                'updated_by' => auth()->id(),
            ]);

            OperationLog::record(
                'undo_completion',
                ManufacturingSources::LOG_ORDER,
                $order,
                'تم التراجع عن إتمام أمر التصنيع **'.$order->name.'** — عُكس '.$reversed.' قيداً و'.$permits->count().' إذناً.'
            );

            // حالةُ خطّة الإنتاج تتبع أوامرَها — لا أثرَ لأمرٍ لم يولَّد من خطّة
            \App\Models\ProductionPlan::syncStatusForOrder($order);

            DB::commit();

            return redirect()->route('manufacturing.orders.show', $order->id)
                ->with('success', 'تم التراجع عن الإتمام — عُكست القيود والحركة المخزنية.');

        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'undoCompletion', $id);

            return redirect()->back()->with('error', $this->lifecycleFailure($e, 'تعذّر التراجع عن الإتمام.'));
        }
    }

    // ═════════════════════════════════════════════════════════════════
    // ٥) الإلغاء
    // ═════════════════════════════════════════════════════════════════

    /**
     * إلغاء الأمر وإرجاع مواده إن كانت مصروفة.
     *
     * ما تغيّر:
     *  · `updateProductInventory` كانت **جسمها معلّقاً بالكامل** فتُرجع
     *    null، فكان الإلغاء يبتلع المواد الخام نهائياً ويعرض رسالة نجاح
     *  · الإرجاع الآن بإذن مخزني وقيد مقابل لا بتعديل صامت
     */
    public function cancelOrder(Request $request, $id)
    {
        if ($block = $this->accountingPeriodBlock(now())) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $order = $this->lockOrder($id, ['manufacturOrdersItem']);
            $this->authorizeOrder($order, 'update');

            $status = $this->statusOf($order);
            $status->assertCanTransitionTo(ManufacturOrderStatus::Cancelled);

            $inv = app(InventoryService::class);
            $returnedTotal = 0.0;
            $permit = null;

            // المواد مصروفة ⇒ تُرجَع بإذن لا بتعديل صامت
            if ($status->holdsMaterials() && $order->storehouse_id) {
                $warehouseId = (int) $order->storehouse_id;
                $storehouse = StoreHouse::find($warehouseId);

                /*
                    المتبقّي وحده يُرجَع — لا كامل الكمية.

                    كان الإلغاء يمرّ على البنود ويُرجع `raw_quantity`
                    كاملةً لكلٍّ منها، بلا نظرٍ إلى ما أُرجع قبله. فمن
                    صرف ١٠ وأرجع ٤ من شاشة الإرجاع ثم ألغى الأمر، أُرجعت
                    له ١٠ أخرى — ١٤ من ١٠. أربعُ وحداتٍ تُخلق من العدم،
                    ويُرحَّل لها قيدٌ يزيد المخزون بلا مصدر.
                */
                $returnedBefore = $this->returnedSoFar($order);

                // بالمنتج لا بالسطر، وبالوحدة الأساسية، وبالتكلفة التي خرج
                // بها — الإرجاعُ بغير سعر الصرف يترك فرقاً لا يقابله شيء
                $lines = collect($this->issuedByProduct($order->manufacturOrdersItem))
                    ->map(fn ($row, $pid) => (object) [
                        'raw_product_id' => (int) $pid,
                        'remaining' => max(0.0, round($row['quantity'] - (float) ($returnedBefore[(int) $pid] ?? 0), 4)),
                        'unit_price' => (float) $row['unit_cost'],
                    ])
                    ->filter(fn ($l) => $l->remaining > 0.0001)
                    ->values();

                if ($lines->isNotEmpty()) {
                    $permit = $this->createPermit(
                        order: $order,
                        warehouseId: $warehouseId,
                        type: ManufacturingSources::TYPE_ADD,
                        source: ManufacturingSources::PERMIT_RETURNED_MATERIALS,
                        section: ManufacturingSources::SECTION_PERMIT_ADD,
                        details: 'إرجاع مواد بسبب إلغاء أمر التصنيع: '.$order->name
                    );

                    foreach ($lines as $item) {
                        $inv->addPermitLine(
                            $permit,
                            $item->raw_product_id,
                            $item->remaining,
                            $item->unit_price,
                            'receive',
                            $warehouseId
                        );
                    }

                    $returnedTotal = $inv->sealPermit($permit);

                    if ($returnedTotal > 0) {
                        app(ManufacturingJournalService::class)->post(
                            lines: [
                                $this->inventoryAccountLine($warehouseId, ManufacturingSources::ACCOUNT_RAW_MATERIALS) + [
                                    'debit' => $returnedTotal,
                                    'description' => 'إرجاع مواد إلى '.($storehouse->name ?? '#'.$warehouseId),
                                ],
                                [
                                    'key' => ManufacturingSources::ACCOUNT_WIP,
                                    'credit' => $returnedTotal,
                                    'description' => 'إلغاء أمر التصنيع '.$order->code,
                                ],
                            ],
                            referenceId: $order->id,
                            description: 'إرجاع مواد بإلغاء أمر التصنيع: '.$order->name.' (#'.$order->id.')',
                            date: now(),
                            currency: $this->documentCurrency($order),
                            clientId: $order->client_id,
                            employeeId: $order->employee_id,
                            stage: ManufacturingSources::STAGE_CANCEL,
                        );
                    }
                }
            }

            /*
                فكُّ الحجز.

                أمرٌ ملغيٌّ لا يَعِد بشيء. وبقاءُ حجزه نشطاً يُنقص المتاح
                إلى الأبد بكميةٍ لن تُصرف أبداً — وهو أسوأُ من غياب الحجز
                أصلاً، لأنه نقصٌ لا يفسّره شيءٌ في الشاشة.
            */
            app(ReservationService::class)->release($order, 'إلغاء أمر التصنيع');

            /*
                الأمرُ الملغى رصيدُه صفر.

                ما صُرف يُرجَع أعلاه بتكلفته، فيُقفل «تحت التشغيل» وحده في
                الحالة السليمة. لكنّ أمراً قديماً أُرجع بعضُ مواده بالسعر
                التقديريّ — أو فرقَ تقريبٍ في المتوسط — يترك باقياً (سالباً
                كان أو موجباً) معلَّقاً على أمرٍ انتهى. فيُقفل الباقي على
                حساب انحراف الإنتاج، ولا يبقى على الأمر شيء.
            */
            $journal = app(ManufacturingJournalService::class);
            $residue = $journal->wipResidueFor((int) $order->id);

            if (abs($residue) > 0.004) {
                $journal->post(
                    lines: $residue > 0
                        ? [
                            ['key' => ManufacturingSources::ACCOUNT_PRODUCTION_VARIANCE, 'debit' => $residue, 'description' => 'إقفال رصيد أمرٍ ملغى — '.$order->code],
                            ['key' => ManufacturingSources::ACCOUNT_WIP, 'credit' => $residue],
                        ]
                        : [
                            ['key' => ManufacturingSources::ACCOUNT_WIP, 'debit' => abs($residue)],
                            ['key' => ManufacturingSources::ACCOUNT_PRODUCTION_VARIANCE, 'credit' => abs($residue), 'description' => 'إقفال رصيد أمرٍ ملغى — '.$order->code],
                        ],
                    referenceId: $order->id,
                    description: 'إقفال رصيد أمر التصنيع الملغى: '.$order->name.' (#'.$order->id.')',
                    date: now(),
                    currency: $this->documentCurrency($order),
                    clientId: $order->client_id,
                    employeeId: $order->employee_id,
                    stage: ManufacturingSources::STAGE_CANCEL,
                );
            }

            // دورةُ الصرف انتهت بالإلغاء
            $this->settleMaterialPermits($order);

            $order->update([
                'status' => ManufacturOrderStatus::Cancelled,
                'finish_notes' => $request->cancellation_reason,
                'updated_by' => auth()->id(),
            ]);

            OperationLog::record(
                'cancelled',
                ManufacturingSources::LOG_ORDER,
                $order,
                'تم إلغاء أمر التصنيع **'.$order->name.'**: '.$request->cancellation_reason
                    .($permit ? ' — أُرجعت المواد بإذن رقم '.$permit->number.'.' : '')
            );

            // حالةُ خطّة الإنتاج تتبع أوامرَها — لا أثرَ لأمرٍ لم يولَّد من خطّة
            \App\Models\ProductionPlan::syncStatusForOrder($order);

            DB::commit();

            return redirect()->route('manufacturing.orders.show', $order->id)->with(
                'success',
                'تم إلغاء أمر التصنيع'.($permit ? ' وإرجاع المواد للمستودع — إذن رقم '.$permit->number : '')
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'cancelOrder', $id);

            return redirect()->back()->withInput()->with('error', $this->lifecycleFailure($e, 'تعذّر إلغاء أمر التصنيع.'));
        }
    }

    // ═════════════════════════════════════════════════════════════════
    // ٦) الإغلاق وإعادة الفتح
    // ═════════════════════════════════════════════════════════════════

    public function closeOrder(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $order = $this->lockOrder($id);
            $this->authorizeOrder($order, 'update');

            $this->statusOf($order)->assertCanTransitionTo(ManufacturOrderStatus::Closed);

            $order->update([
                'status' => ManufacturOrderStatus::Closed,
                'finished_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            OperationLog::record(
                'closed',
                ManufacturingSources::LOG_ORDER,
                $order,
                'تم إغلاق أمر التصنيع **'.$order->name.'**'
            );

            // حالةُ خطّة الإنتاج تتبع أوامرَها — لا أثرَ لأمرٍ لم يولَّد من خطّة
            \App\Models\ProductionPlan::syncStatusForOrder($order);

            DB::commit();

            return redirect()->back()->with('success', 'تم إغلاق أمر التصنيع.');

        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'closeOrder', $id);

            return redirect()->back()->with('error', $this->safeError($e, 'تعذّر إغلاق الأمر.'));
        }
    }

    public function reopenOrder($id)
    {
        DB::beginTransaction();

        try {
            $order = $this->lockOrder($id);
            $this->authorizeOrder($order, 'update');

            $this->statusOf($order)->assertCanTransitionTo(ManufacturOrderStatus::Completed);

            $order->update([
                'status' => ManufacturOrderStatus::Completed,
                'finished_at' => null,
                'updated_by' => auth()->id(),
            ]);

            OperationLog::record(
                'reopened',
                ManufacturingSources::LOG_ORDER,
                $order,
                'تم إعادة فتح أمر التصنيع **'.$order->name.'**'
            );

            // حالةُ خطّة الإنتاج تتبع أوامرَها — لا أثرَ لأمرٍ لم يولَّد من خطّة
            \App\Models\ProductionPlan::syncStatusForOrder($order);

            DB::commit();

            return redirect()->back()->with('success', 'تم إعادة فتح أمر التصنيع.');

        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'reopenOrder', $id);

            return redirect()->back()->with('error', $this->safeError($e, 'تعذّر إعادة فتح الأمر.'));
        }
    }

    // ═════════════════════════════════════════════════════════════════
    // ٧) الحذف
    // ═════════════════════════════════════════════════════════════════

    /**
     * حذف أمر تصنيع — بحراسٍ ثلاثة.
     *
     * ما تغيّر: كانت تحذف القيود بـ`where('type_id', …)` و
     * `LIKE '%أمر تصنيع%'` — والعمود `type_id` لا يُكتب أبداً، والأوصاف
     * تقول «أمر التصنيع» بأل التعريف فلا يطابقها النمط. أي أن القيود
     * كانت تبقى يتيمة دائماً، والأمر يُحذف بلا معاملة ولا فحص حالة.
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $order = $this->lockOrder($id);
            $this->authorizeOrder($order, 'delete');

            $status = $this->statusOf($order);

            if (! $status->isDeletable()) {
                DB::rollBack();

                return redirect()->back()->with(
                    'error',
                    'لا يمكن حذف أمر تصنيع في حالة «'.$status->label().'». ألغِ الأمر أو تراجع عن إنهائه أولاً.'
                );
            }

            if ($block = $this->accountingPeriodBlock($order->created_at ?? now())) {
                DB::rollBack();

                return redirect()->back()->with('error', $block);
            }

            // أمرٌ محذوف لا يَعِد بشيء — يُفكّ حجزُه قبل زواله
            app(ReservationService::class)->release($order, 'حذف أمر التصنيع');

            $hasPermits = WarehousePermits::where('reference_type', ReferenceType::MANUFACTURING_ORDER->value)
                ->where('reference_id', $order->id)
                ->whereIn('permission_source_id', [
                    ManufacturingSources::PERMIT_ISSUE_MATERIALS,
                    ManufacturingSources::PERMIT_FINISHED_PRODUCT,
                    ManufacturingSources::PERMIT_WASTE,
                    ManufacturingSources::PERMIT_RETURNED_MATERIALS,
                ])
                ->exists();

            if ($hasPermits) {
                DB::rollBack();

                return redirect()->back()->with(
                    'error',
                    'لهذا الأمر إذونات مخزنية — لا يُحذف. الإلغاء يُبقي الأثر التدقيقي.'
                );
            }

            $name = $order->name;

            // القيود بالمفتاح الصحيح — إن وُجدت أصلاً
            $entries = JournalEntry::where('reference_type', ReferenceType::MANUFACTURING_ORDER)
                ->where('reference_id', $order->id)
                ->get();

            foreach ($entries as $entry) {
                $entry->details()->delete();
                $entry->delete();
            }

            // رابطُ الخطّة يُصفَّر بحذف الأمر — تُقرأ خططُه قبله وتُحدَّث بعده
            $planIds = \App\Models\ProductionPlan::planIdsForOrder((int) $order->id);

            $order->manufacturOrdersItem()->delete();
            $order->delete();

            \App\Models\ProductionPlan::syncStatuses($planIds);

            OperationLog::record('deleted', ManufacturingSources::LOG_ORDER, $id, 'تم حذف أمر التصنيع **'.$name.'**');

            DB::commit();

            return redirect()->route('manufacturing.orders.index')->with('success', 'تم حذف أمر التصنيع.');

        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'destroy', $id);

            return redirect()->back()->with('error', $this->safeError($e, 'تعذّر حذف أمر التصنيع.'));
        }
    }

    // ═════════════════════════════════════════════════════════════════
    // مساعدات مشتركة
    // ═════════════════════════════════════════════════════════════════

    /**
     * إذن مخزني بكل ما تفرضه الإعدادات: مصدرٌ صحيح، ورقمٌ من الترقيم
     * المتسلسل، وعملةُ المستند، ونوعُ مرجعٍ يربطه بأمره.
     */
    protected function createPermit(
        ManufacturOrders $order,
        int $warehouseId,
        int $type,
        int $source,
        string $section,
        string $details
    ): WarehousePermits {
        return WarehousePermits::create([
            'permission_type' => $type,
            'permission_source_id' => $source,
            'permission_date' => now(),
            'reference_type' => ReferenceType::MANUFACTURING_ORDER->value,
            'reference_id' => $order->id,
            /*
                رقمُ الإذن من سمة الترقيم في مديول المخزون.

                كان الاستدعاء `resolveDocumentNumber(null, $section)` —
                وهي دالّةٌ **لا وجود لها هنا**: تعيش في سمة الترقيم
                الخاصّة بمديول المبيعات (`Modules\Sales\…\GeneratesDocumentNumbers`)،
                بينما متحكّمات التصنيع تستعمل سمةَ المخزون التي تُصدِّر
                `issueDocumentNumber` و`keepDocumentNumber` و`previewDocumentNumber`.

                فكان `createPermit` يرمي BadMethodCallException، ويلتقطه
                `catch (\Throwable)` في كل مسار فيُبدَّل برسالة عامّة
                («تعذّر استلام المواد»)، ويُطوى الاستثناء في سجلّ الأخطاء.

                والأثر أن **دورة أمر التصنيع كلَّها معطّلة**: الصرف
                والإرجاع والإتمام والإلغاء — كلُّها تمرّ من هنا. وهو
                ما يفسّر خلوّ `manufactur_orders` من أي صفّ.

                و`resolveDocumentNumber(null, …)` تعني «لا رقم مُرسَل،
                فأصدِر واحداً» — وهو عينُ ما تفعله `issueDocumentNumber`.
            */
            'number' => $this->issueDocumentNumber($section),
            'store_houses_id' => $warehouseId,
            'client_id' => $order->client_id,
            'currency' => $this->documentCurrency($order),
            'details' => $details,
            'grand_total' => 0,     // يُشتقّ من البنود في sealPermit
            'created_by' => auth()->id(),
            'status' => ManufacturingSources::STATUS_APPROVED,
        ]);
    }

    /**
     * الحالة ككائن Enum — يستوعب النصّ الخام قبل تفعيل الـ cast.
     */
    /**
     * قراءة أمر التصنيع **مع قفل صفّه** — نقطة الدخول الوحيدة لكل عملية.
     *
     * ── لماذا ────────────────────────────────────────────────────────
     *
     * كانت كل عملية تبدأ بـ`ManufacturOrders::findOrFail($id)` بلا قفل،
     * في ثمانية مواضع. فطلبان متزامنان على نفس الأمر — نقرةٌ مزدوجة، أو
     * تبويبان، أو إعادة إرسال بعد مهلة — يقرآن الحالة نفسها، ويجتازان
     * آلة الحالات كلاهما، ثم:
     *
     *   completeOrder  ⇒  إذنا إضافة وقيدان ⇒ الكمية تدخل المستودع مرّتين
     *   receiveMaterials ⇒ صرفٌ مزدوج للمواد
     *
     * والقفل الوحيد في المديول كان على `product_details` داخل
     * `InventoryService::receive` — يحمي حساب المتوسط المرجّح ولا يمنع
     * تنفيذ العملية مرّتين.
     *
     * وفي `completeOrder` خاصّةً: الطلبان يقرآن `wipResidueFor()` قبل أن
     * يُثبّت أيّهما، فيُحسب الانحراف مرّتين على الرصيد نفسه.
     *
     * ── كيف يعمل ─────────────────────────────────────────────────────
     *
     * `SELECT … FOR UPDATE` يحجز الصفّ حتى نهاية المعاملة. فالطلب الثاني
     * ينتظر عند هذا السطر، ثم يقرأ الحالة **بعد** أن كتبها الأول — فترفضه
     * آلة الحالات كما ينبغي برسالة مفهومة، لا بحركة مزدوجة صامتة.
     *
     * ⚠️ يجب أن يُستدعى **داخل معاملة مفتوحة** — القفل خارجها بلا أثر.
     *
     * @param  array<int, string>  $with  العلاقات المحمَّلة بعد القفل
     */
    protected function lockOrder($id, array $with = []): ManufacturOrders
    {
        $order = ManufacturOrders::whereKey($id)->lockForUpdate()->firstOrFail();

        /*
            حارسُ الفرع في نقطة الدخول الوحيدة — لا في ثمانية مواضع.

            دوالُّ دورة الحياة الثماني تقبل المعرّف من العنوان مباشرةً
            (`orders/{id}/complete`، `/cancel`، `/close`…) ولا تمرّ بقائمةٍ
            مصفّاة. فبلا هذا السطر يُنهي موظّفٌ أمرَ تصنيعٍ في فرعٍ آخر
            بطلبٍ واحد: تُصرَف موادُّه من مستودعٍ لا يراه، ويُرحَّل قيدُه،
            ويدخل المنتجُ التام مخزناً لا يخصّه — بلا شيءٍ في الشاشة يشي
            بذلك، لأن الشاشة لم تُفتَح أصلاً.

            وموضعُه هنا لا في كل دالّة: ثمانيةُ حرّاسٍ يعني أن نسيان
            واحدٍ يفتح عمليةً كاملة، وأخطرُها أقلُّها استعمالاً — فلا
            يُلاحَظ غيابُه.
        */
        $this->authorizeBranchScope($order->branch_id);

        if ($with !== []) {
            $order->load($with);
        }

        return $order;
    }

    /**
     * مستودعُ العملية — من الفرع النشط وحده.
     *
     * `storehouse_id` يصل في الطلب و`exists:store_houses,id` تتحقّق من
     * وجوده لا من فرعه. والمستودعُ هنا يُخصَم منه ويُضاف إليه حقيقةً —
     * فمعرّفٌ مُحرَّر في النموذج يصرف موادَّ فرعٍ آخر أو يُدخل إليه
     * إنتاجاً، ويخرج الفرعان بجردٍ لا يطابق واقعهما.
     */
    protected function lockedStorehouse(int $warehouseId, ?ManufacturOrders $order = null): StoreHouse
    {
        $storehouse = StoreHouse::findOrFail($warehouseId);

        // مستودعُ الأمر نفسُه مقبولٌ دائماً — خُتم معه عند إنشائه
        if (! $order || (int) $order->storehouse_id !== $warehouseId) {
            $this->assertBranchScope($storehouse->branch_id, 'storehouse_id');
        }

        return $storehouse;
    }

    protected function statusOf(ManufacturOrders $order): ManufacturOrderStatus
    {
        if ($order->status instanceof ManufacturOrderStatus) {
            return $order->status;
        }

        return ManufacturOrderStatus::fromLegacy($order->status) ?? ManufacturOrderStatus::Pending;
    }

    /**
     * صلاحية العملية على هذا الأمر.
     *
     * تُفعَّل بعد كتابة `ManufacturOrderPolicy` وتسجيلها. حتى ذلك الحين
     * تمرّ بلا فحص — ولا ترمي، كي لا تتعطّل الشاشات قبل اكتمال المرحلة.
     */
    protected function authorizeOrder(ManufacturOrders $order, string $ability): void
    {
        try {
            if (app(Gate::class)->getPolicyFor($order)) {
                $this->authorize($ability, $order);
            }
        } catch (AuthorizationException $e) {
            throw $e;   // الرفض يصعد كما هو
        } catch (\Throwable $e) {
            // لا Policy بعد — تُخطّى بصمت
        }
    }

    protected function productName(int $productId): string
    {
        return (string) (Product::where('id', $productId)->value('name') ?: '#'.$productId);
    }
}
