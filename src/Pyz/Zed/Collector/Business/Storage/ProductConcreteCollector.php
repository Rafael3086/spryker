<?php

/**
 * This file is part of the Spryker Demoshop.
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Pyz\Zed\Collector\Business\Storage;

use Generated\Shared\Transfer\StorageProductTransfer;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Shared\Library\Json;
use Spryker\Shared\Product\ProductConstants;
use Spryker\Zed\Collector\Business\Collector\Storage\AbstractStoragePdoCollector;
use Spryker\Zed\Collector\CollectorConfig;
use Spryker\Zed\Price\Business\PriceFacadeInterface;
use Spryker\Zed\ProductImage\Persistence\ProductImageQueryContainerInterface;
use Spryker\Zed\Product\Business\Attribute\AttributeProcessor;

class ProductConcreteCollector extends AbstractStoragePdoCollector
{

    const ID_PRODUCT = 'id_product';
    const ID_CATEGORY_NODE = 'id_category_node';
    const ID_IMAGE_SET = 'id_image_set';
    const SKU = 'sku';
    const QUANTITY = 'quantity';
    const ATTRIBUTES = 'attributes';
    const CONCRETE_LOCALIZED_ATTRIBUTES = 'concrete_localized_attributes';
    const CONCRETE_ATTRIBUTES = 'concrete_attributes';
    const NAME = 'name';
    const PRICE = 'price';
    const PRICE_NAME = 'price_name';
    const ABSTRACT_ATTRIBUTES = 'abstract_attributes';
    const ABSTRACT_LOCALIZED_ATTRIBUTES = 'abstract_localized_attributes';
    const CONCRETE_DESCRIPTION = 'concrete_description';
    const ABSTRACT_DESCRIPTION = 'abstract_description';
    const ID_PRODUCT_ABSTRACT = 'id_product_abstract';
    const META_KEYWORDS = 'meta_keywords';
    const META_TITLE = 'meta_title';
    const META_DESCRIPTION = 'meta_description';

    /**
     * @var \Pyz\Zed\Collector\Business\Storage\PriceFacadeInterface
     */
    protected $priceFacade;

    /**
     * @var \Pyz\Zed\Collector\Business\Storage\ProductImageQueryContainerInterface|\Spryker\Zed\ProductImage\Persistence\ProductImageQueryContainerInterface
     */
    protected $productImageQueryContainer;

    /**
     * @param \Spryker\Zed\Price\Business\PriceFacadeInterface $priceFacade
     * @param \Spryker\Zed\ProductImage\Persistence\ProductImageQueryContainerInterface $productImageQueryContainer
     */
    public function __construct(
        PriceFacadeInterface $priceFacade,
        ProductImageQueryContainerInterface $productImageQueryContainer
    ) {
        $this->priceFacade = $priceFacade;
        $this->productImageQueryContainer = $productImageQueryContainer;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Product Concrete';
    }

    /**
     * @param string $touchKey
     * @param array $collectItemData
     *
     * @return array
     */
    protected function collectItem($touchKey, array $collectItemData)
    {
        return [
            StorageProductTransfer::ID => $collectItemData[CollectorConfig::COLLECTOR_RESOURCE_ID],
            StorageProductTransfer::NAME => $collectItemData[self::NAME],
            StorageProductTransfer::DESCRIPTION => $this->getDescription($collectItemData),
            StorageProductTransfer::ATTRIBUTES => $this->getConcreteAttributes($collectItemData),
            StorageProductTransfer::SKU => $collectItemData[self::SKU],
            StorageProductTransfer::QUANTITY => $collectItemData[self::QUANTITY],
            StorageProductTransfer::AVAILABLE => (int)$collectItemData[self::QUANTITY] > 0,
            StorageProductTransfer::IMAGES => $this->generateProductConcreteImages(
                $collectItemData[self::ID_PRODUCT_ABSTRACT],
                $collectItemData[CollectorConfig::COLLECTOR_RESOURCE_ID]
            ),
            StorageProductTransfer::PRICE => $this->getPriceBySku($collectItemData[self::SKU]),
            StorageProductTransfer::META_TITLE => $collectItemData[self::META_TITLE],
            StorageProductTransfer::META_KEYWORDS => $collectItemData[self::META_KEYWORDS],
            StorageProductTransfer::META_DESCRIPTION => $collectItemData[self::META_DESCRIPTION],
        ];
    }

    /**
     * @param array $collectItemData
     *
     * @return array
     */
    protected function getConcreteAttributes(array $collectItemData)
    {
        $abstractAttributesData = Json::decode($collectItemData[self::ABSTRACT_ATTRIBUTES], true);
        $concreteAttributesData = Json::decode($collectItemData[self::CONCRETE_ATTRIBUTES], true);

        $abstractLocalizedAttributesData = Json::decode($collectItemData[self::ABSTRACT_LOCALIZED_ATTRIBUTES], true);
        $concreteLocalizedAttributesData = Json::decode($collectItemData[self::CONCRETE_LOCALIZED_ATTRIBUTES], true);

        $attributeProcess = new AttributeProcessor(
            $abstractAttributesData,
            $concreteAttributesData,
            [$this->locale->getLocaleName() => $abstractLocalizedAttributesData],
            [$this->locale->getLocaleName() => $concreteLocalizedAttributesData]
        );

        return $attributeProcess->mergeAttributes($this->locale->getLocaleName());
    }

    /**
     * @param int $idProductAbstract
     * @param int $idProductConcrete
     *
     * @return array
     */
    protected function generateProductConcreteImages($idProductAbstract, $idProductConcrete)
    {
        $imageSets = $this->productImageQueryContainer
            ->queryProductImageSet()
                ->filterByFkProductAbstract($idProductAbstract)
                ->_or()
                ->filterByFkProduct($idProductConcrete)
            ->find();

        $result = [];
        foreach ($imageSets as $imageSetEntity) {
            $result[$imageSetEntity->getName()] = [];
            $images = $imageSetEntity->getSpyProductImageSetToProductImages(
                $this->productImageQueryContainer->queryProductImageSetToProductImage()
                    ->orderBySortOrder(Criteria::DESC)
            );
            foreach ($images as $imageEntity) {
                $result[$imageSetEntity->getName()][] = $imageEntity->getSpyProductImage()->toArray();
            }
        }

        return $result;
    }


    /**
     * @param string $sku
     *
     * @return int
     */
    protected function getPriceBySku($sku)
    {
        return $this->priceFacade->getPriceBySku($sku);
    }

    /**
     * @return string
     */
    protected function collectResourceType()
    {
        return ProductConstants::RESOURCE_TYPE_PRODUCT_CONCRETE;
    }

    /**
     * @param array $collectItemData
     *
     * @return string
     */
    protected function getDescription(array $collectItemData)
    {
        $description = trim($collectItemData[self::CONCRETE_DESCRIPTION]);
        if (!$description) {
            $description = trim($collectItemData[self::ABSTRACT_DESCRIPTION]);
        }

        return $description;
    }

}
