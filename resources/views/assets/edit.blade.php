@extends('layouts.app')
@section('title', 'Edit Asset')
@section('page-title', 'Asset Management')
@section('content')
<nav class="module-breadcrumb" aria-label="breadcrumb"><a href="{{ route('assets.index') }}">Asset Management</a><i class="bi bi-chevron-right"></i><a href="{{ route('assets.show', $asset) }}">{{ $asset }}</a><i class="bi bi-chevron-right"></i><span>Edit</span></nav>
<div class="module-heading"><div><span class="department-label">Asset Registry</span><h2>Edit Asset</h2><p>Update identification, assignment, and condition information.</p></div></div>
<div class="dashboard-card form-card">@include('assets.form', ['editing' => true])</div>
@endsection
