<?php

namespace App\Services;

use App\Http\Requests\AssetRequest;
use App\Models\Department;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AssetImport
{
    public const HEADERS = ['asset_tag', 'name', 'category', 'brand', 'model', 'serial_number', 'condition', 'status', 'department', 'location', 'purchase_date', 'purchase_price', 'supplier', 'warranty_expiry', 'notes'];

    public function read(string $path): array
    {
        $file = fopen($path, 'r');
        try {
            $headers = fgetcsv($file, 0, ',', '"', '');
            if (! $headers) {
                throw ValidationException::withMessages(['file' => 'The CSV is empty.']);
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            $headers = array_map('trim', $headers);
            if (count($headers) !== count(array_unique($headers)) || array_diff($headers, self::HEADERS) || array_diff(['asset_tag', 'name', 'category', 'condition', 'status', 'location'], $headers)) {
                throw ValidationException::withMessages(['file' => 'Use the template headers. Required columns: asset_tag, name, category, condition, status, location.']);
            }
            $rows = [];
            $line = 1;
            while (($values = fgetcsv($file, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if (count($rows) >= 500) {
                    throw ValidationException::withMessages(['file' => 'Import at most 500 rows per file.']);
                }
                if (count($values) !== count($headers)) {
                    throw ValidationException::withMessages(['file' => "Row $line has an incorrect number of columns."]);
                }
                $data = array_combine($headers, array_map(fn ($v) => trim($v) === '' ? null : trim($v), $values));
                if (! mb_check_encoding(implode('', $values), 'UTF-8')) {
                    throw ValidationException::withMessages(['file' => "Row $line must use UTF-8 encoding."]);
                }
                $rows[] = ['line' => $line, 'data' => $data];
            }
            if (! $rows) {
                throw ValidationException::withMessages(['file' => 'The CSV contains no asset rows.']);
            }

            return $rows;
        } finally {
            fclose($file);
        }
    }

    public function validate(array $rows): array
    {
        $valid = [];
        $errors = [];
        $seen = ['asset_tag' => [], 'serial_number' => []];
        foreach ($rows as $row) {
            $data = $row['data'];
            $line = $row['line'];
            $messages = [];
            $department = $data['department'] ?? null;
            unset($data['department']);
            $data['department_id'] = $department ? Department::where('name', $department)->where('is_active', true)->value('id') : null;
            if ($department && ! $data['department_id']) {
                $messages[] = 'Department must match an active department name.';
            }
            $request = AssetRequest::create('/', 'POST', $data);
            $rules = $request->rules();
            $rules['purchase_date'] = 'nullable|date_format:Y-m-d|before_or_equal:today';
            $rules['warranty_expiry'] = ['nullable', 'date_format:Y-m-d', ...(! empty($data['purchase_date']) ? ['after_or_equal:purchase_date'] : [])];
            $validator = Validator::make($data, $rules);
            $messages = array_merge($messages, $validator->errors()->all());
            foreach (array_keys($seen) as $field) {
                if (! empty($data[$field])) {
                    $key = mb_strtolower($data[$field]);
                    if (isset($seen[$field][$key])) {
                        $messages[] = "$field duplicates row {$seen[$field][$key]}.";
                    }
                    $seen[$field][$key] = $line;
                }
            }
            if ($messages) {
                $errors[$line] = $messages;
            } else {
                $valid[] = $validator->validated();
            }
        }

        return ['valid' => $valid, 'errors' => $errors];
    }
}
