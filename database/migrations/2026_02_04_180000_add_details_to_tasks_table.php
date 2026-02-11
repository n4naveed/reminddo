<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('recurrence_pattern')->nullable()->after('icon');
            $table->string('recurrence_id')->nullable()->after('recurrence_pattern')->index();
            $table->text('notes')->nullable()->after('recurrence_id');
            $table->boolean('all_day')->default(false)->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['recurrence_pattern', 'recurrence_id', 'notes', 'all_day']);
        });
    }
};
