<?php

use App\Models\Admin;
use App\Models\UrlImportJob;
use App\Services\GeoFlow\UrlImportProcessingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$jobId = (int) ($argv[1] ?? 0);
$libraryName = (string) ($argv[2] ?? '');

if ($jobId <= 0 || $libraryName === '') {
    fwrite(STDERR, "Usage: php scripts/url-import-commit-job.php {jobId} {libraryName}\n");
    exit(1);
}

$admin = Admin::query()->where('status', 'active')->orderBy('id')->first();
if (! $admin) {
    fwrite(STDERR, "No admin\n");
    exit(1);
}

Auth::guard('admin')->login($admin);

$job = UrlImportJob::query()->find($jobId);
if (! $job) {
    fwrite(STDERR, "Job not found\n");
    exit(1);
}

$service = app(UrlImportProcessingService::class);
$service->commit($job, $libraryName);

$job->refresh();
echo "Committed as {$libraryName}; step={$job->current_step}\n";
