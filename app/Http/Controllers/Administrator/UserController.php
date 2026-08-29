<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserCreateRequest;
use App\Http\Requests\User\UserIndexRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Models\User;
use App\Parsers\User\UserParserInterface;
use App\Repositories\User\UserRepositoryInterface;
use App\Services\Auth\RoleServiceInterface;
use App\Services\User\UserServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        protected UserParserInterface $parser,
        protected UserRepositoryInterface $users,
        protected UserServiceInterface $service,
        protected RoleServiceInterface $roles,
    ) {}

    public function index(UserIndexRequest $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $this->parser->getFilterTransferFromArrayData($request->validated());
        $activeAdministratorCount = $this->users->countActiveAdministratorUsers($this->roles->administratorRoleValues());

        return view('administrator.users.index', [
            'filters' => $filters,
            'users' => $this->users->paginateForAdministrator($filters),
            'filterRoleOptions' => $this->roles->userManagementFilterOptions(),
            'activeAdministratorCount' => $activeAdministratorCount,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('administrator.users.create', [
            'managedUser' => new User(['is_active' => true, 'role' => 'customer']),
            'roleOptions' => $this->roles->userManagementRoleOptions(),
            'selectedRole' => 'customer',
        ]);
    }

    public function store(UserCreateRequest $request): RedirectResponse
    {
        $managedUser = $this->service->store(
            $this->parser->getTransferFromArrayData($request->validated()),
            $request->user('admin'),
        );

        return redirect()
            ->route('administrator.users.edit', $managedUser)
            ->with('status', 'User created successfully.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        return view('administrator.users.show', [
            'managedUser' => $user,
            'selectedRole' => $this->roles->normalizeUserManagementRoleValue($user->role),
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('administrator.users.edit', [
            'managedUser' => $user,
            'roleOptions' => $this->roles->userManagementRoleOptions($user),
            'selectedRole' => $this->roles->normalizeUserManagementRoleValue($user->role),
            'activeAdministratorCount' => $this->users->countActiveAdministratorUsers($this->roles->administratorRoleValues()),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->service->update(
            $user,
            $this->parser->getTransferFromArrayData($request->validated()),
            $request->user('admin'),
        );

        return redirect()
            ->route('administrator.users.edit', $user)
            ->with('status', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        $this->service->archive($user, $request->user('admin'));

        return redirect()
            ->route('administrator.users.index')
            ->with('status', 'User archived successfully.');
    }
}
