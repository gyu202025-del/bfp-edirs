<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use App\Models\Department;
use App\Models\User;

class DepartmentRouting
{
    public static function options(): array
    {
        return array_keys(config('departments.categories', []));
    }

    public static function categoriesFor(?string $department): array
    {
        return config('departments.categories.' . $department, []);
    }

    public static function departmentsForCategory(?string $category): array
    {
        if ($category === 'Crime') {
            $category = 'Crime/Security';
        }

        return collect(config('departments.categories', []))
            ->filter(fn ($categories) => in_array($category, $categories, true))
            ->keys()
            ->all();
    }

    public static function filterAssignedIncidents(Builder $query, User $user): Builder
    {
        if ($user->isAdministrator()) {
            return $query;
        }

        if (!$user->department_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('assignments', fn (Builder $q) => $q->where('department_id', $user->department_id));
    }

    public static function departmentIdsForCategory(?string $category): array
    {
        $names = self::departmentsForCategory($category);

        return Department::whereIn('name', $names)->pluck('id')->all();
    }
}
