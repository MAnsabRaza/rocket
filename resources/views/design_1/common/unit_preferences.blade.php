@php
    $unitService = app(\App\Services\UnitConversionService::class);
    $authUser = $user ?? (auth()->check() ? auth()->user() : null);
    $errors = $errors ?? session()->get('errors', new \Illuminate\Support\ViewErrorBag);
    $isHeaderPicker = !empty($isHeaderPicker);
    $isDrawerPicker = !empty($isDrawerPicker);
    $isAutoSubmit = $isHeaderPicker || $isDrawerPicker;
    $formAction = $formAction ?? route('unitPreferences.update');
    $triggerClass = $triggerClass ?? 'bg-gray-100';
    $triggerIconClass = $triggerIconClass ?? 'text-gray-500';
    $menuClass = $menuClass ?? '';

    $unitTypes = [
        'length' => __('units.unit_preferences.types.length'),
        'area' => __('units.unit_preferences.types.area'),
        'mass' => __('units.unit_preferences.types.mass'),
        'speed' => __('units.unit_preferences.types.speed'),
        'temperature' => __('units.unit_preferences.types.temperature'),
        'force' => __('units.unit_preferences.types.force'),
        'volume' => __('units.unit_preferences.types.volume'),
        'energy' => __('units.unit_preferences.types.energy'),
        'heat_flow_rate' => __('units.unit_preferences.types.heat_flow_rate'),
    ];

    $unitPreferences = $unitPreferences ?? [];
    foreach ($unitTypes as $type => $title) {
        $unitPreferences[$type] = $unitPreferences[$type] ?? $unitService->getAvailableUnits($type);
    }

    // Preview text is generated from a fixed reference date/time rather than
    // hardcoded strings, so it renders in the current locale's month/day
    // names (Carbon ships its own locale data for this, no extra package
    // needed) instead of always showing English regardless of locale.
    $previewMoment = \Illuminate\Support\Carbon::createFromFormat('Y-m-d H:i', '2025-09-22 17:25')
        ->locale(app()->getLocale());

    $dateFormats = collect(['F j, Y', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'd.m.Y'])
        ->mapWithKeys(fn ($code) => [$code => ['label' => $previewMoment->translatedFormat($code), 'code' => $code]])
        ->all();

    $timeFormats = collect(['g:i a', 'g:i A', 'H:i'])
        ->mapWithKeys(fn ($code) => [$code => ['label' => $previewMoment->translatedFormat($code), 'code' => $code]])
        ->all();

    $weekDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    // Date, time and week start now persist in user_format_preferences for a
    // signed-in user. The session and cookie remain the guest path and the
    // fallback, so nothing regresses when no stored row exists.
    $displayPreferences = app(\App\Services\UnitConversion\DisplayPreferenceRepository::class)->all($authUser);

    $selectedDateFormat = old('preferred_date_format', $displayPreferences['date_format'] ?? ($authUser->preferred_date_format ?? session('preferred_date_format', request()->cookie('preferred_date_format', 'F j, Y'))));
    $selectedTimeFormat = old('preferred_time_format', $displayPreferences['time_format'] ?? ($authUser->preferred_time_format ?? session('preferred_time_format', request()->cookie('preferred_time_format', 'g:i a'))));
    $selectedWeekStart = old('preferred_week_start', $displayPreferences['week_start'] ?? ($authUser->preferred_week_start ?? session('preferred_week_start', request()->cookie('preferred_week_start', 'Monday'))));
@endphp

@if($isHeaderPicker)
    <div class="js-unit-select {{ $dropdownClass ?? 'theme-header-2__dropdown' }} position-relative">
        <div class="size-32 d-flex-center {{ $triggerClass }} rounded-8 cursor-pointer" title="{{ __('units.unit_preferences.tooltip') }}">
            <x-iconsax-lin-setting-2 class="icons {{ $triggerIconClass }}" width="18px" height="18px"/>
        </div>

        <div class="{{ $menuClass }} js-common-unit-menu common-units-menu">
@endif

