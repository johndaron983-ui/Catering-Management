<?php

namespace App\Controller;

use App\Entity\Inventory;
use App\Form\InventoryType;
use App\Repository\InventoryRepository;
use App\Service\ActivityLogService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/inventory')]
#[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
class InventoryController extends AbstractController
{
    public function __construct(
        private InventoryRepository $inventoryRepository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private ActivityLogService $activityLogService
    ) {}

    #[Route('/', name: 'app_inventory_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');
        $category = $request->query->get('category');
        $status = $request->query->get('status', 'active');

        $queryBuilder = $this->inventoryRepository->createQueryBuilder('i');

        if ($search) {
            $queryBuilder->andWhere('i.name LIKE :search OR i.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($category) {
            $queryBuilder->andWhere('i.category = :category')
                ->setParameter('category', $category);
        }

        if ($status) {
            $queryBuilder->andWhere('i.status = :status')
                ->setParameter('status', $status);
        }

        $inventories = $queryBuilder
            ->orderBy('i.name', 'ASC')
            ->getQuery()
            ->getResult();

        // GET STATISTICS
        $stats = $this->inventoryRepository->getInventoryStats();
        $lowStockItems = $this->inventoryRepository->findLowStockItems();
        $outOfStockItems = $this->inventoryRepository->findOutOfStockItems();
        $expiringItems = $this->inventoryRepository->findExpiredOrExpiringItems();

        //  GET CATEGORIES FOR FILTER
        $categories = $this->inventoryRepository->findAllCategories();
        $categoryChoices = [];
        foreach ($categories as $cat) {
            $categoryChoices[$cat['category']] = $cat['category'];
        }

        return $this->render('/admin/inventory/index.html.twig', [
            'inventories' => $inventories,
            'stats' => $stats,
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
            'expiring_items' => $expiringItems,
            'categories' => $categoryChoices,
            'current_search' => $search,
            'current_category' => $category,
            'current_status' => $status,
        ]);
    }

    #[Route('/new', name: 'app_inventory_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $inventory = new Inventory();
        $form = $this->createForm(InventoryType::class, $inventory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Auto-populate name from product if not set
            if (!$inventory->getName() && $inventory->getProduct()) {
                $inventory->setName($inventory->getProduct()->getName());
            }
            
            // Check if item already exists for this product
            $existingInventory = $this->inventoryRepository->findOneByProduct($inventory->getProduct());
            
            if ($existingInventory) {
                // Item exists - update stock quantity instead of creating new row
                $additionalStock = $inventory->getCurrentStock();
                $newStock = $existingInventory->getCurrentStock() + $additionalStock;
                $existingInventory->setCurrentStock($newStock);
                $existingInventory->setLastRestocked(new \DateTime());
                $existingInventory->setUpdatedAt(new \DateTime());
                
                // Update other fields if needed (optional - only if they were changed)
                if ($inventory->getUnitPrice()) {
                    $existingInventory->setUnitPrice($inventory->getUnitPrice());
                }
                if ($inventory->getSupplier()) {
                    $existingInventory->setSupplier($inventory->getSupplier());
                }
                
                $this->entityManager->flush();
                
                // Log inventory restock
                $user = $this->getUser();
                if ($user) {
                    $this->activityLogService->logInventoryStockAdjustment(
                        $user,
                        $existingInventory->getId(),
                        $existingInventory->getName(),
                        $additionalStock,
                        'Stock added to existing item'
                    );
                }
                
                $this->addFlash('success', "Stock updated successfully! Added {$additionalStock} {$existingInventory->getUnit()}(s) to existing item '{$existingInventory->getName()}'. New total: {$newStock} {$existingInventory->getUnit()}(s).");
                return $this->redirectToRoute('app_inventory_index');
            }
            
            // Item does not exist - create new inventory record
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();
            
            if ($imageFile) {
                $imagePath = $this->handleImageUpload($imageFile, $inventory);
                if ($imagePath) {
                    $inventory->setImagePath($imagePath);
                }
            }
            
            $inventory->setUpdatedAt(new \DateTime());
            $inventory->setCreatedBy($this->getUser());
            $this->entityManager->persist($inventory);
            $this->entityManager->flush();

            // Log inventory creation
            $user = $this->getUser();
            if ($user) {
                $this->activityLogService->logInventoryCreation(
                    $user,
                    $inventory->getId(),
                    $inventory->getName(),
                    $inventory->getCategory()
                );
            }

            $this->addFlash('success', 'New inventory item created successfully!');
            return $this->redirectToRoute('app_inventory_index');
        }

