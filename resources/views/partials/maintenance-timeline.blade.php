<div class="small">
@foreach(['date_reported'=>'Problem Reported','problem_attended_at'=>'Problem Attended','repair_started_at'=>'Repair Started','actual_completion_date'=>'Completed'] as $field=>$label)
<div><strong>{{ $label }}:</strong> {{ $record->timelineDisplay($field) }}</div>
@endforeach
<div><strong>Response Time:</strong> {{ $record->response_time ?? '—' }}</div>
<div><strong>Repair Duration:</strong> {{ $record->repair_duration ?? '—' }}</div>
</div>