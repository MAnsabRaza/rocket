@extends('admin.layouts.app')

@section('content')
<section class="section">
    <div class="section-header">
        <h1><i class="fas fa-file-alt text-secondary"></i> {{ $pageTitle }}</h1>
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
                                <th>{{ trans('iso_content.total_sections') }}</th>
                                <td>{{ count($item->schema ?? []) }}</td>
                            </tr>
                            <tr>
                                <th>{{ trans('iso_content.total_fields') }}</th>
                                <td>{{ count($item->allFields()) }}</td>
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
                @if(!empty($item->approval))
                <div class="card">
                    <div class="card-header"><h4>{{ trans('iso_content.approval_chain') }}</h4></div>
                    <div class="card-body">
                        @foreach($item->approval['chain'] ?? [] as $i => $step)
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge badge-primary mr-2">{{ $i + 1 }}</span>
                            <span class="badge badge-light">{{ $step['role'] ?? '-' }}</span>
                            @if(!empty($step['required']))
                                <span class="badge badge-danger ml-2">{{ trans('iso_content.required') }}</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Sections & Fields --}}
        @foreach($item->schema ?? [] as $section)
        <div class="card">
            <div class="card-header">
                <h4>
                    <span class="badge badge-primary mr-2">{{ $section['key'] }}</span>
                    {{ \App\Models\IsoOverlay::pickLocale($section['label'] ?? []) }}
                </h4>
                <div class="card-header-action">
                    <span class="badge badge-light">{{ count($section['fields'] ?? []) }} {{ trans('iso_content.fields') }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <tr>
                            <th width="10%">{{ trans('iso_content.key') }}</th>
                            <th width="10%">{{ trans('iso_content.type') }}</th>
                            <th>{{ trans('iso_content.label') }}</th>
                            <th width="8%">{{ trans('iso_content.required') }}</th>
                            <th>{{ trans('iso_content.properties') }}</th>
                        </tr>
                        @foreach($section['fields'] ?? [] as $f)
                        <tr>
                            <td><bdi class="font-12">{{ $f['key'] }}</bdi></td>
                            <td><span class="badge badge-info">{{ $f['type'] }}</span></td>
                            <td>{{ \App\Models\IsoOverlay::pickLocale($f['label'] ?? []) ?: '-' }}</td>
                            <td>
                                @if(!empty($f['required']))
                                    <span class="text-danger"><i class="fas fa-check"></i></span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="font-12">
                                @if(!empty($f['default']))
                                    <div><small class="text-muted">{{ trans('iso_content.default') }}:</small> <bdi>{{ $f['default'] }}</bdi></div>
                                @endif
                                @if(!empty($f['help']))
                                    <div><small class="text-muted">{{ trans('iso_content.help') }}:</small> {{ \App\Models\IsoOverlay::pickLocale($f['help']) }}</div>
                                @endif
                                @if(!empty($f['options']))
                                    <div><small class="text-muted">{{ trans('iso_content.options') }}:</small> {{ implode(', ', array_column($f['options'], 'label')) }}</div>
                                @endif
                                @if(!empty($f['source']))
                                    <div><small class="text-muted">{{ trans('iso_content.source') }}:</small> <bdi>{{ $f['source'] }}</bdi></div>
                                @endif
                                @if(!empty($f['accept']))
                                    <div><small class="text-muted">{{ trans('iso_content.accept') }}:</small> {{ implode(', ', $f['accept']) }}</div>
                                @endif
                                @if(!empty($f['columns']))
                                    <div><small class="text-muted">{{ trans('iso_content.columns') }}:</small> {{ implode(', ', array_column($f['columns'], 'key')) }}</div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</section>
@endsection