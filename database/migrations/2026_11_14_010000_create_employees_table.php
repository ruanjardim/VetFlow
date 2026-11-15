<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('position', 120)->nullable();
            $table->text('document')->nullable();
            $table->string('employment_type', 30)->default('employee');
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->decimal('base_compensation', 12, 2)->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->text('payment_details')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'active', 'name']);
        });

        DB::table('users')
            ->whereNotNull('clinic_id')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    DB::table('employees')->insert([
                        'ulid' => (string) Str::ulid(),
                        'clinic_id' => $user->clinic_id,
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'position' => $user->position,
                        'employment_type' => 'employee',
                        'active' => (bool) $user->active,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        Schema::table('sales_commission_settlements', function (Blueprint $table): void {
            $table->foreignId('employee_id')
                ->nullable()
                ->after('seller_user_id')
                ->constrained('employees')
                ->nullOnDelete();
            $table->json('payment_snapshot')->nullable()->after('snapshot_hash');
        });

        DB::table('sales_commission_settlements')
            ->orderBy('id')
            ->chunkById(100, function ($settlements): void {
                foreach ($settlements as $settlement) {
                    $employeeId = DB::table('employees')
                        ->where('user_id', $settlement->seller_user_id)
                        ->value('id');

                    if ($employeeId) {
                        DB::table('sales_commission_settlements')
                            ->where('id', $settlement->id)
                            ->update(['employee_id' => $employeeId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('sales_commission_settlements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn('payment_snapshot');
        });

        Schema::dropIfExists('employees');
    }
};
