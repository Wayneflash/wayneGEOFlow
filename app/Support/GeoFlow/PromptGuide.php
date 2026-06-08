<?php

namespace App\Support\GeoFlow;

final class PromptGuide
{
    public static function description(string $name): string
    {
        $category = ContentPromptPresets::categoryForName($name);
        $categories = ContentPromptPresets::categories();

        foreach (ContentPromptPresets::templates() as $template) {
            if ($template['name'] === $name) {
                return $template['summary'];
            }
        }

        return match ($category) {
            'ranking' => '榜单/TOP 标题专用：先写评价维度，再列候选对象，不虚构排名。',
            'soft' => '软文/品牌传播：场景叙事 + 可信观点，避免硬广与无证据夸张词。',
            'entity' => '实体说明：定义、能力、场景与边界，利于 AI 引用与 RAG。',
            'comparison' => '选型对比：判断框架、维度对比、风险与 FAQ。',
            'solution' => '场景方案：痛点、路径、落地步骤与适用边界。',
            'faq' => 'FAQ 问答：每题短答案 + 展开，适合 AI 摘要。',
            'process' => '流程指南：阶段、动作、交付物与检查清单。',
            default => $categories['general'].'：适合大多数知识型、问答型文章。',
        };
    }

    public static function categoryLabel(string $name): string
    {
        $category = ContentPromptPresets::categoryForName($name);

        return ContentPromptPresets::categories()[$category] ?? '通用';
    }
}
