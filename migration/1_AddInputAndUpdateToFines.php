<?php
use SLiMS\Migration\Migration;
use SLiMS\Table\Schema;
use SLiMS\Table\Blueprint;

class AddInputAndUpdateToFines extends Migration
{
    public function up()
    {
        Schema::table('fines', function(Blueprint $table) {
            if (!Schema::table('fines')->column('input_date')->isExists()) {
                $table->date('input_date')->nullable()->after('description')->add();
            }

            if (!Schema::table('fines')->column('last_update')->isExists()) {
                $table->date('last_update')->nullable()->after('input_date')->add();
            }
        });
    }

    public function down()
    {
        Schema::table('fines', function(Blueprint $table) {
            if (Schema::table('fines')->column('input_date')->isExists()) {
                $table->drop('input_date');
            }

            if (Schema::table('fines')->column('last_update')->isExists()) {
                $table->drop('last_update');
            }
        });
    }
}
