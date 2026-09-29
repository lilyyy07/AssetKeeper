@extends('layouts.app')
@section('title', 'Inventory Tracking')
@section('page-title', 'Inventory Tracking')
@section('content')
@php
$summary = [
 ['Total Assets','1,248','bi-boxes','purple','All registered ICT assets'],
 ['Available','326','bi-check-circle','green','Ready for allocation'],
 ['Allocated','874','bi-person-check','blue','Currently assigned'],
 ['Under Maintenance','48','bi-tools','orange','In service or repair'],
 ['Damaged / Disposed','22','bi-exclamation-triangle','red','Unavailable assets'],
];
$assets = [
 ['AK-LP-00148','Dell Latitude 5440','Laptop','Block A - Level 3','Faculty of Computing','Dr. Amir Hakim','Allocated','Good'],
 ['AK-DT-00095','Lenovo ThinkCentre M90q','Desktop','Admin Building','Finance Department','Nur Aisyah Rahman','Allocated','Good'],
 ['AK-PR-00062','HP LaserJet Pro 4003dn','Printer','Registry Office','Registry Department','—','Available','Good'],
 ['AK-PJ-00041','Epson EB-2250U Projector','Projector','Lecture Hall 2','Academic Affairs','—','Available','Good'],
 ['AK-NT-00029','Cisco Catalyst 9200','Network Device','Data Centre','ICT Department','Mohd Faizal','Under Maintenance','Fair'],
 ['AK-MN-00176','Dell UltraSharp U2422H','Monitor','Block B - Lab 5','Faculty of Computing','Siti Hajar','Allocated','Good'],
 ['AK-SV-00011','Dell PowerEdge R740','Server','Data Centre','ICT Department','Infrastructure Team','Under Maintenance','Poor'],
 ['AK-KB-00234','Logitech MK540 Combo','Peripheral','ICT Store','ICT Department','—','Available','Good'],
 ['AK-NT-00018','Cisco Aironet 2802i','Network Device','Block C - Level 2','ICT Department','—','Damaged','Poor'],
 ['AK-DT-00037','HP ProDesk 600 G6','Desktop','Resource Centre','Library','—','Disposed','Fair'],
];
$statusClass = fn($status) => match($status) {'Available'=>'status-green','Allocated'=>'status-blue','Under Maintenance'=>'status-orange',default=>'status-red'};
$conditionClass = fn($condition) => match($condition) {'Good'=>'condition-good','Fair'=>'condition-fair',default=>'condition-poor'};
@endphp
<div class="module-heading"><div><span class="department-label">Inventory Overview</span><h2>Inventory Tracking</h2><p>Monitor asset availability, location, department, status, and condition.</p></div></div>
<section class="row row-cols-1 row-cols-sm-2 row-cols-xl-5 g-3 mb-4" aria-label="Inventory summary">
@foreach($summary as [$label,$value,$icon,$tone,$detail])
<div class="col"><article class="dashboard-card inventory-stat h-100"><div class="stat-icon stat-icon-{{ $tone }}"><i class="bi {{ $icon }}"></i></div><div><p>{{ $label }}</p><strong>{{ $value }}</strong><small>{{ $detail }}</small></div></article></div>
@endforeach
</section>
<section class="dashboard-card filter-card inventory-filter-card mb-4"><div class="filter-heading"><div><h3>Search &amp; Filter Inventory</h3><p>Narrow the inventory by identification, ownership, or asset state.</p></div><button type="reset" form="inventoryFilters" class="btn btn-reset"><i class="bi bi-arrow-counterclockwise"></i> Reset</button></div>
<form id="inventoryFilters" class="inventory-filters" action="{{ route('inventory.index') }}" method="get">
<div class="search-control"><i class="bi bi-upc-scan"></i><input class="form-control" name="asset_tag" placeholder="Search by Asset Tag" aria-label="Search by Asset Tag"></div>
<div class="search-control"><i class="bi bi-search"></i><input class="form-control" name="asset_name" placeholder="Search by Asset Name" aria-label="Search by Asset Name"></div>
<div class="search-control"><i class="bi bi-hash"></i><input class="form-control" name="serial_number" placeholder="Search by Serial Number" aria-label="Search by Serial Number"></div>
<select class="form-select" name="category" aria-label="Category filter"><option value="">All Categories</option>@foreach(['Laptop','Desktop','Printer','Projector','Network Device','Monitor','Server','Peripheral'] as $option)<option>{{ $option }}</option>@endforeach</select>
<select class="form-select" name="status" aria-label="Status filter"><option value="">All Statuses</option>@foreach(['Available','Allocated','Under Maintenance','Damaged','Disposed'] as $option)<option>{{ $option }}</option>@endforeach</select>
<select class="form-select" name="location" aria-label="Location filter"><option value="">All Locations</option>@foreach(['Block A','Block B','Block C','Admin Building','Data Centre','ICT Store','Registry Office'] as $option)<option>{{ $option }}</option>@endforeach</select>
<select class="form-select" name="department" aria-label="Department filter"><option value="">All Departments</option>@foreach(['ICT Department','Faculty of Computing','Finance Department','Registry Department','Academic Affairs','Library'] as $option)<option>{{ $option }}</option>@endforeach</select>
<select class="form-select" name="condition" aria-label="Condition filter"><option value="">All Conditions</option><option>Good</option><option>Fair</option><option>Poor</option></select>
<button class="btn btn-filter inventory-filter-button" type="submit"><i class="bi bi-funnel"></i><span>Apply Filters</span></button>
</form></section>
<section class="dashboard-card asset-table-card"><div class="table-card-header"><div><h3>ICT Asset Inventory</h3><p>Showing 10 of 1,248 assets</p></div><div class="inventory-table-meta"><span><i class="bi bi-clock-history"></i> Last updated: 03 Sep 2026, 11:45 AM</span><button class="btn btn-light-border"><i class="bi bi-download"></i> Export</button></div></div>
<div class="table-responsive"><table class="table inventory-table align-middle mb-0"><thead><tr><th>Asset Tag</th><th>Asset Name</th><th>Category</th><th>Location</th><th>Department</th><th>Assigned To</th><th>Status</th><th>Condition</th><th class="text-end">Actions</th></tr></thead><tbody>
@foreach($assets as [$tag,$name,$category,$location,$department,$assigned,$status,$condition])
<tr><td><a href="{{ route('assets.show',$tag) }}" class="asset-tag">{{ $tag }}</a></td><td><strong>{{ $name }}</strong></td><td>{{ $category }}</td><td><span class="location-cell"><i class="bi bi-geo-alt"></i>{{ $location }}</span></td><td>{{ $department }}</td><td>{{ $assigned }}</td><td><span class="status-badge {{ $statusClass($status) }}">{{ $status }}</span></td><td><span class="condition-badge {{ $conditionClass($condition) }}"><i></i>{{ $condition }}</span></td><td><div class="table-actions"><a href="{{ route('assets.show',$tag) }}" class="btn action-button view-detail-action" title="View Asset Details"><i class="bi bi-eye"></i><span>View</span></a></div></td></tr>
@endforeach
</tbody></table></div>
<div class="table-pagination"><p>Showing <strong>1–10</strong> of <strong>1,248</strong></p><nav aria-label="Inventory pagination"><ul class="pagination mb-0"><li class="page-item disabled"><a class="page-link" href="#"><i class="bi bi-chevron-left"></i></a></li><li class="page-item active"><a class="page-link" href="#">1</a></li><li class="page-item"><a class="page-link" href="#">2</a></li><li class="page-item"><a class="page-link" href="#">3</a></li><li class="page-item"><span class="page-link">…</span></li><li class="page-item"><a class="page-link" href="#">125</a></li><li class="page-item"><a class="page-link" href="#"><i class="bi bi-chevron-right"></i></a></li></ul></nav></div></section>
@endsection
