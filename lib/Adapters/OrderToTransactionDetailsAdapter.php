<?php

namespace Siren\WordPress\Extensions\YourExtension\Adapters;

use PHPNomad\Datastore\Exceptions\DatastoreErrorException;
use PHPNomad\Logger\Interfaces\LoggerStrategy;
use PHPNomad\Utils\Helpers\Arr;
use Siren\Commerce\Adapters\FloatToIntPriceAdapter;
use Siren\Mappings\Core\Datastores\Mapping\MappingDatastore;

/*
 * OrderToTransactionDetailsAdapter: Converting orders to Siren's format
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * Siren needs a standardized array of "transaction details" (line items) to
 * calculate commissions. This adapter converts the target plugin's native
 * order/cart format into Siren's expected structure.
 *
 * ── Transaction Detail Format ────────────────────────────────────────────────
 *
 * Each item in the returned array represents one line in the order:
 *
 *   [
 *       'name'        => string,       // Display name (e.g., "Blue Widget")
 *       'description' => string,       // Longer description or quantity label
 *       'type'        => string,       // One of: 'product', 'shipping', 'tax',
 *                                      //         'discount', 'fee'
 *       'value'       => int,          // Price in CENTS (not dollars!)
 *       'quantity'    => int,          // Item quantity
 *       'units'       => string,       // Currency code (e.g., 'USD', 'EUR')
 *       'externalId'  => mixed|null,   // Item ID in the target plugin
 *       'attributes'  => [             // Optional, product-type items only
 *           'collaborators' => array,  // Collaborator IDs assigned to this product
 *           'categories'    => array,  // Product category slugs
 *           'sku'           => ?string // Product SKU
 *       ],
 *   ]
 *
 * ── Price Format (CRITICAL) ─────────────────────────────────────────────────
 *
 * ALL prices MUST be in cents (integer). A $29.99 product = 2999.
 *
 * Use FloatToIntPriceAdapter to convert:
 *   $this->priceAdapter->toInt(29.99) → 2999
 *
 * If the target plugin stores prices as strings (e.g., "$29.99"), parse
 * the float first, then convert:
 *   $this->priceAdapter->toInt((float) str_replace(['$', ','], '', $price))
 *
 * ── Item Types ──────────────────────────────────────────────────────────────
 *
 * 'product'   — A purchasable item. Siren calculates commissions on these.
 *               Include 'attributes' with collaborators and categories.
 *
 * 'shipping'  — Shipping charges. May or may not be commissionable
 *               depending on the site's Siren configuration.
 *
 * 'tax'       — Tax amounts. Typically excluded from commission calculation
 *               but recorded for audit trail.
 *
 * 'discount'  — Coupon/discount amounts. Value should be NEGATIVE (e.g., -500
 *               for a $5.00 discount).
 *
 * 'fee'       — Signup fees, processing fees, etc. Positive value.
 *
 * ── Collaborator Mapping ────────────────────────────────────────────────────
 *
 * The 'collaborators' attribute on product items links specific products to
 * specific collaborators. This is set up in the admin UI via the
 * ProductAdminService (or CouponAdminService for coupons).
 *
 * The mapping is stored in Siren's mapping table. Query it with:
 *   $this->mappings->andWhere([
 *       ['column' => 'externalId',   'operator' => '=', 'value' => $productId],
 *       ['column' => 'localType',    'operator' => '=', 'value' => 'collaborator'],
 *       ['column' => 'externalType', 'operator' => '=', 'value' => 'your_ext_product'],
 *   ])
 *
 * TODO: Replace 'your_ext_product' with your actual external type.
 * Convention: '{extension_id}_product' (e.g., 'wc_product', 'edd_product',
 * 'gf_product')
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 */
class OrderToTransactionDetailsAdapter
{
    protected FloatToIntPriceAdapter $priceAdapter;
    protected MappingDatastore $mappings;
    protected LoggerStrategy $logger;

    public function __construct(
        FloatToIntPriceAdapter $priceAdapter,
        MappingDatastore       $mappings,
        LoggerStrategy         $logger
    ) {
        $this->priceAdapter = $priceAdapter;
        $this->mappings = $mappings;
        $this->logger = $logger;
    }

