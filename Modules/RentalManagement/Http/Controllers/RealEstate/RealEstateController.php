<?php

namespace Modules\RentalManagement\Http\Controllers\RealEstate;

use App\Constants\permissions\RealEstatePermissions;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Support\DocumentAttachments;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Models\Building;
use Modules\RentalManagement\Models\Contract;
use Modules\RentalManagement\Models\CustomerRelationship;
use Modules\RentalManagement\Models\Owner;
use Modules\RentalManagement\Models\Unit;
use Modules\RentalManagement\Support\RealEstateScope;

/**
 * أساس متحكّمات العقارات: نطاق البيانات، القوائم المشتركة، والمرفقات.
 */
abstract class RealEstateController extends Controller
{
    /** مجلّدات المرفقات تحت `assets/uploads`. */
    protected const UPLOADS = 'realestate';

    /**
     * نطاق البيانات على قوائم العقارات — {@see RealEstateScope}: `data_scope_all`
     * بلا قيد؛ محور الأشخاص (خاصّ/فريق) يرى ما أنشأه هو ومن يديرهم وما أُسند
     * إليهم؛ محور المكان (فرع/منطقة) يرى فروعه ومعها ما يخصّه ومرؤوسيه؛ ثم
     * الفرع النشط في الشريط يضيّق الجميع.
     */
    protected function scoped($query, ?string $table = null)
    {
        return RealEstateScope::apply($query, $table);
    }

    /** يجلب سجلّاً داخل نطاقي أو 404 — فلا يُفتح بالرابط ما لا يظهر في القائمة. */
    protected function findScoped(string $model, $id, array $with = [])
    {
        return $this->scoped($model::query())->with($with)->findOrFail($id);
    }

    /** عملاء الملفّ المركزي داخل نطاقي — {@see RealEstateScope::clients()}. */
    protected function scopedClients()
    {
        return RealEstateScope::clients();
    }

    /** عميلٌ داخل نطاقي أو 404. */
    protected function findClient($id, array $with = []): Client
    {
        return $this->scopedClients()->with($with)->findOrFail($id);
    }

    /** يرفض معرّف عميلٍ خارج النطاق برسالة تحقّقٍ على حقله. */
    protected function assertClientInScope($clientId, string $field = 'client_id'): void
    {
        if ($clientId && ! $this->scopedClients()->whereKey($clientId)->exists()) {
            throw ValidationException::withMessages([$field => 'العميل المختار خارج نطاق بياناتك.']);
        }
    }

    /** يرفض إسناد سجلٍّ إلى مستخدمٍ خارج النطاق. */
    protected function assertAssignable($userId, string $field = 'assigned_user_id'): void
    {
        if (! RealEstateScope::canAssignTo($userId)) {
            throw ValidationException::withMessages([$field => 'لا يمكنك الإسناد إلى مستخدمٍ خارج فريقك أو فرعك.']);
        }
    }

