@extends('admin.layouts.app')

@section('content')
<section class="section">
    <div class="section-header">
        <h1><i class="fas fa-edit text-primary"></i> {{ $pageTitle }}</h1>
        <div class="section-header-button">
            <a href="{{ route('admin.iso_content.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> {{ trans('iso_content.back') }}
            </a>
        </div>
    </div>

    <div class="section-body">
        @include('admin.iso_content._messages')

        <form method="post" action="{{ route('admin.iso_content.update', [$type, $item->technical_key]) }}">
            @csrf

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h4>{{ trans('iso_content.details') }}</h4></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>{{ trans('iso_content.key') }}</label>
                                <input class="form-control" value="{{ $item->technical_key }}" disabled>
                            </div>
                            <div class="form-group">
                                <label>{{ trans('iso_content.version') }}</label>
                                <input class="form-control" value="v{{ $item->version }}" disabled>
                            </div>

                            @foreach(config('iso_overlay.locales', ['en']) as $locale)
                                <div class="form-group">
                                    <label>{{ trans('iso_content.name') }} ({{ strtoupper($locale) }})</label>
                                    <input name="name[{{ $locale }}]"
                                           class="form-control"
                                           value="{{ old('name.' . $locale, $item->name[$locale] ?? '') }}"
                                           @if($locale === 'en') required @endif>
                                    @error('name.' . $locale)
                                        <span class="text-danger font-12">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label>{{ trans('iso_content.description') }} ({{ strtoupper($locale) }})</label>
                                    <textarea name="description[{{ $locale }}]" class="form-control" rows="2">{{ old('description.' . $locale, $item->description[$locale] ?? '') }}</textarea>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><h4>{{ trans('iso_content.payload') }}</h4></div>
                        <div class="card-body">
                            <p class="text-muted font-12">{{ trans('iso_content.payload_help') }}</p>
                            @error('payload')
                                <span class="text-danger font-12 d-block mb-2">{{ $message }}</span>
                            @enderror
                            <textarea name="payload" rows="24" class="form-control font-12" spellcheck="false"
                                      style="font-family: monospace">{{ old('payload', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <button class="btn btn-primary">
                        <i class="fas fa-save"></i> {{ trans('iso_content.save') }}
                    </button>
                    <a href="{{ route('admin.iso_content.index') }}" class="btn btn-light">{{ trans('iso_content.cancel') }}</a>
                </div>
            </div>
        </form>
    </div>
</section>
@endsection
