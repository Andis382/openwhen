<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Support\Tokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** People in the organisation and the join links waiting to be used. */
class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $me = $request->user();
        $members = User::where('organization_id', $me->organization_id)->orderBy('name')->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'lastLoginAt' => $u->last_login_at?->toIso8601String(),
                'you' => $u->id === $me->id,
            ]);
        $pending = Invitation::whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get()
            ->map(fn (Invitation $i) => $i->toApi());

        return response()->json(['members' => $members, 'invitations' => $pending]);
    }

    /** People a route or trip can be given to. Users carry no tenant scope, hence the explicit filter. */
    public function drivers(Request $request): JsonResponse
    {
        return response()->json(
            User::where('organization_id', $request->user()->organization_id)
                ->where('role', User::DRIVER)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]),
        );
    }

    public function invite(Request $request): JsonResponse
    {
        $this->ownerOnly($request);
        $data = $request->validate([
            'role' => ['required', Rule::in(User::INVITABLE_ROLES)],
            'name' => ['nullable', 'string', 'max:120'],
        ]);
        $invitation = Invitation::create([
            'token' => Tokens::urlToken(),
            'role' => $data['role'],
            'name' => isset($data['name']) ? trim($data['name']) : null,
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(14),
        ]);

        return response()->json($invitation->toApi(), 201);
    }

    public function revoke(Request $request, int $id): Response
    {
        $this->ownerOnly($request);
        Invitation::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function remove(Request $request, int $id): Response
    {
        $this->ownerOnly($request);
        abort_if($id === $request->user()->id, 409, __('errors.cannot_remove_self'));
        User::where('organization_id', $request->user()->organization_id)->findOrFail($id)->delete();

        return response()->noContent();
    }

    private function ownerOnly(Request $request): void
    {
        abort_unless($request->user()->hasRole(User::OWNER), 403, __('errors.forbidden'));
    }
}
