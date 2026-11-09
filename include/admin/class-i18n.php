<?php
/*
 * @Author: Amirhossein Hosseinpour <https://amirhp.com>
 * @Date Created: 2026/10/09 19:12:40
 * @Last modified by: amirhp-com <its@amirhp.com>
 */

namespace peproulitmateinvoice;

defined("ABSPATH") or die("Pepro Ultimate Invoice :: Unauthorized Access!");

if (!class_exists("PeproUltimateInvoice_I18n")) {
  /**
   * per language invoice texts, template and settings
   *
   * works on its own with the locales installed in WordPress and picks up the
   * language list from Polylang when it is active
   */
  class PeproUltimateInvoice_I18n {

    const OPT_STRINGS  = "puiw_lang_strings";
    const OPT_TEMPLATE = "puiw_lang_template";
    const OPT_SETTINGS = "puiw_lang_settings";

    protected $active     = "";
    protected $switched   = false;
    protected $option_map = array();

    public function __construct() {
      add_filter("puiw_template_part_markup", array($this, "translate_markup"), 10, 3);
      add_filter("puiw_get_template", array($this, "filter_template"), 20, 2);
    }

    /**
     * language codes the shop can produce invoices in
     *
     * @method get_languages
     * @return array code => array(code, locale, name)
     */
    public function get_languages() {
      $languages = array();

      if (function_exists("pll_languages_list")) {
        $codes   = pll_languages_list(array("fields" => "slug"));
        $locales = pll_languages_list(array("fields" => "locale"));
        $names   = pll_languages_list(array("fields" => "name"));
        foreach ((array) $codes as $i => $code) {
          $languages[$code] = array(
            "code"   => $code,
            "locale" => isset($locales[$i]) ? $locales[$i] : $code,
            "name"   => isset($names[$i]) ? $names[$i] : $code,
          );
        }
      }

      if (empty($languages)) {
        $locales = get_available_languages();
        $locales[] = get_locale();
        $locales[] = "en_US";
        $locales = array_values(array_unique(array_filter($locales)));
        foreach ($locales as $locale) {
          $code = $this->locale_to_code($locale);
          $languages[$code] = array(
            "code"   => $code,
            "locale" => $locale,
            "name"   => $this->locale_name($locale),
          );
        }
      }

      return apply_filters("puiw_invoice_languages", $languages);
    }

    /**
     * the language an invoice falls back to
     *
     * @method default_language
     * @return string
     */
    public function default_language() {
      if (function_exists("pll_default_language")) {
        $default = pll_default_language("slug");
        if ($default) {
          return $default;
        }
      }
      return $this->locale_to_code(get_locale());
    }

    /**
     * language an order was placed in
     *
     * @method detect
     * @param  \WC_Order|false $order
     * @return string
     */
    public function detect($order = false) {
      $lang = "";

      if ($order && is_a($order, "WC_Order")) {
        // Polylang and anything that follows the same convention store it on the order
        foreach (array("_pll_language", "_order_language") as $key) {
          $value = $order->get_meta($key, true);
          if (!empty($value)) {
            $lang = (string) $value;
            break;
          }
        }
        if ("" === $lang && function_exists("pll_get_post_language")) {
          $value = pll_get_post_language($order->get_id(), "slug");
          if (!empty($value)) {
            $lang = (string) $value;
          }
        }
      }

      if ("" === $lang) {
        $lang = $this->locale_to_code(determine_locale());
      }

      return (string) apply_filters("puiw_invoice_language", $lang, $order);
    }

    /**
     * switch everything the invoice renderer reads over to one language
     *
     * @method activate
     * @param  string $lang
     */
    public function activate($lang) {
      $lang = (string) $lang;
      if ($lang === $this->active) {
        return;
      }
      $this->deactivate();
      if ("" === $lang) {
        return;
      }
      $this->active = $lang;

      $settings = $this->get_settings($lang);
      foreach ($settings as $key => $value) {
        if ("" === $value || !in_array($key, $this->option_keys(), true)) {
          continue;
        }
        $this->option_map[$key] = $value;
        add_filter("option_{$key}", array($this, "filter_option"), 20, 2);
      }

      $languages = $this->get_languages();
      $locale    = isset($languages[$lang]["locale"]) ? $languages[$lang]["locale"] : "";
      if ($locale && function_exists("switch_to_locale") && $locale !== determine_locale()) {
        $this->switched = switch_to_locale($locale);
      }
    }

    /**
     * put the site locale and the unfiltered options back
     *
     * @method deactivate
     */
    public function deactivate() {
      foreach (array_keys($this->option_map) as $key) {
        remove_filter("option_{$key}", array($this, "filter_option"), 20);
      }
      $this->option_map = array();
      if ($this->switched && function_exists("restore_previous_locale")) {
        restore_previous_locale();
      }
      $this->switched = false;
      $this->active   = "";
    }

    public function get_active() {
      return $this->active;
    }

    /**
     * @method filter_option
     * @param  mixed  $value
     * @param  string $option
     * @return mixed
     */
    public function filter_option($value, $option = "") {
      return isset($this->option_map[$option]) ? $this->option_map[$option] : $value;
    }

    /**
     * swap the template folder for the active language
     *
     * @method filter_template
     * @param  string $template
     * @param  string $default
     * @return string
     */
    public function filter_template($template, $default = "default") {
      if ("" === $this->active) {
        return $template;
      }
      $map = (array) get_option(self::OPT_TEMPLATE, array());
      if (empty($map[$this->active])) {
        return $template;
      }
      $candidate = $this->normalise_template($map[$this->active]);
      return is_readable("{$candidate}/default.cfg") ? $candidate : $template;
    }

    /**
     * replace the texts the shop manager translated, before the placeholders run
     *
     * @method translate_markup
     * @param  string $markup
     * @param  string $file
     * @param  string $dir
     * @return string
     */
    public function translate_markup($markup, $file = "", $dir = "") {
      if ("" === $this->active || "" === trim($markup)) {
        return $markup;
      }
      // stylesheets carry no visible copy and must never be string replaced
      if (preg_match('/\.css$/i', (string) $file)) {
        return $markup;
      }
      $map = $this->get_strings($this->active);
      if (empty($map)) {
        return $markup;
      }
      $replace = array();
      foreach ($map as $source => $target) {
        $source = (string) $source;
        $target = (string) $target;
        if ("" === trim($source) || "" === trim($target) || $source === $target) {
          continue;
        }
        // anchored on the tag boundaries so a word inside an attribute is left alone
        $replace[">{$source}<"] = ">{$target}<";
      }
      return empty($replace) ? $markup : strtr($markup, $replace);
    }

    /**
     * every visible text node of a template, grouped by file
     *
     * @method scan_template
     * @param  string $dir
     * @return array file => string[]
     */
    public function scan_template($dir = "") {
      $dir = "" === $dir ? $this->normalise_template(get_option("puiw_template", "default")) : $this->normalise_template($dir);
      $files = array_merge((array) glob(untrailingslashit($dir) . "/*.html"), (array) glob(untrailingslashit($dir) . "/*.tpl"));
      if (empty($files)) {
        return array();
      }
      sort($files);
      $key   = "puiw_tpl_strings2_" . md5($dir . implode(",", array_map("filemtime", $files)));
      $found = get_transient($key);
      if (is_array($found)) {
        return $found;
      }
      $found = array();
      foreach ($files as $file) {
        $markup = (string) file_get_contents($file);
        $texts  = array();
        if (preg_match_all('/>([^<>{}]*[\p{L}][^<>{}]*)</u', $markup, $matches)) {
          foreach ($matches[1] as $text) {
            $text = trim($text);
            if ("" === $text || is_numeric($text)) {
              continue;
            }
            $texts[$text] = true;
          }
        }
        // the newer templates write their labels as {{{i18n:English text}}}; those are
        // listed by their English text and replaced before the plugin's own translation
        if (preg_match_all('/\{\{\{i18n:([^{}]+)\}\}\}/', $markup, $labels)) {
          foreach ($labels[1] as $text) {
            $texts[trim($text)] = true;
          }
        }
        if (!empty($texts)) {
          $found[basename($file)] = array_keys($texts);
        }
      }
      set_transient($key, $found, DAY_IN_SECONDS);
      return $found;
    }

    /**
     * the active language's own wording for one template label, "" when none was set
     *
     * @method override
     * @param  string $source
     * @return string
     */
    public function override($source) {
      if ("" === $this->active) {
        return "";
      }
      $map = $this->get_strings($this->active);
      $source = (string) $source;
      return isset($map[$source]) && "" !== trim((string) $map[$source]) ? (string) $map[$source] : "";
    }

    /**
     * @method get_strings
     * @param  string $lang
     * @return array
     */
    public function get_strings($lang) {
      $all = (array) get_option(self::OPT_STRINGS, array());
      return isset($all[$lang]) && is_array($all[$lang]) ? $all[$lang] : array();
    }

    /**
     * @method get_settings
     * @param  string $lang
     * @return array
     */
    public function get_settings($lang) {
      $all = (array) get_option(self::OPT_SETTINGS, array());
      return isset($all[$lang]) && is_array($all[$lang]) ? $all[$lang] : array();
    }

    /**
     * @method get_template_for
     * @param  string $lang
     * @return string
     */
    public function get_template_for($lang) {
      $map = (array) get_option(self::OPT_TEMPLATE, array());
      return isset($map[$lang]) ? (string) $map[$lang] : "";
    }

    /**
     * the settings worth having a separate value per language
     *
     * @method option_keys
     * @return array
     */
    public function option_keys() {
      return apply_filters("puiw_per_language_option_keys", array(
        "puiw_invoice_title",
        "puiw_store_name",
        "puiw_store_address",
        "puiw_invoices_footer",
        "puiw_email_subject",
        "puiw_email_from_name",
        "puiw_signature",
        "puiw_user_uin_label",
        "puiw_address_display_method",
        "puiw_preorder_customer_extra_note",
        "puiw_preorder_shopmngr_extra_note",
      ));
    }

    /**
     * human label for a per language setting
     *
     * @method option_label
     * @param  string $key
     * @return string
     */
    public function option_label($key) {
      $labels = array(
        "puiw_invoice_title"                => _x("Invoice Title", "wc-setting", "pepro-ultimate-invoice"),
        "puiw_store_name"                   => _x("Store Name", "wc-setting", "pepro-ultimate-invoice"),
        "puiw_store_address"                => _x("Store Address", "wc-setting", "pepro-ultimate-invoice"),
        "puiw_invoices_footer"              => _x("Invoice Footer", "wc-setting", "pepro-ultimate-invoice"),
        "puiw_email_subject"                => _x("Email Subject", "wc-setting", "pepro-ultimate-invoice"),
        "puiw_email_from_name"              => _x("Email From Name", "wc-setting", "pepro-ultimate-invoice"),
        "puiw_signature"                    => _x("Signature", "wc-setting", "pepro-ultimate-invoice"),
        "puiw_user_uin_label"               => _x("UIN Field Label", "wc-setting", "pepro-ultimate-invoice"),
        "puiw_address_display_method"       => _x("Address Format", "wc-setting", "pepro-ultimate-invoice"),
        "puiw_preorder_customer_extra_note" => _x("Pre-invoice Customer Note", "wc-setting", "pepro-ultimate-invoice"),
        "puiw_preorder_shopmngr_extra_note" => _x("Pre-invoice Manager Note", "wc-setting", "pepro-ultimate-invoice"),
      );
      return isset($labels[$key]) ? $labels[$key] : $key;
    }

    /**
     * @method normalise_template
     * @param  string $template
     * @return string
     */
    public function normalise_template($template) {
      $template = untrailingslashit((string) $template);
      if ("" === $template) {
        return PEPROULTIMATEINVOICE_DIR . "template/default";
      }
      if (is_readable("{$template}/default.cfg")) {
        return $template;
      }
      $bundled = PEPROULTIMATEINVOICE_DIR . "template/" . basename($template);
      return is_readable("{$bundled}/default.cfg") ? $bundled : PEPROULTIMATEINVOICE_DIR . "template/default";
    }

    /**
     * @method locale_to_code
     * @param  string $locale
     * @return string
     */
    protected function locale_to_code($locale) {
      $locale = (string) $locale;
      return "" === $locale ? "en" : strtolower(substr(str_replace("-", "_", $locale), 0, 2));
    }

    /**
     * @method locale_name
     * @param  string $locale
     * @return string
     */
    protected function locale_name($locale) {
      if (!function_exists("wp_get_available_translations")) {
        require_once ABSPATH . "wp-admin/includes/translation-install.php";
      }
      $available = function_exists("wp_get_available_translations") ? wp_get_available_translations() : array();
      if (isset($available[$locale]["native_name"])) {
        return $available[$locale]["native_name"];
      }
      return "en_US" === $locale ? "English (United States)" : $locale;
    }
  }
}
