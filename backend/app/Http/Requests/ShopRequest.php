<?php

namespace App\Http\Requests;

use App\Hours\DeclaredHours;
use App\Hours\InvalidHours;
use App\Models\Shop;
use App\Support\Phones;
use App\Support\Tenant;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShopRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Shop|null $shop */
        $shop = $this->route('shop');

        return [
            'name' => ['required', 'string', 'max:160'],
            'address' => ['required', 'string', 'max:200'],
            'town' => ['required', 'string', 'max:80'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('shops', 'code')->where('organization_id', Tenant::id())->ignore($shop?->id)],
            'phone' => ['nullable', 'string', 'max:40', function (string $attribute, mixed $value, Closure $fail) {
                if (! Phones::isPlausible(Phones::normalize($value))) {
                    $fail(__('validation.phone'));
                }
            }],
            'contactName' => ['nullable', 'string', 'max:120'],
            'accessNotes' => ['nullable', 'string', 'max:1000'],
            'orderValueCents' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'active' => ['sometimes', 'boolean'],
            'declaredHours' => ['nullable', 'array', function (string $attribute, mixed $value, Closure $fail) {
                try {
                    DeclaredHours::fromArray($value);
                } catch (InvalidHours $e) {
                    $fail(__('hours.'.$e->reason));
                }
            }],
        ];
    }

    /** The validated input as model attributes; "active" only when it was sent. */
    public function shopAttributes(): array
    {
        $data = $this->validated();
        $attributes = [
            'name' => trim($data['name']),
            'address' => trim($data['address']),
            'town' => trim($data['town']),
            'lat' => round((float) $data['lat'], 6),
            'lng' => round((float) $data['lng'], 6),
            'code' => isset($data['code']) && trim($data['code']) !== '' ? trim($data['code']) : null,
            'phone' => Phones::normalize($data['phone'] ?? null),
            'contact_name' => isset($data['contactName']) ? trim($data['contactName']) : null,
            'access_notes' => isset($data['accessNotes']) ? trim($data['accessNotes']) : null,
            'order_value_cents' => $data['orderValueCents'] ?? null,
            'declared_hours' => DeclaredHours::fromArray($data['declaredHours'] ?? null)->toArray(),
        ];
        if (array_key_exists('active', $data)) {
            $attributes['active'] = (bool) $data['active'];
        }

        return $attributes;
    }
}
