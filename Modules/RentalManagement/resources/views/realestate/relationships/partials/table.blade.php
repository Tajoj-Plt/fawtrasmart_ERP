{{--
    بطاقةٌ لكلّ عميل: أدواره في الأعلى، وتحتها اهتماماته (كلٌّ بمحلّه وحالته ومتابِعه وإجراءاته).
    `$relationships` صفحةُ العملاء (client_id, relationships_count, last_activity_at)،
    و`$groups` علاقات كلّ عميل مفهرسةً بمعرّفه. الأصناف `.re-rel-edit` و`.re-action` يربطها سكربت الصفحة.
--}}
@php
    use Modules\RentalManagement\Support\RealEstateVocabulary as V;
    $canEdit = hasPermission('realestate.relationships.edit');
    $canDelete = hasPermission('realestate.relationships.delete');
@endphp
@if ($relationships->count() > 0)
    <div class="d-flex justify-content-end px-2 pt-2">@include('stock::components.per-page', ['onchange' => 'loadData(1)'])</div>

    <div class="re-rel-grid" id="reportTable">
        @foreach ($relationships as $row)
            @php
                $items = $groups->get($row->client_id, collect());
                $client = $items->first()?->client;
                $clientName = $client->display_name ?? ('#'.$row->client_id);
                $roles = $items->pluck('roles')->flatten()->filter()->unique()->values();
                $lastActivity = $items->max('last_activity_at');
                $needsFollow = $items->contains(fn ($i) => $i->relationship_status === 'follow_up');
                $phone = $client?->mobile ?: $client?->phone;
            @endphp
            <article class="re-rel-card {{ $needsFollow ? 'is-follow' : '' }}">
                <header class="re-rel-card__head">
                    <span class="re-rel-avatar" aria-hidden="true">{{ mb_substr(trim($clientName) ?: '؟', 0, 1) }}</span>
                    <div class="flex-grow-1" style="min-width:0">
                        <a href="{{ route('realestate.relationships.client', $row->client_id) }}" class="re-rel-card__name text-truncate">{{ $clientName }}</a>
                        <span class="re-rel-card__sub">
                            <span class="stk-num">{{ $client->code ?? '—' }}</span>
                            @if ($phone) · <a href="tel:{{ $phone }}" dir="ltr" class="text-muted">{{ $phone }}</a> @endif
                        </span>
                    </div>
                    @if ($needsFollow) <span class="badge badge-light-warning" title="لديه اهتمام بحاجة إلى متابعة"><i class="feather icon-phone-call"></i> متابعة</span> @endif
                </header>

                @if ($roles->isNotEmpty())
                    <div class="re-rel-card__roles">
                        @foreach ($roles as $role)
                            <span class="badge badge-light-{{ V::tone(V::RELATIONSHIP_ROLES, $role) }}">{{ V::label(V::RELATIONSHIP_ROLES, $role) }}</span>
                        @endforeach
                    </div>
                @endif

                <ul class="re-rel-list">
                    @foreach ($items as $relationship)
                        @php
                            $place = $relationship->unit?->building ?? $relationship->building;
                            $icon = $relationship->unit ? 'home' : ($relationship->listing ? 'tag' : ($place ? 'map-pin' : 'heart'));
                        @endphp
                        <li class="re-rel-item">
                            <span class="re-rel-item__icon"><i class="feather icon-{{ $icon }}"></i></span>
                            <div class="re-rel-item__body">
                                <div class="re-rel-item__title">
                                    @if ($relationship->unit)
                                        {{ $relationship->unit->type_label }} {{ $relationship->unit->unit_no }}
                                        <span class="text-muted fw-normal">— {{ $place->name ?? '—' }}</span>
                                    @else
                                        {{ $place->name ?? 'اهتمام عامّ (بلا عقار محدَّد)' }}
                                    @endif
                                </div>
                                <div class="re-rel-item__meta">
                                    <span class="badge badge-light-{{ $relationship->status_meta['tone'] }}">{{ $relationship->status_meta['label'] }}</span>
                                    @if ($relationship->assignee) <span><i class="feather icon-user"></i> {{ $relationship->assignee->name }}</span> @endif
                                    @if ($relationship->contract)
                                        <span><i class="feather icon-file-text"></i>
                                            @if (hasPermission('realestate.contracts.show'))
                                                <a href="{{ route('realestate.contracts.show', $relationship->contract->id) }}" class="stk-num">{{ $relationship->contract->code }}</a>
                                            @else
                                                <span class="stk-num">{{ $relationship->contract->code }}</span>
                                            @endif
                                        </span>
                                    @endif
                                    @if ($relationship->listing)
                                        <span><i class="feather icon-tag"></i>
                                            @if (hasPermission('realestate.listings.show'))
                                                <a href="{{ route('realestate.listings.show', $relationship->listing->id) }}" class="stk-num">{{ $relationship->listing->code }}</a>
                                            @else
                                                <span class="stk-num">{{ $relationship->listing->code }}</span>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                                @if ($relationship->notes) <div class="re-rel-card__sub mt-25 text-truncate" title="{{ $relationship->notes }}">{{ $relationship->notes }}</div> @endif
                            </div>
                            <span class="re-rel-item__actions">
                                @if ($canEdit)
                                    <a class="btn btn-sm btn-icon btn-flat-secondary re-rel-edit" href="#" title="تعديل الاهتمام" aria-label="تعديل اهتمام {{ $clientName }}"
                                       data-url="{{ route('realestate.relationships.update', $relationship->id) }}"
                                       data-client="{{ $relationship->client_id }}"
                                       data-client-name="{{ $clientName }}{{ $client?->code ? ' — '.$client->code : '' }}"
                                       data-roles="{{ json_encode(array_values((array) $relationship->roles)) }}"
                                       data-building="{{ $relationship->building_id }}"
                                       data-unit="{{ $relationship->unit_id }}"
                                       data-status="{{ $relationship->relationship_status }}"
                                       data-assignee="{{ $relationship->assigned_user_id }}"
                                       data-notes="{{ $relationship->notes }}">
                                        <i class="feather icon-edit"></i>
                                    </a>
                                @endif
                                @if ($canDelete && ! $relationship->contract_id)
                                    <a class="btn btn-sm btn-icon btn-flat-danger re-action" href="#" title="حذف الاهتمام" aria-label="حذف اهتمام {{ $clientName }}"
                                       data-url="{{ route('realestate.relationships.destroy', $relationship->id) }}"
                                       data-method="DELETE" data-tone="danger" data-confirm="حذف هذا الاهتمام للعميل «{{ $clientName }}»؟ (لا يُحذف العميل نفسه)">
                                        <i class="feather icon-trash-2"></i>
                                    </a>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>

                <footer class="re-rel-card__foot">
                    <span title="{{ $lastActivity?->format('Y-m-d H:i') }}"><i class="feather icon-clock"></i> آخر نشاط: {{ $lastActivity?->diffForHumans() ?? '—' }}</span>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('realestate.relationships.client', $row->client_id) }}"><i class="feather icon-user"></i> ملف العميل</a>
                </footer>
            </article>
        @endforeach
    </div>

    @include('rentalmanagement::realestate.components.pagination', ['paginator' => $relationships, 'unit' => 'عميل'])
@else
    @include('rentalmanagement::realestate.components.empty', ['icon' => 'users', 'title' => 'لا اهتمامات مسجَّلة', 'hint' => 'سجّل اهتمام عميلٍ بوحدة أو مبنى أو عرض، أو بدّل التبويب ومعايير البحث.'])
@endif