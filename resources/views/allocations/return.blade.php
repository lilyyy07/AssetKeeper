@extends('layouts.app')
@section('title', 'Return Asset')
@section('page-title', 'Asset Allocation')
@section('content')
<nav class="module-breadcrumb"><a href="{{ route('allocations.index') }}">Asset Allocation</a><i class="bi bi-chevron-right"></i><a href="{{ route('allocations.show',$allocation) }}">{{ $allocation }}</a><i class="bi bi-chevron-right"></i><span>Return Asset</span></nav>
<div class="module-heading"><div><span class="department-label">Complete Assignment</span><h2>Return Asset</h2><p>Record the return and condition of the allocated ICT asset.</p></div></div>
<div class="row g-4"><div class="col-xl-8"><div class="dashboard-card form-card"><form class="asset-form" action="#" method="post">@csrf
<section class="form-section"><div class="form-section-heading"><span class="section-number"><i class="bi bi-arrow-return-left"></i></span><div><h3>Return Information</h3><p>Confirm the return date and inspected asset condition.</p></div></div><div class="row g-3">
<div class="col-md-6"><label class="form-label" for="return_date">Return Date <span>*</span></label><input type="date" class="form-control" id="return_date" name="return_date" value="2026-09-03" required></div>
<div class="col-md-6"><label class="form-label" for="return_condition">Asset Condition on Return <span>*</span></label><select class="form-select" id="return_condition" name="return_condition" required><option>Good</option><option>Fair</option><option>Poor</option></select></div>
<div class="col-12"><label class="form-label" for="return_remarks">Return Remarks</label><textarea class="form-control" id="return_remarks" name="return_remarks" rows="5" placeholder="Inspection notes, missing accessories, damage, or follow-up action"></textarea></div>
</div></section><div class="form-actions"><a href="{{ route('allocations.show',$allocation) }}" class="btn btn-light-border">Cancel</a><button type="button" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Confirm Return</button></div></form></div></div>
<div class="col-xl-4"><aside class="dashboard-card return-summary"><span class="detail-icon"><i class="bi bi-laptop"></i></span><h3>Dell Latitude 5440</h3><p>AK-LP-00148</p><dl><div><dt>Assigned To</dt><dd>Dr. Amir Hakim</dd></div><div><dt>Department</dt><dd>Faculty of Computing</dd></div><div><dt>Allocation ID</dt><dd>{{ $allocation }}</dd></div><div><dt>Current Status</dt><dd><span class="status-badge status-blue">Active</span></dd></div></dl></aside></div></div>
@endsection
