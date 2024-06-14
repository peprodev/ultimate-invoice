<?php
/*
Plugin Name: PeproDev Ultimate Invoice
Description: The Most Advanced Invoice Plugin you were looking for! Create customizable PDF/HTML invoices for WooCommerce, attach to Email, Inventory Report, Shipping Labels, Shipping Tracking, Single-shop feature and ...
Contributors: peprodev, amirhpcom, blackswanlab
Tags: woocommerce invoice, pdf invoice, persian
Author: Pepro Dev. Group
Developer: amirhp.com
Author URI: https://pepro.dev/
Developer URI: https://amirhp.com
Plugin URI: https://peprodev.com/pepro-woocommerce-ultimate-invoice/
Tested up to: 6.5.4
WC tested up to: 8.9.2
Version: 2.0.4
Stable tag: 2.0.4
Requires at least: 5.0
Requires PHP: 7.0
WC requires at least: 5.0
Text Domain: pepro-ultimate-invoice
Domain Path: /languages
Copyright: (c) 2024 Pepro Dev. Group, All rights reserved.
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
/*
 * @Last modified by: amirhp-com <its@amirhp.com>
 * @Last modified time: 2024/06/14 10:13:35
 */

namespace peproulitmateinvoice;
use voku\CssToInlineStyles\CssToInlineStyles;
use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
/**
 * prevent data leak
 */
