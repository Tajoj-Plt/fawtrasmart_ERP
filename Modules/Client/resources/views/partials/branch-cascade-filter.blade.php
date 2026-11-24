{{-- فلتر الفروع — مدمج في صف الفلاتر --}}
<div class="rm-branch-cascade rm-branch-cascade--inline" id="branchCascadeWrapper">
    <div class="rm-branch-cascade-levels" id="branchCascadeContainer"></div>
</div>

<script>
(function() {
    const CASCADE_BRANCHES = @json($cascadeBranches ?? []);
    const INITIAL_BRANCH_ID = @json($selectedBranchId ?? null);
    const MANG_CLIENT_URL = @json(route('clients.mang_client'));

    function getRootBranches() {
        const ids = {};
        CASCADE_BRANCHES.forEach(function(b) { ids[b.id] = true; });
        return CASCADE_BRANCHES.filter(function(b) {
            return !b.parent_id || !ids[b.parent_id];
        });
    }

    function getChildBranches(parentId) {
        return CASCADE_BRANCHES.filter(function(b) {
            return String(b.parent_id) === String(parentId);
        });
    }

    function hasChildren(branchId) {
        return getChildBranches(branchId).length > 0;
    }

    function getAncestorPath(branchId) {
        const path = [];
        let id = parseInt(branchId, 10);
        let safety = 25;
        while (id && safety-- > 0) {
            const node = CASCADE_BRANCHES.find(function(x) { return parseInt(x.id, 10) === id; });
            if (!node) break;
            path.unshift(parseInt(node.id, 10));
            id = node.parent_id ? parseInt(node.parent_id, 10) : null;
        }
        return path;
    }

    /* `type_label` نوعُ العرض من الخادم (`Branch::displayNodeType`) — كان `is_territory`
       وحدَه يسمّي كلَّ مجموعةٍ ومنفذٍ «منطقة». */
    function branchLabel(b) {
        return (b.name || '') + (b.type_label ? ' — ' + b.type_label : '');
    }

    function levelLabel(level) {
        if (level === 0) return 'الفرع الرئيسي';
        return 'المستوى ' + (level + 1);
    }

    function navigateBranchFilter(branchId) {
        const url = new URL(MANG_CLIENT_URL, window.location.origin);
        if (branchId) {
            url.searchParams.set('branch_id', String(branchId));
        } else {
            url.searchParams.delete('branch_id');
        }
        url.searchParams.delete('cascade_node');
        window.location.href = url.toString();
    }

    function destroySelect2In(el) {
        if (!window.jQuery || !$.fn.select2) return;
        $(el).find('select.select2').each(function() {
            const $s = $(this);
            if ($s.data('select2')) {
                $s.select2('destroy');
            }
        });
    }

    function removeLevelsAfter(level) {
        document.querySelectorAll('#branchCascadeContainer .rm-cascade-level').forEach(function(wrap) {
            const lvl = parseInt(wrap.getAttribute('data-level'), 10);
            if (lvl > level) {
                destroySelect2In(wrap);
                wrap.remove();
            }
        });
    }

    function addLevel(level, parentId, selectedId) {
        const branches = level === 0 ? getRootBranches() : getChildBranches(parentId);
        if (!branches.length) {
            return false;
        }

        const container = document.getElementById('branchCascadeContainer');
        if (!container) return false;

        const wrap = document.createElement('div');
        wrap.className = 'rm-cascade-level rm-fg-item rm-fg-cascade';
        wrap.setAttribute('data-level', String(level));

        const label = document.createElement('label');
        label.className = 'rm-fg-label rm-fg-label--level';
        label.innerHTML = '<span>' + levelLabel(level) + '</span>';

        const control = document.createElement('div');
        control.className = 'rm-filter-control';

        const select = document.createElement('select');
        select.className = 'select2 rm-filter-select2 rm-cascade-select';
        select.setAttribute('data-level', String(level));
        select.setAttribute('data-placeholder', level === 0 ? 'جميع الفروع' : 'اختر الفرع');
        select.setAttribute('aria-label', levelLabel(level));

        const emptyOpt = document.createElement('option');
        emptyOpt.value = '';
        emptyOpt.textContent = level === 0 ? 'جميع الفروع' : '— اختر —';
        select.appendChild(emptyOpt);

        branches.forEach(function(b) {
            const opt = document.createElement('option');
            opt.value = String(b.id);
            opt.textContent = branchLabel(b, level);
            if (selectedId && String(selectedId) === String(b.id)) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });

        control.appendChild(select);
        wrap.appendChild(label);
        wrap.appendChild(control);
        container.appendChild(wrap);

        if (typeof window.initRmFilterSelect2 === 'function') {
            window.initRmFilterSelect2(wrap);
        }

        $(select).on('change.rmCascade', function() {
            const val = $(this).val();
            removeLevelsAfter(level);

            if (val) {
                navigateBranchFilter(val);
                return;
            }

            if (level === 0) {
                navigateBranchFilter('');
                return;
            }

            const prevSelect = document.querySelector(
                '#branchCascadeContainer select[data-level="' + (level - 1) + '"]'
            );
            const prevVal = prevSelect ? prevSelect.value : '';
            navigateBranchFilter(prevVal || '');
        });

        return true;
    }

    window.initClientBranchCascade = function() {
        const container = document.getElementById('branchCascadeContainer');
        if (!container) return;
        destroySelect2In(container);
        container.innerHTML = '';

        const path = INITIAL_BRANCH_ID ? getAncestorPath(INITIAL_BRANCH_ID) : [];
        const firstSel = path.length ? path[0] : null;
        addLevel(0, null, firstSel);

        for (let i = 0; i < path.length; i++) {
            const parentId = path[i];
            const nextId = path[i + 1] || null;
            if (hasChildren(parentId)) {
                addLevel(i + 1, parentId, nextId);
            }
        }
    };

    window.bootClientBranchCascade = function() {
        if (typeof window.initClientBranchCascade === 'function') {
            window.initClientBranchCascade();
        }
    };
})();
</script>
