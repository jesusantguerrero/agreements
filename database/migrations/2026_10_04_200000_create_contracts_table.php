<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->index();
            $table->foreignId('user_id');
            $table->string('type', 32);                       // key in agreements.types
            $table->foreignId('client_id')->index();          // who pays
            $table->string('title', 160);
            $table->string('number', 40)->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 13, 2);
            $table->string('currency_code', 3)->default('USD');
            $table->string('cycle', 16)->default('monthly');  // monthly, biweekly, weekly, quarterly, yearly, one_time
            $table->date('start_date');
            $table->date('end_date')->nullable();             // null = indefinite
            $table->unsignedTinyInteger('invoice_day')->nullable(); // 1-31 for monthly-like cycles
            $table->unsignedSmallInteger('due_days')->default(15);
            $table->date('next_invoice_date')->nullable();    // null when nothing else to bill
            $table->json('generated_invoice_dates')->nullable();
            $table->boolean('auto_issue')->default(false);    // false: invoices are drafts to review
            $table->decimal('late_fee', 13, 2)->nullable();
            $table->string('late_fee_type', 16)->nullable();  // fixed | percentage
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->string('status', 16)->default('active');  // draft, active, paused, ended, cancelled
            $table->json('meta_data')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->index();
            $table->string('role', 32);                       // project, contact, property, student…
            $table->string('related_type');
            $table->unsignedBigInteger('related_id');
            $table->timestamps();
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_relations');
        Schema::dropIfExists('contracts');
    }
};
