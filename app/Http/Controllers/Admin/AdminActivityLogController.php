<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Support\AdminWeb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * 管理员操作日志控制器（超级管理员专用）。
 *
 * 对齐 bak/admin/admin-activity-logs.php 核心能力：
 * 1. 按管理员和关键词筛选日志；
 * 2. 展示日志统计（总量、今日、近7天活跃管理员）；
 * 3. 列表分页与详情预览。
 */
class AdminActivityLogController extends Controller
{
    /**
     * 操作日志列表页。
     */
    public function index(Request $request): View
    {
        $filters = $this->buildFilters($request);
        $logs = $this->queryLogs($filters);

        return view('admin.admin-activity-logs.index', [
            'pageTitle' => __('admin.activity_logs.page_title'),
            'activeMenu' => 'admin_users',
            'adminSiteName' => AdminWeb::siteName(),
            'filters' => $filters,
            'logs' => $logs,
            'admins' => $this->loadAdmins(),
            'stats' => $this->loadStats(),
        ]);
    }

    /**
     * 弹窗用：按管理员 ID 返回最近的活动记录 JSON。
     */
    public function activitiesForAdmin(int $adminId, Request $request): JsonResponse
    {
        $limit = min(50, max(5, (int) $request->query('limit', 20)));
        $admin = Admin::query()->find($adminId);
        if (! $admin) {
            return response()->json(['activities' => [], 'admin' => null], 404);
        }

        $logs = AdminActivityLog::query()
            ->where('admin_id', (int) $admin->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'action', 'request_method', 'page', 'target_type', 'target_id', 'ip_address', 'details', 'created_at']);

        $activities = $logs->map(static function (AdminActivityLog $log): array {
            return [
                'id' => (int) $log->id,
                'action' => (string) ($log->action ?? ''),
                'method' => (string) ($log->request_method ?? ''),
                'page' => (string) ($log->page ?? ''),
                'target' => trim(((string) ($log->target_type ?? '')).($log->target_id !== null ? '#'.$log->target_id : '')),
                'ip' => (string) ($log->ip_address ?? ''),
                'details' => (string) ($log->details ?? ''),
                'created_at' => $log->created_at?->format('Y-m-d H:i:s') ?? '',
                'created_at_human' => $log->created_at?->diffForHumans() ?? '',
            ];
        })->all();

        return response()->json([
            'admin' => [
                'id' => (int) $admin->id,
                'username' => (string) $admin->username,
                'display_name' => (string) ($admin->display_name ?? ''),
                'role' => (string) $admin->role,
            ],
            'activities' => $activities,
            'total' => AdminActivityLog::query()->where('admin_id', (int) $admin->id)->count(),
        ]);
    }

    /**
     * @return array{search:string,admin_id:int}
     */
    private function buildFilters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'admin_id' => max(0, (int) $request->query('admin_id', 0)),
        ];
    }

    /**
     * @param  array{search:string,admin_id:int}  $filters
     */
    private function queryLogs(array $filters): LengthAwarePaginator
    {
        $query = AdminActivityLog::query()
            ->select([
                'id',
                'admin_id',
                'admin_username',
                'admin_role',
                'action',
                'request_method',
                'page',
                'target_type',
                'target_id',
                'ip_address',
                'details',
                'created_at',
            ])
            ->with(['admin:id,username,display_name,role'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($filters['admin_id'] > 0) {
            $query->where('admin_id', $filters['admin_id']);
        }

        if ($filters['search'] !== '') {
            $keyword = '%'.$filters['search'].'%';
            $query->where(static function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('admin_username', 'like', $keyword)
                    ->orWhere('action', 'like', $keyword)
                    ->orWhere('page', 'like', $keyword)
                    ->orWhere('details', 'like', $keyword);
            });
        }

        return $query->paginate(50)->withQueryString();
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    private function loadAdmins(): array
    {
        return Admin::query()
            ->select(['id', 'username', 'display_name', 'role'])
            ->orderByRaw("CASE WHEN LOWER(COALESCE(role, '')) IN ('super_admin', 'superadmin') THEN 0 ELSE 1 END")
            ->orderBy('username')
            ->get()
            ->map(static function (Admin $admin): array {
                $displayName = trim((string) ($admin->display_name ?? ''));
                $username = (string) ($admin->username ?? '');

                return [
                    'id' => (int) $admin->id,
                    'name' => $displayName !== '' ? $displayName.' / '.$username : $username,
                ];
            })
            ->all();
    }

    /**
     * @return array{total_logs:int,today_logs:int,active_admins:int}
     */
    private function loadStats(): array
    {
        return [
            'total_logs' => AdminActivityLog::query()->count(),
            'today_logs' => AdminActivityLog::query()->whereDate('created_at', Carbon::today())->count(),
            'active_admins' => AdminActivityLog::query()
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->distinct('admin_id')
                ->count('admin_id'),
        ];
    }
}
