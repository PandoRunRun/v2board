<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class OttResetTables extends Command
{
    protected $signature = 'ott:reset-tables';
    protected $description = 'Drop OTT tables to fix failed migration state';

    public function handle()
    {
        if (!$this->confirm('This will DROP all OTT tables (v2_ott_account, v2_ott_user, etc). Data will be lost. Continue?')) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        $tables = ['v2_ott_message', 'v2_ott_renewal', 'v2_ott_user', 'v2_ott_account'];
        
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
            $this->info("Dropped table: $table");
        }
        
        if (Schema::hasColumn('v2_user', 'is_ott')) {
            Schema::table('v2_user', function ($table) {
                $table->dropColumn('is_ott');
            });
            $this->info("Dropped column: is_ott from v2_user");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        $this->info('All OTT tables dropped. You can now run the migration again.');
    }
}
