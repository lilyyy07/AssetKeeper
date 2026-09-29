@extends('layouts.app')
@section('title', 'Add Maintenance Record')
@section('page-title', 'Maintenance')
@section('content')
<nav class="module-breadcrumb"><a href="{{ route('maintenance.index') }}">Maintenance</a><i class="bi bi-chevron-right"></i><span>Add Record</span></nav>
<div class="module-heading"><div><span class="department-label">New Service Record</span><h2>Add Maintenance Record</h2><p>Log an ICT asset issue and assign the required maintenance work.</p></div></div>
<div class="dashboard-card form-card">@include('maintenance.form')</div>
@endsection
