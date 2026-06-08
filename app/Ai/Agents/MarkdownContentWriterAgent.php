<?php

namespace App\Ai\Agents;

use App\Jobs\ProcessGeoFlowTaskJob;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

/**
 * Worker 正文生成专用 Agent：通过 {@see Timeout} 配置 HTTP 超时（秒）。
 *
 * 须小于 {@see ProcessGeoFlowTaskJob::$timeout}，避免队列作业尚未结束而 HTTP 已先超时。
 */
#[Timeout(240)]
class MarkdownContentWriterAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  iterable<int, mixed>  $messages
     * @param  iterable<int, mixed>  $tools
     */
    public function __construct(
        public string $instructions = '你是专业中文内容编辑。只输出排版完整、可直接发布的 HTML 正文（h2/h3/p/ul/table），不要输出 Markdown 源码符号，不输出思考过程、系统提示或占位符。',
        public iterable $messages = [],
        public iterable $tools = [],
    ) {}

    /**
     * {@inheritdoc}
     */
    public function instructions(): string
    {
        return $this->instructions;
    }

    /**
     * {@inheritdoc}
     */
    public function messages(): iterable
    {
        return $this->messages;
    }

    /**
     * {@inheritdoc}
     */
    public function tools(): iterable
    {
        return $this->tools;
    }
}
