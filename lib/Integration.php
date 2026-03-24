<?php

/*
 * TODO: Update this namespace
 * ─────────────────────────────────────────────────────────────────────────────
 * All Siren extensions live under Siren\WordPress\Extensions\{ExtensionName}.
 * Replace "YourExtension" with a PascalCase name matching the target plugin.
 *
 * Examples from existing extensions:
 *   - Siren\WordPress\Extensions\WooCommerce
 *   - Siren\WordPress\Extensions\EDD
 *   - Siren\WordPress\Extensions\LifterLMS
 *   - Siren\WordPress\Extensions\LearnDash
 *   - Siren\WordPress\Extensions\GravityForms
 *   - Siren\WordPress\Extensions\NorthCommerce
 *
 * This namespace MUST match the PSR-4 mapping in composer.json.
 * ─────────────────────────────────────────────────────────────────────────────
 */
namespace Siren\WordPress\Extensions\YourExtension;

use PHPNomad\Di\Interfaces\CanSetContainer;
use PHPNomad\Di\Traits\HasSettableContainer;
use PHPNomad\Events\Interfaces\HasEventBindings;
use PHPNomad\Events\Interfaces\HasListeners;
use PHPNomad\Loader\Interfaces\HasLoadCondition;
use PHPNomad\Loader\Interfaces\Loadable;
use Siren\Extensions\Core\Enums\Features;
use Siren\Extensions\Core\Interfaces\Extension;

/*
 * The Integration class: how Siren discovers and loads your extension
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * This is the heart of every Siren extension. It tells Siren:
 *   1. WHO you are        → getName(), getDescription(), getId()
 *   2. WHETHER to load     → canActivate(), shouldLoad()
 *   3. WHAT you support    → getSupports()
 *   4. HOW to wire events  → getEventBindings()
 *   5. WHAT to do on load  → load()
 *
 * ── Two types of extensions ────────────────────────────────────────────────
 *
 * Siren extensions fall into two categories:
 *
 * 1. INTEGRATIONS connect a third-party plugin (WooCommerce, LifterLMS,
 *    etc.) to Siren's commission system. They use getEventBindings() to
 *    map that plugin's hooks to Siren domain events like SaleTriggered.
 *    → If this is you, focus on the EVENT BINDINGS section and the
 *      Transformers/ and Adapters/ scaffolding.
 *
 * 2. CAPABILITY EXTENSIONS add new features to Siren itself — exports,
 *    custom admin pages, reporting tools, REST endpoints, etc. They don't
 *    bridge a third-party plugin; they extend what Siren can do.
 *    → If this is you, focus on the CAPABILITY HANDLER section in
 *      getEventBindings() and/or the LOADING section. You can delete
 *      the commerce event templates and the Transformers/Adapters scaffolding.
 *
 * Both types use the same Integration class and the same initializer
 * processing pipeline. The difference is what you put in getEventBindings()
 * and load().
 *
 * ── This class IS an initializer ─────────────────────────────────────────────
 *
 * In PHPNomad, an "initializer" is any class that implements a combination
 * of interfaces that the bootstrapper knows how to process. This Integration
 * class is an initializer. When Siren registers it via Extensions::add(),
 * the bootstrapper processes each interface in a defined order:
 *
 *   1. CanSetContainer  → Container is injected (gives you $this->container)
 *   2. HasLoadCondition → shouldLoad() is called; if false, STOP — skip everything
 *   3. HasEventBindings → getEventBindings() is called; hooks are wired to events
 *   4. HasListeners     → getListeners() is called; internal events wired to handlers
 *   5. Loadable         → load() is called LAST, after all registrations
 *
 * This ordering matters. By the time load() runs, the container is available
 * and all event bindings are registered. By the time event bindings run,
 * the container is available for resolving transformers.
 *
 * To learn more about how initializers work:
 *   https://phpnomad.com/core-concepts/bootstrapping/creating-and-managing-initializers
 *
 * ── Interfaces explained ─────────────────────────────────────────────────────
 *
 * Extension (Siren\Extensions\Core\Interfaces\Extension)
 *   The primary Siren contract. Extends Module, which requires getId() and
 *   getRootPath(). Adds getName(), getDescription(), canActivate(),
 *   getIsActive(), and getSupports().
 *
 * HasEventBindings (PHPNomad\Events\Interfaces\HasEventBindings)
 *   Declares getEventBindings(), which maps WordPress action hooks to Siren
 *   domain events. This is the bridge between the target plugin's hooks and
 *   Siren's event-driven architecture. The bootstrapper calls this method
 *   and registers each binding with the platform's action binding strategy.
 *
 *   HasEventBindings connects EXTERNAL events (WordPress hooks from the
 *   target plugin) to INTERNAL Siren domain events. Think of it as the
 *   inbound translation layer.
 *
 *   Learn more:
 *     https://phpnomad.com/core-concepts/bootstrapping/initializers/event-binding
 *
 * HasLoadCondition (PHPNomad\Loader\Interfaces\HasLoadCondition)
 *   Declares shouldLoad(). Checked EARLY in the processing order — if this
 *   returns false, the bootstrapper skips the entire initializer. No event
 *   bindings, no listeners, no load() call. Zero overhead when the target
 *   plugin isn't active.
 *
 * Loadable (PHPNomad\Loader\Interfaces\Loadable)
 *   Declares load(). Called LAST in the processing order, after all other
 *   interface methods have been processed. This is where you register
 *   WordPress hooks for admin UI, enqueue scripts, etc. — anything that
 *   isn't an event binding or listener.
 *
 *   Learn more:
 *     https://phpnomad.com/core-concepts/bootstrapping/creating-and-managing-initializers
 *
 * CanSetContainer (PHPNomad\Di\Interfaces\CanSetContainer)
 *   Processed FIRST. Siren injects its DI container so that all subsequent
 *   methods (getEventBindings, getListeners, load) can resolve services.
 *   The HasSettableContainer trait provides the implementation — it stores
 *   the container as $this->container.
 *
 *   Use $this->container->get(ServiceClass::class) to resolve dependencies.
 *   NEVER construct services manually — always resolve from the container.
 *
 * HasListeners (PHPNomad\Events\Interfaces\HasListeners)
 *   Declares getListeners(). Maps Siren's INTERNAL events to handler
 *   classes that implement CanHandle. Each handler gets its dependencies
 *   injected via constructor and has a single handle(Event): void method.
 *
 *   HasListeners is the complement to HasEventBindings:
 *     - HasEventBindings: external hooks → internal events (inbound)
 *     - HasListeners:     internal events → handler classes (reactive)
 *
 *   For capability extensions, this is the primary mechanism: listen for
 *   the Ready event and register WordPress hooks from your handler. For
 *   integrations, you may not need it — but the GravityForms extension
 *   shows a case where an integration uses both (it listens for Ready
 *   to initialize its GF Add-On).
 *
 *   Learn more:
 *     https://phpnomad.com/core-concepts/bootstrapping/initializers/event-listeners
 *
 * For a broader overview of PHPNomad's bootstrapping system:
 *   https://phpnomad.com/core-concepts/bootstrapping/introduction
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 */
class Integration implements Extension, HasEventBindings, HasListeners, HasLoadCondition, CanSetContainer, Loadable
{
    use HasSettableContainer;

