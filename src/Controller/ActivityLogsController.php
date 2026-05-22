<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/activity-logs')]
#[IsGranted('ROLE_ADMIN')]
final class ActivityLogsController extends AbstractController
{
    #[Route('/', name: 'app_activity_logs_index', methods: ['GET'])]
    public function index(Request $request, ActivityLogRepository $activityLogRepository): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Get filter parameters
        $username = $request->query->get('username');
        $action = $request->query->get('action');
        $recordType = $request->query->get('record_type');
        $role = $request->query->get('role');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        // Build query
        $qb = $activityLogRepository->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if ($username) {
            $qb->andWhere('a.username LIKE :username')
                ->setParameter('username', '%' . $username . '%');
        }

        if ($action) {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $action);
        }

        if ($recordType) {
            $qb->andWhere('a.recordType = :recordType')
                ->setParameter('recordType', $recordType);
        }

        if ($role) {
            $qb->andWhere('a.role = :role')
                ->setParameter('role', $role);
        }

        if ($startDate) {
            $qb->andWhere('a.createdAt >= :startDate')
                ->setParameter('startDate', new \DateTime($startDate));
        }

        if ($endDate) {
            $qb->andWhere('a.createdAt <= :endDate')
                ->setParameter('endDate', new \DateTime($endDate . ' 23:59:59'));
        }

        // Get total count for pagination
        $totalCount = count($qb->getQuery()->getResult());
        $totalPages = ceil($totalCount / $limit);

        // Get paginated results
        $logs = $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Get available filter options
        $availableActions = $activityLogRepository->findDistinctActions();
        $availableRecordTypes = $activityLogRepository->findDistinctRecordTypes();
        $availableRoles = $activityLogRepository->findDistinctRoles();
        $availableUsernames = $activityLogRepository->findDistinctUsernames();

        return $this->render('admin/activity_logs/index.html.twig', [
            'logs' => $logs,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
            'available_actions' => $availableActions,
            'available_record_types' => $availableRecordTypes,
            'available_roles' => $availableRoles,
            'available_usernames' => $availableUsernames,
            'current_username' => $username,
            'current_action' => $action,
            'current_record_type' => $recordType,
            'current_role' => $role,
            'current_start_date' => $startDate,
            'current_end_date' => $endDate,
        ]);
    }

    #[Route('/{id}', name: 'app_activity_logs_show', methods: ['GET'])]
    public function show(int $id, ActivityLogRepository $activityLogRepository): Response
    {
        $log = $activityLogRepository->find($id);
        
        if (!$log) {
            throw $this->createNotFoundException('Activity log not found');
        }

        // Get related logs for context
        if ($log->getRecordType() && $log->getRecordId()) {
            $relatedLogs = $activityLogRepository->findBy(
                ['recordType' => $log->getRecordType(), 'recordId' => $log->getRecordId()],
                ['createdAt' => 'DESC'],
                10
            );
        } else {
            $relatedLogs = [];
        }

        return $this->render('admin/activity_logs/show.html.twig', [
            'log' => $log,
            'related_logs' => $relatedLogs,
        ]);
    }

    #[Route('/user/{username}', name: 'app_activity_logs_by_user', methods: ['GET'])]
    public function byUser(Request $request, string $username, ActivityLogRepository $activityLogRepository): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $qb = $activityLogRepository->createQueryBuilder('a')
            ->andWhere('a.username = :username')
            ->setParameter('username', $username)
            ->orderBy('a.createdAt', 'DESC');

        $totalCount = count($qb->getQuery()->getResult());
        $totalPages = ceil($totalCount / $limit);

        $logs = $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('admin/activity_logs/by_user.html.twig', [
            'logs' => $logs,
            'username' => $username,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
        ]);
    }

    #[Route('/record/{recordType}/{recordId}', name: 'app_activity_logs_by_record', methods: ['GET'])]
    public function byRecord(
        Request $request,
        string $recordType,
        int $recordId,
        ActivityLogRepository $activityLogRepository
    ): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $qb = $activityLogRepository->createQueryBuilder('a')
            ->andWhere('a.recordType = :recordType')
            ->andWhere('a.recordId = :recordId')
            ->setParameter('recordType', $recordType)
            ->setParameter('recordId', $recordId)
            ->orderBy('a.createdAt', 'DESC');

        $totalCount = count($qb->getQuery()->getResult());
        $totalPages = ceil($totalCount / $limit);

        $logs = $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('admin/activity_logs/by_record.html.twig', [
            'logs' => $logs,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_count' => $totalCount,
        ]);
    }
}
