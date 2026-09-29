@php
$navigation=[['Dashboard','bi-grid-1x2-fill','dashboard'],['Asset Management','bi-pc-display','assets.index']];
if(auth()->user()->isOperator())$navigation[]=['Asset Allocation','bi-person-check','allocations.index'];
$navigation[]=['Inventory Tracking','bi-box-seam','inventory.index'];
if(auth()->user()->isOperator())$navigation[]=['Maintenance','bi-tools','maintenance.index'];
if(auth()->user()->can('reports'))$navigation[]=['Reports','bi-file-earmark-bar-graph','reports'];
if(auth()->user()->isAdmin())$navigation[]=['User Management','bi-people','users.index'];
@endphp
<aside class="sidebar d-none d-lg-flex" aria-label="Main navigation">@include('partials.sidebar-content')</aside>
<div class="offcanvas offcanvas-start sidebar sidebar-mobile" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel"><div class="offcanvas-header"><div class="brand" id="mobileSidebarLabel"><x-brand-logo size="small" variant="light"/></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button></div><div class="offcanvas-body p-0 d-flex flex-column">@include('partials.sidebar-content',['hideBrand'=>true])</div></div>
