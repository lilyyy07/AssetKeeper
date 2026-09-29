<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Services\AssetWorkflow;
use App\Services\Audit;
use App\Services\Qr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class AssetController extends Controller
{
    public function store(AssetRequest $r)
    {
        DB::transaction(function () use ($r) {
            $asset = Asset::create($r->validated() + ['created_by' => $r->user()->id]);
            Audit::record('Asset created', $asset, [], $asset->toArray());
        });

        return back()->with('success', 'Asset created.');
    }

    public function update(AssetRequest $r, Asset $asset, AssetWorkflow $workflow)
    {
        DB::transaction(function () use ($r, $asset, $workflow) {
            $asset = Asset::lockForUpdate()->findOrFail($asset->id);
            $old = $asset->toArray();
            $data = $r->validated();
            if ($data['status'] === 'Disposed' && ($asset->currentAllocation()->exists() || $asset->maintenanceRecords()->where('status', '!=', 'Completed')->exists())) {
                throw ValidationException::withMessages(['status' => 'Return the asset and complete maintenance before disposal.']);
            }$asset->update($data);
            $workflow->recalculate($asset);
            Audit::record('Asset updated', $asset, $old, $asset->toArray());
        });

        return back()->with('success', 'Asset updated.');
    }

    public function show(Asset $asset)
    {
        Gate::authorize('view-asset', $asset);
        $asset->load(['department', 'creator', 'currentAllocation.department', 'allocations.department', 'maintenanceRecords.technician']);
        $activities = auth()->user()->isAdmin() ? $asset->activityLogs()->with('user')->latest()->limit(50)->get() : collect();
        $qr = Qr::svg(URL::signedRoute('assets.verify', ['asset' => $asset->id]));

        return view('assets.show', compact('asset', 'activities', 'qr'));
    }
}
