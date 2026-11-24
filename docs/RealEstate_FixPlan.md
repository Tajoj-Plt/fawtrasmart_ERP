<div dir="rtl" align="right">

# خطة إصلاح نظام العقارات — جدول المهام

**التاريخ**: 2026-10-05 · **الوحدة**: `Modules/RentalManagement`
المراجع: `docs/RealEstate_Report.md` (التشخيص والقيود المقترحة) · `docs/ComprehensiveTest.md` (نتائج الاختبار) · `docs/RealEstate_PropertyType_Usage.md` (مواصفة النوع والاستخدام).

## جدول المهام

| # | المهمة | الوكيل | الحالة |
|---|---|---|---|
| A | **المحاسبة**: تقييد الخزينة، ملكية المبنى وإيراد المملوك، تسجيل المالك في الضريبة وضريبة التجاري لحسابه، ضريبة العمولة، قيد ردّ التأمين، إلغاء تحويل المالك، قيود الصيانة (مالك/مستأجر/منشأة)، حالة «غير مرحّلة»، الحسابات الناقصة، طرق الدفع من النظام، **العملة** في الدفعات ودفتر المالك والصيانة | A | ✅ |
| B1 | **العقود**: الضريبة من إعدادات النظام، نوع العقد والضريبة من استخدام الوحدة، العملة في العقد، تحقق الخطوات، التعديل يلحق بالجدول، التجديد بلا تداخل وترحيل التأمين، الإنهاء بتاريخ صحيح، منع إعادة بناء الملغى، إخفاء «بيع»، الطباعة | B1 | ✅ |
| B2 | **المباني والوحدات**: لماذا لا تظهر الطوابق عند إنشاء وحدة، أتمتة النوع والاستخدام حسب المواصفة، استخدام الوحدة، الوحدة التلقائية، خطأ 500 لرقم الوحدة المحذوفة، خلل `activeContract`، منع حذف وحدة عليها عروض أو صيانة، ترقيم المجمعات | B2 | ✅ |
| C | **الربط مع الإعدادات**: التسلسل، قوالب المستندات (عقد وإيصال)، أنواع الإشعارات وتجميع التنبيهات وتقييدها بالفرع، النسخ الاحتياطي والتصدير، حماية سجل الملّاك | C | ✅ |
| D | **الصلاحيات والأدوار ونطاق البيانات والفروع**: الموظف يرى بياناته وبيانات من يديرهم، الفروع، الفرع الرئيسي يرى الكل، `data_scope_*`، صلاحيات المستأجرين، إصلاح خطأ 500 في تعديل العرض، العلاقات والمهام | D | ✅ |
| T | **الاختبار النهائي** كمستخدم في المتصفح وإصلاح ما يظهر | الرئيسي | ✅ |

## قواعد مشتركة لكل الوكلاء

