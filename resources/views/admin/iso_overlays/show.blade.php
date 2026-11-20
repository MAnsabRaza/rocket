@extends('admin.layouts.app')

@section('content')
<section class="section">
    <div class="section-header">
        <h1><bdi>{{ $overlay->code }}</bdi>: {{ $overlay->localized('name') }}</h1>
        <div class="section-header-button">
            <a href="{{ route('admin.iso_overlays.index') }}" class="btn btn-light">{{ trans('iso_overlay.overlays') }}</a>
        </div>
    </div>

    <div class="section-body">
        @include('admin.iso_overlays._messages')

        {{-- Versions --}}
        <div class="card">
            <div class="card-header">
                <h4>{{ trans('iso_overlay.versions') }}</h4>
                <div class="card-header-action">
                    @if($overlay->is_active)
                        <span class="badge badge-success">{{ trans('iso_overlay.active') }}</span>
                        @can('admin_iso_overlays_publish')
                            <form method="post" action="{{ route('admin.iso_overlays.deactivate', $overlay->id) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger">{{ trans('iso_overlay.deactivate') }}</button>
                            </form>
                        @endcan
                    @else
                        <span class="badge badge-secondary">{{ trans('iso_overlay.inactive') }}</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <table class="table custom-table font-14">
                    <tr>
                        <th>{{ trans('iso_overlay.version') }}</th>
                        <th>{{ trans('iso_overlay.edition') }}</th>
                        <th>{{ trans('iso_overlay.status') }}</th>
                        <th>{{ trans('iso_overlay.change_note') }}</th>
                        <th>{{ trans('iso_overlay.actions') }}</th>
                    </tr>
                    @foreach($overlay->versions as $version)
                        @php $isCurrent = (int)$overlay->current_version_id === (int)$version->id; @endphp
                        <tr>
                            <td>
                                v{{ $version->version_no }}
                                @if($isCurrent) <span class="badge badge-primary">{{ trans('iso_overlay.current_version') }}</span> @endif
                            </td>
                            <td><bdi>{{ $version->edition ?: '-' }}</bdi></td>
                            <td>
                                @include('admin.iso_overlays._status', ['status' => $version->status])
                                @if(isset($integrity[$version->id]))
                                    @if($integrity[$version->id])
                                        <span class="text-success fas fa-lock" title="{{ trans('iso_overlay.integrity_ok') }}"></span>
                                    @else
                                        <span class="text-danger fas fa-exclamation-triangle" title="{{ trans('iso_overlay.integrity_failed') }}"></span>
                                    @endif
                                @endif
                            </td>
                            <td>{{ $version->change_note }}</td>
                            <td>
                                <a href="{{ route('admin.iso_overlays.version', $version->id) }}" class="btn btn-sm btn-primary">{{ trans('iso_overlay.open') }}</a>
                                @if($version->status === 'published' && !$isCurrent)
                                    @can('admin_iso_overlays_publish')
                                        @php
                                            $current = $overlay->versions->firstWhere('id', $overlay->current_version_id);
                                            $isEarlier = $current && $version->version_no < $current->version_no;
                                        @endphp
                                        <form method="post" action="{{ route($isEarlier ? 'admin.iso_overlays.rollback' : 'admin.iso_overlays.activate', $overlay->id) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="version_id" value="{{ $version->id }}">
                                            <button class="btn btn-sm btn-success">{{ trans($isEarlier ? 'iso_overlay.rollback' : 'iso_overlay.activate') }}</button>
                                        </form>
                                        <form method="post" action="{{ route('admin.iso_overlays.version.archive', $version->id) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary">{{ trans('iso_overlay.archive') }}</button>
                                        </form>
                                    @endcan
                                @endif
                                @if($version->status === 'published' && $isCurrent && !$overlay->is_active)
                                    @can('admin_iso_overlays_publish')
                                        <form method="post" action="{{ route('admin.iso_overlays.activate', $overlay->id) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="version_id" value="{{ $version->id }}">
                                            <button class="btn btn-sm btn-success">{{ trans('iso_overlay.activate') }}</button>
                                        </form>
                                    @endcan
                                @endif
                                @if($version->status !== 'draft')
                                    @can('admin_iso_overlays_manage')
                                        <form method="post" action="{{ route('admin.iso_overlays.versions.new', $overlay->id) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="version_id" value="{{ $version->id }}">
                                            <button class="btn btn-sm btn-outline-primary">{{ trans('iso_overlay.new_version') }}</button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>

        {{-- Overlay details: recorded in each version; editable only in a draft --}}
        <div class="card">
            <div class="card-header"><h4>{{ trans('iso_overlay.details') }}</h4></div>
            <div class="card-body">
                @if($draft)
                    <div class="alert alert-light">{{ trans('iso_overlay.details_in_draft', ['no' => $draft->version_no]) }}</div>
                @endif
                @if($draft && auth()->user()->can('admin_iso_overlays_manage'))
                    @php $d = $draft->meta ?? []; @endphp
                    <form method="post" action="{{ route('admin.iso_overlays.update', $overlay->id) }}">
                        @csrf
                        <div class="row">
                            @foreach($locales as $locale)
                                <div class="form-group col-md-4">
                                    <label>{{ trans('iso_overlay.name') }} ({{ strtoupper($locale) }})</label>
                                    <input type="text" name="name[{{ $locale }}]" class="form-control" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" maxlength="500" value="{{ old('name.' . $locale, $d['name'][$locale] ?? '') }}">
                                </div>
                            @endforeach
                            @foreach($locales as $locale)
                                <div class="form-group col-md-4">
                                    <label>{{ trans('iso_overlay.description') }} ({{ strtoupper($locale) }})</label>
                                    <textarea name="description[{{ $locale }}]" class="form-control" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" rows="2">{{ old('description.' . $locale, $d['description'][$locale] ?? '') }}</textarea>
                                </div>
                            @endforeach
                            <div class="form-group col-md-6">
                                <label>{{ trans('iso_overlay.standard') }}</label>
                                <select name="flinkiso_standard_id" class="form-control">
                                    <option value="">-</option>
                                    @foreach($standards as $id => $label)
                                        <option value="{{ $id }}" {{ ($d['flinkiso_standard_id'] ?? null) == $id ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label>{{ trans('iso_overlay.framework_label') }}</label>
                                <input type="text" name="framework_label" class="form-control" maxlength="120" value="{{ old('framework_label', $d['framework_label'] ?? '') }}">
                            </div>
                        </div>
                        <button class="btn btn-primary">{{ trans('iso_overlay.save') }}</button>
                    </form>
                @else
                    @include('admin.iso_overlays._details', ['details' => $overlay->only(['name', 'description', 'framework_label', 'flinkiso_standard_id'])])
                    @if(!$draft)
                        <div class="alert alert-light mt-2 mb-0">{{ trans('iso_overlay.details_locked') }}</div>
                    @endif
                @endif
            </div>
        </div>

        @can('admin_iso_overlays_manage')
            {{-- Clone --}}
            @php $cloneable = $overlay->versions->where('status', '!=', 'draft'); @endphp
            @if($cloneable->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h4>{{ trans('iso_overlay.clone') }}</h4></div>
                    <div class="card-body">
                        <form method="post" action="{{ route('admin.iso_overlays.clone', $overlay->id) }}" class="row">
                            @csrf
                            <div class="form-group col-md-3">
                                <label>{{ trans('iso_overlay.version') }}</label>
                                <select name="version_id" class="form-control">
                                    @foreach($cloneable as $version)
                                        <option value="{{ $version->id }}">v{{ $version->version_no }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>{{ trans('iso_overlay.clone_code') }}</label>
                                <input type="text" name="code" class="form-control" dir="ltr" maxlength="64" required>
                            </div>
                            <div class="form-group col-md-5">
                                <label>{{ trans('iso_overlay.clone_name') }}</label>
                                <input type="text" name="name[en]" class="form-control" maxlength="500" required>
                            </div>
                            <div class="col-12"><button class="btn btn-outline-primary">{{ trans('iso_overlay.clone') }}</button></div>
                        </form>
                    </div>
                </div>
            @endif
        @endcan

        {{-- Assignments --}}
        <div class="card">
            <div class="card-header"><h4>{{ trans('iso_overlay.assignments') }}</h4></div>
            <div class="card-body">
                <table class="table custom-table font-14">
                    <tr>
                        <th>{{ trans('iso_overlay.target_type') }}</th>
                        <th>{{ trans('iso_overlay.target_ref') }}</th>
                        <th>{{ trans('iso_overlay.status') }}</th>
                        <th>{{ trans('iso_overlay.actions') }}</th>
                    </tr>
                    @forelse($overlay->assignments as $assignment)
                        <tr>
                            <td>{{ $assignment->target_type }}</td>
                            <td>
                                @php
                                    $lists = ['department' => $departments, 'process' => $processes];
                                    $shown = $lists[$assignment->target_type][$assignment->target_ref] ?? $assignment->target_ref;
                                @endphp
                                <bdi>{{ $shown }}</bdi>
                            </td>
                            <td>{{ $assignment->is_active ? trans('iso_overlay.active') : trans('iso_overlay.inactive') }}</td>
                            <td>
                                @can('admin_iso_overlays_manage')
                                    <form method="post" action="{{ route('admin.iso_overlays.assignments.toggle', $assignment->id) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">{{ $assignment->is_active ? trans('iso_overlay.disable') : trans('iso_overlay.enable') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">{{ trans('iso_overlay.none') }}</td></tr>
                    @endforelse
                </table>

                @can('admin_iso_overlays_manage')
                    <form method="post" action="{{ route('admin.iso_overlays.assign', $overlay->id) }}" class="row mt-3">
                        @csrf
                        <div class="form-group col-md-3">
                            <label>{{ trans('iso_overlay.target_type') }}</label>
                            <select name="target_type" class="form-control">
                                @foreach($targets as $target)
                                    <option value="{{ $target }}">{{ $target }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>{{ trans('iso_overlay.target_ref') }}</label>
                            <input type="text" name="target_ref" class="form-control" list="iso-assign-targets" maxlength="64" required>
                            <datalist id="iso-assign-targets">
                                @foreach($departments as $id => $label)
                                    <option value="{{ $id }}">{{ trans('iso_overlay.target_type') }}: department, {{ $label }}</option>
                                @endforeach
                                @foreach($processes as $id => $label)
                                    <option value="{{ $id }}">{{ trans('iso_overlay.target_type') }}: process, {{ $label }}</option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="form-group col-md-3 d-flex align-items-end">
                            <button class="btn btn-primary">{{ trans('iso_overlay.assign') }}</button>
                        </div>
                    </form>
                @endcan
            </div>
        </div>

        {{-- History --}}
        <div class="card">
            <div class="card-header"><h4>{{ trans('iso_overlay.history') }}</h4></div>
            <div class="card-body">
                <table class="table custom-table font-14">
                    <tr>
                        <th>{{ trans('iso_overlay.when') }}</th>
                        <th>{{ trans('iso_overlay.actions') }}</th>
                        <th>{{ trans('iso_overlay.version') }}</th>
                        <th>{{ trans('iso_overlay.user') }}</th>
                        <th>{{ trans('iso_overlay.data') }}</th>
                    </tr>
                    @foreach($audit as $entry)
                        @php $v = $overlay->versions->firstWhere('id', $entry->version_id); @endphp
                        <tr>
                            <td><bdi>{{ optional($entry->created_at)->format('Y-m-d H:i') }}</bdi></td>
                            <td>{{ $entry->action }}</td>
                            <td>{{ $v ? 'v' . $v->version_no : '-' }}</td>
                            <td>{{ $entry->user_id ?: '-' }}</td>
                            <td><bdi class="font-12">{{ $entry->payload ? json_encode($entry->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '' }}</bdi></td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