defined("ABSPATH") or die("<h2>Unauthorized Access!</h2><hr><small>PeproDev Ultimate Invoice :: Developed by Pepro Dev. Group (<a href='https://pepro.dev/'>https://pepro.dev/</a>)</small>");
/**
* if plugin was not initiated before, let's do it
*/
if (!class_exists("PeproUltimateInvoice")) {
    /**
     * Main class, accessible on initiation with global $PeproUltimateInvoice
     */
    class PeproUltimateInvoice
    {
        public $td;
        public $plugin_dir;
        public $plugin_url;
        public $assets_url;
        public $plugin_basename;
        public $plugin_file;
        public $version;
        public $db_slug;
        public $title;
        public $url;
        public $title_tw;
        public $title_d;
        public $title_w;
        public $title_t;
        public $barcode;
        public $tpl;
        public $Unauthorized_Access;
        protected $print;
        protected $invoice;
        protected $mta;
        protected $db_table = null;
        protected $manage_links = array();
        protected $meta_links = array();
        /**
         * construct plugin and set initiation hook and declare consts
         *
         * @method  __construct
         * @version 1.0.0
         * @since   1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function __construct()
        {
            load_plugin_textdomain("pepro-ultimate-invoice", false, dirname(plugin_basename(__FILE__))."/languages/");
            $this->td                   = "pepro-ultimate-invoice";
            $this->version              = "2.0.3";
            $this->db_slug              = $this->td;
            $this->plugin_file          = __FILE__;
            $this->plugin_dir           = plugin_dir_path(__FILE__);
            $this->plugin_url           = plugins_url("", __FILE__);
            $this->assets_url           = plugins_url("/assets/", __FILE__);
            $this->plugin_basename      = plugin_basename(__FILE__);
            $this->url                  = admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=general");
            $this->title                = __("Ultimate Invoice", $this->td);
            $this->title_t              = __("PeproDev Ultimate Invoice for Woocommerce", $this->td);
            $this->title_tw             = sprintf(__("%2\$s ver. %1\$s", $this->td), $this->version, $this->title_t);
            $this->title_d              = __("Pepro Development Group", $this->td);
            $this->title_w              = sprintf(__("%2\$s ver. %1\$s", $this->td), $this->version, $this->title);
            $this->Unauthorized_Access  = "<h2 dir='rtl' align='center'>".__("Unauthorized Access!", $this->td)."</h2>";
            $this->Unauthorized_Access .= "<a href='".home_url()."' class='button button-primary'>".__("Go Back", $this->td)."</a>";
            // define constants to be accessible out of the plugin class
            defined('PEPROULTIMATEINVOICE_VER') || define('PEPROULTIMATEINVOICE_VER', $this->version);
            defined('PEPROULTIMATEINVOICE_DIR') || define('PEPROULTIMATEINVOICE_DIR', plugin_dir_path(__FILE__));
            defined('PEPROULTIMATEINVOICE_URL') || define('PEPROULTIMATEINVOICE_URL', plugins_url("", __FILE__));
            defined('PEPROULTIMATEINVOICE_ASSETS_URL') || define('PEPROULTIMATEINVOICE_ASSETS_URL', $this->assets_url);
            add_action("admin_notices", array( $this, "admin_notices"));
            // register_deactivation_hook( __FILE__, function () { update_option("peprodev_ultimate_invoice_alert_viewed_yet", ""); });

            /**
            * add compatibility with High-Performance Order Storage
            */
            add_action("before_woocommerce_init", function(){
              if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
              }
            });

            if ($this->_wc_activated()){
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

              // handle template based functions
              $this->tpl   = new \peproulitmateinvoice\PeproUltimateInvoice_Template();
              // handle print invoice functions
              $this->print = new \peproulitmateinvoice\PeproUltimateInvoice_Print();
              // handle metaboxes and extras for wc orders
              $this->mta   = new \peproulitmateinvoice\PeproUltimateInvoice_Columns();

              // attach pdf to woocommerce emails automatically
              if ("yes" == $this->tpl->get_attach_pdf_invoices_to_mail()) {
                  add_filter('woocommerce_email_attachments', array($this,"attach_pdf_to_wC_emails"), 10, 3);
              }

              // automatic email sending
              if ("automatic" == $this->tpl->get_send_invoices_via_email() || "automatic" == $this->tpl->get_send_invoices_via_email_admin()) {
                  add_action("woocommerce_order_status_changed", array($this,"woocommerce_order_status_changed_action"), 10, 3);
                  add_action("woocommerce_new_order", array($this,"woocommerce_new_order_action"), 10, 1);
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
                  return str_replace(array( 'kg', 'g', 'lbs', 'oz', ), array( __('kg', $this->td), __('g', $this->td), __('lbs', $this->td), __('oz', $this->td), ), $weight);
              });
              add_filter('woocommerce_format_dimensions', function ($weight) {
                  return str_replace( array( 'mm', 'cm', 'in', 'yd', ), array( __('mm', $this->td), __('cm', $this->td), __('in', $this->td), __('yd', $this->td), ), $weight );
              });

              if (is_blog_admin() && isset($_GET["tab"]) && sanitize_text_field($_GET["tab"]) == sanitize_text_field("pepro_ultimate_invoice")) {
                  add_filter('woocommerce_admin_disabled', '__return_true');
                  add_filter('woocommerce_marketing_menu_items', '__return_empty_array');
                  add_filter('woocommerce_helper_suppress_admin_notices', '__return_true');
                  if (!isset($_GET["section"]) || empty(sanitize_text_field($_GET["section"]))) {
                      wp_safe_redirect(admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=general"));
                  }
              }

              // change colors and template if invoice is pre-invoice
              add_filter("puiw_get_default_dynamic_params", array( $this, "puiw_get_default_dynamic_params"), 10, 2);
              add_filter('query_vars', function ($vars) {
                  $vars[] = "tp";
                  $vars[] = "pclr";
                  $vars[] = "sclr";
                  $vars[] = "tclr";
                  return $vars;
              });
              add_filter('bulk_actions-edit-shop_order', array( $this, 'downloads_bulk_actions_edit_product'), 20, 1 );
              add_filter('handle_bulk_actions-edit-shop_order', array( $this, 'downloads_handle_bulk_action_edit_shop_order'), 10, 3 );
            }

        }
        public function admin_notices()
        {
          $message = "";
          $seen = get_option("peprodev_ultimate_invoice_alert_viewed_yet", "");
          if (!$seen || $seen !== $this->version){}
          if (!$this->_wc_activated()) {
            $installWC = admin_url( "plugin-install.php?s=woo&tab=search&type=tag" );
            $alerttext = sprintf(__("%s needs %s to function. Please install and activate it.",$this->td), "<strong>$this->title_t</strong>", "<a href='$installWC' target='_blank'><strong>WooCommerce</strong></a>");
            $message = "<div style='padding: 0.3rem 0.5rem;line-height: 1; margin-inline-start: -0.5rem;'><img src='{$this->assets_url}img/peprodev.svg' width='32px' /></div>";
            $message .= "<div>$alerttext</div>";
            $message = apply_filters("peprodevups_alert_after_active", $message);
          }
          if (!empty($message)) printf( '<div class="%1$s" style="border-color: #ffa176;"><div class="pepro_alert" style="display: flex;align-items: center;">%2$s</div></div>', esc_attr("notice notice-info is-not-dismissible"), $message);
        }
        public function downloads_bulk_actions_edit_product( $actions )
        {
          $actions['invoice_zip'] = __("Bulk Download PDF Invoice",$this->td);
          $actions['invoice_inventory_bulk'] = __("Bulk Print Inventory Report",$this->td);
          $actions['invoice_slips_bulk'] = __("Bulk Print Packing Slips",$this->td);
          return $actions;
        }
        public function downloads_handle_bulk_action_edit_shop_order( $redirect_to, $action, $post_ids )
        {
          global $attach_download_dir,
          $attach_download_file;

          if ($action == 'invoice_zip'){
            $redirect_to = add_query_arg( array('invoice-zip' => implode(',', $post_ids)), $redirect_to );
          }
          if ($action == 'invoice_inventory_bulk'){
            $redirect_to = add_query_arg( array('inventory-bulk' => implode(',', $post_ids)), $redirect_to );
          }
          if ($action == 'invoice_slips_bulk'){
            $redirect_to = add_query_arg( array('slips-bulk' => implode(',', $post_ids)), $redirect_to );
          }

          return $redirect_to;
        }
        public function debugEnabled($debug_true = true, $debug_false = false)
        {
            return apply_filters("puiw_debug_enabled", $debug_true);
        }
        /**
         * woocommerce order placed hook to send emails
         *
         * @method woocommerce_new_order_action
         * @param int $order_id
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function woocommerce_new_order_action($order_id)
        {
            $order = wc_get_order($order_id);
            $note = "";
            if ("automatic" == $this->tpl->get_send_invoices_via_email()) {
                $email = $order->get_billing_email();
                $wp_mail = $this->send_formatted_email($order_id, $email, true);
                if ($wp_mail) {
                    $note .= "<br />". sprintf(__("Automatic Email sent to customer mail address:<br> %s", $this->td), $email);
                } else {
                    $note .= "<br />". sprintf(__("An error occured sending Automatic Email to customer mail address:<br> %s", $this->td), $email);
                }
            }
            if ("automatic" == $this->tpl->get_send_invoices_via_email_admin()) {
                $valid_reciever_shopmngrs = $this->tpl->get_send_invoices_via_email_shpmngrs();
                if (!empty($valid_reciever_shopmngrs)) {
                    $wp_mail = $this->send_formatted_email($order_id, $valid_reciever_shopmngrs, true);
                    if ($wp_mail) {
                        $note .= "<br />". sprintf(__("Automatic Email sent to following shop managers:<br> %s", $this->td), (count($valid_reciever_shopmngrs) > 1 ? implode(", ", $valid_reciever_shopmngrs) : $valid_reciever_shopmngrs));
                    } else {
                        $note .= "<br />". sprintf(__("An error occured sending Automatic Email to following shop managers:<br> %s", $this->td), (count($valid_reciever_shopmngrs) > 1 ? implode(", ", $valid_reciever_shopmngrs) : $valid_reciever_shopmngrs));
                    }
                } else {
                    $note .= "<br />". __("No Shop Manager selected to send Automatic Email to.", $this->td);
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function woocommerce_order_status_changed_action($order_id, $old_status, $new_status)
        {
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
                        $note .= "<br />". sprintf(__("Automatic Email sent to customer mail address:<br> %s", $this->td), $email);
                    } else {
                        $note .= "<br />". sprintf(__("An error occured sending Automatic Email to customer mail address:<br> %s", $this->td), $email);
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
                            $note .= "<br />". sprintf(__("Automatic Email sent to following shop managers:<br> %s", $this->td), (count($valid_reciever_shopmngrs) > 1 ? implode(", ", $valid_reciever_shopmngrs) : $valid_reciever_shopmngrs));
                        } else {
                            $note .= "<br />". sprintf(__("An error occured sending Automatic Email to following shop managers:<br> %s", $this->td), (count($valid_reciever_shopmngrs) > 1 ? implode(", ", $valid_reciever_shopmngrs) : $valid_reciever_shopmngrs));
                        }
                    } else {
                        $note .= "<br />". __("No Shop Manager selected to send Automatic Email to.", $this->td);
                    }
                }
            }
            if (!empty($note)) {
                $order->add_order_note($note);
            }
        }
        /**
         * send mail using pepro ultinate invoice core
         *
         * @method send_formatted_email
         * @param int $order_id
         * @param string $email
         * @param boolean $attach
         * @return string wp_mail status
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function send_formatted_email($order_id, $email, $attach)
        {
            add_filter("puiw_printinvoice_return_html_minfied", "__return_false", 10, 1);
            $invc = $this->print->create_html($order_id, "HTML", "", true, true);
            $cssToInlineStyles = new CssToInlineStyles($invc);
            $cssToInlineStyles->setCleanup(true);
            $cssToInlineStyles->setUseInlineStylesBlock(true);
            $invc = $cssToInlineStyles->convert();
            $subject = apply_filters("puiw_email_invoice_customer_subject", sprintf($this->tpl->get_email_subject(_x("Order #%s invoice on ", "wc-setting", $this->td) . get_bloginfo('name', 'display')), $order_id));
            $PDFattachments = false;
            if ($attach) {
              $PDFattachments = true;
              $namedir = PEPROULTIMATEINVOICE_DIR . "/pdf_temp";
              $namedir = apply_filters("puiw_get_default_mail_pdf_temp_path", $namedir);
              $invcPDF = $this->print->create_pdf($order_id, false, "S", false);
              $attachments = array("$namedir/$invcPDF");
            }
            $wp_mail = wp_mail($email, $subject, $invc, array('Content-Type: text/html; charset=UTF-8', "From: {$this->tpl->get_email_from_name()} <{$this->tpl->get_email_from_address()}>"), $attachments);
            if ($PDFattachments) {
                unlink("$namedir/$invcPDF");
            }
            return $wp_mail;
        }
        public function pwoosms_shortcodes_list($shortcodes)
        {
          return "<br><strong>{$this->title}</strong><br><code>{track_id}</code> = " . __("Shipping Track Serial", $this->td) . "<br>" . PHP_EOL . $shortcodes;
        }
        public function sms_body_after_replace($content, $order_id, $order, $all_product_ids, $vendor_product_ids)
        {
          $track_id = get_post_meta($order_id, "_shipping_puiw_invoice_track_id", true);
          $content  = str_replace("{track_id}", $track_id, $content);
          return $content;
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function die($preTitle = "", $title = "ERR", $msg = "")
        {
            $preTitle2 = "user-guest";
            $preTitle2 = (get_current_user_id() > 0) ? ("user-" . get_current_user_id()) : $preTitle2;
            $ext = "
              @font-face { font-family: 'bodyfont'; font-style: normal; font-weight: normal; src: url('".PEPROULTIMATEINVOICE_URL."/assets/css/96594ad4.woff') format('woff'); }
              @font-face { font-family: 'bodyfont'; font-style: normal; font-weight: bold; src: url('".PEPROULTIMATEINVOICE_URL."/assets/css/96594ad5.woff') format('woff'); }";
            $html = '<title>'. $title .'</title><!--ERR: '.$preTitle.' --><style type="text/css">'.$ext.
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
                  <body id="error-page '."$preTitle $preTitle2".'"><div class="wp-die-message">'.$msg.'</div></body></html>';
            die($html);
        }
        /**
         * Initiate plugin with init hook
         *
         * @method  init_plugin
         * @version 1.1.0
         * @since   1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function init_plugin()
        {
            // add compatibility with WPC Product Bundles for WooCommerce By WPClever
            // Since v. 1.3.5
            if (class_exists('WPCleverWoosb') && function_exists('WPCleverWoosb')) {
                $WPCleverWoosb = WPCleverWoosb();
                remove_action("woocommerce_before_order_itemmeta", array( $WPCleverWoosb, 'woosb_before_order_item_meta'), 10);
                remove_action('woocommerce_order_formatted_line_subtotal', array( $WPCleverWoosb, 'woosb_order_formatted_line_subtotal' ), 10, 2);
                // remove_all_actions("woocommerce_before_order_itemmeta", 10);
            }

            // public methods to get receipts 
            if (isset($_GET["invoice"]) && !empty(trim(sanitize_text_field($_GET["invoice"])))) {
              $order_id = (int) trim(sanitize_text_field($_GET["invoice"]));
              if (wc_get_order($order_id)){ die($this->print->create_html($order_id)); }
            }
            if (isset($_GET["invoice-pdf"]) && !empty(trim(sanitize_text_field($_GET["invoice-pdf"])))) {
              $force_download = false;
              if (isset($_GET["download"]) && !empty(sanitize_text_field($_GET["download"]))) { $force_download = true; }
              die($this->print->create_pdf((int) trim(sanitize_text_field($_GET["invoice-pdf"])), $force_download));
            }
            if (isset($_GET["invoice-slips"]) && !empty(trim(sanitize_text_field($_GET["invoice-slips"])))) {
              die($this->print->create_slips((int) trim(sanitize_text_field($_GET["invoice-slips"]))));
            }
            if (isset($_GET["invoice-slips-pdf"]) && !empty(trim(sanitize_text_field($_GET["invoice-slips-pdf"])))) {
              $force_download = false;
              if (isset($_GET["download"]) && !empty(sanitize_text_field($_GET["download"]))) { $force_download = true; }
              die($this->print->create_slips_pdf((int) trim(sanitize_text_field($_GET["invoice-slips-pdf"])), $force_download));
            }
            if (isset($_GET["invoice-inventory"]) && !empty(trim(sanitize_text_field($_GET["invoice-inventory"])))) {
              die($this->print->create_inventory((int) trim(sanitize_text_field($_GET["invoice-inventory"]))));
            }


            // admin methods for bulk actions and setting export/import
            if (is_admin() && !is_ajax() && current_user_can("manage_options")) {
              if (isset($_GET["invoice-zip"]) && !empty(trim(sanitize_text_field($_GET["invoice-zip"])))) {
                $pdfs = array();
                $orderidsvalid = array();
                $orderidsname = array();
                $orderids = (array) explode(",", $_GET["invoice-zip"]);
                $orderids = array_map("trim", $orderids);
                $invoicename_parts = apply_filters( "puiw_generate_pdf_name_orderid_format", array(
                  "invoice_prefix"=> $this->tpl->get_invoice_prefix(),
                  "invoice_suffix"=> $this->tpl->get_invoice_suffix(),
                  "invoice_start"=> $this->tpl->get_invoice_start(),
                ));
  
                foreach ( $orderids as $order_id) {
                  $order = wc_get_order($order_id);
                  if ($order) {
                    $orderidsvalid[] = $order_id;
                    $invoice_startpad = $invoicename_parts["invoice_start"]+$order_id;
                    $pdf_temp = $this->print->create_pdf((int)$order_id, false, "S", false);
                    if(file_exists(PEPROULTIMATEINVOICE_DIR . "/pdf_temp/$pdf_temp")){
                      $pdfs[] = PEPROULTIMATEINVOICE_DIR . "/pdf_temp/$pdf_temp";
                    }
                    $orderidsname[] = "FILE: $pdf_temp".PHP_EOL.
                    "MD5: " . md5_file(PEPROULTIMATEINVOICE_DIR . "/pdf_temp/$pdf_temp").PHP_EOL.
                    "SHA1: " . sha1_file(PEPROULTIMATEINVOICE_DIR . "/pdf_temp/$pdf_temp").PHP_EOL;
                  }
                }
  
                $order_id_formatted = implode("-", $orderidsvalid);
                $datenow = current_time('timestamp');
                $datetime = date_i18n("Y_m_d_H_i_s", $datenow);
                $namedir = PEPROULTIMATEINVOICE_DIR . "/zip_temp";
                if (!file_exists( $namedir )) { mkdir($namedir, 0777, true); }
  
                $namedotext = "InvoicesArchive-$datetime.zip";
                $zip_file = "{$namedir}/{$namedotext}";
  
                file_exists($zip_file) AND unlink($zip_file);
  
                $zip = new \ZipArchive;
  
                if ($zip->open($zip_file, $zip::CREATE|$zip::OVERWRITE) === TRUE) {
                  foreach ($pdfs as $file) { $zip->addFile($file, basename($file)); }
                  $lineone = "WooCommerce PDF Invoices Zipped Archive File";
                  $linetwo = "Created by Pepro Ultimate Invoice for WooCommerce (ver. {$this->version})";
                  $comments_lines = array(
                    PHP_EOL.str_pad($lineone, strlen($linetwo)+8, " ", STR_PAD_BOTH).PHP_EOL,
                    str_pad($linetwo, strlen($linetwo)+8, " ", STR_PAD_BOTH).PHP_EOL,
                    str_pad("https://wordpress.org/plugins/pepro-ultimate-invoice/", strlen($linetwo)+8, " ", STR_PAD_BOTH).PHP_EOL,
                    str_pad("Developed by Pepro Dev ( https://pepro.dev/ )", strlen($linetwo)+8, " ", STR_PAD_BOTH).PHP_EOL,
                    str_pad("*", strlen($linetwo)+8, "=", STR_PAD_BOTH).PHP_EOL,
                    "Date: " . $this->tpl->get_date("now"),
                    "Generated on: {$this->tpl->get_store_name()} ({$this->tpl->get_store_website()})",
                    "Support Email: {$this->tpl->get_store_email()}".PHP_EOL,
                    str_pad("*", strlen($linetwo)+8, "=", STR_PAD_BOTH).PHP_EOL,
                    "This archive should contain following Invoice PDF files:".PHP_EOL,
                    implode(PHP_EOL, $orderidsname).PHP_EOL,
                  );
                  $zip->setArchiveComment( implode(PHP_EOL, $comments_lines));
                  $zip->close();
                } else {
                  wp_die( sprintf(__("Failed creating zip archive! (code: %s) %s",$this->td), $zip->status, "<br>".$zip->getStatusString()), __("Error",$this->td), array("back_link"=> true));
                  exit;
                }
                foreach ($pdfs as $file) { file_exists($file) AND unlink($file); }
                header('Content-type: application/force-download');
                header("Content-Disposition: attachment; filename=$namedotext");
                readfile($zip_file);
                exit;
              }
              if (isset($_GET["inventory-bulk"]) && !empty(trim(sanitize_text_field($_GET["inventory-bulk"])))) {
                $orderids = (array) explode(",", $_GET["inventory-bulk"]);
                $orderids = array_map("trim", $orderids);
                $inventory_temp = "";
                foreach ( $orderids as $order_id) {
                  $order = wc_get_order($order_id);
                  if ($order) {
                    $inventory_temp .= "<iframe
                    onload=\"this.style.width='100%';this.style.height = 0; this.style.height=(this.contentWindow.document.body.scrollHeight+20)+'px';this.contentWindow.document.body.getElementsByTagName('p')[0].style.display='none'; \"
                    frameborder='0' scrolling='no' src='".home_url("?invoice-inventory=$order_id")."' title='OrderID#$order_id'></iframe>";
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
                $printBtn .= "<p style='text-align: center'><a class=\"print-button\" href=\"javascript:;\" onclick=\"window.print();return false;\" >".__("Print",$this->td)."</a></p>";
                die("<title>".__("Bulk Inventory Report",$this->td)."</title>{$printBtn}{$inventory_temp}");
              }
              if (isset($_GET["slips-bulk"]) && !empty(trim(sanitize_text_field($_GET["slips-bulk"])))) {
                $orderids = (array) explode(",", $_GET["slips-bulk"]);
                $orderids = array_map("trim", $orderids);
                $inventory_temp = "";
                foreach ( $orderids as $order_id) {
                  $order = wc_get_order($order_id);
                  if ($order) {
                    $inventory_temp .= "<iframe
                    onload=\"this.style.width='100%';this.style.height = 0; this.style.height=(this.contentWindow.document.body.scrollHeight+20)+'px';this.contentWindow.document.body.getElementsByTagName('p')[0].style.display='none'; \"
                    frameborder='0' scrolling='no' src='".home_url("?invoice-slips=$order_id")."' title='OrderID#$order_id'></iframe>";
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
                $printBtn .= "<p style='text-align: center'><a class=\"print-button\" href=\"javascript:;\" onclick=\"window.print();return false;\" >".__("Print",$this->td)."</a></p>";
                die("<title>".__("Bulk Packing Slip",$this->td)."</title>{$printBtn}{$inventory_temp}");
              }
            }

            if (current_user_can("manage_options") && is_admin() && isset($_GET["nonce"], $_GET["ultimate-invoice-reset"]) && wp_verify_nonce($_GET["nonce"], "pepro-ultimate-invoice") ) {
              wp_die("Pepro Ultimate Invoice — FORCE RESET TO DEFAULT Settings done!<br />Return count: " . $this->change_default_settings("RESET") , "Force-reset Options", array("link_text"=> __("Back to Settings",$this->td), "link_url" => admin_url( "admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=migrate" ), "text_direction" => "ltr"));
            }
            if (current_user_can("manage_options") && is_admin() && isset($_GET["nonce"], $_GET["ultimate-invoice-set"]) && wp_verify_nonce($_GET["nonce"], "pepro-ultimate-invoice") ) {
              wp_die("Pepro Ultimate Invoice — RESET TO DEFAULT Settings done!<br />Return count: " . $this->change_default_settings("SET") , "Reset Options", array("link_text"=> __("Back to Settings",$this->td), "link_url" => admin_url( "admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=migrate" ), "text_direction" => "ltr"));
            }
            if (current_user_can("manage_options") && is_admin() && isset($_GET["nonce"], $_GET["ultimate-invoice-clear"]) && wp_verify_nonce($_GET["nonce"], "pepro-ultimate-invoice") ) {
              wp_die("Pepro Ultimate Invoice — FORCE CLEAR Settings done!<br />Return count: " . $this->change_default_settings("CLEAR") , "Clear Options", array("link_text"=> __("Back to Settings",$this->td), "link_url" => admin_url( "admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=migrate" ), "text_direction" => "ltr"));
            }
            if (current_user_can("manage_options") && is_admin() && isset($_GET["nonce"], $_GET["ultimate-invoice-get"]) && wp_verify_nonce($_GET["nonce"], "pepro-ultimate-invoice") ) {
              $string = "<div class='log5'>".highlight_string("<?php".PHP_EOL.$this->change_default_settings("GET"), 1) ."</div><style>.log5{text-align: left; direction: ltr; padding: 1rem; display: block; overflow: auto;z-index: 77777777777 !important;position: relative;background: white;} .log5 code {background: transparent !important;}</style>";
              wp_die($string, "Export as PHP Script", array("link_text"=> __("Back to Settings",$this->td), "link_url" => admin_url( "admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=migrate" ), "text_direction" => "ltr"));
            }

            if ("yes" == $this->tpl->get_allow_quick_shop()) {
                add_shortcode("puiw_quick_shop", array($this, "integrate_with_shortcode"));
                if ($this->_vc_activated()) {
                    add_action("vc_before_init", array($this, "integrate_with_vc"));
                    if (function_exists('vc_add_shortcode_param')) {
                        vc_add_shortcode_param("{$this->td}_about", array($this,'vc_add_pepro_about'), plugins_url("/assets/js/vc.init" . $this->debugEnabled(".js", ".min.js"), __FILE__));
                    }
                }
            }
            add_action("plugin_row_meta"                , array($this, "plugin_row_meta"), 10, 4);
            add_filter("plugin_action_links"            , array($this, "plugin_action_links"), 10, 2);
            add_action("admin_menu"                     , array($this, "admin_menu"), 1000);
            add_action("admin_init"                     , array($this, "admin_init"));
            add_action("wp_ajax_nopriv_puiw_{$this->td}", array($this, "handel_ajax_req"));
            add_action("wp_ajax_puiw_{$this->td}"       , array($this, "handel_ajax_req"));
            add_action("wp_before_admin_bar_render"     , array($this, "wp_before_admin_bar_render"));

            add_filter("pwoosms_shortcodes_list"             , array($this, "pwoosms_shortcodes_list"));
            add_filter("pwoosms_order_sms_body_after_replace", array($this, "sms_body_after_replace"), -1, 5);

            if ("yes" == $this->tpl->get_allow_preorder_invoice()) {
                add_action("woocommerce_proceed_to_checkout",                 array( $this,"woocommerce_after_cart_contents"), 1000);
            }
            if ("yes" == $this->tpl->get_allow_users_use_invoices()) {
                add_filter("woocommerce_my_account_my_orders_actions",        array( $this,'add_view_invoice_button_orderpage'), 10, 2);
            }
            if ("yes" == $this->tpl->get_allow_quick_shop()) {
                add_shortcode("puiw_quick_shop",                              array($this, 'integrate_with_shortcode'));
            }

            add_action('woocommerce_admin_order_data_after_shipping_address', array($this, "after_shipping_shopmngr_provided_note"), 10, 1);
            add_action("woocommerce_order_details_after_order_table_items",   array($this, "woocommerce_order_details_after_order_table_items"));
            add_action('woocommerce_checkout_update_order_meta',              array($this, "woocommerce_checkout_update_order_meta"));
            add_action('woocommerce_checkout_update_user_meta',               array($this, "woocommerce_checkout_update_user_meta"), 10, 2);
            add_filter('woocommerce_checkout_fields',                         array($this, "checkout_fields_add_uin"));
            add_action("woocommerce_order_details_before_order_table",        array($this, "woocommerce_order_details_before_order_table"), -1000);
            add_filter("wc_order_statuses",                                   array($this, "add_wc_order_statuses"));
            add_filter("woocommerce_admin_billing_fields",                    array($this, "woocommerce_admin_billing_fields"));
            add_filter("woocommerce_admin_shipping_fields",                   array($this, "woocommerce_admin_shipping_fields"));
            add_action("woocommerce_checkout_create_order_line_item",         array($this, "prices_as_order_line_item_meta"), 20, 4 );


            // remove previous generated pdf files
            $saved_pdfs = glob(PEPROULTIMATEINVOICE_DIR . "/pdf_temp/*.pdf");
            foreach($saved_pdfs as $pdf){if(is_file($pdf)) {unlink($pdf);}}

            // add_action( 'woocommerce_admin_order_data_after_shipping_address', array($this,'checkout_field_admin_display_uin'), 10, 1 );
            // add_action( 'woocommerce_admin_order_data_after_billing_address', array($this,'checkout_field_admin_display_uin'), 10, 1 );
            // add_action( 'woocommerce_admin_order_data_after_order_details', array($this,'checkout_field_admin_display_odt'), 10, 1 );

            $this->add_wc_prebuy_status();

            if ($this->version != get_option("puiw_last_import_version")){
              $this->change_default_settings("SET");
              update_option("puiw_last_import_version", $this->version);
            }

        }
        public function plugin_row_meta($links_array, $plugin_file_name, $plugin_data, $status)
        {
          if (strpos( $plugin_file_name, basename(__FILE__) ) ) {
            $links_array[] = "<a href='mailto:support+UltimateInvoice@pepro.dev?subject=Ask for Help [Ultimate Invoice]'>".__("Support", $this->td)."</a>";
          }
          return $links_array;
        }
        public function plugin_action_links($actions, $plugin_file)
        {
          if (plugin_basename(__FILE__) == $plugin_file) {
            $actions[$this->db_slug] = "<a href='$this->url'>".__("Settings", $this->td)."</a>";
          }
          return $actions;
        }
        public function make_pdf_file($order_id=0)
        {
          $order = wc_get_order($order_id);
          if (!$order_id || !$order) return false;
          $pdf_temp = $this->print->create_pdf((int) $order_id, false, "S", false);
          $pdf_file = PEPROULTIMATEINVOICE_DIR . "/pdf_temp/$pdf_temp";
          if(file_exists($pdf_file)) return $pdf_file;
          return false;
        }
        public function prices_as_order_line_item_meta( $item, $cart_item_key, $values, $order )
        {
          $product = $item->get_variation_id() > 0 ? $item->get_variation_id() : $item->get_product_id();
          $product = wc_get_product($product);
          $item->update_meta_data('_puiw_regular', $product->get_regular_price());
          $item->update_meta_data('_puiw_sale',    $product->is_on_sale() ? $product->get_sale_price() : $product->get_regular_price());
          $item->update_meta_data('_puiw_html',    $product->get_price_html());
          $item->update_meta_data('_puiw_version', $this->version);
        }
        public function hidden_order_itemmeta($args)
        {
          $args[] = '_puiw_regular';
          $args[] = '_puiw_sale';
          $args[] = '_puiw_html';
          $args[] = '_puiw_version';
          return $args;
        }
        public function woocommerce_order_discount_to_display($total_rows, $order, $tax_display)
        {
          $coupons_code        = "yes" === $this->tpl->get_show_coupons_code_at_totals();
          $coupons_description = "yes" === $this->tpl->get_show_coupons_description_at_totals();
          $coupons_amount      = "yes" === $this->tpl->get_show_coupons_amount_at_totals();
          $coupons_discount    = "yes" === $this->tpl->get_show_coupons_discount_at_totals();
          $coupuns_array       = array();
          $coupons             = $order->get_items('coupon');
          foreach ($coupons as $item) {
            $coupon = new \WC_Coupon($item->get_code());
            if ($coupon) {
              $coupon_label   = _x("Coupon:", "invoice-template", $this->td);
              $coupon_display = [];
              if ($coupons_code) {
                $coupon_label = sprintf(_x("Coupon (%s):", "invoice-template", $this->td), $coupon->get_code());
              }
              if ($coupons_amount) {
                $coupon_display[] = ("percent" == $coupon->get_discount_type() ? $coupon->get_amount() . "%" : wc_price( $coupon->get_amount(), ['currency' => $order->get_currency()]));
              }
              if ($coupons_discount) {
                $coupon_display[] = wc_price( $item->get_discount(), array( 'currency' => $order->get_currency() ) );
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
          $i = 0; $pos = 0;
          foreach ($total_rows as $key => $value) {$i++;
            if ("discount" === $key){$pos = $i; break;}
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function woocommerce_admin_billing_fields($ar)
        {
          if ("yes" == $this->tpl->get_show_user_uin()) {
            $default_UIN = "";
            if (get_current_user_id()) {
              $default_UIN = get_user_meta(get_current_user_id(), "billing_uin", true);
            }
            $ar['puiw_billing_uin'] = array(
              'label'   => __('User Unique Identification Number', $this->td),
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function woocommerce_admin_shipping_fields($ar)
        {
          $ar['puiw_invoice_shipdaterow'] = array(
            "label" => "",
            "name"  => "",
            "style" => "display:none",
            "class" => "long persianDatepickerRow",
          );
          $ar['puiw_invoice_shipdatefa']  = array(
            'label'             => __('Shipped Date (Shamsi)', $this->td),
            'class'             => 'long persianDatepicker disabled',
            'placeholder'       => __('Select Shipped Date', $this->td),
            'custom_attributes' => array("readonly"=>"true"),
          );
          $ar['puiw_invoice_shipdate']    = array(
            'label'             => __('Shipped Date (Gregorian)', $this->td),
            'class'             => 'long persianDatepicker disabled',
            'placeholder'       => __('Select Shipped Date', $this->td),
            'custom_attributes' => array("readonly"=>"true"),
          );
          $ar['puiw_invoice_track_id']    = array(
            'label'       => __('Shipping Track Serial', $this->td),
            'class'       => 'long',
            'placeholder' => __('Enter Shipping Track Serial', $this->td),

          );
          $ar['puiw_customer_signature']  = array(
            'label' => __('Customer Signature', $this->td),
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function woocommerce_order_details_before_order_table($order)
        {
            $allowed_statuses = $this->tpl->get_allow_users_use_invoices_criteria("");
            echo "<div class='puiw_orders_invoice_btn_container'>";
            if (!empty($allowed_statuses) && in_array("wc-{$order->get_status()}", (array) $allowed_statuses)) {
                if ($this->print->has_access("HTML", $order)) {
                    echo '<a style="margin-inline-end: 1rem;-webkit-margin-end: 1rem;" href="'.home_url("?invoice=".$order->get_order_number()).'" class="button">'._x('View Invoice', "order-page", $this->td).'</a>';
                }
                if ($this->print->has_access("PDF", $order)) {
                    echo '<a href="'.home_url("?invoice-pdf=".$order->get_order_number()).'" class="button">'._x('PDF Invoice', "order-page", $this->td).'</a>';
                }
            }
            echo "</div>";
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function puiw_get_default_dynamic_params($opts, $order)
        {
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function woocommerce_order_details_after_order_table_items($order)
        {
            $order_id = $order->get_id();
            $date = get_post_meta($order_id, "_shipping_puiw_invoice_shipdate", true);
            $serial = get_post_meta($order_id, "_shipping_puiw_invoice_track_id", true);
            if ("yes" == $this->tpl->show_shipped_date()) {
                echo '<tr><td><strong>'._x("Shipped Date:", "wc-orders", $this->td).' </strong></td><td>'. $this->tpl->get_date($date, "Y/m/d l", true) .'</td></tr>';
            }
            if ("yes" == $this->tpl->show_shipping_serial()) {
                echo '<tr><td><strong>'._x("Shipping tracking Serial:", "wc-orders", $this->td).' </strong></td><td>'. $serial .'</td></tr>';
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function attach_pdf_to_wC_emails( $attachments, $email_id, $order )
        {
          // Avoiding errors and problems
          if ( ! is_a( $order, 'WC_Order' ) || ! isset( $email_id ) ) {
            return $attachments;
          }
          $invcPDF = $this->print->create_pdf($order->get_id(), false, "S", false);
          if (!$invcPDF) { return $attachments; }
          $namedir       = PEPROULTIMATEINVOICE_DIR . "/pdf_temp";
          $namedir       = apply_filters("puiw_get_default_mail_pdf_temp_path", $namedir);
          $attachments[] = "$namedir/$invcPDF";
          return $attachments;
        }
        /**
         * add metabox to cpts
         *
         * @method add_meta_boxes
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function add_meta_boxes()
        {
          $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		        ? wc_get_page_screen_id( 'shop-order' )
		        : 'shop_order';
          add_meta_box( $this->td, $this->title, array( $this, 'wc_shop_order_metabox' ), $screen, 'side', 'high' );
        }
        /**
         * wc orders screen on save
         *
         * @method wc_save_shop_order_metabox
         * @param int $post_id
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function wc_save_shop_order_metabox($post_id)
        {
            if (
              !isset($_POST["{$this->td}_nonce"]) ||
              !wp_verify_nonce($_POST["{$this->td}_nonce"], 'security_nonce') ||
              !current_user_can('edit_post') ||
              (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            ) {
                return;
            }


            if (!isset($_POST['puiw_shopmngr_provided_note'])) {
                delete_post_meta($post_id, 'puiw_shopmngr_provided_note');
            } else {
                update_post_meta($post_id, 'puiw_shopmngr_provided_note', ($_POST['puiw_shopmngr_provided_note']));
                // update_post_meta( $post_id, 'puiw_shopmngr_provided_note', sanitize_textarea_field( $_POST['puiw_shopmngr_provided_note'] ));
            }


            if (!isset($_POST['_billing_puiw_billing_uin'])) {
                delete_post_meta($post_id, 'puiw_billing_uin');
            } else {
                update_post_meta($post_id, 'puiw_billing_uin', sanitize_text_field($_POST['_billing_puiw_billing_uin']));
            }

            if (!isset($_POST['_shipping_puiw_invoice_track_id'])) {
                delete_post_meta($post_id, 'puiw_invoice_track_id');
            } else {
                update_post_meta($post_id, 'puiw_invoice_track_id', sanitize_text_field($_POST['_shipping_puiw_invoice_track_id']));
            }
        }
        /**
         * read css file header and info
         *
         * @method parseTemplate
         * @param string $contents css content
         * @return array header info
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        private function parseTemplate($contents)
        {
            preg_match('!/\*[^*]*\*+([^/][^*]*\*+)*/!', $contents, $themeinfo);
            $ss = str_ireplace(array("\n"), "|", $themeinfo[0]);
            $ss = substr($ss, 4, -3);
            $ss = str_ireplace(array("\n","\r\n","\r"), "", $ss);
            $styleExifDAta = array();
            foreach (explode("|", $ss) as $tt) {
                $uu = explode(":", $tt);
                $styleExifDAta[strtolower($uu[0])] = substr($uu[1], 1);
            }
            return $styleExifDAta;
        }
        /**
        * return array list of available templates
        *
        * @method load_themes
        * @param boolean $advanced return advanced or simple
        * @return array template info
        * @version 1.1.0
        * @since 1.0.0
        * @license https://pepro.dev/license Pepro.dev License
        */
        public function load_themes($advanced=false)
        {
            $tempaltes = array();
            $tempaltesInfo = array();
            $styleFiles = glob(PEPROULTIMATEINVOICE_DIR ."template/*/default.cfg");
            $styleFiles = apply_filters("puiw_get_templates_list", $styleFiles);
            foreach ($styleFiles as $style) {
                $contents                = file_get_contents($style);
                $styleExifDAta           = $this->parseTemplate($contents);
                $template                = sprintf(_x('%1$s — by %2$s', "theme-name", $this->td), $styleExifDAta["name"], $styleExifDAta["designer"]);
                $simple_path             = apply_filters("puiw_load_themes_simple_path", dirname($style), $style);
                $simple_title            = apply_filters("puiw_load_themes_simple_title", $template, $styleExifDAta["name"], $styleExifDAta["designer"], $styleExifDAta);
                $tempaltes[$simple_path] = $simple_title;
                $tempaltesInfoTemp       = array(
                  "title"   => $template,
                  "name"    => $styleExifDAta["name"],
                  "author"  => $styleExifDAta["designer"],
                  "version" => $styleExifDAta["version"],
                  "folder"  => basename(dirname($style)),
                  "path"    => dirname($style),
                  "url"     => plugin_dir_url($style),
                  "icon"    => trailingslashit(plugin_dir_url($style))."screenshot.png",
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function wc_shop_order_metabox($post){
          
          if (OrderUtil::is_order($post, wc_get_order_types() )  && is_a($post, "WC_Order") ) {
            $order_id = $post->get_id();
          }else{
            $order_id = isset($post->ID) ? $post->ID : $post;
          }

            $puiw_billing_uin = get_post_meta($order_id, 'puiw_billing_uin', true);
            $_billing_puiw_billing_uin = get_post_meta($order_id, '_billing_puiw_billing_uin', true);
            $puiw_invoice_track_id = get_post_meta($order_id, 'puiw_invoice_track_id', true);

            wp_nonce_field('security_nonce', "{$this->td}_nonce");

            wp_enqueue_script("jquery-confirm", "{$this->assets_url}js/jquery-confirm.min.js", array("jquery"));
            wp_enqueue_style("jquery-confirm", "{$this->assets_url}css/jquery-confirm.min.css", array(), '1.0', 'all');
            wp_enqueue_style("fontawesome", "{$this->assets_url}fontawesome-free/css/all.css", array(), '5.15.3', 'all');

            $localize_script = (new PeproUltimateInvoice_Columns())->localize_script();
            wp_register_script("pepro-ultimate-invoice-orders-options", "{$this->assets_url}/admin/wc_orders" . $this->debugEnabled(".js", ".min.js"), array("jquery"), current_time('timestamp'));
            wp_localize_script("pepro-ultimate-invoice-orders-options", "_i18n", array_merge($localize_script, array(
                "calendarType"        => ($this->tpl->get_date_shamsi()=="yes") ? "persian" : "gregorian",
                "prev_img_url"        => get_post_meta($order_id, '_shipping_puiw_customer_signature', true),
                "shipping_procc"      => __("Select The Date Product Shipped", $this->td),
                "shipping_clear"      => __("Clear", $this->td),
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
            $current  = $this->tpl->get_template();
            echo '<template id="puiw_DateSelectorContainer" style="display:none;"><div id="puiw_DateContainer" data-date="" style="width: 100%;"></div></template>';

            echo ""; ?>
            <p>
              <input type="checkbox" value="1" id="puiwc_advanced_opts" />
              <label for="puiwc_advanced_opts"><?php echo __("Use Advanced Options?", $this->td); ?></label>
            </p>
            <div class="advabced_puiwc" style="display: none;">
              <div class="puiw_half">
                <label style="color: gray;"><?php echo __("Force Use theme", $this->td); ?></label>
                <select style="width: 100%;" id="puiw_metabox_theme_select" class='jqui-select' selecteditem='<?php echo $current; ?>'></select>
              </div>
              <div class="puiw_half">
                <label style="color: gray;"><?php echo __("Primary, Secondary and Tertiary Colors", $this->td); ?></label>
                <select style="width: 100%;" id="puiw_metabox_swatch_select" class="swatch-select" swatches="<?php echo esc_js(get_option("puiw_color_swatches", "")); ?>"></select>
              </div>
              <div class="puiw-color-pickers">
                  <input type="text" id="puiw_metabox_theme_color" value="<?php echo get_option("puiw_theme_color"); ?>" class="wc-color-picker"/>
                  <input type="text" id="puiw_metabox_theme_color2" value="<?php echo get_option("puiw_theme_color2"); ?>" class="wc-color-picker"/>
                  <input type="text" id="puiw_metabox_theme_color3" value="<?php echo get_option("puiw_theme_color3"); ?>" class="wc-color-picker"/>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' title='<?php echo _x("Reset Advanced Options to default", "wc-orders-popup", $this->td); ?>' class='pwui_reset_advanced' href='#'><?php echo _x("Reset", "wc-orders-popup", $this->td); ?></a>
              </div>
            </div>
            <p><hr></p>
            <div class="puiw_half_container">
              <div class="puiw_half">
                <a rel='puiw_tooltip' data-action='puiw_act_href'
                title='<?php echo _x("View Order HTML Invoice", "wc-orders-popup", $this->td); ?>' class='button button-primary pwui_opts btn-wide' href='<?php echo $url1; ?>'
                target='_blank' data-ref='<?php echo $id; ?>'><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/document.png'/>" .
                _x("HTML Invoice", "wc-orders-popup", $this->td); ?></a>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' data-action='puiw_act_href'
                title='<?php echo _x("View Order PDF Invoice", "wc-orders-popup", $this->td); ?>' class='button button-primary pwui_opts btn-wide' href='<?php echo $url2; ?>'
                target='_blank' data-ref='<?php echo $id; ?>'><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/pdf.png'/>" .
                _x("PDF Invoice", "wc-orders-popup", $this->td); ?></a>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' data-action='puiw_act_href'
                title='<?php echo _x("View Order Inventory report", "wc-orders-popup", $this->td); ?>' class='button button-primary pwui_opts btn-wide' href='<?php echo $url4; ?>'
                target='_blank' data-ref='<?php echo $id; ?>'><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/document-delivery.png'/>" .
                _x("Inventory report", "wc-orders-popup", $this->td); ?></a>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' data-action='puiw_act_href'
                title='<?php echo _x("View Packing Slip for shipping", "wc-orders-popup", $this->td); ?>' class='button button-primary pwui_opts btn-wide' href='<?php echo $url3; ?>'
                target='_blank' data-ref='<?php echo $id; ?>'><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/unpacking.png'/>" .
                _x("Packing Slip", "wc-orders-popup", $this->td); ?></a>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' data-action='puiw_act_href'
                title='<?php echo _x("View PDF Packing Slip for shipping", "wc-orders-popup", $this->td); ?>' class='button button-primary pwui_opts btn-wide' href='<?php echo $url11; ?>'
                target='_blank' data-ref='<?php echo $id; ?>'><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/unpacking.png'/>" .
                _x("PDF Packing Slip", "wc-orders-popup", $this->td); ?></a>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' data-action='puiw_act6' title='<?php echo _x("Mail Order Invoice to Customer", "wc-orders-popup", $this->td); ?>'
                  class='button button-primary pwui_opts btn-wide' href='<?php echo $url2; ?>' target='_blank' data-ref='<?php echo $id; ?>'><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/mail-account.png'/>" .
                  _x("Mail Invoice to Customer", "wc-orders-popup", $this->td); ?>
                </a>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' data-action='puiw_act9' title='<?php echo _x("Mail Order Invoice to Shop Managers", "wc-orders-popup", $this->td); ?>'
                  class='button button-primary pwui_opts btn-wide' href='<?php echo $url2; ?>' target='_blank' data-ref='<?php echo $id; ?>'><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/secure-mail.png'/>" .
                  _x("Mail Invoice to Shop Managers", "wc-orders-popup", $this->td); ?>
                </a>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' data-action='puiw_act10' title='<?php echo _x("Mail Order Invoice to Custom List", "wc-orders-popup", $this->td); ?>'
                  class='button button-primary pwui_opts btn-wide' href='<?php echo $url2; ?>' target='_blank' data-ref='<?php echo $id; ?>'><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/new-message.png'/>" .
                  _x("Mail Invoice to Custom List", "wc-orders-popup", $this->td); ?>
                </a>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' title='<?php echo _x("Edit User Unique Identification Number", "wc-orders-popup", $this->td); ?>'
                  class="button button-primary pwui_opts btn-wide type2" href="#" id="editpuiw_billing_uin"><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/writer-male.png'/>" .
                _x("Edit User UIN", "wc-orders-popup", $this->td); ?></a>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' title='<?php echo _x("Edit transaction ID", "wc-orders-popup", $this->td); ?>'
                  class="button button-primary pwui_opts btn-wide type2" href="#" id="editpuiw_billing_transaction_id"><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/receipt-and-change.png'/>" .
                _x("Edit Transaction ID", "wc-orders-popup", $this->td); ?></a>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' title='<?php echo _x("Edit shipped date", "wc-orders-popup", $this->td); ?>'
                  class="button button-primary pwui_opts btn-wide type2" href="#" id="editpuiw_invoice_shipdate"><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/delivery.png'/>" .
                _x("Edit Shipped Date", "wc-orders-popup", $this->td); ?></a>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' title='<?php echo _x("Edit shipping track serial", "wc-orders-popup", $this->td); ?>'
                  class="button button-primary pwui_opts btn-wide type2" href="#" id="editpuiw_invoice_track_id"><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/in-transit.png'/>" .
                _x("Edit Shipping Track Serial", "wc-orders-popup", $this->td); ?></a>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' title='<?php echo _x("Edit customer signature image", "wc-orders-popup", $this->td); ?>'
                  class="button button-primary pwui_opts btn-wide type2" href="#" id="editpuiw_invoice_customer_signature"><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/sign-up.png'/>" .
                _x("Edit Customer Signature", "wc-orders-popup", $this->td); ?></a>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' title='<?php echo _x("Edit customer provided note", "wc-orders-popup", $this->td); ?>'
                  class="button button-primary pwui_opts btn-wide type2" href="#" id="editpuiw_invoice_customer_note"><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/pencil--v2.png'/>" .
                _x("Edit Customer Note", "wc-orders-popup", $this->td); ?></a>
              </div>
              <div class="puiw_half">
                <a rel='puiw_tooltip' title='<?php echo _x("Edit shop manager provided note", "wc-orders-popup", $this->td); ?>'
                  class="button button-primary pwui_opts btn-wide type2" href="#" id="editpuiw_invoice_shop_manager_note"><?php echo "<img src='".PEPROULTIMATEINVOICE_ASSETS_URL."/img/pencil--v2.png'/>" .
                _x("Edit Shop manager Note", "wc-orders-popup", $this->td); ?></a>
              </div>
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function after_shipping_shopmngr_provided_note($order)
        {
            global $post;
            $value_raw = get_post_meta($order->get_id(), "puiw_shopmngr_provided_note", true);
            $value = wp_kses_post(nl2br(wptexturize($value_raw)));
            echo "
            <p class=\"form-field form-field-wide puiw_shopmngr_provided_note preview\">
              <strong style=\"display: block;\">".__('Shop Manager provided note:', $this->td)."</strong>
              $value
            </p>
            <p class=\"form-field form-field-wide puiw_shopmngr_provided_note edit\">
              <label style=\"display:none;\" for=\"puiw_shopmngr_provided_note\">".__('Shop Manager provided note:', $this->td)."</label>
              <textarea style=\"display:none;\" rows=\"3\" cols=\"40\" name=\"puiw_shopmngr_provided_note\" id=\"puiw_shopmngr_provided_note\" placeholder=\"".__('Enter Shop Manager provided note here', $this->td)."\">$value_raw</textarea>
            </p>
          ";
        }
        /**
         * save wc checkout metas
         *
         * @method woocommerce_checkout_update_order_meta
         * @param int $order_id
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function woocommerce_checkout_update_order_meta($order_id)
        {
            if (! empty($_POST['puiw_billing_uin'])) {
                update_post_meta($order_id, 'puiw_billing_uin', sanitize_text_field($_POST['puiw_billing_uin']));
                update_post_meta($order_id, '_billing_puiw_billing_uin', sanitize_text_field($_POST['puiw_billing_uin']));
            }
        }
        /**
         * save wc checkout user meta
         *
         * @method woocommerce_checkout_update_user_meta
         * @param int $order_id
         * @version 1.0.0
         * @since 1.1.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function woocommerce_checkout_update_user_meta($customer_id, $posted)
        {
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function checkout_fields_add_uin($fields)
        {
            if ("yes" == $this->tpl->get_show_user_uin()) {
                $default_UIN = "";
                if (get_current_user_id()) {
                    $default_UIN = get_user_meta(get_current_user_id(), "billing_uin", true);
                }

                $fields['billing']['puiw_billing_uin'] = array(
                  'label'       => _x("Unique Identification Number", "wc-order-screen", $this->td),
                  'placeholder' => _x("Unique Identification Number", "wc-order-screen", $this->td),
                  'required'    => true,
                  'default'     => $default_UIN,
                  'class'       => array( 'form-row-wide' ),
                  'clear'       => true
                );
            }
            return $fields;
        }
        /**
         * enqueue scripts and styles on admin side
         *
         * @method admin_enqueue_scripts
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function admin_enqueue_scripts()
        {
            wp_register_style("{$this->td}-dmy", false);
            wp_enqueue_style("{$this->td}-dmy");
            $plugin_settinng_url = admin_url('admin.php?page=wc-settings&tab=pepro_ultimate_invoice');
            wp_add_inline_style("{$this->td}-dmy", "#wpadminbar #wp-admin-bar-puiw_toolbar_setting_btn a .ab-icon::before { font-family: WooCommerce !important; content: '\\e03d'; font-size: smaller;}
              #wpadminbar #wp-admin-bar-puiw_toolbar_dash_btn a .ab-icon::before {content: \"\\f340\";top: 3px; }
              .nav-tab-wrapper.woo-nav-tab-wrapper a.nav-tab[href='$plugin_settinng_url'] {display:none;}");
        }
        /**
         * add a link to the WP Toolbar and remove all others
         *
         * @method  custom_toolbar_link
         * @param   object $wp_admin_bar
         * @return  object wp_toolbar
         * @version 1.0.0
         * @since   1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function wp_before_admin_bar_render_back()
        {
            global $wp_admin_bar;
            foreach ($wp_admin_bar->get_nodes() as $key => $value) {
                $wp_admin_bar->remove_menu($key);
            }
            $wp_admin_bar->add_menu(
                array(
                  'id'    => 'puiw_toolbar_dash_btn',
                  'title' => '<span class="ab-icon"></span>' . __("Back to Dashboard", $this->td),
                  'href'  => admin_url()
                )
            );
            $wp_admin_bar->add_menu(
                array(
                  'id'    => 'puiw_toolbar_dark_btn',
                  'title' => __("Switch Dark-mode", $this->td),
                  'href'  => admin_url()
                )
            );
            $wp_admin_bar->add_menu(
                array(
                  'id'    => 'puiw_toolbar_showids',
                  'title' => __("Show IDs", $this->td),
                  'href'  => "#"
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
                  'title' => __("Orders", $this->td),
                  'href'  => admin_url("edit.php?post_type=shop_order")
                )
            );
            $wp_admin_bar->add_menu(
                array(
                  'id'    => 'puiw_toolbar_wc_setting',
                  'title' => __("Settings", $this->td),
                  'href'  => admin_url("admin.php?page=wc-settings")
                )
            );
            $wp_admin_bar->add_menu(
                array(
                  'id'    => 'puiw_toolbar_wc_checkout',
                  'title' => __("Payment", $this->td),
                  'href'  => admin_url("admin.php?page=wc-settings&tab=checkout")
                )
            );
            $wp_admin_bar->add_menu(
                array(
                  'id'    => 'puiw_toolbar_wc_shipping',
                  'title' => __("Shipping", $this->td),
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
                  'title' => __("&#x2B50; Rate us",$this->td),
                  'href'  => "https://wordpress.org/support/plugin/pepro-ultimate-invoice/reviews/#new-post",
                )
            );
            $wp_admin_bar->add_menu(
                array(
                  'id'    => 'puiw_toolbar_wc_bug',
                  'title' => __("&#x1FAB2; Report problem", $this->td),
                  'href'  => 'https://wordpress.org/support/plugin/pepro-ultimate-invoice/#new-post'
                )
            );
            $wp_admin_bar->add_menu(
                array(
                  'id'    => 'puiw_toolbar_wc_about',
                  'title' => __("&#x2139;&#xFE0F; About", $this->td),
                  'href'  => 'https://peprodev.com/pepro-woocommerce-ultimate-invoice/'
                )
            );
        }
        /**
         * add a link to the WP Toolbar
         *
         * @method  custom_toolbar_link
         * @param   object $wp_admin_bar
         * @return  object wp_toolbar
         * @version 1.0.0
         * @since   1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function wp_before_admin_bar_render()
        {
            global $wp_admin_bar;
            if (is_admin()) {
                $wp_admin_bar->add_menu(
                    array(
                      'id'    => 'puiw_toolbar_setting_btn',
                      'title' => '<span class="ab-icon"></span>' . $this->title,
                      'href'  => admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice")
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function add_wc_prebuy_status()
        {
            register_post_status('wc-prebuy-invoice', array(
                  'label'                     => __('Pre-buy Invoice', $this->td),
                  'public'                    => true,
                  'exclude_from_search'       => false,
                  'show_in_admin_all_list'    => true,
                  'show_in_admin_status_list' => true,
                  'label_count'               => _n_noop('Pre-buy Invoice (%s)', 'Pre-buy Invoices (%s)', $this->td)
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function has_access_print($mode = "HTML", $order=false)
        {
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function add_view_invoice_button_orderpage($actions, $order)
        {
            $allowed_statuses = $this->tpl->get_allow_users_use_invoices_criteria("");

            if (!empty($allowed_statuses) && in_array("wc-{$order->get_status()}", (array) $allowed_statuses)) {
                if ($this->print->has_access("HTML", $order)) {
                    $actions["{$this->td}_html"] = array( 'url'  => home_url("?invoice=".$order->get_order_number()), 'name' => _x('View Invoice', "order-page", $this->td), );
                }

                if ($this->print->has_access("PDF", $order)) {
                    $actions["{$this->td}_pdf"] = array( 'url'  => home_url("?invoice-pdf=".$order->get_order_number()), 'name' => _x('PDF Invoice', "order-page", $this->td), );
                }
            }

            return apply_filters("pepro_ultimate_invoice_orders_action", $actions, $order, $allowed_statuses, $this->print->has_access("HTML", $order), $this->print->has_access("PDF", $order) );
        }
        /**
         * add pre-order invoice status to woocommerce
         *
         * @method  add_wc_order_statuses
         * @param   array $order_statuses
         * @version 1.1.0
         * @since   1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function add_wc_order_statuses($order_statuses)
        {
            $new_order_statuses = array();
            foreach ($order_statuses as $key => $status) {
                $new_order_statuses[ $key ] = $status;
                if ('wc-processing' === $key) {
                    $new_order_statuses['wc-prebuy-invoice'] = __('Pre-buy Invoice', $this->td);
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function woocommerce_after_cart_contents()
        {
            wp_enqueue_style("$this->td-ml", "$this->assets_url/css/mobileLayer" . $this->debugEnabled(".css", ".min.css"), array(), "1.0.0", "all");
            wp_enqueue_script("$this->td-ml", "$this->assets_url/js/mobileLayer". $this->debugEnabled(".js", ".min.js"), array("jquery","jquery-ui-core"), "1.0.0", true);
            if (!is_user_logged_in()) {
                wp_enqueue_script("$this->td-cart", "$this->assets_url/js/wc.cart.public" . $this->debugEnabled(".js", ".min.js"), array("jquery"), "1.0.0", true);
                wp_localize_script( "$this->td-cart", "_i18n",
                    array(
                      "title"     => _x("Unauthorized Access!", "js-cart-page", $this->td),
                      "okaylabel" => _x("Okay", "js-cart-page", $this->td),
                      "msg"       => sprintf(_x("We are sorry for inconvenience, this feature is only available to logged-in users.<br>Please %sLogin / Register%s.", "js-cart-page", $this->td), "<a href='".get_permalink(get_option('woocommerce_myaccount_page_id'))."' target='_blank'>", "</a>"),
                ));
            } else {
                wp_enqueue_script("$this->td-cart", "$this->assets_url/js/wc.cart.private". $this->debugEnabled(".js", ".min.js"), array("jquery"), "1.0.0", true);
                wp_localize_script( "$this->td-cart", "_i18n", array(
                        "td"        => "puiw_{$this->td}",
                        "okaylabel" => _x("Okay", "js-cart-page", $this->td),
                        "ajax"      => admin_url("admin-ajax.php"),
                        "nonce"     => wp_create_nonce($this->td),
                ));
            }
            echo "<a href=\"\" id=\"pepro-one-page-purchase--submit-invoice\" class=\"checkout-button button alt\"><span class=\"fa fa-spin fa-cog\" style='display: none;'></span>  ".__("Get Pre-buy Invoice", $this->td)."</a>";
        }
        /**
         * Initiate plugins settings in wp options
         *
         * @method  get_setting_options
         * @return  array settings and sections
         * @version 1.0.0
         * @since   1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function get_setting_options()
        {
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public static function deactivation_hook()
        {
          update_option("peprodev_ultimate_invoice_alert_viewed_yet", "");
        }
        /**
         * run on plugin activation
         *
         * @method  activation_hook
         * @version 1.0.0
         * @since   1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public static function activation_hook()
        {
          flush_rewrite_rules(true);
        }
        /**
         * run on plugin uninstalation
         *
         * @method  uninstall_hook
         * @version 1.0.0
         * @since   1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public static function uninstall_hook()
        {
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function change_default_settings($clear_mode="", $data=array())
        {
            $rtl                                              = is_rtl() ? "-rtl" : "";
            $pdf_font                                         = is_rtl() ? "iranyekanfa" : "dejavu";
            $force_persian_numbers                            = is_rtl() ? "yes" : "no";
            $email_subject                                    = _x("Invoice #%s — ", "wc-setting", $this->td) . get_bloginfo('name', 'display');
            $email_from_name                                  = get_bloginfo('name','display');
            $puiw_store_postcode                              = "";
            if (function_exists("WC")) { $puiw_store_postcode = WC()->countries->get_base_postcode(); }
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
                "puiw_template"                               => PEPROULTIMATEINVOICE_DIR ."template/default{$rtl}",
                "puiw_preinvoice_template"                    => PEPROULTIMATEINVOICE_DIR ."template/default-pre-invoice",
                "puiw_theme_color"                            => "#90caf9",
                "puiw_theme_color2"                           => "#a6d5fc",
                "puiw_theme_color3"                           => "#b5deff",
                "puiw_preinvoice_theme_color"                 => "#faee84",
                "puiw_preinvoice_theme_color2"                => "#fff59d",
                "puiw_preinvoice_theme_color3"                => "#fff8b5",
                "puiw_invoice_title"                          => _x("Invoice %s", "wc-setting", $this->td),
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
                "puiw_dark_mode"                              => "no",
                "puiw_disable_wc_dashboard"                   => "no",
                "puiw_date_format"                            => "Y/m/d H:i",
                "puiw_date_shamsi"                            => "no",
                "puiw_show_shipped_date"                      => "",
                "puiw_show_shipping_serial"                   => "",
                "puiw_force_persian_numbers"                  => $force_persian_numbers,
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
                "puiw_allow_guest_users_view_invoices"        => "",
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
                    $str = "\$my_opts = array( " . PHP_EOL;
                    foreach ($wc_opt as $key => $value) {
                      $str .= "   \"$key\" => \"".get_option($key, "")."\"," . PHP_EOL;
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
                      if ( array_key_exists($key, $wc_opt) ){
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function help_container($hook)
        {
            ob_start();
            wp_enqueue_style("{$this->db_slug}_bkend", "{$this->assets_url}css/backend" . $this->debugEnabled(".css", ".min.css"));
            wp_enqueue_script("{$this->db_slug}_bkend", "{$this->assets_url}/js/settings". $this->debugEnabled(".js", ".min.js"), array('jquery','wp-color-picker'), null, true);
            wp_add_inline_style("{$this->db_slug}_bkend", ".form-table th {} ");
            is_rtl() and wp_add_inline_style("{$this->db_slug}_bkend", ".form-table th {}#wpfooter, #wpbody-content *:not(.dashicons ), #wpbody-content input:not([dir=ltr]), #wpbody-content textarea:not([dir=ltr]), h1.had{ font-family: bodyfont, roboto, Tahoma; }");
            $this->update_footer_info();
            $n___defs = 'dir="ltr" lang="en-US" min="0" step="1" required';
            $t___defs = 'dir="ltr" lang="en-US" required';
            $s___defs = 'required';

            echo "<h1 class='had'>".$this->title_w."</h1>";
            echo '<div class="wrap">';

            // Docmunentations are shown here
            $hll = "<dev><h4><strong>".__("Shortcode and Hooks for Developers", $this->td)."</strong></h4>
            <pre class='caqpde' dir='ltr' align='left' lang='en-US'>".implode("\n",
                apply_filters("puiw_documentation",array(
                    "<b ".(is_rtl() ? "class='fa'" : "").">"._x("WordPress Shortcode", "setting-general", $this->td)."</b><hr>",
                    '<strong class="tag">[puiw_quick_shop el_id="" el_class=""]</strong>      <i>Outputs Quick Shop page. You can use Visual Composer Widget too.</i>',
                    '<br />',
                    "<b ".(is_rtl() ? "class='fa'" : "").">"._x("WordPress Action Hooks", "setting-general", $this->td).'</b>',
                    '<hr>',
                    '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
                    '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
                    '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
                    '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
                    '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
                    '<br />',
                    "<b ".(is_rtl() ? "class='fa'" : "").">"._x("WordPress Filter Hooks", "setting-general", $this->td).'</b>',
                    '<hr>',
                    '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
                    '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
                    '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
                    '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
                    '<br />',
                    "<b ".(is_rtl() ? "class='fa'" : "").">"._x("jQuery Triggering Hooks", "setting-general", $this->td).'</b>',
                    '<hr>',
                    '<strong class="tag" style="display: block;" >const PEOPCA_MOTHER = $(".pepro-one-page-purchase---top-parent");</strong>',
                    '<strong class="tag" >order_ajax_request_success</strong>            <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button successes</i>',
                    '<strong class="tag" >order_ajax_request_failed</strong>             <i>TRIGGERS ON DOCUMENT        --Fires on cart page, when "Get invoice" button fails</i>',
                    '<strong class="tag" >ajax_request_success</strong>                  <i>TRIGGERS ON PEOPCA_MOTHER   --Fires on instant shop page, when "Procceed to chekout" button successes</i>',
                    '<strong class="tag" >ajax_request_failed</strong>                   <i>TRIGGERS ON PEOPCA_MOTHER   --Fires on instant shop page, when "Procceed to chekout" button fails</i>',
                    '<strong class="tag" >update_cart</strong>                           <i>TRIGGERS ON PEOPCA_MOTHER   --Fires on instant shop page, when basket data updates</i>',
                    '<strong class="tag" >startup</strong>                               <i>TRIGGERS ON PEOPCA_MOTHER   --Fires on instant shop page, when page loads</i>',
                    )))."</pre></dev>";
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function update_footer_info()
        {
          add_filter('admin_footer_text', function () {
              return sprintf(_x("Thanks for using %s products.", "footer-copyright", $this->td), "<b><a href='https://pepro.dev/' target='_blank' >".__("Pepro Dev", $this->td)."</a></b>");
          }, 11000);
          add_filter('update_footer', function () {
              return sprintf(_x("%s — Version %s", "footer-copyright", $this->td), "<strong><a href='https://wordpress.org/plugins/pepro-ultimate-invoice/' target='_blank'>$this->title</a></strong>", $this->version);
          }, 1100);
        }
        /**
         * receive and return ajax json/data
         *
         * @method  handel_ajax_req
         * @return  json
         * @version 1.1.0
         * @since   1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function handel_ajax_req()
        {
            if (wp_doing_ajax() && !empty($_POST['wparam']) && !empty($_POST['nonce'])) {
                if (!wp_verify_nonce($_POST['nonce'], $this->td)) {
                  wp_send_json_error(array("message"=>__('Unauthorized Access Denied!', $this->td)));
                }
                global $woocommerce;
                switch ($_POST['wparam']) {
                  case 'save-resid':
                    $order = sanitize_post($_POST['order']);
                    $resid = sanitize_post($_POST['resid']);
                    if (empty($order)) wp_send_json_error(array("message"=>__('Incorrect data!', $this->td)));
                    $order = wc_get_order($order);
                    $order->update_meta_data("_shipping_puiw_invoice_track_id", $resid);
                    $order->save();
                    wp_send_json_success(["msg" => _x("Success", "wc-setting-js", $this->td), "res" => $order->get_meta("_shipping_puiw_invoice_track_id", true)]);
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
                        wp_send_json_success(array("message"=>__('Products successfully added to cart!', $this->td),"url"=>wc_get_cart_url()));
                        
                    } else {
                        wp_send_json_error(array("message"=>__('Incorrect data!', $this->td)));
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
                    $order->add_order_note(sprintf(__("Invoice Created by %s%s", $this->td), "<strong>{$this->title_w}</strong>", "<br /><a class='delete_note' style='font-size: 0.7rem;' href='https://pepro.dev/'>".__("Pepro Dev. Group", $this->td)."</a>"));
                    $order->calculate_totals();
                    $order->save();
                    update_post_meta($order->get_id(), 'puiw_shopmngr_provided_note', wp_kses_post(nl2br(wptexturize($this->tpl->get_preorder_shopmngr_extra_note()))));
                    if ("yes" == $this->tpl->get_allow_preorder_emptycart()) {
                        $woocommerce->cart->empty_cart();
                    }
                    wp_send_json_success(
                        array(
                          "url" => home_url("?invoice=".$order->get_order_number()),
                          "view" => $order->get_view_order_url(),
                          "invoice" => $order->get_order_number(),
                          "msg" => __('Request success!', $this->td),
                        )
                    );
                    
                    break;
                  case "send-mail-html":
                    $order_id = (int) trim($_POST['lparam']);
                    $order = wc_get_order($order_id);
                    if (!$order) {
                        wp_send_json_error(array("msg"=>__("No valid order", $this->td)));
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

                        $email_label = (is_array($email)&&count($email)>0) ? count($email) > 3 ? sprintf(__("%d emails", $this->td), count($email)) : implode("<br>", $email) : $email;

                        if ($wp_mail) {
                            wp_send_json_success(array( "e" => $puiw_send_mail_params_advanced, "msg"=> sprintf(__("Sending email to <br><strong>%s</strong><br>was successfully done", $this->td), $email_label)));
                        } else {
                            wp_send_json_error(array( "e" => $puiw_send_mail_params_advanced, "msg"=> sprintf(__("Error sending email to <br><strong>%s</strong>", $this->td), $email_label)));
                        }
                    } else {
                        wp_send_json_error(array("msg"=>__("No valid email found for this order", $this->td)));
                    }
                    
                    break;
                  case "retrive-admins-emails":
                    $shopmngrs_mail = $this->get_wc_managers();
                    if ($shopmngrs_mail && !empty($shopmngrs_mail) && count($shopmngrs_mail) > 0) {
                        wp_send_json_success(array("emails"=> $shopmngrs_mail));
                    } else {
                        wp_send_json_error(array("msg"=> __("Error fetching shop managers.", $this->td)));
                    }
                    
                    break;
                  case "save-swatches":
                    $lparam = sanitize_textarea_field($_POST['lparam']);
                    update_option("puiw_color_swatches", $lparam);
                    wp_send_json_success(array( "msg" => __('Saved successfully!', $this->td), ));
                    
                    break;
                  case "import_options":
                    $lparam = $_POST['lparam'];
                    if (!empty($_POST['lparam'])){
                      $wc_opt = array();
                      foreach ((array) $_POST['lparam'] as $key => $value) {
                        $wc_opt[$key] = sanitize_text_field($value);
                      }
                      $wc_opt_count = $this->change_default_settings("MODIFY", $wc_opt);
                    }
                    wp_send_json_success(array( "msg" => __('Saved successfully!', $this->td), 'opts' => $wc_opt_count, 'data'=> $wc_opt));
                    
                    break;
                }
            }
        }
        /**
         * list woocommerce managers and administrators
         *
         * @method  get_wc_managers
         * @return  array                     array of users with their name and email
         * @access  public
         * @version 1.0.0
         * @since   1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function get_wc_managers()
        {
            $_wc_managers = array();
            $users = get_users(
                array(
            "role__in" => array( "administrator", "shop_manager" ) )
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function admin_menu()
        {
            add_submenu_page("woocommerce", $this->title, $this->title, "manage_options", $this->url);
        }
        /**
         * fire this hook on admin side load
         *
         * @method  admin_init
         * @param   array $hook
         * @version 1.0.0
         * @since   1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function admin_init($hook)
        {
            if (!$this->_wc_activated()) {
                add_action(
                    'admin_notices',
                    function () {
                        echo "<div class=\"notice error\"><p>".sprintf(
                            _x('%1$s needs %2$s in order to function', "required-plugin", "$this->td"),
                            "<strong>".$this->title."</strong>",
                            "<a href='".admin_url("plugin-install.php?s=woocommerce&tab=search&type=term")."' style='text-decoration: none;' target='_blank'><strong>".
                            _x("WooCommerce", "required-plugin", "$this->td")."</strong> </a>"
                        )."</p></div>";
                    }
                );
                include_once ABSPATH . 'wp-admin/includes/plugin.php';
                deactivate_plugins(plugin_basename(__FILE__));
            }

            $peproultimateinvoice_options = $this->get_setting_options();
            foreach ($peproultimateinvoice_options as $sections) {
                foreach ($sections["data"] as $id=>$def) {
                    add_option($id, $def);
                    register_setting($sections["name"], $id);
                }
            }
            add_action("add_meta_boxes", array( $this,"add_meta_boxes"));
            add_action("admin_enqueue_scripts", array( $this,'admin_enqueue_scripts'));
            add_action("save_post", array( $this, "wc_save_shop_order_metabox"), -1);
            add_filter("woocommerce_email_styles", array( $this,"woocommerce_email_styles_edit"));
        }
        /**
         * Woocommerce Email Styles Edit
         *
         * @method woocommerce_email_styles_edit
         * @param string $css
         * @return string css code
         * @version 1.0.0
         * @since 1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function woocommerce_email_styles_edit($css)
        {
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
          echo "@font-face { font-family: 'bodyfont'; font-style: normal; font-weight: normal; src: url('".PEPROULTIMATEINVOICE_URL."/assets/css/96594ad4.woff') format('woff'); }
          @font-face { font-family: 'bodyfont'; font-style: normal; font-weight: bold; src: url('".PEPROULTIMATEINVOICE_URL."/assets/css/96594ad5.woff') format('woff'); }";
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function integrate_with_shortcode($atts=array(), $content="")
        {
            $atts = extract( shortcode_atts( array(
              "css"      => "",
              "el_class" => "",
              "el_id"    => "",
            ), $atts ));

            ob_start();
            $css_class = "";
            if ($this->_vc_activated()) {
                $css_class = apply_filters(VC_SHORTCODE_CUSTOM_CSS_FILTER_TAG, vc_shortcode_custom_css_class($css, ' '), "puiw_quick_shop", $atts);
            }
            $uniqid = uniqid("{$this->db_slug}-");
            echo "<div class='pepro-one-page-purchase---top-parent $uniqid $el_class $css_class' id='$el_id'>";
            $cats = "<option value=\"[ALL]\">".__("All Categories", $this->td)."</option>";
            $products = "";
            $products_js = array();
            $cat_args = array( 'orderby'=> "name", 'order'=> "ASC", 'hide_empty' => true, );
            $prodcuts_args = array(
              'orderby' => 'modified',
              'order' => 'DESC',
              'return' => 'ids',
              'status' => 'publish',
              'type' => 'simple',
              'stock_status' => 'instock',
              'limit' => -1,
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
                $skutxt = _x("SKU:", "js-i18n", $this->td);
                $skutxt = _x("SKU:", "js-i18n", $this->td);
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
                  <div class=\"pepro-one-page-purchase--product-title\"><a target='_blank' href=\"".get_permalink($product->get_id())."\">{$product->get_name()}</a></div>
                  <div class=\"pepro-one-page-purchase--product-item-info-secondary\">
                    <div class=\"pepro-one-page-purchase--product-cat\">{$categoris_raw}</div>
                    <div class=\"pepro-one-page-purchase--product-sku\">{$skutxt} {$product->get_sku()}</div>
                    </div>
                  </div>
                <div class=\"pepro-one-page-purchase--product-item-pruchase\">
                  <div class=\"pepro-one-page-purchase--product-item-pricelist\">
                    <div class=\"pepro-one-page-purchase--product-regular_price\" data-raw=\"{$product->get_regular_price()}\">".wc_price($product->get_regular_price(), array("currency"=>" "))."</div>
                    <div class=\"pepro-one-page-purchase--product-sale_price\" data-raw=\"{$product->get_price()}\">".wc_price($product->get_price(), array("currency"=>" "))."</div>
                  </div>
                  <div class=\"pepro-one-page-purchase--product-item-pricesymbol\">
                    <div class=\"pepro-one-page-purchase--product-currency_symbol\">".$currencysymbol."</div>
                  </div>
                </div>
                <div class=\"pepro-one-page-purchase--product-add2cart\" >
                  <a href=\"#\" data-pid=\"{$product->get_id()}\" class=\"pepro-one-page-purchase--add2cart\" title=\"".__("Add to cart", $this->td)."\" >
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
                "get_price"           =>  wc_price($product->get_price(), array("currency"=>" ")),
                "get_regular_price_raw" => $product->get_regular_price(),
                "get_regular_price"   =>  wc_price($product->get_regular_price(), array("currency"=>" ")),
                "get_sale_price_raw"  =>  $product->get_sale_price(),
                "get_sale_price"      =>  wc_price($product->get_sale_price(), array("currency"=>" ")),
                "get_image"           =>  $imgurl,
                );
            } ?>
            <script>
              var peproOnePagePurchaseAndInvoice = <?php echo json_encode($products_js); ?>;
            </script>
            <div class="pepro-one-page-purchase--container tool">
              <div class="pepro-one-page-purchase--categories-container">
                <div class="pepro-one-page-purchase--categories">
                  <h4 class="pepro-one-page-purchase--title"><?php echo __("Filter Products by Category", $this->td); ?></h4>
                  <select id="pepro-one-page-purchase--select-categories"><?php echo $cats; ?></select>
                </div>
              </div>
              <div class="pepro-one-page-purchase--products">
                <div class="pepro-one-page-purchase--product-search">
                  <h4 class="pepro-one-page-purchase--title"><?php echo __("Sort and Search shown products", $this->td); ?></h4>
                  <div class="pepro-one-page-purchase--product-search-container">
                    <input type="search" id="pepro-one-page-purchase--search-input" placeholder="<?php echo __("Search among current shown products", $this->td); ?> ..." title="<?php echo __("Search among current shown products", $this->td); ?>" />
                  </div>
                  <div class="pepro-one-page-purchase--product-sort-container">
                    <span class="pepro-one-page-purchase--title"><?php echo __("Sort By: ", $this->td); ?></span>
                    <a class="pepro-one-page-purchase-filter" data-query="alphabetically" href="javascript:void(0);"><?php echo __("Alphabetically", $this->td); ?></a>
                    <a class="pepro-one-page-purchase-filter" data-query="popularity" href="javascript:void(0);"><?php echo __("Popularity", $this->td); ?></a>
                    <a class="pepro-one-page-purchase-filter" data-query="total_sales" href="javascript:void(0);"><?php echo __("Total Sales", $this->td); ?></a>
                    <a class="pepro-one-page-purchase-filter active" data-query="latest" href="javascript:void(0);"><?php echo __("Latest", $this->td); ?></a>
                    <a class="pepro-one-page-purchase-filter" data-query="price_asc" href="javascript:void(0);"><?php echo __("Price (ASC)", $this->td); ?></a>
                    <a class="pepro-one-page-purchase-filter" data-query="price_desc" href="javascript:void(0);"><?php echo __("Price (DESC)", $this->td); ?></a>
                  </div>
                </div>
              </div>
            </div>
            <div class="pepro-one-page-purchase--container">
            <div class="pepro-one-page-purchase--cart-list">
              <div class="pepro-one-page-purchase--cart-body" data-empty="<?php echo __("Your cart is empty!", $this->td); ?>"></div>
            </div>
            <ul class="pepro-one-page-purchase--product-list" data-empty="<?php echo __("Nothing found!", $this->td); ?>">
                <?php echo $products; ?>
            </ul>
            </div>
            <?php
            echo "</div>";
            wp_enqueue_style("$this->td-ml", "$this->assets_url/css/mobileLayer" . $this->debugEnabled(".css", ".min.css"), array(), "1.0.0", "all");
            wp_enqueue_script("$this->td-ml", "$this->assets_url/js/mobileLayer". $this->debugEnabled(".js", ".min.js"), array("jquery","jquery-ui-core"), "1.0.0", true);
            wp_enqueue_style("$this->td", "$this->assets_url/css/front-end" . $this->debugEnabled(".css", ".min.css"), array(), "1.0.0", "all");
            wp_enqueue_script("$this->td", "$this->assets_url/js/front-end". $this->debugEnabled(".js", ".min.js"), array("jquery"), "1.0.0", true);
            wp_enqueue_style("select2", "{$this->assets_url}css/select2.min.css", false, "4.0.6", "all");
            wp_enqueue_script("select2", "{$this->assets_url}js/select2.min.js", array( "jquery" ), "4.0.6", true);
            wp_localize_script(
                "$this->td",
                "_i18n",
                array(
                "ajax"=> admin_url("admin-ajax.php"),
                "td"=> "puiw_$this->td",
                "okaylabel" => _x("Okay", "js-cart-page", $this->td),
                "nonce"=> wp_create_nonce($this->td),
                "addedtocard"=>_x("Added to cart", "js-i18n", $this->td),
                "thisIsAnError"=>_x("THIS IS AN ERROR!", "js-i18n", $this->td),
                "emptycartSubmit"=>_x("Your basket is empty!", "js-i18n", $this->td),
                "tti"=>_x("Total Items:", "js-i18n", $this->td),
                "ttp"=>_x("Total Products:", "js-i18n", $this->td),
                "unknownError"=>_x("There is an unknown error and we are unable to proccess your request", "js-i18n", $this->td),
                "removefromcart"=>_x("Remove from cart", "js-i18n", $this->td),
                "removefromcartContent"=>_x("Clear cart", "js-i18n", $this->td),
                "skuText"=>_x("SKU:", "js-i18n", $this->td),
                "removeFrombasketConfirmation"=>_x("Are you sure you want to remove this item from basket?", "js-i18n", $this->td),
                "removeFrombasketConfirmation2"=>_x("Are you sure you want to remove all items from basket?", "js-i18n", $this->td),
                "removeFrombasketTitle"=>_x("Removing", "js-i18n", $this->td),
                "removeFrombasketTitle2"=>_x("Clear basket", "js-i18n", $this->td),
                "instantShoppingBasketUpated"=>_x("Instant Shopping Basket Upated!", "js-i18n", $this->td),
                "confirmYes"=>_x("Yes", "js-i18n", $this->td),
                "confirmNo"=>_x("No", "js-i18n", $this->td),
                "currencySymbol"=>$currencysymbol,
                "proceedToCheckout"=>_x("Proceed to Checkout / Invoice", "js-i18n", $this->td),
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function integrate_with_vc()
        {
            $params = array(
              array(
                  'group' => esc_html__('Design Options', $this->td),
                  'type' => "{$this->td}_about",
                  'edit_field_class' => 'vc_column vc_col-sm-12',
                  'admin_label' => false,
                  'param_name' => "{$this->td}_about",
              ),
              // vc_map_add_css_animation(),
              array(
                  'type' => 'el_id',
                  'heading' => esc_html__('Element ID', $this->td),
                  'param_name' => 'el_id',
                  'edit_field_class' => 'vc_column vc_col-sm-6',
                  'description' => sprintf(esc_html__('Enter element ID (Note: make sure it is unique and valid according to %sW3C Specification%s).', $this->td), '<a href="https://www.w3schools.com/tags/att_global_id.asp" target="_blank">', '</a>'),
                  'group' => esc_html__('Design Options', $this->td),
              ),
              array(
                  'type' => 'textfield',
                  'heading' => esc_html__('Extra class name', $this->td),
                  'edit_field_class' => 'vc_column vc_col-sm-6',
                  'param_name' => 'el_class',
                  'description' => esc_html__('If you wish to style particular content element differently, then use this field to add a class name and then refer to it in your css file.', $this->td),
                  'group' => esc_html__('Design Options', $this->td),
              ),
              array(
                  'type' => 'css_editor',
                  'heading' => esc_html__('CSS box', $this->td),
                  'param_name' => 'css',
                  'group' => esc_html__('Design Options', $this->td),
              )
            );
            vc_map(
              array(
                  'base'                    => "puiw_quick_shop",
                  'name'                    => __("Quick Shop", $this->td),
                  'description'             => __('One-page Purchase', "$this->td"),
                  'class'                   => "{$this->td}__class",
                  'icon'                    => plugins_url("/assets/img/peprodev.svg", __FILE__),
                  'show_settings_on_create' => false,
                  'category'                => __('Pepro Elements', "$this->td"),
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function vc_add_pepro_about($settings, $value)
        {
            ob_start();
            echo "<div style='display: flex;justify-content: start;align-items: center;'>
                  <p style='margin: 0.8rem 1rem;'><img src='".plugins_url("/assets/img/peprodev.svg", __FILE__)."' width='55px' /></p>
                  <div><p>".sprintf(__("Proudly Developed by %s",$this->td), "<strong><a target='_blank' href='https://pepro.dev/'>".__("Pepro Dev. Group",$this->td)."</a></strong>")."</p></div>
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        private function _wc_activated()
        {
          if (!function_exists('is_woocommerce') || !class_exists('woocommerce') ) { return false; } else { return true; }
        }
        /**
         * check if visual composer is activated
         *
         * @method  _wc_activated
         * @return  boolean true if installed and activated
         * @version 1.0.0
         * @since   1.0.0
         * @license https://pepro.dev/license Pepro.dev License
         */
        private function _vc_activated()
        {
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
         * @license https://pepro.dev/license Pepro.dev License
         */
        public function _callback($a)
        {
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
     * @license https://pepro.dev/license Pepro.dev License
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