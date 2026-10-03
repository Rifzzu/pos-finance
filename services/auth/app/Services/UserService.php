<?php

namespace App\Services;

use App\Exceptions\ApiError;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function paginate(array $filters, int $perPage = 15)
    {
        $q = User::query()->orderByDesc('id');

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%");
            });
        }

        if (! empty($filters['role'])) {
            $q->where('role', $filters['role']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $q->where('is_active', (bool) $filters['is_active']);
        }

        return $q->paginate($perPage);
    }

    public function create(array $data): User
    {
        $this->assertOutletRule($data['role'], $data['outlet_id'] ?? null);

        return DB::transaction(fn () => User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'outlet_id' => in_array($data['role'], ['kasir', 'supervisor_pos'], true) ? $data['outlet_id'] : null,
            'is_active' => true,
        ]));
    }

    public function update(User $target, array $data): User
    {
        $role = $data['role'] ?? $target->role;
        $outletId = array_key_exists('outlet_id', $data) ? $data['outlet_id'] : $target->outlet_id;

        $this->assertOutletRule($role, $outletId);

        return DB::transaction(function () use ($target, $data, $role) {
            if (empty($data['password'] ?? null)) {
                unset($data['password']);
            }

            $target->fill($data);

            if (in_array($role, ['kasir', 'supervisor_pos'], true)) {
                $target->outlet_id = $data['outlet_id'] ?? $target->outlet_id;
            } else {
                $target->outlet_id = null;
            }

            $target->save();

            return $target->fresh();
        });
    }

    public function setStatus(User $actor, User $target, bool $active): User
    {
        if ($actor->id === $target->id && ! $active) {
            throw ApiError::make(409, 'SELF_DEACTIVATE', 'Superadmin tidak boleh menonaktifkan dirinya sendiri.');
        }

        return DB::transaction(function () use ($target, $active) {
            $target->update(['is_active' => $active]);

            if (! $active) {
                app(AuthService::class)->revokeAllRefreshTokens($target);
            }

            return $target->fresh();
        });
    }

    private function assertOutletRule(string $role, mixed $outletId): void
    {
        if (in_array($role, ['kasir', 'supervisor_pos'], true)) {
            if ($outletId === null || $outletId === '' || (int) $outletId < 1) {
                throw ApiError::make(422, 'OUTLET_REQUIRED', 'outlet_id wajib untuk role kasir dan supervisor_pos.', [
                    'outlet_id' => ['outlet_id wajib untuk role ini.'],
                ]);
            }
        } elseif ($outletId !== null && $outletId !== '') {
            throw ApiError::make(422, 'OUTLET_FORBIDDEN', 'outlet_id harus NULL untuk role ini.', [
                'outlet_id' => ['outlet_id harus kosong untuk role ini.'],
            ]);
        }
    }
}
