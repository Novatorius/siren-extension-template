<?php

namespace Siren\WordPress\Extensions\YourExtension\Transformers;

use PHPNomad\Datastore\Exceptions\DatastoreErrorException;
use PHPNomad\Datastore\Exceptions\RecordNotFoundException;
use PHPNomad\Logger\Interfaces\LoggerStrategy;
use Siren\Commerce\Events\SaleTriggered;
use Siren\Mappings\Core\Datastores\Mapping\Interfaces\MappingDatastore;
use Siren\Opportunities\Core\Interfaces\OpportunityLocatorService;
use Siren\Opportunities\Core\Models\Opportunity;
use Siren\Opportunities\Service\LocatorGroups\VisitorOpportunity;

/*
 * SaleTriggeredTransformer: Translating a sale into Siren's domain
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * This transformer is called by Siren's event system when the WordPress hook
 * you mapped to SaleTriggered fires. Its job:
 *
 *   1. Receive the raw arguments from the WordPress action hook
 *   2. Find the Siren "opportunity" (the tracked visitor session)
 *   3. Guard against duplicate processing
 *   4. Build and return a SaleTriggered event
 *
 * The SaleTriggered event is the starting point for Siren's commission
 * pipeline. Once dispatched, Siren handles attribution, distribution rules,
 * commission calculation, and payout — your extension doesn't need to
 * touch any of that.
 *
 * ── Opportunity Locator ──────────────────────────────────────────────────────
 *
 * Siren tracks visitors through "opportunities" — a visitor clicks an
 * affiliate link, and Siren creates an opportunity record. When a sale
 * happens, we need to find the opportunity that led to it.
 *
 * OpportunityLocatorService checks multiple sources (cookies, user meta,
 * session data) using a chain of locator strategies. The VisitorOpportunity
 * locator group provides the standard set of strategies for front-end
 * visitor tracking.
 *
 * You provide the WordPress user ID associated with the order, and the
 * locator does the rest.
 *
 * ── Duplicate Prevention ─────────────────────────────────────────────────────
 *
 * WordPress hooks can fire multiple times for the same order (e.g.,
 * WooCommerce fires on both 'woocommerce_new_order' and
 * 'woocommerce_order_status_processing'). The mapping check prevents
 * creating duplicate transactions.
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 */
class SaleTriggeredTransformer
{
    protected OpportunityLocatorService $opportunityLocator;
    protected VisitorOpportunity $visitorLocators;
    protected MappingDatastore $mappings;
    protected LoggerStrategy $logger;

    /*
     * TODO: Add your adapter as a dependency.
     *
     * If your target plugin has order/transaction objects that need to be
     * converted to Siren's transaction detail format, inject your adapter
     * here. See Adapters/OrderToTransactionDetailsAdapter.php for the
     * expected output format.
     *
     * Example:
     *   protected OrderToTransactionDetailsAdapter $detailsAdapter;
     */

    public function __construct(
        OpportunityLocatorService $opportunityLocator,
        VisitorOpportunity        $visitorLocators,
        MappingDatastore          $mappings,
        LoggerStrategy            $logger
        // TODO: Add your adapter parameter here
        // OrderToTransactionDetailsAdapter $adapter,
    ) {
        $this->opportunityLocator = $opportunityLocator;
        $this->visitorLocators = $visitorLocators;
        $this->mappings = $mappings;
        $this->logger = $logger;
        // $this->detailsAdapter = $adapter;
    }

    /**
     * Locate the Siren opportunity associated with this order.
     *
     * TODO: Adapt this to your target plugin's order/user model.
     *
     * You need to extract the WordPress user ID from the order. How you
     * do this depends on the target plugin:
     *
     *   WooCommerce:   $order = wc_get_order($orderId); $userId = $order->get_user_id();
     *   EDD:           $order = edd_get_payment($orderId); $userId = $order->user_id;
     *   LifterLMS:     $order->get('user_id')
     *   GravityForms:  $entry['created_by']
     *
     * Pass the user ID to the visitor locator. It will check cookies, user
     * meta, and other attribution sources to find the opportunity.
     *
     * @param mixed $orderId The order/transaction ID from the target plugin.
     *
     * @return Opportunity|null The located opportunity, or null if no attribution found.
     */
    protected function locateOpportunity($orderId): ?Opportunity
    {
        // TODO: Replace with your plugin's order retrieval and user ID extraction
        // Example:
        // $order = your_plugin_get_order($orderId);
        // $userId = $order ? $order->getUserId() : null;
        $userId = null;

        return $this->opportunityLocator->locateUsing(
            ...$this->visitorLocators->build($userId)
        );
    }

    /**
     * Check whether this order has already been recorded as a transaction.
     *
     * This prevents duplicate commission creation when WordPress fires
     * multiple hooks for the same order.
     *
     * TODO: Update the external type to match your extension.
     * Convention: '{extension_id}_order' (e.g., 'wc_order', 'edd_order')
     *
     * @param int $orderId
     *
     * @return bool
     */
    protected function orderHasTransaction(int $orderId): bool
    {
        try {
            // TODO: Replace 'your_ext_order' with your external type
            $this->mappings->getByExternalId($orderId, 'your_ext_order', 'transaction');
            return true;
        } catch (RecordNotFoundException $e) {
            // Expected for new orders — no mapping exists yet
        } catch (DatastoreErrorException $e) {
            $this->logger->logException($e);
        }

        return false;
    }

    /**
     * Transform a WordPress hook call into a SaleTriggered event.
     *
     * TODO: Update the method signature to match your WordPress hook's args.
     *
     * The arguments this method receives come directly from the WordPress
     * do_action() call. Check the target plugin's source code or
     * documentation to know what arguments the hook passes.
     *
     * Examples:
     *   WooCommerce 'woocommerce_new_order':       ($orderId)
     *   EDD 'edd_insert_payment':                   ($orderId, $orderData)
     *   LifterLMS 'lifterlms_new_pending_order':    ($order)
     *   GravityForms 'gform_after_submission':      ($entry, $form)
     *   LearnDash 'learndash_transaction_created':  ($transactionId)
     *
     * @param mixed $orderId The primary argument from the WordPress hook.
     *
     * @return SaleTriggered|null The event, or null to skip (no attribution, duplicate, etc.)
     */
    public function getSaleTriggeredEvent($orderId): ?SaleTriggered
    {
        // Validate input
        if (!is_int($orderId)) {
            return null;
        }

        // Find the opportunity (affiliate attribution) for this order
        $opportunity = $this->locateOpportunity($orderId);
        if (!$opportunity) {
            return null;
        }

        // Don't create duplicate transactions
        if ($this->orderHasTransaction($orderId)) {
            return null;
        }

        /*
         * Build and return the SaleTriggered event.
         *
         * Arguments:
         *   1. Opportunity ID — links the sale to the tracked visit
         *   2. Transaction details — line items array (see Adapters/)
         *   3. Source — your extension ID, matching Integration::getId()
         *   4. External ID — the order ID in the target plugin
         *   5. External type — label for the mapping (e.g., 'wc_order')
         *
         * The transaction details array is critical. Use an adapter to
         * convert the target plugin's order into Siren's expected format.
         * See Adapters/OrderToTransactionDetailsAdapter.php.
         */
        return new SaleTriggered(
            $opportunity->getId(),
            [], // TODO: Replace with $this->detailsAdapter->toArray($orderId)
            'yourext', // TODO: Replace with your extension ID
            $orderId,
            'your_ext_order' // TODO: Replace with your external type
        );
    }
}
