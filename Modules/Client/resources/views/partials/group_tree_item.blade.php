@php
    // عملاءُ هذه الجهة وحدها — كلُّ عميلٍ في جهةٍ واحدة (`CRMController::mang_client`)
    $directClients = $group->directClients ?? collect();
    $directClientCount = $directClients->count();
    $clientCount = $group->total_client_count ?? $directClientCount;
    $avatarColors = ['avatar-c1', 'avatar-c2', 'avatar-c3', 'avatar-c4', 'avatar-c5', 'avatar-c6', 'avatar-c7', 'avatar-c8'];
    $hasChildren = $group->childGroups && $group->childGroups->count() > 0;

    // نوعُ العرض لا المخزَّن: المناطق والمنافذ القديمة مخزَّنةٌ «مجموعة» — راجع Branch::displayNodeType
    $nodeType = \App\Models\Branch::displayNodeType($group, $group->childGroups ?? collect());
    $typeMeta = [
        'branch' => ['label' => 'فرع', 'icon' => 'fa-building', 'color' => 'primary'],
        'region' => ['label' => 'منطقة', 'icon' => 'fa-map', 'color' => 'success'],
        'group' => ['label' => 'مجموعة', 'icon' => 'fa-object-group', 'color' => 'info'],
        'outlet' => ['label' => 'منفذ', 'icon' => 'fa-store', 'color' => 'warning'],
    ][$nodeType] ?? ['label' => 'فرع', 'icon' => 'fa-building', 'color' => 'primary'];
@endphp

