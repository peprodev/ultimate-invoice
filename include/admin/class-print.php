<?php
/*
 * @Author: Amirhossein Hosseinpour <https://amirhp.com>
 * @Date Created: 2022/10/15 13:44:52
 * @Last modified by: amirhp-com <its@amirhp.com>
 * @Last modified time: 2025/12/28 14:00:17
 */

namespace peproulitmateinvoice;
use voku\CssToInlineStyles\CssToInlineStyles;
defined("ABSPATH") or die("Pepro Ultimate Invoice :: Unauthorized Access!");

if (!class_exists("PeproUltimateInvoice_Print")) {
  class PeproUltimateInvoice_Print {
    protected $td = "pepro-ultimate-invoice";
    /** @var bool true while the POS receipt is being built */
    protected $pos_mode = false;
    protected $force_ltr = false;
    protected $ltr_template = false;
    protected $order_lang = array();
    protected $fn;
    protected $parent;
    protected $hide_bundles_parent;
    protected $hide_bundles_child;
    protected $_woosb_show_bundles;
    protected $_woosb_show_bundles_subtitle;
    protected $_woosb_show_bundled_products;
    protected $_woosb_show_bundled_subtitle;
    protected $_woosb_show_bundled_hierarchy;
    protected $_woosb_show_bundled_prefix;
    protected $_woosb_show_bundles_prefix;
    public function __construct() {
      global $PeproUltimateInvoice;
      $this->parent = $PeproUltimateInvoice;
      $this->fn = new PeproUltimateInvoice_Template;
      add_filter("puiw_template_part_markup", array($this, "ltr_markup"), 30, 2);

      add_filter("puiw_get_default_dynamic_params", array($this, "puiw_get_default_dynamic_params"), 10, 2);
      // add compatibility with WPC Product Bundles for WooCommerce By WPClever
      $this->hide_bundles_parent = 0;
      $this->hide_bundles_child = 0;

      // read on init: translated defaults must not run before the textdomain is loaded,
      // and the filters below depend on the values so they are registered here too
      add_action("init", function(){
        $this->_woosb_show_bundles           = $this->fn->get_woosb_show_bundles();
        $this->_woosb_show_bundles_subtitle  = $this->fn->get_woosb_show_bundles_subtitle();
        $this->_woosb_show_bundled_products  = $this->fn->get_woosb_show_bundled_products();
        $this->_woosb_show_bundled_subtitle  = $this->fn->get_woosb_show_bundled_subtitle();
        $this->_woosb_show_bundled_hierarchy = $this->fn->get_woosb_show_bundled_hierarchy();
        $this->_woosb_show_bundled_prefix    = $this->fn->get_woosb_bundled_subtitle_prefix(_x("Bundled in:", "wc-setting", "pepro-ultimate-invoice"));
        $this->_woosb_show_bundles_prefix    = $this->fn->get_woosb_bundles_subtitle_prefix(_x("Bundled products:", "wc-setting", "pepro-ultimate-invoice"));
        if ($this->_woosb_show_bundles == "no") {
          add_filter("puiw_order_items", array($this, "woosb_puiw_hide_bundles_parent"), 10, 2);
        }
        if ($this->_woosb_show_bundled_products == "no") {
          add_filter("puiw_order_items", array($this, "woosb_puiw_hide_bundled_childs"), 10, 2);
        }
      });
      add_filter("puiw_order_items", array($this, "puiw_sort_order_items"), 2, 2);
      add_action("woocommerce_before_order_itemmeta", array($this, "woosb_before_order_item_meta"), 10, 1);
      add_filter("puiw_invoice_item_extra_classes",   array($this, "puiw__item_extra_classes"), 10, 6);
      add_filter("puiw_get_custom_css_style",         array($this, "custom_css_per_invoice"), 10, 2);
      add_filter("puiw_get_pdf_css_style",            array($this, "custom_css_per_invoice"), 10, 2);
      add_filter("puiw_get_inventory_css_style",      array($this, "custom_css_per_invoice"), 10, 2);
    }
    public function puiw_get_default_dynamic_params($opts, $order) {

      if (isset($_GET["tp"]) && !empty($_GET["tp"])) {
        // the value is a directory path, so it has to be matched against the
        // registered templates: anything else let a visitor point the template
        // loader at an arbitrary local path or remote url (CVE-2026-65516)
        $requested = $this->validate_template_path(sanitize_text_field(base64_decode(urldecode(wp_unslash($_GET["tp"])))));
        if ($requested) {
          $opts["template"] = $requested;
          $opts["preinvoice_template"] = $requested;
        }
      }

      if (isset($_GET["pclr"]) && !empty($_GET["pclr"])) {
        $opts["theme_color"] = sanitize_hex_color(base64_decode(urldecode(trim($_GET["pclr"]))));
        $opts["preinvoice_theme_color"] = sanitize_hex_color(base64_decode(urldecode($_GET["pclr"])));
      }

      if (isset($_GET["sclr"]) && !empty($_GET["sclr"])) {
        $opts["theme_color2"] = sanitize_hex_color(base64_decode(urldecode($_GET["sclr"])));
        $opts["preinvoice_theme_color2"] = sanitize_hex_color(base64_decode(urldecode($_GET["sclr"])));
      }

      if (isset($_GET["tclr"]) && !empty($_GET["tclr"])) {
        $opts["theme_color3"] = sanitize_hex_color(base64_decode(urldecode($_GET["tclr"])));
        $opts["preinvoice_theme_color3"] = sanitize_hex_color(base64_decode(urldecode($_GET["tclr"])));
      }

      return $opts;
    }
    /**
     * Undocumented function
     *
     * @param  int $order_id
     * @param  \WC_Order $order
     * @return void
     */
    public function get_default_dynamic_params($order_id, $order) {
      $this->use_invoice_language($order);
      $opts = array(
        "order_date_created"                     => $order->get_date_created() ? $this->fn->get_date($order->get_date_created()) : "",
        "order_date_modified"                    => $order->get_date_modified() ? $this->fn->get_date($order->get_date_modified()) : "",
        "order_date_completed"                   => $order->get_date_completed() ? $this->fn->get_date($order->get_date_completed()) : "",
        "order_date_current"                     => $this->fn->get_date(date_i18n("Y/m/d H:i", current_time("timestamp"))),
        "order_date_paid"                        => $order->get_date_paid() ? $this->fn->get_date($order->get_date_paid()) : "",
        "order_status"                           => wc_get_order_status_name($order->get_status()),
        "order_total"                            => $order->get_formatted_order_total(),
        "order_date_shipped"                     => !empty($order->get_meta("_shipping_puiw_invoice_shipdate", true)) ? $this->fn->get_date($order->get_meta("_shipping_puiw_invoice_shipdate", true), "Y/m/d", true) : "",
        "order_date_shipped_raw"                 => $order->get_meta("_shipping_puiw_invoice_shipdate", true),
        "order_date_shipped_raw_shamsi"          => $order->get_meta("_shipping_puiw_invoice_shipdatefa", true),
        "store_name"                             => $this->fn->get_store_name(),
        "store_logo"                             => $this->fn->get_store_logo("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII="),
        "store_website"                          => $this->fn->get_store_website(),
        "store_email"                            => $this->fn->get_store_email(),
        "store_phone"                            => $this->fn->get_store_phone(),
        "store_address"                          => $this->fn->get_store_address(),
        "wc_store_address"                       => $this->fn->get_wc_store_address(),
        "store_postcode"                         => $this->fn->get_store_postcode(),
        "store_national_id"                      => $this->fn->get_store_national_id(),
        "store_registration_number"              => $this->fn->get_store_registration_number(),
        "store_economical_number"                => $this->fn->get_store_economical_number(),
        "send_invoices_via_email"                => $this->fn->get_send_invoices_via_email(),
        "send_invoices_via_email_admin"          => $this->fn->get_send_invoices_via_email_admin(),
        "allow_users_use_invoices"               => $this->fn->get_allow_users_use_invoices(),
        "show_invoices_id_barcode"               => $this->fn->get_show_invoices_id_barcode(),
        "show_shippingslip_store"                => $this->fn->get_show_shippingslip_store(),
        "show_shippingslip_customer"             => $this->fn->get_show_shippingslip_customer(),
        "show_qr_code_id"                        => $this->fn->get_show_qr_code_id(),
        "postal_qr_code_label_for_store"         => $this->fn->get_postal_qr_code_label_for_store(),
        "postal_qr_code_label_for_customer"      => $this->fn->get_postal_qr_code_label_for_customer(),
        "date_shamsi"                            => $this->fn->get_date_shamsi(),
        "disable_wc_dashboard"                   => $this->fn->get_disable_wc_dashboard(),
        "allow_preorder_invoice"                 => $this->fn->get_allow_preorder_invoice(),
        "allow_preorder_emptycart"               => $this->fn->get_allow_preorder_emptycart(),
        "allow_pdf_customer"                     => $this->fn->get_allow_pdf_customer(),
        "allow_pdf_guest"                        => $this->fn->get_allow_pdf_guest(),
        "pdf_size"                               => $this->fn->get_pdf_size(),
        "pdf_orientation"                        => $this->fn->get_pdf_orientation(),
        "attach_pdf_invoices_to_mail"            => $this->fn->get_attach_pdf_invoices_to_mail(),
        "custom_css_style"                       => $this->fn->get_custom_css_style(),
        "custom_pdf_css_style"                   => $this->fn->get_pdf_css_style(),
        "inventory_css_style"                    => $this->fn->get_inventory_css_style(),
        // the slips, their PDF and the POS receipt each take a stylesheet of their own
        "slips_css_style"                        => (string) apply_filters("puiw_get_slips_css_style", get_option("puiw_slips_css_style", ""), ""),
        "slips_pdf_css_style"                    => (string) apply_filters("puiw_get_slips_pdf_css_style", get_option("puiw_slips_pdf_css_style", ""), ""),
        "pos_css_style"                          => (string) apply_filters("puiw_get_pos_css_style", get_option("puiw_pos_css_style", ""), ""),
        "template"                               => $this->fn->get_template(),
        "theme_color"                            => $this->fn->get_theme_color(),
        "theme_color2"                           => $this->fn->get_theme_color2(),
        "theme_color3"                           => $this->fn->get_theme_color3(),
        "preinvoice_template"                    => $this->fn->get_preinvoice_template(),
        "preinvoice_theme_color"                 => $this->fn->get_preinvoice_theme_color(),
        "preinvoice_theme_color2"                => $this->fn->get_preinvoice_theme_color2(),
        "preinvoice_theme_color3"                => $this->fn->get_preinvoice_theme_color3(),
        "font_size"                              => $this->fn->get_font_size() . "px",
        "font_sizes"                             => $this->fn->get_font_size() - 4 . "px",
        "font_sizem"                             => $this->fn->get_font_size() - 2 . "px",
        "invoice_prefix"                         => $this->fn->get_invoice_prefix(),
        "invoice_suffix"                         => $this->fn->get_invoice_suffix(),
        "invoice_start"                          => $this->fn->get_invoice_start(),
        "signature"                              => $this->fn->get_signature(),
        "watermark"                              => $this->fn->get_watermark(),
        "watermark_blend"                        => $this->fn->get_watermark_blend(),
        "watermark_opacity"                      => $this->fn->get_watermark_opacity(),
        "watermark_opacity_10"                   => $this->fn->get_watermark_opacity() / 100,
        "invoices_footer"                        => $this->fn->get_invoices_footer(),
        "show_custom_footer"                     => empty($this->fn->get_invoices_footer()) ? "no" : "yes",
        "show_signature"                         => $this->fn->get_show_signatures(),
        "show_shelf_number_id"                   => $this->fn->get_show_shelf_number_id(),
        "show_product_sku_inventory"             => $this->fn->get_show_product_sku_inventory(),
        "show_product_sku2_inventory"            => $this->fn->get_show_product_sku2_inventory(),
        "show_product_image_inventory"           => $this->fn->get_show_product_image_inventory(),
        "show_product_weight_in_inventory"       => $this->fn->get_show_product_weight_in_inventory(),
        "show_product_total_weight_in_inventory" => $this->fn->get_show_product_total_weight_in_inventory(),
        "show_product_dimensions_in_inventory"   => $this->fn->get_show_product_dimensions_in_inventory(),
        "show_product_quantity_in_inventory"     => $this->fn->get_show_product_quantity_in_inventory(),
        "show_product_note_in_inventory"         => $this->fn->get_show_product_note_in_inventory(),
        "price_inventory_report"                 => $this->fn->get_price_inventory_report(),
        "show_order_note_inventory"              => $this->fn->get_show_order_note_inventory(),
        "show_store_national_id"                 => $this->fn->get_show_store_national_id(),
        "show_store_registration_number"         => $this->fn->get_show_store_registration_number(),
        "show_store_economical_number"           => $this->fn->get_show_store_economical_number(),
        "show_customer_address"                  => $this->fn->get_show_customer_address(),
        "show_customer_order_count"              => $this->fn->get_show_customer_order_count(),
        "show_slip_weight"                       => $this->fn->get_slip_show_weight(),
        "show_slip_barcode"                      => $this->fn->get_slip_show_barcode(),
        "slip_layout"                            => $this->fn->get_slip_layout(),
        "show_customer_phone"                    => $this->fn->get_show_customer_phone(),
        "show_customer_email"                    => $this->fn->get_show_customer_email(),
        "show_order_date"                        => $this->fn->get_show_order_date(),
        "show_payment_method"                    => $this->fn->get_show_payment_method(),
        "show_shipping_method"                   => $this->fn->get_show_shipping_method(),
        "show_shipping_address"                  => $this->fn->get_show_shipping_address(),
        "address_display_method"                 => $this->fn->get_address_display_method(),
        "show_transaction_ref_id"                => $this->fn->get_show_transaction_ref_id(),
        "show_paid_date"                         => $this->fn->get_show_paid_date(),
        "show_purchase_complete_date"            => $this->fn->get_show_purchase_complete_date(),
        "show_shipping_date"                     => $this->fn->get_show_shipping_date(),
        "show_order_status"                      => $this->fn->get_show_order_status(),
        "show_product_image"                     => $this->fn->get_show_product_image(),
        "show_product_purchase_note"             => $this->fn->get_show_product_purchase_note(),
        "show_order_items"                       => $this->fn->get_show_order_items(),
        "show_order_total"                       => $this->fn->get_show_order_total(),
        "show_order_note"                        => $this->fn->get_show_order_note(),
        "show_order_note_slip"                   => $this->fn->get_show_order_note_slip(),
        "show_user_uin"                          => $this->fn->get_show_user_uin(),
        "show_shipping_ref_id"                   => $this->fn->get_show_shipping_ref_id(),
        "show_price_template"                    => $this->fn->get_show_price_template(),
        "show_tax_display"                       => $this->fn->get_show_tax_display(),
        "show_product_weight"                    => $this->fn->get_show_product_weight(),
        "show_product_dimensions"                => $this->fn->get_show_product_dimensions(),
        "show_discount_precent"                  => $this->fn->get_show_discount_precent(),
        "show_discount_calc"                     => $this->fn->get_show_discount_calc(),
        "show_discount_display"                  => $this->fn->get_show_discount_display(),
        "show_product_tax"                       => $this->fn->get_show_product_tax(),
        "show_product_sku"                       => $this->fn->get_show_product_sku(),
        "show_product_sku2"                      => $this->fn->get_show_product_sku2(),
        "trnslt__print"                          => __("Print", "pepro-ultimate-invoice"),
        "trnslt__seller"                         => __("Seller", "pepro-ultimate-invoice"),
        "trnslt__buyer"                          => __("Buyer", "pepro-ultimate-invoice"),
        "trnslt__dates"                          => __("Extras", "pepro-ultimate-invoice"),
        "show_shipping_ref_id_colspan"           => 1,
        "invoice_final_prices_pre_colspan"       => 7,
        "product_description_colspan"            => 4,
        "product_nettotal_colspan"               => 1,
        "invoice_final_prices_colspan"           => 5,
        "invoice_final_row_colspan"              => 14,
      );
      $use_billing = $opts["show_shipping_address"];

      $opts["invoice_id"]                 = apply_filters("puiw_printinvoice_getinvoice_id"               , $opts["invoice_prefix"] . ($opts["invoice_start"] + $order->get_id()) . $opts["invoice_suffix"], $opts, $order);
      $opts["invoice_id_en"]              = apply_filters("puiw_printinvoice_getinvoice_id_en"            , $opts["invoice_prefix"] . ($opts["invoice_start"] + $order->get_id()) . $opts["invoice_suffix"], $opts, $order);
      $opts["invoice_id_nm"]              = apply_filters("puiw_printinvoice_getinvoice_id_raw"           , $order->get_id(), $opts, $order);
      $opts["invoice_title"]              = apply_filters("puiw_printinvoice_getinvoice_title"            , sprintf($this->fn->get_invoice_title(__("Invoice %s", "pepro-ultimate-invoice")), $opts["invoice_id"]), $opts, $order);
      $opts["order_payment_method"]       = apply_filters("puiw_printinvoice_getinvoice_payment_method"   , $order->get_payment_method_title(), $opts, $order);
      $opts["order_shipping_method"]      = apply_filters("puiw_printinvoice_getinvoice_shipping_method"  , $order->get_shipping_method(), $opts, $order);
      $opts["customer_email"]             = apply_filters("puiw_printinvoice_getcustomer_email"           , $order->get_billing_email(), $opts, $order);
      $opts["customer_phone"]             = apply_filters("puiw_printinvoice_getcustomer_phone"           , $order->get_billing_phone(), $opts, $order);
      $opts["customer_fname"]             = apply_filters("puiw_printinvoice_getcustomer_firstname"       , ($use_billing == "billing") ? $order->get_billing_first_name() :  $order->get_shipping_first_name(), $opts, $order, $use_billing);
      $opts["customer_lname"]             = apply_filters("puiw_printinvoice_getcustomer_lastname"        , ($use_billing == "billing") ? $order->get_billing_last_name() :   $order->get_shipping_last_name(), $opts, $order, $use_billing);
      $opts["customer_fullname"]          = apply_filters("puiw_printinvoice_getcustomer_fullname"        , ($use_billing == "billing") ? "{$order->get_billing_first_name()} {$order->get_billing_last_name()}" : "{$order->get_shipping_first_name()} {$order->get_shipping_last_name()}", $opts, $order, $use_billing);
      $opts["customer_company"]           = apply_filters("puiw_printinvoice_getcustomer_company"         , ($use_billing == "billing") ? $order->get_billing_company() : $order->get_shipping_company(), $opts, $order, $use_billing);
      $opts["customer_country"]           = apply_filters("puiw_printinvoice_getcustomer_country"         , ($use_billing == "billing") ? ($order->get_billing_country() ? $order->get_billing_country() : "") : ($order->get_shipping_country() ? $order->get_shipping_country() : ""), $opts, $order, $use_billing);
      $opts["customer_state"]             = apply_filters("puiw_printinvoice_getcustomer_state"           , ($use_billing == "billing") ? ($order->get_billing_state() ? $order->get_billing_state() : "") : ($order->get_shipping_state() ? $order->get_shipping_state() : ""), $opts, $order, $use_billing);
      $opts["customer_city"]              = apply_filters("puiw_printinvoice_getcustomer_city"            , ($use_billing == "billing") ? ($order->get_billing_city() ? $order->get_billing_city() : "") : ($order->get_shipping_city() ? $order->get_shipping_city() : ""), $opts, $order, $use_billing);
      $opts["customer_address_1"]         = apply_filters("puiw_printinvoice_getcustomer_address_1"       , ($use_billing == "billing") ? $order->get_billing_address_1() :   $order->get_shipping_address_1(), $opts, $order, $use_billing);
      $opts["customer_address_2"]         = apply_filters("puiw_printinvoice_getcustomer_address_2"       , ($use_billing == "billing") ? $order->get_billing_address_2() :   $order->get_shipping_address_2(), $opts, $order, $use_billing);
      $opts["customer_postcode"]          = apply_filters("puiw_printinvoice_getcustomer_postcode"        , ($use_billing == "billing") ? $order->get_billing_postcode() :    $order->get_shipping_postcode(), $opts, $order, $use_billing);
      $opts["customer_signature"]         = apply_filters("puiw_printinvoice_getinvoicecustomer_signature", $order->get_meta('_shipping_puiw_customer_signature', true), $opts, $order);
      $opts["order_transaction_ref_id"]   = apply_filters("puiw_printinvoice_getinvoice_shipping_method"  , $order->get_transaction_id(), $opts, $order);
      $opts["customer_uin"]               = apply_filters("puiw_printinvoice_getcustomer_uin"             , $order->get_meta('puiw_billing_uin', true), $opts, $order);
      $opts["invoice_qrcode"]             = apply_filters("puiw_printinvoice_getinvoice_qrdata"           , add_query_arg("invoice", $order->get_id(), home_url()), $opts, $order);
      $opts["invoice_qrcode"]             = wp_strip_all_tags($opts["invoice_qrcode"], true);
      // "no" hides the block, "qr" replaces the 1D barcode, "both" prints them side by side
      $qr_mode                            = $this->fn->get_show_qr_code_id("no");
      $opts["show_qr_code_id"]            = ("no" === $qr_mode || "" === $qr_mode) ? "no" : "yes";
      $opts["show_invoice_barcode_only"]  = "qr" === $qr_mode ? "no" : "yes";
      $opts["invoice_qrcode_pdf"]         = "no" === $opts["show_qr_code_id"] ? "" : sprintf('<barcode code="%s" type="QR" class="barcode" size="0.9" error="M" />', esc_attr($opts["invoice_qrcode"]));
      // rendered server side so the html invoice does not depend on javascript
      $opts["invoice_qrcode_img"]         = "no" === $opts["show_qr_code_id"] ? "" : $this->render_qrcode_img($opts["invoice_qrcode"]);
      $opts["customer_company"] = !empty($opts["customer_company"]) ? "( ".$opts["customer_company"]." )" : "";
      $get_base_countries = (array) WC()->countries->__get('countries');
      // get_states() returns false for an unknown or empty country, and the country
      // itself can be empty on manual orders, so both lookups have to be guarded
      $get_base_states = $opts["customer_country"] ? WC()->countries->get_states($opts["customer_country"]) : array();
      $get_base_states = is_array($get_base_states) ? $get_base_states : array();

      // the postcode is part of the formatted address, printing the dedicated field
      // as well is what made it show up twice on every invoice
      $postcode_display = $this->fn->get_postcode_display("both");
      $address_display_method = $this->fn->get_address_display_method();
      if ("field" === $postcode_display) {
        // the token is [po_box] and the shipped default wraps it in parentheses
        $address_display_method = preg_replace('/\s*[\(\[]?\s*\[po_box\]\s*[\)\]]?/u', "", $address_display_method);
        $address_display_method = preg_replace('/\s*[-،,]\s*$/u', "", trim($address_display_method));
      }
      $opts["show_customer_postcode"] = "address" === $postcode_display ? "no" : "yes";
      $opts["customer_order_count"]   = $order->get_customer_id() ? (string) wc_get_customer_order_count($order->get_customer_id()) : "";

      $opts["customer_address"] = str_replace(
        apply_filters( "puiw_printinvoice_address_template",
          array(
            "[first_name]",
            "[last_name]",
            "[company]",
            "[country]",
            "[province]",
            "[city]",
            "[address1]",
            "[address2]",
            "[po_box]",
            "[email]",
            "[phone]",
            "[uin]"
          ),
          $opts,
          $order
        ),
        apply_filters(
          "puiw_printinvoice_address_template_replace",
          array(
            $opts["customer_fname"],
            $opts["customer_lname"],
            $opts["customer_company"],
            !empty($get_base_countries[$opts["customer_country"]]) ? $get_base_countries[$opts["customer_country"]] : $opts["customer_country"],
            !empty($get_base_states[$opts["customer_state"]]) ? $get_base_states[$opts["customer_state"]] : $opts["customer_state"],
            $opts["customer_city"],
            $opts["customer_address_1"],
            $opts["customer_address_2"],
            $opts["customer_postcode"],
            $opts["customer_email"],
            $opts["customer_phone"],
            $opts["customer_uin"]
          ),
          $opts,
          $order
        ),
        $address_display_method
      );
      // $opts["customer_address"]        = ($use_billing == "billing") ? $order->get_formatted_billing_address() : $order->get_formatted_shipping_address();
      $opts["invoice_track_id"]           = $order->get_meta('puiw_invoice_track_id', true);
      if (empty($opts["invoice_track_id"])) {
        // woocommerce writes the shipping-prefixed key, the metabox writes the plain one
        $opts["invoice_track_id"]         = $order->get_meta('_shipping_puiw_invoice_track_id', true);
      }
      $opts["invoice_track_id_en"]        = $opts["invoice_track_id"];
      // without a tracking serial there is nothing to show, printing a row of
      // zeroes and a barcode for them only confuses the customer
      if ("" === trim((string) $opts["invoice_track_id"])) {
        $opts["show_shipping_ref_id"] = "no";
      }
      $opts["invoice_final_price"]        = $order->get_formatted_order_total();
      $opts["invoice_final_prices"]       = $this->get_order_final_prices($order);
      $opts["invoice_final_prices_pdf"]   = $this->get_order_final_prices_pdf($order);
      $opts["invoice_total_qty"]          = "0";
      $opts["invoice_total_weight"]       = "0";
      $opts["invoice_products_list"]      = "";
      $opts["home_url"]                   = home_url();

      if (empty(trim($opts["customer_signature"]))) {
        $opts["customer_signature_css"] = "display: none !important; width:0; height:0;";
        $opts["customer_signature"] = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=";
      }
      if (empty(trim($opts["signature"]))) {
        $opts["signature_css"] = "display: none !important; width:0; height:0;";
        $opts["signature"] = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=";
      }

      global $PeproUltimateInvoice;
      $generator                         = $PeproUltimateInvoice->barcode;
      $defaultemptyimg                   = 'data:img/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKAQAAAAClSfIQAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAAAmJLR0QAAd2KE6QAAAAHdElNRQfmCQMPAQF2LNlKAAAADklEQVQI12P4f4ABNwIAB1IRd+bI0OMAAAAASUVORK5CYII=';
      $opts["invoice_barcode"]           = 'data:image/jpeg;base64,' . base64_encode($generator->getBarcode($opts["invoice_id_en"], $generator::TYPE_CODE_128));
      $opts["invoice_track_barcode"]     = empty($opts["invoice_track_id_en"])  ? $defaultemptyimg : ('data:image/jpeg;base64,' . base64_encode($generator->getBarcode($opts["invoice_track_id_en"], $generator::TYPE_CODE_128)));
      $opts["customer_postcode_barcode"] = empty($opts["customer_postcode"])    ? $defaultemptyimg : ('data:image/jpeg;base64,' . base64_encode($generator->getBarcode($opts["customer_postcode"], $generator::TYPE_CODE_128)));
      $opts["store_postcode_barcode"]    = empty($opts["store_postcode"])       ? $defaultemptyimg : ('data:image/jpeg;base64,' . base64_encode($generator->getBarcode($opts["store_postcode"], $generator::TYPE_CODE_128)));

      $opts = apply_filters("puiw_get_default_dynamic_params", $opts, $order);
      $opts["template"] = $this->language_template($opts["template"]);
      $this->ltr_template = "ltr" === $this->template_direction($opts["template"]);
      return $opts;
    }
    /**
     * switch to the language this order's invoice prints in
     *
     * "auto" prints a left to right template in English when the order's language
     * is written right to left, "en" always prints in English, "site" never switches
     *
     * @method use_invoice_language
     * @param  \WC_Order $order
     * @return string the language code
     */
    public function use_invoice_language($order) {
      global $PeproUltimateInvoice;
      $i18n = is_object($PeproUltimateInvoice) && isset($PeproUltimateInvoice->i18n) ? $PeproUltimateInvoice->i18n : null;
      // detect() reads the current locale when the order carries no language, and once
      // switched that is no longer the shop's, so the first answer for an order stands
      $key = is_a($order, "WC_Order") ? $order->get_id() : 0;
      if (!isset($this->order_lang[$key])) {
        $this->order_lang[$key] = $i18n ? $i18n->detect($order) : "";
      }
      $lang   = $this->order_lang[$key];
      $mode   = $this->fn->get_invoice_language();
      $locale = "";
      if ("en" === $mode) {
        $locale = "en_US";
      } elseif ("auto" === $mode) {
        if ($this->is_rtl_language($lang) && "ltr" === $this->template_direction($this->order_template())) {
          $locale = "en_US";
        }
      } elseif ("site" !== $mode) {
        $locale = $mode;
      }
      $locale = (string) apply_filters("puiw_invoice_forced_locale", $locale, $order, $lang, $mode);
      if ("" !== $locale) {
        $lang = strtolower(substr($locale, 0, 2));
      }
      // English figures, dates and title whenever a right to left order prints in a left to right language
      $this->force_ltr = "" !== $locale && !$this->is_rtl_language($lang);
      $this->force_ltr = (bool) apply_filters("puiw_invoice_force_ltr", $this->force_ltr, $order, $lang, $mode);
      if ($i18n) {
        $i18n->activate($lang);
      }
      // Polylang or WPML may not list the language, in which case activate() could not switch
      if ("" !== $locale && function_exists("switch_to_locale") && get_locale() !== $locale && determine_locale() !== $locale) {
        switch_to_locale($locale);
      }
      $this->ltr_template = "ltr" === $this->template_direction($this->language_template($this->order_template()));
      $hooked = has_filter("puiw_get_force_persian_numbers", array($this, "latin_digits"));
      if ($this->force_ltr && !$hooked) {
        add_filter("puiw_get_force_persian_numbers", array($this, "latin_digits"), 99);
        add_filter("puiw_get_date_shamsi", array($this, "gregorian"), 99);
        add_filter("puiw_get_invoice_title", array($this, "ltr_title"), 99);
        add_filter("puiw_get_pdf_font", array($this, "ltr_font"), 99);
      } elseif (!$this->force_ltr && $hooked) {
        remove_filter("puiw_get_force_persian_numbers", array($this, "latin_digits"), 99);
        remove_filter("puiw_get_date_shamsi", array($this, "gregorian"), 99);
        remove_filter("puiw_get_invoice_title", array($this, "ltr_title"), 99);
        remove_filter("puiw_get_pdf_font", array($this, "ltr_font"), 99);
      }
      return $lang;
    }
    public function latin_digits() {
      return "latin";
    }
    public function gregorian() {
      return "no";
    }
    /**
     * the saved title is in the site's language; unless one was set for English
     * under Translations, an English invoice gets the stock English title
     *
     * @method ltr_title
     * @param  string $title
     * @return string
     */
    public function ltr_title($title) {
      return preg_match('/[\x{0590}-\x{08FF}]/u', (string) $title) ? _x("Invoice %s", "wc-setting", "pepro-ultimate-invoice") : $title;
    }
    /**
     * the Persian-digit cut of a PDF font draws 0-9 as ۰-۹ whatever the text says
     *
     * @method ltr_font
     * @param  string $font
     * @return string
     */
    public function ltr_font($font) {
      $map = array("iranyekanfa" => "iranyekanen", "iransansfa" => "iransans", "danafa" => "danaen");
      return isset($map[$font]) ? $map[$font] : $font;
    }
    /**
     * the direction the page is laid out in: the shop's switch when it forces one,
     * otherwise the one the template declares
     *
     * @method page_direction
     * @return string ltr|rtl
     */
    public function page_direction() {
      $forced = $this->fn->get_template_direction();
      if ("auto" !== $forced) {
        return $forced;
      }
      // a template says which way it reads; the site language has no say in that
      return $this->ltr_template ? "ltr" : "rtl";
    }
    /**
     * the stylesheets set direction and alignment on body and cells themselves,
     * so the attribute alone loses to them; inline runs keep theirs (numbers, codes)
     *
     * @method forced_direction_css
     * @return string
     */
    public function forced_direction_css() {
      $forced = $this->fn->get_template_direction();
      if ("auto" === $forced) {
        return "";
      }
      $start = "rtl" === $forced ? "right" : "left";
      return "body,.page,table,thead,tbody,tfoot,tr,td,th,div,p,h1,h2,h3,h4,h5,ul,ol,li{direction:{$forced} !important;}body,td,th,p,div{text-align:{$start};}";
    }
    protected function html_dir() {
      return $this->page_direction();
    }
    /**
     * the legacy templates and every bundled slips and inventory page open with
     * <body dir="rtl">, so a left to right template printed in a left to right
     * language came out mirrored, most of all in the PDF where mPDF obeys it.
     * With a forced direction every page body follows the switch.
     *
     * @method ltr_markup
     * @param  string $markup
     * @param  string $file
     * @return string
     */
    public function ltr_markup($markup, $file = "") {
      if (!preg_match('/\.(html|tpl)$/i', (string) $file) || false === stripos($markup, "<body")) {
        return $markup;
      }
      $forced = $this->fn->get_template_direction();
      if ("auto" === $forced) {
        if (!$this->ltr_template) {
          return $markup;
        }
        $forced = "ltr";
      }
      $markup = preg_replace_callback('/<body\b[^>]*>/i', function ($m) use ($forced) {
        $tag = preg_replace('/\sdir\s*=\s*("[^"]*"|\'[^\']*\'|\S+)/i', "", $m[0]);
        return preg_replace('/^<body\b/i', "<body dir=\"{$forced}\"", $tag);
      }, $markup);
      $css = $this->forced_direction_css();
      if ("" !== $css) {
        // after the template's own stylesheet, so it wins over the direction it sets
        $markup = preg_replace('/(<body\b[^>]*>)/i', "$1<style type=\"text/css\">{$css}</style>", $markup, 1);
      }
      return $markup;
    }
    /**
     * an invoice forced into a left to right language has no use for the right to
     * left copy of a template when its left to right twin is there
     *
     * @method language_template
     * @param  string $template
     * @return string
     */
    protected function language_template($template) {
      if (!$this->force_ltr || "auto" === $this->fn->get_invoice_language() || "rtl" !== $this->template_direction($template)) {
        return $template;
      }
      $sibling = preg_replace('/-rtl$/i', "", untrailingslashit($template));
      return $sibling !== untrailingslashit($template) && is_readable("{$sibling}/default.cfg") ? $sibling : $template;
    }
    /**
     * the template folder this request renders with, before the language is known
     *
     * @method order_template
     * @return string
     */
    protected function order_template() {
      if (!empty($_GET["tp"])) {
        $requested = $this->validate_template_path(sanitize_text_field(base64_decode(urldecode(wp_unslash($_GET["tp"])))));
        if ($requested) {
          return $requested;
        }
      }
      return $this->fn->get_template();
    }
    /**
     * @method template_direction
     * @param  string $template folder path
     * @return string ltr|rtl
     */
    public function template_direction($template) {
      $template = untrailingslashit((string) $template);
      $cfg = is_readable("{$template}/default.cfg") ? $this->parseTemplate((string) file_get_contents("{$template}/default.cfg")) : array();
      if (!empty($cfg["direction"])) {
        return "rtl" === strtolower(trim($cfg["direction"])) ? "rtl" : "ltr";
      }
      return preg_match('/-rtl$/i', $template) ? "rtl" : "ltr";
    }
    protected function is_rtl_language($code) {
      return in_array(strtolower((string) $code), array("fa", "ar", "he", "ur", "ps", "ckb", "ug", "yi", "dv", "sd", "ku", "arz", "azb", "haz"), true);
    }
    /**
     * @method html_lang
     * @return string
     */
    protected function html_lang() {
      return esc_attr(str_replace("_", "-", get_locale()));
    }
    public function get_preserve_english_numbers($opt, $order) {
      // values that end up inside a style attribute: "width:۰" is no css at all
      $styles = array_values(array_filter(array_keys((array) $opt), function ($key) {
        return (bool) preg_match('/(_hc|_css|_colspan)$/', (string) $key);
      }));
      return $keepOriginalENnumbers = array_merge($styles, (array) apply_filters(
        "puiw_printinvoice_preserve_english_numbers",
        array(
          "invoice_id_en",
          "invoice_track_id_en",
          "invoice_products_list",
          "customer_postcode",
          "customer_address",
          "invoice_final_price",
          "store_postcode",
          "customer_postcode_barcode",
          "store_postcode_barcode",
          "invoice_id_nm",
          "product_description_colspan",
          "product_nettotal_colspan",
          "invoice_final_prices_pre_colspan",
          "invoice_final_prices_colspan",
          "invoice_final_row_colspan",
          "invoice_final_prices",
          "invoice_final_prices_pdf",
          "store_logo",
          "order_total",
          "invoice_qrcode",
          "invoice_barcode",
          "invoice_track_barcode",
          "store_website",
          "show_invoices_id_barcode_colspan",
          "custom_css_style",
          "custom_pdf_css_style",
          "inventory_css_style",
          "slips_css_style",
          "slips_pdf_css_style",
          "pos_css_style",
          "store_email",
          "base_price",
          "discount",
          "nettotal",
          "tax",
          "sku",
          "font_sizes",
          "font_sizem",
          "font_size",
          "signature",
          "customer_signature",
          "watermark",
          "watermark_opacity",
          "watermark_opacity_10",
          "theme_color",
          "theme_color2",
          "theme_color3",
          "show_shipping_ref_id_colspan",
          "extra_classes",
          "img",
        ),
        $opt,
        $order
      ));
    }
    public function get_preserve_html_tags($opt, $order) {
      return apply_filters(
        "puiw_printinvoice_preserve_html_tags",
        array(
          "invoice_final_prices",
          "invoice_final_prices_pdf",
          "invoice_products_list",
          "invoice_qrcode_pdf",
          "invoice_qrcode_img",
          "original_price",
          "invoices_footer",
          "base_price",
          "customer_address",
          "invoice_notes",
          "invoice_note_customer",
          "invoice_note_shopmngr",
          "invoice_notes_slip",
          "invoice_note_customer_slip",
          "invoice_note_shopmngr_slip",
          "order_total",
          "custom_css_style",
          "custom_pdf_css_style",
          "inventory_css_style",
          "slips_css_style",
          "slips_pdf_css_style",
          "pos_css_style",
        ),
        $opt,
        $order
      );
    }
    public function has_access($type = "HTML", $order = false) {

      if (has_filter("puiw_printinvoice_check_user_has_access")) {
        return apply_filters("puiw_printinvoice_check_user_has_access", false, $type, $order);
      }

      // roles picked in the privacy settings, so sites using a role editor do not
      // need a functions.php snippet to let their staff print invoices
      if (is_user_logged_in() && $this->current_user_has_allowed_role()) {
        return true;
      }

      // Allow Customer/Users view invoices
      $allow_users_view_invoices = $this->fn->get_allow_users_have_invoices();
      // Invoice Output for Customers
      $access_customer = $this->fn->get_allow_pdf_customer();

      // Allow Guest Users view invoices
      $allow_guest_view_invoices = $this->fn->get_allow_guest_users_view_invoices();
      // Invoice Output for Guest Users
      $access_guest = $this->fn->get_allow_pdf_guest();

      // USER IS LOGGED IN
      if (is_user_logged_in()) {

        $user = wp_get_current_user();
        $user_id = get_current_user_id();
        $order = wc_get_order($order);
        if (!$order) return false;
        // force allow admins
        if (in_array("administrator", (array) $user->roles)  || in_array("shop_manager", (array) $user->roles)) {
          return true;
        }
        // prevent users to see others' invoices
        if ($user_id !== $order->get_user_id()) {
          return false;
        }
        // now if user is viewing his order, let's show him
        else {
          // Allow Customer/Users view invoices is NOT CHECKED
          if ("yes" !== $allow_users_view_invoices) {
            return false;
          }
          // Allow Customer/Users view invoices IS CHECKED
          // Request is PDF invoice
          if ("PDF" == $type) {
            // Invoice Output for Customers includes pdf or both
            if ("pdf" == $access_customer  || "both" == $access_customer) {
              return true;
            }
          }
          // Request is HTML invoice
          else {
            // Invoice Output for Customers includes html or both
            if ("html" == $access_customer  || "both" == $access_customer) {
              return true;
            }
          }
          // Invoice Output for Customers is not sufficient
          return false;
        }
      }
      // USER IS GUEST AND NOT LOGGED IN
      else {
        // Even with guest access enabled the visitor has to prove they were given
        // the link: without this, order ids could simply be counted up and every
        // customer's name, address, phone and basket read off the invoice
        // (CVE-2026-27372 / CVE-2026-65499)
        if (!$this->request_has_valid_order_key($order)) {
          return false;
        }
        // Allow Guests view invoices IS CHECKED
        if ("yes" == $allow_guest_view_invoices) {
          // Request is PDF invoice
          if ("PDF" == $type) {
            // Invoice Output for Guests includes pdf or both
            if ("pdf" == $access_guest  || "both" == $access_guest) {
              return true;
            }
          }
          // Request is HTML invoice
          else {
            // Invoice Output for Guests includes html or both
            if ("html" == $access_guest  || "both" == $access_guest) {
              return true;
            }
          }
          // Invoice Output for Guests is not sufficient
          return false;
        }
        // Allow Guests view invoices is NOT CHECKED
        else {
          return false;
        }
      }
      // in case of fatal error, return 403
      return false;
    }
    public function create_html($order_id = 0, $MODE = "HTML", $part = "", $email_printout = "", $skipAuth = false) {
      if (!$this->CheckPDFRequirementsHTML()) {
        $this->CheckPDFRequirementsHTML(true);
      }
      if (!$order_id || empty(trim($order_id)) || !is_numeric(trim($order_id))) {
        return __('Incorrect data!', "pepro-ultimate-invoice");
      }
      (int) $order_id = trim($order_id);
      $order = wc_get_order($order_id);
      if (!$order) {
        return __('Incorrect Order!', "pepro-ultimate-invoice");
      }
      if (!$skipAuth) {
        if ("HTML" == $MODE && !$this->has_access("HTML", $order)) {
          global $PeproUltimateInvoice;
          $PeproUltimateInvoice->die("printClass_create_html auth_check", __("Err 403 - Access Denied", "pepro-ultimate-invoice"), $PeproUltimateInvoice->Unauthorized_Access);
        }
        if ("PDF" == $MODE && !$this->has_access("PDF", $order)) {
          global $PeproUltimateInvoice;
          $PeproUltimateInvoice->die("printClass_create_html_pdf auth_check", __("Err 403 - Access Denied", "pepro-ultimate-invoice"), $PeproUltimateInvoice->Unauthorized_Access);
        }
      }
      ob_start();
      $opts = $this->get_default_dynamic_params($order_id, $order);
      if ("PDF" == $MODE) {
        $opts["invoice_final_prices"] = $this->get_order_final_prices_pdf($order);
      }
      $opt = apply_filters("puiw_printinvoice_create_html_options", $opts, $order);
      $opt["CURENT_DIR_URL"] = apply_filters("puiw_get_template_dir_url", plugin_dir_url($opt["template"] . "/default.cfg"), $opt["template"], $order);
      $keepOriginalHTMLtags  = $this->get_preserve_html_tags($opt, $order);
      $keepOriginalENnumbers = $this->get_preserve_english_numbers($opt, $order);
      do_action("puiw_printinvoice_before_create_html", $opt, $opts, $order);

      $main_css_style_inline = "";
      $order_note_a = apply_filters("puiw_printinvoice_order_note_customer", "<strong>" . __("Note provided by Customer", "pepro-ultimate-invoice") . "</strong><br><div>" . $this->fn->get_order_note($order, "a") . "</div>", $this->fn->get_order_note($order, "a"), $order, $opt);
      $order_note_b = apply_filters("puiw_printinvoice_order_note_shopmngr", "<strong>" . __("Note provided by Shop manager", "pepro-ultimate-invoice") . "</strong><br><div>" . $this->fn->get_order_note($order, "b") . "</div>", $this->fn->get_order_note($order, "b"), $order, $opt);
      // the seller block prints these lines only when the store filled them in
      $opt["show_store_website"] = "" !== trim((string) $opt["store_website"]) ? "yes" : "no";
      $opt["show_store_email"]   = "" !== trim((string) $opt["store_email"]) ? "yes" : "no";
      $notes = ""; $opt["show_order_notes"] = "no";
      $opt["show_invoice_note_customer"] = "no";
      $opt["show_invoice_note_shopmngr"] = "no";
      switch ($opt["show_order_note"]) {
        // hide_note_from_invoice, note_provided_by_customer, note_provided_by_shop_manager, note_provided_by_both
        case "note_provided_by_customer":
          if (!empty(trim( (string) $this->fn->get_order_note($order, "a")))) {
            $notes = "<td>$order_note_a</td>";
            $opt["show_order_note"] = "yes";
            $opt["show_invoice_note_customer"] = "yes";
          }
          break;
        case "note_provided_by_shop_manager":
          if (!empty(trim( (string) $this->fn->get_order_note($order, "b")))) {
            $notes = "<td>$order_note_b</td>";
            $opt["show_order_note"] = "yes";
            $opt["show_invoice_note_shopmngr"] = "yes";
          }
          break;
        case "note_provided_by_both":
          $notes = "";
          if (!empty(trim( (string) $this->fn->get_order_note($order, "a")))) {
            $notes .= "<td>$order_note_a</td>";
            $opt["show_invoice_note_customer"] = "yes";
          }
          if (!empty(trim( (string) $this->fn->get_order_note($order, "b")))) {
            $notes .= "<td>$order_note_b</td>";
            $opt["show_invoice_note_shopmngr"] = "yes";
          }
          $opt["show_order_note"] = "yes";
          break;
        default:
          $notes = "";
          $opt["show_order_note"] = "no";
          $opt["show_invoice_note_customer"] = "no";
          $opt["show_invoice_note_shopmngr"] = "no";
          break;
      }
      $opt["invoice_note_customer"] = $order_note_a;
      $opt["invoice_note_shopmngr"] = $order_note_b;
      $opt["invoice_notes"] = $notes;
      // templates hide the notes block by show_order_notes; it was set to "no" above and
      // never back, so a template that used it squeezed the notes into a zero width cell
      $opt["show_order_notes"] = "" !== trim($notes) ? "yes" : "no";
      foreach ($opt as $key => $value) {
        if (substr($key, 0, 5) == "show_" && $value !== "yes") {
          $main_css_style_inline .= "[if~='$key']{display:none !important; visibility: hidden !important;}" . PHP_EOL;
          $main_css_style_inline .= ".$key{width: 0cm; display:none !important; visibility: hidden !important;}" . PHP_EOL;
          if ("PDF" == $MODE) {
            $opt["{$key}_hide_css"] = 'width:0 !important; margin: 0 !important; padding: 0 !important; font-size: 0 !important; display: none !important; visibility: hidden !important;';
            $opt["{$key}_display_none"] = 'display: none;';
            $opt["{$key}_hc"] = 'width:0 !important; margin: 0 !important; padding: 0 !important; font-size: 0 !important; display: none !important; visibility: hidden !important;';
            $opt["{$key}_dn"] = 'display: none;';
          }
        }
        if ((substr($key, 0, 5) == "show_") && ($value == "yes") && ("PDF" == $MODE)) {
          $opt["{$key}_hide_css"] = '';
          $opt["{$key}_display_none"] = '';
          $opt["{$key}_hc"] = '';
          $opt["{$key}_dn"] = '';
        }
      }
      if (trim($opt["watermark"]) == "") {
        // $main_css_style_inline .= '[if~="watermark"]{display:none !important; visibility: hidden !important;}';
      }
      if ($opt["show_invoices_id_barcode"] !== "yes" || $email_printout) {
        $opt["show_invoices_id_barcode_colspan"] = 2;
      }
      if ($opt["show_shipping_ref_id"] !== "yes" || $email_printout) {
        $opt["show_shipping_ref_id_colspan"] = 2;
      }
      if ($opt["show_product_weight"] !== "yes") {
        if ("PDF" !== $MODE) {
          $opt["invoice_final_row_colspan"] = $opt["invoice_final_row_colspan"] - 1;
        }
      }
      if ($opt["show_product_dimensions"] !== "yes") {
        if ("PDF" !== $MODE) {
          $opt["invoice_final_row_colspan"] = $opt["invoice_final_row_colspan"] - 1;
          $opt["invoice_final_prices_colspan"] = $opt["invoice_final_prices_colspan"] - 1;
        }
      }
      if ($opt["show_product_sku"] !== "yes") {
        if ("PDF" !== $MODE) {
          $opt["invoice_final_row_colspan"] = $opt["invoice_final_row_colspan"] - 1;
          $opt["invoice_final_prices_pre_colspan"] = $opt["invoice_final_prices_pre_colspan"] - 1;
        }
      }
      if ($opt["show_product_image"] !== "yes") {
        if ("PDF" !== $MODE) {
          $opt["invoice_final_row_colspan"] = $opt["invoice_final_row_colspan"] - 1;
          $opt["invoice_final_prices_pre_colspan"] = $opt["invoice_final_prices_pre_colspan"] - 1;
        }
      }
      if ($opt["show_product_image"] !== "yes") {
        if ("PDF" !== $MODE) {
          $opt["invoice_final_row_colspan"] = $opt["invoice_final_row_colspan"] - 1;
          $opt["invoice_final_prices_pre_colspan"] = $opt["invoice_final_prices_pre_colspan"] - 1;
        }
      }
      if ($opt["show_discount_precent"] !== "yes") {
        if ("PDF" !== $MODE) {
          $opt["product_nettotal_colspan"] = $opt["product_nettotal_colspan"] + 1;
          // $opt["invoice_final_prices_colspan"] = $opt["invoice_final_prices_colspan"] + 1;
          // $opt["invoice_final_prices_pre_colspan"] = $opt["invoice_final_prices_pre_colspan"] +1 ;
        }
      }
      if ($opt["show_product_tax"] !== "yes") {
        if ("PDF" !== $MODE) {
          $opt["product_nettotal_colspan"] = $opt["product_nettotal_colspan"] + 1;
          // $opt["invoice_final_prices_colspan"] = $opt["invoice_final_prices_colspan"] + 1;
          // $opt["invoice_final_prices_pre_colspan"] = $opt["invoice_final_prices_pre_colspan"] +1 ;
        }
      }
      $templateDirpath = apply_filters("puiw_get_template_dir_path", $opt["template"], $order);
      if ("PDF_EXTRA_STYLE" == $MODE) {
        return $main_css_style_inline . ($this->pos_mode ? $opts["pos_css_style"] : $opts["custom_pdf_css_style"]);
      }
      if ("PDF" == $MODE) {
        $template = $this->read_template_part($templateDirpath, "template.pdf.tpl");
        if (!empty($part)) {
          $template  = $this->read_template_part($templateDirpath, "template.pdf.{$part}.tpl");
        }
        $product_row_RAW = $this->read_template_part($templateDirpath, "template.pdf.row.tpl");
      } else {
        $extrainvoiceheaddata   = '';
        $main_css_style = $this->read_template_part($templateDirpath, "style.css");
        if ($email_printout) {
          $main_css_style = $this->read_template_part($templateDirpath, "style.email.css");
        }
        $main_css_style = $main_css_style_inline . $main_css_style;
        $body_content = '';
        if (!$email_printout) {
          $body_content = '<p style="text-align:center;">';
          if ($skipAuth) {
            $body_content .= '<a class="print-button" href="javascript:;" onclick="window.print();return false;" >' . __("PRINT", "pepro-ultimate-invoice") . '</a>';
            $body_content .= '<a class="print-button" href="javascript:;" onclick="window.open(window.location.href.replace(\'?invoice=\',\'?invoice-pdf=\'))" >' . __("GET PDF", "pepro-ultimate-invoice") . '</a>';
          } else {
            if ($this->has_access("HTML", $order)) {
              $body_content .= '<a class="print-button" href="javascript:;" onclick="window.print();return false;">' . __("PRINT", "pepro-ultimate-invoice") . '</a>';
            }
            if ($this->has_access("PDF", $order)) {
              $body_content .= '<a class="print-button" href="javascript:;" onclick="window.open(window.location.href.replace(\'?invoice=\',\'?invoice-pdf=\'))" >' . __("GET PDF", "pepro-ultimate-invoice") . '</a>';
            }
          }
          $body_content .= '</p>';
        }
        $body_content           .= $this->read_template_part($templateDirpath, "template.tpl");
        $invoicehtmltitle       = "{{{invoice_title}}} | {{{store_name}}}";
        $body_content           = apply_filters("puiw_printinvoice_HTML_body", $body_content, $order_id);
        $extrainvoiceheaddata   = apply_filters("puiw_printinvoice_HTML_extrahead", $extrainvoiceheaddata, $order_id);
        $invoicehtmltitle       = apply_filters("puiw_printinvoice_HTML_title", $invoicehtmltitle, $order_id);
        $main_css_style         = apply_filters("puiw_printinvoice_HTML_style", $main_css_style, $main_css_style_inline, $order_id);
        $product_row_RAW        = $this->read_template_part($templateDirpath, "template.row.tpl");
        $template               = "<!DOCTYPE html><html lang=\"{$this->html_lang()}\" dir=\"{$this->html_dir()}\"><head><title>$invoicehtmltitle</title>$extrainvoiceheaddata<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><style type='text/css'>{$main_css_style}{{{custom_css_style}}}</style></head>$body_content</html>";
      }
      $n = 0; $total_weight = 0;
      foreach (apply_filters("puiw_order_items", $order->get_items(), $order) as $item_id => $item) {
        $n += 1;
        $product_row  = ($product_row_RAW);
        $product_id   = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $somemeta     = $item->get_meta('_whatever', true);
        $product      = $item->get_product();
        if (!$product) {
          continue;
        }
        $type                = $item->get_type();
        $active_price        = $product->get_price(); // The product active raw price
        $sale_price          = (float)$product->get_sale_price(); // The product raw sale price
        $regular_price       = (float)$product->get_regular_price(); // The product raw regular price
        $prev_regular_price  = (float)$item->get_meta('_puiw_regular', true);
        $prev_sale_price     = (float)$item->get_meta('_puiw_sale', true);
        $prev_price_html     = $item->get_meta('_puiw_html', true);
        $name                = apply_filters("puiw_invoice_item_get_name",              $item->get_name(), $product, $item, $order);
        $extra_classes       = apply_filters("puiw_invoice_item_extra_classes",         [], $product, $item_id, $item, $order, $n);
        $quantity            = apply_filters("puiw_invoice_item_get_quantity",          $item->get_quantity(), $product, $item, $order);
        $subtotal            = apply_filters("puiw_invoice_item_get_subtotal",          wc_price($item->get_subtotal()), $product, $item, $order);
        $tax                 = apply_filters("puiw_invoice_item_get_subtotal_tax",      wc_price($item->get_subtotal_tax()), $product, $item, $order);
        $net_total           = apply_filters("puiw_invoice_item_get_total",             wc_price($item->get_total()), $product, $item, $order);
        $tax_total           = apply_filters("puiw_invoice_item_get_total_tax",         wc_price($item->get_total_tax()), $product, $item, $order);
        $total               = apply_filters("puiw_invoice_item_get_total_tax_inc",     wc_price($item->get_total() + $item->get_total_tax()), $product, $item, $order);
        $taxclass            = apply_filters("puiw_invoice_item_get_tax_class",         $item->get_tax_class(), $product, $item, $order);
        $taxstat             = apply_filters("puiw_invoice_item_get_tax_status",        $item->get_tax_status(), $product, $item, $order);
        $allmeta             = apply_filters("puiw_invoice_item_get_meta_data",         $item->get_meta_data(), $product, $item, $order);
        $description         = apply_filters("puiw_invoice_item_get_purchase_note",     $product->get_purchase_note(), $product, $item, $order);
        $description        .= $this->get_item_meta($item_id, $item, $product);
        $description        .= $this->get_item_extra_attributes($product, $item);
        $weight_raw          = apply_filters("puiw_invoice_item_get_product_weight_raw", $product->get_weight(), $product, $item, $order);
        $weight              = apply_filters("puiw_invoice_item_get_product_weight",    $this->fn->get_format_weight($weight_raw), $product, $item, $order);
        $dimension           = apply_filters("puiw_invoice_item_get_product_dimension", $this->fn->get_product_dimension($item->get_product_id()), $product, $item, $order);
        $sku                 = $product->get_sku();

        if ($opt["show_product_sku2"] == "yes") {
          $sku = empty($sku) || !$sku ? "#$product_id" : $sku;
        }
        $product_weight = $weight_raw ? $weight_raw : 0;
        if ($product_weight) {
          $total_weight += floatval($product_weight * $quantity);
          $opt["invoice_total_weight"] = apply_filters("puiw_printinvoice_calculate_invoice_total_weight", $this->fn->get_format_weight($total_weight), $total_weight, $product_weight, $quantity, $product, $item, $order);
        }
        $opt["invoice_total_qty"] = (int) $opt["invoice_total_qty"] + $quantity;

        // Line item Price Display
        switch ($opt["show_price_template"]) {

            //Show WC_Order price (as shown in order details screen)
          case 'show_wc_price':
            $base_price = wc_price($order->get_item_subtotal($item, false, true), array('currency' => $order->get_currency()));
            break;
            //Show Current-live regular price
          case 'show_only_regular_price':
            $base_price = wc_price($product->get_regular_price(), array('currency' => $order->get_currency()));
            break;
            //Show Current-live sale price
          case 'show_only_sale_price':
            $base_price = wc_price($product->get_sale_price(), array('currency' => $order->get_currency()));
            break;
            //Show Current-live regular/sale price
          case 'show_both_regular_and_sale_price':
            $base_price = $product->get_price_html();
            break;
            //Show Saved Line-item regular price
          case 'show_saved_regular_price':
            $base_price = wc_price($prev_regular_price, array('currency' => $order->get_currency()));
            break;
            //Show Saved Line-item sale price
          case 'show_saved_sale_price':
            $base_price = wc_price($prev_sale_price, array('currency' => $order->get_currency()));
            break;
            //Show Saved Line-item sale/regular price
          case 'show_saved_regular_and_sale_price':
            $base_price = $prev_price_html;
            break;
          default:
            $base_price = wc_price($order->get_item_subtotal($item, false, true), array('currency' => $order->get_currency()));
            break;
        }

        $discount_display = "";
        $refunded_dispaly = "";
        $refunded         = -1 * $order->get_total_refunded_for_item($item_id);
        if ($refunded) {
          $refunded_dispaly = '<small class="refunded">' . wc_price($refunded, array('currency' => $order->get_currency())) . '</small>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        $discount_amount = wc_price(00, array('currency' => $order->get_currency()));
        $discount_precent = sprintf("%'02.1f%%", 0);

        // Line item Discount Calculation
        switch ($opt["show_discount_calc"]) {
            // Based on WC_Order (as shown in order details screen)
          case 'wcorder':
            if ($item->get_subtotal() !== $item->get_total()) {
              $discount_amount  = $item->get_subtotal() - $item->get_total() > 0 ? wc_price(wc_format_decimal($item->get_subtotal() - $item->get_total(), ''), array('currency' => $order->get_currency())) : "";
              $percentage       = $this->calc_precentage((float)$item->get_subtotal(), (float)$item->get_total());
              $discount_precent = sprintf("%'02.1f%%", $percentage);
            }
            break;
            // Based on Product's current-live sale/regular price
          case 'liveprice':
            if ($sale_price > 0 && $sale_price < $regular_price) {
              $discount_amount  = (($regular_price - $sale_price) > 0) ? wc_price(wc_format_decimal($regular_price - $sale_price, ''), array('currency' => $order->get_currency())) : "";
              $percentage       = $this->calc_precentage($sale_price, $regular_price);
              $discount_precent = $percentage > 0 ? sprintf("%'02.1f%%", $percentage) : "";
            } else {
              $discount_amount  = "";
              $discount_precent = "";
            }
            break;
            // Based on Line-item saved sale/regular price
          case 'savepirce':
            if ($prev_sale_price > 0 && $prev_sale_price < $prev_regular_price) {
              $discount_amount  = (($prev_regular_price - $prev_sale_price) > 0) ? wc_price(wc_format_decimal($prev_regular_price - $prev_sale_price, ''), array('currency' => $order->get_currency())) : "";
              $percentage       = $this->calc_precentage($prev_sale_price, $prev_regular_price);
              $discount_precent = $percentage > 0 ? sprintf("%'02.1f%%", $percentage) : "";
            } else {
              $discount_amount  = "";
              $discount_precent = "";
            }
            break;
            // Based on Discounted Line-item saved sale/regular price
          case 'advanced':
            // in next releases, maybe
            break;
        }

        // Line item Discount Display
        switch ($opt["show_discount_display"]) {
            // Show discount value
          case 'value':
            $discount_display = $discount_amount;
            break;
            // Show discount precentage
          case 'precnt':
            $discount_display = $discount_precent;
            break;
            // Show discount precentage and value
          case 'both':
          default:
            $discount_display = "<div>" . (!empty($discount_precent) ? "($discount_precent)" : "") . "</div><div>$discount_amount</div>";
            break;
        }
        $discount_display = apply_filters("puiw_invoice_item_get_product_discount", $discount_display, $discount_precent, $discount_amount, $opt["show_discount_calc"], $opt["show_discount_display"], $product, $item, $order);

        if (empty(trim($base_price))) {
          $base_price = wc_price($order->get_item_subtotal($item, false, true), array('currency' => $order->get_currency()));
        }

        $tax_formatted = $tax;
        $tax_formatted_amount = $tax;
        if (wc_tax_enabled()) {
          $order_taxes      = $order->get_taxes();
          $tax_classes      = \WC_Tax::get_tax_classes();
          $classes_options  = wc_get_product_tax_class_options();
          $show_tax_columns = count($order_taxes) === 1;
        }
        if (($tax_data = $item->get_taxes()) && wc_tax_enabled()) {
          $tax_formatted = "";
          $tax_formatted_amount = "";
          foreach ($order_taxes as $tax_item) {
            $column_label   = ! empty($tax_item['label']) ? $tax_item['label'] : __('Tax', 'woocommerce');
            $tax_item_id    = $tax_item->get_rate_id();
            $tax_item_total = isset($tax_data['total'][$tax_item_id]) ? $tax_data['total'][$tax_item_id] : '';
            $tax_formatted .= '<div class="line_tax"><small style="display: block;" class="tax-title">' . $column_label . '</small>' . (('' !== $tax_item_total) ? wc_price(wc_round_tax_total($tax_item_total), array('currency' => $order->get_currency())) : '&ndash;');
            if ($refunded = -1 * $order->get_tax_refunded_for_item($item_id, $tax_item_id, 'fee')) {
              $tax_formatted .= '<small class="refunded">' . wc_price($refunded, array('currency' => $order->get_currency())) . '</small>';
            }
            $tax_formatted .= '</div>';

            $tax_formatted_amount .= '<div class="line_tax">' . (('' !== $tax_item_total) ? wc_price(wc_round_tax_total($tax_item_total), array('currency' => $order->get_currency())) : '&ndash;');
            if ($refunded = -1 * $order->get_tax_refunded_for_item($item_id, $tax_item_id, 'fee')) {
              $tax_formatted_amount .= '<small class="refunded">' . wc_price($refunded, array('currency' => $order->get_currency())) . '</small>';
            }
            $tax_formatted_amount .= '</div>';
          }
        }
        $tax_formatted = apply_filters("puiw_printinvoice_create_html_lineitem_tax_formatted", $tax_formatted, $order, $item);
        switch ($opt["show_tax_display"]) {

            //Show taxes amount
          case "amount":
            $tax_formatted = $tax_formatted_amount;
            break;

            //Show line total tax amount
          case "onlytotal":
            $tax_formatted = $tax_total;
            break;

            //Show taxes label and amount
          case "labelamount":
          default:
            $tax_formatted = $tax_formatted;
            break;
        }

        if ($this->is_bundled_child($item_id)) {
          $base_price = "";
          $subtotal = "";
          if ($this->_woosb_show_bundled_hierarchy == "yes") {
            $n = $n - 1;
          }
        }

        $totalweight = apply_filters("puiw_printinvoice_create_html_total_weight", $this->fn->get_format_weight(floatval($product_weight * $quantity)), floatval($product_weight * $quantity), $product_weight, $quantity, $item_id, $item, $product_id, $order);
        $optm = array(
          "n"                           => $n,
          "img"                         => ("PDF" == $MODE) ? $product->get_image(array(50, 50)) : $product->get_image('shop_thumbnail'),
          "sku"                         => $sku,
          "title"                       => $name,
          "qty"                         => $quantity,
          "base_price"                  => $base_price,
          "description"                 => $description,
          "extra_classes"               => implode(" ", (array) $extra_classes),
          "discount"                    => $discount_display,
          "original_price"              => $this->get_item_original_price($item, $product, $order),
          "weight"                      => $weight_raw > 0 ? $weight : "",
          "total_weight"                => $totalweight,
          "product_description_colspan" => $opt["product_description_colspan"],
          "product_nettotal_colspan"    => $opt["product_nettotal_colspan"],
          "dimension"                   => $dimension,
          "tax"                         => $tax_formatted,
          "nettotal"                    => $total,
          "shelf_number_id"             => get_post_meta($product_id, "_shelf_number_id", true),
        );
        $optm = apply_filters("puiw_printinvoice_create_html_item_row_metas", $optm, $item_id, $item, $product_id, $order);

        foreach ($optm as $key => $value) {
          if (!in_array($key, $keepOriginalENnumbers)) {
            $value = $this->fn->parse_number($value);
          }
          $product_row = str_replace("{{{{$key}}}}", $value, $product_row);
        }
        $opt["invoice_products_list"] .= $product_row;
      }
      foreach ($opt as $key => $value) {
        if (!in_array($key, $keepOriginalENnumbers)) {
          $value = $this->fn->parse_number($value);
        }
        $value = apply_filters("puiw_printinvoice_create_html_options_{$key}_value", $value, $key, $order);
        if (!in_array($key, $keepOriginalHTMLtags)) {
          $template = str_replace("{{{{$key}}}}", wp_strip_all_tags($value, 1), ($template));
        } else {
          $template = str_replace("{{{{$key}}}}", $value, $template);
        }
      }
      if (apply_filters("puiw_printinvoice_return_html_minfied", true, $template, $opt, $order)) {
        $template = $this->minify_html($template);
      }
      do_action("puiw_printinvoice_after_create_html", $opt, $opts, $order);
      do_action("puiw_printinvoice_before_return_html", $opt, $opts, $order);
      echo apply_filters("puiw_printinvoice_return_html", $template, $opt, $opts, $order);
      do_action("puiw_printinvoice_after_return_html", $opt, $opts, $order);
      $html_output = ob_get_contents();
      ob_end_clean();
      return $html_output;
    }
    public function calc_precentage($offprice = 0, $realprice = 0) {
      // 100 - (newPrice / wasPrice) * 100
      if ($realprice == 0) { return 0; }
      return round(100 - (($offprice / $realprice) * 100), 5);
    }
    public function create_pdf($order_id = 0, $force_download = false, $MODE = "I", $showerror = true, $fit_height=false) {
      // 'D': download the PDF file
      // 'I': serves in-line to the browser
      // 'S': returns the PDF document as a string
      // 'F': save as file $file_out
      if (!$order_id || empty(trim($order_id)) || !is_numeric(trim($order_id))) {
        return __('Incorrect data!', "pepro-ultimate-invoice");
      }
      (int) $order_id = trim($order_id);
      $order = wc_get_order($order_id);
      if (!$order) {
        return false;
      }
      $skipAuth = true;
      if ("S" !== $MODE) {
        $skipAuth = false;
        if (!$this->has_access("PDF", $order)) {
          global $PeproUltimateInvoice;
          $PeproUltimateInvoice->die("printClass_create_pdf auth_check", __("Err 403 - Access Denied", "pepro-ultimate-invoice"), $PeproUltimateInvoice->Unauthorized_Access);
        }
      }
      if (!$this->CheckPDFRequirements()) {
        $this->CheckPDFRequirements($showerror);
      }
      global $PeproUltimateInvoice;
      // add_filter("woocommerce_price_format", function () {return '%2$s %1$s';}, 10000, 2); // you should change it from wc setting
      require_once PEPROULTIMATEINVOICE_DIR . '/include/vendor/autoload.php';
      $this->use_invoice_language($order);
      $class                  = "pepro-one-page-purchase---invoice-simple";
      $class                 .= "rtl" === $this->page_direction() ? "rtl" : "";
      $dire                   = $this->page_direction();
      $texe                   = "rtl" === $this->page_direction() ? 'right' : 'left';
      $defaultConfig          = (new \Mpdf\Config\ConfigVariables())->getDefaults();
      $fontDirs               = $defaultConfig['fontDir'];
      $defaultFontConfig      = (new \Mpdf\Config\FontVariables())->getDefaults();
      $fontData               = $defaultFontConfig['fontdata'];
      // the page geometry has to come from the template this order really renders with,
      // not from the saved default, or a per order template gets the wrong margins and
      // a roll receipt gets an A4 page
      $daynamic_params        = $this->get_default_dynamic_params($order_id, $order);
      $template               = !empty($daynamic_params["template"]) ? $daynamic_params["template"] : $this->fn->get_template();
      $templateDirpath        = apply_filters("puiw_get_template_dir_path", $template, $order);
      $contents               = $this->read_template_part($templateDirpath, "default.cfg");
      $template_pdf_setting   = $this->parseTemplate($contents);
      /* if we had an error, don't let script stop !*/
      $get_allow_pdf_customer = $this->fn->get_allow_pdf_customer();
      $get_pdf_size           = $this->fn->get_pdf_size();
      $get_pdf_orientation    = $this->fn->get_pdf_orientation();

      $_fontData = $fontData + [
        'dejavu' => [
          'R' => 'DejaVuSans.ttf',
          'B' => 'DejaVuSans-Bold.ttf',
          'useOTL' => 0xFF,
          'useKashida' => 75,
        ],
        'iranyekanen' => [
          'R' => 'IRANYekanRegular.ttf',
          'B' => 'IRANYekanBold.ttf',
          'useOTL' => 0xFF,
          'useKashida' => 75,
        ],
        'iranyekanfa' => [
          'R' => 'IRANYekanRegular(FaNum).ttf',
          'B' => 'IRANYekanBold(FaNum).ttf',
          'useOTL' => 0xFF,
          'useKashida' => 75,
        ],
        'iransans' => [
          'R' => 'IRANSans.ttf',
          'B' => 'IRANSans_Bold.ttf',
          'useOTL' => 0xFF,
          'useKashida' => 75,
        ],
        'iransansfa' => [
          'R' => 'IRANSans(FaNum).ttf',
          'B' => 'IRANSans(FaNum)_Bold.ttf',
          'useOTL' => 0xFF,
          'useKashida' => 75,
        ],
        'danaen' => [
          'R' => 'Dana-Regular.ttf',
          'B' => 'Dana-Bold.ttf',
          'useOTL' => 0xFF,
          'useKashida' => 75,
        ],
        'danafa' => [
          'R' => 'Dana-FaNum-Regular.ttf',
          'B' => 'Dana-FaNum-Bold.ttf',
          'useOTL' => 0xFF,
          'useKashida' => 75,
        ],
      ];


      // https://mpdf.github.io/paging/page-size-orientation.html
      $get_pdf_size = $this->resolve_page_format($get_pdf_size, "L" == $get_pdf_orientation);
      // a template may insist on its own page, which is what makes a roll receipt a roll
      // and not an A4 sheet with a receipt in the corner: PDF_page_width/_height in mm,
      // or PDF_page_size with a named format
      // a roll template asks to be exactly as long as its content; mPDF only honours a
      // page format given to the constructor, so the tall page is set up here and the
      // measured height is applied on the second pass below
      $roll_width = 0.0;
      // the POS button asks for a roll too, whatever template it prints with; it used
      // to measure on an A4 page and spill the receipt over two pages
      if ($fit_height || (!empty($template_pdf_setting["pdf_fit_height"]) && "no" !== strtolower((string) $template_pdf_setting["pdf_fit_height"]))) {
        $roll_width = !empty($template_pdf_setting["pdf_page_width"]) ? (float) $template_pdf_setting["pdf_page_width"] : 76;
      }
      if ($roll_width > 0) {
        $get_pdf_size = array($roll_width, 2970);
      } elseif (!empty($template_pdf_setting["pdf_page_width"])) {
        $width  = (float) $template_pdf_setting["pdf_page_width"];
        $height = !empty($template_pdf_setting["pdf_page_height"]) ? (float) $template_pdf_setting["pdf_page_height"] : 2000;
        if ($width > 0) {
          $get_pdf_size = array($width, $height);
        }
      } elseif (!empty($template_pdf_setting["pdf_page_size"])) {
        $get_pdf_size = $this->resolve_page_format($template_pdf_setting["pdf_page_size"], "L" == $get_pdf_orientation);
      }
      $get_pdf_size = apply_filters("puiw_generate_pdf_page_size", $get_pdf_size, $order_id, $order, "PDF");

      $puiw_error_reporting = error_reporting(0);
      @ini_set("display_errors", 0);
      @ini_set("max_execution_time", "300");
      @ini_set("pcre.backtrack_limit", PHP_INT_MAX);
      @ini_set('memory_limit', '20480M');

      try {
        $mpdf = new \Mpdf\Mpdf(apply_filters("puiw_create_pdf_Mpdf_options", array(
          "fontDir"                => array_merge($fontDirs, [plugin_dir_path(__FILE__)]),
          "mode"                   => "utf-8",
          "fontdata"               => $_fontData,
          "default_font"           => $this->fn->get_pdf_font(),
          "format"                 => $get_pdf_size, // A4-L
          "margin_right"           => (float) $template_pdf_setting["pdf_margin_right"] + (!$fit_height ? (float) $this->fn->get_margin_right() : 0),
          "margin_left"            => (float) $template_pdf_setting["pdf_margin_left"]  + (!$fit_height ? (float) $this->fn->get_margin_left() : 0),
          "margin_top"             => (float) $template_pdf_setting["pdf_margin_top"]   + (!$fit_height ? (float) $this->fn->get_margin_top() : 0),
          "margin_bottom"          => (float) $template_pdf_setting["pdf_margin_bottom"]+ (!$fit_height ? (float) $this->fn->get_margin_bottom() : 0),
          "margin_header"          => (float) $template_pdf_setting["pdf_margin_header"]+ (!$fit_height ? (float) $this->fn->get_margin_header() : 0),
          "margin_footer"          => (float) $template_pdf_setting["pdf_margin_footer"]+ (!$fit_height ? (float) $this->fn->get_margin_footer() : 0),
          "debug"                  => false,
          "allow_output_buffering" => true,
          "showImageErrors"        => false,
          "mirrorMargins"          => false,
          // Enable automatic page breaking and let mPDF manage bottom margins so table
          // rows are distributed more evenly across pages (fixes fragmented pages).
          // "autoPageBreak"          => true,
          // "setAutoBottomMargin"    => true,
          // // Prefer slicing behaviour for pagebreak handling (closer to older mPDF versions)
          // "defaultPagebreakType"   => 'slice',
          "watermarkImgBehind"     => false,
          "watermarkImgAlphaBlend" => $daynamic_params["watermark_blend"],
          "autoLangToFont"         => true,
          "defaultPageNumStyle"    => "arabic-indic",
        ), $order, $template_pdf_setting, $opts));

        $opts = apply_filters("puiw_generate_pdf_name_orderid_format", array(
          "invoice_prefix" => $this->fn->get_invoice_prefix(),
          "invoice_suffix" => $this->fn->get_invoice_suffix(),
          "invoice_start"  => $this->fn->get_invoice_start(),
        ));
        $order_id_formatted = $opts["invoice_prefix"] . ($opts["invoice_start"] + $order_id) . $opts["invoice_suffix"] ? $opts["invoice_prefix"] . ($opts["invoice_start"] + $order_id) . $opts["invoice_suffix"] : "0000000000000000";
        $pdf_title          = sprintf(_x("Invoice #%s on %s", "invoice-template", "pepro-ultimate-invoice"), "<strong>{$order_id_formatted}</strong>", get_bloginfo('title'));
        $pdf_title          = apply_filters("puiw_generate_pdf_title_of_pdf", $pdf_title);
        $datenow            = current_time('timestamp');
        $mpdf->SetDirectionality($dire);
        $mpdf->SetTitle(strip_tags($pdf_title));
        $mpdf->SetSubject(strip_tags($pdf_title));
        $mpdf->SetAuthor($PeproUltimateInvoice->title_d);
        $mpdf->SetCreator($PeproUltimateInvoice->title_tw);
        if (!empty($daynamic_params["watermark"])) {
          // @see https://mpdf.github.io/reference/mpdf-functions/setwatermarkimage.html
          $wtrmrk_img      = apply_filters("puiw_generate_pdf_watermark_img", $daynamic_params["watermark"], $order_id, $order, "PDF");
          $wtrmrk_alpha    = apply_filters("puiw_generate_pdf_watermark_alpha", (float) $daynamic_params["watermark_opacity_10"], $order_id, $order, "PDF");
          $wtrmrk_size     = apply_filters("puiw_generate_pdf_watermark_size", "D", $order_id, $order, "PDF");
          $wtrmrk_position = apply_filters("puiw_generate_pdf_watermark_position", "P", $order_id, $order, "PDF");
          $mpdf->SetWatermarkImage($wtrmrk_img, $wtrmrk_alpha, $wtrmrk_size, $wtrmrk_position);
          $mpdf->showWatermarkImage = apply_filters("puiw_generate_pdf_watermark_show", true, $order_id, $order, "PDF");
        }
        if (!$order) {
          $errrxt = _x('Error! Invoice does not exist.', 'invoice-template', "pepro-ultimate-invoice");
          $err_html .= "<body dir='$dire'><h2 style='text-align:center;'>$errrxt</h2><p style='text-align:center;' dir='$dire'>" .
            sprintf(_x('Created by %s<br>( %s )', 'invoice-template', "pepro-ultimate-invoice"), $PeproUltimateInvoice->title_t, "<a href='https://peprodev.com/'>" . $PeproUltimateInvoice->title_d . '</a>') .
            "</p><br><span><hr style='width: 80%;'><p style='text-align:center;' dir='$dire'>" .
            sprintf(_x('%sBack to Dashboard%s', 'invoice-template', "pepro-ultimate-invoice"), "<a href='" . get_permalink(get_option('woocommerce_myaccount_page_id')) . "'>", '</a>') . " / " .
            sprintf(_x('%sBack to Orders%s', 'invoice-template', "pepro-ultimate-invoice"), "<a href='" . wc_get_endpoint_url('orders', '', get_permalink(get_option('woocommerce_myaccount_page_id'))) . "'>", '</a>') .
            "</span></p></body>";
          $mpdf->WriteHTML($err_html);
        }
        else {
          $stylesheet      = $this->get_pdf_style($order_id, $order);
          // the POS button asks for a roll; it styles with its own stylesheet, not the invoice PDF's
          $this->pos_mode  = (bool) $fit_height;
          $PDF_EXTRA_STYLE = $this->create_html($order_id, "PDF_EXTRA_STYLE", "", "", $skipAuth);
          $this->pos_mode  = false;
          $stylesheet      = $PDF_EXTRA_STYLE . $stylesheet . $this->forced_direction_css();
          $html_invoice    = $this->prepare_images_for_pdf($this->create_html($order_id, "PDF", "", "", $skipAuth));
          $html_header     = $this->prepare_images_for_pdf($this->create_html($order_id, "PDF", "header", "", $skipAuth));
          $html_footer     = $this->prepare_images_for_pdf($this->create_html($order_id, "PDF", "footer", "", $skipAuth));
          $footer_middle = apply_filters("puiw_get_pdf_footer_text", $pdf_title, $order);
          // three cells: page number, the invoice line, the credit. An add-on may rewrite
          // or empty any of them; with all three empty the page footer is left out
          $footer_cells  = apply_filters("puiw_pdf_footer_cells", array(
            "start"  => _x("Page", "invoice-footer", "pepro-ultimate-invoice") . " {PAGENO} / {nbpg}",
            "middle" => $footer_middle,
            "end"    => "{$PeproUltimateInvoice->title_t} (https://peprodev.com)",
          ), $order, $order_id);
          $footerhtml = "";
          if ("" !== trim(implode("", array_map("strval", (array) $footer_cells)))) {
            $footerhtml = "<div class='footerauto' style='text-align: center; padding: 1rem;' dir='$dire'><table width='100%'><tr>";
            foreach (array("start", "middle", "end") as $cell) {
              $footerhtml .= "<td style=\"padding: 1rem; text-align: center; width: 33%;\">" . (isset($footer_cells[$cell]) ? $footer_cells[$cell] : "") . "</td>";
            }
            $footerhtml .= "</tr></table></div>";
          }
          $footerhtml = apply_filters_deprecated("puiw_printinvoice_pdf_footer", [$footerhtml, "", "", $order, $order_id], "1.9.0", "puiw_printinvoice_pdf_footer_new");
          $footerhtml = apply_filters("puiw_printinvoice_pdf_footer_new", $footerhtml, $order, $order_id);
          // a roll is one continuous slip: a repeating page header and footer would be
          // drawn on top of the receipt, which is what printed the page number over the
          // barcode
          if ($roll_width > 0) {
            $mpdf->SetHTMLHeader("");
            $mpdf->SetHTMLFooter("");
          } else {
            // signatures and notes live in the footer; a long one used to sit on top of
            // the last item rows, so the bottom margin grows to fit it
            $mpdf->setAutoTopMargin    = "stretch";
            $mpdf->setAutoBottomMargin = "stretch";
            $mpdf->SetHTMLHeader($html_header);
            $mpdf->SetHTMLFooter($html_footer . $footerhtml);
          }
          $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);

          // a roll receipt is as long as it needs to be: render once to measure, then
          // set the page to the measured height and render again. A template asks for
          // this with PDF_fit_height in its default.cfg.
          if ($roll_width > 0) {
            $fit_height = true;
          }
          $fit_height = apply_filters("puiw_create_pdf_fit_height", $fit_height, $order_id, $order, $MODE);
          if ($fit_height) {
            $p = 'P';
            $roll = $roll_width > 0 ? $roll_width : 76;
            $html_invoice = mb_convert_encoding($html_invoice, 'UTF-8', 'auto');
            // a trailing table that is switched off with display:none sends mPDF's
            // cursor back to the top of the page, which made every measurement zero.
            // Hidden markup has no height, so it is dropped from the measuring pass.
            $measure_html = preg_replace(
              '#<table[^>]*display\s*:\s*none[^>]*>.*?</table>#is',
              "",
              $html_invoice
            );
            $mpdf->WriteHTML(null === $measure_html ? $html_invoice : $measure_html, \Mpdf\HTMLParserMode::HTML_BODY);
            // read the cursor before the reset: afterwards it is back at the top of a
            // page that no longer exists, which is why every roll came out 4mm tall
            $measured = (float) $mpdf->y + (max(1, (int) $mpdf->page) - 1) * 2970;
            $mpdf->page  = 0;
            $mpdf->state = 0;
            unset($mpdf->pages[0]);
            $tall = max($measured + (float) $template_pdf_setting["pdf_margin_bottom"] + 6, 40);
            $mpdf->_setPageSize(array($roll, $tall), $p);
            $mpdf->addPage();
            $mpdf->WriteHTML($html_invoice, \Mpdf\HTMLParserMode::HTML_BODY);
          }
          else{
            $mpdf->WriteHTML($html_invoice, \Mpdf\HTMLParserMode::HTML_BODY);
          }
        }

        $datetime = date_i18n("Y_m_d_H_i_s", $datenow);
        if ($this->fn->get_date_shamsi() == "yes") {
          $datetime = pu_jdate("Y_m_d_H_i_s", (int) $datenow, "", "local", "en");
        }
        $name = "Invoice-$order_id_formatted-$datetime";
        $name = apply_filters("puiw_get_export_pdf_name", $name, $order_id_formatted, $order_id, $datenow);
        if ("S" == $MODE) {
          $namedir    = $this->get_pdf_temp_dir();
          $namedotext = "Invoice-$order_id_formatted.$datetime.pdf";
          $namedir    = apply_filters("puiw_get_default_mail_pdf_temp_path", $namedir, $order_id);
          $namedotext = apply_filters("puiw_get_default_mail_pdf_temp_name", $namedotext, $order_id);
          wp_mkdir_p($namedir);
          $tmpname = "$namedir/$namedotext";
          $mpdf->Output($tmpname, "F");
          return $namedotext;
        }
        $mpdf->Output($name . ".pdf", ($force_download ? "D" : "I"));
      } catch (\Throwable $e) {
        // a PDF failure must never take down checkout, the gateway return or an email
        $this->log_error("create_pdf", $e, $order_id);
        if ("S" === $MODE || !$showerror) {
          return false;
        }
        wp_die(esc_html($e->getMessage()), esc_html__("Invoice could not be generated", "pepro-ultimate-invoice"), array("back_link" => true));
      } finally {
        error_reporting($puiw_error_reporting);
      }
    }
    /**
     * print selected product attributes or meta under a line item, used for
     * warranty names and similar data other plugins store on the product
     *
     * @method get_item_extra_attributes
     * @param WC_Product $product
     * @param WC_Order_Item $item
     * @return string
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    protected function get_item_extra_attributes($product, $item) {
      $keys = $this->fn->get_item_extra_attributes("");
      $keys = array_filter(array_map("trim", explode(",", (string) $keys)));
      if (empty($keys) || !$product) {
        return "";
      }
      $out = "";
      foreach ($keys as $key) {
        $value = "";
        if (0 === strpos($key, "pa_") || $product->get_attribute($key)) {
          $value = $product->get_attribute($key);
        }
        if ("" === $value) {
          $value = $product->get_meta($key, true);
        }
        if ("" === $value && $product->get_parent_id()) {
          $parent = wc_get_product($product->get_parent_id());
          $value  = $parent ? ($parent->get_attribute($key) ?: $parent->get_meta($key, true)) : "";
        }
        if ("" === $value || is_array($value)) {
          continue;
        }
        $label = wc_attribute_label($key, $product);
        $out  .= '<div class="puiw-item-extra"><small>' . esc_html($label) . ": " . esc_html($value) . "</small></div>";
      }
      return apply_filters("puiw_invoice_item_extra_attributes", $out, $product, $item);
    }
    /**
     * pre-discount unit price, so invoices can show what dynamic pricing plugins
     * took off the line item instead of silently printing the reduced price
     *
     * @method get_item_original_price
     * @param WC_Order_Item $item
     * @param WC_Product $product
     * @param WC_Order $order
     * @return string
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    protected function get_item_original_price($item, $product, $order) {
      if ("yes" !== $this->fn->get_show_item_discount("no")) {
        return "";
      }
      $quantity = (int) $item->get_quantity();
      if ($quantity < 1) {
        return "";
      }
      $subtotal = (float) $item->get_subtotal();
      $total    = (float) $item->get_total();
      $original = $subtotal / $quantity;
      if ($subtotal <= $total && $product) {
        // dynamic pricing plugins often rewrite the price before the item is stored,
        // in that case the product regular price is the only reference left
        $regular = (float) $product->get_regular_price();
        $original = $regular > $original ? $regular : $original;
      }
      $paid = $total / $quantity;
      if ($original <= $paid) {
        return "";
      }
      $percent = round((($original - $paid) / $original) * 100, 2);
      $html    = '<del>' . wc_price($original, array("currency" => $order->get_currency())) . '</del>';
      $html   .= '<small class="puiw-item-off"> (' . sprintf(_x("%s%% off", "invoice-template", "pepro-ultimate-invoice"), $percent) . ')</small>';
      return apply_filters("puiw_invoice_item_original_price", $html, $item, $product, $order);
    }
    /**
     * check the current user against the roles allowed in the privacy settings
     *
     * @method current_user_has_allowed_role
     * @return bool
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    protected function current_user_has_allowed_role() {
      if (current_user_can("manage_woocommerce")) {
        return true;
      }
      $allowed = $this->fn->get_allowed_roles(array("administrator", "shop_manager"));
      $allowed = is_array($allowed) ? $allowed : array_filter(array_map("trim", explode(",", (string) $allowed)));
      if (empty($allowed)) {
        return false;
      }
      $user = wp_get_current_user();
      return (bool) array_intersect($allowed, (array) $user->roles);
    }
    /**
     * put the sender and recipient blocks of a packing slip in the configured order
     *
     * mpdf has no flexbox, so the order has to be decided in the markup rather
     * than with css
     *
     * @method order_slip_blocks
     * @param string $html
     * @param string $layout
     * @return string
     * @version 1.0.0
     * @since 2.3.8
     * @license https://peprodev.com/license Pepro.dev License
     */
    protected function order_slip_blocks($html, $layout) {
      if ("sender-left" !== $layout) {
        return $html;
      }
      if (!preg_match('/<!--puiw:recipient-->(.*?)<!--\/puiw:recipient-->/s', $html, $r)) {
        return $html;
      }
      if (!preg_match('/<!--puiw:sender-->(.*?)<!--\/puiw:sender-->/s', $html, $d)) {
        return $html;
      }
      // swap the two marked blocks, keeping the markers so a second pass is stable
      $recipient = "<!--puiw:recipient-->" . $r[1] . "<!--/puiw:recipient-->";
      $sender    = "<!--puiw:sender-->" . $d[1] . "<!--/puiw:sender-->";
      $html = str_replace($recipient, "%%PUIW_SLIP_A%%", $html);
      $html = str_replace($sender, "%%PUIW_SLIP_B%%", $html);
      $html = str_replace("%%PUIW_SLIP_A%%", $sender, $html);
      $html = str_replace("%%PUIW_SLIP_B%%", $recipient, $html);
      return $html;
    }
    /**
     * build a QR code as an inline svg data uri
     *
     * the html invoice used to carry an empty div that only a script could fill,
     * and that script was never loaded on the invoice, so nothing was shown
     *
     * @method render_qrcode_img
     * @param string $data
     * @param int $size
     * @return string
     * @version 1.0.0
     * @since 2.3.8
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function render_qrcode_img($data, $size = 110) {
      $data = trim((string) $data);
      if ("" === $data) {
        return "";
      }
      require_once PEPROULTIMATEINVOICE_DIR . "/include/vendor/autoload.php";
      if (!class_exists("\\Mpdf\\QrCode\\QrCode")) {
        return "";
      }
      try {
        $qr  = new \Mpdf\QrCode\QrCode($data);
        $svg = (new \Mpdf\QrCode\Output\Svg())->output($qr, $size);
      } catch (\Throwable $e) {
        $this->log_error("render_qrcode_img", $e);
        return "";
      }
      $src = "data:image/svg+xml;base64," . base64_encode($svg);
      return sprintf('<img class="puiw-qrcode" alt="QR" src="%s" style="width:%dpx;height:%dpx;" />', esc_attr($src), (int) $size, (int) $size);
    }
    /**
     * translate a stored page size into something mpdf understands
     *
     * label sizes are stored as LABEL_<width>x<height> in millimetres
     *
     * @method resolve_page_format
     * @param string $size
     * @param bool $landscape
     * @return string|array
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    protected function resolve_page_format($size, $landscape = false) {
      if (preg_match('/^LABEL_(\d+)x(\d+)$/i', (string) $size, $m)) {
        $width  = (float) $m[1];
        $height = (float) $m[2];
        return $landscape ? array($height, $width) : array($width, $height);
      }
      return $landscape ? "$size-L" : $size;
    }
    /**
     * check the request carries the order key that belongs to this order
     *
     * WooCommerce uses the same scheme for its own guest order pages
     *
     * @method request_has_valid_order_key
     * @param WC_Order|int $order
     * @return bool
     * @version 1.0.0
     * @since 2.3.8
     * @license https://peprodev.com/license Pepro.dev License
     */
    protected function request_has_valid_order_key($order) {
      // a site that really wants unauthenticated access can opt back in
      if (true === apply_filters("puiw_guest_access_without_order_key", false, $order)) {
        return true;
      }
      $order = is_a($order, "WC_Order") ? $order : wc_get_order($order);
      // a refund is an order object without an order key, and asking one for its key is
      // a fatal, not a false
      if (!$order || !method_exists($order, "get_order_key")) {
        return false;
      }
      $expected = (string) $order->get_order_key();
      if ("" === $expected) {
        return false;
      }
      foreach (array("key", "order_key", "_wpnonce_key") as $param) {
        if (!empty($_GET[$param])) {
          $given = sanitize_text_field(wp_unslash($_GET[$param]));
          if (hash_equals($expected, $given)) {
            return true;
          }
        }
      }
      return false;
    }
    /**
     * only allow template directories the site actually registered
     *
     * @method validate_template_path
     * @param string $path
     * @return string|false the real path when it is a known template, false otherwise
     * @version 1.0.0
     * @since 2.3.8
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function validate_template_path($path) {
      $path = trim((string) $path);
      if ("" === $path || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) {
        return false;
      }
      $real = realpath($path);
      if (!$real || !is_dir($real)) {
        return false;
      }
      $allowed = array();
      foreach ((array) apply_filters("puiw_get_templates_list", glob(PEPROULTIMATEINVOICE_DIR . "template/*/default.cfg")) as $cfg) {
        $dir = realpath(dirname($cfg));
        if ($dir) {
          $allowed[] = $dir;
        }
      }
      return in_array($real, $allowed, true) ? $real : false;
    }
    /**
     * read a template part, falling back to the bundled default when the selected
     * template does not ship that part (custom templates and the POS template do
     * not provide the inventory/slip parts, which used to emit file_get_contents
     * warnings straight into the printed page)
     *
     * @method read_template_part
     * @param string $dir selected template directory
     * @param string $file file name inside the template directory
     * @return string
     * @version 1.0.0
     * @since 2.3.1
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function read_template_part($dir, $file) {
      // templates ship as .html since 3.0, a .tpl of the same name still works so
      // themes written for older releases keep rendering untouched
      $names = array($file);
      if (preg_match('/\.tpl$/i', $file)) {
        array_unshift($names, preg_replace('/\.tpl$/i', ".html", $file));
      } elseif (preg_match('/\.html$/i', $file)) {
        $names[] = preg_replace('/\.html$/i', ".tpl", $file);
      }
      $names    = apply_filters("puiw_template_part_names", $names, $file, $dir);
      // a file the template does not ship comes from the bundled template of the same
      // direction; going by the site language gave an LTR template the RTL PDF layout
      $fallback = "rtl" === $this->template_direction($dir) ? "default-rtl" : "default";
      $roots    = array(untrailingslashit($dir), PEPROULTIMATEINVOICE_DIR . "template/$fallback", PEPROULTIMATEINVOICE_DIR . "template/default");
      $candidates = array();
      foreach ($roots as $root) {
        foreach ($names as $name) {
          $candidates[] = "$root/$name";
        }
      }
      $candidates = apply_filters("puiw_template_part_candidates", $candidates, $dir, $file);
      foreach ($candidates as $candidate) {
        if ($candidate && is_readable($candidate)) {
          $markup = (string) file_get_contents($candidate);
          // {{{i18n:Text}}} is a label in the template, written in English and shown
          // in the invoice's language through the plugin's own translations
          if (false !== strpos($markup, "{{{i18n:")) {
            $markup = preg_replace_callback('/\{\{\{i18n:([^{}]+)\}\}\}/', function ($m) {
              global $PeproUltimateInvoice;
              // wording set under Translations for this language wins over the bundled one
              $own = is_object($PeproUltimateInvoice) && isset($PeproUltimateInvoice->i18n) ? $PeproUltimateInvoice->i18n->override(trim($m[1])) : "";
              return esc_html("" !== $own ? $own : translate($m[1], "pepro-ultimate-invoice"));
            }, $markup);
          }
          return (string) apply_filters("puiw_template_part_markup", $markup, $file, $dir);
        }
      }
      return "";
    }
    /**
     * resolve every remote image in the markup to a local file before mpdf sees it
     *
     * mpdf fetching images itself fails on hotlink protection, on hosts without a
     * working http client and on webp/avif sources, and its psr http shim clashes
     * with other plugins shipping a different psr/http-message version
     *
     * @method prepare_images_for_pdf
     * @param string $html
     * @return string
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function prepare_images_for_pdf($html) {
      if (empty($html) || false === strpos($html, "<img")) {
        return $html;
      }
      if ("no" === apply_filters("puiw_localize_pdf_images", "yes")) {
        return $html;
      }
      // srcset/sizes would send mpdf back to the original webp file, and lazy loading
      // attributes have no meaning in a pdf
      $html = preg_replace('/\s(?:srcset|sizes|loading|decoding)\s*=\s*("[^"]*"|\'[^\']*\')/i', "", $html);
      return preg_replace_callback('/(<img\b[^>]*?\bsrc\s*=\s*)(["\'])(.*?)\2/is', function ($m) {
        $src = trim(html_entity_decode($m[3], ENT_QUOTES));
        if ("" === $src || 0 === stripos($src, "data:")) {
          return $m[0];
        }
        $local = $this->localize_image($src);
        return $local ? $m[1] . $m[2] . $local . $m[2] : $m[0];
      }, $html);
    }
    /**
     * map an image url to a readable local path, converting formats mpdf cannot embed
     *
     * @method localize_image
     * @param string $src
     * @return string|false
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    protected function localize_image($src) {
      $path = $this->resolve_local_image_path($src);
      if ($path && !$this->is_safe_local_image($path)) {
        return false;
      }
      if (!$path) {
        $path = $this->download_image($src);
      }
      if (!$path || !is_readable($path)) {
        return false;
      }
      $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
      if (in_array($ext, array("webp", "avif"), true)) {
        $converted = $this->convert_image_to_png($path, $ext);
        if (!$converted) {
          return false;
        }
        $path = $converted;
        $ext  = "png";
      }
      // hand mpdf a data uri: it refuses absolute filesystem paths unless local file
      // access is enabled, and enabling that on a customer site is not worth the risk
      $data = @file_get_contents($path);
      if (false === $data) {
        return false;
      }
      $mimes = array("jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png", "gif" => "image/gif");
      $mime  = isset($mimes[$ext]) ? $mimes[$ext] : "image/png";
      return "data:$mime;base64," . base64_encode($data);
    }
    /**
     * a local file is embedded only when it is an image inside this install: a path with
     * ../ in an image url must not turn wp-config.php or anything else into a PDF
     *
     * @method is_safe_local_image
     * @param  string $path
     * @return bool
     */
    protected function is_safe_local_image($path) {
      $real = realpath($path);
      if (!$real || !in_array(strtolower(pathinfo($real, PATHINFO_EXTENSION)), array("jpg", "jpeg", "png", "gif", "webp", "avif", "svg"), true)) {
        return false;
      }
      $uploads = wp_upload_dir();
      foreach (array(ABSPATH, WP_CONTENT_DIR, $uploads["basedir"]) as $root) {
        $root = realpath($root);
        if ($root && 0 === strpos($real, trailingslashit($root))) {
          return true;
        }
      }
      return false;
    }
    /**
     * translate a site url into a filesystem path when the file belongs to this install
     *
     * @method resolve_local_image_path
     * @param string $src
     * @return string|false
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    protected function resolve_local_image_path($src) {
      if (0 === strpos($src, "//")) {
        $src = (is_ssl() ? "https:" : "http:") . $src;
      }
      if (0 === strpos($src, "/") && 0 !== strpos($src, "//")) {
        $candidate = untrailingslashit(ABSPATH) . $src;
        return file_exists($candidate) ? $candidate : false;
      }
      $uploads = wp_upload_dir();
      foreach (array($uploads["baseurl"] => $uploads["basedir"], content_url() => WP_CONTENT_DIR, site_url() => untrailingslashit(ABSPATH)) as $url => $dir) {
        $url = preg_replace("#^https?://#i", "", untrailingslashit($url));
        $bare = preg_replace("#^https?://#i", "", $src);
        if (0 === stripos($bare, $url)) {
          $candidate = untrailingslashit($dir) . substr($bare, strlen($url));
          $candidate = explode("?", $candidate)[0];
          if (file_exists($candidate)) {
            return $candidate;
          }
        }
      }
      return false;
    }
    /**
     * fetch a remote image into the temp directory, server side
     *
     * @method download_image
     * @param string $src
     * @return string|false
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    protected function download_image($src) {
      if (!preg_match("#^https?://#i", $src)) {
        return false;
      }
      $dir  = $this->get_pdf_temp_dir();
      $name = "img-" . md5($src);
      $hit  = glob("$dir/$name.*");
      if ($hit) {
        return $hit[0];
      }
      // wp_safe_remote_get refuses private and loopback addresses on every redirect hop,
      // so an image url in an order cannot be used to reach the shop's internal network;
      // the shop's own images never get here, they are read from disk above
      $response = wp_safe_remote_get($src, array("timeout" => 10, "redirection" => 3, "limit_response_size" => 8 * MB_IN_BYTES));
      if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
        return false;
      }
      $body = wp_remote_retrieve_body($response);
      if ("" === $body) {
        return false;
      }
      $type = strtolower((string) wp_remote_retrieve_header($response, "content-type"));
      $map  = array("image/jpeg" => "jpg", "image/png" => "png", "image/gif" => "gif", "image/webp" => "webp", "image/avif" => "avif", "image/svg+xml" => "svg");
      $ext  = "";
      foreach ($map as $mime => $candidate) {
        if (false !== strpos($type, $mime)) {
          $ext = $candidate;
          break;
        }
      }
      if ("" === $ext) {
        $ext = strtolower(pathinfo(parse_url($src, PHP_URL_PATH), PATHINFO_EXTENSION));
      }
      if (!in_array($ext, array("jpg", "jpeg", "png", "gif", "webp", "avif"), true)) {
        return false;
      }
      $file = "$dir/$name.$ext";
      return file_put_contents($file, $body) ? $file : false;
    }
    /**
     * convert webp/avif to png so mpdf can embed it
     *
     * @method convert_image_to_png
     * @param string $path
     * @param string $ext
     * @return string|false
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    protected function convert_image_to_png($path, $ext) {
      $target = $this->get_pdf_temp_dir() . "/img-" . md5($path . filemtime($path)) . ".png";
      if (file_exists($target)) {
        return $target;
      }
      $reader = "webp" === $ext ? "imagecreatefromwebp" : "imagecreatefromavif";
      if (!function_exists($reader) || !function_exists("imagepng")) {
        return false;
      }
      $image = @$reader($path);
      if (!$image) {
        return false;
      }
      imagepalettetotruecolor($image);
      imagealphablending($image, true);
      imagesavealpha($image, true);
      $done = @imagepng($image, $target);
      imagedestroy($image);
      return $done ? $target : false;
    }
    /**
     * log a failure to the woocommerce logger and, as a fallback, to the php error log
     *
     * @method log_error
     * @param string $context
     * @param \Throwable $e
     * @param int $order_id
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    protected function log_error($context, $e, $order_id = 0) {
      $message = sprintf("%s failed for order #%s: %s in %s:%s", $context, $order_id, $e->getMessage(), $e->getFile(), $e->getLine());
      if (function_exists("wc_get_logger")) {
        wc_get_logger()->error($message, array("source" => "pepro-ultimate-invoice"));
        return;
      }
      error_log("pepro-ultimate-invoice ~> $message");
    }
    /**
     * writable, self-cleaning directory for temporary pdf files
     *
     * @method get_pdf_temp_dir
     * @return string
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function get_pdf_temp_dir() {
      $uploads = wp_upload_dir();
      $dir     = trailingslashit($uploads["basedir"]) . "puiw-temp";
      if (!file_exists($dir)) {
        wp_mkdir_p($dir);
        // generated invoices may contain customer data, keep them out of the web root listing
        @file_put_contents("$dir/index.php", "<?php\n// Silence is golden.\n");
        @file_put_contents("$dir/.htaccess", "deny from all");
      }
      $this->prune_pdf_temp_dir($dir);
      return $dir;
    }
    /**
     * remove leftover temporary pdf files
     *
     * @method prune_pdf_temp_dir
     * @param string $dir
     * @param int $max_age seconds
     * @version 1.0.0
     * @since 2.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    protected function prune_pdf_temp_dir($dir, $max_age = HOUR_IN_SECONDS) {
      $files = glob(trailingslashit($dir) . "*.{pdf,zip,png,jpg,jpeg,gif,webp,avif}", GLOB_BRACE);
      if (!$files) {
        return;
      }
      $now = time();
      foreach ($files as $file) {
        if (($now - (int) @filemtime($file)) > $max_age) {
          @unlink($file);
        }
      }
    }
    public function create_slips_pdf($order_id = 0, $force_download = false, $MODE = "I", $showerror = true) {
      if (!$order_id || empty(trim($order_id)) || !is_numeric(trim($order_id))) {
        return __('Incorrect data!', "pepro-ultimate-invoice");
      }
      (int) $order_id = trim($order_id);
      $order = wc_get_order($order_id);
      if (!$order) {
        return false;
      }
      $skipAuth = true;
      if ("S" !== $MODE) {
        $skipAuth = false;
        if (!$this->has_access("PDF", $order)) {
          global $PeproUltimateInvoice;
          $PeproUltimateInvoice->die("printClass_create_pdf auth_check", __("Err 403 - Access Denied", "pepro-ultimate-invoice"), $PeproUltimateInvoice->Unauthorized_Access);
        }
      }
      if (!$this->CheckPDFRequirements()) {
        $this->CheckPDFRequirements($showerror);
      }
      global $PeproUltimateInvoice;
      try {
      require_once PEPROULTIMATEINVOICE_DIR . '/include/vendor/autoload.php';
      $this->use_invoice_language($order);
      $class                   = "pepro-one-page-purchase---invoice-simple " . ("rtl" === $this->page_direction() ? "rtl" : "");
      $dire                    = $this->page_direction();
      $texe                    = "rtl" === $this->page_direction() ? 'right' : 'left';
      $defaultConfig           = (new \Mpdf\Config\ConfigVariables())->getDefaults();
      $fontDirs                = $defaultConfig['fontDir'];
      $defaultFontConfig       = (new \Mpdf\Config\FontVariables())->getDefaults();
      $fontData                = $defaultFontConfig['fontdata'];
      $template                = $this->fn->get_template();
      $templateDirpath         = apply_filters("puiw_get_template_dir_path", $template, $order);
      $contents                = $this->read_template_part($templateDirpath, "default.cfg");
      $template_pdf_setting    = $this->parseTemplate($contents);
      $get_allow_pdf_customer  = $this->fn->get_allow_pdf_customer();
      $get_pdf_size            = $this->resolve_page_format($this->fn->get_pdf_size_slip(), "L" == $this->fn->get_pdf_orientation());
      $_fontData               = $fontData + [
        'dejavu' => [
          'R' => 'DejaVuSans.ttf',
          'B' => 'DejaVuSans-Bold.ttf',
          'useOTL' => 0xFF,
          'useKashida' => 75,
        ],
        'iranyekanen' => [
          'R' => 'IRANYekanRegular.ttf',
          'B' => 'IRANYekanBold.ttf',
          'useOTL' => 0xFF,
          'useKashida' => 75,
        ],
        'iranyekanfa' => [
          'R' => 'IRANYekanRegular(FaNum).ttf',
          'B' => 'IRANYekanBold(FaNum).ttf',
          'useOTL' => 0xFF,
          'useKashida' => 75,
        ],
        'iransans' => [
          'R' => 'IRANSans.ttf',
          'B' => 'IRANSans_Bold.ttf',
          'useOTL' => 0xFF,
          'useKashida' => 75,
        ],
        'iransansfa' => [
          'R' => 'IRANSans(FaNum).ttf',
          'B' => 'IRANSans(FaNum)_Bold.ttf',
          'useOTL' => 0xFF,
          'useKashida' => 75,
        ],
        'danaen' => [
          'R' => 'Dana-Regular.ttf',
          'B' => 'Dana-Bold.ttf',
          'useOTL' => 0xFF,
          'useKashida' => 75,
        ],
        'danafa' => [
          'R' => 'Dana-FaNum-Regular.ttf',
          'B' => 'Dana-FaNum-Bold.ttf',
          'useOTL' => 0xFF,
          'useKashida' => 75,
        ]
      ];

      @ini_set('display_errors', 0);
      $puiw_error_reporting = error_reporting(0);

      $mpdf = new \Mpdf\Mpdf(apply_filters("puiw_create_slips_pdf_Mpdf_options", array(
        'fontDir'                => array_merge($fontDirs, [plugin_dir_path(__FILE__)]),
        'fontdata'               => $_fontData,
        'default_font'           => $this->fn->get_pdf_font(),
        'format'                 => $get_pdf_size, // A4-L
        'mode'                   => 'utf-8',
        'margin_right'           => $template_pdf_setting["pdf_margin_right"],
        'margin_left'            => $template_pdf_setting["pdf_margin_left"],
        'margin_top'             => $template_pdf_setting["pdf_margin_top"],
        'margin_bottom'          => $template_pdf_setting["pdf_margin_bottom"],
        'margin_header'          => $template_pdf_setting["pdf_margin_header"],
        'margin_footer'          => $template_pdf_setting["pdf_margin_footer"],
        'debug'                  => false,
        'allow_output_buffering' => true,
        'showImageErrors'        => false,
        'mirrorMargins'          => 0,
        // Enable automatic page breaking and let mPDF manage bottom margins
        // to distribute table rows evenly across pages.
        // 'autoPageBreak'          => true,
        // 'setAutoBottomMargin'    => true,
        // 'defaultPagebreakType'   => 'slice',
        'autoLangToFont'         => true,
        'defaultPageNumStyle'    => 'arabic-indic',
      ), $order, $template_pdf_setting, $opts));
      $opts = apply_filters("puiw_generate_pdf_name_orderid_format", array(
        "invoice_prefix" => $this->fn->get_invoice_prefix(),
        "invoice_suffix" => $this->fn->get_invoice_suffix(),
        "invoice_start" => $this->fn->get_invoice_start(),
      ));
      $order_id_formatted = $opts["invoice_prefix"] . ($opts["invoice_start"] + $order_id) . $opts["invoice_suffix"] ? $opts["invoice_prefix"] . ($opts["invoice_start"] + $order_id) . $opts["invoice_suffix"] : "0000000000000000";
      $pdf_title          = sprintf(_x("Invoice #%s on %s", "invoice-template", "pepro-ultimate-invoice"), $order_id_formatted, get_bloginfo('title'));
      $pdf_title          = apply_filters("puiw_generate_pdf_title_of_pdf", $pdf_title);
      $datenow            = current_time('timestamp');
      $mpdf->SetDirectionality($dire);
      $mpdf->SetTitle($pdf_title);
      $mpdf->SetSubject($pdf_title);
      $mpdf->SetAuthor($PeproUltimateInvoice->title_d);
      $mpdf->SetCreator($PeproUltimateInvoice->title_tw);
      if (!$order) {
        $errrxt = _x('Error! Invoice does not exist.', 'invoice-template', "pepro-ultimate-invoice");
        $err_html .= "<body dir='$dire'><h2 style='text-align:center;'>$errrxt</h2><p style='text-align:center;' dir='$dire'>" .
          sprintf(_x('Created by %s<br>( %s )', 'invoice-template', "pepro-ultimate-invoice"), $PeproUltimateInvoice->title_t, "<a href='https://peprodev.com/'>" . $PeproUltimateInvoice->title_d . '</a>') .
          "</p><br><span><hr style='width: 80%;'><p style='text-align:center;' dir='$dire'>" .
          sprintf(_x('%sBack to Dashboard%s', 'invoice-template', "pepro-ultimate-invoice"), "<a href='" . get_permalink(get_option('woocommerce_myaccount_page_id')) . "'>", '</a>') . " / " .
          sprintf(_x('%sBack to Orders%s', 'invoice-template', "pepro-ultimate-invoice"), "<a href='" . wc_get_endpoint_url('orders', '', get_permalink(get_option('woocommerce_myaccount_page_id'))) . "'>", '</a>') .
          "</span></p></body>";
        $mpdf->WriteHTML($err_html);
      } else {
        $stylesheet      = $this->get_pdf_style($order_id, $order);
        $PDF_EXTRA_STYLE = $this->create_slips($order_id, "CSS");
        $html_invoice    = $this->prepare_images_for_pdf($this->create_slips($order_id, "PDF"));
        $mpdf->WriteHTML($stylesheet . $PDF_EXTRA_STYLE . $this->forced_direction_css(), \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html_invoice, \Mpdf\HTMLParserMode::HTML_BODY);
      }
      $datetime = date_i18n("Y_m_d_H_i", $datenow);
      if ($this->fn->get_date_shamsi() == "yes") {
        $datetime = pu_jdate("Y-m-d_H-i-s", (int) $datenow, "", "local", "en");
      }
      $name = "PackingSlip-$order_id_formatted-$datetime";
      $name = apply_filters("puiw_get_export_pdf_name", $name, $order_id_formatted, $order_id, $datenow);
      if ("S" == $MODE) {
        $rand       = md5(time());
        $namedir    = $this->get_pdf_temp_dir();
        $namedotext = "PackingSlip-$order_id_formatted.$datetime.pdf";
        $namedir    = apply_filters("puiw_get_default_mail_pdf_temp_path", $namedir);
        $namedotext = apply_filters("puiw_get_default_mail_pdf_temp_name", $namedotext);
        wp_mkdir_p($namedir);
        $tmpname = "$namedir/$namedotext";
        $er      = $mpdf->Output($tmpname, "F");
        return $namedotext;
      }
      $mpdf->Output($name . ".pdf", ($force_download ? "D" : "I"));
      } catch (\Throwable $e) {
        $this->log_error("create_slips_pdf", $e, $order_id);
        if ("S" === $MODE || !$showerror) {
          return false;
        }
        wp_die(esc_html($e->getMessage()), esc_html__("Packing slip could not be generated", "pepro-ultimate-invoice"), array("back_link" => true));
      } finally {
        error_reporting($puiw_error_reporting);
      }
    }
    public function create_slips($order_id = 0, $MODE = "HTML") {
      if (!$order_id || empty(trim($order_id)) || !is_numeric(trim($order_id))) {
        return __('Incorrect data!', "pepro-ultimate-invoice");
      }
      (int) $order_id = trim($order_id);
      $order          = wc_get_order($order_id);
      if (!$order) {
        return __('Incorrect Order!', "pepro-ultimate-invoice");
      }

      if ("HTML" == $MODE && !$this->has_access("HTML", $order)) {
        global $PeproUltimateInvoice;
        $PeproUltimateInvoice->die("printClass_create_html auth_check", __("Err 403 - Access Denied", "pepro-ultimate-invoice"), $PeproUltimateInvoice->Unauthorized_Access);
      }
      if ("PDF" == $MODE && !$this->has_access("PDF", $order)) {
        global $PeproUltimateInvoice;
        $PeproUltimateInvoice->die("printClass_create_html_pdf auth_check", __("Err 403 - Access Denied", "pepro-ultimate-invoice"), $PeproUltimateInvoice->Unauthorized_Access);
      }

      ob_start();
      $opts                  = $this->get_default_dynamic_params($order_id, $order);
      $opt                   = apply_filters("puiw_printslips_create_html_options", $opts, $order);
      $templateDirpath       = apply_filters("puiw_get_template_dir_path",  $opt["template"], $order);
      $opt["CURENT_DIR_URL"] = apply_filters("puiw_get_template_dir_url",   plugin_dir_url($opt["template"] . "/default.cfg"), $opt["template"], $order);
      $keepOriginalHTMLtags  = $this->get_preserve_html_tags($opt, $order);
      $keepOriginalENnumbers = $this->get_preserve_english_numbers($opt, $order);
      do_action("puiw_printslips_before_create_html", $opt, $opts, $order);
      $extrainvoiceheaddata  = '';
      $main_css_style        = $this->read_template_part($templateDirpath, "style.slips" . ("PDF" == $MODE || "CSS" == $MODE ? ".pdf" : "") . ".css");
      $body_content          = "PDF" == $MODE ? "" : '<p style="text-align:center;"><a class="print-button" href="javascript:;" onclick="window.print();return false;">' . __("PRINT", "pepro-ultimate-invoice") . '</a></p>';
      $body_content         .= $this->order_slip_blocks(
        $this->read_template_part($templateDirpath, "template.slips" . ("PDF" == $MODE ? ".pdf" : "") . ".tpl"),
        $this->fn->get_slip_layout("sender-right")
      );
      $invoicehtmltitle      = "PDF" == $MODE ? "" : "{{{invoice_title}}} | {{{store_name}}}";
      $order_note_a          = apply_filters("puiw_printslips_order_note_customer", "<strong>" . __("Note provided by Customer", "pepro-ultimate-invoice") . "</strong><br>" . $this->fn->get_order_note($order, "a"), $this->fn->get_order_note($order, "a"), $order, $opt);
      $order_note_b          = apply_filters("puiw_printslips_order_note_shopmngr", "<strong>" . __("Note provided by Shop manager", "pepro-ultimate-invoice") . "</strong><br>" . $this->fn->get_order_note($order, "b"), $this->fn->get_order_note($order, "b"), $order, $opt);

      $notes = ""; $opt["show_order_notes_slip"] = "no";
      $opt["show_invoice_note_customer_slip"] = "no";
      $opt["show_invoice_note_shopmngr_slip"] = "no";
      $opt["invoice_note_customer_slip"] = "";
      $opt["invoice_note_shopmngr_slip"] = "";
      switch ($opt["show_order_note_slip"]) {
        // hide_note_from_invoice, note_provided_by_customer, note_provided_by_shop_manager, note_provided_by_both
        case "note_provided_by_customer":
          if (!empty(trim( (string) $this->fn->get_order_note($order, "a")))) {
            $notes = "<td>$order_note_a</td>";
            $opt["invoice_note_customer_slip"] = $order_note_a;
            $opt["show_order_notes_slip"] = "yes";
            $opt["show_invoice_note_customer_slip"] = "yes";
          }
          break;
        case "note_provided_by_shop_manager":
          if (!empty(trim( (string) $this->fn->get_order_note($order, "b")))) {
            $notes = "<td>$order_note_b</td>";
            $opt["invoice_note_shopmngr_slip"] = $order_note_b;
            $opt["show_order_notes_slip"] = "yes";
            $opt["show_invoice_note_shopmngr_slip"] = "yes";
          }
          break;
        case "note_provided_by_both":
          $notes = "";
          if (!empty(trim( (string) $this->fn->get_order_note($order, "a")))) {
            $notes .= "<td>$order_note_a</td>";
            $opt["invoice_note_customer_slip"] = $order_note_a;
            $opt["show_invoice_note_customer_slip"] = "yes";
          }
          if (!empty(trim( (string) $this->fn->get_order_note($order, "b")))) {
            $notes .= "<td>$order_note_b</td>";
            $opt["invoice_note_shopmngr_slip"] = $order_note_b;
            $opt["show_invoice_note_shopmngr_slip"] = "yes";
          }
          $opt["show_order_notes_slip"] = "yes";
          break;
        default:
          $notes = "";
          $opt["show_order_notes_slip"] = "no";
          $opt["show_invoice_note_customer_slip"] = "no";
          $opt["show_invoice_note_shopmngr_slip"] = "no";
          break;
      }
      $opt["invoice_notes_slip"] = $notes;

      foreach ($opt as $key => $value) {
        if (substr($key, 0, 5) == "show_" && $value !== "yes") {
          $main_css_style .= "[if~='$key']{display:none !important;}.$key{display:none !important;}";
        }
      }
      if (trim($opt["watermark"]) == "") {
        $main_css_style .= '[if~="watermark"]{display:none !important;}';
        $main_css_style .= '."watermark{display:none !important;}';
      }

      if ($opt["show_invoices_id_barcode"] !== "yes") {
        $opt["show_invoices_id_barcode_colspan"] = 2;
      }
      if ($opt["show_shipping_ref_id"] !== "yes") {
        $opt["show_shipping_ref_id_colspan"] = 2;
      }
      if ($opt["show_product_weight"] !== "yes") {
        $opt["invoice_final_row_colspan"] = $opt["invoice_final_row_colspan"] - 1;
      }
      if ($opt["show_product_dimensions"] !== "yes") {
        $opt["invoice_final_row_colspan"] = $opt["invoice_final_row_colspan"] - 1;
        $opt["invoice_final_prices_colspan"] = $opt["invoice_final_prices_colspan"] - 1;
      }

      if ($opt["show_discount_precent"] !== "yes") {
        $opt["invoice_final_row_colspan"] = $opt["invoice_final_row_colspan"] - 1;
        $opt["invoice_final_prices_colspan"] = $opt["invoice_final_prices_colspan"] - 1;
      }
      if ($opt["show_product_tax"] !== "yes") {
        $opt["invoice_final_row_colspan"] = $opt["invoice_final_row_colspan"] - 1;
        $opt["invoice_final_prices_colspan"] = $opt["invoice_final_prices_colspan"] - 1;
      }

      if ($opt["show_product_sku"] !== "yes") {
        $opt["invoice_final_row_colspan"] = $opt["invoice_final_row_colspan"] - 1;
      }
      if ($opt["show_product_image"] !== "yes") {
        $opt["invoice_final_row_colspan"] = $opt["invoice_final_row_colspan"] - 1;
        $opt["invoice_final_prices_pre_colspan"] = $opt["invoice_final_prices_pre_colspan"] - 1;
      }
      $opt["invoice_notes"] = $notes;

      $body_content         = apply_filters("puiw_printslips_HTML_body", $body_content);
      $extrainvoiceheaddata = apply_filters("puiw_printslips_HTML_extrahead", $extrainvoiceheaddata);
      $invoicehtmltitle     = apply_filters("puiw_printslips_HTML_title", $invoicehtmltitle);
      $main_css_style       = apply_filters("puiw_printslips_HTML_style", $main_css_style);
      // the PDF used to get "{{{custom_css_style}}}" back as literal text here
      $template_css         = $main_css_style . (isset($opt["slips_pdf_css_style"]) ? $opt["slips_pdf_css_style"] : "");
      $template             = "<!DOCTYPE html><html lang=\"{$this->html_lang()}\" dir=\"{$this->html_dir()}\"><head><title>$invoicehtmltitle</title>$extrainvoiceheaddata<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><style type='text/css'>{$main_css_style}{{{slips_css_style}}}</style></head>$body_content</html>";
      $template             = "PDF" == $MODE ? $body_content : $template;
      $product_row_RAW      = $this->read_template_part($templateDirpath, "template.row.tpl");
      $n                    = 0;
      $total_weight = 0;
      foreach (apply_filters("puiw_order_items", $order->get_items(), $order) as $item_id => $item) {
        $n             += 1;
        $product_row    = ($product_row_RAW);
        $product_id     = $item->get_product_id();
        $variation_id   = $item->get_variation_id();
        $somemeta       = $item->get_meta('_whatever', true);
        $product        = $item->get_product();
        $type           = $item->get_type();
        $active_price   = $product->get_price(); // The product active raw price
        $sale_price     = $product->get_sale_price(); // The product raw sale price
        $regular_price  = $product->get_regular_price(); // The product raw regular price
        $name           = apply_filters("puiw_invoice_item_get_name",              $item->get_name(), $product, $item, $order);
        $extra_classes  = apply_filters("puiw_invoice_item_extra_classes",         [], $product, $item_id, $item, $order, $n);
        $quantity       = apply_filters("puiw_invoice_item_get_quantity",          $item->get_quantity(), $product, $item, $order);
        $subtotal       = apply_filters("puiw_invoice_item_get_subtotal",          wc_price($item->get_subtotal()), $product, $item, $order);
        $total          = apply_filters("puiw_invoice_item_get_total",             wc_price($item->get_total()), $product, $item, $order);
        $tax            = apply_filters("puiw_invoice_item_get_subtotal_tax",      wc_price($item->get_subtotal_tax()), $product, $item, $order);
        $taxclass       = apply_filters("puiw_invoice_item_get_tax_class",         $item->get_tax_class(), $product, $item, $order);
        $taxstat        = apply_filters("puiw_invoice_item_get_tax_status",        $item->get_tax_status(), $product, $item, $order);
        $allmeta        = apply_filters("puiw_invoice_item_get_meta_data",         $item->get_meta_data(), $product, $item, $order);
        $description    = apply_filters("puiw_invoice_item_get_purchase_note",     $product->get_purchase_note(), $product, $item, $order);
        $description   .= $this->get_item_meta($item_id, $item, $product);
        $weight_raw     = apply_filters("puiw_invoice_item_get_product_weight_raw", $product->get_weight(), $product, $item, $order);
        $weight         = apply_filters("puiw_invoice_item_get_product_weight",    $this->fn->get_format_weight($weight_raw), $product, $item, $order);
        $dimension      = apply_filters("puiw_invoice_item_get_product_dimension", $this->fn->get_product_dimension($item->get_product_id()), $product, $item, $order);
        $percentage     = (100 - ((float)$item->get_total() / (float)$item->get_subtotal() * 100));
        $percentage     = ($percentage > 0) ? sprintf("%2d%%", $percentage) : "00.0%";
        $discount       = apply_filters("puiw_invoice_item_get_product_discount", $percentage, $product, $item, $order);
        $sku            = $product->get_sku();
        if ($opt["show_product_sku2"] == "yes") {
          $sku  = empty($sku) || !$sku ? "#$product_id" : $sku;
        }
        $product_weight = $weight_raw ? $weight_raw : 0;
        if ($product_weight) {
          $total_weight += floatval($product_weight * $quantity);
          $opt["invoice_total_weight"] = apply_filters("puiw_printslips_calculate_invoice_total_weight", $this->fn->get_format_weight($total_weight), $total_weight, $product_weight, $quantity, $product, $item, $order);
        }
        $opt["invoice_total_qty"] = (int) $opt["invoice_total_qty"] + $quantity;
        switch ($opt["show_price_template"]) {
            //show_only_regular_price, show_only_sale_price, show_both_regular_and_sale_price
          case 'show_only_regular_price':
            $base_price = wc_price($product->get_regular_price(), array('currency' => $order->get_currency()));
            break;
          case 'show_only_sale_price':
            $base_price = wc_price($product->get_sale_price(), array('currency' => $order->get_currency()));
            break;
          case 'show_both_regular_and_sale_price':
            $base_price = wc_price($product->get_price(), array('currency' => $order->get_currency()));
            break;
          default:
            $base_price = wc_price($order->get_item_subtotal($item, false, true), array('currency' => $order->get_currency()));
            break;
        }

        if ($this->is_bundled_child($item_id)) {
          $base_price = "";
          $subtotal = "";
          if ($this->_woosb_show_bundled_hierarchy == "yes") {
            $n--;
          }
        }

        $optm = array(
          "n"             => $n,
          "img"           => $product->get_image(array(50, 50)),
          "sku"           => $sku,
          "title"         => $name,
          "qty"           => $quantity,
          "base_price"    => $base_price,
          "description"   => $description,
          "extra_classes" => implode(" ", (array) $extra_classes),
          "discount"      => $discount,
          "weight"        => $weight,
          "total_weight"  => apply_filters(
            "puiw_printslips_create_html_total_weight",
            $this->fn->get_format_weight(floatval($product_weight * $quantity)),
            floatval($product_weight * $quantity),
            $product_weight,
            $quantity,
            $item_id,
            $item,
            $product_id,
            $order
          ),
          "dimension"       => $dimension,
          "tax"             => $tax,
          "nettotal"        => $subtotal,
          "shelf_number_id" => get_post_meta($product_id, "_shelf_number_id", true),
        );
        $optm = apply_filters("puiw_printslips_create_html_item_row_metas", $optm, $item_id, $item, $product_id, $order);

        foreach ($optm as $key => $value) {
          if (!in_array($key, $keepOriginalENnumbers)) {
            $value = $this->fn->parse_number($value);
          }
          $product_row = str_replace("{{{{$key}}}}", $value, $product_row);
        }
        $opt["invoice_products_list"] .= $product_row;
      }
      foreach ($opt as $key => $value) {
        if (!in_array($key, $keepOriginalENnumbers)) {
          $value = $this->fn->parse_number($value);
        }
        $value = apply_filters("puiw_printslips_create_html_options_{$key}_value", $value, $key, $order);
        if (!in_array($key, $keepOriginalHTMLtags)) {
          $template_css = str_replace("{{{{$key}}}}", wp_strip_all_tags($value, 1), ($template_css));
          $template      = str_replace("{{{{$key}}}}", wp_strip_all_tags($value, 1), ($template));
        } else {
          $template_css = str_replace("{{{{$key}}}}", $value, $template_css);
          $template      = str_replace("{{{{$key}}}}", $value, $template);
        }
      }
      if (apply_filters("puiw_printslips_return_html_minfied", true, $template, $opt, $order)) {
        $template = $this->minify_html($template);
      }
      if ("CSS" == $MODE) {
        return $template_css;
      }
      do_action("puiw_printslips_after_create_html", $opt, $opts, $order);
      do_action("puiw_printslips_before_return_html", $opt, $opts, $order);
      echo apply_filters("puiw_printslips_return_html", $template, $opt, $opts, $order);
      do_action("puiw_printslips_after_return_html", $opt, $opts, $order);
      $html_output = ob_get_contents();
      ob_end_clean();
      return $html_output;
    }
    public function create_inventory($order_id = 0, $MODE = "HTML") {
      if (!$order_id || empty(trim($order_id)) || !is_numeric(trim($order_id))) {
        return __('Incorrect data!', "pepro-ultimate-invoice");
      }
      (int) $order_id = trim($order_id);
      $order = wc_get_order($order_id);
      if (!$order) {
        return __('Incorrect Order!', "pepro-ultimate-invoice");
      }
      // the inventory report is a warehouse document: shelf numbers, buying prices
      // and internal notes. Owning the order is not enough to read it.
      if (!apply_filters("puiw_inventory_report_has_access", current_user_can("edit_shop_orders") || current_user_can("manage_woocommerce"), $order)) {
        global $PeproUltimateInvoice;
        $PeproUltimateInvoice->die("printClass_inventory auth_check", __("Err 403 - Access Denied", "pepro-ultimate-invoice"), $PeproUltimateInvoice->Unauthorized_Access);
      }
      ob_start();
      $opts = $this->get_default_dynamic_params($order_id, $order);
      $opt = apply_filters("puiw_printinventory_create_html_options", $opts, $order);

      $templateDirpath = apply_filters("puiw_get_template_dir_path", $opt["template"], $order);
      $opt["CURENT_DIR_URL"] = apply_filters("puiw_get_template_dir_url", plugin_dir_url($opt["template"] . "/default.cfg"), $opt["template"], $order);

      $keepOriginalHTMLtags = $this->get_preserve_html_tags($opt, $order);
      $keepOriginalENnumbers = $this->get_preserve_english_numbers($opt, $order);
      do_action("puiw_printinventory_before_create_html", $opt, $opts, $order);
      $extrainvoiceheaddata = '';
      $main_css_style = $this->read_template_part($templateDirpath, "style.inventory.css");
      $body_content = '<p style="text-align:center;"><a class="print-button" href="javascript:;" onclick="window.print();return false;">' . __("PRINT", "pepro-ultimate-invoice") . '</a></p>';
      $body_content .= $this->read_template_part($templateDirpath, "template.inventory.tpl");
      $invoicehtmltitle = "{{{invoice_title}}} | {{{store_name}}}";
      $extrainvoiceheaddata .= '<script src="' . PEPROULTIMATEINVOICE_URL . '/assets/js/qrcode.min.js"></script>';
      $order_note_a = apply_filters("puiw_printinventory_order_note_customer", "<strong>" . __("Note provided by Customer", "pepro-ultimate-invoice") . "</strong><br>" . $this->fn->get_order_note($order, "a"), $this->fn->get_order_note($order, "a"), $order, $opt);
      $order_note_b = apply_filters("puiw_printinventory_order_note_shopmngr", "<strong>" . __("Note provided by Shop manager", "pepro-ultimate-invoice") . "</strong><br>" . $this->fn->get_order_note($order, "b"), $this->fn->get_order_note($order, "b"), $order, $opt);
      $opt["show_inventory_price"] = "yes";
      if ($opt["price_inventory_report"] == "hide_all_price") {
        $opt["show_inventory_price"] = "no";
      }
      switch ($opt["show_order_note_inventory"]) {
        case 'note_provided_by_customer':
          $notes = "<td>$order_note_a</td>";
          $opt["show_inventory_note"] = "yes";
          break;
        case 'note_provided_by_shop_manager':
          $notes = "<td>$order_note_b</td>";
          $opt["show_inventory_note"] = "yes";
          break;
        case 'note_provided_by_both':
          $notes = "<td>$order_note_b</td><td>$order_note_a</td>";
          $opt["show_inventory_note"] = "yes";
          break;
        default:
          $notes = "";
          $opt["show_inventory_note"] = "no";
          break;
      }
      foreach ($opt as $key => $value) {
        if (substr($key, 0, 5) == "show_" && $value != "yes") {
          $main_css_style .= "[if~='$key'],.$key{display:none !important;}";
        }
      }
      $opt["invoice_final_prices_pre_colspan"] = $nmpo = 8;
      if (trim($opt["watermark"]) == "") {
        $main_css_style .= '[if~="watermark"]{display:none !important;}.watermark{display:none !important;}';
      }
      if ($opt["show_product_image_inventory"] !== "yes") {
        $nmpo -= 1;
      }
      if ($opt["show_product_sku_inventory"] !== "yes") {
        $nmpo -= 1;
      }
      if ($opt["show_shelf_number_id"] !== "yes") {
        $nmpo -= 1;
      }
      if ($opt["show_inventory_price"] !== "yes") {
        $nmpo -= 1;
      }
      if ($opt["show_product_weight_in_inventory"] !== "yes") {
        $nmpo -= 1;
      }
      $opt["invoice_final_prices_pre_colspan"] = $nmpo;
      $opt["invoice_notes"] = $notes;
      $body_content = apply_filters("puiw_printinventory_HTML_body", $body_content);
      $extrainvoiceheaddata = apply_filters("puiw_printinventory_HTML_extrahead", $extrainvoiceheaddata);
      $invoicehtmltitle = apply_filters("puiw_printinventory_HTML_title", $invoicehtmltitle);
      $main_css_style = apply_filters("puiw_printinventory_HTML_style", $main_css_style);
      $template = "<!DOCTYPE html><html lang=\"{$this->html_lang()}\" dir=\"{$this->html_dir()}\"><head><title>$invoicehtmltitle</title>$extrainvoiceheaddata<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><style type='text/css'>{$main_css_style}{{{inventory_css_style}}}</style></head>$body_content</html>";
      $product_row_RAW = $this->read_template_part($templateDirpath, "template.row.inventory.tpl");
      $n = 0;
      $total_weight = 0;
      foreach (apply_filters("puiw_order_items", $order->get_items(), $order) as $item_id => $item) {
        $n += 1;
        $product_row  = ($product_row_RAW);
        $product_id   = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $somemeta     = $item->get_meta('_whatever', true);
        $product      = $item->get_product();
        $type         = $item->get_type();
        $active_price = $product->get_price(); // The product active raw price
        $sale_price   = $product->get_sale_price(); // The product raw sale price
        $regular_price = $product->get_regular_price(); // The product raw regular price
        $name         = apply_filters("puiw_invoice_item_get_name",              $item->get_name(), $product, $item, $order);
        $extra_classes = apply_filters("puiw_invoice_item_extra_classes",         [], $product, $item_id, $item, $order, $n);
        $quantity     = apply_filters("puiw_invoice_item_get_quantity",          $item->get_quantity(), $product, $item, $order);
        $subtotal     = apply_filters("puiw_invoice_item_get_subtotal",          wc_price($item->get_subtotal()), $product, $item, $order);
        $total        = apply_filters("puiw_invoice_item_get_total",             wc_price($item->get_total()), $product, $item, $order);
        $tax          = apply_filters("puiw_invoice_item_get_subtotal_tax",      wc_price($item->get_subtotal_tax()), $product, $item, $order);
        $taxclass     = apply_filters("puiw_invoice_item_get_tax_class",         $item->get_tax_class(), $product, $item, $order);
        $taxstat      = apply_filters("puiw_invoice_item_get_tax_status",        $item->get_tax_status(), $product, $item, $order);
        $allmeta      = apply_filters("puiw_invoice_item_get_meta_data",         $item->get_meta_data(), $product, $item, $order);
        $description  = apply_filters("puiw_invoice_item_get_purchase_note",     $product->get_purchase_note(), $product, $item, $order);
        $description .= $this->get_item_meta($item_id, $item, $product);
        $weight_raw   = apply_filters("puiw_invoice_item_get_product_weight_raw", $product->get_weight(), $product, $item, $order);
        $weight       = apply_filters("puiw_invoice_item_get_product_weight",    $this->fn->get_format_weight($weight_raw), $product, $item, $order);
        $dimension    = apply_filters("puiw_invoice_item_get_product_dimension", $this->fn->get_product_dimension($item->get_product_id()), $product, $item, $order);
        $percentage   = (100 - ((float)$item->get_total() / (float)$item->get_subtotal() * 100));
        $percentage   = ($percentage > 0) ? sprintf("%2d%%", $percentage) : "00.0%";
        $discount     = apply_filters("puiw_invoice_item_get_product_discount", $percentage, $product, $item, $order);
        $sku          = $product->get_sku();
        if ($opt["show_product_sku2_inventory"] == "yes") {
          $sku = empty($sku) || !$sku ? "#$product_id" : $sku;
        }
        $product_weight = $weight_raw ? $weight_raw : 0;
        if ($product_weight) {
          $total_weight += floatval($product_weight * $quantity);
          $opt["invoice_total_weight"] = apply_filters("puiw_printinventory_calculate_invoice_total_weight", $this->fn->get_format_weight($total_weight), $total_weight, $product_weight, $quantity, $product, $item, $order);
        }
        $opt["invoice_total_qty"] = (int) $opt["invoice_total_qty"] + $quantity;
        switch ($opt["price_inventory_report"]) {
            //show_only_regular_price, show_only_sale_price, show_both_regular_and_sale_price
          case 'show_only_regular_price':
            $base_price = wc_price($product->get_regular_price(), array('currency' => $order->get_currency()));
            break;
          case 'show_only_sale_price':
            $base_price = wc_price($product->get_sale_price(), array('currency' => $order->get_currency()));
            break;
          case 'show_both_regular_and_sale_price':
            // $opt["show_product_image"] = "yes";
            $base_price = wc_price($product->get_price(), array('currency' => $order->get_currency()));
            break;
          case 'hide_all_price':
            $base_price = "";
            break;
          default:
            // $base_price = wc_price($item->get_total() / $item->get_quantity());
            $base_price = wc_price($order->get_item_subtotal($item, false, true), array('currency' => $order->get_currency()));
            break;
        }

        if ($this->is_bundled_child($item_id)) {
          $base_price = "";
          $subtotal = "";
          if ($this->_woosb_show_bundled_hierarchy == "yes") {
            $n--;
          }
        }

        $optm = array(
          "n" => $n,
          "img" => $product->get_image(array(50, 50)),
          "sku" => $sku,
          "title" => $name,
          "qty" => $quantity,
          "base_price" => $base_price,
          "description" => $description,
          "extra_classes" => implode(" ", (array) $extra_classes),
          "discount" => $discount,
          "weight" => $weight,
          "total_weight" => apply_filters(
            "puiw_printinventory_create_html_total_weight",
            $this->fn->get_format_weight(floatval($product_weight * $quantity)),
            floatval($product_weight * $quantity),
            $product_weight,
            $quantity,
            $item_id,
            $item,
            $product_id,
            $order
          ),
          "dimension" => $dimension,
          "tax" => $tax,
          "nettotal" => $subtotal,
          "shelf_number_id" => get_post_meta($product_id, "_shelf_number_id", true),
        );
        $optm = apply_filters("puiw_printinventory_create_html_item_row_metas", $optm, $item_id, $item, $product_id, $order);

        foreach ($optm as $key => $value) {
          if (!in_array($key, $keepOriginalENnumbers)) {
            $value = $this->fn->parse_number($value);
          }
          $product_row = str_replace("{{{{$key}}}}", $value, $product_row);
        }
        $opt["invoice_products_list"] .= $product_row;
      }
      foreach ($opt as $key => $value) {
        if (!in_array($key, $keepOriginalENnumbers)) {
          $value = $this->fn->parse_number($value);
        }
        $value = apply_filters("puiw_printinventory_create_html_options_{$key}_value", $value, $key, $order);
        if (!in_array($key, $keepOriginalHTMLtags)) {
          $template = str_replace("{{{{$key}}}}", wp_strip_all_tags($value, 1), ($template));
        } else {
          $template = str_replace("{{{{$key}}}}", $value, $template);
        }
      }
      if (apply_filters("puiw_printinventory_return_html_minfied", true, $template, $opt, $order)) {
        $template = $this->minify_html($template);
      }
      do_action("puiw_printinventory_after_create_html", $opt, $opts, $order);
      do_action("puiw_printinventory_before_return_html", $opt, $opts, $order);
      echo apply_filters("puiw_printinventory_return_html", $template, $opt, $opts, $order);
      do_action("puiw_printinventory_after_return_html", $opt, $opts, $order);
      $html_output = ob_get_contents();
      ob_end_clean();
      return $html_output;
    }
    protected function CheckPDFRequirements($print = false, $forHTML = false) {
      $ok = true;
      $items = array(
        "mbstring"          => "extension",
        "mb_regex_encoding" => "function",
        "gd"                => "extension",
        "zlib"              => "extension",
        "bcmath"            => "extension",
        "xml"               => "extension",
        "curl"              => "extension",
      );
      if (true == $forHTML) {
        $items = array(
          "gd"              => "extension"
        );
      }
      foreach ($items as $key => $value) {
        if ($print) {
          if ("extension" == $value) {
            echo extension_loaded($key) ?
              "<p>Extension <strong>" . ucfirst($key) . "</strong>: OK</p>" :
              "<p>Extension <strong>" . ucfirst($key) . "</strong>: DOES NOT EXISTS</p>";
          } else {
            echo function_exists($key) ?
              "<p>Function <strong>" . ucfirst($key) . "</strong>: OK</p>" :
              "<p>Function <strong>" . ucfirst($key) . "</strong>: DOES NOT EXISTS</p>";
          }
        } else {
          if (false == $ok) {
            return false;
            continue;
          }
          if ("extension" == $value) {
            if (!extension_loaded($key)) {
              $ok = false;
            }
          } else {
            if (!function_exists($key)) {
              $ok = false;
            }
          }
        }
      }
      return $ok;
    }
    protected function CheckPDFRequirementsHTML($print = false) {
      return $this->CheckPDFRequirements($print, true);
    }
    protected function get_pdf_style($order_id = 0, $order = false) {
      // the markup is built from $opt["template"], so the stylesheet has to come from the
      // same place: asking the saved option instead meant a per order template, a
      // pre-invoice or an advanced e-mail send got its own html with the default css
      $opts = $this->get_default_dynamic_params($order_id, $order);
      $opts["invoice_final_prices"] = $this->get_order_final_prices_pdf($order);
      $opt = apply_filters("puiw_printinvoice_create_html_options", $opts, $order);
      $template = !empty($opt["template"]) ? $opt["template"] : $this->fn->get_template();
      $templateDirpath = apply_filters("puiw_get_template_dir_path", $template, $order);
      $template = $this->read_template_part($templateDirpath, "style.pdf.css");
      $opt["CURENT_DIR_URL"] = apply_filters("puiw_get_template_dir_url", plugin_dir_url($template . "/default.cfg"), $template, $order);

      $keepOriginalHTMLtags = $this->get_preserve_html_tags($opt, $order);
      $keepOriginalENnumbers = $this->get_preserve_english_numbers($opt, $order);
      foreach ($opt as $key => $value) {
        if (!in_array($key, $keepOriginalENnumbers)) {
          $value = $this->fn->parse_number($value);
        }
        $value = apply_filters("puiw_printinvoice_create_html_options_{$key}_value", $value, $key, $order);
        if (!in_array($key, $keepOriginalHTMLtags)) {
          $template = str_replace("{{{{$key}}}}", wp_strip_all_tags($value, 1), ($template));
        } else {
          $template = str_replace("{{{{$key}}}}", $value, $template);
        }
      }
      return $template;
    }
    protected function minify_html($input) {
      if (trim($input) === "") return $input;
      // Remove extra white-space(s) between HTML attribute(s)
      $input = preg_replace_callback(
        '#<([^\/\s<>!]+)(?:\s+([^<>]*?)\s*|\s*)(\/?)>#s',
        function ($matches) {
          return '<' . $matches[1] . preg_replace('#([^\s=]+)(\=([\'"]?)(.*?)\3)?(\s+|$)#s', ' $1$2', $matches[2]) . $matches[3] . '>';
        },
        str_replace("\r", "", $input)
      );
      // Minify inline CSS declaration(s)
      if (strpos($input, ' style=') !== false) {
        $input = preg_replace_callback(
          '#<([^<]+?)\s+style=([\'"])(.*?)\2(?=[\/\s>])#s',
          function ($matches) {
            return '<' . $matches[1] . ' style=' . $matches[2] . $this->minify_css($matches[3]) . $matches[2];
          },
          $input
        );
      }
      if (strpos($input, '</style>') !== false) {
        $input = preg_replace_callback(
          '#<style(.*?)>(.*?)</style>#is',
          function ($matches) {
            return '<style' . $matches[1] . '>' . $this->minify_css($matches[2]) . '</style>';
          },
          $input
        );
      }
      if (strpos($input, '</script>') !== false) {
        $input = preg_replace_callback(
          '#<script(.*?)>(.*?)</script>#is',
          function ($matches) {
            return '<script' . $matches[1] . '>' . $this->minify_js($matches[2]) . '</script>';
          },
          $input
        );
      }

      $minified = preg_replace(
        array(
          // t = text
          // o = tag open
          // c = tag close
          // Keep important white-space(s) after self-closing HTML tag(s)
          '#<(img|input)(>| .*?>)#s',
          // Remove a line break and two or more white-space(s) between tag(s)
          '#(<!--.*?-->)|(>)(?:\n*|\s{2,})(<)|^\s*|\s*$#s',
          '#(<!--.*?-->)|(?<!\>)\s+(<\/.*?>)|(<[^\/]*?>)\s+(?!\<)#s', // t+c || o+t
          '#(<!--.*?-->)|(<[^\/]*?>)\s+(<[^\/]*?>)|(<\/.*?>)\s+(<\/.*?>)#s', // o+o || c+c
          '#(<!--.*?-->)|(<\/.*?>)\s+(\s)(?!\<)|(?<!\>)\s+(\s)(<[^\/]*?\/?>)|(<[^\/]*?\/?>)\s+(\s)(?!\<)#s', // c+t || t+o || o+t -- separated by long white-space(s)
          '#(<!--.*?-->)|(<[^\/]*?>)\s+(<\/.*?>)#s', // empty tag
          '#<(img|input)(>| .*?>)<\/\1>#s', // reset previous fix
          '#(&nbsp;)&nbsp;(?![<\s])#', // clean up ...
          '#(?<=\>)(&nbsp;)(?=\<)#', // --ibid
          // Remove HTML comment(s) except IE comment(s)
          '#\s*<!--(?!\[if\s).*?-->\s*|(?<!\>)\n+(?=\<[^!])#s'
        ),
        array(
          '<$1$2</$1>',
          '$1$2$3',
          '$1$2$3',
          '$1$2$3$4$5',
          '$1$2$3$4$5$6$7',
          '$1$2$3',
          '<$1$2',
          '$1 ',
          '$1',
          ""
        ),
        $input
      );
      return (null !== $minified && !empty($minified)) ? $minified : $input;
    }
    protected function minify_css($input) {
      if (trim($input) === "") return $input;
      $minified = preg_replace(
        array(
          // Remove comment(s)
          '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
          // Remove unused white-space(s)
          '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~+]|\s*+-(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
          // Replace `0(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)` with `0`
          '#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
          // Replace `:0 0 0 0` with `:0`
          '#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
          // Replace `background-position:0` with `background-position:0 0`
          '#(background-position):0(?=[;\}])#si',
          // Replace `0.6` with `.6`, but only when preceded by `:`, `,`, `-` or a white-space
          '#(?<=[\s:,\-])0+\.(\d+)#s',
          // Minify string value
          '#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
          '#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
          // Minify HEX color code
          '#(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3#i',
          // Replace `(border|outline):none` with `(border|outline):0`
          '#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
          // Remove empty selector(s)
          '#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s'
        ),
        array(
          '$1',
          '$1$2$3$4$5$6$7',
          '$1',
          ':0',
          '$1:0 0',
          '.$1',
          '$1$3',
          '$1$2$4$5',
          '$1$2$3',
          '$1:0',
          '$1$2'
        ),
        $input
      );
      return (null !== $minified && !empty($minified)) ? $minified : $input;
    }
    protected function minify_js($input) {
      if (trim($input) === "") return $input;
      $minified = preg_replace(
        array(
          // Remove comment(s)
          '#\s*("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')\s*|\s*\/\*(?!\!|@cc_on)(?>[\s\S]*?\*\/)\s*|\s*(?<![\:\=])\/\/.*(?=[\n\r]|$)|^\s*|\s*$#',
          // Remove white-space(s) outside the string and regex
          '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/)|\/(?!\/)[^\n\r]*?\/(?=[\s.,;]|[gimuy]|$))|\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#s',
          // Remove the last semicolon
          '#;+\}#',
          // Minify object attribute(s) except JSON attribute(s). From `{'foo':'bar'}` to `{foo:'bar'}`
          '#([\{,])([\'])(\d+|[a-z_][a-z0-9_]*)\2(?=\:)#i',
          // --ibid. From `foo['bar']` to `foo.bar`
          '#([a-z0-9_\)\]])\[([\'"])([a-z_][a-z0-9_]*)\2\]#i'
        ),
        array(
          '$1',
          '$1$2',
          '}',
          '$1$3',
          '$1.$3'
        ),
        $input
      );
      return (null !== $minified && !empty($minified)) ? $minified : $input;
    }
    /**
     * hook to alter css styles
     *
     * @method  custom_css_per_invoice
     * @param   string $custom_css_style
     * @param   string $default
     * @return  string
     * @version 1.0.0
     * @since   1.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function custom_css_per_invoice($custom_css_style, $default) {
      if ($this->_woosb_show_bundled_hierarchy == "yes") {
        $custom_css_style .= "tr.even {background: var(--theme_color) !important;}";
        $custom_css_style .= "tr.odd {background: white !important;}";
        $custom_css_style .= ".woosb-order-item.woosb-item-child .show_product_image *,.woosb-order-item.woosb-item-child .show_product_n * {display: none;}";
      }
      return $custom_css_style;
    }
    /**
     * Get Order Final Prices
     *
     * @method  get_order_final_prices
     * @param   WC_Order $order
     * @return  string
     * @version 1.0.0
     * @since   1.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function get_order_final_prices($order) {
      ob_start();
      echo '<table style="width: 100%;float: left;">';
      foreach ($order->get_order_item_totals() as $key => $total) {
        ?>
        <tr class="puiw_totalrow <?php echo $key; ?>">
          <th style="border: none;" scope="row"><?php echo esc_html($total['label']); ?></th>
          <td style="border: none;"><?php echo ('payment_method' === $key) ? esc_html($total['value']) : wp_kses_post($total['value']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    ?></td>
        </tr>
        <?php
      }
      echo "</table>";
      $tr = ob_get_contents();
      $tr = apply_filters("puiw_printinvoice_get_order_final_prices", $tr, $order);
      ob_end_clean();
      return $tr;
    }
    /**
     * Get Order Final Prices Pdf
     *
     * @method  get_order_final_prices_pdf
     * @param   WC_Order $order
     * @return  string
     * @version 1.0.0
     * @since   1.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function get_order_final_prices_pdf($order) {
      ob_start();
      foreach ($order->get_order_item_totals() as $key => $total) {
        // one line per total; the <br> that used to follow each one doubled the gaps and
        // pushed the totals of a long order onto a page of their own
        printf("<p dir='%s' class='puiw-total-line puiw-total-%s'><span class='k'>%s</span> %s</p>", $this->page_direction(), esc_attr($key), esc_html($total['label']), ('payment_method' === $key) ? esc_html($total['value']) : wp_kses_post($total['value']));
      }
      $tr = ob_get_contents();
      $tr = apply_filters("puiw_printinvoice_get_order_final_prices_pdf", $tr, $order);
      ob_end_clean();
      return apply_filters("puiw_return_pdf_total_prices_as_single_price", false, $order) ? $order->get_order_item_totals()["order_total"]["value"] : $tr;
    }
    /**
     * show item meta supporting WPC Product Bundles and wc hooks
     *
     * @param   int $item_id
     * @param   Object $item
     * @param   WC_Product $product
     * @return  string
     * @version 1.0.0
     * @since   1.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    private function get_item_meta($item_id, $item, $product) {
      ob_start();
      $ob_get_contents = "";
      $echo = "";
      $found_any = false;
      if (!$item || empty($item)) return '';
      $hidden_order_itemmeta = apply_filters('woocommerce_hidden_order_itemmeta', array('_qty', '_tax_class', '_product_id', '_variation_id', '_line_subtotal', '_line_subtotal_tax', '_line_total', '_line_tax', 'method_id', 'cost', '_reduced_stock',));

      do_action('woocommerce_before_order_itemmeta', $item_id, $item, $product);

      if ($meta_data = $item->get_formatted_meta_data('')) {
        $echo .= "<div class='view order_item_meta'><div cellspacing='0' class='display_meta'>";
        foreach ($meta_data as $meta_id => $meta) {
          if (in_array($meta->key, $hidden_order_itemmeta, true)) {
            continue;
          }
          if ($this->_woosb_show_bundles_subtitle == "no" && "bundled-products" === sanitize_title($meta->key, "")) {
            continue;
          }
          if ($this->_woosb_show_bundles_subtitle == "yes" && "bundled-products" === sanitize_title($meta->key, "")) {
            $meta->display_key = $this->_woosb_show_bundles_prefix;
          }
          $found_any = true;
          $echo .= "<div style='background: unset;' class='" . sanitize_title("$meta->key", "") . "'>
                      <span class='itemmeta_label' style='font-weight: bold'>" . wp_kses_post($meta->display_key) . ":</span>
                      <span class='itemmeta_value'> " . strip_tags(force_balance_tags($meta->display_value)) . "</span>
              </div>";
        }
        if ($found_any) {
          echo "$echo</div></div>";
        }
        $ob_get_contents = ob_get_contents();
        ob_end_clean();
      }

      do_action('woocommerce_after_order_itemmeta', $item_id, $item, $product);

      return $ob_get_contents;
    }
    /**
     * WPC Product Bundles Hide Bundles Parent
     *
     * @method  woosb_puiw_hide_bundles_parent
     * @param   array $data_list
     * @param   WC_Order $order
     * @return  array
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function woosb_puiw_hide_bundles_parent($data_list, $order) {
      foreach ($data_list as $key => $data) {
        $bundles = wc_get_order_item_meta($key, '_woosb_ids', true);
        if (! empty($bundles)) {
          unset($data_list[$key]);
        }
      }
      return $data_list;
    }
    /**
     * WPC Product Bundles Hide Bundled Childs
     *
     * @method  woosb_puiw_hide_bundled_childs
     * @param   array $data_list
     * @param   WC_Order $order
     * @return  array
     * @version 1.0.0
     * @since   1.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function woosb_puiw_hide_bundled_childs($data_list, $order) {
      foreach ($data_list as $key => $data) {
        $bundled = wc_get_order_item_meta($key, '_woosb_parent_id', true);
        if (! empty($bundled)) {
          unset($data_list[$key]);
        }
      }
      return $data_list;
    }
    /**
     * WPC Product Bundles isBundledParent
     *
     * @method  is_bundled_parent
     * @param   string $key
     * @return  boolean
     * @version 1.0.0
     * @since   1.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function is_bundled_parent($key) {
      return wc_get_order_item_meta($key, '_woosb_ids', true);
    }
    /**
     * WPC Product Bundles isBundledChild
     *
     * @method  is_bundled_child
     * @param   string $key
     * @return  boolean
     * @version 1.0.0
     * @since   1.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function is_bundled_child($key) {
      return wc_get_order_item_meta($key, '_woosb_parent_id', true);
    }
    /**
     * invoice items tr html classes
     *
     * @method  puiw__item_extra_classes
     * @param   array $classes
     * @param   WC_Product $product
     * @param   int $item_id
     * @param   Object $item
     * @param   WC_Order $order
     * @param   int $nthitem
     * @return  array classes
     * @version 1.0.0
     * @since   1.0.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function puiw__item_extra_classes($classes, $product, $item_id, $item, $order, $nthitem) {
      if ($nthitem % 2) {
        $odd_even = "odd";
      } else {
        $odd_even = "even";
      }

      global $puiw_last_woosb_bundle_parent_index, $puiw_last_woosb_bundle_child_index;


      if ($this->is_bundled_parent($item_id)) {
        $classes[] = "woosb-order-item woosb-item-parent";
        if ($this->_woosb_show_bundled_hierarchy == "yes") {
          $puiw_last_woosb_bundle_child_index = false;
          $puiw_last_woosb_bundle_parent_index = $nthitem;
          $classes[] = $odd_even;
          return $classes;
        }
      }
      if ($this->is_bundled_child($item_id)) {
        $classes[] = "woosb-order-item woosb-item-child";
        if ($this->_woosb_show_bundled_hierarchy == "yes") {
          if ($puiw_last_woosb_bundle_parent_index % 2) {
            $odd_even = "odd";
          } else {
            $odd_even = "even";
          }
          $puiw_last_woosb_bundle_child_index = $nthitem;
          $classes[] = $odd_even;
          return $classes;
        }
      }
      if ($this->_woosb_show_bundled_hierarchy == "yes") {
        if ($puiw_last_woosb_bundle_child_index && $puiw_last_woosb_bundle_child_index > 0) {
          if (($nthitem - 1 - $puiw_last_woosb_bundle_child_index) % 2) {
            $odd_even = "odd";
          } else {
            $odd_even = "even";
          }
          $classes[] = $odd_even;
          return $classes;
        }
      }

      $classes[] = $odd_even;
      return $classes;
    }
    /**
     * add bundled in to bundled products child
     *
     * @method  woosb_before_order_item_meta
     * @param   int $order_item_id
     * @return  string
     * @version 1.0.0
     * @since   1.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function woosb_before_order_item_meta($order_item_id) {
      if ($parent_id = wc_get_order_item_meta($order_item_id, '_woosb_parent_id', true)) {
        if ($this->_woosb_show_bundled_subtitle == "yes") {
          echo $this->_woosb_show_bundled_prefix . " " . get_the_title($parent_id);
        }
      }
    }
    /**
     * Sort Order Items
     *
     * @method  puiw_sort_order_items
     * @param   array $items
     * @param   WC_Order $order
     * @return  array $items
     * @version 1.0.0
     * @since   1.3.0
     * @license https://peprodev.com/license Pepro.dev License
     */
    public function puiw_sort_order_items($items, $order) {
      if (count($items) > 1) {
        $item_qty = array();
        $item_order = array();
        $sorted_items = array();
        $sort_by = apply_filters("puiw_order_items_sort_by", $this->fn->get_items_sorting("NONE"));
        if (!$sort_by || empty($sort_by) || $sort_by == "NONE") {
          return $items;
        }
        foreach ($items as $items_id => $item) {
          if ($item->is_type('line_item') && method_exists($item, 'get_product')) {
            $product = $item->get_product();
            if (is_a($product, 'WC_Product')) {
              $item_order_data = array(
                "ID" => $items_id,
                'PID' => $item->get_product_id(),
                'SKU' => $product->get_sku(),
                'QTY' => $item->get_quantity(),
                'NAME' => $item->get_name(),
                'PRICE' => $order->get_item_subtotal($item, false, true),
                'TOTAL' => $item->get_total(),
                'WEIGHT' => $product->get_weight(),
                'SUBTOTAL' => $item->get_subtotal(),
                'SUBTOTAL_TAX' => $item->get_subtotal_tax(),
              );
              $item_order[$items_id] = apply_filters("puiw_order_items_sort_by_force", $item_order_data, $item_order, $items_id, $item, $product, $items, $sort_by);
              $item_order[$items_id]["_id"] = $items_id;
            }
          }
        }
        if (!empty($item_order)) {
          $col = array_column($item_order, $sort_by);
          // an unknown sort key yields an empty column, and array_multisort() on
          // mismatched sizes is a fatal, so leave the items in their natural order
          if (count($col) !== count($item_order)) {
            return $items;
          }
          if (apply_filters("puiw_order_items_sort_desc", false) === false) {
            array_multisort($col, SORT_ASC, $item_order);
          } else {
            array_multisort($col, SORT_DESC, $item_order);
          }
          foreach ($item_order as $i => $x) {
            $sorted_items[$x["_id"]] = $items[$x["_id"]];
          }
          return $sorted_items;
        }
      }
      return $items;
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
    private function parseTemplate($contents) {
      $styleExifDAta = array();
      foreach (explode("\n", $contents) as $line) {
        $data = explode(":", $line);
        if (count($data) == 2) {
          $styleExifDAta[strtolower(trim($data[0]))] = trim($data[1]);
        }
      }
      return apply_filters("puiw_parse_pdf_template", $styleExifDAta, $contents);
    }
  }
}
/*##################################################
Lead Developer: [amirhp-com](https://amirhp.com/)
##################################################*/
