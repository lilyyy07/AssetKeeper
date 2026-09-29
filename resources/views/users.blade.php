@extends('layouts.app')
@section('title','User Management')
@section('page-title','User Management')
@section('content')
<div class="module-heading"><div><h2>User Management</h2><p>Manage authorized AssetKeeper users and departments.</p></div><div class="page-actions"><button class="btn btn-light-border" data-bs-toggle="modal" data-bs-target="#manage-departments">Manage Departments</button><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#user-new">Add User</button></div></div>
<form class="d-flex gap-2 mb-4"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search name or email" aria-label="Search users"><button class="btn btn-primary">Search</button></form>
<section class="dashboard-card"><div class="table-responsive"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Account Status</th><th>Actions</th></tr></thead><tbody>@forelse($users as $user)<tr><td>{{ $user->name }}@if($user->is_uat_account)<div class="borrower-meta">UAT account</div>@endif</td><td>{{ $user->email }}</td><td>{{ $user->role }}</td><td>{{ $user->department?->name ?? '—' }}</td><td>@include('components.status',['status'=>$user->is_active?'Active':'Inactive'])</td><td><button class="btn btn-sm btn-light-border" data-bs-toggle="modal" data-bs-target="#user-{{ $user->id }}">Edit User</button></td></tr>@empty<tr><td colspan="6">No users found.</td></tr>@endforelse</tbody></table></div>{{ $users->links() }}</section>
@include('partials.user-form',['user'=>null])@foreach($users as $user)@include('partials.user-form')@endforeach
@include('partials.departments-modal')
@endsection
