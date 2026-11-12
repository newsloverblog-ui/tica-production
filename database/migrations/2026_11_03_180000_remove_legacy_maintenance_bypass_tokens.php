<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The admin area is explicitly reachable during maintenance, so a public
        // bypass secret only creates an unnecessary alternate entry point.
        DB::table('basic_settings')->whereNotNull('bypass_token')->update(['bypass_token' => null]);
    }

    public function down(): void
    {
        // Removed secrets cannot and should not be reconstructed.
    }
};
