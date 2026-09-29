@extends('layouts.app')
@section('title', 'Maintenance Management')
@section('page-title', 'Maintenance')
@section('content')
@php
$summary = [
 ['Pending','12','Awaiting technician review','bi-hourglass-split','orange'],
 ['In Progress','18','Currently being serviced','bi-gear','blue'],
 ['Completed','286','All completed records','bi-check2-circle','green'],
 ['Completed This Month','24','September 2026','bi-calendar-check','purple'],
];
$records = [
 ['MNT-2026-0058','AK-NT-00029','Cisco Catalyst 9200','Intermittent network connectivity on ports 13–18','Hardware','01 Sep 2026','Mohd Faizal','In Progress','—','High'],
 ['MNT-2026-0057','AK-SV-00011','Dell PowerEdge R740','RAID controller reporting predictive disk failure','Corrective','31 Aug 2026','Infrastructure Team','Pending','—','Critical'],
 ['MNT-2026-0056','AK-LP-00148','Dell Latitude 5440','Quarterly device inspection and internal cleaning','Preventive','28 Aug 2026','Nur Izzati','Completed','02 Sep 2026','Low'],
 ['MNT-2026-0055','AK-PR-00062','HP LaserJet Pro 4003dn','Printer driver deployment and queue configuration','Software','27 Aug 2026','Daniel Lee','Completed','29 Aug 2026','Medium'],
 ['MNT-2026-0054','AK-PJ-00041','Epson EB-2250U Projector','Image flickering after extended operation','Hardware','25 Aug 2026','Mohd Faizal','In Progress','—','Medium'],
 ['MNT-2026-0053','AK-DT-00095','Lenovo ThinkCentre M90q','Windows security updates and firmware upgrade','Software','22 Aug 2026','Nur Izzati','Completed','23 Aug 2026','Medium'],
 ['MNT-2026-0052','AK-MN-00176','Dell UltraSharp U2422H','DisplayPort input not consistently detected','Corrective','20 Aug 2026','Daniel Lee','Pending','—','Low'],
 ['MNT-2026-0051','AK-NT-00018','Cisco Aironet 2802i','Unit fails power-on self-test','Hardware','18 Aug 2026','Infrastructure Team','In Progress','—','Critical'],
];
$statusClass = fn($status) => match($status) {'Pending'=>'status-orange','In Progress'=>'status-blue',default=>'status-green'};
$priorityClass = fn($priority) => 'priority-'.strtolower($priority);
@endphp
<div class="module-heading"><div><span class="department-label">Service Register</span><h2>Maintenance Management</h2><p>Monitor and manage ICT asset maintenance records.</p></div><a href="{{ route('maintenance.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Maintenance Record</a></div>
<section class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-4" aria-label="Maintenance summary">@foreach($summary as [$label,$value,$detail,$icon,$tone])<div class="col"><article class="dashboard-card maintenance-stat h-100"><div class="stat-icon stat-icon-{{ $tone }}"><i class="bi {{ $icon }}"></i></div><div><p>{{ $label }}</p><strong>{{ $value }}</strong><small>{{ $detail }}</small></div></article></div>@endforeach</section>
<section class="dashboard-card filter-card maintenance-filter-card mb-4"><div class="filter-heading"><div><h3>Search &amp; Filter Maintenance</h3><p>Find records by reference, asset, service type, technician, or date.</p></div><button type="reset" form="maintenanceFilters" class="btn btn-reset"><i class="bi bi-arrow-counterclockwise"></i> Reset</button></div>
<form id="maintenanceFilters" class="maintenance-filters" action="{{ route('maintenance.index') }}" method="get">
<div class="search-control"><i class="bi bi-search"></i><input class="form-control" name="maintenance_id" placeholder="Search Maintenance ID" aria-label="Search by Maintenance ID"></div>
<div class="search-control"><i class="bi bi-pc-display"></i><input class="form-control" name="asset" placeholder="Asset Tag / Asset Name" aria-label="Search by Asset Tag or Name"></div>
<select class="form-select" name="type" aria-label="Maintenance type"><option value="">All Types</option><option>Preventive</option><option>Corrective</option><option>Hardware</option><option>Software</option></select>
<select class="form-select" name="status" aria-label="Maintenance status"><option value="">All Statuses</option><option>Pending</option><option>In Progress</option><option>Completed</option></select>
<select class="form-select" name="technician" aria-label="Technician"><option value="">All Technicians</option><option>Mohd Faizal</option><option>Nur Izzati</option><option>Daniel Lee</option><option>Infrastructure Team</option></select>
<div class="date-range-control"><i class="bi bi-calendar3"></i><input type="date" class="form-control" name="date_from" aria-label="Date from"><span>to</span><input type="date" class="form-control" name="date_to" aria-label="Date to"></div>
<button class="btn btn-filter" type="submit"><i class="bi bi-funnel"></i><span>Apply Filters</span></button></form></section>
<section class="dashboard-card asset-table-card"><div class="table-card-header"><div><h3>Maintenance Records</h3><p>Showing 8 of 316 service records</p></div><button class="btn btn-light-border"><i class="bi bi-download"></i> Export</button></div>
<div class="table-responsive"><table class="table maintenance-table align-middle mb-0"><thead><tr><th>Maintenance ID</th><th>Asset Tag</th><th>Asset Name</th><th>Issue / Problem</th><th>Type</th><th>Date Reported</th><th>Technician</th><th>Status</th><th>Completion Date</th><th class="text-end">Actions</th></tr></thead><tbody>
@foreach($records as [$id,$tag,$name,$issue,$type,$reported,$technician,$status,$completion,$priority])
<tr><td><a class="asset-tag" href="{{ route('maintenance.show',$id) }}">{{ $id }}</a></td><td><a class="serial-number" href="{{ route('assets.show',$tag) }}">{{ $tag }}</a></td><td><strong>{{ $name }}</strong></td><td><span class="issue-cell" title="{{ $issue }}">{{ $issue }}</span><span class="priority-badge {{ $priorityClass($priority) }}">{{ $priority }}</span></td><td>{{ $type }}</td><td>{{ $reported }}</td><td>{{ $technician }}</td><td><span class="status-badge {{ $statusClass($status) }}">{{ $status }}</span></td><td>{{ $completion }}</td><td><div class="table-actions"><a href="{{ route('maintenance.show',$id) }}" class="btn action-button" title="View"><i class="bi bi-eye"></i></a><a href="{{ route('maintenance.edit',$id) }}" class="btn action-button" title="Edit"><i class="bi bi-pencil"></i></a><a href="{{ route('maintenance.status',$id) }}" class="btn action-button action-status" title="Update Status"><i class="bi bi-arrow-repeat"></i></a></div></td></tr>
@endforeach
</tbody></table></div><div class="table-pagination"><p>Showing <strong>1–8</strong> of <strong>316</strong></p><nav aria-label="Maintenance pagination"><ul class="pagination mb-0"><li class="page-item disabled"><a class="page-link" href="#"><i class="bi bi-chevron-left"></i></a></li><li class="page-item active"><a class="page-link" href="#">1</a></li><li class="page-item"><a class="page-link" href="#">2</a></li><li class="page-item"><a class="page-link" href="#">3</a></li><li class="page-item"><a class="page-link" href="#"><i class="bi bi-chevron-right"></i></a></li></ul></nav></div></section>
@endsection
