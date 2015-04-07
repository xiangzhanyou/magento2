<?php
/**
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Catalog\Model;

use Magento\Catalog\Model\Resource\Product\Collection;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\StateException;
use Magento\Catalog\Model\Product\Gallery\ContentValidator;
use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryContentInterfaceFactory;
use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryContentInterface;
use Magento\Catalog\Model\Product\Gallery\MimeTypeExtensionMap;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class ProductRepository implements \Magento\Catalog\Api\ProductRepositoryInterface
{
    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var Product[]
     */
    protected $instances = [];

    /**
     * @var Product[]
     */
    protected $instancesById = [];

    /**
     * @var \Magento\Catalog\Controller\Adminhtml\Product\Initialization\Helper
     */
    protected $initializationHelper;

    /**
     * @var \Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\Framework\Api\FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var \Magento\Catalog\Model\Resource\Product\CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var \Magento\Catalog\Model\Resource\Product
     */
    protected $resourceModel;

    /*
     * @var \Magento\Catalog\Model\Product\Initialization\Helper\ProductLinks
     */
    protected $linkInitializer;

    /**
     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
     */
    protected $metadataService;

    /**
     * @var \Magento\Eav\Model\Config
     */
    protected $eavConfig;

    /**
     * @var \Magento\Framework\Api\ExtensibleDataObjectConverter
     */
    protected $extensibleDataObjectConverter;

    /**
     * @var \Magento\Catalog\Model\Product\Option\Converter
     */
    protected $optionConverter;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $fileSystem;

    /**
     * @var ContentValidator
     */
    protected $contentValidator;

    /**
     * @var ProductAttributeMediaGalleryEntryContentInterfaceFactory
     */
    protected $contentFactory;

    /**
     * @var MimeTypeExtensionMap
     */
    protected $mimeTypeExtensionMap;

    /**
     * @param ProductFactory $productFactory
     * @param \Magento\Catalog\Controller\Adminhtml\Product\Initialization\Helper $initializationHelper
     * @param \Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory $searchResultsFactory
     * @param Resource\Product\CollectionFactory $collectionFactory
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository
     * @param Resource\Product $resourceModel
     * @param \Magento\Catalog\Model\Product\Initialization\Helper\ProductLinks $linkInitializer
     * @param \Magento\Framework\Api\FilterBuilder $filterBuilder
     * @param \Magento\Catalog\Api\ProductAttributeRepositoryInterface $metadataServiceInterface
     * @param \Magento\Framework\Api\ExtensibleDataObjectConverter $extensibleDataObjectConverter
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param \Magento\Catalog\Model\Product\Option\Converter $optionConverter
     * @param \Magento\Framework\Filesystem $fileSystem
     * @param ContentValidator $contentValidator
     * @param ProductAttributeMediaGalleryEntryContentInterfaceFactory $contentFactory
     * @param MimeTypeExtensionMap $mimeTypeExtensionMap
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ProductFactory $productFactory,
        \Magento\Catalog\Controller\Adminhtml\Product\Initialization\Helper $initializationHelper,
        \Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory $searchResultsFactory,
        \Magento\Catalog\Model\Resource\Product\CollectionFactory $collectionFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository,
        \Magento\Catalog\Model\Resource\Product $resourceModel,
        \Magento\Catalog\Model\Product\Initialization\Helper\ProductLinks $linkInitializer,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $metadataServiceInterface,
        \Magento\Framework\Api\ExtensibleDataObjectConverter $extensibleDataObjectConverter,
        \Magento\Catalog\Model\Product\Option\Converter $optionConverter,
        \Magento\Framework\Filesystem $fileSystem,
        ContentValidator $contentValidator,
        ProductAttributeMediaGalleryEntryContentInterfaceFactory $contentFactory,
        MimeTypeExtensionMap $mimeTypeExtensionMap,
        \Magento\Eav\Model\Config $eavConfig
    ) {
        $this->productFactory = $productFactory;
        $this->collectionFactory = $collectionFactory;
        $this->initializationHelper = $initializationHelper;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->resourceModel = $resourceModel;
        $this->linkInitializer = $linkInitializer;
        $this->attributeRepository = $attributeRepository;
        $this->filterBuilder = $filterBuilder;
        $this->metadataService = $metadataServiceInterface;
        $this->extensibleDataObjectConverter = $extensibleDataObjectConverter;
        $this->optionConverter = $optionConverter;
        $this->fileSystem = $fileSystem;
        $this->contentValidator = $contentValidator;
        $this->contentFactory = $contentFactory;
        $this->mimeTypeExtensionMap = $mimeTypeExtensionMap;
        $this->eavConfig = $eavConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function get($sku, $editMode = false, $storeId = null)
    {
        $cacheKey = $this->getCacheKey(func_get_args());
        if (!isset($this->instances[$sku][$cacheKey])) {
            $product = $this->productFactory->create();

            $productId = $this->resourceModel->getIdBySku($sku);
            if (!$productId) {
                throw new NoSuchEntityException(__('Requested product doesn\'t exist'));
            }
            if ($editMode) {
                $product->setData('_edit_mode', true);
            }
            $product->load($productId);
            $this->instances[$sku][$cacheKey] = $product;
            $this->instancesById[$product->getId()][$cacheKey] = $product;
        }
        return $this->instances[$sku][$cacheKey];
    }

    /**
     * {@inheritdoc}
     */
    public function getById($productId, $editMode = false, $storeId = null)
    {
        $cacheKey = $this->getCacheKey(func_get_args());
        if (!isset($this->instancesById[$productId][$cacheKey])) {
            $product = $this->productFactory->create();

            if ($editMode) {
                $product->setData('_edit_mode', true);
            }
            if ($storeId !== null) {
                $product->setData('store_id', $storeId);
            }
            $product->load($productId);
            if (!$product->getId()) {
                throw new NoSuchEntityException(__('Requested product doesn\'t exist'));
            }
            $this->instancesById[$productId][$cacheKey] = $product;
            $this->instances[$product->getSku()][$cacheKey] = $product;
        }
        return $this->instancesById[$productId][$cacheKey];
    }

    /**
     * Get key for cache
     *
     * @param array $data
     * @return string
     */
    protected function getCacheKey($data)
    {
        unset($data[0]);
        $serializeData = [];
        foreach ($data as $key => $value) {
            if (is_object($value)) {
                $serializeData[$key] = $value->getId();
            } else {
                $serializeData[$key] = $value;
            }
        }

        return md5(serialize($serializeData));
    }

    /**
     * Merge data from DB and updates from request
     *
     * @param array $productData
     * @param bool $createNew
     * @return \Magento\Catalog\Api\Data\ProductInterface|Product
     * @throws NoSuchEntityException
     */
    protected function initializeProductData(array $productData, $createNew)
    {
        if ($createNew) {
            $product = $this->productFactory->create();
        } else {
            unset($this->instances[$productData['sku']]);
            $product = $this->get($productData['sku']);
            $this->initializationHelper->initialize($product);
        }
        foreach ($productData as $key => $value) {
            $product->setData($key, $value);
        }

        return $product;
    }

    /**
     * Process product options, creating new options, updating and deleting existing options
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param array $newOptions
     * @return $this
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function processOptions(\Magento\Catalog\Api\Data\ProductInterface $product, $newOptions)
    {
        //existing options by option_id
        /** @var \Magento\Catalog\Api\Data\ProductCustomOptionInterface[] $existingOptions */
        $existingOptions = $product->getOptions();
        if ($existingOptions === null) {
            $existingOptions = [];
        }

        $newOptionIds = [];
        foreach ($newOptions as $key => $option) {
            if (isset($option['option_id'])) {
                //updating existing option
                $optionId = $option['option_id'];
                if (!isset($existingOptions[$optionId])) {
                    throw new NoSuchEntityException(__('Product option with id %1 does not exist', $optionId));
                }
                $existingOption = $existingOptions[$optionId];
                $newOptionIds[] = $option['option_id'];
                if (isset($option['values'])) {
                    //updating option values
                    $optionValues = $option['values'];
                    $valueIds = [];
                    foreach ($optionValues as $optionValue) {
                        if (isset($optionValue['option_type_id'])) {
                            $valueIds[] = $optionValue['option_type_id'];
                        }
                    }
                    $originalValues = $existingOption->getValues();
                    foreach ($originalValues as $originalValue) {
                        if (!in_array($originalValue->getOptionTypeId(), $valueIds)) {
                            $originalValue->setData('is_delete', 1);
                            $optionValues[] = $originalValue->getData();
                        }
                    }
                    $newOptions[$key]['values'] = $optionValues;
                } else {
                    $existingOptionData = $this->optionConverter->toArray($existingOption);
                    if (isset($existingOptionData['values'])) {
                        $newOptions[$key]['values'] = $existingOptionData['values'];
                    }
                }
            }
        }

        $optionIdsToDelete = array_diff(array_keys($existingOptions), $newOptionIds);
        foreach ($optionIdsToDelete as $optionId) {
            $optionToDelete = $existingOptions[$optionId];
            $optionDataArray = $this->optionConverter->toArray($optionToDelete);
            $optionDataArray['is_delete'] = 1;
            $newOptions[] = $optionDataArray;
        }
        $product->setProductOptions($newOptions);
        return $this;
    }

    /**
     * Process product links, creating new links, updating and deleting existing links
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param \Magento\Catalog\Api\Data\ProductLinkInterface[] $newLinks
     * @return $this
     * @throws NoSuchEntityException
     */
    private function processLinks(\Magento\Catalog\Api\Data\ProductInterface $product, $newLinks)
    {
        if ($newLinks === null) {
            // If product links were not specified, don't do anything
            return $this;
        }

        // Clear all existing product links and then set the ones we want
        $this->linkInitializer->initializeLinks($product, ['related' => []]);
        $this->linkInitializer->initializeLinks($product, ['upsell' => []]);
        $this->linkInitializer->initializeLinks($product, ['crosssell' => []]);

        // Gather each linktype info
        if (!empty($newLinks)) {
            $productLinks = [];
            foreach ($newLinks as $link) {
                $productLinks[$link->getLinkType()][] = $link;
            }

            foreach ($productLinks as $type => $linksByType) {
                $assignedSkuList = [];
                /** @var \Magento\Catalog\Api\Data\ProductLinkInterface $link */
                foreach ($linksByType as $link) {
                    $assignedSkuList[] = $link->getLinkedProductSku();
                }
                $linkedProductIds = $this->resourceModel->getProductsIdsBySkus($assignedSkuList);

                $linksToInitialize = [];
                foreach ($linksByType as $link) {
                    $linkDataArray = $this->extensibleDataObjectConverter
                        ->toNestedArray($link, [], 'Magento\Catalog\Api\Data\ProductLinkInterface');
                    $linkedSku = $link->getLinkedProductSku();
                    if (!isset($linkedProductIds[$linkedSku])) {
                        throw new NoSuchEntityException(
                            __('Product with SKU "%1" does not exist', $linkedSku)
                        );
                    }
                    $linkDataArray['product_id'] = $linkedProductIds[$linkedSku];
                    $linksToInitialize[$linkedProductIds[$linkedSku]] = $linkDataArray;
                }

                $this->linkInitializer->initializeLinks($product, [$type => $linksToInitialize]);
            }
        }

        $product->setProductLinks($newLinks);
        return $this;
    }

    /**
     * @param ProductInterface $product
     * @param array $newEntry
     * @return $this
     * @throws InputException
     * @throws StateException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function processNewMediaGalleryEntry(
        ProductInterface $product,
        array  $newEntry
    ) {
        /** @var ProductAttributeMediaGalleryEntryContentInterface $contentDataObject */
        $contentDataObject = $newEntry['content'];
        if (!$this->contentValidator->isValid($contentDataObject)) {
            throw new InputException(__('The image content is not valid.'));
        }

        $fileContent = @base64_decode($contentDataObject->getEntryData(), true);
        $fileName = $contentDataObject->getName();
        $mimeType = $contentDataObject->getMimeType();

        /** @var \Magento\Catalog\Model\Product\Media\Config $mediaConfig */
        $mediaConfig = $product->getMediaConfig();
        $mediaTmpPath = $mediaConfig->getBaseTmpMediaPath();
        $mediaDirectory = $this->fileSystem->getDirectoryWrite(DirectoryList::MEDIA);
        $mediaDirectory->create($mediaTmpPath);
        $fileName = $fileName . '.' . $this->mimeTypeExtensionMap->getMimeTypeExtension($mimeType);
        $relativeFilePath = $mediaTmpPath . DIRECTORY_SEPARATOR . $fileName;
        $absoluteFilePath = $mediaDirectory->getAbsolutePath($relativeFilePath);
        $mediaDirectory->writeFile($relativeFilePath, $fileContent);

        /** @var \Magento\Catalog\Model\Product\Attribute\Backend\Media $galleryAttributeBackend */
        $galleryAttributeBackend = $product->getGalleryAttributeBackend();
        if ($galleryAttributeBackend == null) {
            throw new StateException(__('Requested product does not support images.'));
        }

        $imageFileUri = $galleryAttributeBackend->addImage(
            $product,
            $absoluteFilePath,
            isset($newEntry['types']) ? $newEntry['types'] : [],
            true,
            isset($newEntry['disabled']) ? $newEntry['disabled'] : true
        );
        // Update additional fields that are still empty after addImage call
        $galleryAttributeBackend->updateImage(
            $product,
            $imageFileUri,
            [
                'label' => $newEntry['label'],
                'position' => $newEntry['position'],
                'disabled' => $newEntry['disabled'],
            ]
        );
        return $this;
    }

    /**
     * @param ProductInterface $product
     * @param array $mediaGalleryEntries
     * @return $this
     * @throws InputException
     * @throws StateException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function processMediaGallery(ProductInterface $product, $mediaGalleryEntries)
    {
        $existingMediaGallery = $product->getMediaGallery('images');
        $newEntries = [];
        if (!empty($existingMediaGallery)) {
            $entriesById = [];
            foreach ($mediaGalleryEntries as $entry) {
                if (isset($entry['id'])) {
                    $entry['value_id'] = $entry['id'];
                    $entriesById[$entry['value_id']] = $entry;
                } else {
                    $newEntries[] = $entry;
                }
            }
            foreach ($existingMediaGallery as $key => &$existingEntry) {
                if (isset($entriesById[$existingEntry['value_id']])) {
                    $updatedEntry = $entriesById[$existingEntry['value_id']];
                    $existingMediaGallery[$key] = array_merge($existingEntry, $updatedEntry);
                } else {
                    //set the removed flag
                    $existingEntry['removed'] = true;
                }
            }
            $product->setData('media_gallery', ["images" => $existingMediaGallery]);
        } else {
            $newEntries = $mediaGalleryEntries;
        }

        /** @var \Magento\Catalog\Model\Product\Attribute\Backend\Media $galleryAttributeBackend */
        $galleryAttributeBackend = $product->getGalleryAttributeBackend();
        $galleryAttributeBackend->clearMediaAttribute($product, array_keys($product->getMediaAttributes()));
        $images = $product->getMediaGallery('images');
        if ($images) {
            foreach ($images as $image) {
                if (!isset($image['removed']) && !empty($image['types'])) {
                    $galleryAttributeBackend->setMediaAttribute($product, $image['types'], $image['file']);
                }
            }
        }

        foreach ($newEntries as $newEntry) {
            if (!isset($newEntry['content'])) {
                throw new InputException(__('The image content is not valid.'));
            }
            /** @var ProductAttributeMediaGalleryEntryContentInterface $contentDataObject */
            $contentDataObject = $this->contentFactory->create()
                ->setName($newEntry['content']['name'])
                ->setEntryData($newEntry['content']['entry_data'])
                ->setMimeType($newEntry['content']['mime_type']);
            $newEntry['content'] = $contentDataObject;
            $this->processNewMediaGalleryEntry($product, $newEntry);
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function save(\Magento\Catalog\Api\Data\ProductInterface $product, $saveOptions = false)
    {
        if ($saveOptions) {
            $productOptions = $product->getProductOptions();
        }
        $isDeleteOptions = $product->getIsDeleteOptions();
        $groupPrices = $product->getData('group_price');
        $tierPrices = $product->getData('tier_price');

        $productId = $this->resourceModel->getIdBySku($product->getSku());
        $ignoreLinksFlag = $product->getData('ignore_links_flag');
        $productDataArray = $this->extensibleDataObjectConverter
            ->toNestedArray($product, [], 'Magento\Catalog\Api\Data\ProductInterface');

        $productLinks = null;
        if (!$ignoreLinksFlag && $ignoreLinksFlag !== null) {
            $productLinks = $product->getProductLinks();
        }

        $product = $this->initializeProductData($productDataArray, empty($productId));

        if (isset($productDataArray['options'])) {
            if (!empty($productDataArray['options']) || $isDeleteOptions) {
                $this->processOptions($product, $productDataArray['options']);
                $product->setCanSaveCustomOptions(true);
            }
        }

        $this->processLinks($product, $productLinks);
        if (isset($productDataArray['media_gallery_entries'])) {
            $this->processMediaGallery($product, $productDataArray['media_gallery_entries']);
        }

        $validationResult = $this->resourceModel->validate($product);
        if (true !== $validationResult) {
            throw new \Magento\Framework\Exception\CouldNotSaveException(
                __('Invalid product data: %1', implode(',', $validationResult))
            );
        }
        try {
            if ($saveOptions) {
                $product->setProductOptions($productOptions);
                $product->setCanSaveCustomOptions(true);
            }
            if ($groupPrices !== null) {
                $product->setData('group_price', $groupPrices);
            }
            if ($tierPrices !== null) {
                $product->setData('tier_price', $tierPrices);
            }
            $this->resourceModel->save($product);
        } catch (\Magento\Eav\Model\Entity\Attribute\Exception $exception) {
            throw \Magento\Framework\Exception\InputException::invalidFieldValue(
                $exception->getAttributeCode(),
                $product->getData($exception->getAttributeCode()),
                $exception
            );
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\CouldNotSaveException(__('Unable to save product'));
        }
        unset($this->instances[$product->getSku()]);
        unset($this->instancesById[$product->getId()]);
        return $this->get($product->getSku());
    }

    /**
     * {@inheritdoc}
     */
    public function delete(\Magento\Catalog\Api\Data\ProductInterface $product)
    {
        $sku = $product->getSku();
        $productId = $product->getId();
        try {
            $this->resourceModel->delete($product);
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\StateException(
                __('Unable to remove product %1', $sku)
            );
        }
        unset($this->instances[$sku]);
        unset($this->instancesById[$productId]);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($sku)
    {
        $product = $this->get($sku);
        return $this->delete($product);
    }

    /**
     * {@inheritdoc}
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        /** @var \Magento\Catalog\Model\Resource\Product\Collection $collection */
        $collection = $this->collectionFactory->create();
        $defaultAttributeSetId = $this->eavConfig
            ->getEntityType(\Magento\Catalog\Api\Data\ProductAttributeInterface::ENTITY_TYPE_CODE)
            ->getDefaultAttributeSetId();
        $extendedSearchCriteria = $this->searchCriteriaBuilder->addFilter(
            [
                $this->filterBuilder
                    ->setField('attribute_set_id')
                    ->setValue($defaultAttributeSetId)
                    ->create(),
            ]
        );

        foreach ($this->metadataService->getList($extendedSearchCriteria->create())->getItems() as $metadata) {
            $collection->addAttributeToSelect($metadata->getAttributeCode());
        }
        $collection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner');
        $collection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner');

        //Add filters from root filter group to the collection
        foreach ($searchCriteria->getFilterGroups() as $group) {
            $this->addFilterGroupToCollection($group, $collection);
        }
        /** @var SortOrder $sortOrder */
        foreach ((array)$searchCriteria->getSortOrders() as $sortOrder) {
            $field = $sortOrder->getField();
            $collection->addOrder(
                $field,
                ($sortOrder->getDirection() == SearchCriteriaInterface::SORT_ASC) ? 'ASC' : 'DESC'
            );
        }
        $collection->setCurPage($searchCriteria->getCurrentPage());
        $collection->setPageSize($searchCriteria->getPageSize());
        $collection->load();

        $searchResult = $this->searchResultsFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());
        return $searchResult;
    }

    /**
     * Helper function that adds a FilterGroup to the collection.
     *
     * @param \Magento\Framework\Api\Search\FilterGroup $filterGroup
     * @param Collection $collection
     * @return void
     */
    protected function addFilterGroupToCollection(
        \Magento\Framework\Api\Search\FilterGroup $filterGroup,
        Collection $collection
    ) {
        $fields = [];
        foreach ($filterGroup->getFilters() as $filter) {
            $condition = $filter->getConditionType() ? $filter->getConditionType() : 'eq';
            $fields[] = ['attribute' => $filter->getField(), $condition => $filter->getValue()];
        }
        if ($fields) {
            $collection->addFieldToFilter($fields);
        }
    }
}
