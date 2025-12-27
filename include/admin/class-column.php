<?php
/*
 * @Author: Amirhossein Hosseinpour <https://amirhp.com>
 * @Date Created: 2020/09/20 23:08:04
 * @Last modified by: amirhp-com <its@amirhp.com>
 * @Last modified time: 2025/12/27 19:03:06
 */

namespace peproulitmateinvoice;
use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

defined("ABSPATH") or die("Pepro Ultimate Invoice :: Unauthorized Access!");

if (!class_exists("PeproUltimateInvoice_Columns")) {
  class PeproUltimateInvoice_Columns {
    private $td;
    public function __construct() {
      $this->td = "pepro-ultimate-invoice";
      add_action("admin_enqueue_scripts", array($this, "admin_enqueue_scripts"));

      if ( class_exists("\Automattic\WooCommerce\Utilities\OrderUtil") && OrderUtil::custom_orders_table_usage_is_enabled()) {
        // HPOS usage is enabled.
        add_filter("manage_woocommerce_page_wc-orders_columns", array($this, "column_header"));
        add_action("manage_woocommerce_page_wc-orders_custom_column", array($this, "column_content"), 20, 2);
      } else {
        // Traditional CPT-based orders are in use.
        add_filter("manage_edit-shop_order_columns",        array($this, "column_header"));
        add_action("manage_shop_order_posts_custom_column", array($this, "column_content"), 20, 2);
      }
    }
    public function localize_script() {
      return array(
        "td"                  => "puiw_{$this->td}",
        "ajax"                => admin_url("admin-ajax.php"),
        "home"                => home_url(),
        "nonce"               => wp_create_nonce("pepro-ultimate-invoice"),
        "title"               => _x("Select image file", "wc-setting-js", "pepro-ultimate-invoice"),
        "btntext"             => _x("Use this image", "wc-setting-js", "pepro-ultimate-invoice"),
        "clear"               => _x("Clear", "wc-setting-js", "pepro-ultimate-invoice"),
        "currentlogo"         => _x("Current preview", "wc-setting-js", "pepro-ultimate-invoice"),
        "selectbtn"           => _x("Select image", "wc-setting-js", "pepro-ultimate-invoice"),
        "plugin_url"          => PEPROULTIMATEINVOICE_URL,

        "rtl"                => is_rtl() ? 1 : 0,
        "tr_submit"          => _x("Submit", "js-string", "pepro-ultimate-invoice"),
        "tr_today"           => _x("Today", "js-string", "pepro-ultimate-invoice"),
        "errorTxt"           => _x("Error", "wc-setting-js", "pepro-ultimate-invoice"),
        "cancelTtl"          => _x("Canceled", "wc-setting-js", "pepro-ultimate-invoice"),
        "confirmTxt"         => _x("Confirm", "wc-setting-js", "pepro-ultimate-invoice"),
        "loading"            => _x("Loading ...", "wc-setting-js", "pepro-ultimate-invoice"),
        "successTtl"         => _x("Success", "wc-setting-js", "pepro-ultimate-invoice"),
        "submitTxt"          => _x("Submit", "wc-setting-js", "pepro-ultimate-invoice"),
        "okTxt"              => _x("Okay", "wc-setting-js", "pepro-ultimate-invoice"),
        "txtYes"             => _x("Yes", "wc-setting-js", "pepro-ultimate-invoice"),
        "txtNop"             => _x("No", "wc-setting-js", "pepro-ultimate-invoice"),
        "cancelbTn"          => _x("Cancel", "wc-setting-js", "pepro-ultimate-invoice"),
        "sendTxt"            => _x("Send to all", "wc-setting-js", "pepro-ultimate-invoice"),
        "closeTxt"           => _x("Close", "wc-setting-js", "pepro-ultimate-invoice"),
        "attach"             => _x("Attach a PDF version to email?", "wc-setting-js", "pepro-ultimate-invoice"),
        "emailCustomerAsk"   => _x("Are you sure you want to email current order's invoice to customer's email address?<br>Customer Email Address: %s", "wc-setting-js", "pepro-ultimate-invoice"),
        "emailCustomlistAsk" => _x("<p>Enter Email list below by adding addresses following by Enter/Space key.</p><p>You can also bulk import Emails from Excel/CSV or any other formats.</p>", "wc-setting-js", "pepro-ultimate-invoice"),
        "emailShopMngrAsk"   => _x("Are you sure you want to email current order's invoice to shop managers listed below?<br>%s", "wc-setting-js", "pepro-ultimate-invoice"),
        "emailCustomerTitle" => _x("Email Invoice to Customer", "wc-setting-js", "pepro-ultimate-invoice"),
        "emailShopMngrTitle" => _x("Email Invoice to Shop managers", "wc-setting-js", "pepro-ultimate-invoice"),
        "emailCustomTitle"   => _x("Email Invoice to Custom List", "wc-setting-js", "pepro-ultimate-invoice"),
        "anErrExprienced"    => _x("An Error Has Occurred", "wc-setting-js", "pepro-ultimate-invoice"),
        "anEmailisrequid"    => _x("Enter at least an email address", "wc-setting-js", "pepro-ultimate-invoice"),

      );
    }
    public function debugEnabled($debug_true = true, $debug_false = false) {
      return apply_filters("puiw_debug_enabled", $debug_true);
    }
    public function admin_enqueue_scripts() {
      wp_register_script("jquery-confirm",                            PEPROULTIMATEINVOICE_ASSETS_URL . "/js/jquery-confirm.min.js", array("jquery"));
      wp_register_style("jquery-confirm",                             PEPROULTIMATEINVOICE_ASSETS_URL . "/css/jquery-confirm.min.css", array(), '1.0', 'all');
      wp_register_style("fontawesome",                                PEPROULTIMATEINVOICE_ASSETS_URL . "/fontawesome-free/css/all.css", array(), '5.15.3', 'all');
      wp_register_script("pepro-ultimate-invoice-multiple-emails",    PEPROULTIMATEINVOICE_ASSETS_URL . "/js/multiple-emails" . $this->debugEnabled(".js", ".min.js"), array("jquery"));
      wp_register_style("pepro-ultimate-invoice-multiple-emails",     PEPROULTIMATEINVOICE_ASSETS_URL . "/css/multiple-emails" . $this->debugEnabled(".css", ".min.css"), array(), '1.0', 'all');
      wp_register_script("pepro-ultimate-invoice-orders-options",     PEPROULTIMATEINVOICE_ASSETS_URL . "/admin/wc_orders" . $this->debugEnabled(".js", ".min.js"), array("jquery"), current_time('timestamp'));
      wp_localize_script("pepro-ultimate-invoice-orders-options",     "_i18n", $this->localize_script());
      wp_register_script("pepro-ultimate-invoice-nicescroll",         PEPROULTIMATEINVOICE_ASSETS_URL . "/js/jquery.nicescroll.min.js", array("jquery"), '1.0.2');
      wp_register_style("pepro-ultimate-invoice-orders-options",      PEPROULTIMATEINVOICE_ASSETS_URL . "/admin/wc_orders" . $this->debugEnabled(".css", ".min.css"));
      wp_register_script("pepro-ultimate-invoice-persian-date",       PEPROULTIMATEINVOICE_ASSETS_URL . "/js/persian-date.min.js", array("jquery"), '1.0.2');
      wp_register_script("pepro-ultimate-invoice-persian-datepicker", PEPROULTIMATEINVOICE_ASSETS_URL . "/js/persian-datepicker.min.js", array("jquery"), '1.0.2');
      wp_register_style("pepro-ultimate-invoice-persian-datepicker",  PEPROULTIMATEINVOICE_ASSETS_URL . "/css/persian-datepicker.min.css");
    }
    /**
     * Adds 'Ultimate Invoice' column header to 'Orders' page immediately after 'Status' column.
     *
     * @param  array $columns
     * @return array $new_columns
     */
    public function column_header($columns) {
      $new_columns = array();
      foreach ($columns as $column_name => $column_info) {
        $new_columns[$column_name] = $column_info;
        if ('order_status' === $column_name) {
          $new_columns['ultimate_invoice'] = __('Ultimate Invoice', "pepro-ultimate-invoice");
        }
      }
      if (!isset($new_columns['ultimate_invoice'])) {
        $new_columns['ultimate_invoice'] = __('Ultimate Invoice', "pepro-ultimate-invoice");
      }
      return $new_columns;
    }
    /**
     * Adds 'Ultimate Invoice' column content to 'Orders' page immediately after 'Status' column.
     *
     * @param array $column name of column being displayed
     */
    public function column_content($column, $order_id) {
      global $post;
      if (!wp_script_is("pepro-ultimate-invoice-orders-options")) {
        add_thickbox();
        wp_enqueue_media();
        wp_enqueue_script("jquery");
        wp_enqueue_style("wp-color-picker");
        wp_enqueue_script("wp-color-picker");
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-selectmenu');

        wp_enqueue_style("fontawesome");
        wp_enqueue_style("jquery-confirm");
        wp_enqueue_script('jquery-confirm');

        wp_enqueue_style("pepro-ultimate-invoice-persian-datepicker");
        wp_enqueue_style("pepro-ultimate-invoice-multiple-emails");
        wp_enqueue_style("pepro-ultimate-invoice-orders-options");
        wp_enqueue_script("pepro-ultimate-invoice-nicescroll");
        wp_enqueue_script("pepro-ultimate-invoice-multiple-emails");
        wp_enqueue_script("pepro-ultimate-invoice-persian-date");
        wp_enqueue_script("pepro-ultimate-invoice-persian-datepicker");
        wp_enqueue_script("pepro-ultimate-invoice-orders-options");
        echo          "<script>var CURRENT_ORDER_MAIL = [];</script>";
      }
      if ('ultimate_invoice' === $column) {
        $order    = wc_get_order($order_id);
        $total    = (float) $order->get_total();
        $email    = $order->get_billing_email();
        $id       = $order->get_id();
        $col_data = "<script>CURRENT_ORDER_MAIL['$id'] = '$email';</script>" . $this->popup_html_data($id);
        echo apply_filters("pepro_ultimate_invoice_orders_column_data", $col_data, $order_id ? $order_id : $post->ID);
      }
    }
    public function popup_html_data($id, $mode = true) {
      // <a class='button pwui_opts' href='#' data-ref='{$id}'>"._x("Invoice Options","wc-orders-popup","pepro-ultimate-invoice")."</a>
      $url1 = home_url("?invoice={$id}");
      $url2 = home_url("?invoice-pdf={$id}");
      $url4 = home_url("?invoice-inventory={$id}");
      $url3 = home_url("?invoice-slips={$id}");
      $url11 = home_url("?invoice-slips-pdf={$id}");
      $url12 = home_url("?invoice-pos={$id}");
      $items = "";
      if ($mode) {
        ob_start();
        ?>
        <a class='button admn pwui_opts maincog' href='#' data-ref='<?=esc_attr($id)?>' rel='puiw_tooltip' title="<?=esc_attr_x("View Invoice Options", "wc-orders-popup", "pepro-ultimate-invoice");?>">
         <svg style="width: 24px; height: 24px;" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 24 24" width="192px" height="192px"><path d="M 11.5 0 C 10.119 0 9 1.119 9 2.5 L 9 4 L 5 4 C 3.895 4 3 4.895 3 6 L 3 9 C 3 9.552 3.448 10 4 10 L 4.3574219 10 C 5.6654219 10 6.8553281 10.941188 6.9863281 12.242188 C 7.1363281 13.739187 5.966 15 4.5 15 L 4 15 C 3.448 15 3 15.448 3 16 L 3 19 C 3 20.105 3.895 21 5 21 L 8 21 C 8.552 21 9 20.552 9 20 L 9 19.642578 C 9 18.334578 9.9411875 17.144672 11.242188 17.013672 C 12.739187 16.863672 14 18.034 14 19.5 L 14 20 C 14 20.552 14.448 21 15 21 L 18 21 C 19.105 21 20 20.105 20 19 L 20 15 L 21.5 15 C 22.881 15 24 13.881 24 12.5 C 24 11.119 22.881 10 21.5 10 L 20 10 L 20 6 C 20 4.895 19.105 4 18 4 L 14 4 L 14 2.5 C 14 1.119 12.881 0 11.5 0 z"/></svg>
        </a>
        <a class='button admn pwui_opts html' href='<?= esc_attr($url1) ?>' data-ref='<?=esc_attr($id)?>' target='_blank' rel='puiw_tooltip' title='<?=esc_attr_x("View Order HTML Invoice", "wc-orders-popup", "pepro-ultimate-invoice");?>'>
          <svg style="width: 24px; height: 24px;" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 24 24" width="192px" height="192px"><path d="M13.172,2H6C4.9,2,4,2.9,4,4v16c0,1.1,0.9,2,2,2h12c1.1,0,2-0.9,2-2V8.828c0-0.53-0.211-1.039-0.586-1.414l-4.828-4.828 C14.211,2.211,13.702,2,13.172,2z M15,18H9c-0.552,0-1-0.448-1-1v0c0-0.552,0.448-1,1-1h6c0.552,0,1,0.448,1,1v0 C16,17.552,15.552,18,15,18z M15,14H9c-0.552,0-1-0.448-1-1v0c0-0.552,0.448-1,1-1h6c0.552,0,1,0.448,1,1v0 C16,13.552,15.552,14,15,14z M13,9V3.5L18.5,9H13z"/></svg>
        </a>
        <a class='button admn pwui_opts pdf' href='<?= esc_attr($url2) ?>' data-ref='<?=esc_attr($id)?>' target='_blank' rel='puiw_tooltip' title='<?=esc_attr_x("View Order PDF Invoice", "wc-orders-popup", "pepro-ultimate-invoice");?>'>
         <svg style="width: 24px; height: 24px;" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 24 24" width="192px" height="192px"><path d="M14,2H6C4.9,2,4,2.9,4,4v16c0,1.1,0.9,2,2,2h12c1.1,0,2-0.9,2-2V8L14,2z M7.6,17.2l1.5-2c0.2-0.267,0.6-0.267,0.8,0 l1.1,1.467l2.1-2.8c0.2-0.267,0.6-0.267,0.8,0l2.5,3.333c0.247,0.33,0.012,0.8-0.4,0.8H8C7.588,18,7.353,17.53,7.6,17.2z M13,9V3.5 L18.5,9H13z"/></svg>
        </a>
        <a class='button admn pwui_opts pdf' href='<?= esc_attr($url12) ?>' data-ref='<?=esc_attr($id)?>' target='_blank' rel='puiw_tooltip' title='<?=esc_attr_x("View Order POS PDF Invoice", "wc-orders-popup", "pepro-ultimate-invoice");?>'>
          <svg style="width: 24px; height: 24px;" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 24 24" width="192px" height="192px"><path d="M 8 2 C 6.3550302 2 5 3.3550302 5 5 L 5 16 L 3 16 A 1.0001 1.0001 0 0 0 2 17 L 2 19 C 2 20.64497 3.3550302 22 5 22 L 14 22 L 15 22 C 16.64497 22 18 20.64497 18 19 L 18 8 L 21 8 A 1.0001 1.0001 0 0 0 22 7 L 22 5 C 22 3.4284616 20.759624 2.1350333 19.214844 2.0214844 A 1.0001 1.0001 0 0 0 19 2 L 8 2 z M 19 4 C 19.56503 4 20 4.4349698 20 5 L 20 6 L 18 6 L 18 5 C 18 4.4349698 18.43497 4 19 4 z M 4 18 L 5.8320312 18 A 1.0001 1.0001 0 0 0 6.1582031 18 L 11.990234 18 L 12 19.023438 A 1.0001 1.0001 0 0 0 12 19.027344 C 12.004654 19.369889 12.081227 19.693696 12.193359 20 L 5 20 C 4.4349698 20 4 19.56503 4 19 L 4 18 z"/></svg>
        </a>
        <?php
        $items = ob_get_clean();
      }
      return ($mode ? $items : "") . "<div class='pwui_overlyblockui' data-ref='{$id}'></div><div class='pwui_ajax_data' data-ref='{$id}'>
            <div class='piuw_toolkit'>
              <a class='puiw_close_overly' title='" . _x("Close", "close-btn", "pepro-ultimate-invoice") . "' rel='puiw_tooltip' href='#'><span class='dashicons dashicons-no'></span></a>
              <a class='secondary puiw_back_overly' data-ref='{$id}' title='" . _x("Back to menu", "wc-orders-popup", "pepro-ultimate-invoice") . "' rel='puiw_tooltip' href='#'><span class='dashicons dashicons-undo'></span></a>
              <a class='secondary puiw_open_newtab' data-ref='{$id}' title='" . _x("Open in new Tab", "wc-orders-popup", "pepro-ultimate-invoice") . "' rel='puiw_tooltip' href='#'><span class='dashicons dashicons-external'></span></a>
              <a class='secondary puiw_print_overly' data-ref='{$id}' title='" . _x("Print Now", "wc-orders-popup", "pepro-ultimate-invoice") . "' rel='puiw_tooltip' href='#'><span class='dashicons dashicons-media-document'></span></a>
              <a class='secondary puiw_download_pdf' data-ref='{$id}' title='" . _x("Download PDF", "wc-orders-popup", "pepro-ultimate-invoice") . "' rel='puiw_tooltip' href='#'><span class='dashicons dashicons-download'></span></a>
              <a class='secondary puiw_download_slip_pdf' data-ref='{$id}' title='" . _x("Download PDF", "wc-orders-popup", "pepro-ultimate-invoice") . "' rel='puiw_tooltip' href='#'><span class='dashicons dashicons-download'></span></a>
            </div>
            <div class='ajax_data'></div>
          </div>
          <div class='pwui_overly' data-ref='{$id}'>
            <div class='piuw_toolkit'>
              <a class='puiw_close_overly' title='" . _x("Close", "wc-orders-popup", "pepro-ultimate-invoice") . "' rel='puiw_tooltip' href='#'><span class='dashicons dashicons-no'></span></a>
            </div>
            <h2>" . sprintf(_x("Invoice #%s Options", "wc-orders-popup", "pepro-ultimate-invoice"), $id) . "</h2>
            <ul class='inner_content' data-ref='{$id}'>
              <li><img style=\"display: inline-block;-webkit-margin-end: 5px;margin-inline-end: 5px;-webkit-filter: invert(.9);filter: invert(.9);\" src='" . PEPROULTIMATEINVOICE_ASSETS_URL . "/img/document.png'/> <a            data-action='puiw_act4'  data-ref='{$id}' href='$url1'> "   . _x("HTML Invoice", "wc-orders-popup", "pepro-ultimate-invoice") . "</a></li>
              <li><img style=\"display: inline-block;-webkit-margin-end: 5px;margin-inline-end: 5px;-webkit-filter: invert(.9);filter: invert(.9);\" src='" . PEPROULTIMATEINVOICE_ASSETS_URL . "/img/pdf.png'/> <a                 data-action='puiw_act5'  data-ref='{$id}' href='$url2'> "   . _x("PDF Invoice", "wc-orders-popup", "pepro-ultimate-invoice") . "</a></li>
              <li><img style=\"display: inline-block;-webkit-margin-end: 5px;margin-inline-end: 5px;-webkit-filter: invert(.9);filter: invert(.9);\" src='" . PEPROULTIMATEINVOICE_ASSETS_URL . "/img/pdf.png'/> <a                 data-action='puiw_act12' data-ref='{$id}' href='$url12'> "  . _x("POS Invoice", "wc-orders-popup", "pepro-ultimate-invoice") . "</a></li>
              <li><img style=\"display: inline-block;-webkit-margin-end: 5px;margin-inline-end: 5px;-webkit-filter: invert(.9);filter: invert(.9);\" src='" . PEPROULTIMATEINVOICE_ASSETS_URL . "/img/document-delivery.png'/> <a   data-action='puiw_act3'  data-ref='{$id}' href='$url4'> "   . _x("Inventory report", "wc-orders-popup", "pepro-ultimate-invoice") . "</a></li>
              <li><img style=\"display: inline-block;-webkit-margin-end: 5px;margin-inline-end: 5px;-webkit-filter: invert(.9);filter: invert(.9);\" src='" . PEPROULTIMATEINVOICE_ASSETS_URL . "/img/unpacking.png'/> <a           data-action='puiw_act2'  data-ref='{$id}' href='$url3'> "   . _x("Packing Slip", "wc-orders-popup", "pepro-ultimate-invoice") . "</a></li>
              <li><img style=\"display: inline-block;-webkit-margin-end: 5px;margin-inline-end: 5px;-webkit-filter: invert(.9);filter: invert(.9);\" src='" . PEPROULTIMATEINVOICE_ASSETS_URL . "/img/unpacking.png'/> <a           data-action='puiw_act15' data-ref='{$id}' href='$url11'> "  . _x("Packing Slip PDF", "wc-orders-popup", "pepro-ultimate-invoice") . "</a></li>
              <li><img style=\"display: inline-block;-webkit-margin-end: 5px;margin-inline-end: 5px;-webkit-filter: invert(.9);filter: invert(.9);\" src='" . PEPROULTIMATEINVOICE_ASSETS_URL . "/img/mail-account.png'/> <a        data-action='puiw_act6'  data-ref='{$id}' href='#'> "       . _x("Mail Invoice to Customer", "wc-orders-popup", "pepro-ultimate-invoice") . "</a></li>
              <li><img style=\"display: inline-block;-webkit-margin-end: 5px;margin-inline-end: 5px;-webkit-filter: invert(.9);filter: invert(.9);\" src='" . PEPROULTIMATEINVOICE_ASSETS_URL . "/img/secure-mail.png'/> <a         data-action='puiw_act9'  data-ref='{$id}' href='#'> "       . _x("Mail Invoice to Shop Managers", "wc-orders-popup", "pepro-ultimate-invoice") . "</a></li>
              <li><img style=\"display: inline-block;-webkit-margin-end: 5px;margin-inline-end: 5px;-webkit-filter: invert(.9);filter: invert(.9);\" src='" . PEPROULTIMATEINVOICE_ASSETS_URL . "/img/edit-message.png'/> <a        data-action='puiw_act10' data-ref='{$id}' href='#'> "       . _x("Mail Invoice to Custom List", "wc-orders-popup", "pepro-ultimate-invoice") . "</a></li>
            </ul>
          </div>
          ";
    }
  }
}
