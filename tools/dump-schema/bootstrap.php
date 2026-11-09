<?php
// Dev tool: loads Elementor's own widget classes headlessly (no WordPress) and
// dumps their control schemas as JSON. Usage:
//   php bootstrap.php <path-to-elementor-plugin-dir> <out-dir>
// Missing WordPress functions/classes are stubbed in shim.php (auto-grown by run.sh).

error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);

$root = rtrim(str_replace(chr(92), '/', $argv[1] ?? ''), '/') . '/';
$out  = rtrim(str_replace(chr(92), '/', $argv[2] ?? '.'), '/');

define('ABSPATH', str_replace(chr(92), '/', __DIR__) . '/wp-root/');
define('ELEMENTOR_VERSION', '4.3.6');
define('ELEMENTOR__FILE__', $root . 'elementor.php');
define('ELEMENTOR_PLUGIN_BASE', 'elementor/elementor.php');
define('ELEMENTOR_PATH', $root);
// URLs end up inside the schema defaults (e.g. the placeholder image): use a marker that
// SchemaRepository swaps for the configured assets URL at runtime.
define('ELEMENTOR_URL', '@@ELEMENTOR_URL@@/');
define('ELEMENTOR_MODULES_PATH', $root . 'modules');
define('ELEMENTOR_ASSETS_PATH', $root . 'assets/');
define('ELEMENTOR_ASSETS_URL', '@@ELEMENTOR_URL@@/assets/');
define('WP_DEBUG', false);

require __DIR__ . '/shim.php';

// The Jetpack autoloader needs WordPress; plain Composer autoload is enough here.
if (file_exists($root . 'vendor/autoload.php')) {
    require_once $root . 'vendor/autoload.php';
}

require_once $root . 'includes/autoloader.php';
Elementor\Autoloader::run();

use Elementor\Plugin;

// Anything else Elementor reaches for answers "nothing" instead of crashing.
class LamentorNull { public function __call($n, $a) { return null; } public function __get($n) { return null; } public static function __callStatic($n, $a) { return null; } }
// Stands in for a Kit document: no saved settings.
class LamentorKit { public function get_meta($k = null) { return null; } public function get_settings($k = null) { return null; } public function get_settings_for_display($k = null) { return null; } public function __call($n, $a) { return null; } }
class LamentorKitsManager { public function get_active_kit() { return new LamentorKit(); } public function get_active_kit_for_frontend() { return new LamentorKit(); } public function __call($n, $a) { return null; } }
// Widgets append the shared "common" controls from here; they are dumped separately.
class LamentorCommonWidget { public function get_controls() { return []; } public function get_tabs_controls() { return []; } }
class LamentorWidgetsManager { public function __call($n, $a) { return null; } public function get_widget_types($name = null) { return new LamentorCommonWidget(); } }

require __DIR__ . '/plugin-stub.php';
$plugin = new Plugin();
Plugin::$instance = $plugin;

// All experiments off: in particular `additional_custom_breakpoints`, which would
// otherwise leave responsive controls as one control and defer the per-device
// copies to the editor's JavaScript. We want them expanded in the schema.
class LamentorExperiments { public function is_feature_active($n = null) { return false; } public function __call($n, $a) { return null; } }
$plugin->experiments      = new LamentorExperiments();
$plugin->breakpoints      = new Elementor\Core\Breakpoints\Manager();
$plugin->controls_manager = new Elementor\Controls_Manager();
$plugin->editor           = new class { public function is_edit_mode() { return false; } public function is_editor_ajax() { return false; } };
$plugin->kits_manager     = new LamentorKitsManager();

$plugin->widgets_manager = new LamentorWidgetsManager();
foreach (['preview', 'frontend', 'documents', 'db', 'elements_manager', 'dynamic_tags', 'icons_manager', 'role_manager', 'files_manager', 'assets_manager', 'modules_manager', 'skins_manager', 'wp', 'uploads_manager', 'common'] as $prop) {
    $plugin->$prop = new LamentorNull();
}

// "Preview mode" makes Elementor register style controls eagerly instead of lazily.
$plugin->preview = new class { public function is_preview_mode() { return true; } public function __call($n, $a) { return null; } };

if (($argv[3] ?? '') === 'eval') {
    require $argv[4];
    exit(0);
}

if (($argv[3] ?? '') === 'smoke') {
    echo "bootstrap ok\n";
    exit(0);
}
require __DIR__ . '/dump.php';
