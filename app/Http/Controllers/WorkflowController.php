<?php

namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\Asset;
use App\Models\Department;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Services\AssetWorkflow;
use App\Services\Audit;
use App\Services\MaintenanceTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkflowController extends Controller
{
    public function showMaintenance(MaintenanceRecord $maintenance)
    {
        $maintenance->load(['asset.department', 'technician']);

        return view('maintenance.show', ['record' => $maintenance]);
    }

    private function allocationRules(?Allocation $allocation = null): array
    {
        return ['assigned_to' => 'required|string|max:255', 'borrower_type' => ['required', Rule::in(Allocation::BORROWER_TYPES)], 'borrower_identifier' => ['required_if:borrower_type,Student,Academic Staff,Administrative Staff,ICT Staff', 'nullable', 'string', 'max:64', 'regex:/\A[\pL\pN][\pL\pN._\/-]*\z/u'], 'borrower_email' => 'nullable|email|max:255', 'department_id' => ['required', Department::selectionRule($allocation?->department_id)], 'location' => 'required|string|max:255', 'allocation_date' => 'required|date|before_or_equal:today', 'expected_return_date' => 'nullable|date|after_or_equal:allocation_date', 'remarks' => 'nullable|string|max:5000'];
    }

    public function allocate(Request $r, AssetWorkflow $flow)
    {
        $data = $r->validate($this->allocationRules() + ['asset_id' => 'required|exists:assets,id'], [], ['assigned_to' => 'borrower name', 'borrower_identifier' => 'student / staff ID']);
        $flow->allocate($data);

        return back()->with('success', 'Asset allocated.');
    }

    public function editAllocation(Request $r, Allocation $allocation)
    {
        $data = $r->validate($this->allocationRules($allocation), [], ['assigned_to' => 'borrower name', 'borrower_identifier' => 'student / staff ID']);
        DB::transaction(function () use ($allocation, $data) {
            $asset = Asset::lockForUpdate()->findOrFail($allocation->asset_id);
            $allocation = Allocation::lockForUpdate()->findOrFail($allocation->id);
            abort_unless($allocation->status === 'Active', 422, 'Returned allocations cannot be edited.');
            $old = $allocation->toArray();
            $allocation->update($data);
            $asset->update(['department_id' => $data['department_id'], 'location' => $data['location']]);
            Audit::record('Allocation updated', $allocation, $old, $allocation->toArray());
        });

        return back()->with('success', 'Allocation updated.');
    }

    public function returnAsset(Request $r, Allocation $allocation, AssetWorkflow $flow)
    {
        $data = $r->validate(['actual_return_date' => 'required|date|after_or_equal:'.$allocation->allocation_date.'|before_or_equal:today', 'return_condition' => ['required', Rule::in(['Good', 'Fair', 'Poor'])], 'return_remarks' => 'nullable|string|max:5000']);
        $flow->returnAsset($allocation, $data);

        return back()->with('success', 'Asset returned.');
    }

    public function maintenance(Request $r, AssetWorkflow $flow, ?MaintenanceRecord $maintenance = null)
    {
        $data = $r->validate(['asset_id' => $maintenance ? 'prohibited' : 'required|exists:assets,id', 'issue' => 'required|string|max:5000', 'type' => ['required', Rule::in(['Preventive', 'Corrective', 'Hardware', 'Software'])], 'priority' => ['required', Rule::in(['Low', 'Medium', 'High', 'Critical'])], 'date_reported' => 'required|date|before_or_equal:now', 'problem_attended_at' => 'nullable|date_format:Y-m-d\\TH:i|before_or_equal:now', 'repair_started_at' => 'nullable|date_format:Y-m-d\\TH:i|before_or_equal:now', 'technician_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_active', true)->whereIn('role', array_slice(User::ROLES, 0, 2)))], 'status' => ['required', Rule::in(['Pending', 'In Progress', 'Completed'])], 'estimated_completion_date' => 'nullable|date', 'actual_completion_date' => 'required_if:status,Completed|nullable|date|before_or_equal:now', 'remarks' => 'nullable|string|max:5000']);
        $data = MaintenanceTimeline::prepare($data, $maintenance);
        if (! empty($data['estimated_completion_date']) && substr($data['estimated_completion_date'], 0, 10) < substr($data['date_reported'], 0, 10)) {
            throw ValidationException::withMessages(['estimated_completion_date' => 'Estimated completion must be on or after Problem Reported.']);
        }
        if ($data['status'] !== 'Completed') {
            $data['completion_time_recorded'] = false;
            $data['actual_completion_date'] = null;
        }$flow->maintenance($data, $maintenance);

        return back()->with('success', 'Maintenance saved.');
    }
}
