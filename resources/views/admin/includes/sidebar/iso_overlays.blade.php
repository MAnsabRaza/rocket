{{-- M8 ISO Overlay Template Engine --}}
@can('admin_iso_overlays_list')
    <li class="menu-header">{{ trans('iso_overlay.overlays') }}</li>
    <li class="{{ request()->is(getAdminPanelUrl('/iso-overlays*', false)) ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.iso_overlays.index') }}">
            <i class="fas fa-layer-group"></i>
            <span>{{ trans('iso_overlay.overlays') }}</span>
        </a>
    </li>
@endcan

{{-- M9 Default ISO Overlay Content Pack --}}
@can('admin_iso_content_list')
    <li class="menu-header">{{ trans('iso_content.library') }}</li>
    <li class="{{ request()->is(getAdminPanelUrl('/iso-content*', false)) ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.iso_content.index') }}">
            <i class="fas fa-clipboard-list"></i>
            <span>{{ trans('iso_content.library') }}</span>
        </a>
    </li>
@endcan
