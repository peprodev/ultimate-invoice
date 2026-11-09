<?php
/*
Plugin Name: PeproDev Ultimate Invoice
Description: The Most Advanced Invoice Plugin you were looking for! Create customizable PDF/HTML invoices for WooCommerce, attach to Email, Inventory Report, Shipping Labels, Shipping Tracking, Single-shop feature and ...
Contributors: peprodev, amirhpcom, pigmentdev, blackswanlab
Tags: woocommerce invoice, pdf invoice, persian
Author: Pepro Dev. Group
Developer: amirhp.com
Author URI: https://peprodev.com/
Developer URI: https://amirhp.com
Plugin URI: https://peprodev.com/pepro-woocommerce-ultimate-invoice/
Version: 3.2.47
Stable tag: 3.2.47
Tested up to: 7.1
WC tested up to: 11.2
Requires at least: 5.0
Requires PHP: 7.4
WC requires at least: 5.0
Text Domain: pepro-ultimate-invoice
Domain Path: /languages
Copyright: (c) 2025 Pepro Dev. Group, All rights reserved.
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * @Last modified by: amirhp-com <its@amirhp.com>
 * @Last modified time: 2026/10/09 20:00:00
 */

namespace peproulitmateinvoice;

use voku\CssToInlineStyles\CssToInlineStyles;
use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * prevent data leak
 */
defined("ABSPATH") or die("<h2>Unauthorized Access!</h2><hr><small>PeproDev Ultimate Invoice :: Developed by Pepro Dev. Group (<a href='https://peprodev.com/'>https://peprodev.com/</a>)</small>");
// Prevent activation / loading on PHP versions older than 7.4
if (version_compare(PHP_VERSION, '7.4', '<')) {
  if (defined('WP_ADMIN') && \is_admin()) {
    // If this request is coming from an activation attempt, deactivate the plugin immediately
    if (isset($_GET['activate']) && function_exists('deactivate_plugins')) {
      \deactivate_plugins(\plugin_basename(__FILE__));
      unset($_GET['activate']);
    }
    // Show an admin notice about the PHP version requirement
    \add_action('admin_notices', function () {
      $msg = sprintf(\__('%s requires at least PHP version %s to function. Your site is currently running PHP version %s. Please contact your hosting provider to upgrade your PHP version.', 'pepro-ultimate-invoice'), '<strong>PeproDev Ultimate Invoice for Woocommerce</strong>', '<strong>7.4</strong>', '<strong>' . PHP_VERSION . '</strong>');
      printf('<div class="notice notice-error"><p>%s</p></div>', $msg);
    });
  }
  return;
}
/**
 * if plugin was not initiated before, let's do it
 */
