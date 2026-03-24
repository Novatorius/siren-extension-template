<?php
/*
 * Plugin Name: Siren Affiliates - Your Extension Integration
 * Description: Integrates Your Extension with Siren Affiliates.
 * Author: Novatorius, LLC
 * Author URI: https://sirenaffiliates.com
 * Version: 1.0.0
 * Requires PHP: 8.1
 *
 * TODO: Update plugin header
 * ─────────────────────────────────────────────────────────────────────────────
 * Replace "Your Extension" with the real plugin name in both Plugin Name and
 * Description above. Keep the "Siren Affiliates - " prefix so the extension
 * sorts next to the core plugin in wp-admin.
 *
 * The Version field should start at 1.0.0 and follow semver. The GitHub
 * release workflow uses this version when building the zip.
 * ─────────────────────────────────────────────────────────────────────────────
 */

use Siren\Extensions\Core\Facades\Extensions;
use Siren\WordPress\Extensions\YourExtension\Integration;

/*
 * TODO: Rename this constant
 * ─────────────────────────────────────────────────────────────────────────────
 * This constant stores the absolute path to this bootstrap file. The
 * Integration class returns it from getRootPath(), and Siren's module system
 * uses it to locate templates and assets relative to this extension.
 *
 * Convention: SIREN_{EXTENSION_ID_UPPER}_ROOT
 * Examples from existing extensions:
 *   - SIREN_WOOCOMMERCE_ROOT
 *   - SIREN_EDD_ROOT
 *   - SIREN_LLMS_ROOT
 *   - SIREN_LEARNDASH_ROOT
 *   - SIREN_GRAVITYFORMS_ROOT
 *   - SIREN_NORTHCOMMERCE_ROOT
 * ─────────────────────────────────────────────────────────────────────────────
 */
const SIREN_YOUREXTENSION_ROOT = __FILE__;

/*
 * Guard: only run inside WordPress.
 *
 * If add_action doesn't exist, this file was loaded outside WordPress (e.g.,
 * during a composer dump or a standalone test runner). Bail silently.
 */
if (!function_exists('add_action')) {
    return;
}

/*
 * Register the extension with Siren.
 * ─────────────────────────────────────────────────────────────────────────────
 * The `siren_ready` action fires after Siren has finished bootstrapping its
 * core systems (DI container, event bus, extension registry). This is the
 * ONLY safe place to register an extension.
 *
 * Extensions::add() takes two arguments:
 *   1. A unique string ID (returned by Integration::getId())
 *   2. A factory closure that returns a new Integration instance
 *
 * Priority 0 ensures extensions register before any other siren_ready
 * callbacks that might depend on the extension registry being populated.
 *
 * DO NOT:
 *   - Register on `plugins_loaded` (Siren isn't ready yet)
 *   - Register on `init` (too late, events may have already fired)
 *   - Instantiate services here (the container isn't injected yet)
 * ─────────────────────────────────────────────────────────────────────────────
 */
add_action('siren_ready', function () {
    Extensions::add(Integration::getId(), fn() => new Integration());
}, 0);
