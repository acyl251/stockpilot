<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    private function authorize(): bool
    {
        return in_array(app('current_user')->role, ['admin', 'super_admin', 'manager']);
    }

    public function index(Request $request): JsonResponse
    {
        if (! $this->authorize()) {
            return $this->errorResponse('Accès refusé.', 403);
        }

        $query = ActivityLog::with('user:id,nom,prenom,role')
            ->when($request->debut,   fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->fin,     fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->module,  fn($q, $m) => $q->where('module', $m))
            ->when($request->user_id, fn($q, $u) => $q->where('user_id', $u))
            ->when($request->search,  fn($q, $s) => $q->where('description', 'like', "%$s%"))
            ->latest('created_at');

        $paginator = $query->paginate(20);

        // Summary stats
        $orgId = app('current_organisation_id');

        $todayCount = ActivityLog::whereDate('created_at', today())->count();

        $lastLog = ActivityLog::with('user:id,nom,prenom')
            ->latest('created_at')->first();

        $mostActive = ActivityLog::whereDate('created_at', today())
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('user_id')
            ->orderByDesc('cnt')
            ->with('user:id,nom,prenom')
            ->first();

        $users = User::withoutGlobalScopes()
            ->where('organisation_id', $orgId)
            ->select('id', 'nom', 'prenom')
            ->orderBy('nom')
            ->get();

        $payload                 = $paginator->toArray();
        $payload['summary']      = [
            'today_count'  => $todayCount,
            'last_action'  => $lastLog ? [
                'description' => $lastLog->description,
                'created_at'  => $lastLog->created_at,
                'user'        => $lastLog->user ? $lastLog->user->prenom . ' ' . $lastLog->user->nom : null,
            ] : null,
            'most_active'  => $mostActive ? [
                'count' => $mostActive->cnt,
                'user'  => $mostActive->user ? $mostActive->user->prenom . ' ' . $mostActive->user->nom : null,
            ] : null,
        ];
        $payload['users']        = $users;

        return response()->json($payload);
    }

    public function export(Request $request): StreamedResponse
    {
        if (! $this->authorize()) {
            abort(403, 'Accès refusé.');
        }

        $logs = ActivityLog::with('user:id,nom,prenom')
            ->when($request->debut,   fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->fin,     fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->module,  fn($q, $m) => $q->where('module', $m))
            ->when($request->user_id, fn($q, $u) => $q->where('user_id', $u))
            ->when($request->search,  fn($q, $s) => $q->where('description', 'like', "%$s%"))
            ->latest('created_at')
            ->get();

        $filename = 'activite_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Utilisateur', 'Module', 'Action', 'Description'], ';');

            foreach ($logs as $log) {
                fputcsv($out, [
                    optional($log->created_at)->format('d/m/Y H:i'),
                    $log->user ? $log->user->prenom . ' ' . $log->user->nom : '',
                    $log->module,
                    $log->action,
                    $log->description,
                ], ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
