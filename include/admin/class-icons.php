<?php
/*
 * @Author: Amirhossein Hosseinpour <https://amirhp.com>
 * @Date Created: 2026/10/09 19:05:11
 * @Last modified by: amirhp-com <its@amirhp.com>
 */

namespace peproulitmateinvoice;

defined("ABSPATH") or die("Pepro Ultimate Invoice :: Unauthorized Access!");

if (!class_exists("PeproUltimateInvoice_Icons")) {
  /**
   * inline svg icons, Phosphor Icons 2.1.1 regular weight, MIT licensed
   * see assets/phosphor/LICENSE
   */
  class PeproUltimateInvoice_Icons {

    protected static $cache = array();

    /**
     * markup for one icon, empty string when the name is unknown
     *
     * @method get
     * @param  string $name  file name without the extension
     * @param  string $class extra css classes
     * @return string
     */
    public static function get($name, $class = "") {
      $name = preg_replace('/[^a-z0-9\-]/', "", strtolower((string) $name));
      if ("" === $name) {
        return "";
      }
      if (!isset(self::$cache[$name])) {
        // an add-on can add its own folder rather than drop files into ours
        $roots = (array) apply_filters("puiw_icon_folders", array(PEPROULTIMATEINVOICE_DIR . "assets/phosphor/"), $name);
        self::$cache[$name] = "";
        foreach ($roots as $root) {
          $file = trailingslashit($root) . "{$name}.svg";
          if (is_readable($file)) {
            self::$cache[$name] = trim((string) file_get_contents($file));
            break;
          }
        }
      }
      if ("" === self::$cache[$name]) {
        return "";
      }
      $classes = trim("puiw-icon puiw-icon-{$name} " . $class);
      // 1em until a stylesheet sizes it, so a page without our css never shows a full-width glyph
      return str_replace("<svg ", sprintf('<svg class="%s" width="1em" height="1em" aria-hidden="true" focusable="false" ', esc_attr($classes)), self::$cache[$name]);
    }

    /**
     * echo helper
     *
     * @method render
     * @param  string $name
     * @param  string $class
     */
    public static function render($name, $class = "") {
      echo self::get($name, $class);
    }
  }
}
