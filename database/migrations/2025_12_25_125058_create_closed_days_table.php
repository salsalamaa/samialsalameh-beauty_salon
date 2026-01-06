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
    Schema::create('closed_days', function (Blueprint $table) {
        $table->id();
        $table->date('date')->unique(); // يوم مُحدد لا يمكن تكراره
        $table->string('reason')->nullable(); // سبب الإغلاق (اختياري)
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('closed_days');
    }
};
