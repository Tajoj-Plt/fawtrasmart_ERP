@extends('sitting::master')

@section('title', __('languages.details'))

@section('content')
    <div class="sl-page-head">
        <div class="sl-page-head__titles">
            <span class="sl-page-head__icon" aria-hidden="true"><i class="feather icon-globe"></i></span>
            <div style="min-width:0">
                <h2 class="sl-page-head__title">@if ($language->flagIsImage())
                            <img src="{{ asset('flags/' . $language->flag) }}" alt="{{ $language->code }}"
                                 style="width: 32px; height: 22px; border-radius: 3px; object-fit: cover;">
                        @elseif ($language->flagIsText())
                            <span>{{ $language->flag }}</span>
                        @endif
                        {{ $language->name }}</h2>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('languages.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('SittingAccount.language') }}">{{ __('languages.title') }}</a></li>
                        <li class="breadcrumb-item active">{{ $language->name }}</li>

                    </ol>
                </nav>
            </div>
        </div>
        <div class="sl-page-head__actions">
            @if (canLanguages('edit'))
                <a href="{{ route('languages.edit', $language->id) }}" class="btn btn-primary mr-1">
                    <i class="feather icon-edit"></i> {{ __('languages.edit') }}
                </a>
            @endif
            <a href="{{ route('SittingAccount.language') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-left"></i> {{ __('languages.back') }}
            </a>

        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- المعلومات الأساسية --}}
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-info"></i> {{ __('languages.basic_info') }}</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    @php
                        $basics = [
                            __('languages.id') => '#' . $language->id,
                            __('languages.name') => $language->name,
                            __('languages.native_name') => $language->native_name ?: '-',
                            __('languages.code') => strtoupper($language->code),
                            __('languages.locale') => $language->locale ?: $language->code,
                            __('languages.sort_order') => $language->sort_order,
                        ];
                    @endphp
                    @foreach ($basics as $label => $value)
                        <div class="col-md-4 col-6 mb-2">
                            <small class="text-muted d-block">{{ $label }}</small>
                            <strong>{{ $value }}</strong>
                        </div>
                    @endforeach

                    <div class="col-md-4 col-6 mb-2">
                        <small class="text-muted d-block">{{ __('languages.direction') }}</small>
                        @if ($language->isRtl())
                            <span class="badge badge-light-info"><i class="fa fa-align-right mr-1"></i> RTL</span>
                        @else
                            <span class="badge badge-light-secondary"><i class="fa fa-align-left mr-1"></i> LTR</span>
                        @endif
                    </div>

                    <div class="col-md-4 col-6 mb-2">
                        <small class="text-muted d-block">{{ __('languages.status') }}</small>
                        <span class="badge badge-light-{{ $language->is_active ? 'success' : 'secondary' }}">
                            {{ $language->is_active ? __('languages.active') : __('languages.inactive') }}
                        </span>
                    </div>

                    <div class="col-md-4 col-6 mb-2">
                        <small class="text-muted d-block">{{ __('languages.default') }}</small>
                        @if ($language->is_default)
                            <span class="badge badge-warning text-white"><i class="fa fa-star mr-1"></i> {{ __('languages.default') }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </div>

                    <div class="col-md-4 col-6 mb-2">
                        <small class="text-muted d-block">{{ __('languages.flag') }}</small>
                        @if ($language->flagIsImage())
                            <img src="{{ asset('flags/' . $language->flag) }}" alt="{{ $language->code }}"
                                 style="width: 35px; height: 25px; border-radius: 4px; object-fit: cover;">
                            <code class="ml-1 mr-1">{{ $language->flag }}</code>
                        @elseif ($language->flagIsText())
                            <span style="font-size: 1.5rem;">{{ $language->flag }}</span>
                        @elseif (filled($language->flag))
                            {{-- اسم ملف مذكور لكنه غير موجود في public/flags --}}
                            <code>{{ $language->flag }}</code>
                            <small class="text-danger d-block">{{ __('languages.file_missing') }}</small>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </div>

                    <div class="col-md-4 col-6 mb-2">
                        <small class="text-muted d-block">{{ __('languages.created_at') }}</small>
                        <strong>{{ $language->created_at?->format('Y-m-d H:i') ?: '-' }}</strong>
                    </div>

                    <div class="col-md-4 col-6 mb-2">
                        <small class="text-muted d-block">{{ __('languages.updated_at') }}</small>
                        <strong>{{ $language->updated_at?->format('Y-m-d H:i') ?: '-' }}</strong>
                    </div>

                    @if (filled($language->description))
                        <div class="col-12 mb-2">
                            <small class="text-muted d-block">{{ __('languages.description') }}</small>
                            <p class="mb-0">{{ $language->description }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- الإعدادات الإقليمية والمظهر --}}
        <div class="row">
            <div class="col-lg-6 col-12">
                <div class="card h-100">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-globe"></i> {{ __('languages.regional_settings') }}</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                                @php
                                    $regional = [
                                        __('languages.date_format') => $language->date_format,
                                        __('languages.time_format') => $language->time_format,
                                        __('languages.number_format') => $language->number_format,
                                        __('languages.timezone') => $language->timezone,
                                        __('languages.calendar_type') => $language->calendar_type,
                                        __('languages.thousands_separator') => $language->thousands_separator,
                                        __('languages.decimal_separator') => $language->decimal_separator,
                                    ];
                                @endphp
                                @foreach ($regional as $label => $value)
                                    <tr>
                                        <td class="text-muted">{{ $label }}</td>
                                        <td><strong>{{ filled($value) ? $value : '-' }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-12">
                <div class="card h-100">
                    <div class="card-header">
                        <h4 class="card-title"><i class="feather icon-type"></i> {{ __('languages.appearance') }}</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted">{{ __('languages.font_family') }}</td>
                                    <td><strong>{{ $language->font_family ?: '-' }}</strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">{{ __('languages.font_size') }}</td>
                                    <td><strong>{{ $language->font_size ?: '-' }}</strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">{{ __('languages.color') }}</td>
                                    <td>
                                        @if (filled($language->color))
                                            <span class="d-inline-block align-middle mr-1 ml-1"
                                                  style="width: 18px; height: 18px; border-radius: 4px; border: 1px solid #ddd; background: {{ $language->color }};"></span>
                                            <strong>{{ $language->color }}</strong>
                                        @else
                                            <strong>-</strong>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ملفات الترجمة --}}
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-file-text"></i> {{ __('languages.translation_files') }}</h4>
                <span class="badge badge-light-primary">{{ count($translationFiles) }}</span>
            </div>
            <div class="card-body">
                @forelse ($translationFiles as $file)
                    <div class="d-flex align-items-center {{ $loop->last ? '' : 'mb-1' }}">
                        <i class="fa fa-{{ $file['exists'] ? 'file-alt text-success' : 'exclamation-triangle text-danger' }} mr-1 ml-1"
                           style="font-size: 1.5rem;"></i>
                        <div>
                            <code>{{ $file['path'] }}</code>
                            <div class="text-muted" style="font-size: .8125rem;">
                                {{ $file['exists'] ? __('languages.file_present') : __('languages.file_missing') }}
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">
                        <i class="fa fa-info-circle mr-1"></i> {{ __('languages.no_translation_file') }}
                    </p>
                @endforelse
            </div>
        </div>

        {{-- مفاتيح الترجمة --}}
        <div class="card">
            <div class="card-header">
                <h4 class="card-title"><i class="feather icon-list"></i> {{ __('languages.translations.title') }}</h4>
                <span class="badge badge-light-secondary">
                    {{ __('languages.translations.total', ['count' => array_sum(array_map(fn ($g) => count($g['entries']), $translationGroups))]) }}
                </span>
            </div>
            <div class="card-body">
                @if (empty($translationGroups) && ! canLanguages('edit'))
                    <p class="text-muted mb-0">
                        <i class="fa fa-info-circle mr-1"></i> {{ __('languages.translations.no_groups') }}
                    </p>
                @else
                    <form action="{{ route('languages.translations.update', $language->id) }}" method="POST">
                        @csrf

                        @include('sitting::languages._translations_editor', [
                            'groups' => $translationGroups,
                            'language' => $language,
                            'standalone' => true,
                        ])

                        @if (canLanguages('edit'))
                            <div class="d-flex justify-content-end pt-2 mt-1 border-top">
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-check"></i> {{ __('languages.translations.save') }}
                                </button>
                            </div>
                        @endif
                    </form>

                    {{-- حذف ملف مجموعة: نموذج مستقل لأن النماذج لا تتداخل --}}
                    @if (canLanguages('edit'))
                        <form action="{{ route('languages.translations.group.destroy', $language->id) }}" method="POST"
                              class="d-none" data-tr-delete-form>
                            @csrf @method('DELETE')
                            <input type="hidden" name="group" value="">
                            <input type="hidden" name="type" value="php">
                        </form>
                    @endif
                @endif
            </div>
        </div>

        {{-- الإجراءات --}}
        <div class="card">
            <div class="card-body d-flex flex-wrap">
                @if (canLanguages('set_default') && !$language->is_default)
                    <form action="{{ route('languages.set-default', $language->id) }}" method="POST" class="mr-1 mb-1"
                          onsubmit="return confirm('{{ __('languages.confirm_set_default') }}');">
                        @csrf
                        <button type="submit" class="btn btn-warning text-white" @disabled(!$language->is_active)>
                            <i class="fa fa-star"></i> {{ __('languages.set_as_default') }}
                        </button>
                    </form>
                @endif

                @if (canLanguages('activate') && !$language->is_default)
                    <form action="{{ route('languages.toggle-status', $language->id) }}" method="POST" class="mr-1 mb-1">
                        @csrf
                        <button type="submit" class="btn btn-outline-{{ $language->is_active ? 'secondary' : 'success' }}">
                            <i class="feather icon-power"></i>
                            {{ $language->is_active ? __('languages.deactivate') : __('languages.activate') }}
                        </button>
                    </form>
                @endif

                @if (canLanguages('delete') && !$language->is_default)
                    <form action="{{ route('languages.destroy', $language->id) }}" method="POST" class="mr-1 mb-1"
                          onsubmit="return confirm('{{ __('languages.confirm_delete') }}');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="feather icon-trash-2"></i> {{ __('languages.delete') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
