
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('incidents', 'municipality')) {
            Schema::table('incidents', function (Blueprint $table) {
                $table->string('municipality')->nullable();
            });
        }

        if (!Schema::hasColumn('incidents', 'barangay')) {
            Schema::table('incidents', function (Blueprint $table) {
                $table->string('barangay')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Do not remove these columns automatically because they
        // may have existed before this migration ran.
    }
};