@can('admin_iso_content_manage')
    <a href="{{ route('admin.iso_content.edit', [$type, $item->technical_key]) }}" class="btn btn-icon icon-left btn-light ml-2">
        <i class="fas fa-edit"></i> {{ trans('iso_content.edit') }}
    </a>

    <form method="post" action="{{ route('admin.iso_content.clone', [$type, $item->technical_key]) }}" class="d-inline">
        @csrf
        <button class="btn btn-icon icon-left btn-info ml-2">
            <i class="fas fa-copy"></i> {{ trans('iso_content.clone') }}
        </button>
    </form>

    <form method="post" action="{{ route('admin.iso_content.version', [$type, $item->technical_key]) }}" class="d-inline"
          onsubmit="return confirm('{{ trans('iso_content.version_confirm') }}')">
        @csrf
        <button class="btn btn-icon icon-left btn-warning ml-2">
            <i class="fas fa-code-branch"></i> {{ trans('iso_content.new_version') }}
        </button>
    </form>

    <form method="post" action="{{ route('admin.iso_content.toggle', [$type, $item->technical_key]) }}" class="d-inline">
        @csrf
        <button class="btn btn-icon icon-left btn-secondary ml-2">
            <i class="fas fa-power-off"></i>
            {{ $item->is_active ? trans('iso_content.deactivate') : trans('iso_content.activate') }}
        </button>
    </form>
@endcan
