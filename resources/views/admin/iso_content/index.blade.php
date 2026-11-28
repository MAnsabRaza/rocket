@extends('admin.layouts.app')

@section('content')
<section class="section">
    <div class="section-header">
        <h1>{{ trans('iso_content.library') }}</h1>
        @can('admin_iso_content_manage')
        <div class="section-header-button">
            <form method="post" action="{{ route('admin.iso_content.reimport') }}" class="d-inline">
                @csrf
                <button class="btn btn-icon icon-left btn-warning" onclick="return confirm('{{ trans('iso_content.reimport_confirm') }}')">
                    <i class="fas fa-sync-alt"></i> {{ trans('iso_content.reimport') }}
                </button>
            </form>
            <form method="post" action="{{ route('admin.iso_content.reimport') }}" class="d-inline">
                @csrf
                <input type="hidden" name="dry_run" value="1">
                <button class="btn btn-icon icon-left btn-info">
                    <i class="fas fa-search"></i> {{ trans('iso_content.dry_run') }}
                </button>
            </form>
            <form method="post" action="{{ route('admin.iso_content.rollback') }}" class="d-inline">
                @csrf
                <button class="btn btn-icon icon-left btn-danger" onclick="return confirm('{{ trans('iso_content.rollback_confirm') }}')">
                    <i class="fas fa-undo"></i> {{ trans('iso_content.rollback') }}
                </button>
            </form>
        </div>
        @endcan
    </div>

    <div class="section-body">
        @include('admin.iso_content._messages')

@if(session('iso_dry_run'))
    <div class="alert alert-info">
        <strong>{{ trans('iso_content.dry_run_report') }}</strong>
        @foreach(session('iso_dry_run') as $mod => $data)
            @if(is_array($data) && isset($data['conflicts']))
                <div><strong>{{ $mod }}</strong>: created {{ $data['created'] ?? 0 }}, updated {{ $data['updated'] ?? 0 }}, skipped {{ $data['skipped'] ?? 0 }}, conflicts {{ count($data['conflicts'] ?? []) }}</div>
                @if(!empty($data['conflicts']))
                    <ul>@foreach($data['conflicts'] as $c)<li>{{ $c }}</li>@endforeach</ul>
                @endif
            @endif
        @endforeach
    </div>
@endif

@if(session('iso_last_report'))
    <div class="alert alert-success">
        <strong>{{ trans('iso_content.last_import_report') }}</strong>
        @foreach(session('iso_last_report') as $mod => $data)
            @if(is_array($data) && isset($data['conflicts']))
                <div><strong>{{ $mod }}</strong>: created {{ $data['created'] ?? 0 }}, updated {{ $data['updated'] ?? 0 }}, skipped {{ $data['skipped'] ?? 0 }}, conflicts {{ count($data['conflicts'] ?? []) }}</div>
            @endif
        @endforeach
    </div>
