<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DepartmentController extends Controller
{
    public function save(Request $request, ?Department $department = null)
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments')->ignore($department)],
            'is_active' => ['required', 'boolean'],
        ]);
        if (Department::whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->when($department, fn ($q) => $q->where('id', '!=', $department->id))->exists()) {
            throw ValidationException::withMessages(['name' => 'A department with this name already exists.']);
        }
        DB::transaction(function () use ($department, $data) {
            $old = $department?->toArray() ?? [];
            if ($department) {
                $department->update($data);
            } else {
                $department = Department::create($data);
            }
            Audit::record($old ? 'Department updated' : 'Department created', $department, $old, $department->toArray());
        });

        return redirect()->route('users.index')->with('success', 'Department saved.')->with('departments_open', true);
    }
}
