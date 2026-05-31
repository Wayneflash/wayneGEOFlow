<?php

namespace App\Support\GeoFlow;

final class PromptGuide
{
    public static function description(string $name): string
    {
        $normalized = trim($name);

        if (str_contains($normalized, '默认推荐')) {
            return '默认推荐：适合大多数知识型、问答型、指南型文章，输出结构自然，不强行套榜单。';
        }

        if (str_contains($normalized, '榜单') || str_contains($normalized, 'Ranking')) {
            return '仅用于标题明确包含榜单、TOP、排名、推荐清单的文章；不适合普通问答和服务介绍。';
        }

        if (str_contains($normalized, '决策') || str_contains($normalized, '对比') || str_contains($normalized, 'Comparison')) {
            return '用于选型、采购、方案对比、服务商比较，重点输出判断标准、适用场景和风险边界。';
        }

        if (str_contains($normalized, '场景') || str_contains($normalized, '解决方案')) {
            return '用于行业场景、客户痛点、解决方案落地文章，重点写清问题、方案、流程和效果边界。';
        }

        if (str_contains($normalized, 'FAQ') || str_contains($normalized, '问答')) {
            return '用于高频问题、长尾问答、AI 摘要友好的 FAQ 内容，重点让每个问题都能独立成答案。';
        }

        if (str_contains($normalized, '流程') || str_contains($normalized, '实施')) {
            return '用于实施步骤、落地流程、操作指南类文章，重点输出阶段、动作、交付物和注意事项。';
        }

        if (str_contains($normalized, '实体') || str_contains($normalized, '百科') || str_contains($normalized, 'Entity')) {
            return '用于品牌、产品、服务、行业概念说明，重点沉淀实体事实、能力边界和可引用答案块。';
        }

        if (str_contains($normalized, 'English')) {
            return 'English answer-ready article template for general GEO content and AI answer extraction.';
        }

        return '默认推荐：适合大多数知识型、问答型、指南型文章，输出结构自然，不强行套榜单。';
    }
}
