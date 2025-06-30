<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
class CreatePermissionTables extends Migration
{
    public function up()
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create($tableNames['roles'], function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
            $table->unique(['name', 'guard_name', 'tenant_id']);
        });
        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->index([$columnNames['model_morph_key'], 'model_type']);
            $table->foreign('permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');
            $table->primary(['permission_id', $columnNames['model_morph_key'], 'model_type', 'tenant_id'], 'model_has_permissions_primary');
        });
        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->index([$columnNames['model_morph_key'], 'model_type']);
            $table->foreign('role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');
            $table->primary(['role_id', $columnNames['model_morph_key'], 'model_type', 'tenant_id'], 'model_has_roles_primary');
        });
        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreign('permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');
            $table->primary(['permission_id', 'role_id', 'tenant_id'], 'role_has_permissions_primary');
        });
        app('cache')->forget('spatie.permission.cache');
    }
    public function down()
    {
        $tableNames = config('permission.table_names');
        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
}