<div class="common-units-card bg-white">
    @if($isAutoSubmit)
        <form action="{{ $formAction }}" method="post" class="js-common-units-form">
            {{ csrf_field() }}

            <input type="hidden" name="previous_url" value="{{ url()->current() }}">
    @else
        <div class="js-common-units-form">
    @endif

        <h3 class="common-units-title text-center">{{ __('units.unit_preferences.title_part1') }}<br>{{ __('units.unit_preferences.title_part2') }}</h3>

        <div class="common-unit-section">
            <h4>{{ __('units.unit_preferences.date_format') }}</h4>

            @foreach($dateFormats as $value => $item)
                <label class="common-unit-option">
                    <input type="radio" name="preferred_date_format" value="{{ $value }}" {{ $selectedDateFormat == $value ? 'checked' : '' }} {!! $isAutoSubmit ? 'data-auto-submit="true"' : '' !!}>
                    <span>{{ $item['label'] }}</span>
                    <span class="common-unit-code">{{ $item['code'] }}</span>
                </label>
            @endforeach

            <label class="common-unit-option">
                <input type="radio" name="preferred_date_format" value="custom" {{ !array_key_exists($selectedDateFormat, $dateFormats) ? 'checked' : '' }} {!! $isAutoSubmit ? 'data-auto-submit="true"' : '' !!}>
                <span>{{ __('units.unit_preferences.custom') }}</span>
                <input type="text" name="preferred_custom_date_format" value="{{ !array_key_exists($selectedDateFormat, $dateFormats) ? $selectedDateFormat : 'F j, Y' }}" class="common-unit-custom-input" {!! $isAutoSubmit ? 'data-auto-submit="true"' : '' !!}>
            </label>

            <p class="common-unit-preview">{{ __('units.unit_preferences.preview') }} {{ $dateFormats['F j, Y']['label'] }}</p>
        </div>

        <div class="common-unit-section">
            <h4>{{ __('units.unit_preferences.time_format') }}</h4>

            @foreach($timeFormats as $value => $item)
                <label class="common-unit-option">
                    <input type="radio" name="preferred_time_format" value="{{ $value }}" {{ $selectedTimeFormat == $value ? 'checked' : '' }} {!! $isAutoSubmit ? 'data-auto-submit="true"' : '' !!}>
                    <span>{{ $item['label'] }}</span>
                    <span class="common-unit-code">{{ $item['code'] }}</span>
                </label>
            @endforeach

            <label class="common-unit-option">
                <input type="radio" name="preferred_time_format" value="custom" {{ !array_key_exists($selectedTimeFormat, $timeFormats) ? 'checked' : '' }} {!! $isAutoSubmit ? 'data-auto-submit="true"' : '' !!}>
                <span>{{ __('units.unit_preferences.custom') }}</span>
                <input type="text" name="preferred_custom_time_format" value="{{ !array_key_exists($selectedTimeFormat, $timeFormats) ? $selectedTimeFormat : 'g:i a' }}" class="common-unit-custom-input" {!! $isAutoSubmit ? 'data-auto-submit="true"' : '' !!}>
            </label>

            <p class="common-unit-preview">{{ __('units.unit_preferences.preview') }} {{ $timeFormats['g:i a']['label'] }}</p>
        </div>

        <div class="common-unit-section">
            <h4>{{ __('units.unit_preferences.week_starts_on') }}</h4>
            <select name="preferred_week_start" class="common-unit-select" {!! $isAutoSubmit ? 'data-auto-submit="true"' : '' !!}>
                @foreach($weekDays as $day)
                    <option value="{{ $day }}" {{ $selectedWeekStart == $day ? 'selected' : '' }}>{{ __("units.unit_preferences.days.$day") }}</option>
                @endforeach
            </select>
        </div>

        @foreach($unitTypes as $unitType => $title)
            @if(!empty($unitPreferences[$unitType]))
                @php
                    $fieldName = "preferred_{$unitType}_unit";
                    $selectedUnit = old($fieldName, $unitService->getPreferredUnit($unitType, $authUser));
                @endphp

                <div class="common-unit-section">
                    <h4>{{ __("units.unit_preferences.types.$unitType") }}</h4>

                    <div class="common-unit-grid">
                        @foreach($unitPreferences[$unitType] as $unit => $label)
                            <label class="common-unit-choice">
                                <input type="radio" name="{{ $fieldName }}" value="{{ $unit }}" {{ $selectedUnit == $unit ? 'checked' : '' }} {!! $isAutoSubmit ? 'data-auto-submit="true"' : '' !!}>
                                <span>{{ config("units.short_labels.{$unit}", $unit) }}</span>
                            </label>
                        @endforeach
                    </div>

                    @error($fieldName)
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            @endif
        @endforeach

        @php
            $formatPreferences  = app(\App\Services\UnitConversion\PreferenceRepository::class)->formatPreferences($authUser);
            $measurementSystems = array_keys(config('unit_formatting.measurement_systems', []));
            $availableLocales   = array_keys(config('unit_formatting.locales', []));
            $autoAttr           = $isAutoSubmit ? 'data-auto-submit="true"' : '';
        @endphp

        <div class="common-unit-section">
            <h4>{{ __('units.unit_preferences.measurement_system') }}</h4>

            <div class="common-unit-grid common-unit-grid--wide">
                <label class="common-unit-choice">
                    <input type="radio" name="measurement_system" value=""
                        {{ old('measurement_system', $formatPreferences['measurement_system']) ? '' : 'checked' }} {!! $autoAttr !!}>
                    <span>{{ __('units.unit_preferences.per_category') }}</span>
                </label>
                @foreach($measurementSystems as $system)
                    <label class="common-unit-choice">
                        <input type="radio" name="measurement_system" value="{{ $system }}"
                            {{ old('measurement_system', $formatPreferences['measurement_system']) === $system ? 'checked' : '' }} {!! $autoAttr !!}>
                        <span>{{ \Illuminate\Support\Facades\Lang::has('units.unit_preferences.systems.'.$system) ? __('units.unit_preferences.systems.'.$system) : ucfirst($system) }}</span>
                    </label>
                @endforeach
            </div>

            @error('measurement_system')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="common-unit-section">
            <h4>{{ __('units.unit_preferences.number_format') }}</h4>

            <div class="common-unit-grid common-unit-grid--wide">
                @foreach(['.' => __('units.unit_preferences.separators.point'), ',' => __('units.unit_preferences.separators.comma')] as $sep => $label)
                    <label class="common-unit-choice">
                        <input type="radio" name="decimal_separator" value="{{ $sep }}"
                            {{ old('decimal_separator', $formatPreferences['decimal_separator'] ?? '.') === $sep ? 'checked' : '' }} {!! $autoAttr !!}>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            @error('decimal_separator')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    @if($isAutoSubmit)
        </form>
    @else
        </div>
    @endif
