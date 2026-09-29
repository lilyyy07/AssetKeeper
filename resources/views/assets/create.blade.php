@extends('layouts.app')
@section('title', 'Add New Asset')
@section('page-title', 'Asset Management')
@section('content')
<nav class="module-breadcrumb" aria-label="breadcrumb"><a href="{{ route('assets.index') }}">Asset Management</a><i class="bi bi-chevron-right"></i><span>Add New Asset</span></nav>
<div class="module-heading"><div><span class="department-label">Asset Registry</span><h2>Add New Asset</h2><p>Register a new ICT asset in the AssetKeeper inventory.</p></div></div>
<div class="dashboard-card form-card">@include('assets.form')</div>
@endsection
