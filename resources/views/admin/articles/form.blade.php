@extends('admin.layouts.app')

@php
    $i18nRoot = $isEdit ? 'admin.article_edit' : 'admin.article_create';
    $formAction = $isEdit
        ? route('admin.articles.update', ['articleId' => (int) $articleId])
        : route('admin.articles.store');

    $formData = [
        'title' => old('title', (string) ($articleForm['title'] ?? '')),
        'excerpt' => old('excerpt', (string) ($articleForm['excerpt'] ?? '')),
        'content' => old('content', (string) ($articleForm['content'] ?? '')),
        'keywords' => old('keywords', (string) ($articleForm['keywords'] ?? '')),
        'meta_description' => old('meta_description', (string) ($articleForm['meta_description'] ?? '')),
        'status' => old('status', (string) ($articleForm['status'] ?? 'draft')),
        'review_status' => old('review_status', (string) ($articleForm['review_status'] ?? 'pending')),
        'category_id' => old('category_id', (string) ($articleForm['category_id'] ?? '')),
        'author_id' => old('author_id', (string) ($articleForm['author_id'] ?? '')),
        'slug' => (string) ($articleForm['slug'] ?? ''),
        'published_at' => (string) ($articleForm['published_at'] ?? ''),
        'task_id' => (int) ($articleForm['task_id'] ?? 0),
        'task_name' => (string) ($articleForm['task_name'] ?? ''),
        'is_hot' => old('is_hot', !empty($articleForm['is_hot']) ? '1' : '0'),
        'is_featured' => old('is_featured', !empty($articleForm['is_featured']) ? '1' : '0'),
    ];
    $articleBackUrl = $formData['task_id'] > 0
        ? route('admin.articles.index', ['task_id' => $formData['task_id']])
        : route('admin.articles.index');
@endphp

