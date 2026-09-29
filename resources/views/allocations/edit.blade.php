@extends('layouts.app')
@section('title', 'Edit Allocation')
@section('page-title', 'Asset Allocation')
@section('content')
<nav class="module-breadcrumb"><a href="{{ route('allocations.index') }}">Asset Allocation</a><i class="bi bi-chevron-right"></i><a href="{{ route('allocations.show',$allocation) }}">{{ $allocation }}</a><i class="bi bi-chevron-right"></i><span>Edit</span></nav>
<div class="module-heading"><div><span class="department-label">Assignment Record</span><h2>Edit Allocation</h2><p>Update assignment, location, and expected return information.</p></div></div>
<div class="dashboard-card form-card">@include('allocations.form',['editing'=>true])</div>
@endsection
