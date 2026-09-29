@extends('layouts.app')
@section('title','Import Assets')
@section('page-title','Import Assets')
@section('content')
<div class="module-heading"><div><h2>Import Assets: Validation Preview</h2><p>{{ count($rows) }} rows checked. Valid: {{ count($result['valid']) }}. Failed: {{ count($result['errors']) }}. Imported: 0.</p></div><a href="{{ route('assets.index') }}" class="btn btn-light-border">Back to Assets</a></div>
<section class="dashboard-card">
@if($result['errors'])<div class="alert alert-danger">Nothing has been imported. Correct every invalid row and upload the file again.</div>
@foreach($result['errors'] as $line=>$messages)<p><strong>Row {{ $line }}:</strong> {{ implode(' ', $messages) }}</p>@endforeach
@else<p>All rows passed validation. Confirm within 30 minutes. Records will be checked again before import. Existing assets will never be overwritten.</p>@endif
<div class="table-responsive"><table class="table"><thead><tr><th>Row</th><th>Asset Tag</th><th>Name</th><th>Category</th><th>Department</th><th>Status</th></tr></thead><tbody>@foreach($rows as $row)<tr><td>{{ $row['line'] }}</td>@foreach(['asset_tag','name','category','department','status'] as $field)<td>{{ $row['data'][$field] ?? '—' }}</td>@endforeach</tr>@endforeach</tbody></table></div>
@if(!$result['errors'])<form method="post" action="{{ route('assets.import.commit') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><button class="btn btn-primary">Import {{ count($rows) }} Assets</button></form>@endif
</section>
@endsection