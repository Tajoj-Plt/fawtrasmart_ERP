/* ============================================================
   Account View - Tree + Actions
   Lazy tree rendering, instant search, AJAX recalc
   ============================================================ */
(function () {
    'use strict';

    if (!window.ACV) return;

    // ----- State -----
    var state = {
        flat: [],          // كل الحسابات بشكل مسطح
        byParent: {},      // {parent_id: [accounts]}
        byId: {},          // {id: account}
        rendered: {},      // تتبع العقد التي تم تركيب أبنائها (lazy render)
        currentId: window.ACV.currentAccountId || null,
        searchTimer: null,
        loaded: false
    };

    // أقصى عدد نتائج تُرسم في الشجرة — الباقي يُطلب بتضييق البحث.
    // بلا حدّ، حرفٌ واحد يطابق آلاف الحسابات فيتجمد المتصفح.
    var SEARCH_LIMIT = 150;

    /*
        تطبيع النص العربي للبحث: توحيد الألف والتاء المربوطة والياء، وحذف
        التشكيل والتطويل. يُحسب مرةً عند تحميل الشجرة لا عند كل ضغطة.
    */
    function normalize(s) {
        return String(s == null ? '' : s)
            .toLowerCase()
            .replace(/[ً-ْـ]/g, '')
            .replace(/[أإآٱ]/g, 'ا')
            .replace(/ة/g, 'ه')
            .replace(/ى/g, 'ي');
    }

    var $tree = document.getElementById('acvTree');
    var $loading = document.getElementById('acvTreeLoading');
    var $empty = document.getElementById('acvTreeEmpty');
    var $search = document.getElementById('acvTreeSearch');
    var $searchClear = document.getElementById('acvTreeClear');
    var $branchFilter = document.getElementById('acvBranchFilter');

    /*
        الأب الفعلي للعقدة في الشجرة. حسابٌ أبوه غير موجود (محذوف أو خارج
        فلتر الفرع) أو سلسلةُ آبائه تدور على نفسها (parent_id = id مثلاً)
        لا يصل إلى الجذر أبداً فيختفي من الشجرة ومن البحث — فيُعلَّق على
        الجذر (0) ليظهر ويمكن تصحيحه.
    */
    function effectiveParent(n) {
        if (!n.parent_id) return 0;
        if (!state.byId[n.parent_id]) { n._broken = true; return 0; }
        // هل تعود سلسلة الآباء إلى الحساب نفسه؟ (هو عضوٌ في الحلقة)
        // أحفاد الحساب المكسور يبقون تحته — هو وحده يُعلَّق على الجذر.
        var seen = {};
        var cur = state.byId[n.parent_id];
        while (cur) {
            if (cur.id === n.id) { n._broken = true; return 0; }
            if (seen[cur.id] || !cur.parent_id) break;
            seen[cur.id] = true;
            cur = state.byId[cur.parent_id];
        }
        return n.parent_id;
    }

    // ----- API: load flat tree -----
    function loadTree(branchId) {
        $loading.style.display = 'flex';
        $empty.style.display = 'none';
        $tree.innerHTML = '';

        var url = window.ACV.flatTreeUrl + (branchId && branchId !== 'all' ? '?branch_id=' + branchId : '');

        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (rows) {
                state.flat = rows;
                state.byParent = {};
                state.byId = {};
                state.rendered = {};
                rows.forEach(function (n, i) {
                    n._idx = i;
                    n._name = normalize(n.name);
                    n._code = String(n.code == null ? '' : n.code).toLowerCase();
                    state.byId[n.id] = n;
                });
                rows.forEach(function (n) {
                    n._pid = effectiveParent(n);
                    (state.byParent[n._pid] = state.byParent[n._pid] || []).push(n);
                });
                // من الشجرة نفسها لا من الخادم: حسابٌ أبوه نفسُه كان «مجلداً» بلا أبناء
                rows.forEach(function (n) { n.has_children = !!state.byParent[n.id]; });
                state.loaded = true;
                renderRoots();
                if (state.currentId) {
                    expandPathTo(state.currentId);
                }
            })
            .catch(function (err) {
                console.error('Tree load error', err);
                $tree.innerHTML = '<li class="acv-empty"><p>تعذر تحميل الشجرة</p></li>';
            })
            .finally(function () { $loading.style.display = 'none'; });
    }

    // ----- Render roots (top level) -----
    function renderRoots() {
        var roots = state.byParent[0] || [];
        if (roots.length === 0) {
            $empty.style.display = 'flex';
            return;
        }
        var frag = document.createDocumentFragment();
        roots.forEach(function (n) { frag.appendChild(buildNode(n)); });
        $tree.appendChild(frag);
    }

    // ----- Build a single tree <li> node -----
    function buildNode(account) {
        var li = document.createElement('li');
        li.className = 'acv-tree-node' + (account.has_children ? '' : ' acv-tree-node--leaf');
        li.setAttribute('data-id', account.id);
        li.setAttribute('role', 'treeitem');

        var row = document.createElement('a');
        row.className = 'acv-tree-node__row';
        row.href = window.ACV.showAccountUrl + '/' + account.id;

        var toggle = document.createElement('span');
        toggle.className = 'acv-tree-node__toggle' + (account.has_children ? '' : ' acv-tree-node__toggle--leaf');
        toggle.innerHTML = '<i class="feather icon-chevron-right"></i>';

        var icon = document.createElement('span');
        icon.className = 'acv-tree-node__icon';
        icon.innerHTML = account.has_children
            ? '<i class="feather icon-folder"></i>'
            : '<i class="feather icon-file-text"></i>';

        var label = document.createElement('span');
        label.className = 'acv-tree-node__label';
        label.textContent = account.name;
        label.title = account.name + ' (#' + account.code + ')';
        if (account._broken) {
            // أبوه مفقود أو أبوه نفسُه — ظاهرٌ على الجذر ليُصحَّح من شاشة التعديل
            label.title += ' — الحساب الأب غير صحيح، عدّل الحساب واختر أباً';
            label.style.color = 'var(--acv-danger, #dc2626)';
        }

        var code = document.createElement('span');
        code.className = 'acv-tree-node__code';
        code.textContent = '#' + account.code;

        row.appendChild(toggle);
        row.appendChild(icon);
        row.appendChild(label);
        row.appendChild(code);

        // فتح/إغلاق العقدة يُعالَج بمستمعٍ واحد على الشجرة (bindTreeToggle)
        // بدل مستمعٍ لكل عقدة — والنقر على الصف نفسه يتنقّل عبر <a href>.

        // Highlight if current
        if (state.currentId == account.id) {
            row.classList.add('is-active');
        }

        li.appendChild(row);

        var ul = document.createElement('ul');
        ul.className = 'acv-tree-node__children';
        ul.setAttribute('role', 'group');
        li.appendChild(ul);

        return li;
    }

    // ----- Lazy: render children on demand -----
    function ensureChildrenRendered(li, account) {
        if (state.rendered[account.id]) return;
        var ul = li.lastChild; // .acv-tree-node__children دائماً آخر ابن (buildNode)
        var kids = state.byParent[account.id] || [];
        var frag = document.createDocumentFragment();
        kids.forEach(function (k) { frag.appendChild(buildNode(k)); });
        ul.appendChild(frag);
        state.rendered[account.id] = true;
    }

    function bindTreeToggle() {
        $tree.addEventListener('click', function (e) {
            var toggle = e.target.closest('.acv-tree-node__toggle');
            if (!toggle || !$tree.contains(toggle)) return;
            e.preventDefault();
            e.stopPropagation();
            var li = toggle.closest('li.acv-tree-node');
            var account = li && state.byId[li.getAttribute('data-id')];
            if (!account || !account.has_children) return;
            toggleNode(li, account);
        });
    }

    function toggleNode(li, account) {
        ensureChildrenRendered(li, account);
        li.classList.toggle('is-open');
    }

    function openNode(li, account) {
        ensureChildrenRendered(li, account);
        li.classList.add('is-open');
    }

    // ----- Expand the chain from root -> id -----
    function expandPathTo(id) {
        var node = state.byId[id];
        var chain = [];
        var seen = {};
        // `seen` يحمي من حسابٍ أبوه نفسُه أو من حلقةٍ في parent_id — بدونه يتجمد المتصفح
        while (node && !seen[node.id]) {
            seen[node.id] = true;
            chain.unshift(node);
            if (!node._pid) break;
            node = state.byId[node._pid];
        }
        // open every ancestor
        chain.forEach(function (n) {
            if (!n.has_children) return;
            var li = $tree.querySelector('li[data-id="' + n.id + '"]');
            if (li) openNode(li, n);
        });
        // scroll active into view
        var active = $tree.querySelector('.acv-tree-node__row.is-active');
        if (active && active.scrollIntoView) {
            active.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }

    // ----- Search (filters the rendered tree) -----
    function highlightLabel(li, name, term) {
        // التظليل على النص الأصلي؛ إن طابق البحثُ بعد التطبيع فقط (همزة/تشكيل)
        // يبقى الاسم بلا تظليل — المطابقة نفسها صحيحة.
        var idx = String(name).toLowerCase().indexOf(term.toLowerCase());
        if (idx === -1) return;
        var label = li.firstChild.childNodes[2]; // row > [toggle, icon, label, code]
        label.textContent = '';
        label.appendChild(document.createTextNode(name.substring(0, idx)));
        var em = document.createElement('span');
        em.className = 'acv-tree-highlight';
        em.textContent = name.substring(idx, idx + term.length);
        label.appendChild(em);
        label.appendChild(document.createTextNode(name.substring(idx + term.length)));
    }

    function showSearchNote(text) {
        var note = document.createElement('li');
        note.className = 'acv-tree-note';
        note.textContent = text;
        $tree.appendChild(note);
    }

    function performSearch(term) {
        term = (term || '').trim();
        $searchClear.style.display = term ? 'inline-flex' : 'none';

        if (!state.loaded) return;

        $tree.textContent = '';
        state.rendered = {};
        $empty.style.display = 'none';

        if (!term) {
            renderRoots();
            if (state.currentId) expandPathTo(state.currentId);
            return;
        }

        var q = normalize(term);
        var qCode = term.toLowerCase();

        /*
            مسحٌ واحد على الحقول المطبَّعة مسبقاً، مع ترتيبٍ حسب قوة المطابقة:
            كودٌ مطابق تماماً ← كودٌ يبدأ بالنص ← اسمٌ يبدأ بالنص ← احتواء.
            فإذا تجاوزت النتائج الحدّ، يُرسم الأقرب لا أوّل ما صادفناه.
        */
        var hits = [];
        for (var i = 0, n = state.flat.length; i < n; i++) {
            var a = state.flat[i];
            var ci = a._code.indexOf(qCode);
            var ni = a._name.indexOf(q);
            if (ci === -1 && ni === -1) continue;
            var score = a._code === qCode ? 0 : ci === 0 ? 1 : ni === 0 ? 2 : 3;
            hits.push({ a: a, s: score, i: i });
        }

        if (hits.length === 0) {
            $empty.style.display = 'flex';
            return;
        }

        var total = hits.length;
        if (total > SEARCH_LIMIT) {
            hits.sort(function (x, y) { return x.s - y.s || x.i - y.i; });
            hits.length = SEARCH_LIMIT;
        }

        // المطابقات وآباؤها، مجمّعةً حسب الأب بترتيب الشجرة الأصلي.
        // (لا نمرّ على كل أبناء الأب — حسابٌ أبٌ لآلاف العملاء يبقى رخيصاً)
        var isHit = {};
        var keep = {};
        var keptByParent = {};
        hits.forEach(function (h) {
            isHit[h.a.id] = true;
            var cur = h.a;
            var guard = 0;
            while (cur && !keep[cur.id] && guard++ < 50) {
                keep[cur.id] = true;
                var p = cur._pid;
                (keptByParent[p] = keptByParent[p] || []).push(cur);
                cur = p ? state.byId[p] : null;
            }
        });
        Object.keys(keptByParent).forEach(function (p) {
            keptByParent[p].sort(function (x, y) { return x._idx - y._idx; });
        });

        function renderFiltered(parentId, container) {
            (keptByParent[parentId] || []).forEach(function (k) {
                var li = buildNode(k);
                if (keptByParent[k.id]) {
                    renderFiltered(k.id, li.lastChild);
                    li.classList.add('is-open');
                    state.rendered[k.id] = true;
                }
                if (isHit[k.id]) highlightLabel(li, k.name || '', term);
                container.appendChild(li);
            });
        }

        var frag = document.createDocumentFragment();
        renderFiltered(0, frag);
        $tree.appendChild(frag);

        if (total > SEARCH_LIMIT) {
            showSearchNote('يُعرض أقرب ' + SEARCH_LIMIT + ' من ' + total.toLocaleString('en-US') + ' نتيجة — أكمل الكتابة لتضييق البحث');
        }
    }

    // ----- Recalc balance (single account) -----
    function bindRecalcButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.acv-btn-recalc');
            if (!btn) return;
            e.preventDefault();
            var id = btn.getAttribute('data-id');
            if (!id) return;

            btn.classList.add('is-loading');
            var oldHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

            fetch(window.ACV.recalcOneUrl(id), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.ACV.csrf,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        if (window.Swal) {
                            Swal.fire({ icon: 'success', title: 'تم', text: data.message, timer: 1400, showConfirmButton: false });
                        }
                        // إذا كان هذا الحساب الحالي - حدّث القيمة المعروضة
                        if (state.currentId == id) {
                            var $bal = document.getElementById('acvCurrentBalance');
                            if ($bal && typeof data.balance !== 'undefined') {
                                $bal.textContent = Number(data.balance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }
                        // حدِّث صف الابن إن وجد
                        var row = document.querySelector('tr.acv-row-child[data-account-id="' + id + '"]');
                        if (row && typeof data.balance !== 'undefined') {
                            var cell = row.querySelector('.acv-balance-cell');
                            if (cell) cell.textContent = Number(data.balance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    } else {
                        if (window.Swal) Swal.fire({ icon: 'error', title: 'خطأ', text: data.message || 'فشل التحديث' });
                    }
                })
                .catch(function (err) {
                    console.error(err);
                    if (window.Swal) Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذّر الاتصال بالخادم' });
                })
                .finally(function () {
                    btn.classList.remove('is-loading');
                    btn.innerHTML = oldHtml;
                });
        });
    }

    // ----- Update ALL balances button -----
    function bindUpdateAllButton() {
        var btn = document.getElementById('acvBtnUpdateAllBalances');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var go = function () {
                btn.disabled = true;
                var oldHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> جار التحديث...';
                fetch(window.ACV.recalcAllUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': window.ACV.csrf,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'تم',
                                    text: data.message || 'تم تحديث جميع الأرصدة',
                                    timer: 1600,
                                    showConfirmButton: false
                                }).then(function () { window.location.reload(); });
                            } else {
                                window.location.reload();
                            }
                        } else {
                            if (window.Swal) Swal.fire({ icon: 'error', title: 'خطأ', text: data.message || 'فشل التحديث' });
                        }
                    })
                    .catch(function (err) {
                        console.error(err);
                        if (window.Swal) Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذّر الاتصال' });
                    })
                    .finally(function () {
                        btn.disabled = false;
                        btn.innerHTML = oldHtml;
                    });
            };

            if (window.Swal) {
                Swal.fire({
                    title: 'تأكيد التحديث',
                    text: 'سيتم إعادة احتساب أرصدة جميع الحسابات من القيود. هل تريد المتابعة؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، حدّث',
                    cancelButtonText: 'إلغاء'
                }).then(function (r) { if (r.isConfirmed) go(); });
            } else {
                if (confirm('سيتم إعادة احتساب أرصدة جميع الحسابات من القيود. متابعة؟')) go();
            }
        });
    }

    // ----- Journal Entry Modal -----
    function escapeHtml(s) {
        if (s === null || typeof s === 'undefined') return '';
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function fmtMoney(n) {
        var v = Number(n || 0);
        return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function showJournalEntryModal(entryId) {
        var $modalEl = document.getElementById('acvJournalEntryModal');
        var $body = document.getElementById('acvJournalEntryBody');
        var $title = document.getElementById('acvJournalEntryModalTitle');
        var $editLink = document.getElementById('acvJournalEntryEditLink');

        $body.innerHTML =
            '<div class="text-center py-4">' +
            '  <div class="acv-spinner"></div>' +
            '  <p class="mt-2 text-muted">جار تحميل القيد...</p>' +
            '</div>';
        $editLink.href = window.ACV.journalEntryEditUrl(entryId);

        if (window.jQuery) jQuery($modalEl).modal('show');

        fetch(window.ACV.journalEntryDetailsUrl(entryId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (!resp.success) {
                    $body.innerHTML = '<div class="alert alert-danger">' + escapeHtml(resp.message || 'تعذّر تحميل بيانات القيد') + '</div>';
                    return;
                }
                var d = resp.data;
                $title.innerHTML = '<i class="fa fa-file-invoice"></i> قيد رقم ' + escapeHtml(d.reference_number || ('#' + d.id));

                var statusClass = d.status == 1 ? 'success' : (d.status == 2 ? 'danger' : 'warning');

                var html =
                    '<div class="acv-je-info">' +
                    '  <div class="row">' +
                    '    <div class="col-md-6 col-12">' +
                    '      <p class="mb-1"><strong>التاريخ:</strong> <span class="text-muted">' + escapeHtml(d.date || '—') + '</span></p>' +
                    '      <p class="mb-1"><strong>المرجع:</strong> <span class="text-muted">' + escapeHtml(d.reference_number || '—') + '</span></p>' +
                    '    </div>' +
                    '    <div class="col-md-6 col-12">' +
                    '      <p class="mb-1"><strong>نوع المرجع:</strong> <span class="text-muted">' + escapeHtml(d.reference_type_name || 'غير محدد') + '</span></p>' +
                    '      <p class="mb-1"><strong>الحالة:</strong> <span class="badge badge-' + statusClass + '">' + escapeHtml(d.status_text || '—') + '</span></p>' +
                    '    </div>' +
                    '    <div class="col-12">' +
                    '      <p class="mb-0"><strong>الوصف:</strong> <span class="text-muted">' + escapeHtml(d.description || '—') + '</span></p>' +
                    '    </div>' +
                    '  </div>' +
                    '</div>';

                html +=
                    '<div class="acv-je-table-wrap">' +
                    '<table class="acv-je-table">' +
                    '<thead>' +
                    '  <tr>' +
                    '    <th>الحساب</th>' +
                    '    <th>الوصف</th>' +
                    '    <th class="text-left">مدين</th>' +
                    '    <th class="text-left">دائن</th>' +
                    '  </tr>' +
                    '</thead><tbody>';

                var totalDebit = 0, totalCredit = 0;
                if (d.details && d.details.length > 0) {
                    d.details.forEach(function (dt) {
                        var dr = parseFloat(dt.debit || 0);
                        var cr = parseFloat(dt.credit || 0);
                        totalDebit += dr;
                        totalCredit += cr;
                        html +=
                            '<tr>' +
                            '  <td>' +
                            '    <strong>' + escapeHtml(dt.account_name || '—') + '</strong>' +
                            (dt.account_code ? '<small class="d-block text-muted" style="direction:ltr">#' + escapeHtml(dt.account_code) + '</small>' : '') +
                            '  </td>' +
                            '  <td>' + escapeHtml(dt.description || '') + '</td>' +
                            '  <td class="text-left">' + (dr > 0 ? '<strong class="text-success">' + fmtMoney(dr) + '</strong>' : '<span class="text-muted">—</span>') + '</td>' +
                            '  <td class="text-left">' + (cr > 0 ? '<strong class="text-danger">' + fmtMoney(cr) + '</strong>' : '<span class="text-muted">—</span>') + '</td>' +
                            '</tr>';
                    });
                } else {
                    html += '<tr><td colspan="4" class="text-center text-muted py-3">لا توجد تفاصيل</td></tr>';
                }

                html +=
                    '<tr class="acv-je-total">' +
                    '  <td colspan="2" class="text-center"><strong>الإجمالي</strong></td>' +
                    '  <td class="text-left"><strong class="text-success">' + fmtMoney(totalDebit) + '</strong></td>' +
                    '  <td class="text-left"><strong class="text-danger">' + fmtMoney(totalCredit) + '</strong></td>' +
                    '</tr>';

                if (Math.abs(totalDebit - totalCredit) > 0.01) {
                    html +=
                        '<tr><td colspan="4" class="text-center"><span class="badge badge-danger">القيد غير متوازن</span></td></tr>';
                }

                html += '</tbody></table></div>';

                $body.innerHTML = html;
            })
            .catch(function (err) {
                console.error(err);
                $body.innerHTML = '<div class="alert alert-danger">تعذّر الاتصال بالخادم</div>';
            });
    }

    function bindJournalEntryView() {
        // Click on view button
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.acv-btn-view-entry');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                var id = btn.getAttribute('data-entry-id');
                if (id) showJournalEntryModal(id);
                return;
            }
            // Click on the row itself (excluding action buttons)
            var row = e.target.closest('.acv-row-entry.acv-clickable');
            if (row && !e.target.closest('.acv-actions')) {
                var id = row.getAttribute('data-entry-id');
                if (id) showJournalEntryModal(id);
            }
        });

        // Print button
        var $printBtn = document.getElementById('acvJournalEntryPrint');
        if ($printBtn) {
            $printBtn.addEventListener('click', function () {
                var content = document.getElementById('acvJournalEntryBody').innerHTML;
                var win = window.open('', '_blank');
                win.document.write(
                    '<html dir="rtl"><head><title>طباعة القيد</title>' +
                    '<style>' +
                    'body{font-family:Cairo,Arial,sans-serif;padding:20px;}' +
                    '.acv-je-info{background:#f8f9fa;padding:12px;border-radius:6px;margin-bottom:16px;}' +
                    '.acv-je-info p{margin:4px 0;font-size:13px;}' +
                    '.acv-je-table{width:100%;border-collapse:collapse;margin-top:12px;}' +
                    '.acv-je-table th,.acv-je-table td{border:1px solid #dee2e6;padding:8px;text-align:right;font-size:13px;}' +
                    '.acv-je-table th{background:#f8f9fa;}' +
                    '.acv-je-total td{background:#e9ecef;font-weight:bold;}' +
                    '.text-left{text-align:left!important;}' +
                    '.row{display:flex;flex-wrap:wrap;}' +
                    '.col-md-6{width:50%;}' +
                    '.col-12{width:100%;}' +
                    '.text-success{color:#10b981;}' +
                    '.text-danger{color:#ef4444;}' +
                    '.text-muted{color:#6b7280;}' +
                    '.badge{padding:2px 8px;border-radius:10px;font-size:11px;}' +
                    '.badge-success{background:#d1fae5;color:#047857;}' +
                    '.badge-warning{background:#fef3c7;color:#92400e;}' +
                    '.badge-danger{background:#fee2e2;color:#b91c1c;}' +
                    '@media print{body{padding:0;}}' +
                    '</style></head><body>' + content + '</body></html>'
                );
                win.document.close();
                win.focus();
                setTimeout(function () { win.print(); }, 300);
            });
        }
    }

    // ----- Edit Account modal -----
    function populateParentOptions(currentParentId, currentParentLabel, selfId) {
        // الخيارات السريعة من state.flat (إذا حُمِّلت). إن لم تكن، نضع الأب الحالي فقط.
        var $select = document.getElementById('acvEditParentId');
        if (!$select) return;
        $select.innerHTML = '<option value="">— لا يوجد —</option>';
        if (state.flat && state.flat.length > 0) {
            // build optgroup per top-level for easier scanning (basic, no select2)
            var frag = document.createDocumentFragment();
            state.flat.forEach(function (a) {
                // الحساب لا يكون أباً لنفسه (والخادم يرفض ذلك أيضاً)
                if (selfId && String(a.id) === String(selfId)) return;
                var opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = a.name + ' (#' + a.code + ')';
                if (currentParentId && parseInt(currentParentId, 10) === a.id) opt.selected = true;
                frag.appendChild(opt);
            });
            $select.appendChild(frag);
        } else if (currentParentId) {
            // fallback - one option only
            var opt = document.createElement('option');
            opt.value = currentParentId;
            opt.textContent = currentParentLabel || ('#' + currentParentId);
            opt.selected = true;
            $select.appendChild(opt);
        }
    }

    function showEditError(msg) {
        var $err = document.getElementById('acvEditError');
        if (!$err) return;
        $err.textContent = msg;
        $err.style.display = 'block';
    }
    function clearEditError() {
        var $err = document.getElementById('acvEditError');
        if ($err) { $err.style.display = 'none'; $err.textContent = ''; }
    }

    function openEditModal(id) {
        var $modal = document.getElementById('acvEditAccountModal');
        var $form = document.getElementById('acvEditAccountForm');
        var $loading = document.getElementById('acvEditLoading');
        if (!$modal || !$form) return;

        clearEditError();
        $form.style.display = 'none';
        $loading.style.display = 'block';
        $form.setAttribute('data-id', id);
        document.getElementById('acvEditId').value = id;

        if (window.jQuery) jQuery($modal).modal('show');

        fetch(window.ACV.editAccountUrl(id), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (!resp.success || !resp.data) {
                    showEditError(resp.message || 'تعذّر جلب بيانات الحساب');
                    $form.style.display = 'block';
                    $loading.style.display = 'none';
                    return;
                }
                var d = resp.data;
                document.getElementById('acvEditName').value = d.name || '';
                document.getElementById('acvEditCode').value = d.code || '';
                document.getElementById('acvEditType').value = d.type || 'sub';
                document.getElementById('acvEditAccountType').value = d.account_type || '';
                document.getElementById('acvEditBalanceType').value = d.balance_type || 'debit';
                document.getElementById('acvEditBranchId').value = d.branch_id || '';
                document.getElementById('acvEditOpeningBalance').value = d.opening_balance != null ? d.opening_balance : 0;
                document.getElementById('acvEditOpeningBalanceType').value = d.opening_balance_type || '';
                // الحفاظ على الرصيد الأصلي - المتحكم يحتاجه عند الحفظ
                var $balHidden = document.getElementById('acvEditBalanceHidden');
                if ($balHidden) $balHidden.value = d.balance != null ? d.balance : 0;

                // Parent dropdown
                var parentLabel = '';
                if (state.byId && state.byId[d.parent_id]) {
                    var p = state.byId[d.parent_id];
                    parentLabel = p.name + ' (#' + p.code + ')';
                }
                populateParentOptions(d.parent_id, parentLabel, id);

                $form.style.display = 'block';
                $loading.style.display = 'none';
            })
            .catch(function (err) {
                console.error(err);
                showEditError('تعذّر الاتصال بالخادم');
                $form.style.display = 'block';
                $loading.style.display = 'none';
            });
    }

    function submitEditForm() {
        var $form = document.getElementById('acvEditAccountForm');
        var $btn = document.getElementById('acvEditSubmit');
        if (!$form) return;

        clearEditError();
        var id = $form.getAttribute('data-id');
        if (!id) { showEditError('معرّف الحساب غير محدد'); return; }

        // Validation
        var name = document.getElementById('acvEditName').value.trim();
        var code = document.getElementById('acvEditCode').value.trim();
        if (!name || !code) {
            showEditError('الاسم والكود مطلوبان');
            return;
        }

        var data = new FormData($form);
        // Ensure _method is PUT (already in hidden input)

        var oldHtml = $btn.innerHTML;
        $btn.disabled = true;
        $btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> جار الحفظ...';

        fetch('/accounts/' + id + '/update', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': window.ACV.csrf,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: data,
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json().then(function (json) { return { ok: r.ok, status: r.status, body: json }; });
            })
            .then(function (resp) {
                if (resp.ok && resp.body && resp.body.success) {
                    if (window.Swal) {
                        Swal.fire({ icon: 'success', title: 'تم', text: resp.body.message || 'تم تحديث الحساب', timer: 1400, showConfirmButton: false })
                            .then(function () { window.location.reload(); });
                    } else {
                        window.location.reload();
                    }
                } else {
                    // Validation errors
                    var msg = (resp.body && resp.body.message) || 'حدث خطأ أثناء التحديث';
                    if (resp.body && resp.body.errors) {
                        var firstField = Object.keys(resp.body.errors)[0];
                        msg = resp.body.errors[firstField][0] || msg;
                    }
                    showEditError(msg);
                }
            })
            .catch(function (err) {
                console.error(err);
                showEditError('تعذّر الاتصال بالخادم');
            })
            .finally(function () {
                $btn.disabled = false;
                $btn.innerHTML = oldHtml;
            });
    }

    function bindDeleteButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.acv-btn-delete');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();

            var id = btn.getAttribute('data-id');
            var name = btn.getAttribute('data-name') || 'هذا الحساب';
            var scope = btn.getAttribute('data-scope') || 'child';
            if (!id) return;

            if (!window.Swal) {
                if (!confirm('هل أنت متأكد من حذف الحساب "' + name + '"؟')) return;
                doDelete(id, scope, btn);
                return;
            }

            Swal.fire({
                title: 'تأكيد الحذف',
                html: 'هل أنت متأكد من حذف الحساب <b>' + name + '</b>؟<br><small class="text-muted">سيتم رفض الحذف إذا كان مرتبطاً بقيود محاسبية أو حسابات فرعية.</small>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#dc3545',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    doDelete(id, scope, btn);
                }
            });
        });
    }

    function doDelete(id, scope, btn) {
        var oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

        fetch(window.ACV.destroyAccountUrl(id), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': window.ACV.csrf,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(function (res) {
            return res.json().then(function (body) { return { ok: res.ok, body: body }; });
        })
        .then(function (resp) {
            if (resp.ok && resp.body && resp.body.success) {
                var msg = resp.body.message || 'تم حذف الحساب بنجاح';
                if (window.Swal) {
                    Swal.fire({ icon: 'success', title: 'تم الحذف', text: msg, timer: 1600, showConfirmButton: false });
                }
                if (scope === 'current') {
                    setTimeout(function () { window.location.href = window.ACV.parentRedirectUrl; }, 800);
                } else {
                    var row = btn.closest('tr');
                    if (row) row.parentNode.removeChild(row);
                    // أيضاً ازل العقدة من شجرة الـ sidebar إن وجدت
                    var treeNode = document.querySelector('#acvTree [data-account-id="' + id + '"]');
                    if (treeNode) {
                        var li = treeNode.closest('li');
                        if (li) li.parentNode.removeChild(li);
                    }
                }
            } else {
                var errMsg = (resp.body && resp.body.message) ? resp.body.message : 'تعذر حذف الحساب';
                if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'تعذر الحذف', text: errMsg });
                } else {
                    alert(errMsg);
                }
                btn.disabled = false;
                btn.innerHTML = oldHtml;
            }
        })
        .catch(function () {
            if (window.Swal) {
                Swal.fire({ icon: 'error', title: 'خطأ', text: 'حدث خطأ في الاتصال بالخادم' });
            }
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        });
    }

    function bindEditButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.acv-btn-edit');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            var id = btn.getAttribute('data-id');
            if (id) openEditModal(id);
        });

        var $submit = document.getElementById('acvEditSubmit');
        if ($submit) {
            $submit.addEventListener('click', submitEditForm);
        }

        // Submit on Enter key
        var $form = document.getElementById('acvEditAccountForm');
        if ($form) {
            $form.addEventListener('submit', function (e) {
                e.preventDefault();
                submitEditForm();
            });
        }
    }

    // ----- Add Account form submit (AJAX) -----
    function bindAddAccountSubmit() {
        var $form = document.getElementById('acvAddAccountForm');
        if (!$form) return;
        $form.addEventListener('submit', function (e) {
            e.preventDefault();
            var $btn = $form.querySelector('button[type="submit"]');
            var oldHtml = $btn ? $btn.innerHTML : '';
            if ($btn) { $btn.disabled = true; $btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> جار الحفظ...'; }

            var data = new FormData($form);
            fetch($form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.ACV.csrf,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: data,
                credentials: 'same-origin'
            })
                .then(function (r) {
                    return r.json().then(function (j) { return { ok: r.ok, body: j }; });
                })
                .then(function (resp) {
                    if (resp.ok && resp.body && resp.body.success) {
                        if (window.Swal) {
                            Swal.fire({ icon: 'success', title: 'تم', text: resp.body.message || 'تم إضافة الحساب', timer: 1400, showConfirmButton: false })
                                .then(function () { window.location.reload(); });
                        } else {
                            window.location.reload();
                        }
                    } else {
                        var msg = (resp.body && resp.body.message) || 'حدث خطأ أثناء الإضافة';
                        if (resp.body && resp.body.errors) {
                            var firstField = Object.keys(resp.body.errors)[0];
                            msg = resp.body.errors[firstField][0] || msg;
                        }
                        if (window.Swal) {
                            Swal.fire({ icon: 'error', title: 'خطأ', text: msg });
                        } else {
                            alert(msg);
                        }
                    }
                })
                .catch(function (err) {
                    console.error(err);
                    if (window.Swal) Swal.fire({ icon: 'error', title: 'خطأ', text: 'تعذّر الاتصال' });
                })
                .finally(function () {
                    if ($btn) { $btn.disabled = false; $btn.innerHTML = oldHtml; }
                });
        });
    }

    // ----- قائمةُ الآباء وتوليدُ الكود في نافذة الإضافة -----
    function bindAutoCode() {
        var $modal = document.getElementById('acvAddAccountModal');
        if (!$modal) return;
        var $code = document.getElementById('acvNewAccountCode');
        var $refreshBtn = document.getElementById('acvRefreshCodeBtn');
        var $parentSelect = $modal.querySelector('select[name="parent_id"]');
        var $typeSelect = $modal.querySelector('select[name="type"]');

        /*
            قائمةُ الآباء كانت مكتوبةً في القالب: خيارُ «لا يوجد» والحسابُ
            المعروض وحدَه. فمن أراد إضافةَ حسابٍ تحت حسابٍ آخر لم يجده،
            ولم تعمل الشاشةُ إلا بعد الدخول إلى حسابٍ بعينه.

            وهي تُبنى الآن من `state.flat` — نفسِ الصفوف التي بُنيت منها
            الشجرة، فلا طلبَ إضافيّ. والمسارُ يُعرض مع الاسم لأن «مصروفات»
            تحت الإدارة و«مصروفات» تحت المبيعات اسمُهما واحد.
        */
        function pathOf(node) {
            var names = [];
            var cur = node.parent_id ? state.byId[node.parent_id] : null;
            var guard = 0;
            while (cur && guard < 20) {
                names.unshift(cur.name);
                cur = cur.parent_id ? state.byId[cur.parent_id] : null;
                guard++;
            }
            return names.join(' ← ');
        }

        function populateParents(preselectId) {
            if (!$parentSelect) return;

            var keep = $parentSelect.value || preselectId || '';
            $parentSelect.innerHTML = '<option value="">— لا يوجد (حساب رئيسي) —</option>';

            if (!state.flat || !state.flat.length) return;

            var rows = state.flat.slice().sort(function (a, b) {
                var ca = parseInt(a.code, 10), cb = parseInt(b.code, 10);
                if (!isNaN(ca) && !isNaN(cb) && ca !== cb) return ca - cb;
                return String(a.code).localeCompare(String(b.code));
            });

            var frag = document.createDocumentFragment();
            rows.forEach(function (a) {
                var opt = document.createElement('option');
                opt.value = a.id;
                var path = pathOf(a);
                opt.textContent = a.code + ' — ' + a.name + (path ? '   (' + path + ')' : '');
                if (String(keep) === String(a.id)) opt.selected = true;
                frag.appendChild(opt);
            });
            $parentSelect.appendChild(frag);
        }

        /*
            الكودُ التالي. المسارُ يقبل الأبَ اختيارياً، فالحسابُ الرئيسيّ
            له كودٌ مولَّدٌ كغيره — وكان يُترك للإدخال اليدوي لأن النقطة
            كانت تُلزم أباً.
        */
        function fetchNextCode(parentId) {
            if (!$code) return;

            $code.value = '';
            $code.placeholder = 'جارٍ التوليد...';

            var url = parentId
                ? '/accounts/' + parentId + '/next-code'
                : '/accounts/next-code';

            fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.nextCode) {
                        $code.value = data.nextCode;
                        $code.placeholder = 'الكود المولَّد';
                    } else {
                        $code.placeholder = 'تعذّر التوليد — أدخل الكود يدوياً';
                    }
                })
                .catch(function () {
                    $code.placeholder = 'تعذّر التوليد — أدخل الكود يدوياً';
                });
        }

        function openModal() {
            // الحسابُ المعروض يُقترح أباً، وللمستخدم تغييرُه من القائمة
            var pid = $parentSelect ? $parentSelect.value : '';
            if (!pid && window.ACV && window.ACV.currentAccountId) {
                pid = String(window.ACV.currentAccountId);
            }
            populateParents(pid);
            if ($parentSelect) $parentSelect.value = pid || '';
            if ($typeSelect) $typeSelect.value = pid ? 'sub' : 'main';
            fetchNextCode(pid);
        }

        if (window.jQuery) {
            jQuery($modal).on('shown.bs.modal', openModal);
        } else {
            $modal.addEventListener('shown.bs.modal', openModal);
        }

        if ($parentSelect) {
            $parentSelect.addEventListener('change', function () {
                // النوعُ يتبع الأب: باختيارِ أبٍ يصير الحسابُ فرعياً، وبنزعه رئيسياً
                if ($typeSelect) $typeSelect.value = this.value ? 'sub' : 'main';
                fetchNextCode(this.value);
            });
        }

        if ($refreshBtn) {
            $refreshBtn.addEventListener('click', function () {
                fetchNextCode($parentSelect ? $parentSelect.value : '');
            });
        }

        if ($typeSelect) {
            $typeSelect.addEventListener('change', function () {
                if (this.value === 'main' && $parentSelect) {
                    $parentSelect.value = '';
                    fetchNextCode('');
                } else if (this.value === 'sub' && $parentSelect && !$parentSelect.value) {
                    // «فرعي» بلا أبٍ لا يُحفظ: يُقترح الحسابُ المعروض
                    if (window.ACV && window.ACV.currentAccountId) {
                        $parentSelect.value = String(window.ACV.currentAccountId);
                        fetchNextCode($parentSelect.value);
                    }
                }
            });
        }
    }

    // ----- Branch filter triggers tree reload -----
    function bindBranchFilter() {
        if (!$branchFilter) return;
        $branchFilter.addEventListener('change', function () {
            loadTree(this.value);
        });
    }

    // ----- Search bindings -----
    function bindSearch() {
        if (!$search) return;
        $search.addEventListener('input', function () {
            clearTimeout(state.searchTimer);
            var v = this.value;
            state.searchTimer = setTimeout(function () { performSearch(v); }, 250);
        });
        // Enter يبحث فوراً دون انتظار المؤقّت
        $search.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            clearTimeout(state.searchTimer);
            performSearch(this.value);
        });
        $searchClear.addEventListener('click', function () {
            clearTimeout(state.searchTimer);
            $search.value = '';
            performSearch('');
            $search.focus();
        });
    }

    // Re-render feather icons after dynamic DOM additions
    function refreshIcons() {
        if (window.feather && typeof window.feather.replace === 'function') {
            try { window.feather.replace(); } catch (e) {}
        }
    }

    // ----- init -----
    document.addEventListener('DOMContentLoaded', function () {
        loadTree(window.ACV.currentBranch);
        bindTreeToggle();
        bindSearch();
        bindBranchFilter();
        bindRecalcButtons();
        bindUpdateAllButton();
        bindEditButtons();
        bindDeleteButtons();
        bindJournalEntryView();
        bindAutoCode();
        bindAddAccountSubmit();
        // Initial icon render
        refreshIcons();
        // لا MutationObserver على الشجرة: أيقوناتها خطوط (<i class="feather icon-…">)
        // لا تحتاج feather.replace()، والمراقب كان يمسح الصفحة كلها مع كل رسم.
    });

    // expose for debugging
    window.ACV._state = state;
})();
