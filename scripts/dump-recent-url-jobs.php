<?php

use App\Models\UrlImportJob;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

foreach (UrlImportJob::query()->orderByDesc('id')->limit(8)->get() as $job) {
    echo sprintf(
        "#%d | %s | step=%s | %d%% | %s | err=%s\n",
        (int) $job->id,
        (string) $job->status,
        (string) $job->current_step,
        (int) $job->progress_percent,
        (string) $job->normalized_url,
        mb_substr((string) ($job->error_message ?? ''), 0, 80)
    );
}
