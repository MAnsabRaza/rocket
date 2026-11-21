@extends('admin.layouts.app')

@section('content')
<section class="section">
    <div class="section-header">
        <h1><i class="fas fa-chart-line text-warning"></i> {{ $pageTitle }}</h1>
        <div class="section-header-button">
            @include('admin.iso_content._actions')
            <a href="{{ route('admin.iso_content.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> {{ trans('iso_content.back') }}
            </a>
        </div>
    </div>

    <div class="section-body">
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
                                <th>{{ trans('iso_content.clause') }}</th>
                                <td><bdi>{{ $item->clause_ref ?: '-' }}</bdi></td>
                            </tr>
                            <tr>
                                <th>{{ trans('iso_content.unit') }}</th>
                                <td>{{ $item->unit }}</td>
                            </tr>
                            <tr>
                                <th>{{ trans('iso_content.frequency') }}</th>
                                <td>{{ $item->frequency }}</td>
                            </tr>
                            <tr>
                                <th>{{ trans('iso_content.calculation') }}</th>
                                <td><span class="badge badge-{{ $item->calculation === 'automatic' ? 'success' : 'warning' }}">{{ $item->calculation }}</span></td>
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
                    <div class="card-header"><h4>{{ trans('iso_content.formula') }}</h4></div>
                    <div class="card-body">
                        <div class="alert alert-light">
                            <code class="font-14">{{ $item->formula }}</code>
                        </div>

                        <h6>{{ trans('iso_content.thresholds') }}</h6>
                        @if($item->thresholds)
                        <table class="table table-sm mb-0">
                            <tr>
                                <th>{{ trans('iso_content.target') }}</th>
                                <td><strong class="text-success">{{ $item->thresholds['target'] ?? '-' }}</strong></td>
                            </tr>
                            <tr>
                                <th>{{ trans('iso_content.warning') }}</th>
                                <td><strong class="text-warning">{{ $item->thresholds['warning'] ?? '-' }}</strong></td>
                            </tr>
                            <tr>
                                <th>{{ trans('iso_content.critical') }}</th>
                                <td><strong class="text-danger">{{ $item->thresholds['critical'] ?? '-' }}</strong></td>
                            </tr>
                            <tr>
                                <th>{{ trans('iso_content.direction') }}</th>
                                <td>
                                    @if(($item->thresholds['direction'] ?? '') === 'higher_is_better')
                                        <i class="fas fa-arrow-up text-success"></i> {{ trans('iso_content.higher_is_better') }}
                                    @else
                                        <i class="fas fa-arrow-down text-danger"></i> {{ trans('iso_content.lower_is_better') }}
                                    @endif
                                </td>
                            </tr>
                        </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Roles --}}
        @if(!empty($item->roles))
        <div class="card">
            <div class="card-header"><h4>{{ trans('iso_content.roles') }}</h4></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    @foreach($item->roles as $k => $v)
                    <tr>
                        <th width="30%">{{ $k }}</th>
                        <td><span class="badge badge-light">{{ $v }}</span></td>
                    </tr>
                    @endforeach
                </table>
            </div>
        </div>
        @endif

        {{-- Source --}}
        @if(!empty($item->source))
        <div class="card">
            <div class="card-header"><h4>{{ trans('iso_content.source') }}</h4></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    @foreach($item->source as $k => $v)
                    <tr>
                        <th width="30%">{{ $k }}</th>
                        <td><bdi>{{ $v }}</bdi></td>
                    </tr>
                    @endforeach
                </table>
            </div>
        </div>
        @endif
    </div>
</section>
@endsection