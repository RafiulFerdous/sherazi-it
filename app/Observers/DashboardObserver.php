<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use SebastianBergmann\CodeCoverage\Report\Html\Dashboard;

class DashboardObserver
{
    private function clearCache(): void{
        Cache::tags('dashboard')->flush();
    }

    public function created(Dashboard $dashboard){
        $this->clearCache();
    }
    public function updated(Dashboard $dashboard){
        $this->clearCache();
    }
}
