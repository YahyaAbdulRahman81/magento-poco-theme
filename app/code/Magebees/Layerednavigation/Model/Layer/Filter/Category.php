<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magebees\Layerednavigation\Model\Layer\Filter;

use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Filter\DataProvider\Category as CategoryDataProvider;

/**
 * Layer category filter
 */
class Category extends \Magento\CatalogSearch\Model\Layer\Filter\Category
{
    /**
     * @var \Magento\Framework\Escaper
     */
    private $escaper;

    /**
     * @var CategoryDataProvider
     */
    private $dataProvider;
    protected $_coreResource;

    protected $_requestVar;
    protected $level;
    protected $resourceConnection;
    protected $_scopeConfig;
    protected $_categoryFactory;
    protected $category;
    protected $categoryRepository;
    protected $itemCollectionProvider;
    protected $layerHelper;
    protected $request;
    protected $_collection;
    protected $_objectManager;

    /**
     * @param \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Layer $layer
     * @param \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder
     * @param \Magento\Catalog\Model\CategoryFactory $categoryFactory
     * @param \Magento\Framework\Escaper $escaper
     * @param CategoryManagerFactory $categoryManager
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer $layer,
        \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder,
        \Magento\Framework\Escaper $escaper,
        \Magento\Catalog\Model\Layer\Filter\DataProvider\CategoryFactory $categoryDataProviderFactory,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\CatalogSearch\Model\Layer\Category\ItemCollectionProvider $itemCollectionProvider,
        \Magebees\Layerednavigation\Helper\Data $layerHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Catalog\Helper\Category $category,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository,
        \Magento\Catalog\Model\ResourceModel\Product\Collection $collection,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct(
            $filterItemFactory,
            $storeManager,
            $layer,
            $itemDataBuilder,
            $escaper,
            $categoryDataProviderFactory,
            $data
        );
        $this->_coreResource = $resource;
        $this->escaper = $escaper;
        $this->_requestVar = "cat";
        $this->dataProvider = $categoryDataProviderFactory->create([
            "layer" => $this->getLayer(),
        ]);
        $this->level = 0;
        $this->resourceConnection = $resourceConnection;
        $this->_scopeConfig = $scopeConfig;
        $this->_categoryFactory = $categoryFactory;
        $this->category = $category;
        $this->categoryRepository = $categoryRepository;
        $this->itemCollectionProvider = $itemCollectionProvider;
        $this->layerHelper = $layerHelper;
        $this->request = $request;
        $this->_collection = $collection;
        $this->_objectManager = $objectManager;
    }

    /**
     * Apply category filter to product collection
     *
     * @param   \Magento\Framework\App\RequestInterface $request
     * @return  $this
     */
    public function apply(\Magento\Framework\App\RequestInterface $request)
    {
        //  $categoryId = $request->getParam($this->_requestVar) ?: $request->getParam('id');
        $currentEngine = $this->layerHelper->getCurrentSearchEngine();
        $IsElasticSearchEnabled = $this->layerHelper->IsElasticSearch();
        $categoryId = $request->getParam($this->_requestVar);
        if (empty($categoryId)) {
            return $this;
        }
        $is_enabled = $this->_scopeConfig->getValue(
            "layerednavigation/setting/enable",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $display_mode = $this->_scopeConfig->getValue(
            "layerednavigation/category_filter/display_mode",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $enable_multiselect = $this->_scopeConfig->getValue(
            "layerednavigation/category_filter/enable_multiselect",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $is_default_enabled = $this->_scopeConfig->getValue(
            "advanced/modules_disable_output/Magebees_Layerednavigation",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($is_default_enabled == 0) {
            if ($is_enabled) {
                if ($enable_multiselect && $display_mode == 0) {
                    if (preg_match("/,/", $categoryId)) {
                        if ($IsElasticSearchEnabled) {
                            $categoryIdArr = explode(",", (string) $categoryId);
                            //removed for solve multi select issue for 248
							//$this->getLayer()->getProductCollection()->addFieldToFilter('category_ids', $categoryId);
                            //added for 248
							$this->getLayer()
                                ->getProductCollection()
                                ->addCategoriesFilter(["in" => $categoryIdArr]);

                            foreach ($categoryIdArr as $category) {
                                $applied_params = $request->getParams();
                                if (isset($applied_params["cat"])) {
                                    $cat_id = $category;
                                    $category_data = $this->_categoryFactory
                                        ->create()
                                        ->load($category);
                                    $this->dataProvider->setCategoryId($cat_id);

                                    if (
                                        $category_data->getId() &&
                                        $this->dataProvider->isValid()
                                    ) {
                                        $this->getLayer()
                                            ->getState()
                                            ->addFilter(
                                                $this->_createItem(
                                                    $category_data->getName(),
                                                    $category
                                                )
                                            );
                                    }
                                }
                            }
                        } else {
                            //	if(strpos($categoryId,',') > -1)
                            //$cat_id_str=implode(',',$categoryId);
                            $categorytable = $this->_coreResource->getTableName(
                                "catalog_category_product"
                            );
                            $this->getLayer()
                                ->getProductCollection()
                                ->getSelect()
                                ->joinLeft(
                                    ["ccp" => $categorytable],
                                    "e.entity_id = ccp.product_id",
                                    "ccp.category_id"
                                )
                                ->group("e.entity_id")
                                ->where(
                                    "ccp.category_id IN (" . $categoryId . ")"
                                );

                            $categoryIdArr = explode(",", (string) $categoryId);
                            foreach ($categoryIdArr as $category) {
                                $applied_params = $request->getParams();
                                if (isset($applied_params["id"])) {
                                    $cat_id = $applied_params["id"];
                                    $category_data = $this->_categoryFactory
                                        ->create()
                                        ->load($category);
                                    $this->dataProvider->setCategoryId($cat_id);
                                    if (
                                        $request->getParam("id") !=
                                            $category_data->getId() &&
                                        $this->dataProvider->isValid()
                                    ) {
                                        $this->getLayer()
                                            ->getState()
                                            ->addFilter(
                                                $this->_createItem(
                                                    $category_data->getName(),
                                                    $category
                                                )
                                            );
                                    }
                                }
                            }
                        }
                        return $this;
                    }
                }
            } else {
                return parent::apply($request);
            }
        }
        return parent::apply($request);
    }
    /**
     * Get data array for building category filter items
     *
     * @return array
     */
    protected function _getItemsData()
    {
        $is_enabled = $this->_scopeConfig->getValue(
            "layerednavigation/setting/enable",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $is_default_enabled = $this->_scopeConfig->getValue(
            "advanced/modules_disable_output/Magebees_Layerednavigation",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($is_default_enabled == 0) {
            if ($is_enabled) {
                $sku_arr = [];
                $productCollection = $this->getLayer()->getProductCollection();
                $optionsFacetedData = $productCollection->getFacetedData(
                    "category"
                );
                $display_mode = $this->_scopeConfig->getValue(
                    "layerednavigation/category_filter/display_mode",
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
                $enable_multiselect = $this->_scopeConfig->getValue(
                    "layerednavigation/category_filter/enable_multiselect",
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
                $activeFilters = $this->_objectManager
                    ->get("\Magento\LayeredNavigation\Block\Navigation\State")
                    ->getActiveFilters();
                $applied_filter_count = count($activeFilters);
                if ($enable_multiselect && $display_mode == 0) {
                    $connection = $this->resourceConnection->getConnection();
                    $select = $this->getLayer()
                        ->getProductCollection()
                        ->getSelect();
                    $result = $connection->query($select)->fetchAll();
                    foreach ($result as $res) {
                        $sku_arr[] = $res["sku"];
                    }
                    $sku_str = implode(",", $sku_arr);
                    $productCollection = $this->getUnfilteredProductCollection();
                    $optionsFacetedData = $productCollection->getFacetedData(
                        "category"
                    );
                    $applied_params = $this->request->getParams();
                    if (isset($applied_params["id"])) {
                        $cat_id = $applied_params["id"];
                        $category = $this->_categoryFactory
                            ->create()
                            ->load($cat_id);
                        $categories = $category->getChildrenCategories();
                        if ($category->getIsActive()) {
                            foreach ($categories as $category) {
                                if ($category->getIsActive()) {
                                    $category = $category->getId();
                                    $category = $this->_categoryFactory
                                        ->create()
                                        ->load($category);
                                    $collection = $this->_objectManager->create(
                                        "\Magento\Catalog\Model\ResourceModel\Product\Collection"
                                    );
                                    if ($applied_filter_count > 0) {
                                        $prodCollection = $this->getUnfilteredProductCollection();
                                        $optionsFacetedData = $productCollection->getFacetedData(
                                            "category"
                                        );
                                        if (
                                            isset(
                                                $optionsFacetedData[
                                                    $category->getId()
                                                ]["count"]
                                            )
                                        ) {
                                            $count =
                                                $optionsFacetedData[
                                                    $category->getId()
                                                ]["count"];
                                        } else {
                                            $prodCollection = $collection->addCategoryFilter(
                                                $category
                                            );
                                            $count = $prodCollection->count();
                                        }
                                        // $prodCollection = $collection->addCategoryFilter($category)->addFieldToFilter('sku', ['in' => $sku_str]);
                                    } else {
                                        $prodCollection = $collection->addCategoryFilter(
                                            $category
                                        );
                                        $count = $prodCollection->count();
                                    }

                                    if ($count > 0) {
                                        $this->itemDataBuilder->addItemData(
                                            $this->escaper->escapeHtml(
                                                $category->getName()
                                            ),
                                            $category->getId(),
                                            $count
                                        );
                                    }
                                }
                            }
                        }
                    } else {
                        return parent::_getItemsData();
                    }
                } elseif (
                    $display_mode == 0 ||
                    $display_mode == 1 ||
                    $display_mode == 2
                ) {
                    $category = $this->dataProvider->getCategory();
                    $collectionSize = $productCollection->getSize();
                    $categories = $category->getChildrenCategories();

                    if ($category->getIsActive()) {
                        foreach ($categories as $category) {
                            if (
                                $category->getIsActive() &&
                                isset(
                                    $optionsFacetedData[$category->getId()]
                                ) &&
                                $this->isOptionReducesResults(
                                    $optionsFacetedData[$category->getId()][
                                        "count"
                                    ],
                                    $collectionSize
                                )
                            ) {
                                $this->itemDataBuilder->addItemData(
                                    $this->escaper->escapeHtml(
                                        $category->getName()
                                    ),
                                    $category->getId(),
                                    $optionsFacetedData[$category->getId()][
                                        "count"
                                    ]
                                );
                                if ($display_mode == 2) {
                                    $this->getChildCategoryData($category);
                                }
                            }
                        }
                    }
                } elseif ($display_mode == 4 || $display_mode == 3) {
                    $connection = $this->resourceConnection->getConnection();
                    $select = $this->getLayer()
                        ->getProductCollection()
                        ->getSelect();
                    $result = $connection->query($select)->fetchAll();
                    foreach ($result as $res) {
                        $sku_arr[] = $res["sku"];
                    }
                    $sku_str = implode(",", $sku_arr);

                    $categories = $this->category->getStoreCategories();
                    foreach ($categories as $category) {
                        if ($category->getIsActive()) {
                            $category = $category->getId();
                            $category = $this->_categoryFactory
                                ->create()
                                ->load($category);
                            $collection = $this->_objectManager->create(
                                "\Magento\Catalog\Model\ResourceModel\Product\Collection"
                            );
                            if ($applied_filter_count > 0) {
                                $prodCollection = $collection
                                    ->addCategoryFilter($category)
                                    ->addFieldToFilter("sku", [
                                        "in" => $sku_str,
                                    ]);
                            } else {
                                $prodCollection = $collection->addCategoryFilter(
                                    $category
                                );
                            }

                            $count = $prodCollection->count();

                            if ($count) {
                                $this->itemDataBuilder->addItemData(
                                    $this->escaper->escapeHtml(
                                        $category->getName()
                                    ),
                                    $category->getId(),
                                    $count
                                );
                            }
                            $this->getChildCatForTree($category);
                        }
                    }
                }
                return $this->itemDataBuilder->build();
            } else {
                return parent::_getItemsData();
            }
        }
        return parent::_getItemsData();
    }

    public function getChildCatForTree($category, $level = 0)
    {
        $sku_arr = [];
        $activeFilters = $this->_objectManager
            ->get("\Magento\LayeredNavigation\Block\Navigation\State")
            ->getActiveFilters();
        $applied_filter_count = count($activeFilters);
        $connection = $this->resourceConnection->getConnection();
        $select = $this->getLayer()
            ->getProductCollection()
            ->getSelect();
        $result = $connection->query($select)->fetchAll();
        foreach ($result as $res) {
            $sku_arr[] = $res["sku"];
        }
        $sku_str = implode(",", $sku_arr);
        $level++;
        $child = 0;
        $display_mode = $this->_scopeConfig->getValue(
            "layerednavigation/category_filter/display_mode",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $nonEscapableNbspChar = html_entity_decode(
            "&#160;",
            ENT_NOQUOTES,
            "UTF-8"
        );
        $category = $category->getId();
        $category = $this->_categoryFactory->create()->load($category);
        $subcategories = $category->getChildrenCategories();
        if ($subcategories) {
            foreach ($subcategories as $subcategory) {
                $category = $subcategory->getId();
                $category = $this->_categoryFactory->create()->load($category);
                if ($subcategory->getIsActive()) {
                    $collection = $this->_objectManager->create(
                        "\Magento\Catalog\Model\ResourceModel\Product\Collection"
                    );
                    if ($applied_filter_count > 0) {
                        $prodCollection = $collection
                            ->addCategoryFilter($category)
                            ->addFieldToFilter("sku", ["in" => $sku_str]);
                    } else {
                        $prodCollection = $collection->addCategoryFilter(
                            $category
                        );
                    }
                    $count = $prodCollection->count();
                    if ($count) {
                        $child++;
                        if ($display_mode == 3) {
                            $this->itemDataBuilder->addItemData(
                                $subcategory->getName(),
                                $subcategory->getId(),
                                $count
                            );
                        } elseif ($display_mode == 4) {
                            $this->itemDataBuilder->addItemData(
                                $subcategory->getName(),
                                $subcategory->getId(),
                                $count
                            );
                        }
                    }
                    if ($display_mode == 4) {
                        $this->getChildCatForTree($subcategory, $level);
                    }
                }
            }
            return $this;
        }
    }

    public function getChildCategoryData($category)
    {
        $this->level++;
        $nonEscapableNbspChar = html_entity_decode(
            "&#160;",
            ENT_NOQUOTES,
            "UTF-8"
        );
        $display_mode = $this->_scopeConfig->getValue(
            "layerednavigation/category_filter/display_mode",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $productCollection = $this->getUnfilteredProductCollection();
        $optionsFacetedData = $productCollection->getFacetedData("category");
        $categories = $category->getChildrenCategories();
        if ($category->getIsActive()) {
            foreach ($categories as $category) {
                if (
                    $category->getIsActive() &&
                    isset($optionsFacetedData[$category->getId()]) &&
                    isset($optionsFacetedData[$category->getId()]["count"])
                ) {
                    $this->itemDataBuilder->addItemData(
                        $category->getName(),
                        $category->getId(),
                        $optionsFacetedData[$category->getId()]["count"]
                    );
                    $this->getChildCategoryData($category);
                }
            }
        }
        return $this;
    }
    private function getUnfilteredProductCollection()
    {
        $layer = $this->getLayer();
        $productCollection = $this->itemCollectionProvider->getCollection(
            $layer->getCurrentCategory()
        );
        $layer->prepareProductCollection($productCollection);
        return $productCollection;
    }
}