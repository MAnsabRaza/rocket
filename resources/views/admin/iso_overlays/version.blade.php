@extends('admin.layouts.app')

@section('content')
<section class="section">
    <div class="section-header">
        <h1><bdi>{{ $overlay->code }}</bdi> v{{ $version->version_no }}</h1>
        <div class="section-header-button">
            <a href="{{ route('admin.iso_overlays.show', $overlay->id) }}" class="btn btn-light">{{ $overlay->localized('name') }}</a>
        </div>
    </div>

    <div class="section-body">
        @include('admin.iso_overlays._messages')

        <div class="card">
            <div class="card-header">
                <h4>{{ trans('iso_overlay.version') }} v{{ $version->version_no }} @include('admin.iso_overlays._status', ['status' => $version->status])</h4>
            </div>
            <div class="card-body">
                <h6>{{ trans('iso_overlay.version_details') }}</h6>
                @if($version->hasRecordedDetails())
                    @include('admin.iso_overlays._details', ['details' => $version->meta])
                    @if($version->isDraft())
                        @can('admin_iso_overlays_manage')
                            <a href="{{ route('admin.iso_overlays.show', $overlay->id) }}" class="btn btn-sm btn-outline-primary mt-2">{{ trans('iso_overlay.edit') }}</a>
                        @endcan
                    @endif
                @else
                    <div class="alert alert-warning">{{ trans('iso_overlay.details_not_recorded') }}</div>
                @endif
                <hr>
                @if($version->isDraft())
                    @can('admin_iso_overlays_manage')
                        <form method="post" action="{{ route('admin.iso_overlays.version.update', $version->id) }}" class="row">
                            @csrf
                            <div class="form-group col-md-3">
                                <label>{{ trans('iso_overlay.edition') }}</label>
                                <input type="text" name="edition" class="form-control" dir="ltr" maxlength="60" value="{{ old('edition', $version->edition) }}">
                            </div>
                            <div class="form-group col-md-6">
                                <label>{{ trans('iso_overlay.change_note') }}</label>
                                <input type="text" name="change_note" class="form-control" maxlength="1000" value="{{ old('change_note', $version->change_note) }}">
                            </div>
                            <div class="form-group col-md-3 d-flex align-items-end">
                                <button class="btn btn-primary">{{ trans('iso_overlay.save') }}</button>
                            </div>
                        </form>
                    @endcan

                    @if($problems)
                        <div class="alert alert-warning">
                            <strong>{{ trans('iso_overlay.problems') }}</strong>
                            <ul class="mb-0 mt-2">
                                @foreach($problems as $problem)
                                    <li>{{ $problem }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="alert alert-success">{{ trans('iso_overlay.ready_to_publish') }}</div>
                    @endif

                    <div class="mt-2">
                        @can('admin_iso_overlays_publish')
                            <form method="post" action="{{ route('admin.iso_overlays.version.publish', $version->id) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-success" {{ $problems ? 'disabled' : '' }}>{{ trans('iso_overlay.publish') }}</button>
                            </form>
                        @endcan
                        @can('admin_iso_overlays_manage')
                            <form method="post" action="{{ route('admin.iso_overlays.version.delete', $version->id) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-outline-danger">{{ trans('iso_overlay.delete_draft') }}</button>
                            </form>
                        @endcan
                    </div>
                @else
                    <p><bdi>{{ trans('iso_overlay.edition') }}: {{ $version->edition ?: '-' }}</bdi></p>
                    <p>{{ trans('iso_overlay.change_note') }}: {{ $version->change_note ?: '-' }}</p>
                    <div class="alert alert-light">{{ trans('iso_overlay.published_readonly') }}</div>
                    <div class="{{ $intact ? 'text-success' : 'text-danger' }}">
                        {{ $intact ? trans('iso_overlay.integrity_ok') : trans('iso_overlay.integrity_failed') }}
                        <bdi class="font-12 text-muted">{{ $version->content_hash }}</bdi>
                        <div class="font-12 text-muted">{{ trans('iso_overlay.hash_scheme') }}: {{ $version->hashScheme() }}</div>
                    </div>
                @endif
            </div>
        </div>

        @if($version->isDraft())
            @can('admin_iso_overlays_manage')
                <div class="card">
                    <div class="card-header"><h4>{{ trans('iso_overlay.add_item') }}</h4></div>
                    <div class="card-body">
                        @foreach(array_keys($types) as $type)
                            <a href="{{ route('admin.iso_overlays.items.create', ['version' => $version->id, 'type' => $type]) }}" class="btn btn-sm btn-outline-primary mb-1">{{ $type }}</a>
                        @endforeach
                    </div>
                </div>
            @endcan
        @endif

        <div class="card">
            <div class="card-header"><h4>{{ trans('iso_overlay.items') }}</h4></div>
            <div class="card-body">
                @forelse($items as $type => $group)
                    <h6 class="mt-3">{{ $type }} ({{ $group->count() }})</h6>
                    <table class="table custom-table font-14">
                        <tr>
                            <th>{{ trans('iso_overlay.key') }}</th>
                            <th>{{ trans('iso_overlay.label') }}</th>
                            <th>{{ trans('iso_overlay.parent_key') }}</th>
                            <th>{{ trans('iso_overlay.reference') }}</th>
                            <th>{{ trans('iso_overlay.data') }}</th>
                            @if($version->isDraft()) <th>{{ trans('iso_overlay.actions') }}</th> @endif
                        </tr>
                        @foreach($group as $item)
                            <tr>
                                <td><bdi>{{ $item->key }}</bdi></td>
                                <td>{{ $item->localizedLabel() }}</td>
                                <td><bdi>{{ $item->parent_key ?: '-' }}</bdi></td>
                                <td><bdi class="font-12">{{ $item->ref_table ? $item->ref_table . ':' : '' }}{{ $item->ref_id ?: '-' }}</bdi></td>
                                <td>@include('admin.iso_overlays._data', ['data' => $item->data])</td>
                                @if($version->isDraft())
                                    <td>
                                        @can('admin_iso_overlays_manage')
                                            <a href="{{ route('admin.iso_overlays.items.edit', $item->id) }}" class="btn btn-sm btn-primary">{{ trans('iso_overlay.edit') }}</a>
                                            <form method="post" action="{{ route('admin.iso_overlays.items.delete', $item->id) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-danger">{{ trans('iso_overlay.remove') }}</button>
                                            </form>
                                        @endcan
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </table>
                @empty
                    <p>{{ trans('iso_overlay.none') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
@endsection
