@extends('admin.layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <div class="text-xs font-medium uppercase tracking-widest text-blue-600">{{ __('admin.nav.api_tokens') }}</div>
                    <h1 class="mt-1 text-xl font-semibold tracking-tight text-slate-950">{{ __('admin.api_tokens.page_heading') }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.api_tokens.page_subtitle') }}</p>
                </div>
                <button type="button" class="admin-btn-primary" onclick="showApiTokenModal()">
                    <i data-lucide="key-round" class="h-4 w-4"></i>
                    {{ __('admin.api_tokens.button.create') }}
                </button>
            </div>
        </div>

        @if (session('new_api_token'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <i data-lucide="shield-alert" class="mt-0.5 h-5 w-5 shrink-0 text-amber-700"></i>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-amber-900">{{ __('admin.api_tokens.notice.one_time_visible') }}</div>
                        <div class="mt-3 flex flex-col gap-3 lg:flex-row lg:items-center">
                            <code id="new-api-token" class="min-w-0 flex-1 break-all rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm text-amber-900">{{ session('new_api_token') }}</code>
                            <button type="button" id="copy-api-token-btn" class="admin-btn-secondary shrink-0">
                                <i data-lucide="copy" class="h-4 w-4"></i>
                                {{ __('admin.api_tokens.button.copy') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="admin-panel overflow-hidden">
            <div class="admin-panel-header">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.api_tokens.section.list') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.api_tokens.page_subtitle') }}</p>
                </div>
                <button type="button" class="admin-btn-secondary" onclick="showApiTokenModal()">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    {{ __('admin.api_tokens.button.create') }}
                </button>
            </div>

            @if (empty($tokens))
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <i data-lucide="key-round" class="h-6 w-6"></i>
                    </div>
                    <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('admin.api_tokens.empty.no_tokens') }}</div>
                    <button type="button" onclick="showApiTokenModal()" class="admin-btn-primary mt-5">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        {{ __('admin.api_tokens.button.create') }}
                    </button>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>{{ __('admin.api_tokens.column.name') }}</th>
                                <th>Scopes</th>
                                <th>{{ __('admin.api_tokens.column.created_by') }}</th>
                                <th>{{ __('admin.api_tokens.column.last_used') }}</th>
                                <th>{{ __('admin.api_tokens.column.expires_at') }}</th>
                                <th>{{ __('admin.api_tokens.column.status') }}</th>
                                <th class="text-right">{{ __('admin.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tokens as $token)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="text-sm font-semibold text-slate-900">{{ $token['name'] ?? '' }}</td>
                                    <td class="text-sm text-slate-600">
                                        <div class="flex max-w-md flex-wrap gap-1.5">
                                            @foreach (($token['scopes'] ?? []) as $scope)
                                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{{ $scope }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="text-sm text-slate-600">{{ $token['created_by_username'] !== '' ? $token['created_by_username'] : __('admin.api_tokens.value.system') }}</td>
                                    <td class="text-sm text-slate-600">{{ $token['last_used_at'] ?? __('admin.api_tokens.value.never_used') }}</td>
                                    <td class="text-sm text-slate-600">{{ $token['expires_at'] ?? __('admin.api_tokens.value.no_expiry') }}</td>
                                    <td class="text-sm">
                                        @if (($token['status'] ?? 'active') === 'active')
                                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ __('admin.ai_models.status_active') }}</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ __('admin.api_tokens.status.revoked') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right text-sm">
                                        @if (($token['status'] ?? 'active') === 'active')
                                            <form action="{{ route('admin.api-tokens.revoke', ['tokenId' => (int) ($token['id'] ?? 0)]) }}" method="POST" onsubmit="return confirm(@js(__('admin.api_tokens.confirm.revoke')));">
                                                @csrf
                                                <button type="submit" class="font-medium text-red-600 transition hover:text-red-800">{{ __('admin.api_tokens.button.revoke') }}</button>
                                            </form>
                                        @else
                                            <span class="text-slate-400">{{ __('admin.api_tokens.status.revoked') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div id="api-token-modal" class="admin-modal-shell fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="admin-modal-backdrop fixed inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideApiTokenModal()"></div>
        <div class="relative mx-auto my-[7vh] w-11/12 max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <i data-lucide="key-round" class="h-4 w-4"></i>
                    </span>
                    <h3 class="text-base font-semibold text-slate-950">{{ __('admin.api_tokens.section.create') }}</h3>
                </div>
                <button type="button" onclick="hideApiTokenModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <form action="{{ route('admin.api-tokens.store') }}" method="POST" class="space-y-5 px-6 py-5">
                @csrf

                <div>
                    <label for="name" class="admin-label">{{ __('admin.api_tokens.field.name') }}</label>
                    <input id="name" name="name" type="text" required value="{{ old('name') }}" placeholder="{{ __('admin.api_tokens.placeholder.name') }}" class="admin-input mt-1">
                </div>

                <div>
                    <label for="expires_at" class="admin-label">{{ __('admin.api_tokens.field.expires_at') }}</label>
                    <input id="expires_at" name="expires_at" type="datetime-local" value="{{ old('expires_at', $defaultExpiresAtInput ?? '') }}" class="admin-input mt-1">
                    <p class="mt-1 text-xs text-slate-500">{{ __('admin.api_tokens.help.expires_at') }}</p>
                </div>

                <div>
                    <div class="mb-3 text-sm font-semibold text-slate-700">Scopes *</div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($availableScopes as $scope)
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                <input type="checkbox" name="scopes[]" value="{{ $scope }}" @checked(in_array($scope, old('scopes', []), true)) class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span>{{ $scope }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideApiTokenModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-primary">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        {{ __('admin.api_tokens.button.create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function showApiTokenModal() {
            document.getElementById('api-token-modal')?.classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
            window.lucide?.createIcons?.();
        }

        function hideApiTokenModal() {
            document.getElementById('api-token-modal')?.classList.add('hidden');
            document.documentElement.classList.remove('admin-modal-open');
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                hideApiTokenModal();
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const copyButton = document.getElementById('copy-api-token-btn');
            const tokenElement = document.getElementById('new-api-token');
            if (!copyButton || !tokenElement) {
                return;
            }

            async function copyToken(text) {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                    return true;
                }

                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', 'readonly');
                textarea.style.position = 'fixed';
                textarea.style.top = '-9999px';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();

                let copied = false;
                try {
                    copied = document.execCommand('copy');
                } finally {
                    document.body.removeChild(textarea);
                }

                return copied;
            }

            copyButton.addEventListener('click', async function () {
                const tokenText = tokenElement.textContent ? tokenElement.textContent.trim() : '';
                if (tokenText === '') {
                    return;
                }

                try {
                    const copied = await copyToken(tokenText);
                    if (copied && window.AdminUtils && typeof window.AdminUtils.showToast === 'function') {
                        window.AdminUtils.showToast(@json(__('admin.message.copied')), 'success');
                    }
                    if (!copied) {
                        window.prompt('复制失败，请手动复制 Token：', tokenText);
                    }
                } catch (error) {
                    window.prompt('复制失败，请手动复制 Token：', tokenText);
                }
            });
        });
    </script>
@endpush