@endif

        {{-- Summary Cards --}}
        <div class="row">
            <div class="col-lg-2 col-md-4 col-sm-6 col-12">
                <div class="card card-statistic-1">
                    <div class="card-wrap">
                        <div class="card-header"><h4>{{ trans('iso_content.workflows') }}</h4></div>
                        <div class="card-body">
                            {{ $workflows->total() }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 col-12">
                <div class="card card-statistic-1">
                    <div class="card-wrap">
                        <div class="card-header"><h4>{{ trans('iso_content.checklists') }}</h4></div>
                        <div class="card-body">
                            {{ $checklists->total() }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 col-12">
                <div class="card card-statistic-1">
                    <div class="card-wrap">
                        <div class="card-header"><h4>{{ trans('iso_content.kpis') }}</h4></div>
                        <div class="card-body">
                            {{ $kpis->total() }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 col-12">
                <div class="card card-statistic-1">
                    <div class="card-wrap">
                        <div class="card-header"><h4>{{ trans('iso_content.risks') }}</h4></div>
                        <div class="card-body">
                            {{ $risks->total() }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 col-12">
                <div class="card card-statistic-1">
                    <div class="card-wrap">
                        <div class="card-header"><h4>{{ trans('iso_content.forms') }}</h4></div>
                        <div class="card-body">
                            {{ $forms->total() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Workflows --}}
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-project-diagram text-primary"></i> {{ trans('iso_content.workflows') }}</h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-md mb-0">
                        <tr>
                            <th>{{ trans('iso_content.key') }}</th>
                            <th>{{ trans('iso_content.category') }}</th>
                            <th>{{ trans('iso_content.name') }}</th>
                            <th>{{ trans('iso_content.states') }}</th>
                            <th>{{ trans('iso_content.version') }}</th>
                            <th>{{ trans('iso_content.actions') }}</th>
                        </tr>
                        @forelse($workflows as $item)
                        <tr>
                            <td><bdi class="font-12">{{ $item->technical_key }}</bdi></td>
                            <td><span class="badge badge-info">{{ $item->category }}</span></td>
                            <td>{{ \App\Models\IsoOverlay::pickLocale($item->name) ?: '-' }}</td>
                            <td>{{ count($item->states ?? []) }}</td>
                            <td>v{{ $item->version }}</td>
                            <td>
                                <a href="{{ route('admin.iso_content.workflows.show', $item->technical_key) }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @can('admin_iso_content_manage')
                                <a href="{{ route('admin.iso_content.edit', ['workflows', $item->technical_key]) }}" class="btn btn-sm btn-light">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted">{{ trans('iso_content.none') }}</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
            @if($workflows->hasPages())
            <div class="card-footer">{{ $workflows->appends(request()->query())->links() }}</div>
            @endif
        </div>

        {{-- Checklists --}}
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-clipboard-check text-success"></i> {{ trans('iso_content.checklists') }}</h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-md mb-0">
                        <tr>
                            <th>{{ trans('iso_content.key') }}</th>
                            <th>{{ trans('iso_content.standard') }}</th>
                            <th>{{ trans('iso_content.name') }}</th>
                            <th>{{ trans('iso_content.questions') }}</th>
                            <th>{{ trans('iso_content.version') }}</th>
                            <th>{{ trans('iso_content.actions') }}</th>
                        </tr>
                        @forelse($checklists as $item)
                        <tr>
                            <td><bdi class="font-12">{{ $item->technical_key }}</bdi></td>
                            <td><bdi class="font-12">{{ $item->standard_ref ?: '-' }}</bdi></td>
                            <td>{{ \App\Models\IsoOverlay::pickLocale($item->name) ?: '-' }}</td>
                            <td>{{ $item->questionCount() }}</td>
                            <td>v{{ $item->version }}</td>
                            <td>
                                <a href="{{ route('admin.iso_content.checklists.show', $item->technical_key) }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @can('admin_iso_content_manage')
                                <a href="{{ route('admin.iso_content.edit', ['checklists', $item->technical_key]) }}" class="btn btn-sm btn-light">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted">{{ trans('iso_content.none') }}</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
            @if($checklists->hasPages())
            <div class="card-footer">{{ $checklists->appends(request()->query())->links() }}</div>
            @endif
        </div>

        {{-- KPIs --}}
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-chart-line text-warning"></i> {{ trans('iso_content.kpis') }}</h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-md mb-0">
                        <tr>
                            <th>{{ trans('iso_content.key') }}</th>
                            <th>{{ trans('iso_content.category') }}</th>
                            <th>{{ trans('iso_content.name') }}</th>
                            <th>{{ trans('iso_content.formula') }}</th>
                            <th>{{ trans('iso_content.unit') }}</th>
                            <th>{{ trans('iso_content.actions') }}</th>
                        </tr>
                        @forelse($kpis as $item)
                        <tr>
                            <td><bdi class="font-12">{{ $item->technical_key }}</bdi></td>
                            <td><span class="badge badge-info">{{ $item->category }}</span></td>
                            <td>{{ \App\Models\IsoOverlay::pickLocale($item->name) ?: '-' }}</td>
                            <td><code class="font-11">{{ $item->formula }}</code></td>
                            <td>{{ $item->unit }}</td>
                            <td>
                                <a href="{{ route('admin.iso_content.kpis.show', $item->technical_key) }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @can('admin_iso_content_manage')
                                <a href="{{ route('admin.iso_content.edit', ['kpis', $item->technical_key]) }}" class="btn btn-sm btn-light">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted">{{ trans('iso_content.none') }}</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
            @if($kpis->hasPages())
            <div class="card-footer">{{ $kpis->appends(request()->query())->links() }}</div>
            @endif
        </div>

        {{-- Risks --}}
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-exclamation-triangle text-danger"></i> {{ trans('iso_content.risks') }}</h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-md mb-0">
                        <tr>
                            <th>{{ trans('iso_content.key') }}</th>
                            <th>{{ trans('iso_content.category') }}</th>
                            <th>{{ trans('iso_content.name') }}</th>
                            <th>{{ trans('iso_content.probability') }}</th>
                            <th>{{ trans('iso_content.impact') }}</th>
                            <th>{{ trans('iso_content.score') }}</th>
                            <th>{{ trans('iso_content.actions') }}</th>
                        </tr>
                        @forelse($risks as $item)
                        @php $score = ($item->default_probability ?? 0) * ($item->default_impact ?? 0); @endphp
                        <tr>
                            <td><bdi class="font-12">{{ $item->technical_key }}</bdi></td>
                            <td><span class="badge badge-info">{{ $item->category }}</span></td>
                            <td>{{ \App\Models\IsoOverlay::pickLocale($item->name) ?: '-' }}</td>
                            <td>{{ $item->default_probability }}</td>
                            <td>{{ $item->default_impact }}</td>
                            <td>
                                @if($score >= 15)
                                    <span class="badge badge-danger">{{ $score }}</span>
                                @elseif($score >= 8)
                                    <span class="badge badge-warning">{{ $score }}</span>
                                @else
                                    <span class="badge badge-success">{{ $score }}</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.iso_content.risks.show', $item->technical_key) }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @can('admin_iso_content_manage')
                                <a href="{{ route('admin.iso_content.edit', ['risks', $item->technical_key]) }}" class="btn btn-sm btn-light">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted">{{ trans('iso_content.none') }}</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
            @if($risks->hasPages())
            <div class="card-footer">{{ $risks->appends(request()->query())->links() }}</div>
            @endif
        </div>

        {{-- Forms --}}
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-file-alt text-secondary"></i> {{ trans('iso_content.forms') }}</h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-md mb-0">
                        <tr>
                            <th>{{ trans('iso_content.key') }}</th>
                            <th>{{ trans('iso_content.category') }}</th>
                            <th>{{ trans('iso_content.name') }}</th>
                            <th>{{ trans('iso_content.sections') }}</th>
                            <th>{{ trans('iso_content.fields') }}</th>
                            <th>{{ trans('iso_content.version') }}</th>
                            <th>{{ trans('iso_content.actions') }}</th>
                        </tr>
                        @forelse($forms as $item)
                        <tr>
                            <td><bdi class="font-12">{{ $item->technical_key }}</bdi></td>
                            <td><span class="badge badge-info">{{ $item->category }}</span></td>
                            <td>{{ \App\Models\IsoOverlay::pickLocale($item->name) ?: '-' }}</td>
                            <td>{{ count($item->schema ?? []) }}</td>
                            <td>{{ count($item->allFields()) }}</td>
                            <td>v{{ $item->version }}</td>
                            <td>
                                <a href="{{ route('admin.iso_content.forms.show', $item->technical_key) }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @can('admin_iso_content_manage')
                                <a href="{{ route('admin.iso_content.edit', ['forms', $item->technical_key]) }}" class="btn btn-sm btn-light">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted">{{ trans('iso_content.none') }}</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
            @if($forms->hasPages())
            <div class="card-footer">{{ $forms->appends(request()->query())->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection