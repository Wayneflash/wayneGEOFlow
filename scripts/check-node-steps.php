<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$jobId = (int) ($argv[1] ?? 0);
$query = App\Models\UrlImportJob::query()->orderByDesc('id');
if ($jobId > 0) {
    $query->whereKey($jobId);
}
$job = $query->first();
if ($job === null) {
    echo "no job\n";
    exit(1);
}

$controller = app(App\Http\Controllers\Admin\UrlImportController::class);
$ref = new ReflectionClass($controller);
$method = $ref->getMethod('buildNodeSteps');
$method->setAccessible(true);
$steps = $method->invoke($controller, $job);

echo 'job #' . $job->id . ' ' . $job->url . PHP_EOL;
echo 'steps: ' . count($steps) . PHP_EOL;
foreach ($steps as $step) {
    echo '  - ' . $step['key'] . ' | ' . $step['label'] . ' | ' . $step['status'] . PHP_EOL;
}
