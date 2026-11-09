<?php
/*
 * @Author: Amirhossein Hosseinpour <https://amirhp.com>
 * @Date Created: 2026/10/09 20:41:08
 * @Last modified by: amirhp-com <its@amirhp.com>
 */

namespace peproulitmateinvoice;

defined("ABSPATH") or die("Pepro Ultimate Invoice :: Unauthorized Access!");

if (!class_exists("PeproUltimateInvoice_Themes")) {
  /**
   * user owned invoice templates living in the uploads folder
   *
   * bundled templates are never written to, editing one duplicates it first
   */
  class PeproUltimateInvoice_Themes {

    const FOLDER = "puiw-templates";

    /** files a shop manager is allowed to open in the editor */
    public static function editable_extensions() {
      return apply_filters("puiw_theme_editable_extensions", array("html", "css", "cfg"));
    }

    /**
     * uploads/puiw-templates, created on demand and shielded from listing
     *
     * @method root
     * @return string
     */
    public static function root() {
      $uploads = wp_upload_dir();
      $root    = trailingslashit($uploads["basedir"]) . self::FOLDER;
      if (!is_dir($root)) {
        wp_mkdir_p($root);
      }
      if (is_dir($root) && !file_exists("$root/index.php")) {
        @file_put_contents("$root/index.php", "<?php // Silence is golden\n");
      }
      if (is_dir($root) && !file_exists("$root/.htaccess")) {
        // the renderer reads these files from disk, nothing here is meant to be
        // fetched by url, so apache is told not to serve them at all
        @file_put_contents("$root/.htaccess", implode("\n", array(
          "<FilesMatch \"\\.(html?|svg|cfg|php)$\">",
          "  <IfModule mod_authz_core.c>",
          "    Require all denied",
          "  </IfModule>",
          "  <IfModule !mod_authz_core.c>",
          "    Deny from all",
          "  </IfModule>",
          "</FilesMatch>",
          "Options -Indexes",
          "<IfModule mod_headers.c>",
          "  Header set X-Content-Type-Options nosniff",
          "</IfModule>",
          "",
        )));
      }
      return $root;
    }

    public static function root_url() {
      $uploads = wp_upload_dir();
      return trailingslashit($uploads["baseurl"]) . self::FOLDER;
    }

    /**
     * every template folder the shop can edit
     *
     * @method user_themes
     * @return array slug => path
     */
    public static function user_themes() {
      $out = array();
      foreach ((array) glob(self::root() . "/*/default.cfg") as $cfg) {
        $dir = dirname($cfg);
        $out[basename($dir)] = $dir;
      }
      return $out;
    }

    /**
     * true when the path sits inside a template folder we may write to
     *
     * @method is_user_theme
     * @param  string $dir
     * @return bool
     */
    public static function is_user_theme($dir) {
      $dir  = wp_normalize_path(untrailingslashit((string) $dir));
      $root = wp_normalize_path(untrailingslashit(self::root()));
      return "" !== $dir && 0 === strpos($dir, $root . "/") && is_readable("$dir/default.cfg");
    }

    /**
     * resolve a slug or a path to a real template folder
     *
     * @method resolve
     * @param  string $theme
     * @return string empty when it is not a template we know
     */
    public static function resolve($theme) {
      $theme = (string) $theme;
      if ("" === $theme) {
        return "";
      }
      $slug = basename(untrailingslashit($theme));
      foreach (array(self::root() . "/$slug", PEPROULTIMATEINVOICE_DIR . "template/$slug") as $candidate) {
        if (is_readable("$candidate/default.cfg")) {
          return $candidate;
        }
      }
      return "";
    }

    /**
     * header of a template folder
     *
     * @method info
     * @param  string $dir
     * @return array
     */
    public static function info($dir) {
      global $PeproUltimateInvoice;
      $cfg    = is_readable("$dir/default.cfg") ? (string) file_get_contents("$dir/default.cfg") : "";
      $parsed = $PeproUltimateInvoice->parseTemplate($cfg);
      return array(
        "slug"    => basename($dir),
        "name"    => isset($parsed["name"]) ? $parsed["name"] : basename($dir),
        "author"  => isset($parsed["designer"]) ? $parsed["designer"] : "",
        "version" => isset($parsed["version"]) ? $parsed["version"] : "1.0.0",
        "editable" => self::is_user_theme($dir),
        "path"    => $dir,
      );
    }

    /**
     * files the editor may open, newest format first
     *
     * @method files
     * @param  string $dir
     * @return array name => array(type, size)
     */
    public static function files($dir) {
      $out = array();
      foreach ((array) glob("$dir/*") as $file) {
        if (!is_file($file)) {
          continue;
        }
        $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, self::editable_extensions(), true)) {
          continue;
        }
        $out[basename($file)] = array("type" => $ext, "size" => filesize($file));
      }
      ksort($out);
      return $out;
    }

    /**
     * copy a template into the uploads folder so it can be edited
     *
     * @method duplicate
     * @param  string $source slug or path
     * @param  string $name
     * @param  string $author
     * @return array|\WP_Error
     */
    public static function duplicate($source, $name = "", $author = "") {
      global $PeproUltimateInvoice;
      $from = self::resolve($source);
      if ("" === $from) {
        return new \WP_Error("puiw_theme_missing", __("That template could not be found.", "pepro-ultimate-invoice"));
      }
      $name = "" === trim((string) $name) ? self::info($from)["name"] . " copy" : trim((string) $name);
      $slug = sanitize_title($name);
      if ("" === $slug) {
        $slug = "template-" . wp_generate_password(6, false, false);
      }
      $target = self::root() . "/$slug";
      $i = 2;
      while (is_dir($target)) {
        $target = self::root() . "/$slug-$i";
        $i++;
      }
      if (!wp_mkdir_p($target)) {
        return new \WP_Error("puiw_theme_mkdir", __("The templates folder is not writable.", "pepro-ultimate-invoice"));
      }
      foreach ((array) glob("$from/*") as $file) {
        if (is_file($file)) {
          copy($file, "$target/" . basename($file));
        }
      }
      // old format templates become html on the way in, both still render
      foreach ((array) glob("$target/*.tpl") as $file) {
        rename($file, preg_replace('/\.tpl$/i', ".html", $file));
      }
      self::write_header($target, array(
        "name"    => $name,
        "author"  => "" === trim((string) $author) ? wp_get_current_user()->display_name : trim((string) $author),
        "version" => "1.0.0",
      ));
      return self::info($target);
    }

    /**
     * rewrite the name, designer and version lines of default.cfg
     *
     * @method write_header
     * @param  string $dir
     * @param  array  $header
     * @return bool
     */
    public static function write_header($dir, $header) {
      global $PeproUltimateInvoice;
      if (!self::is_user_theme($dir) && !is_dir($dir)) {
        return false;
      }
      $cfg    = is_readable("$dir/default.cfg") ? (string) file_get_contents("$dir/default.cfg") : "/* \n*/";
      $map    = array("name" => "Name", "author" => "Designer", "version" => "Version");
      foreach ($map as $key => $label) {
        if (!isset($header[$key])) {
          continue;
        }
        $line  = "$label: " . sanitize_text_field($header[$key]);
        $regex = '/^' . preg_quote($label, "/") . ':.*$/mi';
        if (preg_match($regex, $cfg)) {
          $cfg = preg_replace($regex, $line, $cfg, 1);
        } else {
          $cfg = preg_replace('/^(\/\*\s*\n)/', "$1$line\n", $cfg, 1);
        }
      }
      if (!empty($header["plugin_version"])) {
        $line  = "Requires: " . sanitize_text_field($header["plugin_version"]);
        $cfg = preg_match('/^Requires:.*$/mi', $cfg) ? preg_replace('/^Requires:.*$/mi', $line, $cfg, 1) : preg_replace('/^(\/\*\s*\n)/', "$1$line\n", $cfg, 1);
      }
      return false !== file_put_contents("$dir/default.cfg", $cfg);
    }

    /**
     * read one editable file
     *
     * @method read_file
     * @param  string $dir
     * @param  string $file
     * @return string|\WP_Error
     */
    public static function read_file($dir, $file) {
      $path = self::safe_path($dir, $file);
      if (is_wp_error($path)) {
        return $path;
      }
      return is_readable($path) ? (string) file_get_contents($path) : "";
    }

    /**
     * write one editable file of a user owned template
     *
     * @method write_file
     * @param  string $dir
     * @param  string $file
     * @param  string $contents
     * @return true|\WP_Error
     */
    public static function write_file($dir, $file, $contents) {
      if (!self::is_user_theme($dir)) {
        return new \WP_Error("puiw_theme_readonly", __("Bundled templates cannot be edited, duplicate it first.", "pepro-ultimate-invoice"));
      }
      $path = self::safe_path($dir, $file);
      if (is_wp_error($path)) {
        return $path;
      }
      if (false === file_put_contents($path, $contents)) {
        return new \WP_Error("puiw_theme_write", __("That file could not be written.", "pepro-ultimate-invoice"));
      }
      return true;
    }

    /**
     * reject anything that is not a plain editable file inside the folder
     *
     * @method safe_path
     * @param  string $dir
     * @param  string $file
     * @return string|\WP_Error
     */
    protected static function safe_path($dir, $file) {
      $dir  = self::resolve($dir);
      $file = basename((string) $file);
      if ("" === $dir || "" === $file) {
        return new \WP_Error("puiw_theme_path", __("That file could not be found.", "pepro-ultimate-invoice"));
      }
      $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
      if (!in_array($ext, self::editable_extensions(), true)) {
        return new \WP_Error("puiw_theme_ext", __("Only HTML and CSS files can be edited.", "pepro-ultimate-invoice"));
      }
      return "$dir/$file";
    }

    /**
     * zip a template up for download
     *
     * @method export
     * @param  string $theme
     * @return array|\WP_Error url and file name
     */
    public static function export($theme) {
      $dir = self::resolve($theme);
      if ("" === $dir) {
        return new \WP_Error("puiw_theme_missing", __("That template could not be found.", "pepro-ultimate-invoice"));
      }
      if (!class_exists("ZipArchive")) {
        return new \WP_Error("puiw_theme_zip", __("PHP is built without the zip extension on this server.", "pepro-ultimate-invoice"));
      }
      $slug = basename($dir);
      $name = $slug . "-" . gmdate("Ymd-His") . ".zip";
      $path = trailingslashit(self::root()) . $name;
      $zip  = new \ZipArchive();
      if (true !== $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
        return new \WP_Error("puiw_theme_zip", __("The archive could not be created.", "pepro-ultimate-invoice"));
      }
      foreach ((array) glob("$dir/*") as $file) {
        if (is_file($file)) {
          $zip->addFile($file, $slug . "/" . basename($file));
        }
      }
      $zip->close();
      foreach ((array) glob(self::root() . "/*.zip") as $old) {
        if ($old !== $path && filemtime($old) < time() - HOUR_IN_SECONDS) {
          unlink($old);
        }
      }
      return array("url" => trailingslashit(self::root_url()) . $name, "file" => $name);
    }

    /**
     * unpack an uploaded template archive into the uploads folder
     *
     * @method import
     * @param  string $zip_path
     * @return array|\WP_Error
     */
    public static function import($zip_path, $original_name = "") {
      if (!class_exists("ZipArchive")) {
        return new \WP_Error("puiw_theme_zip", __("PHP is built without the zip extension on this server.", "pepro-ultimate-invoice"));
      }
      $zip = new \ZipArchive();
      if (true !== $zip->open($zip_path)) {
        return new \WP_Error("puiw_theme_zip", __("That file is not a readable archive.", "pepro-ultimate-invoice"));
      }
      // svg is deliberately not accepted: it is active content and the uploads
      // folder is public, so a poisoned template zip would be stored xss
      $allowed = self::editable_extensions();
      $allowed[] = "png";
      $allowed[] = "jpg";
      $allowed[] = "jpeg";
      $files   = array();
      $has_cfg = false;
      for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if (false === $entry || "/" === substr($entry, -1)) {
          continue;
        }
        $base = basename($entry);
        // a template is a flat folder of files, anything nested or escaping is dropped
        if ($base !== $entry && substr_count(trim($entry, "/"), "/") > 1) {
          continue;
        }
        if (false !== strpos($entry, "..")) {
          continue;
        }
        $ext = strtolower((string) pathinfo($base, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
          continue;
        }
        if ("default.cfg" === $base) {
          $has_cfg = true;
        }
        $files[$base] = $i;
      }
      if (!$has_cfg) {
        $zip->close();
        return new \WP_Error("puiw_theme_cfg", __("The archive has no default.cfg, so it is not an invoice template.", "pepro-ultimate-invoice"));
      }
      // the upload lands under a php temp name, so the slug comes from what the
      // browser sent, then from the template header, and only then from a random one
      $slug = sanitize_title(pathinfo((string) $original_name, PATHINFO_FILENAME));
      if ("" === $slug) {
        $stream = $zip->getStream("default.cfg");
        if (!$stream) {
          $stream = $zip->getStream($zip->getNameIndex($files["default.cfg"]));
        }
        if ($stream) {
          preg_match('/^Name:\s*(.+)$/mi', (string) stream_get_contents($stream), $named);
          fclose($stream);
          $slug = isset($named[1]) ? sanitize_title($named[1]) : "";
        }
      }
      $slug = "" === $slug ? "template-" . wp_generate_password(6, false, false) : $slug;
      $target = self::root() . "/$slug";
      $i = 2;
      while (is_dir($target)) {
        $target = self::root() . "/$slug-$i";
        $i++;
      }
      wp_mkdir_p($target);
      foreach ($files as $base => $index) {
        $stream = $zip->getStream($zip->getNameIndex($index));
        if (!$stream) {
          continue;
        }
        file_put_contents("$target/$base", stream_get_contents($stream));
        fclose($stream);
      }
      $zip->close();
      foreach ((array) glob("$target/*.tpl") as $file) {
        rename($file, preg_replace('/\.tpl$/i', ".html", $file));
      }
      return self::info($target);
    }

    /**
     * delete a template the shop owns
     *
     * @method remove
     * @param  string $theme
     * @return true|\WP_Error
     */
    public static function remove($theme) {
      $dir = self::resolve($theme);
      if ("" === $dir || !self::is_user_theme($dir)) {
        return new \WP_Error("puiw_theme_readonly", __("Only templates you created can be deleted.", "pepro-ultimate-invoice"));
      }
      foreach ((array) glob("$dir/*") as $file) {
        if (is_file($file)) {
          unlink($file);
        }
      }
      return rmdir($dir) ? true : new \WP_Error("puiw_theme_delete", __("That template could not be deleted.", "pepro-ultimate-invoice"));
    }
  }
}
