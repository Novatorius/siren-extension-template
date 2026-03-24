<?php

namespace Siren\WordPress\Extensions\YourExtension\Handlers;

use PHPNomad\Events\Interfaces\CanHandle;
use PHPNomad\Events\Interfaces\Event;

/*
 * AdminHandler: A container-injected event handler
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * This is a PHPNomad event handler. It implements CanHandle, which gives it
 * a single method: handle(Event $event). The container resolves all of the
 * handler's constructor dependencies automatically.
 *
 * HOW IT CONNECTS:
 *
 * In Integration.php, you implement HasListeners and register this handler
 * against a PHPNomad event (typically Ready, which fires after Siren
 * finishes bootstrapping):
 *
 *   public function getListeners(): array
 *   {
 *       return [
 *           Ready::class => [
 *               AdminHandler::class,
 *           ],
 *       ];
 *   }
 *
 * When the Ready event fires, the container builds this handler with all
 * its dependencies injected, then calls handle(). Inside handle(), you
 * register whatever WordPress hooks your extension needs.
 *
 * Learn more about event listeners:
 *   https://phpnomad.com/core-concepts/bootstrapping/initializers/event-listeners
 *
 * WHY THIS PATTERN EXISTS:
 *
 * Siren's DI container holds all of the services your extension might need:
 * datastores, configuration, collaborator data, engagement records, etc.
 * The handler pattern gets container-resolved services into your WordPress
 * hook callbacks. Every callback in handle() has access to $this and all
 * its injected dependencies.
 *
 * WHEN TO USE THIS vs load():
 *
 *   load() is fine for one or two simple add_action/add_filter calls.
 *
 *   A handler is better when:
 *     - Multiple related hooks share the same injected services
 *     - The setup logic is complex enough to deserve its own class
 *     - You want clean separation of concerns
 *
 * YOU CAN CREATE AS MANY HANDLERS AS YOU NEED:
 *
 * Each handler is a separate class registered in getListeners(). Example:
 *
 *   Ready::class => [
 *       AdminHandler::class,
 *       RestHandler::class,
 *       FrontendHandler::class,
 *   ],
 *
 * Each handler is focused on one concern. The container resolves each
 * one independently with its own dependencies.
 *
 * REAL EXAMPLE — GravityForms extension:
 *
 * The GravityForms extension uses this exact pattern. Its
 * InitializeGravityFormsAddon handler listens for Ready, then calls
 * add_action('gform_loaded', ...) to register its GF Add-On.
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 */

/*
 * TODO: Inject your dependencies via the constructor.
 *
 * The DI container resolves this class, so any interface or concrete class
 * registered in the container can be injected here. Common deps:
 *
 * use PHPNomad\Logger\Interfaces\LoggerStrategy;
 * use Siren\Collaborators\Core\Datastores\Collaborator\Interfaces\CollaboratorDatastore;
 * use Siren\Engagements\Core\Datastores\Engagement\Interfaces\EngagementDatastore;
 * use Siren\Transactions\Core\Datastores\Transaction\Interfaces\TransactionDatastore;
 */

/**
 * Handles bootstrapping WordPress hooks with container-injected services.
 *
 * @implements CanHandle<\PHPNomad\Core\Events\Ready>
 */
class AdminHandler implements CanHandle
{
    /*
     * TODO: Add constructor with injected dependencies.
     *
     * Example:
     *
     * protected CollaboratorDatastore $collaborators;
     * protected LoggerStrategy $logger;
     *
     * public function __construct(
     *     CollaboratorDatastore $collaborators,
     *     LoggerStrategy        $logger
     * ) {
     *     $this->collaborators = $collaborators;
     *     $this->logger = $logger;
     * }
     */

    /**
     * Handle the event by registering WordPress hooks.
     *
     * This fires when Siren's Ready event broadcasts (after bootstrapping
     * is complete). Register all WordPress hooks your feature needs here.
     * Every callback has access to $this and all its injected dependencies.
     *
     * @param Event $event The Ready event instance.
     *
     * @return void
     */
    public function handle(Event $event): void
    {
        /*
         * TODO: Register your WordPress hooks here.
         *
         * Examples of what a capability extension handler might do:
         */

        // ── Admin menu page ──────────────────────────────────────────────
        // add_action('admin_menu', function () {
        //     add_submenu_page(
        //         'siren',                            // Parent slug (Siren's menu)
        //         'My Feature',                       // Page title
        //         'My Feature',                       // Menu title
        //         'manage_options',                    // Capability
        //         'siren-my-feature',                  // Menu slug
        //         [$this, 'renderAdminPage']           // $this is available here!
        //     );
        // });

        // ── Enqueue admin scripts/styles ─────────────────────────────────
        // add_action('admin_enqueue_scripts', function (string $hookSuffix) {
        //     if ($hookSuffix !== 'siren_page_siren-my-feature') {
        //         return;
        //     }
        //     wp_enqueue_style(
        //         'siren-my-feature',
        //         plugin_dir_url(SIREN_YOUREXTENSION_ROOT) . 'public/css/admin.css'
        //     );
        // });

        // ── AJAX endpoint ────────────────────────────────────────────────
        // add_action('wp_ajax_siren_my_feature_action', function () {
        //     // Injected services available via $this
        //     // $data = $this->collaborators->getAll();
        //     // wp_send_json_success($data);
        // });

        // ── Filters ──────────────────────────────────────────────────────
        // add_filter('some_siren_filter', function ($value) {
        //     // Modify Siren behavior using injected services
        //     return $value;
        // });
    }

    /*
     * TODO: Add methods for your hook callbacks.
     *
     * You can reference methods on $this from your hook callbacks above.
     * This keeps handle() clean and puts the actual logic in dedicated
     * methods.
     *
     * Example:
     *
     * public function renderAdminPage(): void
     * {
     *     $collaborators = $this->collaborators->where([]);
     *     include plugin_dir_path(SIREN_YOUREXTENSION_ROOT) . 'public/admin-page.php';
     * }
     */
}