    /**
     * Tracks whether this extension has been loaded.
     *
     * @var bool
     */
    protected bool $isActive = false;

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * IDENTITY
     * ─────────────────────────────────────────────────────────────────────────
     * These methods tell Siren who this extension is. They are used in the
     * admin UI, REST API, and internal logging.
     */

    /**
     * TODO: Return the human-readable name of the target plugin.
     *
     * This appears in the Siren admin UI in the extensions list.
     * Examples: 'WooCommerce', 'Easy Digital Downloads', 'LifterLMS',
     *           'LearnDash', 'Gravity Forms', 'North Commerce'
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Your Extension';
    }

    /**
     * TODO: Write a one-line description of what this extension enables.
     *
     * Convention: "Makes it possible to award engagements for actions within {Plugin}"
     * Or for form/lead plugins: "Integrates {Plugin} with Siren Affiliates,
     * enabling commission tracking for {what it does}"
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Integrates Your Extension with Siren Affiliates';
    }

    /**
     * TODO: Choose a short, unique ID for this extension.
     *
     * This is used internally as a registry key and for template paths
     * (e.g., 'wc::module/template' resolves templates relative to this
     * extension's root path).
     *
     * Existing IDs for reference:
     *   - 'wc'   → WooCommerce
     *   - 'edd'  → Easy Digital Downloads
     *   - 'llms' → LifterLMS
     *   - 'ld'   → LearnDash
     *   - 'gf'   → Gravity Forms
     *   - 'nc'   → North Commerce
     *
     * Keep it short (2-4 chars), lowercase, and based on the plugin's
     * common abbreviation.
     *
     * @return string
     */
    public static function getId(): string
    {
        return 'yourext';
    }

