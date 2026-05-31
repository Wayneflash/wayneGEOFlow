<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $pageTitle }} - 文章预览</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('js/lucide.min.js') }}"></script>
    <style>
        body {
            background: #f5f7fb;
        }
        .doc-shell {
            max-width: 920px;
            margin: 32px auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.06);
        }
        .doc-toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 24px;
            border-bottom: 1px solid #eef2f7;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(10px);
            border-radius: 12px 12px 0 0;
        }
        .doc-page {
            padding: 56px 72px 76px;
        }
        .doc-title {
            margin: 0;
            color: #111827;
            font-size: 34px;
            line-height: 1.24;
            font-weight: 700;
            letter-spacing: 0;
        }
        .doc-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
            color: #64748b;
            font-size: 14px;
        }
        .doc-content {
            margin-top: 42px;
            color: #1f2937;
            font-size: 16px;
            line-height: 1.9;
        }
        .doc-content :where(h1, h2, h3, h4) {
            color: #111827;
            font-weight: 700;
            letter-spacing: 0;
        }
        .doc-content h1 {
            font-size: 30px;
            line-height: 1.3;
            margin: 42px 0 18px;
        }
        .doc-content h2 {
            font-size: 24px;
            line-height: 1.35;
            margin: 40px 0 16px;
        }
        .doc-content h3 {
            font-size: 19px;
            line-height: 1.45;
            margin: 28px 0 12px;
        }
        .doc-content :where(p, ul, ol, .article-table-wrap) {
            margin: 14px 0;
        }
        .doc-content :where(ul, ol) {
            padding-left: 1.4em;
        }
        .doc-content li {
            margin: 6px 0;
        }
        .doc-content blockquote {
            margin: 16px 0;
            padding: 0;
            border: 0;
            color: #1f2937;
            background: transparent;
        }
        .doc-content blockquote p {
            margin: 14px 0;
        }
        .doc-content .article-table-wrap {
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        .doc-content table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .doc-content th,
        .doc-content td {
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
        }
        .doc-content th {
            background: #f8fafc;
            font-weight: 600;
            color: #334155;
        }
        .doc-content img {
            max-width: 100%;
            height: auto;
            margin: 22px 0;
            border-radius: 8px;
        }
        @media (max-width: 760px) {
            .doc-shell {
                margin: 0;
                border-width: 0;
                border-radius: 0;
            }
            .doc-toolbar {
                border-radius: 0;
                padding: 12px 16px;
            }
            .doc-page {
                padding: 34px 22px 56px;
            }
            .doc-title {
                font-size: 28px;
            }
        }
    </style>
</head>
<body class="font-sans antialiased">
<main class="doc-shell">
    <div class="doc-toolbar">
        <div class="min-w-0">
            <div class="truncate text-sm font-semibold text-slate-900">文章预览</div>
            <div class="truncate text-xs text-slate-500">用于人工检查和浏览器插件同步</div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" id="copy-html-button" class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <i data-lucide="copy" class="h-4 w-4"></i>
                复制正文
            </button>
            <a href="{{ route('admin.articles.edit', ['articleId' => (int) $article->id]) }}" class="inline-flex h-9 items-center gap-2 rounded-lg bg-blue-600 px-3 text-sm font-medium text-white hover:bg-blue-700">
                <i data-lucide="edit-3" class="h-4 w-4"></i>
                返回编辑
            </a>
        </div>
    </div>

    <article class="doc-page">
        <h1 class="doc-title">{{ $article->title }}</h1>
        <div class="doc-meta">
            @if($article->category)
                <span>{{ $article->category->name }}</span>
            @endif
            @if($article->author)
                <span>{{ $article->author->name }}</span>
            @endif
            <span>{{ optional($publishedAt)->format('Y-m-d H:i') }}</span>
        </div>
        <section id="article-preview-content" class="doc-content">
            {!! $contentHtml !!}
        </section>
    </article>
</main>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        window.lucide?.createIcons?.();
        const button = document.getElementById('copy-html-button');
        const content = document.getElementById('article-preview-content');
        button?.addEventListener('click', async () => {
            if (!content || !navigator.clipboard) return;
            const html = `<h1>${@json((string) $article->title)}</h1>` + content.innerHTML;
            const text = content.innerText || '';
            try {
                await navigator.clipboard.write([
                    new ClipboardItem({
                        'text/html': new Blob([html], { type: 'text/html' }),
                        'text/plain': new Blob([text], { type: 'text/plain' }),
                    }),
                ]);
                button.classList.add('border-emerald-300', 'text-emerald-700');
                button.innerHTML = '<i data-lucide="check" class="h-4 w-4"></i>已复制';
                window.lucide?.createIcons?.();
            } catch (error) {
                await navigator.clipboard.writeText(text);
                button.textContent = '已复制文本';
            }
        });
    });
</script>
</body>
</html>
