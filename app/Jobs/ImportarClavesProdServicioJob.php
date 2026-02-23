<?php

namespace App\Jobs;

use App\Imports\ClaveProdServicioImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportarClavesProdServicioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public string $path
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        $fullPath = Storage::disk('local')->path($this->path);
        if (!is_readable($fullPath)) {
            return;
        }

        try {
            Excel::import(new ClaveProdServicioImport, $fullPath);
        } finally {
            Storage::disk('local')->delete($this->path);
        }
    }
}
