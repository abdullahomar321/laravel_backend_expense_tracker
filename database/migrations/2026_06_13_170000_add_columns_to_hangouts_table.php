<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hangouts', function (Blueprint $table) {
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
        });
    }

    public function down(): void
    {
        Schema::table('hangouts', function (Blueprint $table) {
            $table->dropForeign(['creator_id']);
            $table->dropColumn(['creator_id', 'name']);
        });
    }
};
