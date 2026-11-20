{{--
    Readable view of an item's structured data. Generic: works for any item type
    declared in config/iso_overlay.php. Lists of rows with max_score and label
    (risk levels) read as "up to 6: Low"; other nested values as "name: value".
--}}
@php
    $fmt = function ($value) {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        return is_scalar($value) ? (string)$value : '';
    };
    $name = fn($key) => ucfirst(str_replace('_', ' ', (string)$key));
@endphp
@if(!empty($data) && is_array($data))
    <dl class="mb-0 font-12">
        @foreach($data as $key => $value)
            <dt class="d-inline font-weight-bold">{{ $name($key) }}:</dt>
            <dd class="d-inline mb-0">
                @if(is_array($value) && array_is_list($value))
                    @foreach($value as $row)
                        @if(is_array($row) && isset($row['max_score'], $row['label']))
                            <bdi>{{ trans('iso_overlay.up_to') }} {{ $fmt($row['max_score']) }}: {{ $fmt($row['label']) }}</bdi>@if(!$loop->last);@endif
                        @elseif(is_array($row))
                            <bdi>{{ collect($row)->map(fn($v, $k) => $name($k) . ' ' . $fmt($v))->implode(', ') }}</bdi>@if(!$loop->last);@endif
                        @else
                            <bdi>{{ $fmt($row) }}</bdi>@if(!$loop->last),@endif
                        @endif
                    @endforeach
                @elseif(is_array($value))
                    <bdi>{{ collect($value)->map(fn($v, $k) => $name($k) . ' ' . $fmt($v))->implode(', ') }}</bdi>
                @else
                    <bdi>{{ $fmt($value) }}</bdi>
                @endif
            </dd>
            <br>
        @endforeach
    </dl>
@endif
