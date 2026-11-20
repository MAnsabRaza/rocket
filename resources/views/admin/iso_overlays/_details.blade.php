{{-- Overlay details as recorded: name, description, framework label, FlinkISO standard. --}}
<table class="table custom-table font-14 mb-0">
    <tr>
        <th>{{ trans('iso_overlay.name') }}</th>
        <td>{{ \App\Models\IsoOverlay::pickLocale($details['name'] ?? null) ?: '-' }}</td>
    </tr>
    <tr>
        <th>{{ trans('iso_overlay.description') }}</th>
        <td>{{ \App\Models\IsoOverlay::pickLocale($details['description'] ?? null) ?: '-' }}</td>
    </tr>
    <tr>
        <th>{{ trans('iso_overlay.framework_label') }}</th>
        <td><bdi>{{ ($details['framework_label'] ?? null) ?: '-' }}</bdi></td>
    </tr>
    <tr>
        <th>{{ trans('iso_overlay.standard') }}</th>
        <td><bdi class="font-12">{{ ($details['flinkiso_standard_id'] ?? null) ?: '-' }}</bdi></td>
    </tr>
</table>
