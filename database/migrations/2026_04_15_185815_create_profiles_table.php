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
        Schema::create('profiles', function (Blueprint $table) {
           $table->uuid('id')->primary();
        $table->string('name')->unique();
        $table->string('gender');
        $table->float('gender_probability');
        $table->integer('sample_size');
        $table->integer('age');
        $table->string('age_group');
        $table->string('country_id');
        $table->float('country_probability');
        $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