</div>

@if($isHeaderPicker)
        </div>
    </div>
@endif

@once
    @push('styles_bottom')
        <style>
            .common-units-card {
                border: 1px solid var(--gray-400, #d9d9d9);
                border-radius: 8px;
                padding: 10px 14px 14px;
                color: var(--dark, #1f2933);
            }

            .common-units-menu {
                width: 278px;
                max-height: 70vh;
                overflow-y: auto;
                padding: 0;
            }

            .common-units-title {
                font-size: 20px;
                line-height: 1.05;
                font-weight: 800;
                margin: 0 0 14px;
            }

            .common-unit-section {
                margin-top: 18px;
            }

            .common-unit-section:first-of-type {
                margin-top: 0;
            }

            .common-unit-section h4 {
                font-size: 16px;
                line-height: 1.25;
                font-weight: 800;
                margin: 0 0 10px;
            }

            .common-unit-option,
            .common-unit-choice {
                display: flex;
                align-items: center;
                margin-bottom: 8px;
                cursor: pointer;
                color: var(--dark, #1f2933);
            }

            .common-unit-option {
                gap: 8px;
                font-size: 12px;
            }

            .common-unit-option input[type="radio"],
            .common-unit-choice input[type="radio"] {
                width: 13px;
                height: 13px;
                margin: 0;
            }

            .common-unit-code,
            .common-unit-custom-input {
                margin-inline-start: auto;
                min-width: 46px;
                border: 1px solid var(--gray-400, #cfcfcf);
                border-radius: 2px;
                background: var(--gray-200, #f2f2f2);
                padding: 2px 5px;
                font-size: 11px;
                line-height: 1.2;
                color: var(--dark, #202124);
            }

            .common-unit-custom-input {
                background: var(--white, #fff);
                max-width: 58px;
            }

            .common-unit-preview {
                font-size: 11px;
                color: var(--dark, #4b5563);
                margin: 6px 0 0 22px;
            }

            .common-unit-select {
                display: block;
                width: 80px;
                margin-inline-start: auto;
                border: 1px solid var(--gray-400, #cfcfcf);
                border-radius: 2px;
                padding: 4px 6px;
                font-size: 12px;
                background: var(--white, #fff);
            }

            .common-unit-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                column-gap: 14px;
                row-gap: 6px;
            }

            .common-unit-choice {
                gap: 7px;
                min-height: 24px;
                margin-bottom: 0;
                font-size: 15px;
                font-weight: 700;
                white-space: nowrap;
            }

            @media (max-width: 575px) {
                .common-units-menu {
                    width: min(278px, calc(100vw - 32px));
                }
            }
        
            /* Measurement system and number format use worded labels rather
               than short symbols, so they need wider cells than the three fixed
               columns sized for "kg" and "lb". */
            .common-unit-grid--wide {
                grid-template-columns: repeat(auto-fit, minmax(170px, max-content));
                column-gap: 22px;
                row-gap: 10px;
            }

            .common-unit-grid--wide .common-unit-choice {
                min-width: 0;
                white-space: nowrap;
            }

            .common-unit-grid--wide .common-unit-choice span {
                overflow: hidden;
                text-overflow: ellipsis;
            }
</style>
    @endpush

    @push('scripts_bottom')
        <script>
            (function ($) {
                "use strict";

                $('body').on('change', '.js-common-units-form [data-auto-submit="true"]', function () {
                    $(this).closest('form').trigger('submit');
                });

                $('body').on('change', '.js-common-units-form input[value="custom"]', function () {
                    $(this).closest('.common-unit-option').find('.common-unit-custom-input').trigger('focus');
                });
            })(jQuery);
        </script>
    @endpush
@endonce