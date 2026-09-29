<div class="maintenance-dates">
@foreach(['date_reported'=>'Reported','problem_attended_at'=>'Attended','repair_started_at'=>'Repair Started','actual_completion_date'=>'Completed'] as $field=>$label)
<div><span>{{ $label }}:</span> {{ $record->timelineDisplay($field) }}</div>
@endforeach
</div>