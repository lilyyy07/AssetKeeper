@php
$value = old('_form') === ($formId ?? '') ? old($name, $value ?? '') : ($value ?? '');
$id = ($formId ?? 'filter').'-'.$name;
$invalid = old('_form') === ($formId ?? '') && $errors->has($name);
@endphp
<div class="{{ $column ?? 'col-md-6' }}">
<label class="form-label" for="{{ $id }}">{{ $label ?? ucfirst(str_replace('_',' ',$name)) }} @if($required ?? false)<span aria-hidden="true">*</span>@endif</label>
@if(isset($options))
<select class="form-select {{ $invalid?'is-invalid':'' }}" name="{{ $name }}" id="{{ $id }}" @required($required ?? false) @if($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif><option value="">Select {{ strtolower($label ?? str_replace('_',' ',$name)) }}</option>@foreach($options as $key=>$text)<option value="{{ $key }}" @selected((string)$value===(string)$key)>{{ $text }}</option>@endforeach</select>
@elseif(($type ?? '')==='textarea')
<textarea class="form-control {{ $invalid?'is-invalid':'' }}" name="{{ $name }}" id="{{ $id }}" rows="3" maxlength="{{ $maxlength ?? 5000 }}" @required($required ?? false) @if($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>{{ $value }}</textarea>
@else
<input class="form-control {{ $invalid?'is-invalid':'' }}" type="{{ $type ?? 'text' }}" name="{{ $name }}" id="{{ $id }}" value="{{ ($type ?? '')==='password' ? '' : $value }}" maxlength="{{ $maxlength ?? 255 }}" @if(($type ?? '')==='number') step="0.01" min="0" @endif @required($required ?? false) @if(($type ?? '')==='password') autocomplete="new-password" @endif @if($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
@endif
@if($invalid)<div class="invalid-feedback" id="{{ $id }}-error">{{ $errors->first($name) }}</div>@endif
</div>
