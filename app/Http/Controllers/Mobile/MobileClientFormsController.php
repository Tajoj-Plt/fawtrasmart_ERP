<?php

namespace App\Http\Controllers\Mobile;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\CategoriesClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\Client\Http\Controllers\ClientController;

/**
 * قوائم الاختيار لنماذج الإنشاء في وحدة العملاء — من بيانات شاشة الإنشاء في الويب نفسها.
 * لا نموذج «منافس» في الويب (مودالٌ ضمن الشاشة لا شاشة create مستقلّة)، فلا قوائم له.
 */
class MobileClientFormsController extends Controller
{
    private const LIMIT = 60;

    public function options(Request $request, string $form): JsonResponse
    {
        $builders = [
            'clients' => fn () => $this->clients($request),
            'competitors' => fn () => ['options' => [], 'defaults' => [], 'flags' => []],
        ];

        abort_unless(isset($builders[$form]), 404, 'نموذج غير معروف.');

        $lists = $builders[$form]();
        $q = trim((string) $request->query('q', ''));
        $field = (string) $request->query('field', '');

        if ($field !== '') {
            abort_unless(isset($lists['options'][$field]), 404, 'حقل غير معروف.');

            return response()->json(['success' => true, 'data' => $this->filter($lists['options'][$field], $q)]);
        }

        $lists['options'] = collect($lists['options'])->map(fn ($items) => $this->filter($items, ''))->all();

        return response()->json(['success' => true, 'data' => $lists]);
    }

    /* ─────────────────────────── عميلٌ جديد ─────────────────────────── */

    private function clients(Request $request): array
    {
        $data = $this->viewData(app(ClientController::class)->create());
        $user = $request->user();

        $branchOptions = collect($data['clientBranchTreeOptions'] ?? [])->map(fn ($b) => [
            'id' => (int) $b->id,
            'label' => trim((($b->code ?? '') !== '' ? $b->code.' — ' : '').$b->name),
        ])->all();

        return [
            'options' => [
                'client_tree_branch_id' => $branchOptions,
                'category_id' => $this->toOptions($data['categories'] ?? CategoriesClient::all()),
            ],
            'defaults' => [],
            'flags' => [
                'requirePhone' => ClientSettingsHelper::clientRequirePhone(),
                'requireMobile' => ClientSettingsHelper::clientRequireMobile(),
                'requireEmail' => ClientSettingsHelper::clientRequireEmail(),
                'requireTaxNumber' => ClientSettingsHelper::clientRequireTaxNumber(),
                'requireCommercialRegistration' => ClientSettingsHelper::clientRequireCommercialRegistration(),
                'requireCategory' => ClientSettingsHelper::clientRequireCategory(),
                'locationRequired' => ClientSettingsHelper::isLocationRequired(),
                'canAssignEmployees' => (bool) ($user && $user->can('clients_assign_employees')),
            ],
        ];
    }

    /* ─────────────────────────── أدوات ─────────────────────────── */

    private function viewData(mixed $view): array
    {
        abort_unless($view instanceof View, 422, 'تعذّر تحميل بيانات النموذج.');

        return $view->getData();
    }

    private function toOptions(iterable $items, string $nameColumn = 'name'): array
    {
        return Collection::make($items)->map(function ($row) use ($nameColumn) {
            $get = fn (string $k) => is_array($row) ? ($row[$k] ?? null) : ($row->{$k} ?? null);
            $name = $get($nameColumn) ?? $get('name') ?? $get('trade_name');
            $code = $get('code');

            return [
                'id' => (int) $get('id'),
                'label' => trim(($code !== null && $code !== '' && $code !== 0 && $code !== '0' ? $code.' — ' : '').$name),
            ];
        })->values()->all();
    }

    private function filter(array $items, string $q): array
    {
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $items = array_values(array_filter($items, fn ($o) => str_contains(mb_strtolower($o['label']), $needle)));
        }

        return array_slice($items, 0, self::LIMIT);
    }
}