    /**
     * Returns the root path for this extension's templates and assets.
     *
     * TODO: Update the constant name to match the one in plugin.php.
     *
     * @return string
     */
    public function getRootPath(): string
    {
        return SIREN_YOUREXTENSION_ROOT;
    }

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * ACTIVATION
     * ─────────────────────────────────────────────────────────────────────────
     * These methods control whether the extension loads. In the initializer
     * processing order, HasLoadCondition is checked second (after the
     * container is injected). If shouldLoad() returns false, the bootstrapper
     * stops processing this initializer entirely — no event bindings, no
     * listeners, no load() call. This means there is zero runtime overhead
     * when the target plugin isn't installed.
     */

    /**
     * TODO: Detect whether the target plugin is installed and active.
     *
     * The standard approach is to check for a class that the target plugin
     * always defines. This works because WordPress loads all active plugins
     * before `siren_ready` fires.
     *
     * Examples from existing extensions:
     *   - WooCommerce:    class_exists('WooCommerce')
     *   - EDD:            class_exists('Easy_Digital_Downloads')
     *   - LifterLMS:      class_exists('LifterLMS')
     *   - LearnDash:      class_exists('SFWD_LMS')
     *   - GravityForms:   class_exists('GFForms') || class_exists('GFCommon')
     *   - NorthCommerce:  class_exists('North_Commerce')
     *
     * For some extensions you may also want to check a minimum version:
     *   return class_exists('TargetPlugin')
     *       && version_compare(TargetPlugin::VERSION, '3.0', '>=');
     *
     * @return bool
     */
    public function canActivate(): bool
    {
        // TODO: Replace with the target plugin's main class
        return class_exists('YourTargetPlugin');
    }

    /**
     * Determines whether Siren should load this extension.
     *
     * In most cases, just delegate to canActivate(). Override if you need
     * additional conditions (e.g., minimum version checks, license validation,
     * or feature flags).
     *
     * @return bool
     */
    public function shouldLoad(): bool
    {
        return $this->canActivate();
    }

    /**
     * Whether the extension has been loaded.
     *
     * @return bool
     */
    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * FEATURES
     * ─────────────────────────────────────────────────────────────────────────
     * Declare which Siren features this extension supports. This controls
     * which UI elements and behaviors are available when this extension is
     * active.
     */