if (!class_exists("PeproUltimateInvoice")) {
  /**
   * Main class, accessible on initiation with global $PeproUltimateInvoice
   */
  class PeproUltimateInvoice {
    public $td      = "pepro-ultimate-invoice";
    public $version = "3.2.47";
    public $title   = "Ultimate Invoice";
    public $db_slug = "pepro-ultimate-invoice";
    public $plugin_dir;
    public $plugin_url;
    public $assets_url;
    public $plugin_basename;
    public $plugin_file;
    public $url;
    public $barcode;
    public $tpl;
    public $i18n;
    public $Unauthorized_Access;
    public $print;
    protected $invoice;
    protected $mta;
    protected $db_table = null;
    protected $manage_links = array();
    protected $meta_links = array();
    /**
     * construct plugin and set initiation hook and declare constants
     *
     * @method  __construct
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function __construct() {
      add_action("after_setup_theme", function(){
        // load textdomain for translations
      });
      $this->plugin_file     = __FILE__;
      $this->plugin_dir      = plugin_dir_path(__FILE__);
      $this->plugin_url      = plugins_url("", __FILE__);
      $this->assets_url      = plugins_url("/assets/", __FILE__);
      $this->plugin_basename = plugin_basename(__FILE__);
      $this->url             = admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=general");

      // for those use plugin in local environment
      // WordPress prefers wp-content/languages/plugins/ over the copy we ship, and the
      // one it downloads from translate.wordpress.org lags behind this release, which
      // left newer strings untranslated. Use whichever catalogue is newer.
      add_filter("load_textdomain_mofile", array($this, "prefer_bundled_translation"), 10, 2);
      add_filter("load_translation_file", array($this, "prefer_bundled_translation_file"), 10, 3);
      add_action("init", function(){ load_plugin_textdomain("pepro-ultimate-invoice", false, dirname(plugin_basename(__FILE__)) . "/languages/"); }, 1);
      add_action("init", function(){
        $this->title = __("Ultimate Invoice", "pepro-ultimate-invoice");
      }, 2);

      // define constants to be accessible out of the plugin class
      defined('PEPROULTIMATEINVOICE_VER') || define('PEPROULTIMATEINVOICE_VER', $this->version);
      defined('PEPROULTIMATEINVOICE_DIR') || define('PEPROULTIMATEINVOICE_DIR', plugin_dir_path(__FILE__));
      defined('PEPROULTIMATEINVOICE_URL') || define('PEPROULTIMATEINVOICE_URL', plugins_url("", __FILE__));
      defined('PEPROULTIMATEINVOICE_ASSETS_URL') || define('PEPROULTIMATEINVOICE_ASSETS_URL', $this->assets_url);
      add_action("admin_notices", array($this, "admin_notices"));
      add_action("admin_notices", array($this, "rtl_template_notice"));
      // register_deactivation_hook( __FILE__, function () { update_option("peprodev_ultimate_invoice_alert_viewed_yet", ""); });

      /**
       * add compatibility with High-Performance Order Storage
       */
      add_action("before_woocommerce_init", function () {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
          \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
      });

      if ($this->_wc_activated()) {
        // hook into wp init and load plugin other hooks
        add_action("init", array($this, 'init_plugin'));

        include_once $this->plugin_dir . 'include/vendor/autoload.php';
        $this->barcode = new \Picqer\Barcode\BarcodeGeneratorJPG();
        $this->barcode->useGd();
        include_once $this->plugin_dir . "include/admin/class-setting.php";
        include_once $this->plugin_dir . "include/admin/class-print.php";
        include_once $this->plugin_dir . "include/admin/class-template.php";
        include_once $this->plugin_dir . "include/admin/class-column.php";
        include_once $this->plugin_dir . 'include/admin/class-jdate.php';
        include_once $this->plugin_dir . 'include/admin/class-wcproduct-panel.php';
        include_once $this->plugin_dir . 'include/admin/class-icons.php';
        include_once $this->plugin_dir . 'include/admin/class-fields.php';
        include_once $this->plugin_dir . 'include/admin/class-themes.php';
        include_once $this->plugin_dir . 'include/admin/class-i18n.php';
        include_once $this->plugin_dir . 'include/admin/class-setup.php';

        // per language invoice texts, template and settings
        $this->i18n  = new \peproulitmateinvoice\PeproUltimateInvoice_I18n();
        // handle template based functions
        $this->tpl   = new \peproulitmateinvoice\PeproUltimateInvoice_Template();
        // handle print invoice functions
        $this->print = new \peproulitmateinvoice\PeproUltimateInvoice_Print();
        // handle metaboxes and extras for wc orders
        $this->mta   = new \peproulitmateinvoice\PeproUltimateInvoice_Columns();

        // attach pdf to woocommerce emails automatically
        if ("yes" == $this->tpl->get_attach_pdf_invoices_to_mail()) {
          add_filter('woocommerce_email_attachments', array($this, "attach_pdf_to_wC_emails"), 10, 3);
        }

        // automatic email sending
        if ("automatic" == $this->tpl->get_send_invoices_via_email() || "automatic" == $this->tpl->get_send_invoices_via_email_admin()) {
          add_action("woocommerce_order_status_changed", array($this, "woocommerce_order_status_changed_action"), 10, 3);
          add_action("woocommerce_new_order", array($this, "woocommerce_new_order_action"), 10, 1);
        }

        // initiate plugin main instance
        if (is_admin()) {
          (new PeproUltimateInvoice_wcPanel())->init();
        }

        // disable woocommerce modern admin dashboard, has to be called here!
        if ("yes" == $this->tpl->get_disable_wc_dashboard()) {
          add_filter('woocommerce_admin_disabled', '__return_true');
          add_filter('woocommerce_marketing_menu_items', '__return_empty_array');
          add_filter('woocommerce_helper_suppress_admin_notices', '__return_true');
        }

        add_filter("woocommerce_hidden_order_itemmeta", array($this, "hidden_order_itemmeta"));
        add_filter("woocommerce_get_order_item_totals", array($this, "woocommerce_order_discount_to_display"), 10, 3);

        add_filter('woocommerce_format_weight', function ($weight) {
          return str_replace(array('kg', 'g', 'lbs', 'oz',), array(__('kg', "pepro-ultimate-invoice"), __('g', "pepro-ultimate-invoice"), __('lbs', "pepro-ultimate-invoice"), __('oz', "pepro-ultimate-invoice"),), $weight);
        });
        add_filter('woocommerce_format_dimensions', function ($weight) {
          return str_replace(array('mm', 'cm', 'in', 'yd',), array(__('mm', "pepro-ultimate-invoice"), __('cm', "pepro-ultimate-invoice"), __('in', "pepro-ultimate-invoice"), __('yd', "pepro-ultimate-invoice"),), $weight);
        });

        if (is_blog_admin() && isset($_GET["tab"]) && sanitize_text_field($_GET["tab"]) == sanitize_text_field("pepro_ultimate_invoice")) {
          add_filter('woocommerce_admin_disabled', '__return_true');
          add_filter('woocommerce_marketing_menu_items', '__return_empty_array');
          add_filter('woocommerce_helper_suppress_admin_notices', '__return_true');
        }

        // change colors and template if invoice is pre-invoice
        add_filter("puiw_get_default_dynamic_params", array($this, "puiw_get_default_dynamic_params"), 10, 2);
        add_filter('query_vars', function ($vars) {
          $vars[] = "tp";
          $vars[] = "pclr";
          $vars[] = "sclr";
          $vars[] = "tclr";
          return $vars;
        });

      }
    }
    /**
     * point a .mo lookup at the bundled catalogue when ours is the newer one
     *
     * @method prefer_bundled_translation
     * @param string $mofile
     * @param string $domain
     * @return string
     * @version 1.0.0
     * @since 2.4.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function prefer_bundled_translation($mofile, $domain) {
      if ($this->td !== $domain) {
        return $mofile;
      }
      return $this->newest_translation($mofile, "mo");
    }
    /**
     * same thing for the php translation format used since WordPress 6.5
     *
     * @method prefer_bundled_translation_file
     * @param string $file
     * @param string $domain
     * @param string $locale
     * @return string
     * @version 1.0.0
     * @since 2.4.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function prefer_bundled_translation_file($file, $domain, $locale) {
      if ($this->td !== $domain) {
        return $file;
      }
      $ext = "php" === strtolower((string) pathinfo($file, PATHINFO_EXTENSION)) ? "l10n.php" : "mo";
      return $this->newest_translation($file, $ext, $locale);
    }
    /**
     * compare the catalogue WordPress picked with the one we ship
     *
     * @method newest_translation
     * @param string $candidate
     * @param string $ext
     * @param string $locale
     * @return string
     * @version 1.0.0
     * @since 2.4.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    protected function newest_translation($candidate, $ext = "mo", $locale = "") {
      $locale = $locale ? $locale : determine_locale();
      $bundled = plugin_dir_path(__FILE__) . "languages/{$this->td}-{$locale}.{$ext}";
      if (!is_readable($bundled) || $bundled === $candidate) {
        return $candidate;
      }
      // the catalogue we ship always matches this version's strings; a language pack
      // from translate.wordpress.org lags behind it, and comparing file dates let a pack
      // fetched today replace newer translations with English for every new string
      return $bundled;
    }
    public function admin_notices() {
      $message = "";
      $seen = get_option("peprodev_ultimate_invoice_alert_viewed_yet", "");
      if (!$seen || $seen !== $this->version) {
      }
      if (!$this->_wc_activated()) {
        $installWC = admin_url("plugin-install.php?s=woocommerce&tab=search&type=term");
        $alerttext = sprintf(__("%s needs %s to function. Please install and activate it.", "pepro-ultimate-invoice"), "<strong>PeproDev Ultimate Invoice for Woocommerce</strong>", "<a href='$installWC' target='_blank'><strong>WooCommerce</strong></a>");
        $message = "<div style='padding: 0.3rem 0.5rem;line-height: 1; margin-inline-start: -0.5rem;'><img src='{$this->assets_url}img/peprodev.svg' width='32px' /></div>";
        $message .= "<div>$alerttext</div>";
        $message = apply_filters("peprodevups_alert_after_active", $message);
      }
      // check php version be 7.4 at least or show warning and disable plugin
      if (version_compare(PHP_VERSION, '7.4', '<')) {
        $alerttext = sprintf(__("%s requires at least PHP version %s to function. Your site is currently running PHP version %s. Please contact your hosting provider to upgrade your PHP version.", "pepro-ultimate-invoice"), "<strong>PeproDev Ultimate Invoice for Woocommerce</strong>", "<strong>7.4</strong>", "<strong>" . PHP_VERSION . "</strong>");
        $message = "<div style='padding: 0.3rem 0.5rem;line-height: 1; margin-inline-start: -0.5rem;'><img src='{$this->assets_url}img/peprodev.svg' width='32px' /></div>";
        $message .= "<div>$alerttext</div>";
        $message = apply_filters("peprodevups_alert_after_active", $message);
      }
      if (!empty($message)) printf('<div class="%1$s" style="border-color: #ffa176;"><div class="pepro_alert" style="display: flex;align-items: center;">%2$s</div></div>', esc_attr("notice notice-info is-not-dismissible"), $message);
    }
    public function downloads_bulk_actions_edit_product($actions) {
      // recorded for the debug tab, so a site missing the menu entries can be told why
      set_transient("puiw_bulk_actions_present", 1, DAY_IN_SECONDS);
      $actions['invoice_zip'] = __("Bulk Download PDF Invoice", "pepro-ultimate-invoice");
      $actions['invoice_pos_zip'] = __("Bulk Download POS PDF Invoice", "pepro-ultimate-invoice");
      $actions['invoice_inventory_bulk'] = __("Bulk Print Inventory Report", "pepro-ultimate-invoice");
      $actions['invoice_slips_bulk'] = __("Bulk Print Packing Slips", "pepro-ultimate-invoice");
      $actions['invoice_slips_pdf_zip'] = __("Bulk Download Packing Slips (ZIP of PDF)", "pepro-ultimate-invoice");
      $actions['invoice_slips_pdf_merged'] = __("Bulk Download Packing Slips (single PDF)", "pepro-ultimate-invoice");
      return $actions;
    }
    public function downloads_handle_bulk_action_edit_shop_order($redirect_to, $action, $post_ids) {
      global $attach_download_dir, $attach_download_file;

      if ($action == 'invoice_zip') {
        $redirect_to = add_query_arg(array('invoice-zip' => implode(',', $post_ids)), $redirect_to);
      }
      if ($action == 'invoice_pos_zip') {
        $redirect_to = add_query_arg(array('invoice-pos-zip' => implode(',', $post_ids)), $redirect_to);
      }
      if ($action == 'invoice_inventory_bulk') {
        $redirect_to = add_query_arg(array('inventory-bulk' => implode(',', $post_ids)), $redirect_to);
      }
      if ($action == 'invoice_slips_bulk') {
        $redirect_to = add_query_arg(array('slips-bulk' => implode(',', $post_ids)), $redirect_to);
      }
      if ($action == 'invoice_slips_pdf_zip') {
        $redirect_to = add_query_arg(array('slips-pdf-zip' => implode(',', $post_ids)), $redirect_to);
      }
      if ($action == 'invoice_slips_pdf_merged') {
        $redirect_to = add_query_arg(array('slips-pdf-merged' => implode(',', $post_ids)), $redirect_to);
      }

      return $redirect_to;
    }
    public function debugEnabled($debug_true = true, $debug_false = false) {
      return apply_filters("puiw_debug_enabled", $debug_true);
    }
    /**
     * woocommerce order placed hook to send emails
     *
     * @method woocommerce_new_order_action
     * @param int $order_id
     * @version 1.0.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function woocommerce_new_order_action($order_id) {
      $order = wc_get_order($order_id);
      if (!$order) {
        return;
      }
      $note = "";
      if ("automatic" == $this->tpl->get_send_invoices_via_email()) {
        $email = $order->get_billing_email();
        $wp_mail = $this->send_formatted_email($order_id, $email, true);
        if ($wp_mail) {
          $note .= "<br />" . sprintf(__("Automatic Email sent to customer mail address:<br> %s", "pepro-ultimate-invoice"), $email);
        } else {
          $note .= "<br />" . sprintf(__("An error occured sending Automatic Email to customer mail address:<br> %s", "pepro-ultimate-invoice"), $email);
        }
      }
      if ("automatic" == $this->tpl->get_send_invoices_via_email_admin()) {
        $valid_reciever_shopmngrs = $this->tpl->get_send_invoices_via_email_shpmngrs();
        if (!empty($valid_reciever_shopmngrs)) {
          $wp_mail = $this->send_formatted_email($order_id, $valid_reciever_shopmngrs, true);
          if ($wp_mail) {
            $note .= "<br />" . sprintf(__("Automatic Email sent to following shop managers:<br> %s", "pepro-ultimate-invoice"), (count($valid_reciever_shopmngrs) > 1 ? implode(", ", $valid_reciever_shopmngrs) : $valid_reciever_shopmngrs));
          } else {
            $note .= "<br />" . sprintf(__("An error occured sending Automatic Email to following shop managers:<br> %s", "pepro-ultimate-invoice"), (count($valid_reciever_shopmngrs) > 1 ? implode(", ", $valid_reciever_shopmngrs) : $valid_reciever_shopmngrs));
          }
        } else {
          $note .= "<br />" . __("No Shop Manager selected to send Automatic Email to.", "pepro-ultimate-invoice");
        }
      }
      if (!empty($note)) {
        $order->add_order_note($note);
      }
    }
    /**
     * woocommerce order status changed hook to send emails
     *
     * @method woocommerce_order_status_changed_action
     * @param int $order_id
     * @param string $old_status
     * @param string $new_status
     * @version 1.0.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function woocommerce_order_status_changed_action($order_id, $old_status, $new_status) {
      // $order = wc_get_order( $order_id );
      $old_status = "wc-$old_status";
      $new_status = "wc-$new_status";
      $order = wc_get_order($order_id);
      $note = "";
      if ("automatic" == $this->tpl->get_send_invoices_via_email()) {
        $valid_order_status = $this->tpl->get_send_invoices_via_email_opt(array("wc-completed"));
        if (in_array($new_status, apply_filters('puiw_valid_order_statuses_customer_auto_email', (array) $valid_order_status))) {
          $email = $order->get_billing_email();
          $wp_mail = $this->send_formatted_email($order_id, $email, true);
          if ($wp_mail) {
            $note .= "<br />" . sprintf(__("Automatic Email sent to customer mail address:<br> %s", "pepro-ultimate-invoice"), $email);
          } else {
            $note .= "<br />" . sprintf(__("An error occured sending Automatic Email to customer mail address:<br> %s", "pepro-ultimate-invoice"), $email);
          }
        }
      }
      if ("automatic" == $this->tpl->get_send_invoices_via_email_admin()) {
        $valid_order_status = $this->tpl->get_send_invoices_via_email_opt_admin(array("wc-completed"));
        if (in_array($new_status, apply_filters('puiw_valid_order_statuses_shopmngr_auto_email', (array) $valid_order_status))) {
          $valid_reciever_shopmngrs = $this->tpl->get_send_invoices_via_email_shpmngrs();
          if (!empty($valid_reciever_shopmngrs)) {
            $wp_mail = $this->send_formatted_email($order_id, $valid_reciever_shopmngrs, true);
            if ($wp_mail) {
              $note .= "<br />" . sprintf(__("Automatic Email sent to following shop managers:<br> %s", "pepro-ultimate-invoice"), (count($valid_reciever_shopmngrs) > 1 ? implode(", ", $valid_reciever_shopmngrs) : $valid_reciever_shopmngrs));
            } else {
              $note .= "<br />" . sprintf(__("An error occured sending Automatic Email to following shop managers:<br> %s", "pepro-ultimate-invoice"), (count($valid_reciever_shopmngrs) > 1 ? implode(", ", $valid_reciever_shopmngrs) : $valid_reciever_shopmngrs));
            }
          } else {
            $note .= "<br />" . __("No Shop Manager selected to send Automatic Email to.", "pepro-ultimate-invoice");
          }
        }
      }
      if (!empty($note)) {
        $order->add_order_note($note);
      }
    }
    /**
     * send mail using pepro ultimate invoice core
     *
     * @method send_formatted_email
     * @param int $order_id
     * @param string $email
     * @param boolean $attach
     * @return string wp_mail status
     * @version 1.0.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function send_formatted_email($order_id, $email, $attach) {
      add_filter("puiw_printinvoice_return_html_minfied", "__return_false", 10, 1);
      $invc = $this->print->create_html($order_id, "HTML", "", true, true);
      $cssToInlineStyles = new CssToInlineStyles($invc);
      $cssToInlineStyles->setCleanup(true);
      $cssToInlineStyles->setUseInlineStylesBlock(true);
      $invc = $cssToInlineStyles->convert();
      $subject = apply_filters("puiw_email_invoice_customer_subject", sprintf($this->tpl->get_email_subject(_x("Order #%s invoice on ", "wc-setting", "pepro-ultimate-invoice") . get_bloginfo('name', 'display')), $order_id));
      $attachments = array();
      $pdf_path    = "";
      if ($attach) {
        $namedir = $this->print->get_pdf_temp_dir();
        $namedir = apply_filters("puiw_get_default_mail_pdf_temp_path", $namedir);
        $invcPDF = $this->print->create_pdf($order_id, false, "S", false);
        if ($invcPDF && is_readable("$namedir/$invcPDF")) {
          $pdf_path      = "$namedir/$invcPDF";
          $attachments[] = $pdf_path;
        }
      }
      $wp_mail = wp_mail($email, $subject, $invc, array('Content-Type: text/html; charset=UTF-8', "From: {$this->tpl->get_email_from_name()} <{$this->tpl->get_email_from_address()}>"), $attachments);
      if ($pdf_path && file_exists($pdf_path)) {
        unlink($pdf_path);
      }
      return $wp_mail;
    }
    public function pwoosms_shortcodes_list($shortcodes) {
      return "<br><strong>{$this->title}</strong><br>" .
        "<code>{track_id}</code> = " . __("Shipping Track Serial", "pepro-ultimate-invoice") . "<br>" .
        "<code>{invoice_link}</code> = " . __("Link to HTML invoice", "pepro-ultimate-invoice") . "<br>" .
        "<code>{invoice_pdf_link}</code> = " . __("Link to PDF invoice", "pepro-ultimate-invoice") . "<br>" . PHP_EOL . $shortcodes;
    }
    public function sms_body_after_replace($content, $order_id, $order, $all_product_ids, $vendor_product_ids) {
      if (!$order_id) {
        return $content;
      }
      $order = wc_get_order($order_id);
      if (!$order) {
        return $content;
      }
      $content = str_replace("{track_id}", $this->get_order_track_id($order), $content);
      $content = str_replace("{invoice_link}", $this->get_public_invoice_url($order, "HTML"), $content);
      $content = str_replace("{invoice_pdf_link}", $this->get_public_invoice_url($order, "PDF"), $content);
      return $content;
    }
    /**
     * read shipping track serial regardless of which meta key it was stored in
     *
     * @method get_order_track_id
     * @param WC_Order|int $order
     * @return string
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function get_order_track_id($order) {
      $order = is_a($order, "WC_Order") ? $order : wc_get_order($order);
      if (!$order) {
        return "";
      }
      $track_id = $order->get_meta("_shipping_puiw_invoice_track_id", true);
      if (empty($track_id)) {
        $track_id = $order->get_meta("puiw_invoice_track_id", true);
      }
      return apply_filters("puiw_get_order_track_id", $track_id, $order);
    }
    /**
     * read shipping date regardless of which meta key it was stored in
     *
     * @method get_order_ship_date
     * @param WC_Order|int $order
     * @param bool $shamsi return the jalali variant
     * @return string
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function get_order_ship_date($order, $shamsi = false) {
      $order = is_a($order, "WC_Order") ? $order : wc_get_order($order);
      if (!$order) {
        return "";
      }
      $key  = $shamsi ? "_shipping_puiw_invoice_shipdatefa" : "_shipping_puiw_invoice_shipdate";
      $date = $order->get_meta($key, true);
      if (empty($date)) {
        $date = $order->get_meta(ltrim($key, "_"), true);
      }
      return apply_filters("puiw_get_order_ship_date", $date, $order, $shamsi);
    }
    /**
     * build a public, key-signed invoice url usable outside of a logged-in session
     *
     * @method get_public_invoice_url
     * @param WC_Order|int $order
     * @param string $type HTML|PDF
     * @return string
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function get_public_invoice_url($order, $type = "HTML") {
      $order = is_a($order, "WC_Order") ? $order : wc_get_order($order);
      if (!$order) {
        return "";
      }
      $url = add_query_arg(array(
        "pepro-invoice" => "PDF" === strtoupper($type) ? "pdf" : "html",
        "order"         => $order->get_id(),
        "key"           => $order->get_order_key(),
      ), home_url("/"));
      return apply_filters("puiw_get_public_invoice_url", $url, $order, $type);
    }
    /**
     * customized die wp function
     *
     * @method die
     * @param string $title
     * @param string $msg
     * @return string html die msg
     * @version 1.0.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function die($preTitle = "", $title = "ERR", $msg = "") {
      $preTitle2 = "user-guest";
      $preTitle2 = (get_current_user_id() > 0) ? ("user-" . get_current_user_id()) : $preTitle2;
      // the access-denied page went out as 200 OK, so links checkers and caches kept it as the invoice
      if (!headers_sent()) {
        status_header(403);
        nocache_headers();
      }
      $ext = "
              @font-face { font-family: 'bodyfont'; font-style: normal; font-weight: normal; src: url('" . PEPROULTIMATEINVOICE_URL . "/assets/css/96594ad4.woff') format('woff'); }
              @font-face { font-family: 'bodyfont'; font-style: normal; font-weight: bold; src: url('" . PEPROULTIMATEINVOICE_URL . "/assets/css/96594ad5.woff') format('woff'); }";
      $html = '<title>' . $title . '</title><!--ERR: ' . $preTitle . ' --><style type="text/css">' . $ext .
        'html { background: #f1f1f1; } body { background: #fff; color: #444; font-family: bodyfont, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
              margin: 2em auto; padding: 1em 2em; max-width: 700px; -webkit-box-shadow: 0 1px 3px rgba(0, 0, 0, 0.13); box-shadow: 0 1px 3px rgba(0, 0, 0, 0.13); width: 80%; max-height: 130px;}
              h1 { border-bottom: 1px solid #dadada; clear: both; color: #666; font-size: 24px; margin: 30px 0 0 0; padding: 0; padding-bottom: 7px; } #error-page { margin-top: 50px; }
              #error-page p, #error-page .wp-die-message { font-size: 14px; line-height: 1.5; margin: 25px 0 20px; }
              #error-page code { font-family: Consolas, Monaco, monospace; } ul li { margin-bottom: 10px; font-size: 14px ; }
              a { color: #0073aa; } a:hover, a:active { color: #00a0d2; }
              a:focus { color: #124964; -webkit-box-shadow: 0 0 0 1px #5b9dd9, 0 0 2px 1px rgba(30, 140, 190, 0.8); box-shadow: 0 0 0 1px #5b9dd9, 0 0 2px 1px rgba(30, 140, 190, 0.8); outline: none; }
              .button { background: #f7f7f7; border: 1px solid #ccc; color: #555; display: inline-block; text-decoration: none; font-size: 13px; line-height: 2; height: 28px; margin: 0; padding: 0 10px 1px; cursor: pointer;
                -webkit-border-radius: 3px; -webkit-appearance: none; border-radius: 3px; white-space: nowrap; -webkit-box-sizing: border-box; -moz-box-sizing: border-box; box-sizing: border-box; -webkit-box-shadow: 0 1px 0 #ccc;
                box-shadow: 0 1px 0 #ccc; vertical-align: top; } .button.button-large { height: 30px; line-height: 2.15384615; padding: 0 12px 2px; } .button:hover, .button:focus { background: #fafafa; border-color: #999; color: #23282d; }
                .button:focus { border-color: #5b9dd9; -webkit-box-shadow: 0 0 3px rgba(0, 115, 170, 0.8); box-shadow: 0 0 3px rgba(0, 115, 170, 0.8); outline: none; } .button:active { background: #eee; border-color: #999;
                  -webkit-box-shadow: inset 0 2px 5px -3px rgba(0, 0, 0, 0.5); box-shadow: inset 0 2px 5px -3px rgba(0, 0, 0, 0.5); }body { font-family: bodyfont, Tahoma, Arial; }	</style>
                  <body id="error-page ' . "$preTitle $preTitle2" . '"><div class="wp-die-message">' . $msg . '</div></body></html>';
      die($html);
    }
    /**
     * Initiate plugin with init hook
     *
     * @method  init_plugin
     * @version 1.1.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function init_plugin() {
      // add compatibility with WPC Product Bundles for WooCommerce By WPClever
      // Since v. 1.3.5
      if (class_exists('WPCleverWoosb') && function_exists('WPCleverWoosb')) {
        $WPCleverWoosb = WPCleverWoosb();
        remove_action("woocommerce_before_order_itemmeta", array($WPCleverWoosb, 'woosb_before_order_item_meta'), 10);
        remove_action('woocommerce_order_formatted_line_subtotal', array($WPCleverWoosb, 'woosb_order_formatted_line_subtotal'), 10, 2);
        // remove_all_actions("woocommerce_before_order_itemmeta", 10);
      }

      // registered late so themes and other plugins rewriting the bulk menu cannot drop our entries
      if ( class_exists("\Automattic\WooCommerce\Utilities\OrderUtil") && OrderUtil::custom_orders_table_usage_is_enabled()) {
        // HPOS usage is enabled.
        add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'downloads_bulk_actions_edit_product'), 999, 1);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'downloads_handle_bulk_action_edit_shop_order'), 10, 3);
      }
      else {
        // Traditional CPT-based orders are in use.
        add_filter('bulk_actions-edit-shop_order', array($this, 'downloads_bulk_actions_edit_product'), 999, 1);
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'downloads_handle_bulk_action_edit_shop_order'), 10, 3);
      }

      // key-signed public invoice link, used by sms/email shortcodes
      if (isset($_GET["pepro-invoice"], $_GET["order"], $_GET["key"])) {
        $order_id = (int) sanitize_text_field(wp_unslash($_GET["order"]));
        $order    = $order_id ? wc_get_order($order_id) : false;
        $key      = sanitize_text_field(wp_unslash($_GET["key"]));
        if ($order && $key && hash_equals($order->get_order_key(), $key)) {
          $mode = "pdf" === sanitize_text_field(wp_unslash($_GET["pepro-invoice"])) ? "pdf" : "html";
          add_filter("puiw_printinvoice_check_user_has_access", "__return_true", 999);
          if ("pdf" === $mode) {
            die($this->print->create_pdf($order_id, false, "I", false));
          }
          die($this->print->create_html($order_id, "HTML", "", "", true));
        }
        wp_die(esc_html__("Invalid or expired invoice link.", "pepro-ultimate-invoice"), "", array("response" => 403));
      }

      // public methods to get receipts
      $requested = array("invoice", "invoice-pdf", "invoice-pos", "invoice-slips", "invoice-slips-pdf", "invoice-inventory");
      foreach ($requested as $param) {
        if (!isset($_GET[$param]) || "" === trim(sanitize_text_field(wp_unslash($_GET[$param])))) {
          continue;
        }
        $order_id       = (int) trim(sanitize_text_field(wp_unslash($_GET[$param])));
        $force_download = !empty($_GET["download"]);
        // a link to an order that is gone used to answer with an empty white page
        if (!$order_id || !wc_get_order($order_id)) {
          wp_die(
            esc_html__("This invoice does not exist. The order may have been deleted, or the link is wrong.", "pepro-ultimate-invoice"),
            esc_html__("Invoice not found", "pepro-ultimate-invoice"),
            array("response" => 404, "back_link" => true)
          );
        }
        switch ($param) {
          case "invoice":
            die($this->print->create_html($order_id));
          case "invoice-pdf":
            $out = $this->print->create_pdf($order_id, $force_download, "I", false);
            break;
          case "invoice-pos":
            $this->setup_thermal_invoice();
            $out = $this->print->create_pdf($order_id, $force_download, "I", false, true);
            break;
          case "invoice-slips":
            die($this->print->create_slips($order_id));
          case "invoice-slips-pdf":
            $out = $this->print->create_slips_pdf($order_id, $force_download);
            break;
          case "invoice-inventory":
            die($this->print->create_inventory($order_id));
        }
        // the PDF engine failed; say so instead of sending an empty page
        if (false === $out) {
          wp_die(
            esc_html__("The PDF could not be generated. The error is in WooCommerce, Status, Logs, under pepro-ultimate-invoice.", "pepro-ultimate-invoice"),
            esc_html__("Invoice could not be generated", "pepro-ultimate-invoice"),
            array("response" => 500, "back_link" => true)
          );
        }
        die($out);
      }


      // admin methods for bulk actions and setting export/import
      if (is_admin() && !is_ajax() && current_user_can("manage_options") && is_blog_admin()) {
        #region packing slips bulk actions
        if (isset($_GET["slips-pdf-zip"]) || isset($_GET["slips-pdf-merged"])) {
          $merged    = isset($_GET["slips-pdf-merged"]);
          $raw       = sanitize_text_field(wp_unslash($merged ? $_GET["slips-pdf-merged"] : $_GET["slips-pdf-zip"]));
          $order_ids = array_filter(array_map("intval", array_map("trim", explode(",", $raw))));
          $pdfs      = array();
          $temp_dir  = $this->print->get_pdf_temp_dir();
          foreach ($order_ids as $order_id) {
            if (!wc_get_order($order_id)) {
              continue;
            }
            $file = $this->print->create_slips_pdf($order_id, false, "S", false);
            if ($file && is_readable("$temp_dir/$file")) {
              $pdfs[] = "$temp_dir/$file";
            }
          }
          if (empty($pdfs)) {
            wp_die(esc_html__("No packing slip could be generated for the selected orders.", "pepro-ultimate-invoice"), esc_html__("Error", "pepro-ultimate-invoice"), array("back_link" => true));
          }
          $date_time = date_i18n("Y_m_d_H_i_s", current_time("timestamp"));
          $random    = wp_generate_password(8, false, false);
          if ($merged) {
            require_once PEPROULTIMATEINVOICE_DIR . "/include/vendor/autoload.php";
            try {
              $mpdf = new \Mpdf\Mpdf(array("mode" => "utf-8", "format" => "A4"));
              foreach ($pdfs as $file) {
                $pages = $mpdf->setSourceFile($file);
                for ($page = 1; $page <= $pages; $page++) {
                  $tpl  = $mpdf->importPage($page);
                  $size = $mpdf->getTemplateSize($tpl);
                  $mpdf->AddPageByArray(array("orientation" => $size["width"] > $size["height"] ? "L" : "P", "newformat" => array($size["width"], $size["height"])));
                  $mpdf->useTemplate($tpl);
                }
              }
              foreach ($pdfs as $file) { is_file($file) and unlink($file); }
              $mpdf->Output("PackingSlips-$date_time-$random.pdf", "D");
              exit;
            } catch (\Throwable $e) {
              foreach ($pdfs as $file) { is_file($file) and unlink($file); }
              wp_die(esc_html($e->getMessage()), esc_html__("Error", "pepro-ultimate-invoice"), array("back_link" => true));
            }
          }
          $name_dir = $this->print->get_pdf_temp_dir();
          if (!file_exists($name_dir)) @mkdir($name_dir, 0777, true);
          $name_dot_ext = "PackingSlips-$date_time-$random.zip";
          $zip_file     = "{$name_dir}/{$name_dot_ext}";
          file_exists($zip_file) and unlink($zip_file);
          $zip = new \ZipArchive;
          if ($zip->open($zip_file, $zip::CREATE | $zip::OVERWRITE) !== TRUE) {
            wp_die(esc_html__("Failed creating zip archive!", "pepro-ultimate-invoice"), esc_html__("Error", "pepro-ultimate-invoice"), array("back_link" => true));
          }
          foreach ($pdfs as $file) @$zip->addFile($file, basename($file));
          $zip->close();
          foreach ($pdfs as $file) { is_file($file) and unlink($file); }
          header("Content-type: application/force-download");
          header("Content-Disposition: attachment; filename={$name_dot_ext}");
          readfile($zip_file);
          unlink($zip_file);
          exit;
        }
        #endregion

        #region invoice zip bulk action
        if (isset($_GET["invoice-zip"]) && !empty(trim(sanitize_text_field($_GET["invoice-zip"])))) {
          $pdfs = array();
          $order_ids_valid = array();
          $order_ids_name = array();
          $order_ids = (array) explode(",", $_GET["invoice-zip"]);
          $order_ids = array_map("trim", $order_ids); $i=0;
          foreach ($order_ids as $order_id) {
            try {
              $order = wc_get_order($order_id);
              if ($order) { $i++;
                $order_ids_valid[] = $order_id;
                $pdf_temp = $this->print->create_pdf((int)$order_id, false, "S", false);
                if (file_exists($this->print->get_pdf_temp_dir() . "/$pdf_temp")) { $pdfs[] = $this->print->get_pdf_temp_dir() . "/$pdf_temp"; }
                $order_ids_name[] = "FILE {$i}: {$pdf_temp}" . PHP_EOL . "MD5: " . md5_file($this->print->get_pdf_temp_dir() . "/$pdf_temp") . PHP_EOL . "SHA1: " . sha1_file($this->print->get_pdf_temp_dir() . "/$pdf_temp") . PHP_EOL;
              }
            } catch (\Throwable $th) {
              //throw $th;
            }
          }
          $date_now = current_time('timestamp');
          $date_time = date_i18n("Y_m_d_H_i_s", $date_now);
          $name_dir = $this->print->get_pdf_temp_dir();
          if (!file_exists($name_dir)) @mkdir($name_dir, 0777, true);
          $random = wp_generate_password(8, false, false);
          $name_dot_ext = "InvoicesArchive-$date_time-$random.zip";
          $zip_file = "{$name_dir}/{$name_dot_ext}";
          file_exists($zip_file) and unlink($zip_file);
          $zip = new \ZipArchive;
          if ($zip->open($zip_file, $zip::CREATE | $zip::OVERWRITE) === TRUE) {
            foreach ($pdfs as $file) @$zip->addFile($file, basename($file));
            $comments_lines = array(
              "== WooCommerce PDF Invoices Zipped Archive File ==",
              "Created by *Pepro Ultimate Invoice for WooCommerce* v{$this->version}",
              "Developed by Pepro Dev. Group ( https://peprodev.com/ )",
              "Read more: https://wordpress.org/plugins/pepro-ultimate-invoice/",
              "Disclaimer: Pepro Dev. Group is not responsible for the content of files included in this archive." . PHP_EOL . PHP_EOL,
              "Archive Created Date: " . $this->tpl->get_date("now"),
              "Archive Generated on: ".str_replace(["https", "http", "://www.", "://"], "", $this->tpl->get_store_website())." ({$this->tpl->get_store_name()})",
              "Site Admin Email: {$this->tpl->get_store_email()}" . PHP_EOL,
              "This archive should contain following Invoice PDF files:" . PHP_EOL,
              implode(PHP_EOL, $order_ids_name),
            );
            $zip->setArchiveComment(implode(PHP_EOL, $comments_lines));
            $zip->close();
          }
          else {
            wp_die(sprintf(__("Failed creating zip archive! (code: %s) %s", "pepro-ultimate-invoice"), $zip->status, "<br>" . $zip->getStatusString()), __("Error", "pepro-ultimate-invoice"), array("back_link" => true));
            exit;
          }
          foreach ($pdfs as $file) { is_file($file) and unlink($file); }
          header('Content-type: application/force-download');
          header("Content-Disposition: attachment; filename={$name_dot_ext}");
          readfile($zip_file);
          unlink($zip_file);
          exit;
        }
        #endregion
        #region invoice-pos zip bulk action
        if (isset($_GET["invoice-pos-zip"]) && !empty(trim(sanitize_text_field($_GET["invoice-pos-zip"])))) {
          $pdfs = array();
          $order_ids_valid = array();
          $order_ids_name = array();
          $order_ids = (array) explode(",", $_GET["invoice-pos-zip"]);
          $order_ids = array_map("trim", $order_ids); $i=0;
          foreach ($order_ids as $order_id) {
            try {
              $order = wc_get_order($order_id);
              if ($order) { $i++;
                $order_ids_valid[] = $order_id;
                $this->setup_thermal_invoice();
                $pdf_temp = $this->print->create_pdf((int)$order_id, false, "S", false, true);
                if (file_exists($this->print->get_pdf_temp_dir() . "/$pdf_temp")) { $pdfs[] = $this->print->get_pdf_temp_dir() . "/$pdf_temp"; }
                $order_ids_name[] = "FILE {$i}: $pdf_temp" . PHP_EOL . "MD5: " . md5_file($this->print->get_pdf_temp_dir() . "/$pdf_temp") . PHP_EOL . "SHA1: " . sha1_file($this->print->get_pdf_temp_dir() . "/$pdf_temp") . PHP_EOL;
              }
            } catch (\Throwable $th) {
              //throw $th;
            }
          }
          $date_now = current_time('timestamp');
          $date_time = date_i18n("Y_m_d_H_i_s", $date_now);
          $name_dir = $this->print->get_pdf_temp_dir();
          if (!file_exists($name_dir)) @mkdir($name_dir, 0777, true);
          $random = wp_generate_password(8, false, false);
          $name_dot_ext = "InvoicesPOSArchive-$date_time-$random.zip";
          $zip_file = "{$name_dir}/{$name_dot_ext}";
          file_exists($zip_file) and unlink($zip_file);
          $zip = new \ZipArchive;
          if ($zip->open($zip_file, $zip::CREATE | $zip::OVERWRITE) === TRUE) {
            foreach ($pdfs as $file) @$zip->addFile($file, basename($file));
            $comments_lines = array(
              "== WooCommerce PDF Invoices Zipped Archive File ==",
              "Created by *Pepro Ultimate Invoice for WooCommerce* v{$this->version}",
              "Developed by Pepro Dev. Group ( https://peprodev.com/ )",
              "Read more: https://wordpress.org/plugins/pepro-ultimate-invoice/",
              "Disclaimer: Pepro Dev. Group is not responsible for the content of files included in this archive." . PHP_EOL . PHP_EOL,
              "Archive Created Date: " . $this->tpl->get_date("now"),
              "Archive Generated on: ".str_replace(["https", "http", "://www.", "://"], "", $this->tpl->get_store_website())." ({$this->tpl->get_store_name()})",
              "Site Admin Email: {$this->tpl->get_store_email()}" . PHP_EOL,
              "This archive should contain following Invoice PDF files:" . PHP_EOL,
              implode(PHP_EOL, $order_ids_name),
            );
            $zip->setArchiveComment(implode(PHP_EOL, $comments_lines));
            $zip->close();
          }
          else {
            wp_die(sprintf(__("Failed creating zip archive! (code: %s) %s", "pepro-ultimate-invoice"), $zip->status, "<br>" . $zip->getStatusString()), __("Error", "pepro-ultimate-invoice"), array("back_link" => true));
            exit;
          }
          foreach ($pdfs as $file) { is_file($file) and unlink($file); }
          header('Content-type: application/force-download');
          header("Content-Disposition: attachment; filename={$name_dot_ext}");
          readfile($zip_file);
          unlink($zip_file);
          exit;
        }
        #endregion
        #region inventory bulk action
        if (isset($_GET["inventory-bulk"]) && !empty(trim(sanitize_text_field($_GET["inventory-bulk"])))) {
          $orderids = (array) explode(",", $_GET["inventory-bulk"]);
          $orderids = array_map("trim", $orderids);
          $inventory_temp = "";
          foreach ($orderids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
              $inventory_temp .= "<iframe
                    onload=\"this.style.width='100%';this.style.height = 0; this.style.height=(this.contentWindow.document.body.scrollHeight+20)+'px';this.contentWindow.document.body.getElementsByTagName('p')[0].style.display='none'; \"
                    frameborder='0' scrolling='no' src='" . home_url("?invoice-inventory=$order_id") . "' title='OrderID#$order_id'></iframe>";
            }
          }
          $printBtn = "<style> .print-button {
                  cursor: pointer;
                  text-decoration: none;
                  background-color: #555;
                  padding: 1rem;
                  margin: auto;
                  -webkit-margin-end: 2px;
                  margin-inline-end: 2px;
                  color: aliceblue;
                  display: inline-block;
                  border-radius: 15px;
                  line-height: 0;
                } @media print {
                  .print-button {
                    display: none;
                  }
                }</style>";
          $printBtn .= "<p style='text-align: center'><a class=\"print-button\" href=\"javascript:;\" onclick=\"window.print();return false;\" >" . __("Print", "pepro-ultimate-invoice") . "</a></p>";
          die("<title>" . __("Bulk Inventory Report", "pepro-ultimate-invoice") . "</title>{$printBtn}{$inventory_temp}");
        }
        #region slips bulk action
        if (isset($_GET["slips-bulk"]) && !empty(trim(sanitize_text_field($_GET["slips-bulk"])))) {
          $orderids = (array) explode(",", $_GET["slips-bulk"]);
          $orderids = array_map("trim", $orderids);
          $inventory_temp = "";
          foreach ($orderids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
              $inventory_temp .= "<iframe
                    onload=\"this.style.width='100%';this.style.height = 0; this.style.height=(this.contentWindow.document.body.scrollHeight+20)+'px';this.contentWindow.document.body.getElementsByTagName('p')[0].style.display='none'; \"
                    frameborder='0' scrolling='no' src='" . home_url("?invoice-slips=$order_id") . "' title='OrderID#$order_id'></iframe>";
            }
          }
          $printBtn = "<style>
                  .print-button {
                    cursor: pointer;
                    text-decoration: none;
                    background-color: #555;
                    padding: 1rem;
                    margin: auto;
                    -webkit-margin-end: 2px;
                    margin-inline-end: 2px;
                    color: aliceblue;
                    display: inline-block;
                    border-radius: 15px;
                    line-height: 0;
                   }
                   @media print { .print-button { display: none; }
                  }
                </style>";
          $printBtn .= "<p style='text-align: center'><a class=\"print-button\" href=\"javascript:;\" onclick=\"window.print();return false;\" >" . __("Print", "pepro-ultimate-invoice") . "</a></p>";
          die("<title>" . __("Bulk Packing Slip", "pepro-ultimate-invoice") . "</title>{$printBtn}{$inventory_temp}");
        }
        #endregion
      }

      if (current_user_can("manage_options") && is_admin() && isset($_GET["nonce"], $_GET["ultimate-invoice-reset"]) && wp_verify_nonce($_GET["nonce"], "pepro-ultimate-invoice")) {
        wp_die("Pepro Ultimate Invoice — FORCE RESET TO DEFAULT Settings done!<br />Return count: " . $this->change_default_settings("RESET"), "Force-reset Options", array("link_text" => __("Back to Settings", "pepro-ultimate-invoice"), "link_url" => admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=migrate"), "text_direction" => "ltr"));
      }
      if (current_user_can("manage_options") && is_admin() && isset($_GET["nonce"], $_GET["ultimate-invoice-set"]) && wp_verify_nonce($_GET["nonce"], "pepro-ultimate-invoice")) {
        wp_die("Pepro Ultimate Invoice — RESET TO DEFAULT Settings done!<br />Return count: " . $this->change_default_settings("SET"), "Reset Options", array("link_text" => __("Back to Settings", "pepro-ultimate-invoice"), "link_url" => admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=migrate"), "text_direction" => "ltr"));
      }
      if (current_user_can("manage_options") && is_admin() && isset($_GET["nonce"], $_GET["ultimate-invoice-clear"]) && wp_verify_nonce($_GET["nonce"], "pepro-ultimate-invoice")) {
        wp_die("Pepro Ultimate Invoice — FORCE CLEAR Settings done!<br />Return count: " . $this->change_default_settings("CLEAR"), "Clear Options", array("link_text" => __("Back to Settings", "pepro-ultimate-invoice"), "link_url" => admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=migrate"), "text_direction" => "ltr"));
      }
      if (current_user_can("manage_options") && is_admin() && isset($_GET["nonce"], $_GET["ultimate-invoice-get"]) && wp_verify_nonce($_GET["nonce"], "pepro-ultimate-invoice")) {
        $string = "<div class='log5'>" . highlight_string("<?php" . PHP_EOL . $this->change_default_settings("GET"), 1) . "</div><style>.log5{text-align: left; direction: ltr; padding: 1rem; display: block; overflow: auto;z-index: 77777777777 !important;position: relative;background: white;} .log5 code {background: transparent !important;}</style>";
        wp_die($string, "Export as PHP Script", array("link_text" => __("Back to Settings", "pepro-ultimate-invoice"), "link_url" => admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=migrate"), "text_direction" => "ltr"));
      }

      if ("yes" == $this->tpl->get_allow_quick_shop()) {
        add_shortcode("puiw_quick_shop", array($this, "integrate_with_shortcode"));
        if ($this->_vc_activated()) {
          add_action("vc_before_init", array($this, "integrate_with_vc"));
          if (function_exists('vc_add_shortcode_param')) {
            vc_add_shortcode_param("{$this->td}_about", array($this, 'vc_add_pepro_about'), plugins_url("/assets/js/vc.init" . $this->debugEnabled(".js", ".min.js"), __FILE__));
          }
        }
      }
      add_action("plugin_row_meta", array($this, "plugin_row_meta"), 10, 4);
      add_filter("plugin_action_links", array($this, "plugin_action_links"), 10, 2);
      add_action("admin_menu", array($this, "admin_menu"), 1000);
      add_action("admin_init", array($this, "admin_init"));
      add_action("wp_ajax_nopriv_puiw_{$this->td}", array($this, "handel_ajax_req"));
      add_action("wp_ajax_puiw_{$this->td}", array($this, "handel_ajax_req"));
      add_action("wp_ajax_puiw_setup_step", array("\\peproulitmateinvoice\\PeproUltimateInvoice_Setup", "ajax_save_step"));
      add_action("admin_notices", array("\\peproulitmateinvoice\\PeproUltimateInvoice_Setup", "notice"));
      add_action("admin_init", array("\\peproulitmateinvoice\\PeproUltimateInvoice_Setup", "maybe_dismiss"));
      add_action("wp_before_admin_bar_render", array($this, "wp_before_admin_bar_render"));

      add_filter("pwoosms_shortcodes_list", array($this, "pwoosms_shortcodes_list"));
      add_filter("pwoosms_order_sms_body_after_replace", array($this, "sms_body_after_replace"), -1, 5);

      if ("yes" == $this->tpl->get_allow_preorder_invoice()) {
        add_action("woocommerce_proceed_to_checkout", array($this, "woocommerce_after_cart_contents"), 1000);
      }
      if ("yes" == $this->tpl->get_allow_users_use_invoices()) {
        add_filter("woocommerce_my_account_my_orders_actions", array($this, 'add_view_invoice_button_orderpage'), 10, 2);
      }
      if ("yes" == $this->tpl->get_allow_quick_shop()) {
        add_shortcode("puiw_quick_shop", array($this, 'integrate_with_shortcode'));
      }

      add_action('woocommerce_admin_order_data_after_shipping_address', array($this, "after_shipping_shopmngr_provided_note"), 10, 1);
      add_action("woocommerce_order_details_after_order_table_items", array($this, "woocommerce_order_details_after_order_table_items"));
      add_action('woocommerce_checkout_update_order_meta', array($this, "woocommerce_checkout_update_order_meta"));
      add_action('woocommerce_checkout_update_user_meta', array($this, "woocommerce_checkout_update_user_meta"), 10, 2);
      add_filter('woocommerce_checkout_fields', array($this, "checkout_fields_add_uin"));
      add_filter('woocommerce_billing_fields', array($this, "billing_fields_add_uin"));
      add_action('woocommerce_customer_save_address', array($this, "uin_sync_address_meta"), 10, 2);
      add_action("woocommerce_order_details_before_order_table", array($this, "woocommerce_order_details_before_order_table"), -1000);
      add_filter("wc_order_statuses", array($this, "add_wc_order_statuses"));
      add_filter("woocommerce_admin_billing_fields", array($this, "woocommerce_admin_billing_fields"));
      add_filter("woocommerce_admin_shipping_fields", array($this, "woocommerce_admin_shipping_fields"));
      add_action("woocommerce_checkout_create_order_line_item", array($this, "prices_as_order_line_item_meta"), 20, 4);


      // leftover temp files are pruned by age inside get_pdf_temp_dir(), deleting them
      // on every request raced with mails generated in a parallel request

      // add_action( 'woocommerce_admin_order_data_after_shipping_address', array($this,'checkout_field_admin_display_uin'), 10, 1 );
      // add_action( 'woocommerce_admin_order_data_after_billing_address', array($this,'checkout_field_admin_display_uin'), 10, 1 );
      // add_action( 'woocommerce_admin_order_data_after_order_details', array($this,'checkout_field_admin_display_odt'), 10, 1 );

      $this->add_wc_prebuy_status();

      if ($this->version != get_option("puiw_last_import_version")) {
        // 2.x kept no version of its own; remember which one this was before the
        // marker moves on, so the upgrade step knows where the shop came from
        if ("" === (string) get_option("puiw_plugin_version", "")) {
          update_option("puiw_upgraded_from", (string) get_option("puiw_last_import_version", ""), false);
        }
        $this->change_default_settings("SET");
        update_option("puiw_last_import_version", $this->version);
      }
    }
    public function calculate_total_discount($order) {
      $total_discount_from_prices = 0;
      // Iterate through order items
      foreach ($order->get_items() as $item) {
        // Get line item prices
        $regular_price = (float) $item->get_meta('_line_regular_price', true); // Regular price
        $item_price = (float) $item->get_total() / (int) $item->get_quantity(); // Item price (total / quantity)
        // Calculate discount for this item
        if ($regular_price > $item_price) {
          $quantity = (int) $item->get_quantity();
          $item_discount = ($regular_price - $item_price) * $quantity;
          $total_discount_from_prices += $item_discount;
        }
      }
      // Add coupon discount if any
      $coupon_discount = $order->get_total_discount(); // Built-in WooCommerce method
      $total_discount = $total_discount_from_prices + $coupon_discount;
      return wc_price($total_discount);
      // return [
      //   'price_discount' => wc_price($total_discount_from_prices),
      //   'coupon_discount' => wc_price($coupon_discount),
      //   'total_discount' => wc_price($total_discount),
      // ];
    }
    /**
     * @param  array $args
     * @param  \WC_Order $order
     * @return void
     */
    public function modify_storage_invoice_params($args, $order) {
      $args["invoice_total"] = $order->get_formatted_order_total();
      $args["invoice_final_totals"] = $this->get_order_final_prices($order);
      $date_time = date_i18n("Y/m/d H:i", current_time("timestamp"));
      // the last line of the POS receipt; an add-on may replace or empty it
      $args["thermal_copyright"] = apply_filters("puiw_receipt_credit", "<p style='text-align:center; font-size: 0.8rem;font-family: Arial;'><bdi dir='ltr'>PeproDev Ultimate Invoice v{$this->version} (www.peprodev.com)<br><small>Generated at {$date_time}</small></bdi></p>", $order, $date_time);
      $order_note_a = apply_filters("puiw_printslips_order_note_customer", "<strong>" . __("Note provided by Customer", "pepro-ultimate-invoice") . "</strong><br>" . $this->tpl->get_order_note($order, "a"), $this->tpl->get_order_note($order, "a"), $order, $args);
      $order_note_b = apply_filters("puiw_printslips_order_note_shopmngr", "<strong>" . __("Note provided by Shop manager", "pepro-ultimate-invoice") . "</strong><br>" . $this->tpl->get_order_note($order, "b"), $this->tpl->get_order_note($order, "b"), $order, $args);
      $note_customer = !empty(trim((string) $this->tpl->get_order_note($order, "a"))) ? "<tr><div><td colspan='3' style='text-align:start; padding: 0.5rem;'>".$order_note_a."</td></div></tr>" : "";
      $note_shopmngr = !empty(trim((string) $this->tpl->get_order_note($order, "b"))) ? "<tr><div><td colspan='3' style='text-align:start; padding: 0.5rem;'>".$order_note_b."</td></div></tr>" : "";
      $args["order_notes_new"] = $note_customer . $note_shopmngr;
      return $args;
    }
    /**
     * @param  \WC_Order $order
     * @return void
     */
    public function get_order_final_prices($order){
      ob_start();
      $ttl = $order->get_order_item_totals();
      foreach ( $ttl as $key => $total ) {
        if ("payment_method" == $key) {
          ?>
            <tr class="puiw_totalrow transaction_id">
              <th colspan="2">کد رهگیری پرداخت:</th>
              <td width="4cm" colspan="1"><?php echo $order->get_transaction_id() ;?></td>
            </tr>
          <?php
        }
        if ( $key != "order_total" ){
          ?>
            <tr class="puiw_totalrow <?php echo $key;?>">
              <th colspan="2"><?php echo esc_html( $total['label'] ); ?></th>
              <td width="4cm" colspan="1"><?php echo ( "payment_method" == $key ) ? esc_html( $total['value'] ) : wp_kses_post( $total['value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
            </tr>
          <?php
        }
      }
      $html = ob_get_contents();
      ob_end_clean();
      return $html;
    }
    public function printinvoice_create_html_item_row($args, $item_id, $item, $product_id, $order) {
      // Retrieve saved price or use current price
      $saved_price = (float) $item->get_meta('_saved_price', true);
      $current_price = (float) $item->get_meta('_line_regular_price', true);
      // Determine which price to use
      $price_to_use = $saved_price > 0 ? $saved_price : $current_price;
      // Get the quantity
      $quantity = (int) $item->get_quantity();
      // Calculate net total
      $net_total = $price_to_use * $quantity;
      // Save net total
      $args['base_price'] = wc_price($net_total);
      $args['discount'] = wc_price($net_total - $item->get_total());
      $args['sku'] = !empty($args["sku"]) ? "({$args["sku"]})" : "";
      return $args;
    }
    public function setup_thermal_invoice(){
      add_filter("puiw_get_default_dynamic_params", array($this, "modify_storage_invoice_params"), 999, 2);
      add_filter("puiw_printinvoice_create_html_item_row_metas", array($this, "printinvoice_create_html_item_row"), 10, 5);
      add_filter("puiw_printinvoice_preserve_html_tags", function($arg, $opt, $order){$arg[] = "invoice_title"; $arg[] = "invoice_final_totals"; $arg[] = "thermal_copyright"; $arg[] = "order_notes_new"; return $arg;}, 10, 3);
      add_filter("puiw_printinvoice_preserve_english_numbers", function($arg, $opt, $order){$arg[] = "invoice_title"; $arg[] = "invoice_final_totals"; $arg[] = "thermal_copyright"; $arg[] = "order_notes_new"; return $arg;}, 10, 3);
      add_filter("puiw_get_export_pdf_name", function($name, $order_id_formatted, $order_id, $date){ return "sales-pos--$order_id_formatted.pdf"; }, 10, 4);
      add_filter("puiw_get_templates_list", function ($templates) { $templates[] = plugin_dir_path(__FILE__) . "template/default-pos-rtl/default.cfg"; return $templates; }, 10, 1);
      add_filter("puiw_get_template", function($template, $default){ return plugin_dir_path(__FILE__) . "template/default-pos-rtl"; }, 10, 2);
      add_filter("puiw_printinvoice_pdf_footer_new", "__return_empty_string");
    }
    public function plugin_row_meta($links_array, $plugin_file_name, $plugin_data, $status) {
      if (strpos($plugin_file_name, basename(__FILE__))) {
        $links_array[] = "<a href='mailto:support+UltimateInvoice@peprodev.com?subject=Ask for Help [Ultimate Invoice]'>" . __("Support", "pepro-ultimate-invoice") . "</a>";
      }
      return $links_array;
    }
    public function plugin_action_links($actions, $plugin_file) {
      if (plugin_basename(__FILE__) == $plugin_file) {
        $actions[$this->db_slug] = "<a href='$this->url'>" . __("Settings", "pepro-ultimate-invoice") . "</a>";
        $actions["puiw-setup"]   = sprintf('<a href="%s">%s</a>', esc_url(\peproulitmateinvoice\PeproUltimateInvoice_Setup::url()), esc_html__("Setup wizard", "pepro-ultimate-invoice"));
      }
      return $actions;
    }
    public function make_pdf_file($order_id = 0) {
      $order = wc_get_order($order_id);
      if (!$order_id || !$order) return false;
      $pdf_temp = $this->print->create_pdf((int) $order_id, false, "S", false);
      $pdf_file = $this->print->get_pdf_temp_dir() . "/$pdf_temp";
      if (file_exists($pdf_file)) return $pdf_file;
      return false;
    }
    public function prices_as_order_line_item_meta($item, $cart_item_key, $values, $order) {
      $product = $item->get_variation_id() > 0 ? $item->get_variation_id() : $item->get_product_id();
      $product = wc_get_product($product);
      $item->update_meta_data('_puiw_regular', $product->get_regular_price());
      $item->update_meta_data('_puiw_sale',    $product->is_on_sale() ? $product->get_sale_price() : $product->get_regular_price());
      $item->update_meta_data('_puiw_html',    $product->get_price_html());
      $item->update_meta_data('_puiw_version', $this->version);
    }
    public function hidden_order_itemmeta($args) {
      $args[] = '_puiw_regular';
      $args[] = '_puiw_sale';
      $args[] = '_puiw_html';
      $args[] = '_puiw_version';
      return $args;
    }
    public function woocommerce_order_discount_to_display($total_rows, $order, $tax_display) {
      $coupons_code        = "yes" === $this->tpl->get_show_coupons_code_at_totals();
      $coupons_description = "yes" === $this->tpl->get_show_coupons_description_at_totals();
      $coupons_amount      = "yes" === $this->tpl->get_show_coupons_amount_at_totals();
      $coupons_discount    = "yes" === $this->tpl->get_show_coupons_discount_at_totals();
      $coupuns_array       = array();
      $coupons             = $order->get_items('coupon');
      foreach ($coupons as $item) {
        $coupon = new \WC_Coupon($item->get_code());
        if ($coupon) {
          $coupon_label   = _x("Coupon:", "invoice-template", "pepro-ultimate-invoice");
          $coupon_display = [];
          if ($coupons_code) {
            $coupon_label = sprintf(_x("Coupon (%s):", "invoice-template", "pepro-ultimate-invoice"), $coupon->get_code());
          }
          if ($coupons_amount) {
            $coupon_display[] = ("percent" == $coupon->get_discount_type() ? $coupon->get_amount() . "%" : wc_price($coupon->get_amount(), ['currency' => $order->get_currency()]));
          }
          if ($coupons_discount) {
            $coupon_display[] = wc_price($item->get_discount(), array('currency' => $order->get_currency()));
          }
          if ($coupons_description) {
            $coupon_display[] = $coupon->get_description();
          }
          $coupuns_array["puiw_coupon_" . $item->get_id()] = array(
            'label' => $coupon_label,
            'value' => implode("<br>", $coupon_display),
          );
        }
      }
      $i = 0;
      $pos = 0;
      foreach ($total_rows as $key => $value) {
        $i++;
        if ("discount" === $key) {
          $pos = $i;
          break;
        }
      }
      $total_rows = array_merge(array_slice($total_rows, 0, $pos), $coupuns_array, array_slice($total_rows, $pos));
      return $total_rows;
    }
    /**
     * Woocommerce Admin Billing Fields
     *
     * @method  woocommerce_admin_billing_fields
     * @param   array $ar
     * @return  array
     * @version 1.0.0
     * @since   1.3.7
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function woocommerce_admin_billing_fields($ar) {
      if ("yes" == $this->tpl->get_show_user_uin()) {
        $default_UIN = "";
        if (get_current_user_id()) {
          $default_UIN = get_user_meta(get_current_user_id(), "billing_uin", true);
        }
        $ar['puiw_billing_uin'] = array(
          'label'   => __('User Unique Identification Number', "pepro-ultimate-invoice"),
          'default' => $default_UIN,
          'class'   => 'long',
        );
      }
      return $ar;
    }
    /**
     * Woocommerce Admin Shipping Fields
     *
     * @method  woocommerce_admin_shipping_fields
     * @param   array $ar
     * @return  array
     * @version 1.0.0
     * @since   1.3.7
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function woocommerce_admin_shipping_fields($ar) {
      $ar['puiw_invoice_shipdaterow'] = array(
        "label" => "",
        "name"  => "",
        "style" => "display:none",
        "class" => "long persianDatepickerRow",
      );
      $ar['puiw_invoice_shipdatefa']  = array(
        'label'             => __('Shipped Date (Shamsi)', "pepro-ultimate-invoice"),
        'class'             => 'long persianDatepicker disabled',
        'placeholder'       => __('Select Shipped Date', "pepro-ultimate-invoice"),
        'custom_attributes' => array("readonly" => "true"),
      );
      $ar['puiw_invoice_shipdate']    = array(
        'label'             => __('Shipped Date (Gregorian)', "pepro-ultimate-invoice"),
        'class'             => 'long persianDatepicker disabled',
        'placeholder'       => __('Select Shipped Date', "pepro-ultimate-invoice"),
        'custom_attributes' => array("readonly" => "true"),
      );
      $ar['puiw_invoice_track_id']    = array(
        'label'       => __('Shipping Track Serial', "pepro-ultimate-invoice"),
        'class'       => 'long',
        'placeholder' => __('Enter Shipping Track Serial', "pepro-ultimate-invoice"),
      );
      $ar['puiw_customer_signature']  = array(
        'label' => __('Customer Signature', "pepro-ultimate-invoice"),
        'class' => 'wc-select-uploader',
        'style' => 'display:none',
      );
      return $ar;
    }
    /**
     * print get invoices buttons on view order and order recieved pages
     *
     * @method woocommerce_order_details_before_order_table
     * @param WC_Order $order
     * @return string html buttons
     * @version 1.0.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function woocommerce_order_details_before_order_table($order) {
      $allowed_statuses = $this->tpl->get_allow_users_use_invoices_criteria("");
      if (empty($allowed_statuses) || !in_array("wc-{$order->get_status()}", (array) $allowed_statuses)) {
        return;
      }
      $display = $this->tpl->get_thankyou_display("buttons");
      if ("inline" !== $display) {
        echo "<div class='puiw_orders_invoice_btn_container'>";
        if ($this->print->has_access("HTML", $order)) {
          echo '<a style="margin-inline-end: 1rem;-webkit-margin-end: 1rem;" href="' . add_query_arg("key", $order->get_order_key(), home_url("?invoice=" . $order->get_order_number())) . '" class="button">' . _x('View Invoice', "order-page", "pepro-ultimate-invoice") . '</a>';
        }
        if ($this->print->has_access("PDF", $order)) {
          echo '<a href="' . add_query_arg("key", $order->get_order_key(), home_url("?invoice-pdf=" . $order->get_order_number())) . '" class="button">' . _x('PDF Invoice', "order-page", "pepro-ultimate-invoice") . '</a>';
        }
        echo "</div>";
      }
      if ("buttons" !== $display && function_exists("is_order_received_page") && is_order_received_page() && $this->print->has_access("HTML", $order)) {
        $this->render_inline_invoice($order);
      }
    }
    /**
     * embed the html invoice in the order received page
     *
     * @method render_inline_invoice
     * @param WC_Order $order
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function render_inline_invoice($order) {
      // create_html() runs its own output buffer and returns the markup
      $html = (string) $this->print->create_html($order->get_id(), "HTML", "", "", true);
      if ("" === trim($html)) {
        return;
      }
      // the invoice is a full document, keep its body only so it can live inside the page
      if (preg_match("#<body[^>]*>(.*)</body>#is", $html, $body)) {
        $styles = "";
        if (preg_match_all("#<style[^>]*>.*?</style>#is", $html, $found)) {
          $styles = implode("", $found[0]);
        }
        $html = $styles . $body[1];
      }
      printf('<div class="puiw-inline-invoice">%s</div>', $html);
    }
    /**
     * alter invoice template and colors if pre-invoice is status of order
     *
     * @method puiw_get_default_dynamic_params
     * @param array $opts pre data
     * @param WC_Order $order
     * @return array filtered data
     * @version 1.0.0
     * @since 1.1.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function puiw_get_default_dynamic_params($opts, $order) {
      if ("prebuy-invoice" == $order->get_status()) {
        $opts["template"] = $this->tpl->get_preinvoice_template();
        $opts["theme_color"] = $this->tpl->get_preinvoice_theme_color();
        $opts["theme_color2"] = $this->tpl->get_preinvoice_theme_color2();
        $opts["theme_color3"] = $this->tpl->get_preinvoice_theme_color3();

        $opts["preinvoice_template"] = $this->tpl->get_preinvoice_template();
        $opts["preinvoice_theme_color"] = $this->tpl->get_preinvoice_theme_color();
        $opts["preinvoice_theme_color2"] = $this->tpl->get_preinvoice_theme_color2();
        $opts["preinvoice_theme_color3"] = $this->tpl->get_preinvoice_theme_color3();
      }

      return $opts;
    }
    /**
     * Woocommerce Cart Totals After Order Total Description
     *
     * @method woocommerce_order_details_after_order_table_items
     * @return string details about shipping date and tracking
     * @version 1.1.0
     * @since 1.1.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function woocommerce_order_details_after_order_table_items($order) {
      $order_id = $order->get_id();
      $date = $this->get_order_ship_date($order);
      $serial = $this->get_order_track_id($order);
      if ("yes" == $this->tpl->show_shipped_date()) {
        echo '<tr><td><strong>' . _x("Shipped Date:", "wc-orders", "pepro-ultimate-invoice") . ' </strong></td><td>' . $this->tpl->get_date($date, "Y/m/d l", true) . '</td></tr>';
      }
      if ("yes" == $this->tpl->show_shipping_serial()) {
        echo '<tr><td><strong>' . _x("Shipping tracking Serial:", "wc-orders", "pepro-ultimate-invoice") . ' </strong></td><td>' . $serial . '</td></tr>';
      }
    }
    /**
     * attach pdf invoices to woocommerce emails
     *
     * @method attach_pdf_to_wC_emails
     * @param array $attachments
     * @param string $email_id
     * @param WC_Order $order
     * @return array $attachments
     * @version 1.2.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function attach_pdf_to_wC_emails($attachments, $email_id, $order) {
      // Avoiding errors and problems
      if (!is_a($order, 'WC_Order') || !isset($email_id)) {
        return $attachments;
      }
      $invcPDF = $this->print->create_pdf($order->get_id(), false, "S", false);
      if (!$invcPDF) {
        return $attachments;
      }
      $namedir = $this->print->get_pdf_temp_dir();
      $namedir = apply_filters("puiw_get_default_mail_pdf_temp_path", $namedir);
      // an unreadable attachment makes wp_mail() fail and can break the whole order flow
      if (!is_readable("$namedir/$invcPDF")) {
        return $attachments;
      }
      $attachments[] = "$namedir/$invcPDF";
      return $attachments;
    }
    /**
     * add metabox to cpts
     *
     * @method add_meta_boxes
     * @version 1.0.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function add_meta_boxes() {
      $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') && wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
        ? wc_get_page_screen_id('shop-order')
        : 'shop_order';
      add_meta_box($this->td, $this->title, array($this, 'wc_shop_order_metabox'), $screen, 'side', 'high');
    }
    /**
     * wc orders screen on save
     *
     * @param int $post_id
     * @version 1.0.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function wc_save_shop_order_metabox($post_id=0) {
      $order = wc_get_order( $post_id );
      if (!$order) {
        return;
      }
      // only touch a value when its field was actually submitted, otherwise a form
      // without our metabox (quick edit, 3rd party screens) would wipe stored data
      $fields = array(
        'puiw_shopmngr_provided_note'      => 'puiw_shopmngr_provided_note',
        '_billing_puiw_billing_uin'        => 'puiw_billing_uin',
        '_shipping_puiw_invoice_track_id'  => 'puiw_invoice_track_id',
      );
      $changed = false;
      foreach ($fields as $post_key => $meta_key) {
        if (!isset($_POST[$post_key])) {
          continue;
        }
        $value = wc_clean(wp_unslash($_POST[$post_key]));
        $order->update_meta_data($meta_key, $value);
        // woocommerce writes the shipping-prefixed keys itself, keep both in sync so
        // readers of either key (sms plugins, customer panel, invoice) see the value
        if ('_shipping_puiw_invoice_track_id' === $post_key) {
          $order->update_meta_data('_shipping_puiw_invoice_track_id', $value);
        }
        if ('_billing_puiw_billing_uin' === $post_key) {
          $order->update_meta_data('_billing_puiw_billing_uin', $value);
        }
        $changed = true;
      }
      if ($changed) {
        $order->save();
      }
    }
    /**
     * read css file header and info
     *
     * @method parseTemplate
     * @param string $contents css content
     * @return array header info
     * @version 2.0.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function parseTemplate($contents) {
      $styleExifDAta = array();
      foreach (explode("\n", $contents) as $line) {
        $data = explode(":", $line);
        if (count($data) == 2) {
          $styleExifDAta[strtolower(trim($data[0]))] = trim($data[1]);
        }
      }
      return apply_filters("puiw_parse_pdf_template", $styleExifDAta, $contents);
    }
    /**
     * return array list of available templates
     *
     * @method load_themes
     * @param boolean $advanced return advanced or simple
     * @return array template info
     * @version 1.1.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function load_themes($advanced = false) {
      $tempaltes = array();
      $tempaltesInfo = array();
      $styleFiles = (array) glob(PEPROULTIMATEINVOICE_DIR . "template/*/default.cfg");
      if (class_exists("\peproulitmateinvoice\PeproUltimateInvoice_Themes")) {
        $styleFiles = array_merge($styleFiles, (array) glob(\peproulitmateinvoice\PeproUltimateInvoice_Themes::root() . "/*/default.cfg"));
      }
      $styleFiles = apply_filters("puiw_get_templates_list", $styleFiles);
      foreach ($styleFiles as $style) {
        $contents                = file_get_contents($style);
        $styleExifDAta           = $this->parseTemplate($contents);
        $template                = sprintf(_x('%1$s — by %2$s', "theme-name", "pepro-ultimate-invoice"), $styleExifDAta["name"], $styleExifDAta["designer"]);
        $simple_path             = apply_filters("puiw_load_themes_simple_path", dirname($style), $style);
        $simple_title            = apply_filters("puiw_load_themes_simple_title", $template, $styleExifDAta["name"], $styleExifDAta["designer"], $styleExifDAta);
        $tempaltes[$simple_path] = $simple_title;
        $folder = basename(dirname($style));
        // a template under uploads is not inside the plugin, so plugin_dir_url() would
        // hand the browser a path that does not exist and every tile would be blank
        $base = (class_exists("\peproulitmateinvoice\PeproUltimateInvoice_Themes")
          && \peproulitmateinvoice\PeproUltimateInvoice_Themes::is_user_theme(dirname($style)))
          ? trailingslashit(\peproulitmateinvoice\PeproUltimateInvoice_Themes::root_url()) . $folder . "/"
          : trailingslashit(plugin_dir_url($style));
        // direction and tags make the picker searchable; a template that does not
        // declare them is read from its folder name, which is how the bundled RTL
        // ones have always been told apart
        $direction = !empty($styleExifDAta["direction"])
          ? strtolower(trim($styleExifDAta["direction"]))
          : (preg_match('/-rtl$/i', $folder) ? "rtl" : "ltr");
        $tags = array();
        if (!empty($styleExifDAta["tags"])) {
          $tags = array_values(array_filter(array_map("trim", explode(",", (string) $styleExifDAta["tags"]))));
        }
        $tempaltesInfoTemp       = array(
          "title"     => $template,
          "name"      => $styleExifDAta["name"],
          "author"    => $styleExifDAta["designer"],
          "version"   => $styleExifDAta["version"],
          "folder"    => $folder,
          "path"      => dirname($style),
          "url"       => $base,
          // the file name never changes, so the version is its age or browsers keep an old shot
          "icon"      => $base . "screenshot.png?v=" . (file_exists(dirname($style) . "/screenshot.png") ? filemtime(dirname($style) . "/screenshot.png") : "0"),
          "direction" => "rtl" === $direction ? "rtl" : "ltr",
          "tags"      => $tags,
          "pro"       => !empty($styleExifDAta["pro"]) && "no" !== strtolower((string) $styleExifDAta["pro"]),
        );
        $tempaltesInfo[dirname($style)] = apply_filters("puiw_load_themes_advanced_info", $tempaltesInfoTemp);
      }
      if ($advanced) {
        return apply_filters("puiw_load_themes_return_advanced", $tempaltesInfo);
      } else {
        return apply_filters("puiw_load_themes_return_simple", $tempaltes);
      }
    }
    /**
     * wc order metabox callback
     *
     * @method wc_shop_order_metabox
     * @param WP_Post $post
     * @return string html wrapper to metabox
     * @version 1.0.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function wc_shop_order_metabox($post) {

      if (class_exists("\Automattic\WooCommerce\Utilities\OrderUtil") && OrderUtil::is_order($post, wc_get_order_types()) && is_a($post, "WC_Order")) {
        $order_id = $post->get_id();
      } else {
        $order_id = isset($post->ID) ? $post->ID : $post;
      }
      $order = wc_get_order($order_id);
      if (!$order) {
        echo "<p>" . __("Invalid Order!", "pepro-ultimate-invoice") . "</p>";
        return;
      }
      wp_nonce_field('security_nonce', "{$this->td}_nonce");
      wp_enqueue_script("jquery-confirm", "{$this->assets_url}js/jquery-confirm.min.js", array("jquery"));
      wp_enqueue_style("jquery-confirm", "{$this->assets_url}css/jquery-confirm.min.css", array(), '1.0', 'all');
      wp_enqueue_style("fontawesome", "{$this->assets_url}fontawesome-free/css/all.css", array(), '5.15.3', 'all');

      $localize_script = (new PeproUltimateInvoice_Columns())->localize_script();
      wp_register_script("pepro-ultimate-invoice-orders-options", "{$this->assets_url}/admin/wc_orders" . $this->debugEnabled(".js", ".min.js"), array("jquery"), current_time('timestamp'));
      wp_localize_script("pepro-ultimate-invoice-orders-options", "_i18n", array_merge($localize_script, array(
        "calendarType"        => ($this->tpl->get_date_shamsi() == "yes") ? "persian" : "gregorian",
        "prev_img_url"        => $order->get_meta('_shipping_puiw_customer_signature', true),
        "shipping_procc"      => __("Select The Date Product Shipped", "pepro-ultimate-invoice"),
        "shipping_clear"      => __("Clear", "pepro-ultimate-invoice"),
        "load_themes"         => $this->load_themes(1),
        "get_template"        => $this->tpl->get_template(),
        "theme_color"         => get_option("puiw_theme_color"),
        "theme_color2"        => get_option("puiw_theme_color2"),
        "theme_color3"        => get_option("puiw_theme_color3"),
      )));

      wp_register_script("pepro-ultimate-invoice-nicescroll", PEPROULTIMATEINVOICE_ASSETS_URL . "/js/jquery.nicescroll.min.js", array("jquery"), '1.0.2');
      wp_register_script("pepro-ultimate-invoice-persian-date", PEPROULTIMATEINVOICE_ASSETS_URL . "/js/persian-date.min.js", array("jquery"), '1.0.2');
      wp_register_script("pepro-ultimate-invoice-persian-datepicker", PEPROULTIMATEINVOICE_ASSETS_URL . "/js/persian-datepicker.min.js", array("jquery"), '1.0.2');

      wp_register_style("pepro-ultimate-invoice-multiple-emails", PEPROULTIMATEINVOICE_ASSETS_URL . "/css/multiple-emails" . $this->debugEnabled(".css", ".min.css"));
      wp_register_script("pepro-ultimate-invoice-multiple-emails", PEPROULTIMATEINVOICE_ASSETS_URL . "/js/multiple-emails" . $this->debugEnabled(".js", ".min.js"), array("jquery"));

      wp_register_style("pepro-ultimate-invoice-orders-options", PEPROULTIMATEINVOICE_ASSETS_URL . "/admin/wc_orders" . $this->debugEnabled(".css", ".min.css"));
      wp_register_style("pepro-ultimate-invoice-persian-datepicker", PEPROULTIMATEINVOICE_ASSETS_URL . "/css/persian-datepicker.min.css");

      wp_enqueue_style("pepro-ultimate-invoice-multiple-emails");
      wp_enqueue_style("pepro-ultimate-invoice-orders-options");
      wp_enqueue_style("pepro-ultimate-invoice-persian-datepicker");

      wp_enqueue_script("jquery");
      wp_enqueue_style("wp-color-picker");
      wp_enqueue_script("wp-color-picker");
      wp_enqueue_script('jquery-ui-core');
      wp_enqueue_script('jquery-ui-selectmenu');
      wp_enqueue_media();
      add_thickbox();

      wp_enqueue_script("pepro-ultimate-invoice-nicescroll");
      wp_enqueue_script("pepro-ultimate-invoice-multiple-emails");
      wp_enqueue_script("pepro-ultimate-invoice-persian-date");
      wp_enqueue_script("pepro-ultimate-invoice-persian-datepicker");


      \peproulitmateinvoice\PeproUltimateInvoice_Setup::enqueue_alwan();
      wp_enqueue_script("pepro-ultimate-invoice-orders-options");

      $order    = wc_get_order($order_id);
      $total    = (float) $order->get_total();
      $email    = $order->get_billing_email();
      $id       = $order->get_id();
      echo      "<script>var CURRENT_ORDER_MAIL = []; CURRENT_ORDER_MAIL['$id'] = '$email';</script>";
      $url1     = home_url("?invoice={$id}");
      $url2     = home_url("?invoice-pdf={$id}");
      $url4     = home_url("?invoice-inventory={$id}");
      $url3     = home_url("?invoice-slips={$id}");
      $url11    = home_url("?invoice-slips-pdf={$id}");
      $url12    = home_url("?invoice-pos={$id}");
      $current  = $this->tpl->get_template();
      echo '<template id="puiw_DateSelectorContainer" style="display:none;"><div id="puiw_DateContainer" data-date="" style="width: 100%;"></div></template>';

      echo "";

      $icon = function ($name, $class = "") {
        return \peproulitmateinvoice\PeproUltimateInvoice_Icons::get($name, $class);
      };
      // what is already filled in, so the details list doubles as a checklist
      $uin       = $order->get_meta("_billing_puiw_billing_uin", true);
      $uin       = $uin ? $uin : $order->get_meta("puiw_billing_uin", true);
      $track     = $order->get_meta("_shipping_puiw_invoice_track_id", true);
      $track     = $track ? $track : $order->get_meta("puiw_invoice_track_id", true);
      $signed    = $order->get_meta("_shipping_puiw_customer_signature", true);
      $is_set    = _x("Set", "wc-orders-popup", "pepro-ultimate-invoice");

      // tiles for documents, rows for everything else; an add-on group without a
      // layout is drawn as rows
      $groups = array(
        "documents" => array(
          "title"   => _x("Documents", "wc-orders-popup", "pepro-ultimate-invoice"),
          "layout"  => "tiles",
          "actions" => array(
            array("icon" => "file-html",      "label" => _x("HTML Invoice", "wc-orders-popup", "pepro-ultimate-invoice"),      "title" => _x("View Order HTML Invoice", "wc-orders-popup", "pepro-ultimate-invoice"),       "href" => $url1,  "data-action" => "puiw_act_href", "target" => "_blank"),
            array("icon" => "file-pdf",       "label" => _x("PDF Invoice", "wc-orders-popup", "pepro-ultimate-invoice"),       "title" => _x("View Order PDF Invoice", "wc-orders-popup", "pepro-ultimate-invoice"),        "href" => $url2,  "data-action" => "puiw_act_href", "target" => "_blank"),
            array("icon" => "receipt",        "label" => _x("POS Invoice", "wc-orders-popup", "pepro-ultimate-invoice"),       "title" => _x("View Order POS PDF Invoice", "wc-orders-popup", "pepro-ultimate-invoice"),    "href" => $url12, "data-action" => "puiw_act_href", "target" => "_blank"),
            array("icon" => "clipboard-text", "label" => _x("Inventory report", "wc-orders-popup", "pepro-ultimate-invoice"),  "title" => _x("View Order Inventory report", "wc-orders-popup", "pepro-ultimate-invoice"),   "href" => $url4,  "data-action" => "puiw_act_href", "target" => "_blank"),
            array("icon" => "package",        "label" => _x("Packing Slip", "wc-orders-popup", "pepro-ultimate-invoice"),      "title" => _x("View Packing Slip for shipping", "wc-orders-popup", "pepro-ultimate-invoice"), "href" => $url3,  "data-action" => "puiw_act_href", "target" => "_blank"),
            array("icon" => "archive",        "label" => _x("PDF Packing Slip", "wc-orders-popup", "pepro-ultimate-invoice"),  "title" => _x("View PDF Packing Slip for shipping", "wc-orders-popup", "pepro-ultimate-invoice"), "href" => $url11, "data-action" => "puiw_act_href", "target" => "_blank"),
          ),
        ),
        "email" => array(
          "title"   => _x("Send by e-mail", "wc-orders-popup", "pepro-ultimate-invoice"),
          "actions" => array(
            array("icon" => "envelope-simple",      "label" => _x("Mail Invoice to Customer", "wc-orders-popup", "pepro-ultimate-invoice"),      "title" => _x("Mail Order Invoice to Customer", "wc-orders-popup", "pepro-ultimate-invoice"),      "href" => $url2, "data-action" => "puiw_act6"),
            array("icon" => "envelope-simple-open", "label" => _x("Mail Invoice to Shop Managers", "wc-orders-popup", "pepro-ultimate-invoice"), "title" => _x("Mail Order Invoice to Shop Managers", "wc-orders-popup", "pepro-ultimate-invoice"), "href" => $url2, "data-action" => "puiw_act9"),
            array("icon" => "paper-plane-tilt",     "label" => _x("Mail Invoice to Custom List", "wc-orders-popup", "pepro-ultimate-invoice"),   "title" => _x("Mail Order Invoice to Custom List", "wc-orders-popup", "pepro-ultimate-invoice"),   "href" => $url2, "data-action" => "puiw_act10"),
          ),
        ),
        "details" => array(
          "title"   => _x("Order details", "wc-orders-popup", "pepro-ultimate-invoice"),
          "actions" => array(
            array("icon" => "identification-card", "label" => _x("Edit User UIN", "wc-orders-popup", "pepro-ultimate-invoice"),               "title" => _x("Edit User Unique Identification Number", "wc-orders-popup", "pepro-ultimate-invoice"), "id" => "editpuiw_billing_uin", "value" => $uin),
            array("icon" => "receipt",             "label" => _x("Edit Transaction ID", "wc-orders-popup", "pepro-ultimate-invoice"),         "title" => _x("Edit transaction ID", "wc-orders-popup", "pepro-ultimate-invoice"),                   "id" => "editpuiw_billing_transaction_id", "value" => $order->get_transaction_id()),
            array("icon" => "truck",               "label" => _x("Edit Shipped Date", "wc-orders-popup", "pepro-ultimate-invoice"),           "title" => _x("Edit shipped date", "wc-orders-popup", "pepro-ultimate-invoice"),                     "id" => "editpuiw_invoice_shipdate", "value" => $order->get_meta("_shipping_puiw_invoice_shipdate", true)),
            array("icon" => "barcode",             "label" => _x("Edit Shipping Track Serial", "wc-orders-popup", "pepro-ultimate-invoice"),  "title" => _x("Edit shipping track serial", "wc-orders-popup", "pepro-ultimate-invoice"),            "id" => "editpuiw_invoice_track_id", "value" => $track),
            array("icon" => "signature",           "label" => _x("Edit Customer Signature", "wc-orders-popup", "pepro-ultimate-invoice"),     "title" => _x("Edit customer signature image", "wc-orders-popup", "pepro-ultimate-invoice"),          "id" => "editpuiw_invoice_customer_signature", "value" => $signed ? $is_set : ""),
            array("icon" => "note-pencil",         "label" => _x("Edit Customer Note", "wc-orders-popup", "pepro-ultimate-invoice"),          "title" => _x("Edit customer provided note", "wc-orders-popup", "pepro-ultimate-invoice"),            "id" => "editpuiw_invoice_customer_note", "value" => $order->get_customer_note() ? $is_set : ""),
            array("icon" => "note",                "label" => _x("Edit Shop manager Note", "wc-orders-popup", "pepro-ultimate-invoice"),      "title" => _x("Edit shop manager provided note", "wc-orders-popup", "pepro-ultimate-invoice"),        "id" => "editpuiw_invoice_shop_manager_note", "value" => $order->get_meta("puiw_shopmngr_provided_note", true) ? $is_set : ""),
          ),
        ),
      );
      $groups = apply_filters("puiw_order_metabox_actions", $groups, $order);
      $render_action = function ($action, $layout) use ($id, $icon) {
        $attributes = array(
          "class"    => "pwui_opts puiw-action" . (isset($action["id"]) ? " type2" : ""),
          "href"     => isset($action["href"]) ? $action["href"] : "#",
          "title"    => $action["title"],
          "rel"      => "puiw_tooltip",
          "data-ref" => $id,
        );
        foreach (array("id", "target", "data-action") as $key) {
          if (isset($action[$key])) {
            $attributes[$key] = $action[$key];
          }
        }
        $printable = "";
        foreach ($attributes as $key => $value) {
          $printable .= sprintf(' %s="%s"', esc_attr($key), "href" === $key ? esc_url($value) : esc_attr($value));
        }
        $tail = "";
        if ("rows" === $layout) {
          $value = isset($action["value"]) ? trim((string) $action["value"]) : "";
          $tail  = "" !== $value
            ? sprintf('<span class="puiw-action-value" dir="auto">%s</span>', esc_html(wp_html_excerpt($value, 18, "…")))
            : $icon("caret-right", "puiw-action-go");
        }
        printf('<a%1$s>%2$s<span class="puiw-action-label">%3$s</span>%4$s</a>', $printable, $icon($action["icon"]), esc_html($action["label"]), $tail);
      };
      ?>
      <div class="puiw-metabox">
        <?php foreach ($groups as $key => $group) {
          // add-ons used to append a bare list of actions under a title key
          if (!isset($group["actions"])) {
            $group = array("title" => is_string($key) ? $key : "", "actions" => (array) $group);
          }
          $layout = isset($group["layout"]) && "tiles" === $group["layout"] ? "tiles" : "rows";
          ?>
          <div class="puiw-metabox-group is-<?php echo esc_attr($layout); ?>">
            <?php if (!empty($group["title"])) { ?><h4><?php echo esc_html($group["title"]); ?></h4><?php } ?>
            <div class="puiw-actions">
              <?php foreach ($group["actions"] as $action) {
                if (is_array($action) && !empty($action["label"])) {
                  $render_action($action + array("title" => $action["label"], "icon" => ""), $layout);
                }
              } ?>
            </div>
            <?php if ("documents" === $key) { ?>
              <div class="puiw-metabox-advanced">
                <label class="puiw-metabox-advanced-row" for="puiwc_advanced_opts">
                  <?php echo $icon("palette"); ?>
                  <span><?php echo __("Use Advanced Options?", "pepro-ultimate-invoice"); ?></span>
                  <input type="checkbox" value="1" id="puiwc_advanced_opts" class="puiw-toggle" />
                </label>
                <div class="advabced_puiwc" style="display: none;">
          <div class="puiw_half">
            <label><?php echo __("Force Use theme", "pepro-ultimate-invoice"); ?></label>
            <select style="width: 100%;" id="puiw_metabox_theme_select" class='jqui-select' selecteditem='<?php echo esc_attr($current); ?>'></select>
          </div>
          <div class="puiw_half">
            <label><?php echo __("Primary, Secondary and Tertiary Colors", "pepro-ultimate-invoice"); ?></label>
            <select style="width: 100%;" id="puiw_metabox_swatch_select" class="swatch-select" swatches="<?php echo esc_attr(get_option("puiw_color_swatches", "")); ?>"></select>
          </div>
          <div class="puiw-color-pickers">
            <?php
            // one row per colour: its name, the swatch that opens the picker, the hex field
            foreach (array(
              ""  => _x("Primary", "wc-setting", "pepro-ultimate-invoice"),
              "2" => _x("Secondary", "wc-setting", "pepro-ultimate-invoice"),
              "3" => _x("Tertiary", "wc-setting", "pepro-ultimate-invoice"),
            ) as $n => $name) { ?>
              <div class="puiw-color-row">
                <label for="puiw_metabox_theme_color<?php echo $n; ?>"><?php echo esc_html($name); ?></label>
                <input type="text" dir="ltr" spellcheck="false" maxlength="7" id="puiw_metabox_theme_color<?php echo $n; ?>" data-puiw-color-label="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr(get_option("puiw_theme_color{$n}")); ?>" class="wc-color-picker" />
              </div>
            <?php } ?>
          </div>
          <p class="puiw-metabox-reset">
            <a rel='puiw_tooltip' title='<?php echo esc_attr(_x("Reset Advanced Options to default", "wc-orders-popup", "pepro-ultimate-invoice")); ?>' class='pwui_reset_advanced' href='#'>
              <?php echo $icon("arrow-counter-clockwise"); ?>
              <span><?php echo _x("Reset", "wc-orders-popup", "pepro-ultimate-invoice"); ?></span>
            </a>
          </p>
                </div>
              </div>
            <?php } ?>
          </div>
        <?php } ?>
      </div>
    <?php
      echo $this->mta->popup_html_data($id, false);
    }
    /**
    * add shopmngr provided note description after shipping details in wc_order screen
    *
    * @method after_shipping_shopmngr_provided_note
    * @return string
    * @version 1.0.0
    * @since 1.0.0
    * @license https://peprodev.com/license Pepro.dev License
    */
    public function after_shipping_shopmngr_provided_note(\WC_Order $order) {
      global $post;
      $value_raw = $order->get_meta("puiw_shopmngr_provided_note", true);
      $value = wp_kses_post(nl2br(wptexturize($value_raw)));
      echo "<p class=\"form-field form-field-wide puiw_shopmngr_provided_note preview\">
          <strong style=\"display: block;\">" . __('Shop Manager provided note:', "pepro-ultimate-invoice") . "</strong>
          ".$value."
        </p>
        <p class=\"form-field form-field-wide puiw_shopmngr_provided_note edit\">
          <label style=\"display:none;\" for=\"puiw_shopmngr_provided_note\">" . __('Shop Manager provided note:', "pepro-ultimate-invoice") . "</label>
          <textarea style=\"display:none;\" rows=\"3\" cols=\"40\" name=\"puiw_shopmngr_provided_note\" id=\"puiw_shopmngr_provided_note\" placeholder=\"" . __('Enter Shop Manager provided note here', "pepro-ultimate-invoice") . "\">".$value_raw."</textarea>
        </p>";
    }

    /**
     * save wc checkout metas
     *
     * @method woocommerce_checkout_update_order_meta
     * @param int $order_id
     * @version 1.0.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function woocommerce_checkout_update_order_meta($order_id) {
      if (!empty($_POST['puiw_billing_uin'])) {
        $order = wc_get_order($order_id);
        if ($order) {
          $uin = sanitize_text_field(wp_unslash($_POST['puiw_billing_uin']));
          $order->update_meta_data('puiw_billing_uin', $uin);
          $order->update_meta_data('_billing_puiw_billing_uin', $uin);
          $order->save();
        }
      }
    }
    /**
     * save wc checkout user meta
     *
     * @method woocommerce_checkout_update_user_meta
     * @param int $order_id
     * @version 1.0.0
     * @since 1.1.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function woocommerce_checkout_update_user_meta($customer_id, $posted) {
      if (isset($posted['puiw_billing_uin'])) {
        update_user_meta($customer_id, 'billing_uin', sanitize_text_field($posted['puiw_billing_uin']));
      }
    }
    /**
     * add extra field to checkout to receive user uin
     *
     * @method checkout_fields_add_uin
     * @param array $fields
     * @version 1.0.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function checkout_fields_add_uin($fields) {
      if ("yes" == $this->tpl->get_show_user_uin()) {
        $default_UIN = "";
        if (get_current_user_id()) {
          $default_UIN = get_user_meta(get_current_user_id(), "billing_uin", true);
        }
        $label = $this->tpl->get_user_uin_label(_x("Unique Identification Number", "wc-order-screen", "pepro-ultimate-invoice"));

        $fields['billing']['puiw_billing_uin'] = array(
          'label'       => $label,
          'placeholder' => $label,
          'required'    => "yes" == $this->tpl->get_user_uin_required(),
          'default'     => $default_UIN,
          'class'       => array('form-row-wide'),
          'clear'       => true
        );
      }
      return $fields;
    }
    /**
     * expose the same field on my-account billing address so it can be completed later
     *
     * @method billing_fields_add_uin
     * @param array $fields
     * @return array
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function billing_fields_add_uin($fields) {
      if ("yes" != $this->tpl->get_show_user_uin() || isset($fields['puiw_billing_uin']) || is_checkout()) {
        return $fields;
      }
      $label = $this->tpl->get_user_uin_label(_x("Unique Identification Number", "wc-order-screen", "pepro-ultimate-invoice"));
      $fields['puiw_billing_uin'] = array(
        'label'       => $label,
        'placeholder' => $label,
        'required'    => "yes" == $this->tpl->get_user_uin_required(),
        'class'       => array('form-row-wide'),
        'priority'    => 125,
        'value'       => get_user_meta(get_current_user_id(), "billing_uin", true),
      );
      return $fields;
    }
    /**
     * keep the my-account address copy in sync with the canonical billing_uin user meta
     *
     * @method uin_sync_address_meta
     * @param int $user_id
     * @param string $load_address
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function uin_sync_address_meta($user_id, $load_address = "billing") {
      if ("billing" !== $load_address || !isset($_POST['puiw_billing_uin'])) {
        return;
      }
      update_user_meta($user_id, "billing_uin", sanitize_text_field(wp_unslash($_POST['puiw_billing_uin'])));
    }
    /**
     * enqueue scripts and styles on admin side
     *
     * @method admin_enqueue_scripts
     * @version 1.0.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function admin_enqueue_scripts() {
      $screen = function_exists("get_current_screen") ? get_current_screen() : null;
      $orders = array("shop_order", function_exists("wc_get_page_screen_id") ? wc_get_page_screen_id("shop-order") : "woocommerce_page_wc-orders");
      if ($screen && in_array($screen->id, $orders, true)) {
        // in the head, not from the metabox callback, so no optimiser or late print can drop it
        wp_enqueue_style("pepro-ultimate-invoice-orders-options", PEPROULTIMATEINVOICE_ASSETS_URL . "/admin/wc_orders" . $this->debugEnabled(".css", ".min.css"), array(), $this->version);
      }
      wp_register_style("{$this->td}-dmy", false);
      wp_enqueue_style("{$this->td}-dmy");
      $plugin_settinng_url = admin_url('admin.php?page=wc-settings&tab=pepro_ultimate_invoice');
      wp_add_inline_style("{$this->td}-dmy", "#wpadminbar #wp-admin-bar-puiw_toolbar_setting_btn a .ab-icon::before { font-family: WooCommerce !important; content: '\\e03d'; font-size: smaller;}#wpadminbar #wp-admin-bar-puiw_toolbar_dash_btn a .ab-icon::before {content: \"\\f340\";top: 3px; }#wpadminbar .puiw-ab-pro{display:inline-block;background:linear-gradient(135deg,#f6c343,#e0a526);color:#3d2b03;padding:0 7px;border-radius:10px;font-size:11px;line-height:18px;font-weight:600;vertical-align:middle;}#wpadminbar a:hover .puiw-ab-pro{color:#3d2b03;}");
    }
    /**
     * add a link to the WP Toolbar and remove all others
     *
     * @method  custom_toolbar_link
     * @param   object $wp_admin_bar
     * @return  object wp_toolbar
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function wp_before_admin_bar_render_back() {
      global $wp_admin_bar;
      foreach ($wp_admin_bar->get_nodes() as $key => $value) {
        $wp_admin_bar->remove_menu($key);
      }
      $wp_admin_bar->add_menu(
        array(
          'id'    => 'puiw_toolbar_dash_btn',
          'title' => '<span class="ab-icon"></span>' . __("Back to Dashboard", "pepro-ultimate-invoice"),
          'href'  => admin_url()
        )
      );
      $wp_admin_bar->add_menu(
        array(
          'id'    => "puiw_toolbar_sep2",
          'title' => "",
        )
      );
      $wp_admin_bar->add_menu(
        array(
          'id'    => 'puiw_toolbar_wc_orders',
          'title' => __("Orders", "pepro-ultimate-invoice"),
          'href'  => admin_url("edit.php?post_type=shop_order")
        )
      );
      $wp_admin_bar->add_menu(
        array(
          'id'    => 'puiw_toolbar_wc_setting',
          'title' => __("Settings", "pepro-ultimate-invoice"),
          'href'  => admin_url("admin.php?page=wc-settings")
        )
      );
      $wp_admin_bar->add_menu(
        array(
          'id'    => 'puiw_toolbar_wc_checkout',
          'title' => __("Payment", "pepro-ultimate-invoice"),
          'href'  => admin_url("admin.php?page=wc-settings&tab=checkout")
        )
      );
      $wp_admin_bar->add_menu(
        array(
          'id'    => 'puiw_toolbar_wc_shipping',
          'title' => __("Shipping", "pepro-ultimate-invoice"),
          'href'  => admin_url("admin.php?page=wc-settings&tab=shipping")
        )
      );
      $wp_admin_bar->add_menu(
        array(
          'id'    => "puiw_toolbar_sep",
          'title' => "",
        )
      );
      $wp_admin_bar->add_menu(
        array(
          'id'    => 'puiw_toolbar_wc_rate',
          'title' => __("&#x2B50; Rate us", "pepro-ultimate-invoice"),
          'href'  => "https://wordpress.org/support/plugin/pepro-ultimate-invoice/reviews/#new-post",
        )
      );
      $wp_admin_bar->add_menu(
        array(
          'id'    => 'puiw_toolbar_wc_bug',
          'title' => __("&#x1FAB2; Report problem", "pepro-ultimate-invoice"),
          'href'  => 'https://wordpress.org/support/plugin/pepro-ultimate-invoice/#new-post'
        )
      );
      $wp_admin_bar->add_menu(
        array(
          'id'    => 'puiw_toolbar_wc_about',
          'title' => __("&#x2139;&#xFE0F; About", "pepro-ultimate-invoice") . $this->admin_bar_version_badge(),
          'href'  => 'https://peprodev.com/pepro-woocommerce-ultimate-invoice/'
        )
      );
      if (!apply_filters("puiw_is_pro", false)) {
        $wp_admin_bar->add_menu(
          array(
            'id'    => 'puiw_toolbar_get_pro',
            'title' => '<span class="puiw-ab-pro">' . esc_html__("Get PRO", "pepro-ultimate-invoice") . '</span>',
            'href'  => 'https://peprodev.com/pepro-woocommerce-ultimate-invoice/',
            'meta'  => array("target" => "_blank", "rel" => "noopener"),
          )
        );
      }
    }
    /**
     * add a link to the WP Toolbar
     *
     * @method  custom_toolbar_link
     * @param   object $wp_admin_bar
     * @return  object wp_toolbar
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    /**
     * version pill used next to the plugin name in the toolbar
     *
     * @method  admin_bar_version_badge
     * @return  string
     * @version 1.0.0
     * @since   2.4.1
     * @license https://peprodev.com/license PeproDev License
     */
    public function admin_bar_version_badge() {
      // the version lives on the settings screen; the toolbar only says PRO
      return apply_filters("puiw_is_pro", false)
        ? ' <span class="puiw-ab-pro">' . esc_html_x("PRO", "wc-setting", "pepro-ultimate-invoice") . '</span>'
        : "";
    }
    public function wp_before_admin_bar_render() {
      global $wp_admin_bar;
      if (is_admin()) {
        $badge = $this->admin_bar_version_badge();
        $wp_admin_bar->add_menu(
          array(
            'id'    => 'puiw_toolbar_setting_btn',
            'title' => '<span class="ab-icon"></span>' . esc_html($this->title) . $badge,
            'href'  => admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice"),
            'meta'  => array("title" => sprintf(_x('%1$s v.%2$s', "adminbar-name", "pepro-ultimate-invoice"), $this->title, $this->version)),
          )
        );
      }
    }
    /**
     * add wc-prebuy-invoice status to wc order statuses
     *
     * @method  add_wc_prebuy_status
     * @version 1.1.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function add_wc_prebuy_status() {
      register_post_status('wc-prebuy-invoice', array(
        'label'                     => __('Pre-buy Invoice', "pepro-ultimate-invoice"),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Pre-buy Invoice (%s)', 'Pre-buy Invoices (%s)', "pepro-ultimate-invoice")
      ));
    }
    /**
     * check if user has access to use print invoice fn
     *
     * @method has_access_print
     * @param string $mode
     * @param WC_Order $order
     * @return boolean
     * @version 1.0.0
     * @since 1.1.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function has_access_print($mode = "HTML", $order = false) {
      return $this->print->has_access($mode, $order);
    }
    /**
     * add get invoice button to customer's order list column
     *
     * @method  add_view_invoice_button_orderpage
     * @param   array    $actions
     * @param   WC_Order $order
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function add_view_invoice_button_orderpage($actions, $order) {
      $allowed_statuses = $this->tpl->get_allow_users_use_invoices_criteria("");

      if (!empty($allowed_statuses) && in_array("wc-{$order->get_status()}", (array) $allowed_statuses)) {
        if ($this->print->has_access("HTML", $order)) {
          $actions["{$this->td}_html"] = array('url'  => add_query_arg("key", $order->get_order_key(), home_url("?invoice=" . $order->get_order_number())), 'name' => _x('View Invoice', "order-page", "pepro-ultimate-invoice"),);
        }

        if ($this->print->has_access("PDF", $order)) {
          $actions["{$this->td}_pdf"] = array('url'  => add_query_arg("key", $order->get_order_key(), home_url("?invoice-pdf=" . $order->get_order_number())), 'name' => _x('PDF Invoice', "order-page", "pepro-ultimate-invoice"),);
        }
      }

      return apply_filters("pepro_ultimate_invoice_orders_action", $actions, $order, $allowed_statuses, $this->print->has_access("HTML", $order), $this->print->has_access("PDF", $order));
    }
    /**
     * add pre-order invoice status to woocommerce
     *
     * @method  add_wc_order_statuses
     * @param   array $order_statuses
     * @version 1.1.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function add_wc_order_statuses($order_statuses) {
      $new_order_statuses = array();
      foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        if ('wc-processing' === $key) {
          $new_order_statuses['wc-prebuy-invoice'] = __('Pre-buy Invoice', "pepro-ultimate-invoice");
        }
      }
      return $new_order_statuses;
    }
    /**
     * add Get Pre-Order Invoice button to cart page, before proccess to checkout button
     *
     * @method  woocommerce_after_cart_contents
     * @return  string
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function woocommerce_after_cart_contents() {
      wp_enqueue_style("$this->td-ml", "$this->assets_url/css/mobileLayer" . $this->debugEnabled(".css", ".min.css"), array(), "1.0.0", "all");
      wp_enqueue_script("$this->td-ml", "$this->assets_url/js/mobileLayer" . $this->debugEnabled(".js", ".min.js"), array("jquery", "jquery-ui-core"), "1.0.0", true);
      if (!is_user_logged_in()) {
        wp_enqueue_script("$this->td-cart", "$this->assets_url/js/wc.cart.public" . $this->debugEnabled(".js", ".min.js"), array("jquery"), "1.0.0", true);
        wp_localize_script(
          "$this->td-cart",
          "_i18n",
          array(
            "title"     => _x("Unauthorized Access!", "js-cart-page", "pepro-ultimate-invoice"),
            "okaylabel" => _x("Okay", "js-cart-page", "pepro-ultimate-invoice"),
            "msg"       => sprintf(_x("We are sorry for inconvenience, this feature is only available to logged-in users.<br>Please %sLogin / Register%s.", "js-cart-page", "pepro-ultimate-invoice"), "<a href='" . get_permalink(get_option('woocommerce_myaccount_page_id')) . "' target='_blank'>", "</a>"),
          )
        );
      } else {
        wp_enqueue_script("$this->td-cart", "$this->assets_url/js/wc.cart.private" . $this->debugEnabled(".js", ".min.js"), array("jquery"), "1.0.0", true);
        wp_localize_script("$this->td-cart", "_i18n", array(
          "td"        => "puiw_{$this->td}",
          "okaylabel" => _x("Okay", "js-cart-page", "pepro-ultimate-invoice"),
          "ajax"      => admin_url("admin-ajax.php"),
          "nonce"     => wp_create_nonce("pepro-ultimate-invoice"),
        ));
      }
      echo "<a href=\"\" id=\"pepro-one-page-purchase--submit-invoice\" class=\"checkout-button button alt\"><span class=\"fa fa-spin fa-cog\" style='display: none;'></span>  " . __("Get Pre-buy Invoice", "pepro-ultimate-invoice") . "</a>";
    }
    /**
     * Initiate plugins settings in wp options
     *
     * @method  get_setting_options
     * @return  array settings and sections
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function get_setting_options() {
      return array(
        array(
          "name" => "{$this->td}_general",
          "data" => array(
            "{$this->td}_invoice_img" => plugins_url("/assets/img/pepro.png", __FILE__),
          )
        ),
      );
    }
    /**
     * run on plugin deactivation
     *
     * @method  deactivation_hook
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public static function deactivation_hook() {
      update_option("peprodev_ultimate_invoice_alert_viewed_yet", "");
    }
    /**
     * run on plugin activation
     *
     * @method  activation_hook
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public static function activation_hook() {
      flush_rewrite_rules(true);
    }
    /**
     * run one-off routines after the plugin files were updated
     *
     * @method maybe_upgrade
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function maybe_upgrade() {
      $stored = get_option("puiw_plugin_version", "");
      if ($stored === $this->version) {
        return;
      }
      $from = "" !== (string) $stored ? (string) $stored : (string) get_option("puiw_upgraded_from", "");
      \peproulitmateinvoice\PeproUltimateInvoice_Setup::upgrade($from, $this->version);
      delete_option("puiw_upgraded_from");
      $this->maybe_switch_to_rtl_template();
      update_option("puiw_plugin_version", $this->version, false);
    }
    /**
     * on rtl installs the ltr template leaves every invoice heading in english,
     * so move the site to the bundled rtl template unless a custom one is in use
     *
     * @method maybe_switch_to_rtl_template
     * @return bool true when the template was switched
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function maybe_switch_to_rtl_template() {
      if (!is_rtl() && !in_array(substr(get_locale(), 0, 2), array("fa", "ar", "he", "ur", "ps"), true)) {
        return false;
      }
      $ltr_default = PEPROULTIMATEINVOICE_DIR . "template/default";
      $rtl_default = PEPROULTIMATEINVOICE_DIR . "template/default-rtl";
      $current     = untrailingslashit(get_option("puiw_template", ""));
      if ("" !== $current && untrailingslashit($ltr_default) !== $current) {
        return false;
      }
      if (!file_exists("$rtl_default/default.cfg")) {
        return false;
      }
      update_option("puiw_template", $rtl_default);
      update_option("puiw_rtl_template_autoswitched", "yes", false);
      return true;
    }
    /**
     * warn when an ltr template is selected on an rtl store
     *
     * @method rtl_template_notice
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function rtl_template_notice() {
      if (!current_user_can("manage_woocommerce")) {
        return;
      }
      if ("yes" === get_option("puiw_rtl_template_autoswitched", "")) {
        delete_option("puiw_rtl_template_autoswitched");
        printf(
          '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
          esc_html(sprintf(__("%s: the right-to-left invoice template has been selected automatically, so invoice headings are no longer printed in English.", "pepro-ultimate-invoice"), $this->title))
        );
        return;
      }
      if (!is_rtl()) {
        return;
      }
      $current = untrailingslashit(get_option("puiw_template", ""));
      if ($current !== untrailingslashit(PEPROULTIMATEINVOICE_DIR . "template/default")) {
        return;
      }
      printf(
        '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
        esc_html(sprintf(__("%s: your store is right-to-left but a left-to-right invoice template is selected, which prints the invoice headings in English.", "pepro-ultimate-invoice"), $this->title)),
        esc_url(admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=theme")),
        esc_html__("Select the RTL template", "pepro-ultimate-invoice")
      );
    }
    /**
     * run on plugin uninstalation
     *
     * @method  uninstall_hook
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public static function uninstall_hook() {
      $ppa = new PeproUltimateInvoice();
      $peproultimateinvoice_options = $ppa->get_setting_options();
      foreach ($peproultimateinvoice_options as $options) {
        $opparent = $options["name"];
        foreach ($options["data"] as $optname => $optvalue) {
          // unregister_setting($opparent, $optname);
          // delete_option($optname);
        }
      }
    }
    /**
     * import default setting for plugin
     *
     * @method  change_default_settings
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function change_default_settings($clear_mode = "", $data = array()) {
      $rtl                                              = is_rtl() ? "-rtl" : "";
      $pdf_font                                         = is_rtl() ? "iranyekanfa" : "dejavu";
      $force_persian_numbers                            = is_rtl() ? "farsi" : "no";
      $email_subject                                    = _x("Invoice #%s — ", "wc-setting", "pepro-ultimate-invoice") . get_bloginfo('name', 'display');
      $email_from_name                                  = get_bloginfo('name', 'display');
      $puiw_store_postcode                              = "";
      if (function_exists("WC")) {
        $puiw_store_postcode = WC()->countries->get_base_postcode();
      }
      $email_from_address                               = "wordpress@" . parse_url(get_bloginfo('url'), PHP_URL_HOST);
      $wc_opt                                           = array(
        "puiw_show_store_national_id"                 => "no",
        "puiw_show_store_registration_number"         => "no",
        "puiw_show_store_economical_number"           => "no",
        "puiw_show_customer_address"                  => "yes",
        "puiw_show_customer_phone"                    => "yes",
        "puiw_show_customer_email"                    => "yes",
        "puiw_show_order_date"                        => "yes",
        "puiw_show_payment_method"                    => "yes",
        "puiw_show_shipping_method"                   => "",
        "puiw_transaction_ref_id"                     => "yes",
        "puiw_paid_date"                              => "yes",
        "puiw_purchase_complete_date"                 => "yes",
        "puiw_shipping_date"                          => "",
        "puiw_order_status"                           => "yes",
        "puiw_show_product_image"                     => "yes",
        "puiw_show_product_purchase_note"             => "yes",
        "puiw_show_order_items"                       => "yes",
        "puiw_show_order_total"                       => "yes",
        "puiw_show_product_weight"                    => "",
        "puiw_show_product_dimensions"                => "",
        "puiw_show_discount_precent"                  => "yes",
        "puiw_show_product_tax"                       => "",
        "puiw_show_product_sku"                       => "",
        "puiw_show_product_sku2"                      => "",
        "puiw_show_user_uin"                          => "",
        "puiw_user_uin_required"                      => "yes",
        "puiw_user_uin_label"                         => "",
        "puiw_postcode_display"                       => "both",
        "puiw_show_customer_order_count"              => "no",
        "puiw_show_item_discount"                     => "no",
        "puiw_item_extra_attributes"                  => "",
        "puiw_allowed_roles"                          => array("administrator", "shop_manager"),
        "puiw_thankyou_display"                       => "buttons",
        "puiw_slip_layout"                            => "sender-right",
        "puiw_slip_show_weight"                       => "yes",
        "puiw_slip_show_barcode"                      => "yes",
        "puiw_show_shipping_address"                  => "billing",
        "puiw_address_display_method"                 => "[country], [province], [city], [address1], [address2] ([po_box])",
        "puiw_show_order_note"                        => "note_provided_by_both",
        "puiw_show_price_template"                    => "show_wc_price",
        "puiw_items_sorting"                          => "NONE",
        "puiw_shelf_number_id"                        => "yes",
        "puiw_show_product_sku_inventory"             => "yes",
        "puiw_show_product_sku2_inventory"            => "yes",
        "puiw_show_product_image_inventory"           => "yes",
        "puiw_show_product_weight_in_inventory"       => "yes",
        "puiw_show_product_total_weight_in_inventory" => "yes",
        "puiw_show_product_dimensions_in_inventory"   => "yes",
        "puiw_show_product_quantity_in_inventory"     => "yes",
        "puiw_show_product_note_in_inventory"         => "yes",
        "puiw_show_order_note_inventory"              => "note_provided_by_both",
        "puiw_price_inventory_report"                 => "show_wc_price",
        "puiw_inventory_css_style"                    => "",
        "puiw_theme_title"                            => "",
        "puiw_template"                               => PEPROULTIMATEINVOICE_DIR . "template/default{$rtl}",
        "puiw_preinvoice_template"                    => PEPROULTIMATEINVOICE_DIR . "template/default-pre-invoice",
        "puiw_theme_color"                            => "#90caf9",
        "puiw_theme_color2"                           => "#a6d5fc",
        "puiw_theme_color3"                           => "#b5deff",
        "puiw_preinvoice_theme_color"                 => "#faee84",
        "puiw_preinvoice_theme_color2"                => "#fff59d",
        "puiw_preinvoice_theme_color3"                => "#fff8b5",
        "puiw_invoice_title"                          => _x("Invoice %s", "wc-setting", "pepro-ultimate-invoice"),
        "puiw_font_size"                              => "11",
        "puiw_invoice_prefix"                         => "INV-",
        "puiw_invoice_suffix"                         => "",
        "puiw_invoice_start"                          => "0",
        "puiw_show_signatures"                        => "",
        "puiw_signature"                              => "",
        "puiw_watermark"                              => "",
        "puiw_watermark_opacity"                      => "80",
        "puiw_invoices_footer"                        => "",
        "puiw_custom_css_style"                       => "",
        "puiw_pdf_css_style"                          => "",
        "puiw_misc_title"                             => "",
        "puiw_disable_wc_dashboard"                   => "no",
        "puiw_date_format"                            => "Y/m/d H:i",
        "puiw_date_shamsi"                            => "no",
        "puiw_show_shipped_date"                      => "",
        "puiw_show_shipping_serial"                   => "",
        "puiw_force_persian_numbers"                  => $force_persian_numbers,
        "puiw_invoice_language"                       => "auto",
        "puiw_template_direction"                     => "auto",
        "puiw_woosb_show_bundles"                     => "",
        "puiw_woosb_show_bundles_subtitle"            => "",
        "puiw_woosb_bundles_subtitle_prefix"          => "",
        "puiw_woosb_show_bundled_products"            => "",
        "puiw_woosb_show_bundled_subtitle"            => "",
        "puiw_woosb_bundled_subtitle_prefix"          => "",
        "puiw_woosb_show_bundled_hierarchy"           => "",
        "puiw_barcode_title"                          => "",
        "puiw_show_barcode_id"                        => "yes",
        "puiw_show_shipping_ref_id"                   => "yes",
        "puiw_show_qr_code_id"                        => "no",
        "puiw_postal_stickey_label_for_store"         => "yes",
        "puiw_postal_stickey_label_for_customer"      => "yes",
        "puiw_automation_title"                       => "",
        "puiw_email_subject"                          => $email_subject,
        "puiw_email_from_name"                        => $email_from_name,
        "puiw_email_from_address"                     => $email_from_address,
        "puiw_send_invoices_via_email"                => "manual",
        "puiw_send_invoices_via_email_opt"            => "wc-completed",
        "puiw_send_invoices_via_email_admin"          => "manual",
        "puiw_send_invoices_via_email_opt_admin"      => "wc-completed",
        "puiw_send_invoices_via_email_shpmngrs"       => "",
        "puiw_attach_pdf_invoices_to_mail"            => "yes",
        "puiw_automation_title"                       => "",
        "puiw_allow_guest_users_view_invoices"        => "no",
        "puiw_allow_pdf_guest"                        => "",
        "puiw_allow_users_have_invoices"              => "yes",
        "puiw_allow_pdf_customer"                     => "both",
        "puiw_allow_users_use_invoices"               => "yes",
        "puiw_allow_users_use_invoices_criteria"      => "wc-completed",
        "puiw_automation_title"                       => "",
        "puiw_quick_shop"                             => "",
        "puiw_allow_preorder_invoice"                 => "",
        "puiw_allow_preorder_emptycart"               => "no",
        "puiw_preorder_shopmngr_extra_note"           => "",
        "puiw_preorder_customer_extra_note"           => "",
        "puiw_automation_title"                       => "",
        "puiw_pdf_size"                               => "A4",
        "puiw_pdf_orientation"                        => "P",
        "puiw_pdf_font"                               => $pdf_font,
        "puiw_default_title"                          => "",
        "puiw_store_name"                             => get_bloginfo('name'),
        "puiw_store_logo"                             => "",
        "puiw_store_website"                          => get_bloginfo('url'),
        "puiw_store_email"                            => get_option("admin_email"),
        "puiw_store_phone"                            => "",
        "puiw_store_national_id"                      => "",
        "puiw_store_registration_number"              => "",
        "puiw_store_economical_number"                => "",
        "puiw_store_address"                          => $this->tpl->get_wc_store_address(),
        "puiw_store_postcode"                         => $puiw_store_postcode,
      );
      $wc_opt = apply_filters("pepro_ultimate_invoice_default_options", $wc_opt, $clear_mode);
      $nn = 0;
      switch (strtoupper($clear_mode)) {
        case 'GET':
          $str = "\$my_opts = array(" . PHP_EOL;
          foreach ($wc_opt as $key => $value) {
            // var_export keeps arrays and quotes intact; the old string build printed
            // "Array" for every multi choice and broke on a quote in any text
            $str .= "  " . var_export((string) $key, true) . " => " . var_export(get_option($key, ""), true) . "," . PHP_EOL;
          }
          $str .= ");" . PHP_EOL;
          $str .= "global \$PeproUltimateInvoice;" . PHP_EOL;
          $str .= "\$updated_count = \$PeproUltimateInvoice->change_default_settings(\"MODIFY\", \$my_opts);";
          return $str;
          break;
        case 'JSON':
          $json = array();
          foreach ($wc_opt as $key => $value) {
            $json[$key] = get_option($key, "");
          }
          return $json;
          break;
        case 'CLEAR':
          foreach ($wc_opt as $key => $value) {
            update_option($key, "");
            $nn++;
          }
          return $nn;
          break;
        case 'SET':
          foreach ($wc_opt as $key => $value) {
            if (!get_option($key) || empty(get_option($key))) {
              update_option($key, $value);
              $nn++;
            }
          }
          return $nn;
          break;
        case 'RESET':
          foreach ($wc_opt as $key => $value) {
            update_option($key, $value);
            $nn++;
          }
          return $nn;
          break;
        case 'MODIFY':
          foreach ($data as $key => $value) {
            if (array_key_exists($key, $wc_opt)) {
              update_option($key, $value);
              $nn++;
            }
          }
          return $nn;
          break;
      }
    }
    /**
     * adminmenu callback
     *
     * @method  help_container
     * @param   array $hook
     * @return  string page content
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function help_container($hook) {
      ob_start();
      wp_enqueue_style("{$this->db_slug}_bkend", "{$this->assets_url}css/backend" . $this->debugEnabled(".css", ".min.css"));
      wp_enqueue_script("{$this->db_slug}_bkend", "{$this->assets_url}/js/settings" . $this->debugEnabled(".js", ".min.js"), array('jquery', 'wp-color-picker'), null, true);
      wp_add_inline_style("{$this->db_slug}_bkend", ".form-table th {} ");
      is_rtl() and wp_add_inline_style("{$this->db_slug}_bkend", ".form-table th {}#wpfooter, #wpbody-content *:not(.dashicons ), #wpbody-content input:not([dir=ltr]), #wpbody-content textarea:not([dir=ltr]), h1.had{ font-family: bodyfont, roboto, Tahoma; }");
      $this->update_footer_info();
      $n___defs = 'dir="ltr" lang="en-US" min="0" step="1" required';
      $t___defs = 'dir="ltr" lang="en-US" required';
      $s___defs = 'required';

      echo "<h1 class='had'>" . $this->title . "</h1>";
      echo '<div class="wrap">';

      // Docmunentations are shown here
      $hll = "<dev><h4><strong>" . __("Shortcode and Hooks for Developers", "pepro-ultimate-invoice") . "</strong></h4>
            <pre class='caqpde' dir='ltr' align='left' lang='en-US'>" . implode(
        "\n",
        apply_filters("puiw_documentation", array(
          "<b " . (is_rtl() ? "class='fa'" : "") . ">" . _x("WordPress Shortcode", "setting-general", "pepro-ultimate-invoice") . "</b><hr>",
          '<strong class="tag">[puiw_quick_shop el_id="" el_class=""]</strong>      <i>Outputs Quick Shop page. You can use Visual Composer Widget too.</i>',
          '<br />',
          "<b " . (is_rtl() ? "class='fa'" : "") . ">" . _x("WordPress Action Hooks", "setting-general", "pepro-ultimate-invoice") . '</b>',
          '<hr>',
          '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
          '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
          '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
          '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
          '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
          '<br />',
          "<b " . (is_rtl() ? "class='fa'" : "") . ">" . _x("WordPress Filter Hooks", "setting-general", "pepro-ultimate-invoice") . '</b>',
          '<hr>',
          '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
          '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
          '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
          '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
          '<br />',
          "<b " . (is_rtl() ? "class='fa'" : "") . ">" . _x("jQuery Triggering Hooks", "setting-general", "pepro-ultimate-invoice") . '</b>',
          '<hr>',
          '<strong class="tag" style="display: block;" >const PEOPCA_MOTHER = $(".pepro-one-page-purchase---top-parent");</strong>',
          '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
          '<strong class="tag" >order_ajax_request_failed</strong>             <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button fails</i>',
          '<strong class="tag" >ajax_request_success</strong>                  <i>TRIGGERS ON PEOPCA_MOTHER   --Fires on instant shop page, when "Procceed to chekout" button successes</i>',
          '<strong class="tag" >ajax_request_failed</strong>                   <i>TRIGGERS ON PEOPCA_MOTHER   --Fires on instant shop page, when "Procceed to chekout" button fails</i>',
          '<strong class="tag" >update_cart</strong>                           <i>TRIGGERS ON PEOPCA_MOTHER   --Fires on instant shop page, when basket data updates</i>',
          '<strong class="tag" >startup</strong>                               <i>TRIGGERS ON PEOPCA_MOTHER   --Fires on instant shop page, when page loads</i>',
        ))
      ) . "</pre></dev>";
      echo "$hll</div>";
      $html_output = ob_get_contents();
      ob_end_clean();
      print $html_output;
    }
    /**
     * change admin area footer text
     *
     * @method  update_footer_info
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function update_footer_info() {
      add_filter('admin_footer_text', function () {
        return sprintf(_x("Thanks for using %s products.", "footer-copyright", "pepro-ultimate-invoice"), "<b><a href='https://peprodev.com/' target='_blank' >" . __("Pepro Dev", "pepro-ultimate-invoice") . "</a></b>");
      }, 11000);
      add_filter('update_footer', function () {
        return sprintf(_x("%s — Version %s", "footer-copyright", "pepro-ultimate-invoice"), "<strong><a href='https://wordpress.org/plugins/pepro-ultimate-invoice/' target='_blank'>$this->title</a></strong>", $this->version);
      }, 1100);
    }
    /**
     * receive and return ajax json/data
     *
     * @method  handel_ajax_req
     * @return  json
     * @version 1.1.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    /**
     * save the whole settings page without a reload
     *
     * the serialised form arrives in `form`, it is parsed back into $_POST so the
     * WooCommerce settings api sees exactly what a normal submit would give it
     *
     * @method  ajax_save_settings
     * @version 1.0.0
     * @since   2.5.0
     * @license https://peprodev.com/license PeproDev License
     */
    public function ajax_save_settings() {
      if (empty($_POST["form"])) {
        wp_send_json_error(array("message" => __('Incorrect data!', "pepro-ultimate-invoice")));
      }
      // the settings pages are not built on an ajax request, ours has to be asked for
      if (!class_exists("WC_Admin_Settings") && defined("WC_ABSPATH")) {
        include_once WC_ABSPATH . "includes/admin/class-wc-admin-settings.php";
      }
      if (class_exists("WC_Admin_Settings")) {
        \WC_Admin_Settings::get_settings_pages();
      }
      $page = isset($GLOBALS["puiw_settings_page"]) ? $GLOBALS["puiw_settings_page"] : null;
      if (!$page || !method_exists($page, "save")) {
        wp_send_json_error(array("message" => __('Incorrect data!', "pepro-ultimate-invoice")));
      }

      // parse_str() honours max_input_vars and truncates without a word, which on a shop
      // with several languages silently drops the tail of the form and reads as "it did
      // not save". Count the pairs first and give the request room for them.
      $serialized = (string) wp_unslash($_POST["form"]);
      $pairs      = substr_count($serialized, "&") + 1;
      $allowed    = (int) ini_get("max_input_vars");
      if ($allowed && $pairs >= $allowed) {
        @ini_set("max_input_vars", (string) ($pairs + 200));
        if ((int) ini_get("max_input_vars") <= $pairs) {
          wp_send_json_error(array("message" => sprintf(
            /* translators: 1: number of fields on the page, 2: the server's max_input_vars */
            _x("This page sends %1\$d fields but the server only accepts %2\$d (max_input_vars). Raise it in php.ini, or your settings will save only in part.", "wc-setting", "pepro-ultimate-invoice"),
            $pairs,
            $allowed
          )));
        }
      }

      $fields = array();
      parse_str($serialized, $fields);
      if (empty($fields["puiw_settings_nonce"]) || !wp_verify_nonce($fields["puiw_settings_nonce"], "pepro-ultimate-invoice-settings")) {
        wp_send_json_error(array("message" => __('Unauthorized Access Denied!', "pepro-ultimate-invoice")), 403);
      }

      // save_fields() reads $_POST and expects slashed values, as a real submit gives
      $_POST = array_merge($_POST, wp_slash($fields));

      $page->save();

      if (class_exists("WC_Cache_Helper")) {
        \WC_Cache_Helper::get_transient_version("settings", true);
      }
      delete_transient("puiw_known_item_meta_keys");

      wp_send_json_success(array(
        "message" => _x("Your settings have been saved.", "wc-setting", "pepro-ultimate-invoice"),
        "time"    => date_i18n(get_option("time_format")),
      ));
    }
    /**
     * template editor endpoints
     *
     * @method  ajax_theme_editor
     * @param   string $action
     * @version 1.0.0
     * @since   3.0.0
     * @license https://peprodev.com/license PeproDev License
     */
    public function ajax_theme_editor($action) {
      $themes = "\peproulitmateinvoice\PeproUltimateInvoice_Themes";
      $theme  = isset($_POST["theme"]) ? sanitize_text_field(wp_unslash($_POST["theme"])) : "";
      $dir    = $themes::resolve($theme);

      $fail = function ($thing) {
        $message = is_wp_error($thing) ? $thing->get_error_message() : (string) $thing;
        wp_send_json_error(array("message" => $message));
      };

      if ("theme-import" === $action) {
        if (empty($_FILES["file"]["tmp_name"]) || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
          $fail(__('Incorrect data!', "pepro-ultimate-invoice"));
        }
        $checked = wp_check_filetype(basename($_FILES["file"]["name"]), array("zip" => "application/zip"));
        if ("zip" !== $checked["ext"]) {
          $fail(__("Only a .zip archive can be imported.", "pepro-ultimate-invoice"));
        }
        $result = $themes::import($_FILES["file"]["tmp_name"], sanitize_file_name(wp_unslash($_FILES["file"]["name"])));
        if (is_wp_error($result)) {
          $fail($result);
        }
        wp_send_json_success(array("theme" => $result, "message" => __("Template imported.", "pepro-ultimate-invoice")));
      }

      if ("" === $dir) {
        $fail(__("That template could not be found.", "pepro-ultimate-invoice"));
      }

      switch ($action) {
        case "theme-open":
          wp_send_json_success(array(
            "theme" => $themes::info($dir),
            "files" => $themes::files($dir),
            "meta"  => $this->theme_meta($dir),
          ));
          break;

        case "theme-read":
          $contents = $themes::read_file($dir, isset($_POST["file"]) ? wp_unslash($_POST["file"]) : "");
          if (is_wp_error($contents)) {
            $fail($contents);
          }
          wp_send_json_success(array("contents" => $contents));
          break;

        case "theme-save":
          // the editor sends raw markup, slashes added by wp must come back off first
          $contents = isset($_POST["contents"]) ? wp_unslash($_POST["contents"]) : "";
          $file     = isset($_POST["file"]) ? wp_unslash($_POST["file"]) : "";
          $written  = $themes::write_file($dir, $file, $contents);
          if (is_wp_error($written)) {
            $fail($written);
          }
          wp_send_json_success(array("message" => __("Saved.", "pepro-ultimate-invoice"), "time" => date_i18n(get_option("time_format"))));
          break;

        case "theme-meta":
          $ok = $themes::write_header($dir, array(
            "name"           => isset($_POST["name"]) ? sanitize_text_field(wp_unslash($_POST["name"])) : "",
            "author"         => isset($_POST["author"]) ? sanitize_text_field(wp_unslash($_POST["author"])) : "",
            "version"        => isset($_POST["ver"]) ? sanitize_text_field(wp_unslash($_POST["ver"])) : "",
            "plugin_version" => isset($_POST["requires"]) ? sanitize_text_field(wp_unslash($_POST["requires"])) : "",
          ));
          if (!$ok) {
            $fail(__("That template could not be written.", "pepro-ultimate-invoice"));
          }
          wp_send_json_success(array("message" => __("Saved.", "pepro-ultimate-invoice"), "theme" => $themes::info($dir)));
          break;

        case "theme-duplicate":
          $result = $themes::duplicate($dir,
            isset($_POST["name"]) ? sanitize_text_field(wp_unslash($_POST["name"])) : "",
            isset($_POST["author"]) ? sanitize_text_field(wp_unslash($_POST["author"])) : ""
          );
          if (is_wp_error($result)) {
            $fail($result);
          }
          wp_send_json_success(array("theme" => $result, "message" => __("Template duplicated.", "pepro-ultimate-invoice")));
          break;

        case "theme-export":
          $result = $themes::export($dir);
          if (is_wp_error($result)) {
            $fail($result);
          }
          wp_send_json_success($result);
          break;

        case "theme-delete":
          $result = $themes::remove($dir);
          if (is_wp_error($result)) {
            $fail($result);
          }
          wp_send_json_success(array("message" => __("Template deleted.", "pepro-ultimate-invoice")));
          break;
      }
    }
    /**
     * the Requires line of a template header
     *
     * @method  theme_meta
     * @param   string $dir
     * @return  array
     * @version 1.0.0
     * @since   3.0.0
     * @license https://peprodev.com/license PeproDev License
     */
    public function theme_meta($dir) {
      $cfg = is_readable("$dir/default.cfg") ? (string) file_get_contents("$dir/default.cfg") : "";
      preg_match('/^Requires:\s*(.+)$/mi', $cfg, $match);
      return array("requires" => isset($match[1]) ? trim($match[1]) : "");
    }
    public function handel_ajax_req() {
      if (wp_doing_ajax() && !empty($_POST['wparam']) && !empty($_POST['nonce'])) {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), "pepro-ultimate-invoice")) {
          wp_send_json_error(array("message" => __('Unauthorized Access Denied!', "pepro-ultimate-invoice")));
        }
        $wparam = sanitize_text_field(wp_unslash($_POST['wparam']));
        // the same nonce is printed on the front end for the cart widgets, so a
        // nonce alone proves nothing about who is calling: shop actions need a
        // capability check of their own
        $privileged = array(
          "retrive-admins-emails" => "manage_woocommerce",
          "save-swatches"         => "manage_woocommerce",
          "import_options"        => "manage_woocommerce",
          "settings-json"         => "manage_woocommerce",
          "settings-php"          => "manage_woocommerce",
          "settings-reset"        => "manage_options",
          "settings-clear"        => "manage_options",
          "settings-fill"         => "manage_options",
          "send-mail-html"        => "manage_woocommerce",
          "save-resid"            => "edit_shop_orders",
          "save-settings"         => "manage_woocommerce",
          "theme-open"            => "manage_woocommerce",
          "theme-read"            => "manage_woocommerce",
          "theme-save"            => "manage_woocommerce",
          "theme-meta"            => "manage_woocommerce",
          "theme-duplicate"       => "manage_woocommerce",
          "theme-export"          => "manage_woocommerce",
          "theme-import"          => "manage_woocommerce",
          "theme-delete"          => "manage_woocommerce",
        );
        $privileged = apply_filters("puiw_ajax_required_capabilities", $privileged);
        if (isset($privileged[$wparam]) && !current_user_can($privileged[$wparam])) {
          wp_send_json_error(array("message" => __('Unauthorized Access Denied!', "pepro-ultimate-invoice")), 403);
        }
        global $woocommerce;
        switch ($wparam) {
          case 'save-settings':
            $this->ajax_save_settings();
            break;
          case 'theme-open':
          case 'theme-read':
          case 'theme-save':
          case 'theme-meta':
          case 'theme-duplicate':
          case 'theme-export':
          case 'theme-import':
          case 'theme-delete':
            $this->ajax_theme_editor($wparam);
            break;
          case 'save-resid':
            $order = sanitize_post($_POST['order']);
            $resid = sanitize_post($_POST['resid']);
            if (empty($order)) wp_send_json_error(array("message" => __('Incorrect data!', "pepro-ultimate-invoice")));
            $order = wc_get_order($order);
            $order->update_meta_data("_shipping_puiw_invoice_track_id", $resid);
            $order->save();
            wp_send_json_success(["msg" => _x("Success", "wc-setting-js", "pepro-ultimate-invoice"), "res" => $order->get_meta("_shipping_puiw_invoice_track_id", true)]);
            break;
          case "add-cart":
            $cart_date = sanitize_post($_POST['lparam']);
            if (is_array($cart_date) && !empty($cart_date)) {
              $woocommerce->cart->empty_cart();
              foreach ($cart_date as $pid => $qty) {
                $product = wc_get_product($pid);
                if (!$product) {
                  continue;
                }
                if ($product->get_type() === "simple" && $product->get_stock_status() === "instock") {
                  $woocommerce->cart->add_to_cart($pid, $qty);
                }
              }
              wp_send_json_success(array("message" => __('Products successfully added to cart!', "pepro-ultimate-invoice"), "url" => wc_get_cart_url()));
            } else {
              wp_send_json_error(array("message" => __('Incorrect data!', "pepro-ultimate-invoice")));
            }
            break;
          case "place-order":
            $customer_id = get_current_user_id();
            $billing_address = array(
              'first_name' => get_user_meta($customer_id, 'billing_first_name', true),
              'last_name' => get_user_meta($customer_id, 'billing_last_name', true),
              'company' => get_user_meta($customer_id, 'billing_company', true),
              'address_1' => get_user_meta($customer_id, 'billing_address_1', true),
              'address_2' => get_user_meta($customer_id, 'billing_address_2', true),
              'city' => get_user_meta($customer_id, 'billing_city', true),
              'state' => get_user_meta($customer_id, 'billing_state', true),
              'postcode' => get_user_meta($customer_id, 'billing_postcode', true),
              'country' => get_user_meta($customer_id, 'billing_country', true),
              'email' => get_user_meta($customer_id, 'billing_email', true),
              'phone' => get_user_meta($customer_id, 'billing_phone', true),
            );
            $shipping_address = array(
              'first_name' => get_user_meta($customer_id, 'shipping_first_name', true),
              'last_name'  => get_user_meta($customer_id, 'shipping_last_name', true),
              'company'  => get_user_meta($customer_id, 'shipping_company', true),
              'address_1'  => get_user_meta($customer_id, 'shipping_address_1', true),
              'address_2'  => get_user_meta($customer_id, 'shipping_address_2', true),
              'city'  => get_user_meta($customer_id, 'shipping_city', true),
              'state'  => get_user_meta($customer_id, 'shipping_state', true),
              'postcode'  => get_user_meta($customer_id, 'shipping_postcode', true),
              'country'  => get_user_meta($customer_id, 'shipping_country', true),
            );
            $default_args = array(
              'status' => "wc-prebuy-invoice",
              'customer_id' => $customer_id,
              'customer_note' => wp_kses_post(nl2br(wptexturize($this->tpl->get_preorder_customer_extra_note()))),
            );
            $order = wc_create_order($default_args);
            foreach ($woocommerce->cart->get_cart() as $cart_item_key => $values) {
              $item_id = $order->add_product(
                $values['data'],
                $values['quantity'],
                array(
                  'variation' => $values['variation'],
                  'totals' => array(
                    'subtotal' => $values['line_subtotal'],
                    'subtotal_tax' => $values['line_subtotal_tax'],
                    'total' => $values['line_total'],
                    'tax' => $values['line_tax'],
                    'tax_data' => $values['line_tax_data'] // Since 2.2
                  )
                )
              );
            }
            $order->set_address($billing_address, 'billing');
            $order->set_address($shipping_address, 'shipping');
            $order->add_order_note(sprintf(__("Invoice Created by %s%s", "pepro-ultimate-invoice"), "<strong>{$this->title} v.{$this->version}</strong>", "<br /><a class='delete_note' style='font-size: 0.7rem;' href='https://peprodev.com/'>" . __("Pepro Dev. Group", "pepro-ultimate-invoice") . "</a>"));
            $order->calculate_totals();
            $order->update_meta_data('puiw_shopmngr_provided_note', wp_kses_post(nl2br(wptexturize($this->tpl->get_preorder_shopmngr_extra_note()))));
            $order->save();
            if ("yes" == $this->tpl->get_allow_preorder_emptycart()) {
              $woocommerce->cart->empty_cart();
            }
            wp_send_json_success(
              array(
                "url" => add_query_arg("key", $order->get_order_key(), home_url("?invoice=" . $order->get_order_number())),
                "view" => $order->get_view_order_url(),
                "invoice" => $order->get_order_number(),
                "msg" => __('Request success!', "pepro-ultimate-invoice"),
              )
            );

            break;
          case "send-mail-html":
            $order_id = (int) trim($_POST['lparam']);
            $order = wc_get_order($order_id);
            if (!$order) {
              wp_send_json_error(array("msg" => __("No valid order", "pepro-ultimate-invoice")));
            }
            $id = $order->get_id();
            $email = $order->get_billing_email();
            if (!empty($_POST['qparam'])) {
              $email = $_POST['qparam'];
            }
            $advanced = false;
            if (!empty($_POST['eparam'])) {
              global $puiw_send_mail_params_advanced;
              $puiw_send_mail_params_advanced = sanitize_post($_POST['eparam']);

              add_filter("puiw_get_default_dynamic_params", function ($opts, $order) {
                global $puiw_send_mail_params_advanced;
                $g_GET = $puiw_send_mail_params_advanced;

                if (isset($g_GET["tp"]) && !empty($g_GET["tp"])) {
                  $opts["template"] = sanitize_text_field(base64_decode(urldecode($g_GET["tp"])));
                  $opts["preinvoice_template"] = sanitize_text_field(base64_decode(urldecode($g_GET["tp"])));
                }

                if (isset($g_GET["pclr"]) && !empty($g_GET["pclr"])) {
                  $opts["theme_color"] = sanitize_hex_color(base64_decode(urldecode(trim($g_GET["pclr"]))));
                  $opts["preinvoice_theme_color"] = sanitize_hex_color(base64_decode(urldecode($g_GET["pclr"])));
                }

                if (isset($g_GET["sclr"]) && !empty($g_GET["sclr"])) {
                  $opts["theme_color2"] = sanitize_hex_color(base64_decode(urldecode($g_GET["sclr"])));
                  $opts["preinvoice_theme_color2"] = sanitize_hex_color(base64_decode(urldecode($g_GET["sclr"])));
                }

                if (isset($g_GET["tclr"]) && !empty($g_GET["tclr"])) {
                  $opts["theme_color3"] = sanitize_hex_color(base64_decode(urldecode($g_GET["tclr"])));
                  $opts["preinvoice_theme_color3"] = sanitize_hex_color(base64_decode(urldecode($g_GET["tclr"])));
                }

                return $opts;
              }, 10, 2);
            }
            if (!empty($email)) {
              if (!empty($_POST['dparam']) && "PDF" == trim($_POST['dparam'])) {
                $wp_mail = $this->send_formatted_email($order_id, $email, true);
              } else {
                $wp_mail = $this->send_formatted_email($order_id, $email, false);
              }

              $email_label = (is_array($email) && count($email) > 0) ? (count($email) > 3 ? sprintf(__("%d emails", "pepro-ultimate-invoice"), count($email)) : implode("<br>", $email)) : $email;

              if ($wp_mail) {
                wp_send_json_success(array("e" => $puiw_send_mail_params_advanced, "msg" => sprintf(__("Sending email to <br><strong>%s</strong><br>was successfully done", "pepro-ultimate-invoice"), $email_label)));
              } else {
                wp_send_json_error(array("e" => $puiw_send_mail_params_advanced, "msg" => sprintf(__("Error sending email to <br><strong>%s</strong>", "pepro-ultimate-invoice"), $email_label)));
              }
            } else {
              wp_send_json_error(array("msg" => __("No valid email found for this order", "pepro-ultimate-invoice")));
            }

            break;
          case "retrive-admins-emails":
            $shopmngrs_mail = $this->get_wc_managers();
            if ($shopmngrs_mail && !empty($shopmngrs_mail) && count($shopmngrs_mail) > 0) {
              wp_send_json_success(array("emails" => $shopmngrs_mail));
            } else {
              wp_send_json_error(array("msg" => __("Error fetching shop managers.", "pepro-ultimate-invoice")));
            }

            break;
          case "save-swatches":
            $lparam = sanitize_textarea_field($_POST['lparam']);
            update_option("puiw_color_swatches", $lparam);
            wp_send_json_success(array("msg" => __('Saved successfully!', "pepro-ultimate-invoice"),));

            break;
          case "settings-json":
            wp_send_json_success(array("data" => $this->change_default_settings("JSON")));
            break;
          case "settings-php":
            wp_send_json_success(array("data" => "<?php" . PHP_EOL . $this->change_default_settings("GET")));
            break;
          case "settings-reset":
          case "settings-clear":
          case "settings-fill":
            // the danger zone runs here and answers in place, so the page never has to
            // leave for a bare wp_die screen in another tab
            $mode  = array("settings-reset" => "RESET", "settings-clear" => "CLEAR", "settings-fill" => "SET")[$wparam];
            $count = (int) $this->change_default_settings($mode);
            delete_transient("puiw_dashboard_stats_v2");
            wp_send_json_success(array("msg" => sprintf(
              /* translators: %d: number of settings changed */
              _n("%d setting was changed.", "%d settings were changed.", $count, "pepro-ultimate-invoice"),
              $count
            ), "count" => $count));
            break;
          case "import_options":
            $wc_opt       = array();
            $wc_opt_count = 0;
            if (!empty($_POST['lparam'])) {
              foreach ((array) wp_unslash($_POST['lparam']) as $key => $value) {
                // the multiselects store arrays, and sanitize_text_field() turns an array
                // into an empty string, so a straight import used to blank every one of them
                $wc_opt[sanitize_key($key)] = $this->sanitize_setting_value($value);
              }
              $wc_opt_count = $this->change_default_settings("MODIFY", $wc_opt);
            }
            wp_send_json_success(array("msg" => __('Saved successfully!', "pepro-ultimate-invoice"), 'opts' => $wc_opt_count, 'data' => $wc_opt));

            break;
        }
      }
    }
    /**
     * clean one imported setting, keeping arrays as arrays
     *
     * @method sanitize_setting_value
     * @param mixed $value
     * @return mixed
     * @version 1.0.0
     * @since 3.2.5
     */
    public function sanitize_setting_value($value) {
      if (is_array($value)) {
        return array_map(array($this, "sanitize_setting_value"), $value);
      }
      if (is_bool($value) || is_int($value) || is_float($value)) {
        return $value;
      }
      return sanitize_text_field((string) $value);
    }
    /**
     * list woocommerce managers and administrators
     *
     * @method  get_wc_managers
     * @return  array                     array of users with their name and email
     * @access  public
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function get_wc_managers() {
      $_wc_managers = array();
      $users = get_users(
        array(
          "role__in" => array("administrator", "shop_manager")
        )
      );
      foreach ($users as $user) {
        $_wc_managers[$user->user_email] = "$user->user_firstname $user->user_lastname";
      }
      return $_wc_managers;
    }
    /**
     * add menu to dashboard, options page
     *
     * @method  admin_menu
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function admin_menu() {
      add_submenu_page("woocommerce", $this->title, $this->title, "manage_options", $this->url);
    }
    /**
     * fire this hook on admin side load
     *
     * @method  admin_init
     * @param   array $hook
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function admin_init($hook) {
      if (!$this->_wc_activated()) {
        add_action(
          'admin_notices',
          function () {
            echo "<div class=\"notice error\"><p>" . sprintf(
              _x('%1$s needs %2$s in order to function', "required-plugin", "pepro-ultimate-invoice"),
              "<strong>" . $this->title . "</strong>",
              "<a href='" . admin_url("plugin-install.php?s=woocommerce&tab=search&type=term") . "' style='text-decoration: none;' target='_blank'><strong>" .
                _x("WooCommerce", "required-plugin", "pepro-ultimate-invoice") . "</strong> </a>"
            ) . "</p></div>";
          }
        );
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        deactivate_plugins(plugin_basename(__FILE__));
      }

      register_setting("{$this->td}_general", "puiw_address_display_method", array(
        'sanitize_callback' => 'wp_kses_post',  // Allows safe HTML tags
      ));

      $peproultimateinvoice_options = $this->get_setting_options();
      foreach ($peproultimateinvoice_options as $sections) {
        foreach ($sections["data"] as $id => $def) {
          add_option($id, $def);
          register_setting($sections["name"], $id);
        }
      }
      $this->maybe_upgrade();
      add_action("add_meta_boxes", array($this, "add_meta_boxes"));
      add_action("admin_enqueue_scripts", array($this, 'admin_enqueue_scripts'));
      // add_action("save_post", array($this, "wc_save_shop_order_metabox"), 10);
      // add_action("woocommerce_process_order_meta", array($this, "wc_save_shop_order_metabox"), 10);
      // priority 5: run before WC_Meta_Box_Order_Data::save() (40) so the tracking serial
      // is stored before the status transition mails/sms of the same request are fired
      add_action("woocommerce_process_shop_order_meta", array($this, "wc_save_shop_order_metabox"), 5);
      add_filter("woocommerce_email_styles", array($this, "woocommerce_email_styles_edit"));
    }
    /**
     * Woocommerce Email Styles Edit
     *
     * @method woocommerce_email_styles_edit
     * @param string $css
     * @return string css code
     * @version 1.0.0
     * @since 1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function woocommerce_email_styles_edit($css) {
      $bg              = get_option('woocommerce_email_background_color');
      $body            = get_option('woocommerce_email_body_background_color');
      $base            = get_option('woocommerce_email_base_color');
      $base_text       = wc_light_or_dark($base, '#202020', '#ffffff');
      $text            = get_option('woocommerce_email_text_color');
      $link_color      = wc_hex_is_light($base) ? $base : $base_text;
      $bg_darker_10    = wc_hex_darker($bg, 10);
      $body_darker_10  = wc_hex_darker($body, 10);
      $base_lighter_20 = wc_hex_lighter($base, 20);
      $base_lighter_40 = wc_hex_lighter($base, 40);
      $text_lighter_20 = wc_hex_lighter($text, 20);
      $text_lighter_40 = wc_hex_lighter($text, 40);
      if (wc_hex_is_light($body)) $link_color = wc_hex_is_light($base) ? $base_text : $base;

      ob_start();
      echo $css;
      echo "@font-face { font-family: 'bodyfont'; font-style: normal; font-weight: normal; src: url('" . PEPROULTIMATEINVOICE_URL . "/assets/css/96594ad4.woff') format('woff'); }
          @font-face { font-family: 'bodyfont'; font-style: normal; font-weight: bold; src: url('" . PEPROULTIMATEINVOICE_URL . "/assets/css/96594ad5.woff') format('woff'); }";
      $currentDir = PEPROULTIMATEINVOICE_URL . "/template/default";
    ?>

      @font-face {
      font-family: iranyekan;
      font-style: normal;
      font-weight: bold;
      src: url("<?php echo $currentDir; ?>/fonts/woff2/iranyekanwebbold.woff2") format('woff2'), url("<?php echo $currentDir; ?>/fonts/woff/iranyekanwebbold.woff") format('woff'));
      }

      @font-face {
      font-family: iranyekan;
      font-style: normal;
      font-weight: normal;
      src: url("<?php echo $currentDir; ?>/fonts/woff2/iranyekanwebregular.woff2") format('woff2'), url("<?php echo $currentDir; ?>/fonts/woff/iranyekanwebregular.woff") format('woff'));
      }

      #wrapper { background-color: <?php echo esc_attr($bg); ?>!important; }
      #template_container { background-color: <?php echo esc_attr($body); ?>!important; border: 1px solid <?php echo esc_attr($bg_darker_10); ?>!important; }
      #template_header { background-color: <?php echo esc_attr($base); ?>!important; color: <?php echo esc_attr($base_text); ?>!important; }
      #template_header h1, #template_header h1 a { color: <?php echo esc_attr($base_text); ?>!important; }
      #template_footer #credit { color: <?php echo esc_attr($text_lighter_40); ?>!important; }
      #body_content { background-color: <?php echo esc_attr($body); ?>!important; }
      .td, .address { border: 1px solid <?php echo esc_attr($body_darker_10); ?>!important; }
      h1 { text-shadow: 0 1px 0 <?php echo esc_attr($base_lighter_20); ?>!important; }
      .td, .address, #body_content_inner { color: <?php echo esc_attr($text_lighter_20); ?>!important; }
      h1, h2, h3, .text, .link{ color: <?php echo esc_attr($base); ?>!important; }
      a { color: <?php echo esc_attr($link_color); ?>!important; }
      body, html, p, td, th, table, tr, a, span, h1, h2, h3, h4, h5, h6, pre, #template_header, #template_footer #credit, #body_content_inner, .text { font-family: iranyekan, Tahoma, Arial, sans-serif !important; }
      h1, strong, h2{font-weight: bold !important;}
    <?php
      $css = ob_get_clean();
      ob_end_clean();
      return $css;
    }
    /**
     * funtion for [puiw_quick_shop] shortcode, callback fn
     *
     * @method  integrate_with_shortcode
     * @param   array $atts shortcode attributes
     * @return  string shortcode data
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function integrate_with_shortcode($atts = array(), $content = "") {
      $atts = extract(shortcode_atts(array(
        "css"      => "",
        "el_class" => "",
        "el_id"    => "",
      ), $atts));

      ob_start();
      $css_class = "";
      if ($this->_vc_activated()) {
        $css_class = apply_filters(VC_SHORTCODE_CUSTOM_CSS_FILTER_TAG, vc_shortcode_custom_css_class($css, ' '), "puiw_quick_shop", $atts);
      }
      $uniqid = uniqid("{$this->db_slug}-");
      $extra = [];
      $extra[] = esc_attr($uniqid);
      $extra[] = esc_attr($el_class);
      $extra[] = esc_attr($css_class);
      echo "<div class='".esc_attr("pepro-one-page-purchase---top-parent " . implode(" ", $extra))."' id='".esc_attr($el_id)."'>";
      $cats = "<option value=\"[ALL]\">" . __("All Categories", "pepro-ultimate-invoice") . "</option>";
      $products = "";
      $products_js = array();
      $cat_args = array('orderby' => "name", 'order' => "ASC", 'hide_empty' => true,);
      $prodcuts_args = array(
        'orderby'      => 'modified',
        'order'        => 'DESC',
        'return'       => 'ids',
        'status'       => 'publish',
        'type'         => 'simple',
        'stock_status' => 'instock',
        'limit'        => -1,
      );
      $product_categories = get_terms('product_cat', apply_filters("pepro_one_page_purchase_categories_args", $cat_args));
      if (!empty($product_categories)) {
        foreach ($product_categories as $key => $category) {
          $cats .= "<option value=\"$category->slug\">$category->name</option>";
        }
      }
      // https://github.com/woocommerce/woocommerce/wiki/wc_get_products-and-WC_Product_Query
      $loop = wc_get_products(apply_filters("pepro_one_page_purchase_prodcuts_args", $prodcuts_args));
      $currencysymbol = get_woocommerce_currency_symbol();
      foreach ($loop as $product) {
        $product = wc_get_product($product);
        $price = $product->get_price_html();
        $sku = $product->get_sku();
        $total_sales = get_post_meta($product->get_id(), 'total_sales', true);
        $_wc_average_rating = get_post_meta($product->get_id(), '_wc_average_rating', true);
        $skutxt = _x("SKU:", "js-i18n", "pepro-ultimate-invoice");
        $skutxt = _x("SKU:", "js-i18n", "pepro-ultimate-invoice");
        $stock = $product->get_stock_quantity();
        //https://www.php.net/manual/en/function.date.php
        $dateformat = apply_filters("pepro_one_page_purchase_prodcuts_date_format", "Y-m-j h:i:s");
        $imgurl = wp_get_attachment_image_src($product->get_image_id(), 'thumbnail')[0];
        $categoris_raw = strip_tags(wc_get_product_category_list($product->get_id()));
        $updated = $product->get_date_modified()->format($dateformat);
        $products .= "
            <li class=\"pepro-one-page-purchase--product-item catfiltered\" data-pid=\"{$product->get_id()}\" data-last-update=\"{$updated}\">
              <div class=\"pepro-one-page-purchase--product-image\"><img src=\"$imgurl\" style=\"height: 96px;\" /></div>
              <div class=\"pepro-one-page-purchase--product-item-info\">
                <div class=\"pepro-one-page-purchase--product-item-info-primary\" data-total-sales='{$total_sales}' data-av-rating='{$_wc_average_rating}' >
                  <div class=\"pepro-one-page-purchase--product-title\"><a target='_blank' href=\"" . get_permalink($product->get_id()) . "\">{$product->get_name()}</a></div>
                  <div class=\"pepro-one-page-purchase--product-item-info-secondary\">
                    <div class=\"pepro-one-page-purchase--product-cat\">{$categoris_raw}</div>
                    <div class=\"pepro-one-page-purchase--product-sku\">{$skutxt} {$product->get_sku()}</div>
                    </div>
                  </div>
                <div class=\"pepro-one-page-purchase--product-item-pruchase\">
                  <div class=\"pepro-one-page-purchase--product-item-pricelist\">
                    <div class=\"pepro-one-page-purchase--product-regular_price\" data-raw=\"{$product->get_regular_price()}\">" . wc_price($product->get_regular_price(), array("currency" => " ")) . "</div>
                    <div class=\"pepro-one-page-purchase--product-sale_price\" data-raw=\"{$product->get_price()}\">" . wc_price($product->get_price(), array("currency" => " ")) . "</div>
                  </div>
                  <div class=\"pepro-one-page-purchase--product-item-pricesymbol\">
                    <div class=\"pepro-one-page-purchase--product-currency_symbol\">" . $currencysymbol . "</div>
                  </div>
                </div>
                <div class=\"pepro-one-page-purchase--product-add2cart\" >
                  <a href=\"#\" data-pid=\"{$product->get_id()}\" class=\"pepro-one-page-purchase--add2cart\" title=\"" . __("Add to cart", "pepro-ultimate-invoice") . "\" >
                    <i class=\"fa fa-shopping-bag\"></i>
                  </a>
                </div>

              </div>
            </li>";
        $products_js[$product->get_id()] = array(
          "get_id"              =>  $product->get_id(),
          "get_date"            =>  $product->get_date_modified()->format($dateformat),
          "get_name"            =>  $product->get_name(),
          "get_sku"             =>  $product->get_sku(),
          "get_permalink"       =>  get_permalink($product->get_id()),
          "get_price_raw"       =>  $product->get_price(),
          "get_price"           =>  wc_price($product->get_price(), array("currency" => " ")),
          "get_regular_price_raw" => $product->get_regular_price(),
          "get_regular_price"   =>  wc_price($product->get_regular_price(), array("currency" => " ")),
          "get_sale_price_raw"  =>  $product->get_sale_price(),
          "get_sale_price"      =>  wc_price($product->get_sale_price(), array("currency" => " ")),
          "get_image"           =>  $imgurl,
        );
      } ?>
      <script>
        var peproOnePagePurchaseAndInvoice = <?php echo json_encode($products_js); ?>;
      </script>
      <div class="pepro-one-page-purchase--container tool">
        <div class="pepro-one-page-purchase--categories-container">
          <div class="pepro-one-page-purchase--categories">
            <h4 class="pepro-one-page-purchase--title"><?php echo __("Filter Products by Category", "pepro-ultimate-invoice"); ?></h4>
            <select id="pepro-one-page-purchase--select-categories"><?php echo $cats; ?></select>
          </div>
        </div>
        <div class="pepro-one-page-purchase--products">
          <div class="pepro-one-page-purchase--product-search">
            <h4 class="pepro-one-page-purchase--title"><?php echo __("Sort and Search shown products", "pepro-ultimate-invoice"); ?></h4>
            <div class="pepro-one-page-purchase--product-search-container">
              <input type="search" id="pepro-one-page-purchase--search-input" placeholder="<?php echo __("Search among current shown products", "pepro-ultimate-invoice"); ?> ..." title="<?php echo __("Search among current shown products", "pepro-ultimate-invoice"); ?>" />
            </div>
            <div class="pepro-one-page-purchase--product-sort-container">
              <span class="pepro-one-page-purchase--title"><?php echo __("Sort By: ", "pepro-ultimate-invoice"); ?></span>
              <a class="pepro-one-page-purchase-filter" data-query="alphabetically" href="javascript:void(0);"><?php echo __("Alphabetically", "pepro-ultimate-invoice"); ?></a>
              <a class="pepro-one-page-purchase-filter" data-query="popularity" href="javascript:void(0);"><?php echo __("Popularity", "pepro-ultimate-invoice"); ?></a>
              <a class="pepro-one-page-purchase-filter" data-query="total_sales" href="javascript:void(0);"><?php echo __("Total Sales", "pepro-ultimate-invoice"); ?></a>
              <a class="pepro-one-page-purchase-filter active" data-query="latest" href="javascript:void(0);"><?php echo __("Latest", "pepro-ultimate-invoice"); ?></a>
              <a class="pepro-one-page-purchase-filter" data-query="price_asc" href="javascript:void(0);"><?php echo __("Price (ASC)", "pepro-ultimate-invoice"); ?></a>
              <a class="pepro-one-page-purchase-filter" data-query="price_desc" href="javascript:void(0);"><?php echo __("Price (DESC)", "pepro-ultimate-invoice"); ?></a>
            </div>
          </div>
        </div>
      </div>
      <div class="pepro-one-page-purchase--container">
        <div class="pepro-one-page-purchase--cart-list">
          <div class="pepro-one-page-purchase--cart-body" data-empty="<?php echo __("Your cart is empty!", "pepro-ultimate-invoice"); ?>"></div>
        </div>
        <ul class="pepro-one-page-purchase--product-list" data-empty="<?php echo __("Nothing found!", "pepro-ultimate-invoice"); ?>">
          <?php echo $products; ?>
        </ul>
      </div>
<?php
      echo "</div>";
      wp_enqueue_style("$this->td-ml", "$this->assets_url/css/mobileLayer" . $this->debugEnabled(".css", ".min.css"), array(), "1.0.0", "all");
      wp_enqueue_script("$this->td-ml", "$this->assets_url/js/mobileLayer" . $this->debugEnabled(".js", ".min.js"), array("jquery", "jquery-ui-core"), "1.0.0", true);
      wp_enqueue_style("$this->td", "$this->assets_url/css/front-end" . $this->debugEnabled(".css", ".min.css"), array(), "1.0.0", "all");
      wp_enqueue_script("$this->td", "$this->assets_url/js/front-end" . $this->debugEnabled(".js", ".min.js"), array("jquery"), "1.0.0", true);
      wp_enqueue_style("select2", "{$this->assets_url}css/select2.min.css", false, "4.0.6", "all");
      wp_enqueue_script("select2", "{$this->assets_url}js/select2.min.js", array("jquery"), "4.0.6", true);
      wp_localize_script(
        "$this->td",
        "_i18n",
        array(
          "ajax" => admin_url("admin-ajax.php"),
          "td" => "puiw_$this->td",
          "okaylabel" => _x("Okay", "js-cart-page", "pepro-ultimate-invoice"),
          "nonce" => wp_create_nonce("pepro-ultimate-invoice"),
          "addedtocard" => _x("Added to cart", "js-i18n", "pepro-ultimate-invoice"),
          "thisIsAnError" => _x("THIS IS AN ERROR!", "js-i18n", "pepro-ultimate-invoice"),
          "emptycartSubmit" => _x("Your basket is empty!", "js-i18n", "pepro-ultimate-invoice"),
          "tti" => _x("Total Items:", "js-i18n", "pepro-ultimate-invoice"),
          "ttp" => _x("Total Products:", "js-i18n", "pepro-ultimate-invoice"),
          "unknownError" => _x("There is an unknown error and we are unable to proccess your request", "js-i18n", "pepro-ultimate-invoice"),
          "removefromcart" => _x("Remove from cart", "js-i18n", "pepro-ultimate-invoice"),
          "removefromcartContent" => _x("Clear cart", "js-i18n", "pepro-ultimate-invoice"),
          "skuText" => _x("SKU:", "js-i18n", "pepro-ultimate-invoice"),
          "removeFrombasketConfirmation" => _x("Are you sure you want to remove this item from basket?", "js-i18n", "pepro-ultimate-invoice"),
          "removeFrombasketConfirmation2" => _x("Are you sure you want to remove all items from basket?", "js-i18n", "pepro-ultimate-invoice"),
          "removeFrombasketTitle" => _x("Removing", "js-i18n", "pepro-ultimate-invoice"),
          "removeFrombasketTitle2" => _x("Clear basket", "js-i18n", "pepro-ultimate-invoice"),
          "instantShoppingBasketUpated" => _x("Instant Shopping Basket Upated!", "js-i18n", "pepro-ultimate-invoice"),
          "confirmYes" => _x("Yes", "js-i18n", "pepro-ultimate-invoice"),
          "confirmNo" => _x("No", "js-i18n", "pepro-ultimate-invoice"),
          "currencySymbol" => $currencysymbol,
          "proceedToCheckout" => _x("Proceed to Checkout / Invoice", "js-i18n", "pepro-ultimate-invoice"),
        )
      );
      $s = ob_get_contents();
      ob_end_clean();
      return do_shortcode("$s");
    }
    /**
     * add Visual Composer widget if it was installed
     *
     * @method  integrate_with_vc
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function integrate_with_vc() {
      $params = array(
        array(
          'group' => esc_html__('Design Options', "pepro-ultimate-invoice"),
          'type' => "{$this->td}_about",
          'edit_field_class' => 'vc_column vc_col-sm-12',
          'admin_label' => false,
          'param_name' => "{$this->td}_about",
        ),
        // vc_map_add_css_animation(),
        array(
          'type' => 'el_id',
          'heading' => esc_html__('Element ID', "pepro-ultimate-invoice"),
          'param_name' => 'el_id',
          'edit_field_class' => 'vc_column vc_col-sm-6',
          'description' => sprintf(esc_html__('Enter element ID (Note: make sure it is unique and valid according to %sW3C Specification%s).', "pepro-ultimate-invoice"), '<a href="https://www.w3schools.com/tags/att_global_id.asp" target="_blank">', '</a>'),
          'group' => esc_html__('Design Options', "pepro-ultimate-invoice"),
        ),
        array(
          'type' => 'textfield',
          'heading' => esc_html__('Extra class name', "pepro-ultimate-invoice"),
          'edit_field_class' => 'vc_column vc_col-sm-6',
          'param_name' => 'el_class',
          'description' => esc_html__('If you wish to style particular content element differently, then use this field to add a class name and then refer to it in your css file.', "pepro-ultimate-invoice"),
          'group' => esc_html__('Design Options', "pepro-ultimate-invoice"),
        ),
        array(
          'type' => 'css_editor',
          'heading' => esc_html__('CSS box', "pepro-ultimate-invoice"),
          'param_name' => 'css',
          'group' => esc_html__('Design Options', "pepro-ultimate-invoice"),
        )
      );
      vc_map(
        array(
          'base'                    => "puiw_quick_shop",
          'name'                    => __("Quick Shop", "pepro-ultimate-invoice"),
          'description'             => __('One-page Purchase', "pepro-ultimate-invoice"),
          'class'                   => "{$this->td}__class",
          'icon'                    => plugins_url("/assets/img/peprodev.svg", __FILE__),
          'show_settings_on_create' => false,
          'category'                => __('Pepro Elements', "pepro-ultimate-invoice"),
          'admin_enqueue_css'       => array(
            "{$this->assets_url}/css/vc.init" . $this->debugEnabled(".css", ".min.css"),
            "{$this->assets_url}/css/select2.min.css"
          ),
          'admin_enqueue_js'        => array("{$this->assets_url}/js/select2.min.js",),
          'params'                  => $params
        )
      );
    }
    /**
     * Visual Composer custom widgets type
     *
     * @method  vc_add_pepro_about
     * @param   array  $settings
     * @param   string $value
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function vc_add_pepro_about($settings, $value) {
      ob_start();
      echo "<div style='display: flex;justify-content: start;align-items: center;'>
                  <p style='margin: 0.8rem 1rem;'><img src='" . plugins_url("/assets/img/peprodev.svg", __FILE__) . "' width='55px' /></p>
                  <div><p>" . sprintf(__("Proudly Developed by %s", "pepro-ultimate-invoice"), "<strong><a target='_blank' href='https://peprodev.com/'>" . __("Pepro Dev. Group", "pepro-ultimate-invoice") . "</a></strong>") . "</p></div>
                </div>";
      $html_output = ob_get_contents();
      ob_end_clean();
      return $html_output;
    }

    /* ======================== MISC. FNs ======================== */

    /**
     * check if woocommerce is activated
     *
     * @method  _wc_activated
     * @return  boolean true if installed and activated
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    private function _wc_activated() {
      if (!function_exists('is_woocommerce') || !class_exists('woocommerce')) {
        return false;
      } else {
        return true;
      }
    }
    /**
     * check if visual composer is activated
     *
     * @method  _wc_activated
     * @return  boolean true if installed and activated
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    private function _vc_activated() {
      if (!defined('WPB_VC_VERSION')) {
        return false;
      } else {
        return true;
      }
    }
    /**
     * sample callback fn
     *
     * @method  _callback
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function _callback($a) {
      return $a;
    }
  }
  /**
   * register plugin activation and deactivation statuses
   */
  /**
   * load plugin and load textdomain then set a global varibale to access plugin class!
   *
   * @method  PeproUltimateInvoice___plugin_init
   * @version 1.0.0
   * @since   1.0.0
   * @license https://peprodev.com/license Pepro.dev License
   */
  add_action("plugins_loaded", function () {
    global $PeproUltimateInvoice;
    $PeproUltimateInvoice = new \peproulitmateinvoice\PeproUltimateInvoice;
    register_activation_hook(__FILE__, array("PeproUltimateInvoice", "activation_hook"));
    // register_deactivation_hook(__FILE__, array("PeproUltimateInvoice", "deactivation_hook"));
    // register_uninstall_hook(__FILE__, array("PeproUltimateInvoice", "uninstall_hook"));
  });
}
/*##################################################
Lead Developer: [amirhp-com](https://amirhp.com/)
##################################################*/