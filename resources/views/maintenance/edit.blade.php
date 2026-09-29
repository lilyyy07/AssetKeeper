@extends('layouts.app')
@section('title', 'Edit Maintenance Record')
@section('page-title', 'Maintenance')
@section('content')
<nav class="module-breadcrumb"><a href="{{ route('maintenance.index') }}">Maintenance</a><i class="bi bi-chevron-right"></i><a href="{{ route('maintenance.show',$maintenance) }}">{{ $maintenance }}</a><i class="bi bi-chevron-right"></i><span>Edit</span></nav>
<div class="module-heading"><div><span class="department-label">Service Record</span><h2>Edit Maintenance Record</h2><p>Update issue, priority, technician, and planned completion information.</p></div></div>
<div class="dashboard-card form-card">@include('maintenance.form',['editing'=>true])</div>
@endsection
