@extends('admin.layouts.app')

@section('content')
<section class="section">
    <div class="section-header">
        <h1><i class="fas fa-project-diagram text-primary"></i> {{ $pageTitle }}</h1>
        <div class="section-header-button">
            @include('admin.iso_content._actions')
            <a href="{{ route('admin.iso_content.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> {{ trans('iso_content.back') }}
            </a>
        </div>
    </div>

    <div class="section-body">
        {{-- Meta Info --}}
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h4>{{ trans('iso_content.details') }}</h4></div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">{{ trans('iso_content.key') }}</th>
                                <td><bdi>{{ $item->technical_key }}</bdi></td>
                            </tr>
                            <tr>
                                <th>{{ trans('iso_content.category') }}</th>
                                <td><span class="badge badge-info">{{ $item->category }}</span></td>
                            </tr>
                            <tr>
                                <th>{{ trans('iso_content.version') }}</th>
                                <td>v{{ $item->version }}</td>
                            </tr>
                            <tr>
                                <th>{{ trans('iso_content.status') }}</th>
                                <td>
                                    @if($item->is_active)
                                        <span class="badge badge-success">{{ trans('iso_content.active') }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ trans('iso_content.inactive') }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>{{ trans('iso_content.description') }}</th>
                                <td>{{ \App\Models\IsoOverlay::pickLocale($item->description) ?: '-' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h4>{{ trans('iso_content.roles') }}</h4></div>
                    <div class="card-body">
                        @forelse($item->roles ?? [] as $role)
                            <span class="badge badge-light mr-1 mb-1">{{ $role }}</span>
                        @empty
                            <span class="text-muted">{{ trans('iso_content.none') }}</span>
                        @endforelse

                        @if(!empty($item->settings))
                        <hr>
                        <h6>{{ trans('iso_content.settings') }}</h6>
                        <table class="table table-sm mb-0">
                            @foreach($item->settings as $k => $v)
                            <tr>
                                <th class="font-12">{{ $k }}</th>
                                <td class="font-12">
                                    @if(is_array($v))
                                        {{ implode(', ', $v) }}
                                    @else
                                        {{ $v }}
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- States --}}
        <div class="card">
            <div class="card-header"><h4>{{ trans('iso_content.states') }}</h4></div>
            <div class="card-body">
                <div class="row">
                    @foreach($item->states ?? [] as $state)
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card border-left-{{ $state['kind'] === 'initial' ? 'primary' : ($state['kind'] === 'final' ? 'success' : 'secondary') }}">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ \App\Models\IsoOverlay::pickLocale($state['label'] ?? []) }}</strong>
                                        <div class="text-muted font-11">{{ $state['key'] }}</div>
                                    </div>
                                    <div>
                                        @if($state['kind'] === 'initial')
                                            <span class="badge badge-primary">{{ trans('iso_content.initial') }}</span>
                                        @elseif($state['kind'] === 'final')
                                            <span class="badge badge-success">{{ trans('iso_content.final') }}</span>
                                        @endif
                                    </div>
                                </div>
                                @if(!empty($state['color']))
                                <div class="mt-2">
                                    <span style="display:inline-block;width:20px;height:20px;background:{{ $state['color'] }};border-radius:3px;"></span>
                                    <code class="font-11 ml-1">{{ $state['color'] }}</code>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Transitions --}}
        <div class="card">
            <div class="card-header"><h4>{{ trans('iso_content.transitions') }}</h4></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <tr>
                            <th>{{ trans('iso_content.from') }}</th>
                            <th></th>
                            <th>{{ trans('iso_content.to') }}</th>
                            <th>{{ trans('iso_content.label') }}</th>
                            <th>{{ trans('iso_content.roles') }}</th>
                            <th>{{ trans('iso_content.notifications') }}</th>
                        </tr>
                        @foreach($item->transitions ?? [] as $t)
                        <tr>
                            <td><span class="badge badge-secondary">{{ $t['from'] }}</span></td>
                            <td><i class="fas fa-arrow-right text-muted"></i></td>
                            <td><span class="badge badge-primary">{{ $t['to'] }}</span></td>
                            <td>{{ \App\Models\IsoOverlay::pickLocale($t['label'] ?? []) ?: '-' }}</td>
                            <td>
                                @foreach($t['roles'] ?? [] as $r)
                                    <span class="badge badge-light">{{ $r }}</span>
                                @endforeach
                            </td>
                            <td>
                                @foreach($t['notifications'] ?? [] as $n)
                                    <span class="badge badge-light">{{ $n }}</span>
                                @endforeach
                                @if(!empty($t['require_comment']))
                                    <span class="badge badge-warning">{{ trans('iso_content.requires_comment') }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection