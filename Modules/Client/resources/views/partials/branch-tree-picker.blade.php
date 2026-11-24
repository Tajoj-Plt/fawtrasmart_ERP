{{--
    «العميل تابع لـ» — قائمةٌ واحدة.

    كانت قائمةً متدرّجة: مستوًى لكل طبقة، تظهر قائمةٌ جديدة كلّما اخترت.
    فمن أراد عميلاً على العمق الرابع يفتح أربع قوائم متتابعة، وشاشةُ إضافة
    العميل تمتلئ بها. صارت قائمةً واحدة بسهمٍ واحد: كلُّ خيارٍ يحمل مساره
    كاملاً من الأعلى ونوعَه، والبحثُ داخلها يختصر الطريق — وهو ما كان
    التدرّجُ يحاول أن يفعله بأربع خطوات.

    ولا JavaScript هنا: `select2` تُهيَّأ عامّةً لكل `.select2` في القالب
    (`app-assets/js/scripts/forms/select/form-select2.js`)، فتأتي معها
    خانةُ البحث ورسائلُها بالعربية.

    @param array $clientBranchTreeNodes  [{id, name, parent_id, selectable, node_type, type_label}]
    @param int|null $selectedBranchId    العقدة المختارة مسبقاً (التعديل / old())
--}}

@php
    $pickerNodes = $clientBranchTreeNodes ?? [];
    $pickerSelected = $selectedBranchId ?? null;

    $byId = [];
    foreach ($pickerNodes as $node) {
        $byId[$node['id']] = $node;
    }

    // المسارُ من الأعلى إلى العقدة — الاسمُ وحده لا يقول تحت أيّ فرعٍ يقع،
    // وأسماءٌ متشابهة تحت آباءٍ مختلفين لا تُميَّز بغيره
    $pathOf = function (int $id) use ($byId) {
        $names = [];
        $cursor = $id;
        $guard = 0;
        while (isset($byId[$cursor]) && $guard++ < 25) {
            array_unshift($names, $byId[$cursor]['name']);
            $parent = $byId[$cursor]['parent_id'] ?? null;
            $cursor = ($parent && isset($byId[$parent])) ? $parent : null;
            if (! $cursor) {
                break;
            }
        }

        return implode(' ← ', $names);
    };

    $pickerOptions = [];
    foreach ($pickerNodes as $node) {
        if (($node['selectable'] ?? true) === false) {
            continue;
        }

        $path = $pathOf((int) $node['id']);
        $pickerOptions[] = [
            'id' => (int) $node['id'],
            'label' => $path . (($node['type_label'] ?? '') !== '' ? ' — ' . $node['type_label'] : ''),
            'sort' => $path,
        ];
    }

    usort($pickerOptions, fn ($a, $b) => strcoll($a['sort'], $b['sort']) ?: strcmp($a['sort'], $b['sort']));
@endphp

<select name="client_tree_branch_id" id="client_tree_branch_id" class="form-control select2" required>
    <option value="">— اختر —</option>
    @foreach ($pickerOptions as $option)
        <option value="{{ $option['id'] }}" {{ (string) $pickerSelected === (string) $option['id'] ? 'selected' : '' }}>
            {{ $option['label'] }}
        </option>
    @endforeach
</select>

<small class="text-muted d-block mt-1">
    اختر الفرع أو المنطقة أو المنفذ الذي يتبع له العميل — اكتب للبحث داخل القائمة.
</small>
