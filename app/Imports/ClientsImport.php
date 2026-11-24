<?php
namespace App\Imports;

use App\Helpers\ClientSettingsHelper;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Client;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * يقرأ الأعمدة بترتيبها الثابت كما في القالب المُنزَّل
 * (`ClientController::getImportTemplate`) — لا بأسماء رؤوسها.
 *
 * كانت الواجهة تستعمل `WithHeadingRow`، التي تحوّل نص كل رأس عمود إلى
 * مفتاحٍ عبر `Str::slug()`؛ لكن رؤوس القالب عربية بالكامل («كود العميل»،
 * «الاسم التجاري»، ...) و`Str::slug()` يحوّلها صوتياً إلى لاتينية مشوَّهة
 * («kod_alaamyl»، «alasm_altgary») لا تُطابق أياً من المفاتيح التي كان
 * `model()` يتوقّعها (`trade_name`, `code`, ...). فكل صفٍّ كان يفشل شرط
 * `empty($row['trade_name'])` صامتاً ويُتجاوَز — الاستيراد "ينجح" برسالة
 * نجاح ولا يُنشئ عميلاً واحداً. الحل: القراءة بترتيب العمود لا باسمه.
 *
 * ترتيب أعمدة القالب (A→O):
 * كود العميل (يُهمَل — يُولَّد تلقائياً كبقية العملاء)، الاسم التجاري،
 * اسم العميل، اسم العائلة، رقم الهاتف، رقم الجوال، البريد الإلكتروني،
 * التصنيف (بالاسم)، الفرع (بالاسم)، المنطقة، الحي، العنوان،
 * الرصيد الافتتاحي، حد الائتمان، مدة الائتمان بالأيام.
 */
class ClientsImport implements ToModel, WithStartRow
{
    public function startRow(): int
    {
        return 2;
    }

    public function model(array $row)
    {
        $tradeName = trim((string) ($row[1] ?? ''));
        $firstName = trim((string) ($row[2] ?? ''));
        $lastName = trim((string) ($row[3] ?? ''));

        if ($tradeName === '' && $firstName === '') {
            return null; // صفّ بلا اسمٍ على الإطلاق — تجاوز
        }

        if ($tradeName === '') {
            $tradeName = trim($firstName.' '.$lastName);
        }

        $categoryName = trim((string) ($row[7] ?? ''));
        $categoryId = $categoryName !== ''
            ? CategoriesClient::where('name', $categoryName)->value('id')
            : null;

        $branchName = trim((string) ($row[8] ?? ''));
        $branchId = $branchName !== ''
            ? Branch::withoutGlobalScopes()->where('name', $branchName)->value('id')
            : null;

        return new Client([
            'trade_name' => $tradeName,
            'first_name' => $firstName ?: null,
            'last_name' => $lastName ?: null,
            'phone' => trim((string) ($row[4] ?? '')) ?: null,
            'mobile' => trim((string) ($row[5] ?? '')) ?: null,
            'email' => trim((string) ($row[6] ?? '')) ?: null,
            'category_id' => $categoryId,
            'branch_id' => $branchId,
            'region' => trim((string) ($row[9] ?? '')) ?: null,
            'street1' => trim((string) ($row[11] ?? '')) ?: null,
            'opening_balance' => is_numeric($row[12] ?? null) ? $row[12] : null,
            'credit_limit' => is_numeric($row[13] ?? null) ? $row[13] : null,
            'credit_period' => is_numeric($row[14] ?? null) ? $row[14] : null,
            // نفس الافتراضات المطبَّقة عند إضافة عميل يدوياً من الشاشة —
            // راجع ClientController::store()
            'status_id' => ClientSettingsHelper::defaultNewClientStatusId(),
            'registration_stage_id' => ClientSettingsHelper::defaultRegistrationStageId(),
            'code' => next_document_number('customer'),
        ]);
    }
}