    /**
     * TODO: Return the features this extension supports.
     *
     * Available features (defined in Siren\Extensions\Core\Enums\Features):
     *
     *   Features::Coupons        → Extension has a coupon/discount system
     *                               that Siren can attribute to collaborators.
     *                               Requires: CouponApplied event binding
     *                               Requires: CouponAdminService (UI for
     *                               linking coupons to collaborators)
     *
     *   Features::ManualOrdering → Extension supports admin-created orders
     *                               that should still trigger commissions.
     *                               Typically used by e-commerce extensions.
     *
     *   Features::Renewals       → Extension has subscription/recurring
     *                               payment support. Enables renewal
     *                               commission tracking.
     *                               Requires: RenewalTriggered event binding
     *
     *   Features::Courses        → Extension is an LMS with course tracking.
     *                               Enables course-completion engagements.
     *                               Requires: StudentCompletedCourse event
     *
     *   Features::Lessons        → Extension is an LMS with lesson tracking.
     *                               Enables lesson-completion engagements.
     *                               Requires: StudentCompletedLesson event
     *
     *   Features::Forms          → Extension is a form builder. Enables
     *                               form-submission-based lead and sale
     *                               tracking.
     *
     *   Features::Posts          → Extension creates content/posts that
     *                               can trigger engagements.
     *
     * Only include features that you actually implement event bindings for.
     * Declaring a feature without the corresponding event binding will cause
     * UI elements to appear with no backing functionality.
     *
     * @return array<string>
     */
    public function getSupports(): array
    {
        // TODO: Replace with the features your extension actually supports.
        // Common combinations:
        //
        // E-commerce plugin (WooCommerce, EDD, NorthCommerce):
        //   return [Features::Coupons, Features::ManualOrdering, Features::Renewals];
        //
        // LMS plugin (LifterLMS, LearnDash):
        //   return [Features::Courses, Features::Lessons];
        //   (LifterLMS also includes Features::Coupons and Features::Renewals)
        //
        // Form plugin (GravityForms):
        //   return [Features::Forms];

        return [];
    }

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * EVENT BINDINGS
     * ─────────────────────────────────────────────────────────────────────────
     * This is the most important method in the extension. It bridges
     * WordPress action hooks from the target plugin to Siren's domain events.
     *
     * In the initializer processing order, getEventBindings() is called
     * AFTER the container is injected and AFTER shouldLoad() passes. The
     * bootstrapper takes the returned array and registers each binding with
     * PHPNomad's ActionBindingStrategy, which wires the WordPress hook to
     * fire the corresponding Siren event.
     *
     * Learn more about how event bindings work in the PHPNomad framework:
     *   https://phpnomad.com/core-concepts/bootstrapping/initializers/event-binding
     *
     * HOW IT WORKS:
     *
     * Siren's event system is built on top of WordPress actions but adds a
     * transformation layer. Instead of your code running directly when
     * 'woocommerce_new_order' fires, Siren:
     *
     *   1. Listens to the WordPress action you specify
     *   2. Calls your transformer with the action's arguments
     *   3. Your transformer returns a Siren domain event (or null to skip)
     *   4. Siren dispatches that domain event through its pipeline
     *   5. The pipeline handles attribution, commission calculation, etc.
     *
     * This means your extension NEVER calculates commissions directly.
     * You just translate "a sale happened in {Plugin}" into Siren's
     * universal SaleTriggered event, and Siren handles the rest.
     *
     * THE TRANSFORMER PATTERN:
     *
     * Each transformer is a closure that receives the WordPress action's
     * arguments and returns a Siren event object (or null).
     *
     * The closure MUST resolve its service from the DI container:
     *   fn($orderId) => $this->container->get(MyTransformer::class)->transform($orderId)
     *
     * NEVER instantiate transformers directly. The container handles
     * dependency injection for the transformer's own dependencies
     * (datastores, adapters, loggers, etc.).
     *
     * RETURN FORMAT:
     *
     * [
     *     SirenEventClass::class => [
     *         ['action' => 'wordpress_hook_name', 'transformer' => $callback],
     *         ['action' => 'another_hook',        'transformer' => $callback],
     *     ],
     * ]
     *
     * Multiple hooks can map to the same event (e.g., WooCommerce fires
     * SaleTriggered on both 'woocommerce_new_order' and
     * 'woocommerce_order_status_completed').
     */

