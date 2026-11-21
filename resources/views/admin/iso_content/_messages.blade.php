@if(session('iso_status'))
<div class="alert alert-success alert-has-icon">
    <div class="alert-icon"><i class="fas fa-check"></i></div>
    <div class="alert-body">{{ session('iso_status') }}</div>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger alert-has-icon">
    <div class="alert-icon"><i class="fas fa-exclamation"></i></div>
    <div class="alert-body">
        <strong>{{ trans('iso_content.errors') }}</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $message)
            <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif