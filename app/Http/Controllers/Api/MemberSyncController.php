<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReconcileMembersRequest;
use App\Http\Requests\Api\SyncMemberRequest;
use App\Services\MemberSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MemberSyncController extends Controller
{
    public function __construct(
        private readonly MemberSyncService $syncService
    ) {}

    public function upsert(SyncMemberRequest $request, int $lid_id): JsonResponse
    {
        $result = $this->syncService->upsertMember($lid_id, $request->validated());

        $statusCode = $result['status'] === MemberSyncService::STATUS_CREATED ? 201 : 200;

        Log::info("Sync API: upsert member {$lid_id}", ['status' => $result['status'], 'relatie_id' => $result['relatie_id'] ?? null]);

        return response()->json($result, $statusCode);
    }

    public function destroy(int $lid_id): JsonResponse
    {
        $result = $this->syncService->deactivateMember($lid_id);

        if ($result['status'] === MemberSyncService::STATUS_NOT_FOUND) {
            Log::notice("Sync API: deactivate member {$lid_id} — not found");

            return response()->json(['message' => 'Member not found.'], 404);
        }

        Log::info("Sync API: deactivate member {$lid_id}", ['status' => $result['status'], 'relatie_id' => $result['relatie_id'] ?? null]);

        return response()->json($result);
    }

    public function reconcile(ReconcileMembersRequest $request): JsonResponse
    {
        try {
            $result = $this->syncService->reconcileMembers($request->validated()['active_lid_ids']);
        } catch (\RuntimeException $e) {
            Log::warning('Sync API: reconcile aborted', ['message' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 409);
        }

        Log::info('Sync API: reconcile completed', ['deactivated_count' => $result['deactivated_count']]);

        return response()->json($result);
    }
}