    /**
     * بحث العملاء لحقول الاختيار (select2 بـAjax) — بديل تضمين آلاف العملاء
     * في كلّ صفحة. النتيجة داخل النطاق نفسه، و`tenants=1` يقصرها على المستأجرين.
     */
    public function clientSearch(Request $request)
    {
        // لا صلاحية بعينها: الحقل يظهر في شاشاتٍ عدّة — يكفي أن يملك صلاحيةً في الوحدة
        abort_unless(auth()->user()->hasAnyPermissionWithPrefix(RealEstatePermissions::PREFIX), 403);

        $term = trim((string) $request->input('q', ''));
        $query = $this->scopedClients()->select('id', 'trade_name', 'first_name', 'last_name', 'code', 'phone', 'mobile');

        if ($request->boolean('tenants')) {
            $query->whereIn('id', $this->tenantClientIds());
        }

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(fn ($w) => $w->where('trade_name', 'like', $like)->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)->orWhere('code', 'like', $like)
                ->orWhere('phone', 'like', $like)->orWhere('mobile', 'like', $like));
        }

        $page = $query->orderBy('trade_name')->simplePaginate(20);

        return response()->json([
            'results' => collect($page->items())->map(fn (Client $c) => [
                'id' => $c->id,
                'text' => ($c->display_name ?? $c->trade_name).($c->code ? ' — '.$c->code : ''),
                'phone' => $c->mobile ?: $c->phone,
            ])->values(),
            'pagination' => ['more' => $page->hasMorePages()],
        ]);
    }

    protected function perPage(Request $request): int
    {
        return min(100, max(5, (int) $request->input('per_page', 10)));
    }

    /**
     * قائمة العملاء كاملةً داخل النطاق — ثقيلةٌ (آلاف الصفوف)؛ الشاشات الجديدة
     * تستعمل `clientSearch` عبر `components.client-select` بدلها.
     */
    protected function clients()
    {
        return $this->scopedClients()->select('id', 'trade_name', 'first_name', 'last_name', 'code', 'phone')->orderBy('trade_name')->get();
    }

    /**
     * معرّفات المستأجرين: من يحمل دور «مستأجر» أو كان طرفاً مستأجراً في عقد.
     * مصدرٌ واحد لشاشة المستأجرين ولحقل «المستأجر / العميل» في العقد.
     *
     * @return array<int, int>
     */
    protected function tenantClientIds(): array
    {
        return $this->scoped(CustomerRelationship::query())->where('roles', 'like', '%"tenant"%')->pluck('client_id')
            ->merge($this->scoped(Contract::query())->pluck('tenant_client_id'))
            ->filter()->unique()->values()->all();
    }

    /** ملّاك العقارات النشطون — ما يُختار منه المالك في المباني والعقود (لا من ملفّ العملاء). */
    protected function owners(?int $include = null)
    {
        return $this->scoped(Owner::query())->where(fn ($q) => $q->where('status', 'active')->when($include, fn ($w) => $w->orWhere('id', $include)))
            ->select('id', 'code', 'name', 'agreement_type', 'agreement_value')->orderBy('name')->get();
    }

    protected function users()
    {
        // من يجوز الإسناد إليهم: الكلّ لمن هو بلا قيد، وإلّا فريقه/فرعه
        return User::query()->whereNotNull('role_id')
            ->when(RealEstateScope::assignableUserIds() !== null, fn ($q) => $q->whereIn('id', RealEstateScope::assignableUserIds()))
            ->select('id', 'name')->orderBy('name')->get();
    }

    protected function buildings()
    {
        return $this->scoped(Building::query())->select('id', 'code', 'name_ar', 'name_en', 'default_treasury_account_id')->orderBy('name_ar')->get();
    }

    protected function units(?array $occupancy = null)
    {
        return $this->scoped(Unit::query())->with('building:id,name_ar,name_en')
            ->when($occupancy, fn ($q) => $q->whereIn('occupancy_status', $occupancy))
            ->orderBy('building_id')->orderBy('unit_no')->get();
    }

    /**
     * يخزّن الملفات المرفوعة ويضمّها إلى الموجود — القيمة جاهزة للعمود.
     */
    protected function storeFiles(Request $request, string $field, string $folder, ?string $existing = null): ?string
    {
        DocumentAttachments::validate($request, $field);

        $files = DocumentAttachments::files($request, $field);
        $kept = array_values(array_diff(
            DocumentAttachments::paths($existing),
            (array) $request->input('remove_'.$field, []),
        ));

        if ($files === [] && count($kept) === count(DocumentAttachments::paths($existing))) {
            return $existing;
        }

        return DocumentAttachments::encode(array_merge(
            $kept,
            DocumentAttachments::storeMany($files, self::UPLOADS.'/'.$folder),
        ));
    }

    protected function ok(Request $request, string $message, ?string $redirect = null)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'redirect' => $redirect]);
        }

        return ($redirect ? redirect($redirect) : back())->with('success', $message);
    }
}
