<?php

/**
 * 快速测试当前默认 chat 模型（MiniMax）是否可用。
 * 用法：php scripts/test-chat-model.php [model_id]
 */

use App\Models\AiModel;
use App\Ai\Agents\MarkdownContentWriterAgent;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$modelId = (int) ($argv[1] ?? 2);
$model = AiModel::query()->find($modelId);
if (! $model) {
    fwrite(STDERR, "Model #{$modelId} not found\n");
    exit(1);
}

$apiUrl = (string) ($model->api_url ?? '');
$resolved = OpenAiRuntimeProvider::resolveChatBaseUrl($apiUrl);
$modelName = (string) ($model->model_id ?? '');
$crypto = app(ApiKeyCrypto::class);
$apiKey = $crypto->decrypt((string) ($model->getRawOriginal('api_key') ?? ''));

echo "Model: #{$model->id} {$model->name}\n";
echo "api_url: {$apiUrl}\n";
echo "resolved: {$resolved}\n";
echo "model_id: {$modelName}\n";
echo "endpoint: ".rtrim($resolved, '/')."/chat/completions\n";
echo "key_len: ".strlen($apiKey)."\n\n";

if ($apiKey === '') {
    fwrite(STDERR, "FAIL: empty api key\n");
    exit(1);
}

$driver = OpenAiRuntimeProvider::resolveChatDriver($resolved, $modelName);
$provider = OpenAiRuntimeProvider::registerProvider('chat_smoke', $driver, $resolved, $apiKey);

$started = microtime(true);
try {
    $agent = new MarkdownContentWriterAgent('你是助手，只回复 JSON：{"ok":true,"ping":"pong"}');
    $response = $agent->prompt('ping', [], $provider, $modelName, 45);
    $text = trim((string) ($response->text ?? ''));
    $ms = (int) round((microtime(true) - $started) * 1000);
    echo "OK in {$ms}ms\n";
    echo "response: ".substr($text, 0, 300)."\n";
    exit(0);
} catch (Throwable $e) {
    $ms = (int) round((microtime(true) - $started) * 1000);
    fwrite(STDERR, "FAIL after {$ms}ms: ".$e->getMessage()."\n");
    exit(1);
}
