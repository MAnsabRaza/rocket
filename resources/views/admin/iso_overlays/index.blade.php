@extends('admin.layouts.app')

@section('content')
<section class="section">
    <div class="section-header">
        <h1>{{ trans('iso_overlay.overlays') }}</h1>
        @can('admin_iso_overlays_manage')
            <div class="section-header-button">
                <a href="{{ route('admin.iso_overlays.create') }}" class="btn btn-primary">{{ trans('iso_overlay.new_overlay') }}</a>
            </div>
        @endcan
    </div>

    <div class="section-body">
        @include('admin.iso_overlays._messages')
        <div class="card">
            <div class="card-body">
                <table class="table custom-table font-14">
                    <tr>
                        <th>{{ trans('iso_overlay.code') }}</th>
                        <th>{{ trans('iso_overlay.name') }}</th>
                        <th>{{ trans('iso_overlay.standard') }}</th>
                        <th>{{ trans('iso_overlay.current_version') }}</th>
                        <th>{{ trans('iso_overlay.versions') }}</th>
                        <th>{{ trans('iso_overlay.status') }}</th>
                        <th>{{ trans('iso_overlay.actions') }}</th>
                    </tr>
                    @forelse($overlays as $overlay)
                        <tr>
                            <td><bdi>{{ $overlay->code }}</bdi></td>
                            <td>{{ $overlay->localized('name') }}</td>
                            <td>{{ $standards[$overlay->flinkiso_standard_id] ?? ($overlay->framework_label ?: '-') }}</td>
                            <td>
                                @if($overlay->currentVersion)
                                    v{{ $overlay->currentVersion->version_no }}
                                    @if($overlay->currentVersion->edition) ({{ $overlay->currentVersion->edition }}) @endif
                                @else - @endif
                            </td>
                            <td>{{ $overlay->versions_count }}</td>
                            <td>
                                @if($overlay->is_active)
                                    <span class="text-success fas fa-check"></span> {{ trans('iso_overlay.active') }}
                                @else
                                    <span class="text-danger fas fa-times"></span> {{ trans('iso_overlay.inactive') }}
                                @endif
                            </td>
                            <td><a href="{{ route('admin.iso_overlays.show', $overlay->id) }}" class="btn btn-sm btn-primary">{{ trans('iso_overlay.open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ trans('iso_overlay.none') }}</td></tr>
                    @endforelse
                </table>
                {{ $overlays->links() }}
            </div>
        </div>
    </div>
</section>
@endsection
