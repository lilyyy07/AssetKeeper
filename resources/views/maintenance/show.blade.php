@extends('layouts.app')
@section('title','Maintenance Details')
@section('page-title','Maintenance Details')
@section('content')
<div class="module-heading"><div><span class="department-label">Maintenance #{{ $record->id }}</span><h2>Maintenance Details</h2><p>{{ $record->asset->asset_tag }} · {{ $record->asset->name }}</p></div><div class="page-actions"><a href="{{ route('maintenance.index') }}" class="btn btn-light-border">Back to Maintenance</a>@can('operate')@if($record->status!=='Completed')<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#maintenance-{{ $record->id }}">Edit / Status</button>@endif @endcan</div></div>
<section class="dashboard-card mb-4"><div class="detail-grid maintenance-detail-grid">
@foreach(['Maintenance ID'=>$record->id,'Asset'=>$record->asset->asset_tag.' · '.$record->asset->name,'Issue / Problem'=>$record->issue,'Maintenance Type'=>$record->type,'Priority'=>$record->priority,'Assigned Technician'=>$record->technician?->name,'Status'=>$record->status] as $label=>$value)<div class="detail-field"><span>{{ $label }}</span><strong>{{ $value ?? '—' }}</strong></div>@endforeach
@foreach(['date_reported'=>'Problem Reported','problem_attended_at'=>'Problem Attended','repair_started_at'=>'Repair Started','actual_completion_date'=>'Completed'] as $field=>$label)<div class="detail-field"><span>{{ $label }}</span><strong>{{ $record->timelineDisplay($field) }}</strong></div>@endforeach
@foreach(['Response Time'=>$record->response_time,'Repair Duration'=>$record->repair_duration,'Estimated Completion'=>$record->estimated_completion_date,'Remarks'=>$record->remarks] as $label=>$value)<div class="detail-field"><span>{{ $label }}</span><strong>{{ $value ?: '—' }}</strong></div>@endforeach
</div><a class="btn btn-light-border mt-3" href="{{ route('assets.show',$record->asset) }}">View Asset</a></section>
@can('operate')@if($record->status!=='Completed')
@include('workflows.form',['kind'=>'maintenance','departments'=>collect(),'available'=>collect(),'technicians'=>\App\Models\User::where('is_active',true)->whereIn('role',array_slice(\App\Models\User::ROLES,0,2))->get()])
@endif @endcan
@endsection