    /**
     * Convert an order from the target plugin into Siren's transaction details format.
     *
     * TODO: Replace the method signature and body with your plugin's order model.
     *
     * Examples from existing extensions:
     *
     *   EDD:           toArray(int $orderId) — fetches order via edd_get_payment()
     *   WooCommerce:   toArray(int $orderId) — fetches order via wc_get_order()
     *   GravityForms:  toArray(array $entry, array $form) — receives entry/form arrays
     *
     * @param int $orderId The order ID in the target plugin.
     *
     * @return array<array{name: string, description: string, type: string, value: int, quantity: int, units: string, externalId: mixed, attributes?: array}>
     */
    public function toArray(int $orderId): array
    {
        $result = [];

        // TODO: Fetch the order from the target plugin.
        // Example:
        // $order = your_plugin_get_order($orderId);
        // if (!$order) {
        //     return $result;
        // }

        // TODO: Determine the currency.
        // Most plugins store this on the order or have a global setting.
        // Example: $currency = $order->getCurrency();
        $currency = 'USD';

        /*
         * TODO: Extract line items (products).
         *
         * Loop through the order's items and build the details array.
         * Each product line item needs:
         *   - name, description, value (in cents!), quantity
         *   - externalId (the product ID in the target plugin)
         *   - attributes with collaborators and categories
         *
         * Example:
         *
         * foreach ($order->getItems() as $item) {
         *     $productId = $item->getProductId();
         *     $result[] = [
         *         'name'        => $item->getName(),
         *         'description' => $item->getQuantity() . ' X ' . $item->getName(),
         *         'type'        => 'product',
         *         'value'       => $this->priceAdapter->toInt($item->getPrice()),
         *         'quantity'    => $item->getQuantity(),
         *         'units'       => $currency,
         *         'externalId'  => $productId,
         *         'attributes'  => [
         *             'collaborators' => $this->getProductCollaborators($productId),
         *             'categories'    => $this->getProductCategories($productId),
         *             'sku'           => $item->getSku() ?: null,
         *         ],
         *     ];
         * }
         */

        /*
         * TODO: Extract non-product line items.
         *
         * If the target plugin has shipping, tax, discounts, or fees,
         * include them as separate line items. Examples:
         *
         * // Shipping
         * $shippingTotal = $this->priceAdapter->toInt($order->getShippingTotal());
         * if ($shippingTotal > 0) {
         *     $result[] = [
         *         'name'        => 'Shipping',
         *         'description' => 'Shipping Fees',
         *         'type'        => 'shipping',
         *         'value'       => $shippingTotal,
         *         'quantity'    => 1,
         *         'units'       => $currency,
         *         'externalId'  => null,
         *     ];
         * }
         *
         * // Tax
         * $taxTotal = $this->priceAdapter->toInt($order->getTaxTotal());
         * if ($taxTotal > 0) {
         *     $result[] = [
         *         'name'        => 'Tax',
         *         'description' => 'Tax',
         *         'type'        => 'tax',
         *         'value'       => $taxTotal,
         *         'quantity'    => 1,
         *         'units'       => $currency,
         *         'externalId'  => null,
         *     ];
         * }
         *
         * // Discount (note: negative value!)
         * $discount = $this->priceAdapter->toInt($order->getDiscountTotal());
         * if ($discount > 0) {
         *     $result[] = [
         *         'name'        => 'Discount',
         *         'description' => 'Coupon Discount',
         *         'type'        => 'discount',
         *         'value'       => $discount * -1,
         *         'quantity'    => 1,
         *         'units'       => $currency,
         *         'externalId'  => null,
         *     ];
         * }
         */

        return $result;
    }

    /**
     * Get collaborator IDs assigned to a specific product.
     *
     * These mappings are created by the ProductAdminService when an admin
     * assigns a collaborator to a product in the target plugin's UI.
     *
     * TODO: Replace 'your_ext_product' with your external type.
     *
     * @param mixed $productId The product ID in the target plugin.
     *
     * @return array<int> Collaborator IDs.
     */
    protected function getProductCollaborators($productId): array
    {
        try {
            return Arr::pluck($this->mappings->andWhere([
                ['column' => 'externalId', 'operator' => '=', 'value' => $productId],
                ['column' => 'localType', 'operator' => '=', 'value' => 'collaborator'],
                ['column' => 'externalType', 'operator' => '=', 'value' => 'your_ext_product'], // TODO: Replace
            ]), 'localId');
        } catch (DatastoreErrorException $e) {
            $this->logger->logException($e);
            return [];
        }
    }
}
