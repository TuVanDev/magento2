<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogUrlRewrite\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogUrlRewrite\Model\Product\CanonicalUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Model\Product\CategoriesUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Model\Product\CurrentUrlRewritesRegenerator;
use Magento\CatalogUrlRewrite\Service\V1\StoreViewService;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ProductUrlRewriteGenerator
{
    /**
     * Entity type code
     */
    public const ENTITY_TYPE = 'product';

    /**
     * @deprecated 100.1.0
     * @see not used
     * @see https://github.com/magento/magento2/commit/6729b6e01368248abc33300208eb292c95050203
     * @var \Magento\CatalogUrlRewrite\Service\V1\StoreViewService
     */
    protected $storeViewService;

    /**
     * @var \Magento\Catalog\Model\Product
     * @see not used
     * @deprecated 100.1.0
     * @see https://github.com/magento/magento2/commit/6729b6e01368248abc33300208eb292c95050203
     */
    protected $product;

    /**
     * @deprecated 100.1.0
     * @see not used
     * @see https://github.com/magento/magento2/commit/6729b6e01368248abc33300208eb292c95050203
     * @var \Magento\CatalogUrlRewrite\Model\Product\CurrentUrlRewritesRegenerator
     */
    protected $currentUrlRewritesRegenerator;

    /**
     * @deprecated 100.1.0
     * @see not used
     * @see https://github.com/magento/magento2/commit/6729b6e01368248abc33300208eb292c95050203
     * @var \Magento\CatalogUrlRewrite\Model\Product\CategoriesUrlRewriteGenerator
     */
    protected $categoriesUrlRewriteGenerator;

    /**
     * @deprecated 100.1.0
     * @see not used
     * @see https://github.com/magento/magento2/commit/6729b6e01368248abc33300208eb292c95050203
     * @var \Magento\CatalogUrlRewrite\Model\Product\CanonicalUrlRewriteGenerator
     */
    protected $canonicalUrlRewriteGenerator;

    /**
     * @deprecated 100.1.0
     * @see not used
     * @see https://github.com/magento/magento2/commit/6729b6e01368248abc33300208eb292c95050203
     * @var \Magento\CatalogUrlRewrite\Model\ObjectRegistryFactory
     */
    protected $objectRegistryFactory;

    /**
     * @deprecated 100.1.0
     * @see not used
     * @see https://github.com/magento/magento2/commit/6729b6e01368248abc33300208eb292c95050203
     * @var \Magento\CatalogUrlRewrite\Model\ObjectRegistry
     */
    protected $productCategories;

    /**
     * @deprecated 100.1.0
     * @var \Magento\Store\Model\StoreManagerInterface
     * @see not used
     * @see https://github.com/magento/magento2/commit/6729b6e01368248abc33300208eb292c95050203
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ProductScopeRewriteGenerator
     */
    private $productScopeRewriteGenerator;

    /**
     * @var GetVisibleForStores
     */
    private $visibleForStores;

    /**
     * @param CanonicalUrlRewriteGenerator $canonicalUrlRewriteGenerator
     * @param CurrentUrlRewritesRegenerator $currentUrlRewritesRegenerator
     * @param CategoriesUrlRewriteGenerator $categoriesUrlRewriteGenerator
     * @param ObjectRegistryFactory $objectRegistryFactory
     * @param StoreViewService $storeViewService
     * @param StoreManagerInterface $storeManager
     * @param GetVisibleForStores|null $visibleForStores
     */
    public function __construct(
        CanonicalUrlRewriteGenerator  $canonicalUrlRewriteGenerator,
        CurrentUrlRewritesRegenerator $currentUrlRewritesRegenerator,
        CategoriesUrlRewriteGenerator $categoriesUrlRewriteGenerator,
        ObjectRegistryFactory         $objectRegistryFactory,
        StoreViewService              $storeViewService,
        StoreManagerInterface         $storeManager,
        GetVisibleForStores           $visibleForStores = null
    ) {
        $this->canonicalUrlRewriteGenerator = $canonicalUrlRewriteGenerator;
        $this->currentUrlRewritesRegenerator = $currentUrlRewritesRegenerator;
        $this->categoriesUrlRewriteGenerator = $categoriesUrlRewriteGenerator;
        $this->objectRegistryFactory = $objectRegistryFactory;
        $this->storeViewService = $storeViewService;
        $this->storeManager = $storeManager;
        $this->visibleForStores = $visibleForStores ?? ObjectManager::getInstance()->get(GetVisibleForStores::class);
    }

    /**
     * Retrieve Delegator for generation rewrites in different scopes
     *
     * @deprecated 100.1.4
     * @see not used
     * @see https://github.com/magento/magento2/commit/b2ce2a37d921b5ad88fc38663fc0ff3dd6c582d1
     * @return ProductScopeRewriteGenerator|mixed
     */
    private function getProductScopeRewriteGenerator()
    {
        if (!$this->productScopeRewriteGenerator) {
            $this->productScopeRewriteGenerator = ObjectManager::getInstance()
            ->get(ProductScopeRewriteGenerator::class);
        }

        return $this->productScopeRewriteGenerator;
    }

    /**
     * Generate product url rewrites
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param int|null $rootCategoryId
     * @return \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
     */
    public function generate(Product $product, $rootCategoryId = null)
    {
        if ($product->getVisibility() == Visibility::VISIBILITY_NOT_VISIBLE) {
            $visibleForStores = $this->visibleForStores->execute($product);
            if (count($visibleForStores) === 0 ||
                $product->getStoreId() !== Store::DEFAULT_STORE_ID &&
                !in_array($product->getStoreId(), $visibleForStores)
            ) {
                return [];
            }
        }

        $productCategories = $product->getCategoryCollection()
            ->addAttributeToSelect('url_key')
            ->addAttributeToSelect('url_path');

        // Generate url rewrites for all store views to ensure store views with different url-key are generated as well.
        return $this->generateForGlobalScope($productCategories, $product, $rootCategoryId);
    }

    /**
     * Check is global scope
     *
     * @deprecated 100.1.4
     * @see not used
     * @see https://github.com/magento/magento2/commit/b2ce2a37d921b5ad88fc38663fc0ff3dd6c582d1
     * @param int|null $storeId
     * @return bool
     */
    protected function isGlobalScope($storeId)
    {
        return $this->getProductScopeRewriteGenerator()->isGlobalScope($storeId);
    }

    /**
     * Generate list of urls for global scope
     *
     * @deprecated 100.1.4
     * @see not used
     * @see https://github.com/magento/magento2/commit/b2ce2a37d921b5ad88fc38663fc0ff3dd6c582d1
     * @param \Magento\Framework\Data\Collection $productCategories
     * @param \Magento\Catalog\Model\Product|null $product
     * @param int|null $rootCategoryId
     * @return \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
     */
    protected function generateForGlobalScope($productCategories, $product = null, $rootCategoryId = null)
    {
        return $this->getProductScopeRewriteGenerator()->generateForGlobalScope(
            $productCategories,
            $product,
            $rootCategoryId
        );
    }

    /**
     * Generate list of urls for specific store view
     *
     * @deprecated 100.1.4
     * @see not used
     * @see https://github.com/magento/magento2/commit/b2ce2a37d921b5ad88fc38663fc0ff3dd6c582d1
     * @param int $storeId
     * @param \Magento\Framework\Data\Collection $productCategories
     * @param Product|null $product
     * @param int|null $rootCategoryId
     * @return \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
     */
    protected function generateForSpecificStoreView(
        $storeId,
        $productCategories,
        $product = null,
        $rootCategoryId = null
    ) {
        return $this->getProductScopeRewriteGenerator()
            ->generateForSpecificStoreView($storeId, $productCategories, $product, $rootCategoryId);
    }

    /**
     * Checking the category is generating correctly
     *
     * @deprecated 100.1.4
     * Check if category should have url rewrites
     *
     * @deprecated 100.1.4
     * @see not used
     * @see https://github.com/magento/magento2/commit/b2ce2a37d921b5ad88fc38663fc0ff3dd6c582d1
     * @param \Magento\Catalog\Model\Category $category
     * @param int $storeId
     * @return bool
     */
    protected function isCategoryProperForGenerating($category, $storeId)
    {
        return $this->getProductScopeRewriteGenerator()->isCategoryProperForGenerating($category, $storeId);
    }
}
