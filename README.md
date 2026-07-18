# Siren Extension Template

A template for building WordPress plugin extensions for [Siren Affiliates](https://sirenaffiliates.com).

## Extension Types

This template supports two types of extensions:

- **Integrations** — Connect a third-party plugin (WooCommerce, MemberPress, etc.) to Siren's commission system. Uses Transformers and Adapters to map plugin hooks to Siren domain events.

- **Capability extensions** — Add new features to Siren itself (exports, admin tools, reports, etc.). Uses Handlers (implementing PHPNomad's `CanHandle` interface) with container-injected dependencies.

Both types use the same `Integration.php` class. Delete the scaffolding that doesn't apply to your use case.

## Quick Start

1. **Clone this template** into a new repository
2. **Decide your type** — integration or capability extension
3. **Find and replace** all placeholder values (see checklist below)
4. **Delete** unused scaffolding (Transformers/Adapters for capability, Handlers for integrations)
5. **Implement** the Integration class and supporting files
6. **Test** with Siren installed on a WordPress site

## Setup Checklist

After cloning, work through these replacements:

### Naming

| Find | Replace with | Example |
|------|-------------|---------|
| `Your Extension` | Plugin display name | `MemberPress` |
| `YourExtension` | PascalCase namespace | `MemberPress` |
| `YourTargetPlugin` | Target plugin's main class | `MeprCtrlFactory` |
| `yourext` | Short extension ID (2-4 chars) | `mp` |
| `YOUREXTENSION` | UPPER_CASE for constants | `MEMBERPRESS` |
| `your_ext_order` | External type for orders | `mp_order` |
| `your_ext_product` | External type for products | `mp_product` |
| `siren-your-extension` | Plugin slug (for repo, zip) | `siren-memberpress` |

### Files to update (all extensions)

- [ ] `plugin.php` — Plugin header, constant name, namespace import
- [ ] `lib/Integration.php` — Namespace, all TODO items
- [ ] `composer.json` — Package name, description, PSR-4 namespace
- [ ] `navigator.yaml` — Name, tags, repository
- [ ] `AGENTS.md` — Extension-specific agent context if needed (`CLAUDE.md` just points here)
- [ ] `.github/workflows/release.yml` — Plugin slug

### Files to update (integrations)

- [ ] `lib/Transformers/SaleTriggeredTransformer.php` — Namespace, method signatures
- [ ] `lib/Adapters/OrderToTransactionDetailsAdapter.php` — Namespace, order conversion

### Files to update (capability extensions)

- [ ] `lib/Handlers/AdminHandler.php` — Namespace, dependencies, WordPress hooks

### Additional files to create (as needed)

**For integrations:**
- `lib/Transformers/RefundTriggeredTransformer.php`
- `lib/Transformers/TransactionCompletedTransformer.php`
- `lib/Transformers/CouponAppliedTransformer.php`
- `lib/Transformers/RenewalTriggeredTransformer.php`
- `lib/Factories/` (for LMS data factories)
- `public/` (for admin UI templates/assets)

**For capability extensions:**
- `lib/Handlers/RestHandler.php` (REST API endpoints — implements CanHandle)
- `lib/Handlers/FrontendHandler.php` (front-end hooks/assets — implements CanHandle)
- `public/` (admin UI templates/assets)

## Architecture

See the `siren-extension-development-guide` entry in the Navigator KB for the full
architecture guide (`AGENTS.md` explains how to load it). In short:

**Integrations:**
```
Target plugin hook → Transformer → Siren Domain Event → Commission Pipeline
```

**Capability extensions:**
```
Siren Ready event → Handler (implements CanHandle, container-injected) → WordPress hooks
```

Both patterns use PHPNomad's initializer system. See the [PHPNomad docs](https://phpnomad.com/core-concepts/bootstrapping/introduction) for the framework fundamentals.

## Building a Release

Create a GitHub release with a `v*` tag. The workflow builds a clean zip with production dependencies and attaches it to the release.

## Reference Extensions

Study these in the `siren-fresh` repository for canonical patterns:

| Extension | Type | Location |
|-----------|------|----------|
| WooCommerce | E-commerce | `lib/WordPress/Extensions/WooCommerce/` |
| EDD | E-commerce | `lib/WordPress/Extensions/EDD/` |
| NorthCommerce | E-commerce | `lib/WordPress/Extensions/NorthCommerce/` |
| LifterLMS | LMS + Commerce | `lib/WordPress/Extensions/LifterLMS/` |
| LearnDash | LMS | `lib/WordPress/Extensions/LearnDash/` |
| GravityForms | Forms + Commerce | `lib/WordPress/Extensions/GravityForms/` |
