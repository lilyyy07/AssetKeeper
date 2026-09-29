@extends('layouts.app')
@section('title', 'Update Maintenance Status')
@section('page-title', 'Maintenance')
@section('content')
<nav class="module-breadcrumb"><a href="{{ route('maintenance.index') }}">Maintenance</a><i class="bi bi-chevron-right"></i><a href="{{ route('maintenance.show',$maintenance) }}">{{ $maintenance }}</a><i class="bi bi-chevron-right"></i><span>Update Status</span></nav>
<div class="module-heading"><div><span class="department-label">Service Progress</span><h2>Update Maintenance Status</h2><p>Record current progress, completion date, and technician remarks.</p></div></div>
<div class="row g-4"><div class="col-xl-8"><div class="dashboard-card form-card"><form class="asset-form" action="#" method="post">@csrf
<section class="form-section"><div class="form-section-heading"><span class="section-number"><i class="bi bi-arrow-repeat"></i></span><div><h3>Status Update</h3><p>Update the workflow stage and service outcome.</p></div></div><div class="row g-3">
<div class="col-md-6"><label class="form-label" for="status">Status <span>*</span></label><select class="form-select" id="status" name="status" required><option>Pending</option><option selected>In Progress</option><option>Completed</option></select><div class="form-text">Choose Completed when all service work and testing are finished.</div></div>
<div class="col-md-6"><label class="form-label" for="completion_date">Completion Date</label><input type="date" class="form-control" id="completion_date" name="completion_date"><div class="form-text">Required when the status is Completed.</div></div>
<div class="col-12"><label class="form-label" for="technician_remarks">Technician Remarks</label><textarea class="form-control" id="technician_remarks" name="technician_remarks" rows="5" placeholder="Work performed, components replaced, test results, and final remarks"></textarea></div>
</div></section><div class="form-actions"><a href="{{ route('maintenance.show',$maintenance) }}" class="btn btn-light-border">Cancel</a><button type="button" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Update Status</button></div></form></div></div>
<div class="col-xl-4"><aside class="dashboard-card return-summary"><span class="detail-icon"><i class="bi bi-router"></i></span><h3>Cisco Catalyst 9200</h3><p>AK-NT-00029</p><dl><div><dt>Maintenance ID</dt><dd>{{ $maintenance }}</dd></div><div><dt>Technician</dt><dd>Mohd Faizal</dd></div><div><dt>Priority</dt><dd><span class="priority-badge priority-high">High</span></dd></div><div><dt>Current Status</dt><dd><span class="status-badge status-blue">In Progress</span></dd></div></dl></aside></div></div>
@endsection
