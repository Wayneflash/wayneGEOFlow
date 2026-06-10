<?php

use App\Models\AiModel;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

foreach (AiModel::query()->where('status', 'active')->orderBy('id')->get() as $model) {
    echo sprintf(
        "#%d %s | type=%s | base=%s | model=%s\n",
        (int) $model->id,
        (string) $model->name,
        (string) ($model->model_type ?? ''),
        (string) ($model->api_url ?? ''),
        (string) ($model->model_id ?? ''),
    );
}
