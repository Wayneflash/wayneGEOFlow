<?php

namespace App\Support\GeoFlow;

/**
 * 关键词 / SEO 描述类辅助提示词默认值。
 *
 * 不含 {{title}} 等变量：调用方会把标题、正文等上下文一并传给模型。
 */
final class SpecialPromptDefaults
{
    public static function keyword(): string
    {
        return <<<'PROMPT'
## 任务
根据系统提供的文章标题与正文，生成 5-10 个适合搜索引擎与 AI 问答抓取的关键词或短语。

## 要求
- 覆盖核心词、长尾问句、决策型词（如「怎么选」「多少钱」「适不适合」）
- 每个词 2-12 个字，不堆砌、不重复
- 优先从标题与正文中提炼，不虚构无关热词

## 输出
只输出关键词列表，每行一个，不要编号、不要解释。
PROMPT;
    }

    public static function description(): string
    {
        return <<<'PROMPT'
## 任务
根据系统提供的标题、关键词与正文，写一段 80-160 字的 SEO/GEO 元描述。

## 要求
- 第一句直接回应标题中的核心问题
- 包含主体实体与核心价值，语气克制、具体
- 适合搜索引擎摘要与 AI 答案引用，不虚构数据或承诺

## 输出
只输出描述文本，不要引号、前缀或 Markdown。
PROMPT;
    }
}
