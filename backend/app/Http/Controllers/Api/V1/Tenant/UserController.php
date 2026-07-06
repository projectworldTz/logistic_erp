<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreUserRequest;
use App\Http\Requests\Tenant\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index()
    {
        return UserResource::collection(User::query()->with('branch')->get());
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'password' => $data['password'],
            'status' => UserStatus::Active,
            'is_super_admin' => false,
            'email_verified_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId(app(TenantContext::class)->id());
        $user->assignRole($data['role']);

        $this->auditLogger->log(
            action: 'user.invited',
            auditable: $user,
            newValues: ['email' => $user->email, 'role' => $data['role']],
        );

        return new UserResource($user->load('branch'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if (isset($data['role']) && $user->id === $request->user()->id) {
            abort(422, 'You cannot change your own role.');
        }

        $user->update(collect($data)->except('role')->toArray());

        if (isset($data['role'])) {
            app(PermissionRegistrar::class)->setPermissionsTeamId(app(TenantContext::class)->id());
            $user->syncRoles([$data['role']]);
        }

        $this->auditLogger->log(
            action: 'user.updated',
            auditable: $user,
            newValues: $data,
        );

        return new UserResource($user->load('branch'));
    }

    public function suspend(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            abort(422, 'You cannot suspend your own account.');
        }

        $user->update(['status' => UserStatus::Suspended]);

        $this->auditLogger->log(action: 'user.suspended', auditable: $user);

        return new UserResource($user->load('branch'));
    }

    public function activate(User $user)
    {
        $user->update(['status' => UserStatus::Active]);

        $this->auditLogger->log(action: 'user.activated', auditable: $user);

        return new UserResource($user->load('branch'));
    }
}
