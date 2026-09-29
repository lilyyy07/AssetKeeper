@extends('layouts.app')
@section('title', 'Asset Allocation')
@section('page-title', 'Asset Allocation')
@section('content')
@php
$allocations = [
 ['ALC-2026-0148','AK-LP-00148','Dell Latitude 5440','Dr. Amir Hakim','Faculty of Computing','Block A - Level 3','20 Jan 2026','20 Jan 2027','Active'],
 ['ALC-2026-0142','AK-DT-00095','Lenovo ThinkCentre M90q','Nur Aisyah Rahman','Finance Department','Admin Building','14 Jan 2026','14 Jan 2027','Active'],
 ['ALC-2026-0136','AK-MN-00176','Dell UltraSharp U2422H','Siti Hajar','Faculty of Computing','Block B - Lab 5','08 Jan 2026','08 Jan 2027','Active'],
 ['ALC-2025-0119','AK-PJ-00041','Epson EB-2250U Projector','Ahmad Firdaus','Academic Affairs','Lecture Hall 2','10 Dec 2025','10 Jun 2026','Overdue'],
 ['ALC-2025-0108','AK-LP-00121','HP EliteBook 840 G8','Farah Nadia','Registry Department','Registry Office','22 Nov 2025','22 May 2026','Returned'],
 ['ALC-2025-0097','AK-KB-00234','Logitech MK540 Combo','Mohd Faizal','ICT Department','ICT Office','02 Nov 2025','02 Nov 2026','Active'],
 ['ALC-2025-0084','AK-DT-00082','Dell OptiPlex 7090','Prof. Dr. Azman','Management','Chancellery','18 Oct 2025','18 Oct 2026','Active'],
 ['ALC-2025-0072','AK-MN-00155','HP E24 G4 Monitor','Aina Syuhada','Library','Resource Centre','06 Sep 2025','06 Mar 2026','Overdue'],
];
$statusClass = fn($status) => match($status) {'Active'=>'status-blue','Returned'=>'status-green',default=>'status-red'};
@endphp
<div class="module-heading"><div><span class="department-label">Assignment Register</span><h2>Asset Allocation</h2><p>Manage and monitor ICT asset assignments.</p></div><a href="{{ route('allocations.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> Allocate Asset</a></div>
<section class="dashboard-card filter-card mb-4"><form class="allocation-filters" action="{{ route('allocations.index') }}" method="get">
<div class="search-control"><i class="bi bi-search"></i><input type="search" class="form-control" name="search" placeholder="Search asset tag, asset name or assigned user" aria-label="Search allocation"></div>
<select class="form-select" name="department" aria-label="Filter by department"><option value="">All Departments</option><option>ICT Department</option><option>Faculty of Computing</option><option>Finance Department</option><option>Registry Department</option><option>Academic Affairs</option><option>Library</option></select>
<select class="form-select" name="status" aria-label="Filter by allocation status"><option value="">All Allocation Statuses</option><option>Active</option><option>Returned</option><option>Overdue</option></select>
<button class="btn btn-filter" type="submit"><i class="bi bi-funnel"></i><span>Filter</span></button></form></section>
<section class="dashboard-card asset-table-card"><div class="table-card-header"><div><h3>Allocation Records</h3><p>Showing 8 of 436 allocation records</p></div><button class="btn btn-light-border"><i class="bi bi-download"></i> Export</button></div>
<div class="table-responsive"><table class="table allocation-table align-middle mb-0"><thead><tr><th>Allocation ID</th><th>Asset Tag</th><th>Asset Name</th><th>Assigned To</th><th>Department</th><th>Location</th><th>Allocation Date</th><th>Expected Return</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
@foreach($allocations as [$id,$tag,$name,$assigned,$department,$location,$date,$expected,$status])
<tr><td><a href="{{ route('allocations.show', $id) }}" class="asset-tag">{{ $id }}</a></td><td><span class="serial-number">{{ $tag }}</span></td><td><strong>{{ $name }}</strong></td><td>{{ $assigned }}</td><td>{{ $department }}</td><td>{{ $location }}</td><td>{{ $date }}</td><td class="{{ $status === 'Overdue' ? 'text-danger fw-semibold' : '' }}">{{ $expected }}</td><td><span class="status-badge {{ $statusClass($status) }}">{{ $status }}</span></td><td><div class="table-actions"><a href="{{ route('allocations.show', $id) }}" class="btn action-button" title="View allocation"><i class="bi bi-eye"></i></a><a href="{{ route('allocations.edit', $id) }}" class="btn action-button" title="Edit allocation"><i class="bi bi-pencil"></i></a>@if($status !== 'Returned')<a href="{{ route('allocations.return', $id) }}" class="btn action-button action-return" title="Return asset"><i class="bi bi-arrow-return-left"></i></a>@endif</div></td></tr>
@endforeach
</tbody></table></div>
<div class="table-pagination"><p>Showing <strong>1–8</strong> of <strong>436</strong></p><nav aria-label="Allocation pagination"><ul class="pagination mb-0"><li class="page-item disabled"><a class="page-link" href="#"><i class="bi bi-chevron-left"></i></a></li><li class="page-item active"><a class="page-link" href="#">1</a></li><li class="page-item"><a class="page-link" href="#">2</a></li><li class="page-item"><a class="page-link" href="#">3</a></li><li class="page-item"><a class="page-link" href="#"><i class="bi bi-chevron-right"></i></a></li></ul></nav></div></section>
@endsection
