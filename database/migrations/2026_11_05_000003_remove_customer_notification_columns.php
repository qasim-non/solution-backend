<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Push notifications are admin-only.
 *
 * The first version of this feature also targeted customers through a browser
 * `device_id`. That approach was removed, so this migration cleans up databases
 * that already ran the original migrations. Fresh databases already have the
 * final schema and this migration is a no-op for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Customer tokens (admin_id = null) can no longer receive anything.
        DB::table('device_tokens')->whereNull('admin_id')->delete();

        if (Schema::hasColumn('device_tokens', 'device_id')) {
            Schema::table('device_tokens', function (Blueprint $table) {
                $table->dropColumn('device_id');
            });
        }

        if (Schema::hasColumn('requests', 'device_id')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->dropColumn('device_id');
            });
        }

        $adminId = collect(Schema::getColumns('device_tokens'))
            ->firstWhere('name', 'admin_id');

        if (($adminId['nullable'] ?? false) === true) {
            // The original key used ON DELETE SET NULL, which is incompatible
            // with a NOT NULL column. Recreate it with ON DELETE CASCADE.
            Schema::table('device_tokens', function (Blueprint $table) {
                $table->dropForeign(['admin_id']);
            });

            Schema::table('device_tokens', function (Blueprint $table) {
                $table->foreignId('admin_id')->nullable(false)->change();
            });

            Schema::table('device_tokens', function (Blueprint $table) {
                $table->foreign('admin_id')
                    ->references('id')
                    ->on('admins')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Intentionally not reversible: the customer notification feature was
     * removed and must not be restored by a rollback.
     */
    public function down(): void
    {
        //
    }
};
