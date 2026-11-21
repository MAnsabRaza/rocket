@extends('admin.layouts.app')

@section('content')
<section class="section">
    <div class="section-header">
        <h1><i class="fas fa-exclamation-triangle text-danger"></i> {{ $pageTitle }}</h1>
        <div class="section-header-button">
            @include('admin.iso_content._actions')
            <a href="{{ route('admin.iso_content.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> {{ trans('iso_content.back') }}
            </a>
        </div>
    </div>

    <div class="section-body">
        {{-- Meta --}}
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
                                <th>{{ trans('iso_content.description') }}</th>
                                <td>{{ \App\Models\IsoOverlay::pickLocale($item->description) ?: '-' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h4>{{ trans('iso_content.scoring') }}</h4></div>
                    <div class="card-body text-center">
                        @php $score = ($item->default_probability ?? 0) * ($item->default_impact ?? 0); @endphp
                        <h2 class="mb-3">
                            @if($score >= 15)
                                <span class="badge badge-danger" style="font-size:2rem;padding:10px 20px;">{{ $score }}</span>
                            @elseif($score >= 8)
                                <span class="badge badge-warning" style="font-size:2rem;padding:10px 20px;">{{ $score }}</span>
                            @else
                                <span class="badge badge-success" style="font-size:2rem;padding:10px 20px;">{{ $score }}</span>
                            @endif
                        </h2>
                        <div class="row">
                            <div class="col-6">
                                <div class="text-muted font-12">{{ trans('iso_content.probability') }}</div>
                                <h4>{{ $item->default_probability }} / 5</h4>
                            </div>
                            <div class="col-6">
                                <div class="text-muted font-12">{{ trans('iso_content.impact') }}</div>
                                <h4>{{ $item->default_impact }} / 5</h4>
                            </div>
                        </div>
                        <hr>
                        <div class="text-muted font-12">{{ trans('iso_content.score_formula') }}: {{ trans('iso_content.probability') }} × {{ trans('iso_content.impact') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Causes --}}
        @if(!empty($item->causes))
        <div class="card">
            <div class="card-header"><h4>{{ trans('iso_content.causes') }}</h4></div>
            <div class="card-body">
                <ul class="mb-0">
                    @foreach($item->causes as $cause)
                        <li>{{ \App\Models\IsoOverlay::pickLocale($cause) ?: (is_string($cause) ? $cause : '-') }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        {{-- Consequences --}}
        @if(!empty($item->consequences))
        <div class="card">
            <div class="card-header"><h4>{{ trans('iso_content.consequences') }}</h4></div>
            <div class="card-body">
                <ul class="mb-0">
                    @foreach($item->consequences as $c)
                        <li>{{ \App\Models\IsoOverlay::pickLocale($c) ?: (is_string($c) ? $c : '-') }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        {{-- Mitigations --}}
        @if(!empty($item->mitigations))
        <div class="card">
            <div class="card-header"><h4>{{ trans('iso_content.mitigations') }}</h4></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <tr>
                            <th width="20%">{{ trans('iso_content.strategy') }}</th>
                            <th>{{ trans('iso_content.label') }}</th>
                        </tr>
                        @foreach($item->mitigations as $m)
                        <tr>
                            <td>
                                @php $strategyColors = ['avoid'=>'danger','reduce'=>'warning','transfer'=>'info','accept'=>'secondary']; @endphp
                                <span class="badge badge-{{ $strategyColors[$m['strategy']] ?? 'light' }}">{{ $m['strategy'] }}</span>
                            </td>
                            <td>{{ \App\Models\IsoOverlay::pickLocale($m['label'] ?? []) }}</td>
                        </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- Controls --}}
        @if(!empty($item->controls))
        <div class="card">
            <div class="card-header"><h4>{{ trans('iso_content.controls') }}</h4></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <tr>
                            <th width="20%">{{ trans('iso_content.type') }}</th>
                            <th>{{ trans('iso_content.label') }}</th>
                        </tr>
                        @foreach($item->controls as $c)
                        <tr>
                            <td>
                                @php $typeColors = ['preventive'=>'success','detective'=>'warning','corrective'=>'danger']; @endphp
                                <span class="badge badge-{{ $typeColors[$c['type']] ?? 'light' }}">{{ $c['type'] }}</span>
                            </td>
                            <td>{{ \App\Models\IsoOverlay::pickLocale($c['label'] ?? []) }}</td>
                        </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</section>
@endsection