<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\SalesInventory\Model\Order;

use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Class ReturnProcessor
 * @since 2.1.3
 */
class ReturnProcessor
{
    /**
     * @var \Magento\CatalogInventory\Api\StockManagementInterface
     * @since 2.1.3
     */
    private $stockManagement;

    /**
     * @var \Magento\CatalogInventory\Model\Indexer\Stock\Processor
     * @since 2.1.3
     */
    private $stockIndexerProcessor;

    /**
     * @var \Magento\Catalog\Model\Indexer\Product\Price\Processor
     * @since 2.1.3
     */
    private $priceIndexer;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     * @since 2.1.3
     */
    private $storeManager;

    /**
     * @var \Magento\Sales\Api\OrderItemRepositoryInterface
     * @since 2.1.3
     */
    private $orderItemRepository;

    /**
     * ReturnProcessor constructor.
     * @param \Magento\CatalogInventory\Api\StockManagementInterface $stockManagement
     * @param \Magento\CatalogInventory\Model\Indexer\Stock\Processor $stockIndexer
     * @param \Magento\Catalog\Model\Indexer\Product\Price\Processor $priceIndexer
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Sales\Api\OrderItemRepositoryInterface $orderItemRepository
     * @since 2.1.3
     */
    public function __construct(
        \Magento\CatalogInventory\Api\StockManagementInterface $stockManagement,
        \Magento\CatalogInventory\Model\Indexer\Stock\Processor $stockIndexer,
        \Magento\Catalog\Model\Indexer\Product\Price\Processor $priceIndexer,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Api\OrderItemRepositoryInterface $orderItemRepository
    ) {
        $this->stockManagement = $stockManagement;
        $this->stockIndexerProcessor = $stockIndexer;
        $this->priceIndexer = $priceIndexer;
        $this->storeManager = $storeManager;
        $this->orderItemRepository = $orderItemRepository;
    }

    /**
     * @param CreditmemoInterface $creditmemo
     * @param OrderInterface $order
     * @param array $returnToStockItems
     * @param bool $isAutoReturn
     * @return void
     * @since 2.1.3
     */
    public function execute(
        CreditmemoInterface $creditmemo,
        OrderInterface $order,
        array $returnToStockItems = [],
        $isAutoReturn = false
    ) {
        $itemsToUpdate = [];
        foreach ($creditmemo->getItems() as $item) {
            $productId = $item->getProductId();
            $orderItem = $this->orderItemRepository->get($item->getOrderItemId());
            $parentItemId = $orderItem->getParentItemId();
            $qty = $item->getQty();
            if ($isAutoReturn || $this->canReturnItem($item, $qty, $parentItemId, $returnToStockItems)) {
                if (isset($itemsToUpdate[$productId])) {
                    $itemsToUpdate[$productId] += $qty;
                } else {
                    $itemsToUpdate[$productId] = $qty;
                }
            }
        }

        if (!empty($itemsToUpdate)) {
            $store = $this->storeManager->getStore($order->getStoreId());
            foreach ($itemsToUpdate as $productId => $qty) {
                $this->stockManagement->backItemQty(
                    $productId,
                    $qty,
                    $store->getWebsiteId()
                );
            }

            $updatedItemIds = array_keys($itemsToUpdate);
            $this->stockIndexerProcessor->reindexList($updatedItemIds);
            $this->priceIndexer->reindexList($updatedItemIds);
        }
    }

    /**
     * @param \Magento\Sales\Api\Data\CreditmemoItemInterface $item
     * @param int $qty
     * @param int[] $returnToStockItems
     * @param int $parentItemId
     * @return bool
     * @since 2.1.3
     */
    private function canReturnItem(
        \Magento\Sales\Api\Data\CreditmemoItemInterface $item,
        $qty,
        $parentItemId = null,
        array $returnToStockItems = []
    ) {
        return (in_array($item->getOrderItemId(), $returnToStockItems) || in_array($parentItemId, $returnToStockItems))
        && $qty;
    }
}