@section('content')
    <form method="POST" action="{{ $formAction }}" class="space-y-5">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <section class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <a href="{{ $articleBackUrl }}" class="mb-3 inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-blue-700">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        返回文章管理
                    </a>
                    <h1 class="truncate text-2xl font-semibold tracking-tight text-slate-950">{{ $isEdit ? '编辑文章' : '创建文章' }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ $isEdit ? ($formData['title'] ?: '调整文章内容与发布设置') : '手动补充单篇文章；批量生成建议从任务管理创建任务。' }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if(!$isEdit)
                        <a href="{{ route('admin.tasks.create') }}" class="admin-btn-secondary">
                            <i data-lucide="bot" class="h-4 w-4"></i>
                            AI 批量生成
                        </a>
                    @else
                        <a href="{{ route('admin.articles.preview', ['articleId' => (int) $articleId]) }}" target="_blank" rel="noopener" class="admin-btn-secondary">
                            <i data-lucide="eye" class="h-4 w-4"></i>
                            预览
                        </a>
                    @endif
                    <button type="submit" class="admin-btn-primary">
                        <i data-lucide="save" class="h-4 w-4"></i>
                        {{ $isEdit ? __('admin.article_edit.button.save_changes') : __('admin.button.create_article') }}
                    </button>
                </div>
            </div>
        </section>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="space-y-5">
                <div class="admin-panel">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-base font-semibold text-slate-950">文章内容</h2>
                    </div>
                    <div class="space-y-4 p-5">
                        <div>
                            <label for="title" class="admin-label">标题 *</label>
                            <input id="title" type="text" name="title" required value="{{ $formData['title'] }}" class="admin-input h-12 text-base font-semibold" placeholder="{{ __($i18nRoot.'.placeholder.title') }}">
                        </div>
                        <div>
                            <label for="excerpt" class="admin-label">摘要</label>
                            <textarea id="excerpt" name="excerpt" rows="2" class="admin-input min-h-[5rem]" placeholder="可选，不填时会自动从正文截取">{{ $formData['excerpt'] }}</textarea>
                        </div>
                        <div>
                            <div class="mb-1.5 flex flex-wrap items-center justify-between gap-3">
                                <label for="content-textarea" class="admin-label mb-0">正文 *</label>
                                <div class="article-editor-tabs" role="tablist" aria-label="{{ __($i18nRoot.'.label.preview') }}">
                                    <button type="button" id="article-tab-edit" class="article-editor-tab is-active" role="tab" aria-selected="true" aria-controls="article-editor-edit-pane" onclick="switchArticleEditorTab('edit')">
                                        <i data-lucide="code-2" class="h-3.5 w-3.5"></i>
                                        {{ __($i18nRoot.'.button.edit_source') }}
                                    </button>
                                    <button type="button" id="article-tab-preview" class="article-editor-tab" role="tab" aria-selected="false" aria-controls="article-editor-preview-pane" onclick="switchArticleEditorTab('preview')">
                                        <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                        {{ __($i18nRoot.'.button.visual_preview') }}
                                    </button>
                                </div>
                            </div>
                            <div id="article-content-editor" class="article-content-editor-shell">
                                <div id="article-editor-edit-pane" class="article-editor-pane is-active" role="tabpanel">
                                    <textarea id="content-textarea" name="content" required class="admin-input min-h-[34rem] w-full font-mono text-[13px] leading-relaxed" placeholder="{{ __($i18nRoot.'.placeholder.content') }}">{{ $formData['content'] }}</textarea>
                                </div>
                                <div id="article-editor-preview-pane" class="article-editor-pane" role="tabpanel" hidden>
                                    <div id="content-preview" class="markdown-preview-pane markdown-preview-pane--visual"></div>
                                </div>
                            </div>
                            <p id="content-image-status" class="mt-1.5 hidden text-xs text-slate-500" aria-live="polite"></p>
                            <p class="mt-1.5 text-xs text-slate-400">{{ __($i18nRoot.'.help.paste_image') }}</p>
                        </div>
                    </div>
                </div>

                <details class="admin-panel group">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">SEO 信息</h2>
                            <p class="mt-0.5 text-xs text-slate-400">低频字段，默认收起，需要时再补充</p>
                        </div>
                        <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition group-open:rotate-180"></i>
                    </summary>
                    <div class="space-y-4 border-t border-slate-100 p-5">
                        <div>
                            <label for="keywords" class="admin-label">关键词</label>
                            <input id="keywords" type="text" name="keywords" value="{{ $formData['keywords'] }}" class="admin-input" placeholder="{{ __($i18nRoot.'.placeholder.keywords') }}">
                        </div>
                        <div>
                            <label for="meta_description" class="admin-label">Meta 描述</label>
                            <textarea id="meta_description" name="meta_description" rows="3" class="admin-input min-h-[6rem]" placeholder="{{ __($i18nRoot.'.placeholder.meta_description') }}">{{ $formData['meta_description'] }}</textarea>
                        </div>
                    </div>
                </details>
            </section>

            <aside class="space-y-5 xl:sticky xl:top-24 xl:self-start">
                <div class="admin-panel">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-base font-semibold text-slate-950">发布设置</h2>
                    </div>
                    <div class="space-y-4 p-5">
                        <div>
                            <label for="status" class="admin-label">发布状态</label>
                            <select id="status" name="status" class="admin-input">
                                <option value="draft" @selected($formData['status'] === 'draft')>{{ __('admin.articles.status.draft') }}</option>
                                <option value="published" @selected($formData['status'] === 'published')>{{ __('admin.articles.status.published') }}</option>
                                <option value="private" @selected($formData['status'] === 'private')>{{ __('admin.articles.status.private') }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="review_status" class="admin-label">审核状态</label>
                            <select id="review_status" name="review_status" class="admin-input">
                                <option value="pending" @selected($formData['review_status'] === 'pending')>{{ __('admin.articles.review.pending') }}</option>
                                <option value="approved" @selected($formData['review_status'] === 'approved')>{{ __('admin.articles.review.approved') }}</option>
                                <option value="rejected" @selected($formData['review_status'] === 'rejected')>{{ __('admin.articles.review.rejected') }}</option>
                                <option value="auto_approved" @selected($formData['review_status'] === 'auto_approved')>{{ __('admin.articles.review.auto_approved') }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="category_id" class="admin-label">栏目 *</label>
                            <select id="category_id" name="category_id" required class="admin-input">
                                <option value="">{{ __($i18nRoot.'.option.select_category') }}</option>
                                @foreach(($formOptions['categories'] ?? []) as $category)
                                    <option value="{{ (int) $category['id'] }}" @selected($formData['category_id'] === (string) $category['id'])>{{ $category['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="author_id" class="admin-label">作者 *</label>
                            <select id="author_id" name="author_id" required class="admin-input">
                                <option value="">{{ __($i18nRoot.'.option.select_author') }}</option>
                                @foreach(($formOptions['authors'] ?? []) as $author)
                                    <option value="{{ (int) $author['id'] }}" @selected($formData['author_id'] === (string) $author['id'])>{{ $author['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <details class="admin-panel group">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">推荐标记</h2>
                            <p class="mt-0.5 text-xs text-slate-400">首页推荐、热门等展示控制</p>
                        </div>
                        <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition group-open:rotate-180"></i>
                    </summary>
                    <div class="space-y-3 border-t border-slate-100 p-5">
                        <label class="flex items-start gap-3 rounded-lg border border-slate-100 bg-slate-50/70 p-3 text-sm text-slate-700">
                            <input type="checkbox" name="is_hot" value="1" @checked((string) $formData['is_hot'] === '1') class="mt-1 rounded border-slate-300 text-blue-600 shadow-sm focus:ring-blue-500">
                            <span>
                                <span class="font-medium text-slate-900">{{ __($i18nRoot.'.field.is_hot') }}</span>
                                <span class="block text-xs text-slate-500">{{ __($i18nRoot.'.help.is_hot') }}</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-lg border border-slate-100 bg-slate-50/70 p-3 text-sm text-slate-700">
                            <input type="checkbox" name="is_featured" value="1" @checked((string) $formData['is_featured'] === '1') class="mt-1 rounded border-slate-300 text-blue-600 shadow-sm focus:ring-blue-500">
                            <span>
                                <span class="font-medium text-slate-900">{{ __($i18nRoot.'.field.is_featured') }}</span>
                                <span class="block text-xs text-slate-500">{{ __($i18nRoot.'.help.is_featured') }}</span>
                            </span>
                        </label>
                    </div>
                </details>

                @if($isEdit)
                    <details class="admin-panel group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4">
                            <div>
                                <h2 class="text-base font-semibold text-slate-950">文章信息</h2>
                                <p class="mt-0.5 text-xs text-slate-400">ID、来源任务和发布时间</p>
                            </div>
                            <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition group-open:rotate-180"></i>
                        </summary>
                        <div class="space-y-2 border-t border-slate-100 p-5 text-sm text-slate-600">
                            <div>ID: #{{ (int) $articleId }}</div>
                            <div class="break-all">Slug: {{ $formData['slug'] ?: '-' }}</div>
                            <div>发布时间: {{ $formData['published_at'] !== '' ? $formData['published_at'] : '-' }}</div>
                            <div>
                                来源:
                                @if($formData['task_id'] > 0)
                                    <a href="{{ route('admin.articles.index', ['task_id' => $formData['task_id']]) }}" class="font-medium text-blue-700 hover:text-blue-900">
                                        {{ $formData['task_name'] !== '' ? $formData['task_name'] : '#'.$formData['task_id'] }}
                                    </a>
                                @else
                                    手动创建
                                @endif
                            </div>
                        </div>
                    </details>
                @endif

                <div class="admin-panel p-4">
                    <div class="flex gap-2">
                        <a href="{{ $articleBackUrl }}" class="admin-btn-secondary flex-1">{{ __('admin.button.cancel') }}</a>
                        <button type="submit" class="admin-btn-primary flex-1">
                            {{ $isEdit ? __('admin.article_edit.button.save_changes') : __('admin.button.create_article') }}
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </form>
@endsection

@push('styles')
    @vite(['resources/js/article-editor.js'])
    <style>
        .article-editor-tabs {
            display: inline-flex;
            gap: 0.25rem;
            border-radius: 0.625rem;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 0.25rem;
        }
        .article-editor-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            border-radius: 0.5rem;
            border: none;
            background: transparent;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        }
        .article-editor-tab.is-active {
            background: #fff;
            color: #1d4ed8;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }
        .article-content-editor-shell {
            position: relative;
            min-width: 0;
        }
        .article-editor-pane {
            display: none;
        }
        .article-editor-pane.is-active {
            display: block;
        }
        .markdown-preview-pane {
            min-height: 34rem;
            max-height: 34rem;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 1rem 1.125rem;
            background-color: #fff;
            overflow-y: auto;
            overflow-x: auto;
            line-height: 1.65;
            word-break: break-word;
            font-size: 0.9375rem;
        }
        .markdown-preview-pane--visual {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }
        .markdown-preview-pane img {
            display: block;
            max-width: 100%;
            height: auto;
            margin: 0.75rem 0;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
        }
        .markdown-preview-pane :where(h1, h2, h3, h4, h5, h6) {
            font-weight: 650;
            margin: 0.85em 0 0.4em;
            line-height: 1.35;
        }
        .markdown-preview-pane :where(p, ul, ol, pre, blockquote) {
            margin: 0.55em 0;
        }
        .markdown-preview-pane pre {
            padding: 0.75rem 1rem;
            overflow-x: auto;
            border-radius: 0.5rem;
            background: #fff;
            border: 1px solid #e5e7eb;
        }
    </style>
@endpush

@push('scripts')
    <script>
        let articleEditorTab = 'edit';
        let previewRenderTimer = null;

        function resolvePreviewImageUrl(src) {
            const value = String(src || '').trim();
            if (value === '') {
                return '';
            }
            if (/^(https?:|data:|blob:)/i.test(value)) {
                return value;
            }
            if (value.startsWith('//')) {
                return `${window.location.protocol}${value}`;
            }
            if (value.startsWith('/')) {
                return value;
            }
            return `/${value.replace(/^\/+/, '')}`;
        }

        function normalizePreviewImageUrls(html) {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html;
            wrapper.querySelectorAll('img').forEach((img) => {
                const src = resolvePreviewImageUrl(img.getAttribute('src'));
                if (src !== '') {
                    img.setAttribute('src', src);
                }
                img.loading = 'lazy';
                img.decoding = 'async';
                img.onerror = function onPreviewImageError() {
                    this.style.outline = '2px solid #fca5a5';
                    this.alt = this.alt || @json(__($i18nRoot.'.preview.image_load_failed'));
                };
            });

            return wrapper.innerHTML;
        }

        function fallbackMarkdownToHtml(raw) {
            const escaped = String(raw || '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;');
            return escaped
                .replace(/!\[([^\]]*)\]\(([^)]+)\)/g, (_match, alt, url) => (
                    `<img src="${resolvePreviewImageUrl(url)}" alt="${alt}">`
                ))
                .replace(/\n/g, '<br>');
        }

        function renderPreview() {
            const source = document.getElementById('content-textarea');
            const target = document.getElementById('content-preview');
            if (!source || !target) {
                return;
            }
            const raw = String(source.value || '');
            if (raw.trim() === '') {
                target.innerHTML = `<p class="text-sm text-slate-400">${@json(__($i18nRoot.'.preview.empty'))}</p>`;
                return;
            }
            const looksLikeHtml = /^\s*</.test(raw) || /<(h[1-6]|p|div|ul|table|img)\b/i.test(raw);
            let html = '';
            if (looksLikeHtml) {
                html = raw;
            } else if (typeof window.marked !== 'undefined') {
                const sanitizedMarkdown = raw.replace(/^\s{0,3}(?:-{3,}|\*{3,}|_{3,})\s*$/gmu, '');
                html = window.marked.parse(sanitizedMarkdown);
            } else {
                html = fallbackMarkdownToHtml(raw);
            }
            target.innerHTML = normalizePreviewImageUrls(html);
        }

        function schedulePreviewRender() {
            if (previewRenderTimer) {
                window.clearTimeout(previewRenderTimer);
            }
            previewRenderTimer = window.setTimeout(() => {
                previewRenderTimer = null;
                renderPreview();
            }, 80);
        }

        function switchArticleEditorTab(tab) {
            const nextTab = tab === 'preview' ? 'preview' : 'edit';
            articleEditorTab = nextTab;
            const editPane = document.getElementById('article-editor-edit-pane');
            const previewPane = document.getElementById('article-editor-preview-pane');
            const editBtn = document.getElementById('article-tab-edit');
            const previewBtn = document.getElementById('article-tab-preview');
            if (!editPane || !previewPane || !editBtn || !previewBtn) {
                return;
            }
            const isPreview = nextTab === 'preview';
            editPane.classList.toggle('is-active', !isPreview);
            previewPane.classList.toggle('is-active', isPreview);
            previewPane.hidden = !isPreview;
            editBtn.classList.toggle('is-active', !isPreview);
            previewBtn.classList.toggle('is-active', isPreview);
            editBtn.setAttribute('aria-selected', isPreview ? 'false' : 'true');
            previewBtn.setAttribute('aria-selected', isPreview ? 'true' : 'false');
            if (isPreview) {
                renderPreview();
            }
            window.lucide?.createIcons?.();
        }

        const ARTICLE_IMAGE_I18N = {
            uploading: @json(__($i18nRoot.'.message.image_uploading')),
            uploaded: @json(__($i18nRoot.'.message.image_uploaded')),
            uploadFailed: @json(__($i18nRoot.'.message.image_upload_failed')),
        };
        const ARTICLE_IMAGE_UPLOAD_URL = @json(route('admin.articles.upload-image'));

        function setContentImageStatus(message, tone = 'muted') {
            const status = document.getElementById('content-image-status');
            if (!status) {
                return;
            }
            status.textContent = message;
            status.classList.remove('hidden', 'text-slate-500', 'text-emerald-600', 'text-rose-600');
            if (tone === 'success') {
                status.classList.add('text-emerald-600');
            } else if (tone === 'error') {
                status.classList.add('text-rose-600');
            } else {
                status.classList.add('text-slate-500');
            }
        }

        function insertMarkdownAtCursor(textarea, markdown) {
            const start = textarea.selectionStart ?? textarea.value.length;
            const end = textarea.selectionEnd ?? textarea.value.length;
            const before = textarea.value.slice(0, start);
            const after = textarea.value.slice(end);
            const needsLeadingBreak = before.length > 0 && !before.endsWith('\n\n');
            const snippet = (needsLeadingBreak ? '\n\n' : '') + markdown + '\n\n';
            textarea.value = before + snippet + after;
            const cursor = before.length + snippet.length;
            textarea.selectionStart = cursor;
            textarea.selectionEnd = cursor;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        }

        async function uploadArticleImage(file) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const formData = new FormData();
            formData.append('image', file);
            const response = await fetch(ARTICLE_IMAGE_UPLOAD_URL, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: formData,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) {
                throw new Error(data.message || ARTICLE_IMAGE_I18N.uploadFailed);
            }

            return data;
        }

        async function handleArticleImageFiles(files) {
            const textarea = document.getElementById('content-textarea');
            if (!textarea) {
                return;
            }

            const imageFiles = Array.from(files).filter((file) => file && file.type && file.type.startsWith('image/'));
            if (imageFiles.length === 0) {
                return;
            }

            let uploadedCount = 0;
            for (const file of imageFiles) {
                setContentImageStatus(ARTICLE_IMAGE_I18N.uploading, 'muted');
                try {
                    const result = await uploadArticleImage(file);
                    insertMarkdownAtCursor(textarea, String(result.markdown || `![](${result.url})`));
                    uploadedCount += 1;
                } catch (error) {
                    setContentImageStatus(
                        ARTICLE_IMAGE_I18N.uploadFailed.replace('__MESSAGE__', error?.message || ''),
                        'error'
                    );
                    return;
                }
            }

            if (uploadedCount > 0) {
                setContentImageStatus(ARTICLE_IMAGE_I18N.uploaded.replace('__COUNT__', String(uploadedCount)), 'success');
                schedulePreviewRender();
                switchArticleEditorTab('preview');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const textarea = document.getElementById('content-textarea');
            if (!textarea) {
                return;
            }

            renderPreview();
            textarea.addEventListener('input', schedulePreviewRender);

            textarea.addEventListener('paste', (event) => {
                const items = Array.from(event.clipboardData?.items || []);
                const imageFiles = items
                    .filter((item) => item.kind === 'file' && item.type.startsWith('image/'))
                    .map((item) => item.getAsFile())
                    .filter(Boolean);
                if (imageFiles.length === 0) {
                    return;
                }
                event.preventDefault();
                void handleArticleImageFiles(imageFiles);
            });

            textarea.addEventListener('dragover', (event) => {
                const hasFiles = Array.from(event.dataTransfer?.types || []).includes('Files');
                if (hasFiles) {
                    event.preventDefault();
                }
            });

            textarea.addEventListener('drop', (event) => {
                const imageFiles = Array.from(event.dataTransfer?.files || []);
                if (imageFiles.length === 0) {
                    return;
                }
                event.preventDefault();
                void handleArticleImageFiles(imageFiles);
            });
        });
    </script>
@endpush