        return $this->render('/admin/inventory/new.html.twig', [
            'inventory' => $inventory,
            'form' => $form,
        ]);
    }

    #[Route('/low-stock', name: 'app_inventory_low_stock', methods: ['GET'])]
    public function lowStock(): Response
    {
        $lowStockItems = $this->inventoryRepository->findLowStockItems();
        $outOfStockItems = $this->inventoryRepository->findOutOfStockItems();

        return $this->render('/admin/inventory/low_stock.html.twig', [
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
        ]);
    }

    #[Route('/expiring', name: 'app_inventory_expiring', methods: ['GET'])]
    public function expiring(): Response
    {
        $expiringItems = $this->inventoryRepository->findExpiredOrExpiringItems();

        return $this->render('/admin/inventory/expiring.html.twig', [
            'expiring_items' => $expiringItems,
        ]);
    }

    #[Route('/stats', name: 'app_inventory_stats', methods: ['GET'])]
    public function stats(): Response
    {
        $stats = $this->inventoryRepository->getInventoryStats();
        $lowStockItems = $this->inventoryRepository->findLowStockItems();
        $outOfStockItems = $this->inventoryRepository->findOutOfStockItems();
        $expiringItems = $this->inventoryRepository->findExpiredOrExpiringItems();
        $recentItems = $this->inventoryRepository->findRecentItems();

        return $this->render('/admin/inventory/stats.html.twig', [
            'stats' => $stats,
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
            'expiring_items' => $expiringItems,
            'recent_items' => $recentItems,
        ]);
    }

    #[Route('/{id}', name: 'app_inventory_show', methods: ['GET'])]
    public function show(Inventory $inventory): Response
    {
        return $this->render('/admin/inventory/show.html.twig', [
            'inventory' => $inventory,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_inventory_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Inventory $inventory): Response
    {
        $this->assertCanManageInventory($inventory);

        $form = $this->createForm(InventoryType::class, $inventory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Auto-populate name from product if not set
            if (!$inventory->getName() && $inventory->getProduct()) {
                $inventory->setName($inventory->getProduct()->getName());
            }
            
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();
            
            if ($imageFile) {
                $imagePath = $this->handleImageUpload($imageFile, $inventory);
                if ($imagePath) {
                    $inventory->setImagePath($imagePath);
                }
            }
            
            $inventory->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            // Log inventory update
            $user = $this->getUser();
            if ($user) {
                $this->activityLogService->logInventoryUpdate(
                    $user,
                    $inventory->getId(),
                    $inventory->getName()
                );
            }

            $this->addFlash('success', 'Inventory item updated successfully!');
            return $this->redirectToRoute('app_inventory_index');
        }

        return $this->render('/admin/inventory/edit.html.twig', [
            'inventory' => $inventory,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_inventory_delete', methods: ['POST'])]
    public function delete(Request $request, Inventory $inventory): Response
    {
        $this->assertCanManageInventory($inventory);

        if ($this->isCsrfTokenValid('delete' . $inventory->getId(), $request->request->get('_token'))) {
            // Deletion logging is handled automatically by DoctrineEventListener via postRemove event
            $this->entityManager->remove($inventory);
            $this->entityManager->flush();
            $this->addFlash('success', 'Inventory item deleted successfully!');
        }

        return $this->redirectToRoute('app_inventory_index');
    }

    #[Route('/{id}/restock', name: 'app_inventory_restock', methods: ['POST'])]
    public function restock(Request $request, Inventory $inventory): Response
    {
        $this->assertCanManageInventory($inventory);

        $quantity = (int) $request->request->get('quantity', 0);

        if ($quantity > 0) {
            $newStock = $inventory->getCurrentStock() + $quantity;
            $inventory->setCurrentStock($newStock);
            $inventory->setLastRestocked(new \DateTime());
            $inventory->setUpdatedAt(new \DateTime());

            $this->entityManager->flush();

            // Log inventory restock
            $user = $this->getUser();
            if ($user) {
                $this->activityLogService->logInventoryStockAdjustment(
                    $user,
                    $inventory->getId(),
                    $inventory->getName(),
                    $quantity,
                    'Restock'
                );
            }

            $this->addFlash('success', "Successfully restocked {$quantity} {$inventory->getUnit()}(s) of {$inventory->getName()}!");
        } else {
            $this->addFlash('error', 'Please enter a valid quantity to restock.');
        }

        return $this->redirectToRoute('app_inventory_show', ['id' => $inventory->getId()]);
    }

    #[Route('/{id}/adjust', name: 'app_inventory_adjust', methods: ['POST'])]
    public function adjust(Request $request, Inventory $inventory): Response
    {
        $this->assertCanManageInventory($inventory);

        $quantity = (int) $request->request->get('quantity', 0);
        $reason = $request->request->get('reason', '');

        if ($quantity != 0) {
            $newStock = $inventory->getCurrentStock() + $quantity;
            
            if ($newStock < 0) {
                $this->addFlash('error', 'Cannot adjust stock below zero.');
                return $this->redirectToRoute('app_inventory_show', ['id' => $inventory->getId()]);
            }

            $inventory->setCurrentStock($newStock);
            $inventory->setUpdatedAt(new \DateTime());

            $this->entityManager->flush();

            // Log inventory adjustment
            $user = $this->getUser();
            if ($user) {
                $this->activityLogService->logInventoryStockAdjustment(
                    $user,
                    $inventory->getId(),
                    $inventory->getName(),
                    $quantity,
                    $reason ?: 'Manual adjustment'
                );
            }

            $action = $quantity > 0 ? 'added' : 'removed';
            $this->addFlash('success', "Successfully {$action} " . abs($quantity) . " {$inventory->getUnit()}(s) of {$inventory->getName()}. Reason: {$reason}");
        } else {
            $this->addFlash('error', 'Please enter a valid quantity to adjust.');
        }

        return $this->redirectToRoute('app_inventory_show', ['id' => $inventory->getId()]);
    }

    private function handleImageUpload(UploadedFile $imageFile, Inventory $inventory): ?string
    {
        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

        try {
            $imageFile->move(
                $this->getParameter('kernel.project_dir') . '/public/image',
                $newFilename
            );
            return $newFilename;
        } catch (FileException $e) {
            $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
            return null;
        }
    }

    private function assertCanManageInventory(Inventory $inventory): void
    {
        $user = $this->getUser();
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $owner = $inventory->getCreatedBy();
        if (!$owner || $owner->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You can only manage your own inventory items.');
        }

        if (in_array('ROLE_ADMIN', $owner->getRoles(), true)) {
            throw $this->createAccessDeniedException('You cannot manage admin records.');
        }
    }
}
