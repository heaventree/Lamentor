<?php
// Minimal WordPress shims for the schema dumper. Auto-extended by run.sh.
function __($t, $d = null) { return $t; }
function esc_html__($t, $d = null) { return $t; }
function esc_attr__($t, $d = null) { return $t; }
function _x($t, $c = null, $d = null) { return $t; }
function esc_html_x($t, $c = null, $d = null) { return $t; }
function _n($s, $p, $n, $d = null) { return $n == 1 ? $s : $p; }
function is_rtl() { return false; }
function add_action(...$a) { return true; }
function add_filter(...$a) { return true; }
function remove_action(...$a) { return true; }
function remove_filter(...$a) { return true; }
function has_action(...$a) { return false; }
function has_filter(...$a) { return false; }
function do_action(...$a) {}
function apply_filters($tag, $value = null, ...$rest) { return $value; }
function get_option($k, $d = false) { return $d; }
function update_option(...$a) { return true; }
function wp_parse_args($args, $defaults = []) { return array_merge((array) $defaults, (array) $args); }
function wp_json_encode(...$a) { return json_encode(...$a); }
function trailingslashit($s) { return rtrim($s, chr(92) . '/') . '/'; }
function plugin_dir_path($f) { return trailingslashit(dirname($f)); }
function plugins_url(...$a) { return ''; }
function is_admin() { return false; }
function get_bloginfo(...$a) { return '6.8'; }
function wp_get_theme(...$a) { return new class { public function get($k) { return ''; } public function exists() { return false; } }; }
function get_post_meta(...$a) { return $a[0] ?? null; }
function is_wp_error(...$a) { return $a[0] ?? null; }
function esc_url(...$a) { return $a[0] ?? null; }
function get_user_meta(...$a) { return $a[0] ?? null; }
function get_current_user_id(...$a) { return $a[0] ?? null; }
function current_user_can(...$a) { return $a[0] ?? null; }
function get_plugins(...$a) { return $a[0] ?? null; }
function is_plugin_active(...$a) { return $a[0] ?? null; }
