@extends('admin.layouts.app')

@section('content')
<section class="section">
    <div class="section-header">
        <h1><i class="fas fa-clipboard-check text-success"></i> {{ $pageTitle }}</h1>
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
                                <th>{{ trans('iso_content.standard') }}</th>
                                <td><bdi>{{ $item->standard_ref ?: '-' }}</bdi></td>
                            </tr>
                            <tr>
                                <th>{{ trans('iso_content.version') }}</th>
                                <td>v{{ $item->version }}</td>
                            </tr>
                            <tr>
                                <th>{{ trans('iso_content.total_questions') }}</th>
                                <td><strong>{{ $item->questionCount() }}</strong></td>
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
                    <div class="card-body">
                        @if($item->scoring)
                            <table class="table table-sm mb-0">
                                @foreach($item->scoring as $k => $v)
                                <tr>
                                    <th class="font-12">{{ $k }}</th>
                                    <td class="font-12">
                                        @if(is_array($v))
                                            {{ json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                                        @elseif(is_bool($v))
                                            {{ $v ? 'true' : 'false' }}
                                        @else
                                            {{ $v }}
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </table>
                        @else
                            <span class="text-muted">{{ trans('iso_content.none') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Sections & Questions --}}
        @foreach($item->schema ?? [] as $section)
        <div class="card">
            <div class="card-header">
                <h4>
                    <span class="badge badge-primary mr-2">{{ $section['key'] }}</span>
                    {{ \App\Models\IsoOverlay::pickLocale($section['label'] ?? []) }}
                </h4>
                <div class="card-header-action">
                    <span class="badge badge-light">{{ count($section['questions'] ?? []) }} {{ trans('iso_content.questions') }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <tr>
                            <th width="10%">{{ trans('iso_content.key') }}</th>
                            <th width="10%">{{ trans('iso_content.type') }}</th>
                            <th>{{ trans('iso_content.question') }}</th>
                            <th width="8%">{{ trans('iso_content.required') }}</th>
                            <th>{{ trans('iso_content.guidance') }}</th>
                        </tr>
                        @foreach($section['questions'] ?? [] as $q)
                        <tr>
                            <td><bdi class="font-12">{{ $q['key'] }}</bdi></td>
                            <td><span class="badge badge-info">{{ $q['type'] }}</span></td>
                            <td>{{ \App\Models\IsoOverlay::pickLocale($q['label'] ?? []) ?: '-' }}</td>
                            <td>
                                @if(!empty($q['required']))
                                    <span class="text-danger"><i class="fas fa-check"></i></span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="font-12 text-muted">
                                {{ \App\Models\IsoOverlay::pickLocale($q['guidance'] ?? []) ?: '-' }}
                                @if(!empty($q['scale']))
                                    <br><small>{{ trans('iso_content.scale') }}: {{ $q['scale'][0] }}-{{ $q['scale'][1] }}</small>
                                @endif
                                @if(!empty($q['options']))
                                    <br><small>{{ trans('iso_content.options') }}: {{ implode(', ', array_column($q['options'], 'label')) }}</small>
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
