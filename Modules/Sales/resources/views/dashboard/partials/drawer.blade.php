{{--
    اللوحةُ الجانبية «ماذا وراء هذا الرقم؟» — يملؤها `partials.script` من
    `sales.dashboard.segment`. تُضمَّن داخل `#sales-dashboard` مرّةً واحدة.
--}}
            {{-- ==================== اللوحة الجانبية: ما وراء الرقم ==================== --}}
            <div class="sd-drawer-backdrop" id="sdDrawerBackdrop" data-sd-drawer-close hidden></div>
            <aside class="sd-drawer" id="sdDrawer" role="dialog" aria-modal="true" aria-labelledby="sdDrawerTitle" hidden>
                <header class="sd-drawer-head">
                    <div>
                        <h5 id="sdDrawerTitle"></h5>
                        <p id="sdDrawerHint"></p>
                    </div>
                    <button type="button" class="sd-drawer-close" data-sd-drawer-close aria-label="{{ __('sales_dashboard.drill.close') }}">&times;</button>
                </header>
                <div class="sd-drawer-tools">
                    <input type="search" id="sdDrawerSearch" placeholder="{{ __('sales_dashboard.drill.search') }}" autocomplete="off">
                    <span class="sd-drawer-count" id="sdDrawerCount"></span>
                    <button type="button" class="sd-reset" id="sdDrawerCsv"><i class="feather icon-download"></i> {{ __('sales_dashboard.drill.export') }}</button>
                </div>
                <div class="sd-drawer-body" id="sdDrawerBody"></div>
            </aside>
