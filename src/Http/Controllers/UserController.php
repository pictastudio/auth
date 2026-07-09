<?php

namespace PictaStudio\Auth\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{JsonResponse, Request, Response};
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController
{
    public function index(Request $request): JsonResponse
    {
        $model = $this->resolveAuthModel();

        if ($model === null) {
            return $this->unresolvedAuthModelResponse();
        }

        if ($response = $this->authorizeAction($request, $model, 'view-any')) {
            return $response;
        }

        /** @var Model $authModel */
        $authModel = new $model;
        $perPage = $this->perPage($request);

        return response()->json(
            $model::query()
                ->orderBy($authModel->getKeyName())
                ->paginate($perPage)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $model = $this->resolveAuthModel();

        if ($model === null) {
            return $this->unresolvedAuthModelResponse();
        }

        if ($request->has('roles') && !$this->supportsRoles($model)) {
            return $this->unsupportedRolesResponse();
        }

        if ($response = $this->authorizeAction($request, $model, 'create')) {
            return $response;
        }

        /** @var Model $authModel */
        $authModel = new $model;

        $payload = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email', Rule::unique($authModel->getTable(), 'email')],
            'password' => $this->passwordRules(),
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', $this->roleExistsRule()],
        ]);

        /** @var Model $user */
        $user = new $model;
        $user->forceFill([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
        ])->save();

        if (array_key_exists('roles', $payload)) {
            $user->syncRoles($payload['roles']);
        }

        return response()->json(['user' => $user->fresh()], 201);
    }

    public function show(Request $request, string $user): JsonResponse
    {
        $model = $this->resolveAuthModel();

        if ($model === null) {
            return $this->unresolvedAuthModelResponse();
        }

        if ($response = $this->authorizeAction($request, $model, 'view')) {
            return $response;
        }

        $userModel = $this->findUser($model, $user);

        if ($userModel === null) {
            return $this->notFoundResponse();
        }

        return response()->json(['user' => $userModel]);
    }

    public function update(Request $request, string $user): JsonResponse
    {
        $model = $this->resolveAuthModel();

        if ($model === null) {
            return $this->unresolvedAuthModelResponse();
        }

        if ($request->has('roles') && !$this->supportsRoles($model)) {
            return $this->unsupportedRolesResponse();
        }

        if ($response = $this->authorizeAction($request, $model, 'update')) {
            return $response;
        }

        $userModel = $this->findUser($model, $user);

        if ($userModel === null) {
            return $this->notFoundResponse();
        }

        $payload = $request->validate([
            'name' => ['sometimes', 'string'],
            'email' => [
                'sometimes',
                'email',
                Rule::unique($userModel->getTable(), 'email')->ignore($userModel->getKey(), $userModel->getKeyName()),
            ],
            'password' => $this->passwordRules(required: false),
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', $this->roleExistsRule()],
        ]);

        $updates = [];

        foreach (['name', 'email'] as $attribute) {
            if (array_key_exists($attribute, $payload)) {
                $updates[$attribute] = $payload[$attribute];
            }
        }

        if (array_key_exists('password', $payload)) {
            $updates['password'] = Hash::make($payload['password']);
        }

        if ($updates !== []) {
            $userModel->forceFill($updates)->save();
        }

        if (array_key_exists('roles', $payload)) {
            $userModel->syncRoles($payload['roles']);
        }

        return response()->json(['user' => $userModel->fresh()]);
    }

    public function destroy(Request $request, string $user): JsonResponse|Response
    {
        $model = $this->resolveAuthModel();

        if ($model === null) {
            return $this->unresolvedAuthModelResponse();
        }

        if ($response = $this->authorizeAction($request, $model, 'delete')) {
            return $response;
        }

        $userModel = $this->findUser($model, $user);

        if ($userModel === null) {
            return $this->notFoundResponse();
        }

        $userModel->delete();

        return response()->noContent();
    }

    /**
     * @return class-string<Model>|null
     */
    private function resolveAuthModel(): ?string
    {
        $guard = $this->guard();
        $provider = config("auth.guards.{$guard}.provider");
        $model = is_string($provider) ? config("auth.providers.{$provider}.model") : null;

        if (!is_string($model) || !class_exists($model)) {
            return null;
        }

        return $model;
    }

    private function guard(): string
    {
        return config('picta-auth.guard', config('auth.defaults.guard', 'web'));
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function findUser(string $model, string $key): ?Model
    {
        return $model::query()->whereKey($key)->first();
    }

    private function authorizeAction(Request $request, string $model, string $action): ?JsonResponse
    {
        if (auth_authorize($model, $action, $request->user())) {
            return null;
        }

        return response()->json(['message' => 'Forbidden.'], 403);
    }

    private function unresolvedAuthModelResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Unable to resolve the auth model for the configured guard.',
        ], 500);
    }

    private function unsupportedRolesResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'The auth model must use Spatie roles to assign roles.',
        ], 500);
    }

    private function notFoundResponse(): JsonResponse
    {
        return response()->json(['message' => 'User not found.'], 404);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function supportsRoles(string $model): bool
    {
        return method_exists($model, 'syncRoles');
    }

    /**
     * @return array<int, mixed>
     */
    private function passwordRules(bool $required = true): array
    {
        $rules = config('picta-auth.password_rules', ['required', 'string', 'confirmed', 'min:8']);
        $rules = is_array($rules) ? $rules : ['required', 'string', 'confirmed', 'min:8'];

        if ($required) {
            return $rules;
        }

        return array_values(array_merge(
            ['sometimes'],
            array_filter($rules, fn (mixed $rule): bool => $rule !== 'required')
        ));
    }

    private function roleExistsRule(): mixed
    {
        $roleModel = config('permission.models.role', Role::class);
        $roleTable = is_string($roleModel) && class_exists($roleModel)
            ? (new $roleModel)->getTable()
            : 'roles';

        return Rule::exists($roleTable, 'name')->where('guard_name', $this->guard());
    }

    private function perPage(Request $request): int
    {
        $default = (int) config('picta-auth.users.pagination.per_page', 15);
        $maximum = (int) config('picta-auth.users.pagination.max_per_page', 100);
        $requested = (int) $request->query('per_page', $default);

        return max(1, min($requested, max(1, $maximum)));
    }
}
