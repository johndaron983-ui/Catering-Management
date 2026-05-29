<?php

namespace App\Controller;

use App\Service\AdminRealtimeSnapshotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/realtime')]
#[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
final class AdminRealtimeController extends AbstractController
{
    public function __construct(
        private AdminRealtimeSnapshotService $snapshotService,
    ) {
    }

    #[Route('/module/{module}', name: 'app_admin_realtime_module', methods: ['GET'])]
    public function moduleSnapshot(string $module, Request $request): JsonResponse
    {
        if (!$this->snapshotService->isAllowedModule($module)) {
            return new JsonResponse(['success' => false, 'error' => 'Unknown module'], Response::HTTP_NOT_FOUND);
        }

        if ($this->snapshotService->requiresAdmin($module) && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['success' => false, 'error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $data = $this->snapshotService->build($module, $request);

        $partial = match ($module) {
            AdminRealtimeSnapshotService::MODULE_INVENTORY => 'admin/realtime/_inventory_rows.html.twig',
            AdminRealtimeSnapshotService::MODULE_PRODUCTS => 'admin/realtime/_products_rows.html.twig',
            AdminRealtimeSnapshotService::MODULE_SUPPLIERS => 'admin/realtime/_suppliers_rows.html.twig',
            AdminRealtimeSnapshotService::MODULE_USERS => 'admin/realtime/_users_rows.html.twig',
            AdminRealtimeSnapshotService::MODULE_ACTIVITY_LOGS => 'admin/realtime/_activity_logs_rows.html.twig',
            default => null,
        };

        $rowsHtml = $this->renderView($partial, $data);

        $payload = [
            'success' => true,
            'module' => $module,
            'rowsHtml' => $rowsHtml,
            'rowCount' => $data['row_count'] ?? 0,
        ];

        if ($module === AdminRealtimeSnapshotService::MODULE_INVENTORY) {
            $payload['latestId'] = $data['latestId'];
            $payload['stats'] = $data['stats'];
            $payload['lowStockCount'] = $data['low_stock_count'];
            $payload['expiringCount'] = $data['expiring_count'];
        } elseif ($module === AdminRealtimeSnapshotService::MODULE_ACTIVITY_LOGS) {
            $payload['latestLogId'] = $data['latestLogId'];
            $payload['totalCount'] = $data['total_count'];
        } else {
            $payload['latestId'] = $data['latestId'];
        }

        if ($module === AdminRealtimeSnapshotService::MODULE_USERS) {
            $payload['totalUsers'] = $data['total_users'];
        }

        return new JsonResponse($payload);
    }

    #[Route('/sidebar', name: 'app_admin_realtime_sidebar', methods: ['GET'])]
    public function sidebar(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'counts' => $this->snapshotService->sidebarCounts(),
        ]);
    }
}
