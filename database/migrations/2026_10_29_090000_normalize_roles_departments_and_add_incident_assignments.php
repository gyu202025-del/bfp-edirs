<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('scope', 30);
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });

        foreach ([
            ['Administrator', 'system'],
            ['Head', 'department'],
            ['Staff', 'department'],
            ['Citizen', 'citizen'],
        ] as [$name, $scope]) {
            DB::table('roles')->insert(['name' => $name, 'scope' => $scope]);
        }

        DB::table('users')->distinct()->pluck('role')->filter()->each(function ($name) {
            DB::table('roles')->insertOrIgnore([
                'name' => $name,
                'scope' => in_array($name, ['Head', 'Staff'], true) ? 'department' : ($name === 'Citizen' ? 'citizen' : 'system'),
            ]);
        });

        foreach (['BFP', 'MDRRMO', 'PNP'] as $name) {
            DB::table('departments')->insert(['name' => $name]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('municipality')->constrained('departments')->nullOnDelete();
        });

        $roleIds = DB::table('roles')->pluck('id', 'name');
        DB::table('users')->orderBy('id')->chunk(200, function ($users) use ($roleIds) {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update([
                    'role_id' => $roleIds[$user->role] ?? null,
                ]);
            }
        });

        if (Schema::hasColumn('users', 'department')) {
            DB::table('users')->whereNotNull('department')->distinct()->pluck('department')
                ->map(fn ($name) => self::canonicalDepartment($name))
                ->filter()
                ->each(fn ($name) => DB::table('departments')->insertOrIgnore(['name' => $name]));

            $departmentIds = DB::table('departments')->pluck('id', 'name');
            DB::table('users')->orderBy('id')->chunk(200, function ($users) use ($departmentIds) {
                foreach ($users as $user) {
                    $legacyDepartment = self::canonicalDepartment($user->department);
                    if ($legacyDepartment !== '' && isset($departmentIds[$legacyDepartment])) {
                        DB::table('users')->where('id', $user->id)->update([
                            'department_id' => $departmentIds[$legacyDepartment],
                        ]);
                    }
                }
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('department');
            });
        }

        Schema::create('incident_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('Pending');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['incident_id', 'department_id']);
            $table->index(['department_id', 'status']);
        });
    }

    private static function canonicalDepartment(?string $name): string
    {
        $trimmed = trim((string) $name);

        foreach (['BFP', 'MDRRMO', 'PNP'] as $department) {
            if (strcasecmp($trimmed, $department) === 0) {
                return $department;
            }
        }

        return $trimmed;
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_assignments');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'department_id') && ! Schema::hasColumn('users', 'department')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('department')->nullable();
            });

            $departmentNames = DB::table('departments')->pluck('name', 'id');
            DB::table('users')->orderBy('id')->chunk(200, function ($users) use ($departmentNames) {
                foreach ($users as $user) {
                    if ($user->department_id && isset($departmentNames[$user->department_id])) {
                        DB::table('users')->where('id', $user->id)->update([
                            'department' => $departmentNames[$user->department_id],
                        ]);
                    }
                }
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('departments');
        Schema::dropIfExists('roles');
    }
};