    /**
     * Maps WordPress hooks from the target plugin to Siren domain events.
     *
     * @return array<class-string, array<array{action: string, transformer: callable}>>
     */
    public function getEventBindings(): array
    {
        /*
         * TODO: Define your event bindings.
         *
         * Below are templates for each Siren domain event. Uncomment and
         * implement the ones relevant to your target plugin.
         *
         * For each event you implement, you will need:
         *   1. A Transformer class in lib/Transformers/
         *   2. Possibly an Adapter class in lib/Adapters/
         *   3. Knowledge of which WordPress hooks the target plugin fires
         *
         * See the scaffolded files in Transformers/ and Adapters/ for
         * detailed guidance on implementing each one.
         */

        // ── Transformer callbacks ────────────────────────────────────────
        // Resolve transformers from the DI container. The container injects
        // all dependencies (datastores, adapters, loggers) automatically.
        //
        // Example:
        // $saleTransformer = fn($orderId) => $this->container->get(
        //     SaleTriggeredTransformer::class
        // )->getSaleTriggeredEvent($orderId);

        return [
            /*
             * ── SaleTriggered ────────────────────────────────────────────
             * Fires when a new sale/order/transaction is created in the
             * target plugin. This is the most common event and is required
             * for any e-commerce integration.
             *
             * Use: Siren\Commerce\Events\SaleTriggered
             *
             * Constructor args:
             *   1. int    $opportunityId  — The Siren opportunity (tracked visit)
             *   2. array  $details        — Line items (see Adapters/ scaffold)
             *   3. string $source         — Your extension ID (e.g., 'wc', 'edd')
             *   4. mixed  $externalId     — The order/transaction ID in the target plugin
             *   5. string $externalType   — Type label (e.g., 'wc_order', 'edd_order')
             *
             * Existing hook examples:
             *   WooCommerce:   'woocommerce_new_order',
             *                  'woocommerce_order_status_processing',
             *                  'woocommerce_order_status_completed'
             *   EDD:           'edd_insert_payment', 'edd_post_add_manual_order'
             *   LifterLMS:     'lifterlms_new_pending_order'
             *   LearnDash:     'learndash_transaction_created'
             *   GravityForms:  'gform_after_submission'
             *   NorthCommerce: 'nc_db_event/order.purchase'
             */
            // SaleTriggered::class => [
            //     ['action' => 'your_plugin_order_created', 'transformer' => $saleTransformer],
            // ],

            /*
             * ── TransactionCompleted ─────────────────────────────────────
             * Fires when an order transitions to a "completed" or "paid"
             * status. This triggers the approval step in Siren's pipeline,
             * which finalizes the commission.
             *
             * SaleTriggered creates the transaction record.
             * TransactionCompleted approves it.
             *
             * In some plugins (e.g., LearnDash), both fire on the same hook
             * because the sale is immediately complete. In others (e.g.,
             * WooCommerce), they fire on different hooks because orders go
             * through status transitions.
             *
             * Use: Siren\Commerce\Events\TransactionCompleted
             */
            // TransactionCompleted::class => [
            //     ['action' => 'your_plugin_order_completed', 'transformer' => $transactionCompletedTransformer],
            // ],

            /*
             * ── RefundTriggered ──────────────────────────────────────────
             * Fires when a refund or cancellation occurs. This reverses
             * the commission associated with the original transaction.
             *
             * Map ALL hooks that represent a refund, cancellation, or
             * status change that should claw back a commission. Err on
             * the side of covering more status transitions.
             *
             * Use: Siren\Commerce\Events\RefundTriggered
             *
             * WooCommerce example (covers many transitions):
             *   'woocommerce_order_status_completed_to_failed'
             *   'woocommerce_order_status_completed_to_cancelled'
             *   'woocommerce_order_status_completed_to_refunded'
             *   'wc-completed_to_trash'
             */
            // RefundTriggered::class => [
            //     ['action' => 'your_plugin_order_refunded', 'transformer' => $refundTransformer],
            // ],

            /*
             * ── CouponApplied ────────────────────────────────────────────
             * Fires when a coupon/discount code is applied during checkout.
             * Used for coupon-based attribution (collaborator owns a coupon).
             *
             * Requires: Features::Coupons in getSupports()
             * Requires: CouponAdminService in load()
             *
             * Use: Siren\Commerce\Events\CouponApplied
             *
             * Note: Some plugins (e.g., LifterLMS) don't have a separate
             * "coupon applied" hook. In that case, handle coupon detection
             * inside the SaleTriggeredTransformer instead.
             */
            // CouponApplied::class => [
            //     ['action' => 'your_plugin_coupon_applied', 'transformer' => $couponTransformer],
            // ],

            /*
             * ── RenewalTriggered ─────────────────────────────────────────
             * Fires when a subscription payment renews. Used for recurring
             * commission tracking.
             *
             * Requires: Features::Renewals in getSupports()
             * The target plugin must have a subscription/recurring system.
             *
             * Use: Siren\Commerce\Events\RenewalTriggered
             *
             * Note: Some renewal systems are add-ons to the main plugin
             * (e.g., EDD Recurring Payments, WooCommerce Subscriptions).
             * Use class_exists() to conditionally add these bindings.
             */
            // RenewalTriggered::class => [
            //     ['action' => 'your_plugin_subscription_renewed', 'transformer' => $renewalTransformer],
            // ],

            /*
             * ── LMS Events ───────────────────────────────────────────────
             * For Learning Management System integrations. These track
             * course and lesson completions as engagement events.
             *
             * Requires: Features::Courses and/or Features::Lessons
             *
             * Use: Siren\LMS\Core\Events\StudentCompletedCourse
             * Use: Siren\LMS\Core\Events\StudentCompletedLesson
             */
            // StudentCompletedCourse::class => [
            //     ['action' => 'your_lms_course_completed', 'transformer' => $courseCompleteTransformer],
            // ],
            // StudentCompletedLesson::class => [
            //     ['action' => 'your_lms_lesson_completed', 'transformer' => $lessonCompleteTransformer],
            // ],

            /*
             * ── Lead Events ──────────────────────────────────────────────
             * For form/lead-generation integrations. Tracks form submissions
             * that don't involve payment (contact forms, quote requests, etc.)
             *
             * Requires: Features::Forms
             *
             * Use: Siren\Commerce\Events\LeadTriggered
             */
            // LeadTriggered::class => [
            //     ['action' => 'your_form_submitted', 'transformer' => $leadTransformer],
            // ],

        ];
    }

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * EVENT LISTENERS (CAPABILITY EXTENSIONS)
     * ─────────────────────────────────────────────────────────────────────────
     * HasListeners is HasEventBindings' complement:
     *
     *   - HasEventBindings: EXTERNAL hooks (WordPress) → INTERNAL events
     *   - HasListeners:     INTERNAL events → handler classes (CanHandle)
     *
     * For integrations, you primarily use HasEventBindings (above) to map
     * the target plugin's hooks to Siren domain events.
     *
     * For capability extensions, you use HasListeners to register handler
     * classes that fire when Siren events occur. The most common pattern
     * is listening for Ready (which fires after Siren finishes bootstrapping)
     * and registering WordPress hooks from inside the handler.
     *
     * Each handler:
     *   - Implements CanHandle (PHPNomad\Events\Interfaces\CanHandle)
     *   - Gets all its dependencies injected via constructor
     *   - Has a single handle(Event $event): void method
     *   - Registers WordPress hooks inside handle()
     *
     * The container resolves the handler and all its dependencies
     * automatically. This is why the handler pattern is powerful — your
     * WordPress hook callbacks have full access to Siren's services
     * through the handler's injected properties.
     *
     * You can register as many handlers as you need against any Siren
     * event. Ready is the most common for capability extensions, but
     * you can listen to any event in Siren's domain.
     *
     * Learn more:
     *   https://phpnomad.com/core-concepts/bootstrapping/initializers/event-listeners
     */

