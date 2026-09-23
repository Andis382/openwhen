<?php

namespace App\Http\Controllers;

use App\Imports\ObservationImport;
use App\Imports\ShopImport;
use App\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CSV imports in two steps from the same endpoint: with "commit" false the file is only checked
 * and every row comes back with its errors; with "commit" true the valid rows are written.
 */
class ImportController extends Controller
{
    private const MAX_BYTES = 2_000_000;

    public function shops(Request $request, ShopImport $import): JsonResponse
    {
        [$csv, $commit] = $this->input($request);

        return response()->json($commit ? $import->import($csv) : $import->preview($csv));
    }

    public function observations(Request $request): JsonResponse
    {
        [$csv, $commit] = $this->input($request);
        $import = new ObservationImport(LocalTime::for($request->user()->organization));

        return response()->json($commit ? $import->import($csv) : $import->preview($csv));
    }

    /** @return array{0: string, 1: bool} */
    private function input(Request $request): array
    {
        $data = $request->validate([
            'csv' => ['required', 'string', 'max:'.self::MAX_BYTES],
            'commit' => ['sometimes', 'boolean'],
        ]);

        return [$data['csv'], (bool) ($data['commit'] ?? false)];
    }
}
