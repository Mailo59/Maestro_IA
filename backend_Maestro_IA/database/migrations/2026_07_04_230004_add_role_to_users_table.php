<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            DB::statement("ALTER TABLE users ADD COLUMN role VARCHAR(255) NOT NULL DEFAULT 'student'");
        }

        $firstUserId = DB::table('users')->min('id');

        if ($firstUserId !== null) {
            DB::table('users')
                ->where('id', $firstUserId)
                ->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            DB::statement('ALTER TABLE users DROP COLUMN role');
        }
    }
};
