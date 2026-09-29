<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssetCategoryController extends Controller
{
    public function save(Request $request, ?AssetCategory $category = null)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('asset_categories')->ignore($category)], 'is_active' => 'required|boolean']);
        DB::transaction(function () use ($category, $data) {
            if ($category) {
                $category = AssetCategory::lockForUpdate()->findOrFail($category->id);
                $old = $category->toArray();
                Asset::where('category', $category->name)->update(['category' => $data['name']]);
                $category->update($data);
            } else {
                $old = [];
                $category = AssetCategory::create($data);
            }
            Audit::record($old ? 'Category updated' : 'Category created', $category, $old, $category->toArray());
        });

        return back()->with('success', 'Asset category saved.');
    }
}
