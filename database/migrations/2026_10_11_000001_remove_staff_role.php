<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Only two roles remain: admin and super_admin. Existing "staff" accounts
 * become admins (they keep their login, history and authorship), and new
 * accounts default to admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotIn('role', ['admin', 'super_admin'])->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('admin')->change();
        });
    }

    public function down(): void
    {
        // Which admins used to be staff isn't recorded, so only the default goes back.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('staff')->change();
        });
    }
};
