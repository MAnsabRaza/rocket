@extends('admin.layouts.app')

@php
    // Fields are generated from the type's rules in config/iso_overlay.php.
    $source = $def['ref']['source'] ?? 'none';
    $isLibrary = $source === 'library';
    $fields = [];
    foreach (($def['data'] ?? []) as $field => $rule) {
        if (str_contains($field, '*') || $field === 'levels' || preg_match('/(^|\|)array(\||$)/', $rule)) {
            continue;
        }
        $options = null;
        if (preg_match('/in:([^|]+)/', $rule, $m)) {
            $options = explode(',', $m[1]);
        }
        $fields[$field] = [
            'options' => $options,
            'numeric' => (bool)preg_match('/numeric|integer/', $rule),
            'required' => str_contains($rule, 'required'),
            'long' => (bool)preg_match('/max:(\d+)/', $rule, $mm) && (int)$mm[1] > 255,
        ];
    }
    $hasLevels = array_key_exists('levels', $def['data'] ?? []);
    $data = $item->data ?? [];
    $levelsText = collect($data['levels'] ?? [])->map(fn($l) => ($l['max_score'] ?? '') . '|' . ($l['label'] ?? ''))->implode("\n");
    $fieldName = fn($field) => 'data[' . str_replace('.', '][', $field) . ']';
@endphp

@section('content')
<section class="section">
    <div class="section-header">
        <h1>{{ $item ? trans('iso_overlay.edit_item') : trans('iso_overlay.add_item') }}: {{ $type }}</h1>
        <div class="section-header-button">
            <a href="{{ route('admin.iso_overlays.version', $version->id) }}" class="btn btn-light"><bdi>{{ $version->overlay->code }}</bdi> v{{ $version->version_no }}</a>
        </div>
    </div>

    <div class="section-body">
        @include('admin.iso_overlays._messages')
        <div class="card">
            <div class="card-body">
                <form method="post" action="{{ $item ? route('admin.iso_overlays.items.update', $item->id) : route('admin.iso_overlays.items.store', $version->id) }}">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label>{{ trans('iso_overlay.key') }}</label>
                            <input type="text" name="key" class="form-control" dir="ltr" maxlength="100" value="{{ old('key', $item->key ?? '') }}" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>{{ trans('iso_overlay.parent_key') }}</label>
                            <input type="text" name="parent_key" class="form-control" dir="ltr" maxlength="100" value="{{ old('parent_key', $item->parent_key ?? '') }}">
                        </div>
                        <div class="form-group col-md-4">
                            <label>{{ trans('iso_overlay.sort_order') }}</label>
                            <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', $item->sort_order ?? 0) }}">
                        </div>
                    </div>

                @if($source === 'flinkiso')
<div class="form-group">
    <label>{{ trans('iso_overlay.reference') }} ({{ $def['ref']['table'] }})</label>
    <select name="ref_id" class="form-control" required>
        <option value="">-</option>
        @foreach($options as $id => $label)
        <option value="{{ $id }}" {{ old('ref_id', $item->ref_id ?? '') == $id ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>
@elseif($source === 'library')
{{-- M9: local content library dropdown --}}
<div class="form-group">
    <label>{{ trans('iso_overlay.reference') }} ({{ $def['ref']['table'] }})</label>
    <select name="ref_id" class="form-control" required>
        <option value="">-</option>
        @foreach($options as $id => $label)
        <option value="{{ $id }}" {{ old('ref_id', $item->ref_id ?? '') == $id ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    <small class="text-muted">Select from local ISO Content Library (M9)</small>
</div>
@elseif($source === 'optional')
<div class="form-group">
    <label>{{ trans('iso_overlay.external_reference') }}</label>
    <input type="text" name="ref_id" class="form-control" dir="ltr" maxlength="64" value="{{ old('ref_id', $item->ref_id ?? '') }}">
</div>
@endif

                    <div class="row">
                        @foreach($locales as $locale)
                            <div class="form-group col-md-4">
                                <label>{{ trans('iso_overlay.label') }} ({{ strtoupper($locale) }})</label>
                                <input type="text" name="label[{{ $locale }}]" class="form-control" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" maxlength="500" value="{{ old('label.' . $locale, $item->label[$locale] ?? '') }}" {{ $locale === 'en' ? 'required' : '' }}>
                            </div>
                        @endforeach
                    </div>

                    @if(!$isLibrary && ($fields || $hasLevels))
                        <h6 class="mt-2">{{ trans('iso_overlay.data') }}</h6>
                        <div class="row">
                            @foreach($fields as $field => $f)
                                @php $value = old('data.' . $field, data_get($data, $field)); @endphp
                                <div class="form-group {{ $f['long'] ? 'col-md-12' : 'col-md-4' }}">
                                    <label><bdi>{{ $field }}</bdi>{{ $f['required'] ? ' *' : '' }}</label>
                                    @if($f['options'])
                                        <select name="{{ $fieldName($field) }}" class="form-control">
                                            <option value="">-</option>
                                            @foreach($f['options'] as $option)
                                                <option value="{{ $option }}" {{ (string)$value === $option ? 'selected' : '' }}>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($f['long'])
                                        <textarea name="{{ $fieldName($field) }}" class="form-control" rows="2">{{ $value }}</textarea>
                                    @else
                                        <input type="{{ $f['numeric'] ? 'number' : 'text' }}" {!! $f['numeric'] ? 'step="any"' : '' !!} name="{{ $fieldName($field) }}" class="form-control" value="{{ $value }}">
                                    @endif
                                </div>
                            @endforeach
                            @if($hasLevels)
                                <div class="form-group col-md-12">
                                    <label>{{ trans('iso_overlay.levels') }} *</label>
                                    <textarea name="levels_text" class="form-control" dir="ltr" rows="4" placeholder="6|Low&#10;14|Medium&#10;25|High">{{ old('levels_text', $levelsText) }}</textarea>
                                </div>
                            @endif
                        </div>
                    @endif

                    <button type="submit" class="btn btn-primary">{{ trans('iso_overlay.save') }}</button>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
