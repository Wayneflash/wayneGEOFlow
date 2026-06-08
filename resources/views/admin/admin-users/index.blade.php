@extends('admin.layouts.app')

@section('content')
    @php
        $stats = is_array($stats ?? null) ? $stats : ['total_admins' => 0, 'active_admins' => 0, 'super_admins' => 0];
        $admins = is_array($admins ?? null) ? $admins : [];
        $currentAdminId = isset($currentAdminId) ? (int) $currentAdminId : 0;
    @endphp
<div class="space-y-6">
    <section class="admin-page-hero">
        <div class="admin-page-hero-glow admin-page-hero-glow--left" aria-hidden="true"></div>
        <div class="admin-page-hero-glow admin-page-hero-glow--right" aria-hidden="true"></div>
        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0 flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-sm shadow-blue-600/20">
                    <i data-lucide="shield-user" class="h-5 w-5"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600">{{ __('admin.admin_users.page_eyebrow') }}</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ __('admin.admin_users.page_title') }}</h1>
                    <p class="mt-1 text-sm text-slate-600">{{ __('admin.admin_users.page_subtitle') }}</p>
                </div>
            </div>
        </div>
    </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <i data-lucide="users" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.admin_users.total_admins') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['total_admins'] ?? 0) }}</div>
                    </div>
                </div>
            </div>

            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i data-lucide="badge-check" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.admin_users.active_admins') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['active_admins'] ?? 0) }}</div>
                    </div>
                </div>
            </div>

            <div class="admin-panel p-5">
                <div class="flex items-center gap-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <i data-lucide="shield-check" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-medium text-slate-500">{{ __('admin.admin_users.super_admins') }}</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ (int) ($stats['super_admins'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50/70 px-4 py-3 text-sm text-blue-900">
            <i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"></i>
            <div class="leading-6">{{ __('admin.admin_users.permission_notice') }}</div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-header">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ __('admin.admin_users.list_title') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.admin_users.list_subtitle') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600">
                        <i data-lucide="shield-user" class="h-3.5 w-3.5 text-slate-400"></i>
                        {{ __('admin.admin_users.list_count', ['count' => count($admins)]) }}
                    </span>
                    <a href="{{ route('admin.admin-activity-logs') }}" class="admin-btn-secondary">
                        <i data-lucide="clipboard-list" class="h-4 w-4"></i>
                        {{ __('admin.admin_users.view_logs') }}
                    </a>
                    <button type="button" onclick="showCreateAdminModal()" class="admin-btn-primary">
                        <i data-lucide="user-plus" class="h-4 w-4"></i>
                        {{ __('admin.admin_users.add_admin') }}
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.admin_users.column_account') }}</th>
                            <th>{{ __('admin.admin_users.column_role') }}</th>
                            <th>{{ __('admin.admin_users.column_status') }}</th>
                            <th>{{ __('admin.admin_users.column_expires_at') }}</th>
                            <th>{{ __('admin.admin_users.column_last_login') }}</th>
                            <th>{{ __('admin.admin_users.column_created') }}</th>
                            <th>{{ __('admin.admin_users.column_activity') }}</th>
                            <th class="text-right">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($admins as $admin)
                            <tr class="transition hover:bg-slate-50/70">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600">
                                            {{ mb_substr((string) ($admin['display_name'] !== '' ? $admin['display_name'] : $admin['username']), 0, 1) }}
                                        </span>
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-slate-900">{{ $admin['display_name'] !== '' ? $admin['display_name'] : $admin['username'] }}</div>
                                            <div class="text-xs text-slate-500">{{ $admin['username'] }}</div>
                                            @if ($admin['email'] !== '')
                                                <div class="text-xs text-slate-400">{{ $admin['email'] }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($admin['is_super_admin'])
                                        <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                            <i data-lucide="shield-check" class="h-3 w-3"></i>
                                            {{ __('admin.admin_users.role_super_admin') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                                            <i data-lucide="user" class="h-3 w-3"></i>
                                            {{ __('admin.admin_users.role_admin') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if ($admin['is_expired'])
                                        <span class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                            {{ __('admin.admin_users.status_expired') }}
                                        </span>
                                    @elseif ($admin['status'] === 'active')
                                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            {{ __('admin.admin_users.status_active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                            {{ __('admin.admin_users.status_inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-sm text-slate-600">
                                    {{ $admin['expires_at'] !== '' ? $admin['expires_at'] : __('admin.admin_users.no_expiry') }}
                                </td>
                                <td class="text-sm text-slate-600">
                                    {{ $admin['last_login'] !== '' ? $admin['last_login'] : __('admin.admin_users.none_last_login') }}
                                </td>
                                <td class="text-sm text-slate-600">
                                    <div>{{ $admin['created_at'] }}</div>
                                    <div class="text-xs text-slate-400">
                                        {{ __('admin.admin_users.created_by', ['value' => $admin['creator_username'] !== '' ? $admin['creator_username'] : __('admin.admin_users.system_init')]) }}
                                    </div>
                                </td>
                                <td class="text-sm text-slate-600">
                                    <a href="{{ route('admin.admin-activity-logs', ['admin_id' => $admin['id']]) }}" class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-600 transition hover:border-blue-200 hover:bg-blue-50/60 hover:text-blue-700">
                                        <i data-lucide="scroll-text" class="h-3 w-3"></i>
                                        {{ __('admin.admin_users.activity_count', ['count' => $admin['activity_count']]) }}
                                    </a>
                                </td>
                                <td class="text-right text-sm font-medium">
                                    @if ($admin['is_system_initial'])
                                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-[11px] font-medium text-slate-500">
                                            <i data-lucide="lock" class="h-3 w-3"></i>
                                            {{ __('admin.admin_users.system_initial_badge') }}
                                        </span>
                                    @elseif ($admin['id'] === $currentAdminId)
                                        <button
                                            type="button"
                                            onclick="showEditAdminModal({{ \Illuminate\Support\Js::from($admin) }})"
                                            class="text-blue-600 transition hover:text-blue-800"
                                        >
                                            {{ __('admin.button.edit') }}
                                        </button>
                                    @elseif (! $admin['is_super_admin'])
                                        <div class="inline-flex items-center justify-end gap-3">
                                            <button
                                                type="button"
                                                onclick="showEditAdminModal({{ \Illuminate\Support\Js::from($admin) }})"
                                                class="text-blue-600 transition hover:text-blue-800"
                                            >
                                                {{ __('admin.button.edit') }}
                                            </button>
                                            <form method="POST" action="{{ route('admin.admin-users.toggle-status', ['adminId' => $admin['id']]) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="next_status" value="{{ $admin['status'] === 'active' ? 'inactive' : 'active' }}">
                                                <button type="submit" class="{{ $admin['status'] === 'active' ? 'text-amber-600 hover:text-amber-800' : 'text-emerald-600 hover:text-emerald-800' }} transition">
                                                    {{ $admin['status'] === 'active' ? __('admin.admin_users.action_disable') : __('admin.admin_users.action_enable') }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.admin-users.delete', ['adminId' => $admin['id']]) }}" class="inline" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('admin.admin_users.confirm_delete', ['username' => $admin['username']])) }})">
                                                @csrf
                                                <button type="submit" class="text-red-600 transition hover:text-red-800">
                                                    {{ __('admin.button.delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-slate-300">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-16 text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                        <i data-lucide="user-round-x" class="h-6 w-6"></i>
                                    </div>
                                    <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('admin.admin_users.empty_title') }}</div>
                                    <div class="mt-1 text-sm text-slate-500">{{ __('admin.admin_users.empty_desc') }}</div>
                                    <button type="button" onclick="showCreateAdminModal()" class="admin-btn-primary mt-5">
                                        <i data-lucide="user-plus" class="h-4 w-4"></i>
                                        {{ __('admin.admin_users.add_admin') }}
                                    </button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="admin-activities-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="admin-activities-modal-title">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideAdminActivitiesModal()"></div>
        <div class="relative mx-auto mt-[5vh] flex w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <i data-lucide="scroll-text" class="h-4 w-4"></i>
                    </span>
                    <div class="min-w-0">
                        <h3 id="admin-activities-modal-title" class="truncate text-base font-semibold text-slate-950">{{ __('admin.admin_users.activities_modal_title') }}</h3>
                        <p class="mt-0.5 truncate text-xs text-slate-500" data-admin-activities-subtitle></p>
                    </div>
                </div>
                <button type="button" onclick="hideAdminActivitiesModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <div class="px-6 py-5">
                <div class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-2.5 text-xs text-slate-500">
                    <div class="flex items-center gap-2">
                        <i data-lucide="hash" class="h-3.5 w-3.5 text-slate-400"></i>
                        <span data-admin-activities-total>—</span>
                    </div>
                    <a href="{{ route('admin.admin-activity-logs') }}" class="text-blue-600 transition hover:text-blue-800">{{ __('admin.admin_users.view_all_logs') }}</a>
                </div>
                <div class="max-h-[60vh] space-y-2 overflow-y-auto pr-1" data-admin-activities-list>
                    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/40 px-4 py-10 text-center text-sm text-slate-400" data-admin-activities-loading>
                        <i data-lucide="loader" class="mx-auto mb-2 h-5 w-5 animate-spin text-slate-400"></i>
                        {{ __('admin.admin_users.activities_loading') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="create-admin-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="create-admin-modal-title">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideCreateAdminModal()"></div>
        <div class="relative mx-auto mt-[6vh] flex w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <i data-lucide="user-plus" class="h-4 w-4"></i>
                    </span>
                    <h3 id="create-admin-modal-title" class="text-base font-semibold text-slate-950">{{ __('admin.admin_users.modal_create') }}</h3>
                </div>
                <button type="button" onclick="hideCreateAdminModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <form id="create-admin-form" method="POST" action="{{ route('admin.admin-users.store') }}" enctype="multipart/form-data" class="px-6 py-5 space-y-4" novalidate>
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="admin-field">
                        <label for="username" class="admin-label">{{ __('admin.admin_users.field_username') }}</label>
                        <input type="text" name="username" id="username" required data-validate="username" class="admin-input" placeholder="{{ __('admin.admin_users.placeholder_username') }}" value="{{ old('username') }}">
                        <p class="admin-field-error hidden" data-field-error="username"><i data-lucide="alert-circle"></i><span data-field-error-text></span></p>
                    </div>

                    <div class="admin-field">
                        <label for="display_name" class="admin-label">{{ __('admin.admin_users.field_display_name') }}</label>
                        <input type="text" name="display_name" id="display_name" class="admin-input" placeholder="{{ __('admin.admin_users.placeholder_display_name') }}" value="{{ old('display_name') }}">
                    </div>
                </div>

                <div class="admin-field">
                    <label for="email" class="admin-label">{{ __('admin.admin_users.field_email') }}</label>
                    <input type="email" name="email" id="email" data-validate="email" class="admin-input" placeholder="{{ __('admin.admin_users.placeholder_email') }}" value="{{ old('email') }}">
                    <p class="admin-field-error hidden" data-field-error="email"><i data-lucide="alert-circle"></i><span data-field-error-text></span></p>
                </div>

                <div class="admin-field">
                    <label for="expires_at" class="admin-label">{{ __('admin.admin_users.field_expires_at') }}</label>
                    <input type="date" name="expires_at" id="expires_at" class="admin-input" value="{{ old('expires_at', now()->addYear()->format('Y-m-d')) }}">
                    <p class="mt-1 text-xs text-slate-500">{{ __('admin.admin_users.expires_at_help') }}</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-start gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-400">
                            <img src="" alt="{{ __('admin.admin_users.tenant_logo_alt') }}" class="hidden h-full w-full object-contain p-2" data-tenant-logo-preview>
                            <i data-lucide="image-plus" class="h-6 w-6" data-tenant-logo-placeholder></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <label for="tenant_logo" class="admin-label">{{ __('admin.admin_users.field_tenant_logo') }}</label>
                            <input
                                type="file"
                                name="tenant_logo"
                                id="tenant_logo"
                                accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                data-tenant-logo-input
                            >
                            <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('admin.admin_users.tenant_logo_hint') }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="admin-field">
                        <label for="password" class="admin-label">{{ __('admin.admin_users.field_password') }}</label>
                        <input type="password" name="password" id="password" required data-validate="password" class="admin-input">
                        <p class="admin-field-error hidden" data-field-error="password"><i data-lucide="alert-circle"></i><span data-field-error-text></span></p>
                    </div>
                    <div class="admin-field">
                        <label for="confirm_password" class="admin-label">{{ __('admin.admin_users.field_confirm_password') }}</label>
                        <input type="password" name="confirm_password" id="confirm_password" required data-validate="confirm_password" class="admin-input">
                        <p class="admin-field-error hidden" data-field-error="confirm_password"><i data-lucide="alert-circle"></i><span data-field-error-text></span></p>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">
                    {{ __('admin.admin_users.create_help') }}
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideCreateAdminModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-primary">
                        <i data-lucide="user-plus" class="h-4 w-4"></i>
                        {{ __('admin.admin_users.create_admin_submit') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="edit-admin-modal" class="admin-modal-shell fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="edit-admin-modal-title">
        <div class="admin-modal-backdrop absolute inset-0 bg-slate-900/45 backdrop-blur-sm" onclick="hideEditAdminModal()"></div>
        <div class="relative mx-auto mt-[6vh] flex w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <i data-lucide="user-cog" class="h-4 w-4"></i>
                    </span>
                    <h3 id="edit-admin-modal-title" class="text-base font-semibold text-slate-950">{{ __('admin.admin_users.modal_edit') }}</h3>
                </div>
                <button type="button" onclick="hideEditAdminModal()" class="admin-icon-btn" aria-label="{{ __('admin.common.close') }}">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <form id="edit-admin-form" method="POST" action="#" class="px-6 py-5 space-y-4" novalidate>
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="admin-field">
                        <label for="edit_username" class="admin-label">{{ __('admin.admin_users.field_username') }}</label>
                        <input type="text" name="username" id="edit_username" required data-validate="username" class="admin-input">
                        <p class="admin-field-error hidden" data-field-error="username"><i data-lucide="alert-circle"></i><span data-field-error-text></span></p>
                    </div>

                    <div class="admin-field">
                        <label for="edit_display_name" class="admin-label">{{ __('admin.admin_users.field_display_name') }}</label>
                        <input type="text" name="display_name" id="edit_display_name" class="admin-input">
                    </div>
                </div>

                <div class="admin-field">
                    <label for="edit_email" class="admin-label">{{ __('admin.admin_users.field_email') }}</label>
                    <input type="email" name="email" id="edit_email" data-validate="email" class="admin-input">
                    <p class="admin-field-error hidden" data-field-error="email"><i data-lucide="alert-circle"></i><span data-field-error-text></span></p>
                </div>

                <div class="admin-field">
                    <label for="edit_status" class="admin-label">{{ __('admin.admin_users.column_status') }}</label>
                    <input type="hidden" name="status" id="edit_status_hidden" disabled>
                    <select name="status" id="edit_status" required class="admin-input">
                        <option value="active">{{ __('admin.admin_users.status_active') }}</option>
                        <option value="inactive">{{ __('admin.admin_users.status_inactive') }}</option>
                    </select>
                </div>

                <div class="admin-field">
                    <label for="edit_expires_at" class="admin-label">{{ __('admin.admin_users.field_expires_at') }}</label>
                    <input type="date" name="expires_at" id="edit_expires_at" class="admin-input">
                    <p class="mt-1 text-xs text-slate-500">{{ __('admin.admin_users.edit_expires_at_help') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="admin-field">
                        <label for="edit_password" class="admin-label">{{ __('admin.admin_users.field_new_password') }}</label>
                        <input type="password" name="password" id="edit_password" data-validate="password" class="admin-input">
                        <p class="admin-field-error hidden" data-field-error="password"><i data-lucide="alert-circle"></i><span data-field-error-text></span></p>
                    </div>
                    <div class="admin-field">
                        <label for="edit_confirm_password" class="admin-label">{{ __('admin.admin_users.field_confirm_new_password') }}</label>
                        <input type="password" name="confirm_password" id="edit_confirm_password" data-validate="confirm_password" class="admin-input">
                        <p class="admin-field-error hidden" data-field-error="confirm_password"><i data-lucide="alert-circle"></i><span data-field-error-text></span></p>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">
                    {{ __('admin.admin_users.edit_help') }}
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" onclick="hideEditAdminModal()" class="admin-btn-secondary">{{ __('admin.button.cancel') }}</button>
                    <button type="submit" class="admin-btn-primary">
                        <i data-lucide="check" class="h-4 w-4"></i>
                        {{ __('admin.admin_users.update_admin_submit') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const updateAdminRouteTemplate = @json(route('admin.admin-users.update', ['adminId' => '__ADMIN_ID__']));
        const currentAdminId = @json($currentAdminId);

        const formMessages = {
            username: @json(__('admin.admin_users.error.username_invalid')),
            email: @json(__('admin.admin_users.error.email_invalid')),
            password: @json(__('admin.admin_users.error.password_too_short')),
            confirm_password: @json(__('admin.admin_users.error.password_mismatch')),
            username_required: @json(__('admin.admin_users.error.username_required')),
        };

        function showFieldError(form, field, message) {
            const input = form.querySelector('[name="' + field + '"]');
            const errorEl = form.querySelector('[data-field-error="' + field + '"]');
            if (!errorEl) return;
            if (input) {
                input.classList.add('admin-input--error');
                input.setAttribute('aria-invalid', 'true');
            }
            errorEl.classList.remove('hidden');
            const textEl = errorEl.querySelector('[data-field-error-text]');
            if (textEl) textEl.textContent = message;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        function clearFieldErrors(form) {
            form.querySelectorAll('.admin-input--error').forEach((el) => {
                el.classList.remove('admin-input--error');
                el.removeAttribute('aria-invalid');
            });
            form.querySelectorAll('[data-field-error]').forEach((el) => el.classList.add('hidden'));
        }

        function validateAdminForm(form) {
            let valid = true;
            const username = form.querySelector('[name="username"]')?.value.trim() || '';
            const email = form.querySelector('[name="email"]')?.value.trim() || '';
            const password = form.querySelector('[name="password"]')?.value || '';
            const confirm = form.querySelector('[name="confirm_password"]')?.value || '';
            const isCreate = form.id === 'create-admin-form';

            if (username === '') {
                showFieldError(form, 'username', formMessages.username_required);
                valid = false;
            } else if (!/^[A-Za-z0-9_.-]{3,50}$/.test(username)) {
                showFieldError(form, 'username', formMessages.username);
                valid = false;
            }

            if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showFieldError(form, 'email', formMessages.email);
                valid = false;
            }

            if (isCreate || password !== '') {
                if (password.length < 8) {
                    showFieldError(form, 'password', formMessages.password);
                    valid = false;
                }
                if (confirm !== password) {
                    showFieldError(form, 'confirm_password', formMessages.confirm_password);
                    valid = false;
                }
            }
            return valid;
        }

        document.getElementById('create-admin-form')?.addEventListener('submit', (event) => {
            clearFieldErrors(event.target);
            if (!validateAdminForm(event.target)) {
                event.preventDefault();
            }
        });
        document.getElementById('edit-admin-form')?.addEventListener('submit', (event) => {
            clearFieldErrors(event.target);
            if (!validateAdminForm(event.target)) {
                event.preventDefault();
            }
        });

        @if ($errors->any())
            (() => {
                const form = document.getElementById('create-admin-form') || document.getElementById('edit-admin-form');
                if (form) {
                    clearFieldErrors(form);
                    @foreach ($errors->messages() as $field => $messages)
                        showFieldError(form, @json($field), @json($messages[0]));
                    @endforeach
                    const modal = form.closest('.admin-modal-shell');
                    if (modal) {
                        modal.classList.remove('hidden');
                        document.documentElement.classList.add('admin-modal-open');
                    }
                }
            })();
        @endif

        function showCreateAdminModal() {
            const form = document.getElementById('create-admin-form');
            if (form) clearFieldErrors(form);
            document.getElementById('create-admin-modal').classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
        }

        function hideCreateAdminModal() {
            document.getElementById('create-admin-modal').classList.add('hidden');
            document.documentElement.classList.remove('admin-modal-open');
        }

        document.querySelector('[data-tenant-logo-input]')?.addEventListener('change', (event) => {
            const file = event.target.files?.[0];
            const preview = document.querySelector('[data-tenant-logo-preview]');
            const placeholder = document.querySelector('[data-tenant-logo-placeholder]');

            if (!preview || !placeholder) {
                return;
            }

            if (!file) {
                preview.classList.add('hidden');
                preview.removeAttribute('src');
                placeholder.classList.remove('hidden');
                return;
            }

            preview.src = URL.createObjectURL(file);
            preview.onload = () => URL.revokeObjectURL(preview.src);
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
        });

        function showEditAdminModal(admin) {
            const form = document.getElementById('edit-admin-form');
            const statusSelect = document.getElementById('edit_status');
            const statusHidden = document.getElementById('edit_status_hidden');
            const isSelf = Number(admin.id) === Number(currentAdminId);
            form.action = updateAdminRouteTemplate.replace('__ADMIN_ID__', admin.id);
            document.getElementById('edit_username').value = admin.username || '';
            document.getElementById('edit_display_name').value = admin.display_name || '';
            document.getElementById('edit_email').value = admin.email || '';
            document.getElementById('edit_expires_at').value = admin.expires_at_input || '';
            statusSelect.value = admin.status || 'active';
            statusSelect.disabled = isSelf;
            statusHidden.disabled = !isSelf;
            statusHidden.value = admin.status || 'active';
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_confirm_password').value = '';
            document.getElementById('edit-admin-modal').classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');
        }

        function hideEditAdminModal() {
            document.getElementById('edit-admin-modal').classList.add('hidden');
            document.documentElement.classList.remove('admin-modal-open');
        }

        const adminActivitiesBaseUrl = @js(route('admin.admin-users.activities', ['adminId' => 0]));
        const adminActivitiesUrlTemplate = adminActivitiesBaseUrl.replace(/\/0$/, '/__ID__');

        function showAdminActivities(adminId, displayName) {
            const modal = document.getElementById('admin-activities-modal');
            const subtitle = document.querySelector('[data-admin-activities-subtitle]');
            const totalEl = document.querySelector('[data-admin-activities-total]');
            const listEl = document.querySelector('[data-admin-activities-list]');

            subtitle.textContent = displayName || '';
            totalEl.textContent = '...';
            listEl.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/40 px-4 py-10 text-center text-sm text-slate-400"><i data-lucide="loader" class="mx-auto mb-2 h-5 w-5 animate-spin text-slate-400"></i>{{ __('admin.admin_users.activities_loading') }}</div>';
            if (typeof lucide !== 'undefined') { lucide.createIcons(); }

            modal.classList.remove('hidden');
            document.documentElement.classList.add('admin-modal-open');

            fetch(adminActivitiesUrlTemplate.replace('__ID__', adminId), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then((response) => {
                    if (response.ok) return response.json();
                    return response.text().then((text) => Promise.reject({ status: response.status, text, url: response.url }));
                })
                .then((data) => {
                    totalEl.textContent = @js(__('admin.admin_users.activities_total')) + ' ' + (data.total || 0);
                    renderAdminActivities(data.activities || []);
                })
                .catch((err) => {
                    console.error('activities load failed', err);
                    const detail = err && (err.status || err.text) ? (' (HTTP ' + (err.status || '?') + ')') : '';
                    listEl.innerHTML = '<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-8 text-center text-sm text-rose-600">{{ __('admin.admin_users.activities_load_error') }}' + detail + '</div>';
                });
        }

        function renderAdminActivities(activities) {
            const listEl = document.querySelector('[data-admin-activities-list]');
            if (!activities.length) {
                listEl.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/40 px-4 py-10 text-center text-sm text-slate-400">{{ __('admin.admin_users.activities_empty') }}</div>';
                return;
            }
            listEl.innerHTML = activities.map((item) => {
                const time = item.created_at || '';
                const human = item.created_at_human || '';
                const page = item.page ? '<div class="mt-0.5 truncate text-[11px] text-slate-500">' + escapeHtml(item.page) + '</div>' : '';
                const details = item.details ? '<div class="mt-1 line-clamp-2 text-[12px] text-slate-500">' + escapeHtml(item.details) + '</div>' : '';
                const ip = item.ip ? '<span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-500">' + escapeHtml(item.ip) + '</span>' : '';
                const method = item.method ? '<span class="rounded-md bg-blue-50 px-1.5 py-0.5 text-[11px] font-semibold text-blue-600">' + escapeHtml(item.method) + '</span>' : '';
                return '<div class="rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:border-blue-200 hover:shadow-sm">' +
                    '<div class="flex items-center justify-between gap-2">' +
                        '<div class="flex min-w-0 items-center gap-2">' + method +
                            '<span class="truncate text-[13px] font-semibold text-slate-800">' + escapeHtml(item.action || '-') + '</span>' +
                        '</div>' +
                        '<span class="shrink-0 text-[11px] text-slate-400" title="' + escapeHtml(time) + '">' + escapeHtml(human) + '</span>' +
                    '</div>' +
                    page + details +
                    (ip ? '<div class="mt-1.5 flex justify-end">' + ip + '</div>' : '') +
                '</div>';
            }).join('');
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
        }

        function hideAdminActivitiesModal() {
            document.getElementById('admin-activities-modal').classList.add('hidden');
            document.documentElement.classList.remove('admin-modal-open');
        }

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            hideCreateAdminModal();
            hideEditAdminModal();
        });
    </script>
@endpush
