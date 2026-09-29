<div class="modal fade" id="manage-departments" tabindex="-1" aria-labelledby="departments-title" data-reopen="{{ session('departments_open') || str_starts_with(old('_form', ''), 'department-') ? 'true' : 'false' }}">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="departments-title">Manage Departments</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <p class="text-muted">Add or edit departments and their availability. Deactivation preserves all linked records.</p>
            @if(str_starts_with(old('_form', ''), 'department-'))@include('partials.alerts')@endif
            <form method="post" action="{{ route('departments.store') }}" class="department-add mb-4">
                @csrf<input type="hidden" name="_form" value="department-new"><input type="hidden" name="is_active" value="1">
                <label class="form-label" for="new-department-name">Department Name <span aria-hidden="true">*</span></label>
                <div class="d-flex gap-2"><input id="new-department-name" class="form-control" name="name" value="{{ old('_form') === 'department-new' ? old('name') : '' }}" maxlength="255" required><button class="btn btn-primary text-nowrap">Add Department</button></div>
            </form>
            <div class="table-responsive"><table class="table department-table"><thead><tr><th>Department Name</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            @forelse($departments as $department)
                <tr><td>{{ $department->name }}</td><td>@include('components.status',['status'=>$department->is_active?'Active':'Inactive'])</td><td><div class="table-actions">
                    <button class="btn btn-sm btn-light-border" type="button" data-bs-toggle="collapse" data-bs-target="#department-edit-{{ $department->id }}" aria-expanded="{{ old('_form') === 'department-'.$department->id ? 'true' : 'false' }}" aria-controls="department-edit-{{ $department->id }}">Edit</button>
                    <form method="post" action="{{ route('departments.update', $department) }}">@csrf @method('PUT')<input type="hidden" name="_form" value="department-{{ $department->id }}"><input type="hidden" name="name" value="{{ $department->name }}"><input type="hidden" name="is_active" value="{{ $department->is_active?0:1 }}"><button class="btn btn-sm btn-outline-primary">{{ $department->is_active?'Deactivate':'Activate' }}</button></form>
                </div></td></tr>
                <tr class="department-edit-row"><td colspan="3" class="p-0"><div class="collapse {{ old('_form') === 'department-'.$department->id ? 'show' : '' }}" id="department-edit-{{ $department->id }}"><form method="post" action="{{ route('departments.update', $department) }}" class="p-3 border-bottom">
                    @csrf @method('PUT')<input type="hidden" name="_form" value="department-{{ $department->id }}">
                    <div class="row g-3">
                        @include('components.field',['formId'=>'department-'.$department->id,'name'=>'name','label'=>'Department Name','value'=>$department->name,'required'=>true])
                        @include('components.field',['formId'=>'department-'.$department->id,'name'=>'is_active','label'=>'Status','options'=>[1=>'Active',0=>'Inactive'],'value'=>(int)$department->is_active,'required'=>true])
                    </div><div class="d-flex gap-2 mt-3"><button class="btn btn-primary">Save Department</button><button type="button" class="btn btn-light-border" data-bs-toggle="collapse" data-bs-target="#department-edit-{{ $department->id }}">Cancel</button></div>
                </form></div></td></tr>
            @empty<tr><td colspan="3" class="empty-state">No departments found.</td></tr>@endforelse
            </tbody></table></div>
        </div><div class="modal-footer"><button type="button" class="btn btn-light-border" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>
