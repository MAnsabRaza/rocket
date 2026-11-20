@extends('admin.layouts.app')

@section('content')
<section class="section">
    <div class="section-header">
        <h1>{{ trans('iso_overlay.new_overlay') }}</h1>
    </div>

    <div class="section-body">
        @include('admin.iso_overlays._messages')
        <div class="card">
            <div class="card-body">
                <form method="post" action="{{ route('admin.iso_overlays.store') }}">
                    @csrf
                    <div class="form-group">
                        <label>{{ trans('iso_overlay.code') }}</label>
                        <input type="text" name="code" class="form-control" dir="ltr" maxlength="64" value="{{ old('code') }}" required>
                    </div>
                    @foreach($locales as $locale)
                        <div class="form-group">
                            <label>{{ trans('iso_overlay.name') }} ({{ strtoupper($locale) }})</label>
                            <input type="text" name="name[{{ $locale }}]" class="form-control" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" maxlength="500" value="{{ old('name.' . $locale) }}" {{ $locale === 'en' ? 'required' : '' }}>
                        </div>
                    @endforeach
                    @foreach($locales as $locale)
                        <div class="form-group">
                            <label>{{ trans('iso_overlay.description') }} ({{ strtoupper($locale) }})</label>
                            <textarea name="description[{{ $locale }}]" class="form-control" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" rows="2">{{ old('description.' . $locale) }}</textarea>
                        </div>
                    @endforeach
                    <div class="form-group">
                        <label>{{ trans('iso_overlay.standard') }}</label>
                        <select name="flinkiso_standard_id" class="form-control">
                            <option value="">-</option>
                            @foreach($standards as $id => $label)
                                <option value="{{ $id }}" {{ old('flinkiso_standard_id') == $id ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ trans('iso_overlay.framework_label') }}</label>
                        <input type="text" name="framework_label" class="form-control" maxlength="120" value="{{ old('framework_label') }}">
                    </div>
                    <div class="form-group">
                        <label>{{ trans('iso_overlay.edition') }}</label>
                        <input type="text" name="edition" class="form-control" dir="ltr" maxlength="60" value="{{ old('edition') }}">
                    </div>
                    <div class="form-group">
                        <label>{{ trans('iso_overlay.change_note') }}</label>
                        <input type="text" name="change_note" class="form-control" maxlength="1000" value="{{ old('change_note') }}">
                    </div>
                    <button type="submit" class="btn btn-primary">{{ trans('iso_overlay.save') }}</button>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