    /**
     * Maps Siren internal events to handler classes.
     *
     * @return array<class-string<Event>, class-string<CanHandle>[]|class-string<CanHandle>>
     */
    public function getListeners(): array
    {
        /*
         * TODO: Register your handlers here.
         *
         * For capability extensions, listen to Ready and register your
         * handler classes. Each handler implements CanHandle and receives
         * its dependencies via constructor injection.
         *
         * You can register multiple handlers per event, and multiple
         * events. Each handler is a focused class for one concern.
         *
         * See lib/Handlers/AdminHandler.php for the scaffolded example.
         *
         * Example:
         *
         * use PHPNomad\Core\Events\Ready;
         * use Siren\WordPress\Extensions\YourExtension\Handlers\AdminHandler;
         *
         * return [
         *     Ready::class => [
         *         AdminHandler::class,
         *     ],
         * ];
         *
         * Multiple handlers example:
         *
         * return [
         *     Ready::class => [
         *         AdminHandler::class,
         *         RestHandler::class,
         *         FrontendHandler::class,
         *     ],
         * ];
         */

        return [];
    }

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * LOADING
     * ─────────────────────────────────────────────────────────────────────────
     * The load() method runs LAST in the initializer processing order. By
     * the time it's called:
     *   - The container has been injected (CanSetContainer)
     *   - shouldLoad() has passed (HasLoadCondition)
     *   - All event bindings are registered (HasEventBindings)
     *   - All listeners are registered (HasListeners, if implemented)
     *
     * This is where you register WordPress hooks for admin UI, enqueue
     * scripts, etc. — anything that needs to happen at WordPress runtime
     * but isn't an event binding or listener.
     *
     * IMPORTANT: Event bindings are handled separately by getEventBindings().
     * You do NOT need to register action hooks for events here.
     *
     * Learn more:
     *   https://phpnomad.com/core-concepts/bootstrapping/creating-and-managing-initializers
     */

    /**
     * Called when the extension is loaded.
     *
     * @return void
     */
    public function load(): void
    {
        $this->isActive = true;

        /*
         * load() vs handlers: when to use which
         * ─────────────────────────────────────────────────────────────────
         *
         * load() is good for simple, self-contained setup:
         *   - One or two add_action/add_filter calls
         *   - Anything that doesn't need complex logic or many hooks
         *
         * Handlers (via getListeners) are better when:
         *   - Multiple related hooks share the same injected services
         *   - The setup logic is complex enough to deserve its own class
         *   - You want clean separation of concerns
         *
         * Both patterns have full access to Siren's container. The
         * difference is organizational: load() keeps simple things inline,
         * handlers keep complex things in dedicated classes with proper
         * constructor-injected dependencies.
         *
         * See lib/Handlers/AdminHandler.php for the handler pattern.
         */

        /*
         * TODO: Add simple load-time setup here.
         *
         * Examples:
         *
         *   // A simple admin menu item
         *   add_action('admin_menu', fn() => $this->container->get(
         *       SomeService::class
         *   )->registerAdminPage());
         *
         *   // Enqueue a script
         *   add_action('admin_enqueue_scripts', fn() => $this->container->get(
         *       SomeService::class
         *   )->enqueueAssets());
         *
         * For anything more complex, use a handler (see getListeners).
         */
    }
}
