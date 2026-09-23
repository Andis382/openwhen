<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Phones;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user()->organization->toApi());
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole(User::OWNER), 403, __('errors.forbidden'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'locale' => ['nullable', Rule::in(['en', 'sq'])],
            'timezone' => ['nullable', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'currency' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/'],
            'depotName' => ['nullable', 'string', 'max:160'],
            'depotLat' => ['nullable', 'required_with:depotLng', 'numeric', 'between:-90,90'],
            'depotLng' => ['nullable', 'required_with:depotLat', 'numeric', 'between:-180,180'],
        ]);
        $org = $request->user()->organization;
        $org->fill([
            'name' => trim($data['name']),
            'phone' => Phones::normalize($data['phone'] ?? null),
        ]);
        if ($request->has('depotLat')) {
            $org->fill([
                'depot_name' => isset($data['depotName']) ? trim($data['depotName']) : null,
                'depot_lat' => $data['depotLat'] ?? null,
                'depot_lng' => $data['depotLng'] ?? null,
            ]);
        }
        foreach (['locale', 'timezone', 'currency'] as $field) {
            if (! empty($data[$field])) {
                $org->{$field} = $data[$field];
            }
        }
        $org->save();

        return response()->json($org->toApi());
    }
}
