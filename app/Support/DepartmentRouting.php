<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

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
        return collect(config('departments.categories', []))
            ->filter(fn ($categories) => in_array($category, $categories, true))
            ->keys()
            ->all();
    }

    public static function filterIncidents(Builder $query, ?string $department): Builder
    {
        $categories = self::categoriesFor($department);

        return $query->where(function (Builder $q) use ($categories) {
            $q->whereIn('category', $categories)
                ->orWhere(function (Builder $legacy) use ($categories) {
                    $legacy->whereNull('category')->whereIn('incident_type', $categories);
                });
        });
    }
}
