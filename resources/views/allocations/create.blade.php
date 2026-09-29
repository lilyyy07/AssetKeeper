@extends('layouts.app')
@section('title', 'Allocate Asset')
@section('page-title', 'Asset Allocation')
@section('content')
<nav class="module-breadcrumb"><a href="{{ route('allocations.index') }}">Asset Allocation</a><i class="bi bi-chevron-right"></i><span>Allocate Asset</span></nav>
<div class="module-heading"><div><span class="department-label">New Assignment</span><h2>Allocate Asset</h2><p>Assign an available ICT asset to a UPTM staff member or department.</p></div></div>
<div class="dashboard-card form-card">@include('allocations.form')</div>
@endsection