<div class="rm-accordion-item" data-group-id="{{ $group->id }}">
    <!-- زر المجموعة -->
    <button class="rm-group-header" type="button" data-bs-toggle="collapse"
        data-bs-target="#collapseGroup{{ $group->id }}" aria-expanded="false"
        aria-controls="collapseGroup{{ $group->id }}" data-group-id="{{ $group->id }}">
        <div class="rm-group-icon text-{{ $typeMeta['color'] }}">
            <i class="fas {{ $typeMeta['icon'] }}"></i>
        </div>
        <div class="rm-group-info">
            <div class="rm-group-name">
                <span class="rm-group-name-text">{{ $group->name }}</span>
                <span class="badge bg-{{ $typeMeta['color'] }} badge-{{ $typeMeta['color'] }}" style="color: #fff;">
                    {{ $typeMeta['label'] }}
                </span>
            </div>
        </div>
        <span class="rm-group-count">{{ $clientCount }}</span>
        <i class="fas fa-chevron-left rm-chevron"></i>
    </button>

    <!-- عملاء المجموعة والمجموعات الفرعية -->
    <div id="collapseGroup{{ $group->id }}" class="accordion-collapse collapse"
        aria-labelledby="headingGroup{{ $group->id }}" data-loaded="true">
        <div class="rm-group-content rm-node-children-first">

            <!-- المجموعات التابعة (الأبناء) -->
            @if($hasChildren)
                <div class="rm-sub-accordion mb-2 mt-1">
                    @foreach($group->childGroups as $childGroup)
                        @include('client::partials.group_tree_item', ['group' => $childGroup])
                    @endforeach
                </div>
            @endif

            <!-- عملاء المجموعة المباشرين -->
            <div id="groupClientsContainer{{ $group->id }}" class="rm-clients-container rm-direct-clients">
                @if($directClientCount > 0)
                    <!-- تحديد كل عملاء المجموعة -->
                    <div class="rm-group-select-all" onclick="event.stopPropagation();">
                        <div class="rm-client-check rm-group-check-wrap">
                            <input class="rm-client-checkbox group-select-all-checkbox" type="checkbox"
                                id="groupSelectAll{{ $group->id }}" data-group-id="{{ $group->id }}"
                                onchange="toggleGroupSelection({{ $group->id }}, this.checked)">
                            <span class="rm-client-check-face" aria-hidden="true"></span>
                        </div>
                        <label for="groupSelectAll{{ $group->id }}" class="rm-group-select-label">
                            <i class="fas fa-check-double"></i>
                            تحديد كل العملاء
                            <span class="rm-group-select-count">({{ $directClientCount }})</span>
                        </label>
                    </div>

                    @foreach($directClients as $client)
                        @php
                            $clientName = $client->trade_name ?: trim($client->first_name . ' ' . $client->last_name);
                            if (empty($clientName)) $clientName = 'عميل بدون اسم (' . $client->id . ')';
                            $clientStatus = $client->status_client;
                            $statusId = $clientStatus && $clientStatus->id ? $clientStatus->id : 'no-status';
                            $initials = mb_substr($clientName, 0, 1, 'UTF-8');
                            $colorIndex = $client->id % 8;
                            $avatarColor = $avatarColors[$colorIndex];
                        @endphp
                        <div class="rm-client-item client-item"
                             data-client-id="{{ $client->id }}"
                             data-status="{{ $statusId }}"
                             data-stage="{{ $client->registration_stage_id ?: 'none' }}"
                             data-group-id="{{ $group->id }}"
                             data-branch-id="{{ $client->branch_id ?? '' }}">

                            <!-- صف علوي: مربع التحديد + الأيقونة + الاسم -->
                            <div class="rm-client-row-top">

                                <!-- مربع التحديد -->
                                <div class="rm-client-check" onclick="event.stopPropagation();">
                                    <input class="rm-client-checkbox client-checkbox"
                                           type="checkbox"
                                           id="client_{{ $client->id }}"
                                           data-client-id="{{ $client->id }}"
                                           data-client-name="{{ $client->trade_name }}"
                                           data-group-id="{{ $group->id }}"
                                           onchange="toggleClientSelection({{ $client->id }}, '{{ addslashes($client->trade_name) }}', this.checked, {{ $group->id }})">
                                    <span class="rm-client-check-face" aria-hidden="true"></span>
                                </div>

                                <!-- أيقونة العميل -->
                                <a href="{{ route('clients.show', $client->id) }}"
                                   target="_blank"
                                   onclick="event.stopPropagation();"
                                   class="rm-client-avatar {{ $avatarColor }}"
                                   title="عرض بيانات العميل">
                                    {{ $initials }}
                                </a>

                                <!-- اسم العميل -->
                                <div class="rm-client-name">{{ $clientName }}</div>
                            </div>

                            <!-- صف سفلي: البادج + الكود والجوال -->
                            <div class="rm-client-row-bottom">

                                <!-- البادج -->
                                <div class="rm-client-status">
                                    @if($clientStatus && $clientStatus->color && $clientStatus->name)
                                        <span class="rm-client-status-badge" style="background-color: {{ $clientStatus->color }};">
                                            {{ $clientStatus->name }}
                                        </span>
                                    @endif
                                    {{-- وضع العميل (مرحلة التسجيل) — أغلب العملاء بلا وضعٍ مُسنَد
                                         (وضعٌ اختياريٌّ دخل لاحقاً)، فبدل إسقاط الشارة صامتةً تظهر
                                         شارةٌ رماديّةٌ تقول ذلك صراحةً بدل أن يبدو الحقل مفقوداً --}}
                                    @if($client->registrationStage && $client->registrationStage->color && $client->registrationStage->name)
                                        <span class="rm-client-status-badge" style="background-color: {{ $client->registrationStage->color }};" title="وضع العميل">
                                            {{ $client->registrationStage->name }}
                                        </span>
                                    @else
                                        <span class="rm-client-status-badge rm-client-status-badge--muted" title="وضع العميل">بدون وضع</span>
                                    @endif
                                    {{-- فئة العميل (هدف العميل) --}}
                                    @if($client->customerTarget && $client->customerTarget->color && $client->customerTarget->name)
                                        <span class="rm-client-status-badge" style="background-color: {{ $client->customerTarget->color }};" title="فئة العميل">
                                            {{ $client->customerTarget->name }}
                                        </span>
                                    @endif
                                </div>

                                <!-- الكود والجوال -->
                                <div class="rm-client-meta">
                                    @if($client->phone)
                                        <span>{{ $client->phone }}</span>
                                        <span class="rm-meta-sep">|</span>
                                    @endif
                                    <span>كود: {{ $client->code ?: '-' }}</span>
                                </div>

                            </div>
                        </div>
                    @endforeach
                @elseif(!$hasChildren)
                    <div class="rm-empty-group">
                        <i class="fas fa-inbox d-block mb-1" style="font-size:1.5rem; opacity:0.4;"></i>
                        <p>لا يوجد عملاء</p>
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>