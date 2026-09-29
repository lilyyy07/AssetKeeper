<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Allocation;
use App\Models\Asset;
use App\Models\Department;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonitorController extends Controller
{
    private function filters(Request $r): array
    {
        return $r->validate(['search' => 'nullable|string|max:255', 'category' => ['nullable', Rule::exists('asset_categories', 'name')], 'status' => ['nullable', Rule::in(array_merge(Asset::STATUSES, ['Active', 'Returned', 'Overdue', 'Pending', 'In Progress', 'Completed']))], 'department_id' => 'nullable|exists:departments,id', 'location' => 'nullable|string|max:255', 'condition' => ['nullable', Rule::in(Asset::CONDITIONS)], 'from' => 'nullable|date', 'to' => ['nullable', 'date', ...($r->filled('from') ? ['after_or_equal:from'] : [])], 'maintenance_status' => ['nullable', Rule::in(['Pending', 'In Progress', 'Completed'])], 'type' => ['nullable', Rule::in(['inventory', 'allocation', 'status', 'location', 'maintenance'])], 'export' => 'nullable|in:csv']);
    }

    private function assetQuery(Request $r, bool $assetStatus = true, bool $placement = true)
    {
        $q = Asset::visibleTo($r->user())->with(['department', 'currentAllocation']);
        if ($r->filled('search')) {
            $s = $r->input('search');
            $q->where(fn ($q) => $q->where('asset_tag', 'like', "%$s%")->orWhere('name', 'like', "%$s%")->orWhere('serial_number', 'like', "%$s%"));
        }foreach (['category', 'department_id', 'location', 'condition'] as $f) {
            if ($r->filled($f) && ($placement || ! in_array($f, ['department_id', 'location']))) {
                $q->where($f, $r->input($f));
            }
        }if ($assetStatus && $r->filled('status')) {
            $q->where('status', $r->input('status'));
        }

        return $q;
    }

    private function lookups(Request $r): array
    {
        return ['departments' => Department::orderBy('name')->get(), 'locations' => Asset::visibleTo($r->user())->distinct()->orderBy('location')->pluck('location')];
    }

    public function assets(Request $r)
    {
        $this->filters($r);
        $assets = $this->assetQuery($r)->latest()->paginate(15)->withQueryString();
        $inventory = $r->routeIs('inventory.*');
        $counts = Asset::visibleTo($r->user())->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('assets.index', compact('assets', 'inventory', 'counts') + $this->lookups($r));
    }

    public function dashboard(Request $r)
    {
        $query = Asset::visibleTo($r->user());
        $counts = (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $categories = (clone $query)->selectRaw('category, COUNT(*) as total')->groupBy('category')->pluck('total', 'category');
        $maintenance = MaintenanceRecord::whereIn('asset_id', (clone $query)->select('id'))->with('asset')->latest()->limit(5)->get();
        $activities = $r->user()->isAdmin() ? ActivityLog::with('user')->whereIn('action', ['Asset created', 'Asset updated', 'Asset allocated', 'Allocation updated', 'Asset returned', 'Asset status changed', 'Maintenance created', 'Maintenance updated', 'Maintenance completed', 'User created', 'User updated', 'User role changed', 'Account activated', 'Account deactivated', 'Department created', 'Department updated'])->latest()->limit(8)->get() : collect();

        return view('dashboard', compact('counts', 'categories', 'maintenance', 'activities'));
    }

    private function allocations(Request $r)
    {
        $q = Allocation::with(['asset', 'department'])->whereIn('asset_id', $this->assetQuery($r, false, false)->select('id'));
        foreach (['department_id', 'location'] as $field) {
            if ($r->filled($field)) {
                $q->where($field, $r->input($field));
            }
        }if ($r->filled('status')) {
            if ($r->status === 'Overdue') {
                $q->where('status', 'Active')->whereDate('expected_return_date', '<', today());
            } elseif ($r->status === 'Active') {
                $q->where('status', 'Active')->where(fn ($q) => $q->whereNull('expected_return_date')->orWhereDate('expected_return_date', '>=', today()));
            } else {
                $q->where('status', $r->status);
            }
        }

        return $q;
    }

    private function maintenanceQuery(Request $r)
    {
        $q = MaintenanceRecord::with(['asset', 'technician'])->whereIn('asset_id', $this->assetQuery($r, false)->select('id'));
        $status = $r->input('maintenance_status', $r->input('status'));
        if ($status) {
            $q->where('status', $status);
        }

        return $q;
    }

    public function workflows(Request $r)
    {
        $this->filters($r);
        $kind = $r->routeIs('allocations.*') ? 'allocation' : 'maintenance';
        $records = ($kind === 'allocation' ? $this->allocations($r) : $this->maintenanceQuery($r))->latest()->paginate(15)->withQueryString();
        $available = Asset::where('status', $kind === 'allocation' ? '=' : '!=', $kind === 'allocation' ? 'Available' : 'Disposed')->orderBy('asset_tag')->get();
        $technicians = User::where('is_active', true)->whereIn('role', array_slice(User::ROLES, 0, 2))->get();

        return view('workflows.index', compact('kind', 'records', 'available', 'technicians') + $this->lookups($r));
    }

    public function reports(Request $r)
    {
        $filters = $this->filters($r);
        $type = $r->input('type', 'inventory');
        $titles = ['inventory' => 'Asset Inventory Report', 'allocation' => 'Asset Allocation Report', 'status' => 'Asset Status Report', 'location' => 'Asset Location / Department Report', 'maintenance' => 'Maintenance Report'];
        $title = $titles[$type];
        $q = match ($type) {
            'allocation' => $this->allocations($r),'maintenance' => $this->maintenanceQuery($r),default => $this->assetQuery($r)
        };
        $date = match ($type) {
            'allocation' => 'allocation_date','maintenance' => 'date_reported',default => 'created_at'
        };
        if ($r->filled('from')) {
            $q->whereDate($date, '>=', $r->input('from'));
        }if ($r->filled('to')) {
            $q->whereDate($date, '<=', $r->input('to'));
        }$total = (clone $q)->count();
        $summary = match ($type) {
            'allocation' => (clone $q)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),'maintenance' => (clone $q)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),default => (clone $q)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')
        };
        $headers = match ($type) {
            'allocation' => ['Allocation ID', 'Asset Tag', 'Asset', 'Borrower Name', 'Borrower Type', 'Student / Staff ID', 'Department / Faculty', 'Location', 'Allocation Date', 'Expected Return', 'Actual Return', 'Status', 'Return Condition'],'maintenance' => ['Maintenance ID', 'Asset Tag', 'Asset', 'Issue', 'Type', 'Priority', 'Technician', 'Problem Reported', 'Problem Attended', 'Repair Started', 'Completed', 'Response Time', 'Repair Duration', 'Status'],default => ['Asset Tag', 'Asset', 'Category', 'Department', 'Location', 'Borrower Name', 'Status', 'Condition', 'Year Purchased', 'Purchase Price (RM)']
        };
        $map = fn ($x) => match ($type) {
            'allocation' => [$x->id, $x->asset->asset_tag, $x->asset->name, $x->assigned_to, $x->borrower_type ?? 'Not recorded', $x->borrower_identifier, $x->department?->name, $x->location, $x->allocation_date, $x->expected_return_date, $x->actual_return_date, $x->display_status, $x->return_condition],'maintenance' => [$x->id, $x->asset->asset_tag, $x->asset->name, $x->issue, $x->type, $x->priority, $x->technician?->name, $x->timelineDisplay('date_reported'), $x->timelineDisplay('problem_attended_at'), $x->timelineDisplay('repair_started_at'), $x->timelineDisplay('actual_completion_date'), $x->response_time ?? '—', $x->repair_duration ?? '—', $x->status],default => [$x->asset_tag, $x->name, $x->category, $x->department?->name, $x->location, $x->currentAllocation?->assigned_to, $x->status, $x->condition, $x->year_purchased, $x->purchase_price]
        };
        if ($r->input('export') === 'csv') {
            return response()->streamDownload(function () use ($q, $map, $headers) {
                $out = fopen('php://output', 'w');
                fputcsv($out, $headers);
                foreach ($q->lazy(200) as $x) {
                    fputcsv($out, array_map(fn ($v) => preg_match('/^[\s]*[=+@-]/u', (string) $v) ? "'".$v : $v, $map($x)));
                }fclose($out);
            }, 'assetkeeper-'.$type.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        if ($type === 'maintenance') {
            $headers = ['Asset / ID', 'Issue / Type', 'Technician / Priority', 'Problem Reported / Attended', 'Repair Started / Completed', 'Response Time', 'Repair Duration', 'Status'];
            $map = fn ($x) => [
                $x->asset->asset_tag."\n".$x->asset->name.' / #'.$x->id,
                $x->issue."\n".$x->type,
                ($x->technician?->name ?? 'Unassigned')."\n".$x->priority,
                'Reported: '.$x->timelineDisplay('date_reported')."\nAttended: ".$x->timelineDisplay('problem_attended_at'),
                'Started: '.$x->timelineDisplay('repair_started_at')."\nCompleted: ".$x->timelineDisplay('actual_completion_date'),
                $x->response_time ?? '—',
                $x->repair_duration ?? '—', $x->status,
            ];
        }
        $rows = $q->orderBy('id')->get()->map($map);

        return view('reports', compact('type', 'title', 'headers', 'rows', 'total', 'summary', 'filters') + $this->lookups($r));
    }
}
