# Siren Extension — Agent Instructions

Read this file at the start of every session.

---

## What This Is

This is a **Siren Affiliates extension** — a standalone WordPress plugin that extends Siren's capabilities. It does NOT run independently; it requires the Siren Affiliates plugin to be active.

Extensions fall into two categories:

1. **Integrations** connect a third-party plugin (WooCommerce, LifterLMS, etc.) to Siren's commission system. They map that plugin's hooks to Siren domain events.

2. **Capability extensions** add new features to Siren itself — exports, custom admin pages, reporting tools, REST endpoints, etc. They don't bridge a third-party plugin; they extend what Siren can do.

Both types use the same Integration class and initializer pipeline. The difference is what you put in `getEventBindings()` and `load()`. This template has scaffolding for both — delete whichever sections don't apply to your use case.

## How Siren Extensions Work

Siren is built on [PHPNomad](https://phpnomad.com/core-concepts/bootstrapping/introduction), a platform-agnostic framework. Your extension's Integration class is a PHPNomad **initializer** — a class that implements a combination of interfaces the bootstrapper knows how to process.

For **integrations**, your job is to:

1. **Detect** that the target plugin is installed (`canActivate()`)
2. **Listen** to the target plugin's WordPress hooks
3. **Transform** those hooks into Siren domain events (SaleTriggered, RefundTriggered, etc.)
4. **Let Siren handle the rest** — attribution, commissions, payouts

For **capability extensions**, your job is to:

1. **Bootstrap** your feature using Siren's DI container
2. **Register** WordPress hooks (admin pages, REST routes, filters, etc.)
3. **Use** Siren's services (datastores, collaborator data, etc.) via the container

### The Initializer Processing Order

When Siren loads your Integration, the bootstrapper processes its interfaces in this exact order:

1. **CanSetContainer** → DI container injected (gives you `$this->container`)
2. **HasLoadCondition** → `shouldLoad()` checked; if false, everything stops
3. **HasEventBindings** → WordPress hooks wired to Siren domain events
4. **HasListeners** → Internal Siren events wired to handler classes
5. **Loadable** → `load()` called last, after all registrations

This ordering matters. By the time `load()` runs, the container is available and all event bindings are registered. By the time event bindings run, the container is available for resolving transformers.

Learn more: [Creating and Managing Initializers](https://phpnomad.com/core-concepts/bootstrapping/creating-and-managing-initializers)

## Architecture Rules

### WordPress is the ONLY platform here

Unlike Siren core (which is platform-agnostic), extensions are WordPress-specific by nature. You ARE integrating two WordPress plugins. Using WordPress APIs directly is expected and correct.

### All classes live in `lib/`

The namespace is `Siren\WordPress\Extensions\{ExtensionName}\` and maps to `lib/` via PSR-4 in `composer.json`.

### Follow the established patterns

Every extension follows the same structure. Before writing code, study the existing extensions in the `siren-fresh` repository at `lib/WordPress/Extensions/`. The patterns for WooCommerce, EDD, LifterLMS, LearnDash, GravityForms, and NorthCommerce are the canonical references.

### Use the DI container — never facades

Never instantiate services or transformers directly. Always resolve from the container:

```php
$this->container->get(MyService::class)->doSomething();
```

The container is injected into the Integration class via `CanSetContainer`. Transformers and services get their dependencies via constructor injection.

### Do NOT use Siren facades

**Using any Siren facade (e.g., `Collaborators::`, `Engagements::`, `Configs::`) inside extension code is a code smell and almost certainly wrong.** The only place a facade appears is in `plugin.php` where `Extensions::add()` registers the extension — that's template-provided code, not something you write.

Facades bypass the DI container and create hidden coupling. In an extension, you always have access to the container through `$this->container` (in Integration) or through constructor injection (in Transformers, Adapters, Services). Use those instead.

If you find yourself reaching for a facade, stop and ask: "What service am I actually trying to use?" Then inject that service through the constructor. For example:

```php
// WRONG — facade usage in a transformer
use Siren\Collaborators\Core\Facades\Collaborators;
$collaborator = Collaborators::getCollaboratorFromUserId($userId);

// RIGHT — constructor injection
public function __construct(
    CollaboratorDatastore $collaborators,
    // ... other deps
) {
    $this->collaborators = $collaborators;
}
// Then use $this->collaborators->getByUserId($userId)
```

If you see facade usage in an existing extension (there is one case in WooCommerce's Integration.php), treat it as tech debt to avoid repeating, not a pattern to follow.

## Key Concepts

### Event Bindings vs Listeners

These are two different interfaces that serve complementary roles:

- **HasEventBindings** — Maps EXTERNAL events (WordPress hooks from the target plugin) to INTERNAL Siren domain events. This is the inbound translation layer. Most of your work happens here. [Docs](https://phpnomad.com/core-concepts/bootstrapping/initializers/event-binding)

- **HasListeners** — Maps INTERNAL Siren events to handler classes that react to them. Use this only when you need to respond to something Siren itself fires (e.g., GravityForms initializes its GF Add-On when Siren's `Ready` event fires). Most extensions don't need this. [Docs](https://phpnomad.com/core-concepts/bootstrapping/initializers/event-listeners)

### Events

Siren domain events are the core of the system:

| Event | When to fire | Required for |
|-------|-------------|--------------|
| `SaleTriggered` | New order/purchase created | Any e-commerce integration |
| `TransactionCompleted` | Order marked as paid/complete | Finalizing commissions |
| `RefundTriggered` | Order refunded or cancelled | Reversing commissions |
| `CouponApplied` | Discount code used at checkout | Coupon-based attribution |
| `RenewalTriggered` | Subscription payment renewed | Recurring commissions |
| `StudentCompletedCourse` | Student finishes a course | LMS course tracking |
| `StudentCompletedLesson` | Student finishes a lesson | LMS lesson tracking |
| `LeadTriggered` | Form submitted (no payment) | Lead-gen attribution |

### Transformers

Transformers convert WordPress hook arguments into Siren events. They:
- Receive the raw hook arguments
- Find the Siren opportunity (tracked visitor)
- Guard against duplicates
- Return a Siren event object (or null to skip)

### Adapters

Adapters convert the target plugin's data structures into Siren's format. The most important one converts orders into "transaction details" — a standardized array of line items with prices in **cents** (not dollars).

### Features

The `getSupports()` method declares what the extension supports. Only declare features you actually implement. See `Siren\Extensions\Core\Enums\Features` for the full list.

### Mappings (integrations only)

Siren tracks the relationship between its internal records and external plugin records through a mapping table. Key external types to define:
- `{ext_id}_order` — for order/transaction mappings
- `{ext_id}_product` — for product-collaborator assignments

### Handlers (capability extensions)

When your extension needs container access across multiple WordPress hooks, use PHPNomad's **event handler pattern**:

1. Create a handler class in `lib/Handlers/` that implements `CanHandle`
2. Inject dependencies via the constructor (the container resolves them)
3. Implement `handle(Event $event): void` — register WordPress hooks inside
4. Register the handler in `getListeners()` against a Siren event (typically `Ready`)

The container builds the handler with all its dependencies, then calls `handle()`. Every WordPress hook callback registered inside has access to `$this` and all injected services.

You can create as many handlers as you need — each is a focused class for one concern. See `lib/Handlers/AdminHandler.php` for the scaffolded example.

For simple cases (one or two hooks), `load()` is fine. Use handlers when the logic deserves its own class.

Learn more: [Event Listeners](https://phpnomad.com/core-concepts/bootstrapping/initializers/event-listeners)

## Knowledgebase Access

This project depends on PHPNomad framework patterns. Before writing framework-level code, check the knowledgebase:

```bash
navigator kb search "phpnomad" --json
navigator kb search "coding standards" --initiative=phpnomad --json
navigator kb search "siren extension" --initiative=siren --json
```

## File Structure

```
plugin.php                              WordPress plugin entry point
lib/
  Integration.php                       Extension registration and configuration

  Handlers/                             ← Capability extensions (implement CanHandle)
    AdminHandler.php                    Container-injected WordPress hook registration
    (+ RestHandler, FrontendHandler, etc. as needed)

  Transformers/                         ← Integrations
    SaleTriggeredTransformer.php        Order → SaleTriggered event
    (+ other transformers as needed)

  Adapters/                             ← Integrations
    OrderToTransactionDetailsAdapter.php Order → line items array
    (+ other adapters as needed)
```

Delete the directories that don't apply to your extension type. A capability extension typically only needs `Handlers/`. An integration typically only needs `Transformers/` and `Adapters/`.

## When You Need Context

**PHPNomad framework documentation (public):**
- [Bootstrapping overview](https://phpnomad.com/core-concepts/bootstrapping/introduction)
- [Initializers (how Integration classes are processed)](https://phpnomad.com/core-concepts/bootstrapping/creating-and-managing-initializers)
- [Event bindings (external hooks → internal events)](https://phpnomad.com/core-concepts/bootstrapping/initializers/event-binding)
- [Event listeners (internal events → handler classes)](https://phpnomad.com/core-concepts/bootstrapping/initializers/event-listeners)
- [Platform integrations](https://phpnomad.com/core-concepts/bootstrapping/platform-integrations)

**Siren codebase references:**
- **Extension patterns:** Study `siren-fresh/lib/WordPress/Extensions/` for canonical examples
- **Siren events:** Check `siren-fresh/lib/Commerce/Events/` for event constructors
- **Extension interface:** `siren-fresh/lib/Extensions/Core/Interfaces/Extension.php`
- **Features enum:** `siren-fresh/lib/Extensions/Core/Enums/Features.php`
- **Navigator journal:** Check recent journals for context on this specific extension

## Mindset

**Be timid, not confident.** When uncertain about how the target plugin works, read its source code. Don't guess at hook names or argument signatures. Verify them.
