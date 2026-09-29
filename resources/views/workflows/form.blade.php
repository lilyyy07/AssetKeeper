@php($formId=$kind.'-'.($record?->id ?? 'new'))
<div class="modal fade" id="{{ $formId }}" tabindex="-1" aria-labelledby="{{ $formId }}-title" data-reopen="{{ old('_form')===$formId?'true':'false' }}"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="{{ $formId }}-title">{{ $record?'Edit':'New' }} {{ ucfirst($kind) }}</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><form method="post" action="{{ route(($kind==='allocation'?'allocations':'maintenance').($record?'.update':'.store'),$record?->id) }}" data-confirm="Save this {{ $kind }} record?"><div class="modal-body">@csrf @if($record)@method('PUT')@endif<input type="hidden" name="_form" value="{{ $formId }}">@if(old('_form')===$formId)@include('partials.alerts')@endif<div class="row g-3">
@if(!$record)@include('components.field',['name'=>'asset_id','label'=>'Asset','options'=>$available->mapWithKeys(fn($a)=>[$a->id=>$a->asset_tag.' · '.$a->name])->all(),'required'=>true])@else<div class="col-12"><strong>{{ $record->asset->asset_tag }} · {{ $record->asset->name }}</strong></div>@endif
@if($kind==='allocation')
@include('components.field',['name'=>'borrower_type','label'=>'Borrower Type','options'=>array_combine(\App\Models\Allocation::BORROWER_TYPES,\App\Models\Allocation::BORROWER_TYPES),'value'=>$record?->borrower_type,'required'=>true])
@include('components.field',['name'=>'assigned_to','label'=>'Borrower Name','value'=>$record?->assigned_to,'required'=>true])
@include('components.field',['name'=>'borrower_identifier','label'=>'Student / Staff ID','value'=>$record?->borrower_identifier,'maxlength'=>64])
@include('components.field',['name'=>'department_id','label'=>'Department / Faculty','options'=>\App\Models\Department::options($departments, $record?->department_id),'value'=>$record?->department_id,'required'=>true])
@include('components.field',['name'=>'location','label'=>'Location','value'=>$record?->location,'required'=>true])
@include('components.field',['name'=>'borrower_email','label'=>'Contact Email (optional)','type'=>'email','value'=>$record?->borrower_email])
@include('components.field',['name'=>'allocation_date','label'=>'Allocation Date','type'=>'date','value'=>$record?->allocation_date ?? today()->toDateString(),'required'=>true])
@include('components.field',['name'=>'expected_return_date','label'=>'Expected Return Date','type'=>'date','value'=>$record?->expected_return_date])
<div class="col-12"><p class="form-text mb-0">Borrowers do not need an AssetKeeper account. Student and staff IDs are required for those borrower types; other reference IDs are optional.</p>@if($record && !$record->borrower_type)<p class="form-text mb-0">Borrower type was not recorded for this allocation. Select the appropriate type before saving.</p>@endif</div>
@else
@include('components.field',['name'=>'issue','label'=>'Issue / Problem','type'=>'textarea','value'=>$record?->issue,'required'=>true,'column'=>'col-12'])
@foreach(['type'=>['Preventive','Corrective','Hardware','Software'],'priority'=>['Low','Medium','High','Critical'],'status'=>['Pending','In Progress','Completed']] as $name=>$choices)@include('components.field',['name'=>$name,'label'=>ucfirst($name),'options'=>array_combine($choices,$choices),'value'=>$record?->$name ?? $choices[0],'required'=>true])@endforeach
@include('components.field',['name'=>'technician_id','label'=>'Assigned Technician','options'=>$technicians->pluck('name','id')->all(),'value'=>$record?->technician_id])
@foreach(['date_reported'=>'Problem Reported','problem_attended_at'=>'Problem Attended','repair_started_at'=>'Repair Started','actual_completion_date'=>'Completed (required when completed)'] as $name=>$label)
@php($legacy = $record?->$name && in_array($name,['date_reported','actual_completion_date']) && !$record->{ $name==='date_reported'?'reported_time_recorded':'completion_time_recorded' })
@include('components.field',['name'=>$name,'label'=>$label.($legacy?' (historical date; time unknown)':''),'type'=>$legacy?'date':'datetime-local','value'=>$record?->timelineInput($name) ?? ($name==='date_reported'?now()->format('Y-m-d\TH:i'):null),'required'=>$name==='date_reported'])
@endforeach
@include('components.field',['name'=>'estimated_completion_date','label'=>'Estimated Completion Date','type'=>'date','value'=>$record?->estimated_completion_date])
@endif
@include('components.field',['name'=>'remarks','label'=>'Remarks','type'=>'textarea','value'=>$record?->remarks,'column'=>'col-12'])
</div></div><div class="modal-footer"><button type="button" class="btn btn-light-border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Record</button></div></form></div></div></div>
