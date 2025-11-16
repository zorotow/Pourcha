<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CatalogItemRepository;
use App\Repository\SupplierRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/catalog')]
#[IsGranted('ROLE_USER')]
class CatalogController extends AbstractController
{
    public function __construct(
        private CatalogItemRepository $catalogItemRepository,
        private SupplierRepository $supplierRepository
    ) {
    }

    #[Route('/items', name: 'api_catalog_items', methods: ['GET'])]
    public function getItems(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $tenant = $user->getTenant();

        $criteria = [
            'search' => $request->query->get('search'),
            'supplier' => $request->query->get('supplier'),
            'category' => $request->query->get('category'),
            'brand' => $request->query->get('brand'),
            'minPrice' => $request->query->get('minPrice'),
            'maxPrice' => $request->query->get('maxPrice'),
            'sortBy' => $request->query->get('sortBy', 'name'),
            'order' => $request->query->get('order', 'ASC'),
        ];

        $items = $this->catalogItemRepository->searchItems($tenant, $criteria);

        $data = array_map(fn($item) => [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'description' => $item->getDescription(),
            'supplier' => [
                'id' => $item->getSupplier()->getId(),
                'name' => $item->getSupplier()->getName(),
                'type' => $item->getSupplier()->getType(),
            ],
            'supplierPartNumber' => $item->getSupplierPartNumber(),
            'price' => $item->getPrice(),
            'currency' => $item->getCurrency(),
            'unit' => $item->getUnit(),
            'category' => $item->getCategory(),
            'commodity' => $item->getCommodity(),
            'brand' => $item->getBrand(),
            'color' => $item->getColor(),
            'model' => $item->getModel(),
            'stockQuantity' => $item->getStockQuantity(),
            'isAvailable' => $item->isAvailable(),
            'imageUrl' => $item->getImageUrl(),
            'specifications' => $item->getSpecifications(),
        ], $items);

        return $this->json([
            'items' => $data,
            'total' => count($data),
        ]);
    }

    #[Route('/items/{id}', name: 'api_catalog_item', methods: ['GET'])]
    public function getItem(int $id): JsonResponse
    {
        $item = $this->catalogItemRepository->find($id);

        if (!$item) {
            return $this->json(['error' => 'Item not found'], 404);
        }

        return $this->json([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'description' => $item->getDescription(),
            'supplier' => [
                'id' => $item->getSupplier()->getId(),
                'name' => $item->getSupplier()->getName(),
                'logoUrl' => $item->getSupplier()->getLogoUrl(),
                'type' => $item->getSupplier()->getType(),
            ],
            'supplierPartNumber' => $item->getSupplierPartNumber(),
            'sku' => $item->getSku(),
            'price' => $item->getPrice(),
            'currency' => $item->getCurrency(),
            'unit' => $item->getUnit(),
            'category' => $item->getCategory(),
            'commodity' => $item->getCommodity(),
            'brand' => $item->getBrand(),
            'color' => $item->getColor(),
            'model' => $item->getModel(),
            'stockQuantity' => $item->getStockQuantity(),
            'isAvailable' => $item->isAvailable(),
            'imageUrl' => $item->getImageUrl(),
            'specifications' => $item->getSpecifications(),
        ]);
    }

    #[Route('/categories', name: 'api_catalog_categories', methods: ['GET'])]
    public function getCategories(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $tenant = $user->getTenant();

        $categories = $this->catalogItemRepository->getCategories($tenant);

        return $this->json(['categories' => $categories]);
    }

    #[Route('/brands', name: 'api_catalog_brands', methods: ['GET'])]
    public function getBrands(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $tenant = $user->getTenant();

        $brands = $this->catalogItemRepository->getBrands($tenant);

        return $this->json(['brands' => $brands]);
    }

    #[Route('/suppliers', name: 'api_catalog_suppliers', methods: ['GET'])]
    public function getSuppliers(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $tenant = $user->getTenant();

        $suppliers = $this->supplierRepository->findByTenant($tenant);

        $data = array_map(fn($supplier) => [
            'id' => $supplier->getId(),
            'name' => $supplier->getName(),
            'code' => $supplier->getCode(),
            'logoUrl' => $supplier->getLogoUrl(),
            'type' => $supplier->getType(),
            'isActive' => $supplier->isActive(),
        ], $suppliers);

        return $this->json(['suppliers' => $data]);
    }
}
