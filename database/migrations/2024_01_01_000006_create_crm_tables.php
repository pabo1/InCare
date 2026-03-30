<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Полиморфная связь: задача может быть у Lead или Deal
            $table->morphs('taskable'); // taskable_type + taskable_id

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('call');        // call | reactivation | remind
            $table->string('status')->default('pending');   // pending | done | cancelled
            $table->dateTime('due_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            // Полиморфная связь: сообщение может быть у Lead или Deal
            $table->morphs('messageable');

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');                      // telegram | sipuni | instagram | internal
            $table->string('direction')->default('out');    // in | out
            $table->text('body');
            $table->string('status')->default('sent');      // sent | delivered | read | failed
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_stage_id')->nullable()->constrained()->nullOnDelete();

            $table->string('event');                        // stage_changed | task_created | appointment_reminder
            $table->string('action');                       // send_message | create_task | change_stage
            $table->json('params');                         // Параметры действия (текст, задержка и т.д.)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stage_history', function (Blueprint $table) {
            $table->id();
            $table->morphs('stageable');                    // Lead или Deal
            $table->foreignId('pipeline_stage_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('entered_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_history');
        Schema::dropIfExists('automations');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('tasks');
    }
};
