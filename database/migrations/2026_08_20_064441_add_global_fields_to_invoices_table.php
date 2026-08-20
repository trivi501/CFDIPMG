<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_global')->default(false)->after('payment_receipt_id');
            $table->unsignedTinyInteger('period_month')->nullable()->after('is_global');
            $table->unsignedSmallInteger('period_year')->nullable()->after('period_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['is_global', 'period_month', 'period_year']);
        });
    }
};
