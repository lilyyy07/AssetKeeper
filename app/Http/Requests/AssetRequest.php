<?php

namespace App\Http\Requests;

use App\Models\Asset;
use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isOperator();
    }

    public function rules(): array
    {
        return ['asset_tag' => ['required', 'string', 'max:100', Rule::unique('assets')->ignore($this->route('asset'))], 'name' => 'required|string|max:255', 'category' => ['required', \App\Models\AssetCategory::selectionRule($this->route('asset')?->category)], 'brand' => 'nullable|string|max:255', 'model' => 'nullable|string|max:255', 'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('assets')->ignore($this->route('asset'))], 'purchase_date' => 'nullable|date|before_or_equal:today', 'purchase_price' => 'nullable|numeric|min:0|max:9999999999.99', 'warranty_expiry' => ['nullable', 'date', ...($this->filled('purchase_date') ? ['after_or_equal:purchase_date'] : [])], 'supplier' => 'nullable|string|max:255', 'location' => 'required|string|max:255', 'department_id' => ['nullable', Department::selectionRule($this->route('asset')?->department_id)], 'status' => ['required', Rule::in(['Available', 'Damaged', 'Disposed'])], 'condition' => ['required', Rule::in(Asset::CONDITIONS)], 'notes' => 'nullable|string|max:5000'];
    }
}
