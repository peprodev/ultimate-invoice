<?php
/*
 * @Author: Amirhossein Hosseinpour <https://amirhp.com>
 * @Date Created: 2026/11/17 14:20:00
 * @Last modified by: amirhp-com <its@amirhp.com>
 */

namespace peproulitmateinvoice;

defined("ABSPATH") or die("Pepro Ultimate Invoice :: Unauthorized Access!");

if (!class_exists("PeproUltimateInvoice_Snapshots")) {
  /**
   * copies of every plugin setting, taken before an update and before anything in
   * Migrate/Backup overwrites them
   *
   * They live in uploads, not in the plugin folder: WordPress deletes the plugin folder
   * on every update, and a json there would be public. The file is a flat
   * option => value object, so the Import JSON of any version can read it.
   */
  class PeproUltimateInvoice_Snapshots {

    const DIR  = "pepro-ultimate-invoice/settings-backups";
    const KEEP = 15;
    const META = "_snapshot";

    /* bookkeeping options that must never travel back with a restore */
    protected static $skip = array("puiw_plugin_version", "puiw_upgraded_from", "puiw_rtl_template_autoswitched");

    public static function init() {
      add_action("admin_post_puiw_snapshot_download", array(__CLASS__, "download"));
    }

    public static function dir() {
      $up = wp_upload_dir(null, false);
      return trailingslashit($up["basedir"]) . self::DIR;
    }

    /* the folder, locked against direct requests on apache and unlisted on nginx */
    protected static function prepare() {
      $dir = self::dir();
      if (!wp_mkdir_p($dir)) {
        return false;
      }
      if (!file_exists("$dir/.htaccess")) {
        @file_put_contents("$dir/.htaccess", "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
      }
      if (!file_exists("$dir/index.php")) {
        @file_put_contents("$dir/index.php", "<?php // Silence is golden.\n");
      }
      return $dir;
    }

    /**
     * every puiw_ option as stored right now
     *
     * @return array
     */
    public static function collect() {
      global $wpdb;
      $names = $wpdb->get_col($wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name",
        $wpdb->esc_like("puiw_") . "%"
      ));
      $data = array();
      foreach ((array) $names as $name) {
        if (in_array($name, self::$skip, true)) {
          continue;
        }
        $data[$name] = get_option($name);
      }
      return $data;
    }

    /**
     * write a snapshot file
     *
     * @param  string $reason  update, manual, reset, clear, fill, import or restore
     * @param  string $version the version the settings belong to
     * @return string|false    file name
     */
    public static function create($reason = "manual", $version = "") {
      $dir = self::prepare();
      if (!$dir) {
        return false;
      }
      $version = "" !== (string) $version ? (string) $version : PEPROULTIMATEINVOICE_VER;
      $data    = self::collect();
      $data[self::META] = array(
        "plugin"  => "pepro-ultimate-invoice",
        "version" => $version,
        "reason"  => sanitize_key($reason),
        "created" => gmdate("c"),
        "site"    => home_url(),
      );
      $name = sprintf(
        "settings-v%s-%s-%s-%s.json",
        preg_replace('/[^0-9a-z.\-]/i', "", $version),
        gmdate("Ymd-His"),
        sanitize_key($reason),
        strtolower(wp_generate_password(10, false, false))
      );
      $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      if (false === $json || false === @file_put_contents("$dir/$name", $json)) {
        return false;
      }
      self::prune();
      return $name;
    }

    /* newest first */
    public static function all() {
      $dir   = self::dir();
      $files = is_dir($dir) ? (array) glob("$dir/settings-*.json") : array();
      $list  = array();
      foreach ($files as $file) {
        $meta = self::meta($file);
        $list[] = array(
          "file"    => basename($file),
          "version" => $meta["version"],
          "reason"  => $meta["reason"],
          "time"    => $meta["time"] ? $meta["time"] : (int) filemtime($file),
          "size"    => (int) filesize($file),
        );
      }
      usort($list, function ($a, $b) { return $b["time"] - $a["time"]; });
      return $list;
    }

    /* reads the head of the name instead of decoding every file on each page load */
    protected static function meta($file) {
      $meta = array("version" => "", "reason" => "", "time" => 0);
      if (preg_match('/^settings-v([0-9a-z.\-]+?)-(\d{8}-\d{6})-([a-z_\-]+)-[a-z0-9]+\.json$/', basename($file), $m)) {
        $time = \DateTime::createFromFormat("Ymd-His", $m[2], new \DateTimeZone("UTC"));
        $meta = array("version" => $m[1], "reason" => $m[3], "time" => $time ? $time->getTimestamp() : 0);
      }
      return $meta;
    }

    protected static function prune() {
      $keep = (int) apply_filters("puiw_settings_snapshots_keep", self::KEEP);
      foreach (array_slice(self::all(), max(1, $keep)) as $old) {
        @unlink(self::dir() . "/" . $old["file"]);
      }
    }

    /* a file name from the request, only ever one of ours */
    public static function path($name) {
      $name = basename((string) $name);
      if (!preg_match('/^settings-[0-9a-z.\-_]+\.json$/i', $name)) {
        return false;
      }
      $path = self::dir() . "/$name";
      return is_file($path) ? $path : false;
    }

    public static function read($name) {
      $path = self::path($name);
      if (!$path) {
        return false;
      }
      $data = json_decode((string) file_get_contents($path), true);
      return is_array($data) ? $data : false;
    }

    /**
     * put every setting from a snapshot back, after taking a copy of the current ones
     *
     * @return int|false number of settings written
     */
    public static function restore($name) {
      $data = self::read($name);
      if (!$data) {
        return false;
      }
      self::create("restore");
      $count = 0;
      foreach ($data as $key => $value) {
        $key = (string) $key;
        if (0 !== strpos($key, "puiw_") || $key !== sanitize_key($key) || in_array($key, self::$skip, true)) {
          continue;
        }
        update_option($key, $value);
        $count++;
      }
      delete_transient("puiw_dashboard_stats_v2");
      return $count;
    }

    public static function delete($name) {
      $path = self::path($name);
      return $path ? @unlink($path) : false;
    }

    public static function download_url($name) {
      return wp_nonce_url(add_query_arg(array("action" => "puiw_snapshot_download", "file" => $name), admin_url("admin-post.php")), "puiw_snapshot_download");
    }

    public static function download() {
      if (!current_user_can("manage_woocommerce")) {
        wp_die(esc_html__("Unauthorized Access Denied!", "pepro-ultimate-invoice"), "", array("response" => 403));
      }
      check_admin_referer("puiw_snapshot_download");
      $path = self::path(isset($_GET["file"]) ? sanitize_file_name(wp_unslash($_GET["file"])) : "");
      if (!$path) {
        wp_die(esc_html__("This backup no longer exists.", "pepro-ultimate-invoice"), "", array("response" => 404));
      }
      $meta = self::meta($path);
      $name = sprintf("ultimate-invoice-settings-v%s-%s.json", $meta["version"], gmdate("Y-m-d", $meta["time"] ? $meta["time"] : time()));
      nocache_headers();
      header("Content-Type: application/json; charset=utf-8");
      header("Content-Disposition: attachment; filename=\"$name\"");
      header("Content-Length: " . filesize($path));
      readfile($path);
      exit;
    }

    public static function reason_label($reason) {
      $labels = array(
        "update"  => _x("Before the update", "snapshots", "pepro-ultimate-invoice"),
        "manual"  => _x("Saved by hand", "snapshots", "pepro-ultimate-invoice"),
        "reset"   => _x("Before reset to defaults", "snapshots", "pepro-ultimate-invoice"),
        "clear"   => _x("Before clearing all settings", "snapshots", "pepro-ultimate-invoice"),
        "fill"    => _x("Before filling empty settings", "snapshots", "pepro-ultimate-invoice"),
        "import"  => _x("Before an import", "snapshots", "pepro-ultimate-invoice"),
        "restore" => _x("Before a restore", "snapshots", "pepro-ultimate-invoice"),
      );
      return isset($labels[$reason]) ? $labels[$reason] : $reason;
    }
  }
}
