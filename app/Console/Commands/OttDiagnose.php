<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OttDiagnose extends Command
{
    protected $signature = 'ott:diagnose';
    protected $description = 'Diagnose database structure for OTT migration';

    public function handle()
    {
        $this->info('Checking v2_user table structure...');
        
        $result = DB::select("SHOW CREATE TABLE v2_user");
        $createTable = $result[0]->{'Create Table'};
        
        $this->line($createTable);
        
        $this->info("\n\nChecking v2_ott_account table (if exists)...");
        try {
            $result = DB::select("SHOW CREATE TABLE v2_ott_account");
            $createTable = $result[0]->{'Create Table'};
            $this->line($createTable);
        } catch (\Exception $e) {
            $this->warn("v2_ott_account table does not exist");
        }
        
        $this->info("\n\nChecking database engine and charset...");
        $db = DB::select("SELECT @@default_storage_engine as engine, @@character_set_database as charset");
        $this->line("Engine: " . $db[0]->engine);
        $this->line("Charset: " . $db[0]->charset);
    }
}
