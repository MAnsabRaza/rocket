@php $badge = ['draft' => 'warning', 'published' => 'success', 'archived' => 'secondary'][$status] ?? 'light'; @endphp
<span class="badge badge-{{ $badge }}">{{ trans('iso_overlay.status_' . $status) }}</span>