1. **لا** `git commit / stash / reset / checkout / pull`. **لا** `php artisan migrate` العامة (توجد ترحيلات معلّقة لوحدات أخرى). شغّل ترحيلك وحده: `php artisan migrate --path=Modules/RentalManagement/database/migrations/<file>.php --force`.
2. عدّل **ملفاتك فقط** (القائمة في مهمتك). إن احتجت تغييراً في ملف وكيل آخر فاكتبه في تقريرك النهائي ولا تنفّذه.
3. الترحيلات **قابلة للتكرار** (`Schema::hasColumn / hasTable`). قاعدة التطوير تحمل أصلاً من عمل سابق مفقود: `re_contracts.tax_id`، `re_payments.payment_method_id`، الحسابات 26 «تأمينات المستأجرين» و44 «إيرادات الإيجار» و45 «إيرادات إدارة الأملاك» ومفاتيحها في `re_settings`، وصفوف `notification_settings` لأنواع `realestate_*`. وجدول `migrations` فيه أسماء `2026_10_03_100000_create_real_estate_accounts` و`2026_10_03_110000_add_tax_id_to_re_contracts` و`2026_10_03_120000_integrate_re_with_system_settings`: **لا تعِد استعمال هذه الأسماء**، استعمل بادئة `2026_10_05_`.
4. استعمل أداة Edit لأي نص فيه `\` (sed و perl يأكلان الشرطة المعكوسة). `python3` معطّل على هذا الجهاز.
5. بعد كل تعديل: `php -l` للملف، و`vendor/bin/pint <ملفاتك فقط>`.
6. نصوص الواجهة والتعليقات بالعربية كما في بقية الوحدة. لا `View::composer('*')` جديدة.
7. فحص الموظف دائماً بـ`isEmployee()` لا باسم الدور. لا تعتمد على `job_roles`.
8. الأرقام التلقائية (الأكواد) يصدرها الخادم بـ`next_document_number()` ولا تُقبل من الطلب.
9. التقرير النهائي: الملفات التي عدّلتها، الترحيلات التي شغّلتها، ما تحققت منه فعلاً (بأمر ونتيجته)، وما لم تنجزه.

## العقود المشتركة بين الوكلاء (أسماء ثابتة)

| الاسم | المالك | المعنى |
|---|---|---|
| `re_buildings.ownership` (`managed` / `own`) | A | مبنى لمالك خارجي أم للمنشأة. الافتراضي `managed`. بلا مالك = `own` |
| `re_owners.is_vat_registered` (bool)، `re_owners.tax_number` | A | تسجيل المالك في الضريبة |
| `re_units.usage_type` (`residential` / `commercial`) | B2 | استخدام الوحدة، المعتمد في العقد والضريبة |
| `re_contracts.tax_id` ← `tax_sittings.id`، مع `tax_rate` و`tax_included` لقطة | B1 | ضريبة العقد من إعدادات النظام |
| `re_contracts.currency`، `re_payments.currency`، `re_owner_entries.currency`، `re_maintenance_costs.currency` | B1 للعقد، A للبقية | لقطة من `App\Support\CurrencySettings::code()` وقت الإنشاء. لا تتغير بتغيّر عملة النظام |
| `Modules\RentalManagement\Support\RealEstateMoney::format($amount, ?string $currency)` | A | عرض مبلغ مع عملته المسجَّلة (أو عملة النظام إن غابت). يستعمله الجميع في الواجهات |
| `Modules\RentalManagement\Support\RealEstateTemplates::render(string $type, int $id, $template = null): ?string` | C | يصيّر قالب المستند أو يعيد null. الأنواع: `realestate_contract`، `realestate_receipt` |
| `Modules\RentalManagement\Support\RealEstateNotifications::*` | C | ثوابت أنواع الإشعارات: `CONTRACT_EXPIRING`، `PAYMENT_DUE`، `PAYMENT_OVERDUE`، `MAINTENANCE_ASSIGNED`، `MAINTENANCE_CLOSED`، `TASK_ASSIGNED` |
| مفاتيح التسلسل `re-complex`، `re-owner-transfer`، `re-deposit-settlement` | C | تُضاف إلى `App\Support\DocumentSections` |
| إعداد `default_tax_id`، `maintenance_expense_account_id`، `commission_taxable` في `RealEstateSettings` | A | — |
| `GET realestate/units/{id}/info` يعيد أيضاً: `usage_type`، `owner_id`، `owner_vat_registered`، `ownership` | B2 | يقرؤه نموذج العقد |

## ملكية الملفات

| الوكيل | الملفات |
|---|---|
| A | `Services/PaymentService.php`، `Services/OwnerLedgerService.php`، `Services/PropertyAccountService.php`، `Http/Controllers/RealEstate/{Payments,Owners,Maintenance,Settings}Controller.php`، `Models/{Payment,PaymentAllocation,PaymentSchedule,Owner,OwnerEntry,MaintenanceRequest,MaintenanceCost}.php`، `Support/RealEstateSettings.php`، `Support/RealEstateMoney.php`، `Support/RealEstateAccounts.php`، واجهات `payments/*`، `owners/*`، `maintenance/*`، `settings/*` |
| B1 | `Http/Controllers/RealEstate/ContractsController.php`، `Services/ContractService.php`، `Models/{Contract,ContractTerm,ContractAmendment,ContractRenewal,TermTemplate}.php`، واجهات `contracts/*`، `components/stepper-scripts.blade.php` |
| B2 | `Http/Controllers/RealEstate/{Buildings,Units}Controller.php`، `Models/{Building,Complex,Floor,Unit,Amenity}.php`، `Support/RealEstateVocabulary.php`، واجهات `buildings/*`، `units/*` |
| C | `app/Support/DocumentSections.php`، `app/Services/DocumentTemplateDataService.php`، `app/Services/DocumentTemplateRenderer.php`، `Modules/Sitting/Http/Controllers/DocumentTemplateController.php`، `resources/lang/{ar,en}/settings.php`، `app/Models/{NotificationSetting,Log}.php`، `database/seeders/NotificationSettingSeeder.php`، `app/Services/Backup/{ArabicSchemaTranslator,DataSectionCatalog}.php`، `Modules/RentalManagement/Console/*`، `Support/RealEstateTemplates.php`، `Support/RealEstateNotifications.php` |
| D | `Http/Controllers/RealEstate/{RealEstate,Tenants,Relationships,Listings,Tasks,Dashboard,Reports}Controller.php`، `Models/{RealEstateModel,Listing,Viewing,Task,CustomerRelationship,TenantProfile,SavedReport}.php`، `Services/{TenantService,ReportService,AttentionService}.php`، `Support/RealEstateRoles.php`، `app/Constants/permissions/RealEstatePermissions.php`، واجهات `tenants/*`، `relationships/*`، `listings/*`، `tasks/*`، `dashboard/*`، `reports/*`، `layouts/menu.blade.php` |

`Support/RealEstateVocabulary.php` يملكه B2. من احتاج قيمة جديدة فيه من الوكلاء الآخرين يعرّفها ثابتاً في ملف يملكه هو.

</div>


## الجولة الأخيرة (2026-10-05) — ما أُضيف بعد اكتمال المهام

| # | المطلوب | ما تمّ | الحالة |
|---|---|---|---|
| 1 | توضيح «علاقات العملاء» | صارت «اهتمامات العملاء»: سطر شرح، «نوع الاهتمام» و«محلّ الاهتمام»، تبويبات (الاهتمامات / مستأجرون حاليون / الكل) | ✅ |
| 2 | قسم «طلبات العملاء» | قسم مستقل في القائمة بقائمته ونموذجه وصفحته و«عروض مطابقة»؛ «العروض العقارية» للعروض فقط | ✅ |
| 3 | المدة أو تاريخ النهاية | اختيار مدّة يحسب تاريخ النهاية ويقفله؛ «تاريخ محدّد» يفتحه للكتابة — لا الاثنان معاً | ✅ |
| 4 | العملة في كل مبلغ | الجداول والبطاقات وعناوين حقول المبالغ والتقارير | ✅ |
| 5 | قيود الصيانة | تُنشأ للأنواع الثلاثة (مالك / منشأة / مستأجر) مع العكس عند الحذف؛ التكاليف القديمة بلا قيد تظهر بعلامة «بلا قيد محاسبي» وزر «قيِّد الآن» | ✅ |
| 6 | إنهاء/إلغاء العقد والاسترداد | الإنهاء يقيّد تسوية التأمين (ردّ + خصم)؛ الإلغاء ممنوع مع دفعات مرحّلة؛ إلغاء الدفعة يعكس قيدها؛ العقود القديمة بتأمين غير مسوّى يظهر عليها تنبيه برابط التسوية | ✅ |

أخطاء وُجدت وأُصلحت في هذه الجولة: حذف تكلفة صيانة على المستأجر بعد إلغاء دفعتها كان يُرفض؛ إعادة بناء جدول العقد كانت تحذف أقساط الصيانة؛ حالة «مُنهى» لم تكن محسوبة في كشف المالك والتنبيهات؛ قوائم العملاء الثقيلة في فلاتر العقود والدفعات (الصفحة من ~570KB إلى ~285KB)؛ تمييز القائمة الجانبية لصفحات الطلبات.
## ربط الشيكات بالحسابات (2026-10-05)

| الخطوة | أين | ما يحدث |
|---|---|---|
| تسجيل دفعة بشيك | العقارات ← تسجيل دفعة | يُنشأ الشيك في «الحسابات ← الشيكات المستلمة»؛ قيده: مدين شيكات تحت التحصيل / دائن «دفعات عقارية بشيكات تحت التحصيل». الدفعة «شيك تحت التحصيل» بلا قيد ولا حركة للمالك |
| إيداع ثم تحصيل | الحسابات ← الشيكات المستلمة | قيد البنك هناك، وتُرحَّل دفعة العقار تلقائياً: مدين الحساب الوسيط / دائن المالك والعمولة والتأمين |
| ارتجاع أو حذف الشيك | الحسابات ← الشيكات المستلمة | تُلغى دفعة العقار تلقائياً وتعود الأقساط مفتوحة |
| إلغاء الدفعة | العقارات | يُسحب الشيك بقيد عكسي ما دام لم يُودَع؛ بعد الإيداع يُعالَج من شاشة الشيكات؛ بعد التحصيل لا تُلغى |
