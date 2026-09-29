<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AssetImport;
use App\Services\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetImportController extends Controller
{
    public function template()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, AssetImport::HEADERS, ',', '"', '');
            fclose($out);
        }, 'assetkeeper-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function preview(Request $request, AssetImport $import)
    {
        $request->session()->forget('asset_import');
        $request->validate(['file' => ['required', 'file', 'max:2048', 'mimes:csv,txt', 'extensions:csv']]);
        $rows = $import->read($request->file('file')->getRealPath());
        $result = $import->validate($rows);
        $token = bin2hex(random_bytes(32));
        if (! $result['errors']) {
            $request->session()->put('asset_import', ['user_id' => $request->user()->id, 'expires' => time() + 1800, 'token' => $token, 'rows' => $rows]);
        }

        return view('assets.import-preview', compact('rows', 'result', 'token'));
    }

    public function commit(Request $request, AssetImport $import)
    {
        $request->validate(['token' => 'required|string']);
        $pending = $request->session()->pull('asset_import');
        if (! $pending || $pending['user_id'] !== $request->user()->id || $pending['expires'] < time() || ! hash_equals($pending['token'], $request->input('token'))) {
            throw ValidationException::withMessages(['file' => 'This preview expired or was already used. Upload the CSV again.']);
        }
        try {
            $count = DB::transaction(function () use ($pending, $import, $request) {
                $result = $import->validate($pending['rows']);
                if ($result['errors']) {
                    $messages = [];
                    foreach ($result['errors'] as $line => $errors) {
                        $messages[] = 'Row '.$line.': '.implode(' ', $errors);
                    }
                    throw ValidationException::withMessages(['file' => $messages]);
                }
                foreach ($result['valid'] as $data) {
                    $asset = Asset::create($data + ['created_by' => $request->user()->id]);
                    Audit::record('Asset created', $asset, [], $asset->toArray());
                }

                return count($result['valid']);
            });
        } catch (QueryException $e) {
            report($e);
            throw ValidationException::withMessages(['file' => 'Import cancelled; no assets were imported. Records may have changed since preview. Upload again to validate duplicates and current categories.']);
        }

        return redirect()->route('assets.index')->with('success', "Imported: $count. Skipped: 0. Failed: 0.");
    }
}
