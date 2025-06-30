<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateTenantsTable extends Migration
{
    public function up()
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('domain')->unique();
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->after('id')->nullable()->index();
            }
        });
    }
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
        Schema::dropIfExists('tenants');
    }
}
