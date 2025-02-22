<?php
# @Last modified time: 2022/10/13 22:34:05

defined("ABSPATH") or die("Pepro Ultimate Invoice :: Unauthorized Access!");

add_filter("woocommerce_get_settings_pages", "PeproUltimateInvoice__wc_get_settings_pages");
/**
 * make woocommerce based setting pages and sections
 *
 * @method  woocommerce_get_settings_pages
 * @return  string
 * @access  public
 * @version 1.0.0
 * @since   1.0.0
 * @license https://pepro.dev/license      Pepro.dev License
 */
function PeproUltimateInvoice__wc_get_settings_pages($settings)
{
  if (!class_exists("PeproUltimateInvoice_WC_Setting")) {
    /**
     * Hook into woocommerce setting page
     */
    class PeproUltimateInvoice_WC_Setting extends WC_Settings_Page
    {

      public $td;

      public function __construct()
      {
        global $PeproUltimateInvoice;

        $this->td     = "pepro-ultimate-invoice";
        $this->id     = "pepro_ultimate_invoice";
        $this->label  = _x("Ultimate Invoice", "wc-setting", $this->td);

        add_action("woocommerce_settings_{$this->id}",       array( $this, 'output' ));
        add_action("woocommerce_settings_save_{$this->id}",  array( $this, 'save' ));
        add_action("woocommerce_sections_{$this->id}",       array( $this, 'output_sections' ));

        // parent::__construct(); #buf fix for WC.v.4.4

        if (isset($_GET["tab"]) && ($_GET["tab"] == $this->id)){

          $PeproUltimateInvoice->update_footer_info();

          add_action("wp_before_admin_bar_render", array( $PeproUltimateInvoice,'wp_before_admin_bar_render_back') );

        }

      }
      public function debugEnabled($debug_true = true, $debug_false = false)
      {
          return apply_filters("puiw_debug_enabled", $debug_true);
      }
      public function get_sections()
      {
        if (isset($_GET["tab"]) && !empty($_GET["tab"]) && "pepro_ultimate_invoice" === $_GET["tab"]){
          // prevent gap between page load css and page first byte transfer
          echo get_option( "puiw_dark_mode", "no") === "yes" ? "<script>document.getElementsByTagName('html')[0].classList.add('dark');</script>" : "";
          $this->wp_enqueue_scripts();
        }
        $sections = array(
          'general' => _x("Store Details", "wc-setting", $this->td),
          'items'   => _x("Invoice Items", "wc-setting", $this->td),
          'pdf'     => _x("PDF Invoice", "wc-setting", $this->td),
          'theme'   => _x("Theming", "wc-setting", $this->td),
          'report'  => _x("Inventory", "wc-setting", $this->td),
          'email'   => _x("Email", "wc-setting", $this->td),
          'barcode' => _x("Barcode & QR", "wc-setting", $this->td),
          'privacy' => _x("Privacy", "wc-setting", $this->td),
          'color'   => _x("Color Schemes", "wc-setting", $this->td),
          'integ'   => _x("Integration", "wc-setting", $this->td),
          'extras'  => _x("Extra Features", "wc-setting", $this->td),
          'misc'    => _x("Misc.", "wc-setting", $this->td),
          'migrate' => _x("Migrate/Backup", "wc-setting", $this->td),
          'debug'   => _x("Debug", "wc-setting", $this->td),
        );
        return apply_filters('woocommerce_get_sections_' . $this->id, $sections);
      }
      public function get_settings($current_section="")
      {
        global $PeproUltimateInvoice;
        $i = $current_section;
        switch ($current_section) {
          case 'items':
            $section_data = apply_filters( "puiw_setting_section_{$current_section}",
                array(
                  "puiw_items_title" => array(
                    'name' => _x("Show/Hide items in Invoices", "wc-setting", $this->td),
                    'type' => 'title',
                    'id'=> 'puiw_items_title'
                  ),
                  'puiw_show_store_national_id' => array(
                    'name' => _x("Show Store National Id", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_store_national_id',
                  ),
                  'puiw_show_store_registration_number' => array(
                    'name' => _x("Show Store Registration Number", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_store_registration_number',
                  ),
                  'puiw_show_store_economical_number' => array(
                    'name' => _x("Show Store Economical Number", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_store_economical_number',
                  ),
                  'puiw_show_customer_address' => array(
                    'name' => _x("Show Customer Address", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_customer_address',
                  ),
                  'puiw_show_customer_phone' => array(
                    'name' => _x("Show Customer Phone", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_customer_phone',
                  ),
                  'puiw_show_customer_email' => array(
                    'name' => _x("Show Customer E-mail", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_customer_email',
                  ),
                  'puiw_show_order_date' => array(
                    'name' => _x("Show Order Date", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_order_date',
                  ),
                  'puiw_show_payment_method' => array(
                    'name' => _x("Show Payment method", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_payment_method',
                  ),
                  'puiw_show_shipping_method' => array(
                    'name' => _x("Show Shipping method", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_shipping_method',
                  ),
                  'puiw_transaction_ref_id' => array(
                    'name' => _x("Show Transaction Ref. ID", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_transaction_ref_id',
                  ),
                  'puiw_paid_date' => array(
                    'name' => _x("Show Paid Date", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_paid_date',
                  ),
                  'puiw_purchase_complete_date' => array(
                    'name' => _x("Show Purchase Completed Date", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_purchase_complete_date',
                  ),
                  'puiw_shipping_date' => array(
                    'name' => _x("Show Shipping Date", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_shipping_date',
                  ),
                  'puiw_order_status' => array(
                    'name' => _x("Show Order Status", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_order_status',
                  ),
                  'puiw_show_product_image' => array(
                    'name' => _x("Show Product Image Column", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_product_image',
                  ),
                  'puiw_show_product_purchase_note' => array(
                    'name' => _x("Show Product Purchase note", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_product_purchase_note',
                  ),
                  'puiw_show_order_items' => array(
                    'name' => _x("Show Order Items", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_order_items',
                  ),
                  'puiw_show_order_total' => array(
                    'name' => _x("Show Order Total", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_order_total',
                  ),
                  'puiw_show_product_weight' => array(
                    'name'     => _x("Show Weight Column", "wc-setting", $this->td),
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_show_product_weight',
                  ),
                  'puiw_show_product_dimensions' => array(
                    'name'     => _x("Show Dimensions Column", "wc-setting", $this->td),
                    'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_show_product_dimensions',
                  ),
                  'puiw_show_discount_precent' => array(
                    'name'     => _x("Show Discount Column", "wc-setting", $this->td),
                    'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_show_discount_precent',
                  ),
                  'puiw_show_product_tax' => array(
                    'name'     => _x("Show Tax Column", "wc-setting", $this->td),
                    'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_show_product_tax',
                  ),
                  'puiw_show_product_sku' => array(
                    'name'     => _x("Show SKU Column", "wc-setting", $this->td),
                    'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_show_product_sku',
                  ),
                  'puiw_show_product_sku2' => array(
                    'name'     => _x("Use ID when SKU's unavailable", "wc-setting", $this->td),
                    'desc'     => _x("Check to use or leave unchecked to not", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_show_product_sku2',
                  ),
                  'puiw_show_user_uin' => array(
                    'name'     => _x("Activate User UIN feature & Show it", "wc-setting", $this->td),
                    'label'     => _x("Check to add or leave unchecked to hide", "wc-setting", $this->td),
                    'desc'     => _x("Check to add or leave unchecked to hide", "wc-setting", $this->td),
                    'desc_tip' => _x("Show User UIN (Unique Identification Number) in invoice which will be received at checkout page (will save as 'billing_uin' user meta)", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_show_user_uin',
                  ),
                  'puiw_show_coupons_code_at_totals' => array(
                    'type' => 'checkbox',
                    'name' => _x("Show Coupon(s) Code at Order totals", "wc-setting", $this->td),
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_coupons_code_at_totals',
                  ),
                  'puiw_show_coupons_amount_at_totals' => array(
                    'type' => 'checkbox',
                    'name' => _x("Show Coupon(s) Amount at Order totals", "wc-setting", $this->td),
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_coupons_amount_at_totals',
                  ),
                  'puiw_show_coupons_discount_at_totals' => array(
                    'type' => 'checkbox',
                    'name' => _x("Show Coupon(s) Discount at Order totals", "wc-setting", $this->td),
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_coupons_discount_at_totals',
                  ),
                  'puiw_show_coupons_description_at_totals' => array(
                    'type' => 'checkbox',
                    'name' => _x("Show Coupon(s) Description at Order totals", "wc-setting", $this->td),
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'default' => 'yes',
                    'id'   => 'puiw_show_coupons_description_at_totals',
                  ),
                  'puiw_show_price_template' => array(
                    'name'    => _x("Line item Price Display", "wc-setting", $this->td),
                    'type'    => 'radio',
                    'id'      => 'puiw_show_price_template',
                    'default' => 'show_both_regular_and_sale_price',
                    'options' => array(
                      'show_wc_price'                     => _x("Show WC_Order price (as shown in order details screen)", "wc-setting", $this->td),
                      'show_only_regular_price'           => _x("Show Current-live regular price", "wc-setting", $this->td),
                      'show_only_sale_price'              => _x("Show Current-live sale price", "wc-setting", $this->td),
                      'show_both_regular_and_sale_price'  => _x("Show Current-live regular/sale price", "wc-setting", $this->td),
                      'show_saved_regular_price'          => _x("Show Saved Line-item regular price", "wc-setting", $this->td),
                      'show_saved_sale_price'             => _x("Show Saved Line-item sale price", "wc-setting", $this->td),
                      'show_saved_regular_and_sale_price' => _x("Show Saved Line-item sale/regular price", "wc-setting", $this->td),
                    ),
                  ),
                  'puiw_show_tax_display' => array(
                    'name'    => _x("Line item Tax Display", "wc-setting", $this->td),
                    'type'    => 'radio',
                    'default' => 'labelamount',
                    'options' => array(
                        "amount"      => _x("Show taxes amount", "wc-setting", $this->td),
                        "labelamount" => _x("Show taxes label and amount", "wc-setting", $this->td),
                        "onlytotal"   => _x("Show line total tax amount", "wc-setting", $this->td),
                    ),
                    'id'   => 'puiw_show_tax_display',
                  ),
                  'puiw_show_discount_calc' => array(
                    'name'     => _x("Line item Discount Calculation", "wc-setting", $this->td),
                    'type'     => 'radio',
                    'default'  => 'wcorder',
                    'desc_tip' => _x("Since ver. 1.8, both regular and sale price of products are saved into line-items meta in order to use later in discount calculation.", "wc-setting", $this->td),
                    'options'  => array(
                        "wcorder"   => _x("Based on WC_Order (as shown in order details screen)", "wc-setting", $this->td),
                        "liveprice" => _x("Based on Product's current-live sale/regular price", "wc-setting", $this->td),
                        "savepirce" => _x("Based on Line-item saved sale/regular price", "wc-setting", $this->td),
                        // "advanced"  => _x("Based on Discounted Line-item saved sale/regular price", "wc-setting", $this->td),
                    ),
                    'id'   => 'puiw_show_discount_calc',
                  ),
                  'puiw_show_discount_display' => array(
                    'name'    => _x("Line item Discount Display", "wc-setting", $this->td),
                    'type'    => 'radio',
                    'default' => 'precnt',
                    'options' => array(
                        "value"  => _x("Show discount value", "wc-setting", $this->td),
                        "precnt" => _x("Show discount precentage", "wc-setting", $this->td),
                        "both"   => _x("Show discount precentage and value", "wc-setting", $this->td),
                    ),
                    'id'   => 'puiw_show_discount_display',
                  ),
                  'puiw_show_order_note' => array(
                    'name'    => _x("Show Order Note?", "wc-setting", $this->td),
                    'type'    => 'radio',
                    'id'      => 'puiw_show_order_note',
                    'default' => 'note_provided_by_customer',
                    'options' => array(
                      'hide_note_from_invoice'        => _x("Hide Note from invoice", "wc-setting", $this->td),
                      'note_provided_by_customer'     => _x("Show Note provided by customer", "wc-setting", $this->td),
                      'note_provided_by_shop_manager' => _x("Show Note provided by shop manager", "wc-setting", $this->td),
                      'note_provided_by_both'         => _x("Show Note provided by customer & shop manager", "wc-setting", $this->td),
                    ),
                  ),
                  'puiw_items_sorting' => array(
                    'id'      => 'puiw_items_sorting',
                    'name'    => _x("Invoice Items Sorting", "wc-setting", $this->td),
                    'type'    => 'radio',
                    'default' => 'NONE',
                    'options' => array(
                      'NONE'         => _x("Default", "wc-setting", $this->td),
                      'ID'           => _x("By Item ID", "wc-setting", $this->td),
                      'PID'          => _x("By Product ID", "wc-setting", $this->td),
                      'SKU'          => _x("By SKU", "wc-setting", $this->td),
                      'QTY'          => _x("By Quantity", "wc-setting", $this->td),
                      'NAME'         => _x("By Name", "wc-setting", $this->td),
                      'PRICE'        => _x("By Price", "wc-setting", $this->td),
                      'TOTAL'        => _x("By Total", "wc-setting", $this->td),
                      'WEIGHT'       => _x("By Weight", "wc-setting", $this->td),
                      'SUBTOTAL'     => _x("By Subtotal", "wc-setting", $this->td),
                      'SUBTOTAL_TAX' => _x("By Subtotal Tax", "wc-setting", $this->td),
                    ),
                  ),
                  'puiw_show_shipping_address' => array(
                    'name' => _x("Address on Invoices", "wc-setting", $this->td),
                    'type' => 'radio',
                    'default' => 'billing',
                    'options' => array(
                      "billing" => _x("Use Billing Address", "wc-setting", $this->td),
                      "shipping" => _x("Use Shipping Address", "wc-setting", $this->td),
                    ),
                    'id'   => 'puiw_show_shipping_address',
                  ),
                  'puiw_address_display_method' => array(
                    'name'    => _x("Customer Address template", "wc-setting", $this->td),
                    'type'    => 'text',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Customer Address template", "wc-setting", $this->td)),
                    'id'      => 'puiw_address_display_method',
                    'desc'    => _x("Use following tags and build your own addressing template.", "wc-setting", $this->td) . "<br><a class='short-tags button'>[first_name]</a> <a class='short-tags button'>[last_name]</a> <a class='short-tags button'>[company]</a> <a class='short-tags button'>[country]</a>
                    <a class='short-tags button'>[province]</a> <a class='short-tags button'>[city]</a> <a class='short-tags button'>[address1]</a> <a class='short-tags button'>[address2]</a> <a class='short-tags button'>[po_box]</a> <a class='short-tags button'>[email]</a> <a class='short-tags button'>[phone]</a> <a class='short-tags button'>[uin]</a>",
                  ),
                  'puiw_items_end' => array(
                    'type' => 'sectionend',
                    'id'   => 'puiw_items_end'
                  ),
                )
            );
            break;
          case 'report':
            $section_data = apply_filters( "puiw_setting_section_{$current_section}",
                array(
                  'puiw_report_title' => array(
                    'name' => _x("Inventory Report Setting", "wc-setting", $this->td),
                    'type' => 'title',
                    'id'   => 'puiw_report_title'
                  ),
                  'puiw_shelf_number_id' => array(
                    'name'     => _x("Shelf Storage Number ID in Inventory Report ", "wc-setting", $this->td),
                    'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_shelf_number_id',
                  ),
                  'puiw_show_product_sku_inventory' => array(
                    'name'     => _x("Show Product SKU in Inventory Report", "wc-setting", $this->td),
                    'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_show_product_sku_inventory',
                  ),
                  'puiw_show_product_sku2_inventory' => array(
                    'name'     => _x("Use ID when SKU's unavailable", "wc-setting", $this->td),
                    'desc'     => _x("Check to use or leave unchecked to not", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_show_product_sku2_inventory',
                  ),
                  'puiw_show_product_image_inventory' => array(
                    'name' => _x("Show Product Image in Inventory Report", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_product_image_inventory',
                  ),
                  'puiw_show_product_weight_in_inventory' => array(
                    'name' => _x("Show Product Weight in Inventory Report", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_product_weight_in_inventory',
                  ),
                  'puiw_show_product_total_weight_in_inventory' => array(
                    'name' => _x("Show Product Total Weight in Inventory Report", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_product_total_weight_in_inventory',
                  ),
                  'puiw_show_product_dimensions_in_inventory' => array(
                    'name' => _x("Show Product Dimensions in Inventory Report", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_product_dimensions_in_inventory',
                  ),
                  'puiw_show_product_quantity_in_inventory' => array(
                    'name' => _x("Show Product Quantity in Inventory Report", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_product_quantity_in_inventory',
                  ),
                  'puiw_show_product_note_in_inventory' => array(
                    'name' => _x("Show Product Note in Inventory Report", "wc-setting", $this->td),
                    'type' => 'checkbox',
                    'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    'id'   => 'puiw_show_product_note_in_inventory',
                  ),
                  'puiw_show_order_note_inventory' => array(
                    'name'=> _x("Show Order Notes in Inventory Report", "wc-setting", $this->td),
                    'type'=> 'select',
                    'id'=> 'puiw_show_order_note_inventory',
                    'class'=> 'wc-enhanced-select',
                    'default'=> 'note_provided_by_customer',
                    'options' => array(
                      'hide_note_from_invoice' => _x("Hide Notes from report", "wc-setting", $this->td),
                      'note_provided_by_customer' => _x("Show Note provided by customer", "wc-setting", $this->td),
                      'note_provided_by_shop_manager' => _x("Show Note provided by shop manager", "wc-setting", $this->td),
                      'note_provided_by_both' => _x("Show Note provided by customer & shop manager", "wc-setting", $this->td),
                    ),
                  ),
                  'puiw_price_inventory_report' => array(
                    'name'=> _x("Price in Inventory Report", "wc-setting", $this->td),
                    'type'=> 'select',
                    'id'=> 'puiw_price_inventory_report',
                    'class'=> 'wc-enhanced-select',
                    'default'=> 'show_both_regular_and_sale_price',
                    'options' => array(
                      'hide_all_price'                    => _x("DO NOT SHOW Prices (HIDE)", "wc-setting", $this->td),
                      'show_only_regular_price'           => _x("Show Live Price (regular)", "wc-setting", $this->td),
                      'show_only_sale_price'              => _x("Show Live Price (sale)", "wc-setting", $this->td),
                      'show_both_regular_and_sale_price'  => _x("Show Live Price (sale & regular)", "wc-setting", $this->td),
                      'show_wc_price'                     => _x("Show Invoice Calculated price", "wc-setting", $this->td),

                    ),
                  ),
                  'puiw_inventory_css_style' => array(
                    'name'              => _x("Inventory Report Custom CSS", "wc-setting", $this->td),
                    'type'              => 'textarea',
                    'id'                => 'puiw_inventory_css_style',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Inventory Report Custom CSS", "wc-setting", $this->td)),
                    'desc_tip'          => _x("You can add custom css here to be used in Inventory Report", "wc-setting", $this->td),
                    'custom_attributes' => array( 'dir'  => 'ltr','rows' => '5', )
                  ),
                  'puiw_report_end' => array(
                    'type' => 'sectionend',
                    'id'   => 'puiw_report_end'
                  ),
              )
            );
            break;
          case 'theme':
            $section_data = apply_filters( "puiw_setting_section_{$current_section}",
                array(
                  'puiw_theme_title' => array(
                    'name' => _x("Invoices Theming Setting", "wc-setting", $this->td),
                    'type' => 'title',
                    'id'   => 'puiw_theme_title'
                  ),
                  'puiw_template' => array(
                    'name'    => _x("Default Theme", "wc-setting", $this->td),
                    'type'    => 'select',
                    'class'   => 'wc-enhanced-select',
                    'id'      => 'puiw_template',
                    'desc_tip'=> _x("This theme will be used as default in Emails, HTML invoices, PDF invoices, Inventory Reports and Packing Slips.", "wc-setting", $this->td),
                    'default' => 'default-rtl',
                    'custom_attributes' => array( 'selecteditem' => get_option("puiw_template"), ),
                    'options' => $PeproUltimateInvoice->load_themes(),
                  ),
                  'puiw_preinvoice_template' => array(
                    'name'    => _x("Pre-Invoice Theme", "wc-setting", $this->td),
                    'type'    => 'select',
                    'class'   => 'wc-enhanced-select',
                    'id'      => 'puiw_preinvoice_template',
                    'desc_tip'=> _x("This theme will be used for Pre-Invoice orders in Emails, HTML invoices, PDF invoices, Inventory Reports and Packing Slips.", "wc-setting", $this->td),
                    'default' => 'default-pre-invoice',
                    'custom_attributes' => array( 'selecteditem' => get_option("puiw_preinvoice_template"), ),
                    'options' => $PeproUltimateInvoice->load_themes(),
                  ),
                  'puiw_theme_color' => array(
                    'name'    => _x("Theme Colors set", "wc-setting", $this->td),
                    'type'    => 'text',
                    'id'      => 'puiw_theme_color',
                    'class'   => 'wc-color-picker puiw_swatch_one',
                    'default' => 'teal',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Theme primary color", "wc-setting", $this->td)),
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_theme_color2' => array(
                    'name'    => "",
                    'type'    => 'text',
                    'id'      => 'puiw_theme_color2',
                    'class'   => 'wc-color-picker puiw_swatch_two',
                    'default' => 'teal',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Theme secondary color", "wc-setting", $this->td)),
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_theme_color3' => array(
                    'name'    => "",
                    'type'    => 'text',
                    'id'      => 'puiw_theme_color3',
                    'class'   => 'wc-color-picker puiw_swatch_three',
                    'default' => 'teal',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Theme tertiary color", "wc-setting", $this->td)),
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_swatch' => array(
                    'name'    => "",
                    'type'    => 'select',
                    'class'   => 'swatch-select',
                    'desc_tip'=> sprintf(_x("You can create pre-defined color schemes %s and use them to load your favourie colors set.", "wc-setting", $this->td), sprintf("<a href='%s' target='_blank'>%s</a>", admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=color") ,__("here",$this->td))),
                    'id'      => 'puiw_swatch',
                    'custom_attributes' => array( 'swatches' => esc_js(get_option("puiw_color_swatches","")), ),
                    'options' => array(),
                  ),
                  'puiw_preinvoice_theme_color' => array(
                    'name'    => _x("Pre-Invoice Theme Colors set", "wc-setting", $this->td),
                    'type'    => 'text',
                    'id'      => 'puiw_preinvoice_theme_color',
                    'class'   => 'wc-color-picker puiw_preinvoice_swatch_one',
                    'default' => 'teal',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Theme primary color", "wc-setting", $this->td)),
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_preinvoice_theme_color2' => array(
                    'name'    => "",
                    'type'    => 'text',
                    'id'      => 'puiw_preinvoice_theme_color2',
                    'class'   => 'wc-color-picker puiw_preinvoice_swatch_two',
                    'default' => 'teal',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Theme secondary color", "wc-setting", $this->td)),
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_preinvoice_theme_color3' => array(
                    'name'    => "",
                    'type'    => 'text',
                    'id'      => 'puiw_preinvoice_theme_color3',
                    'class'   => 'wc-color-picker puiw_preinvoice_swatch_three',
                    'default' => 'teal',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Theme tertiary color", "wc-setting", $this->td)),
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_preinvoice_swatch' => array(
                    'name'    => "",
                    'type'    => 'select',
                    'class'   => 'swatch-select',
                    'id'      => 'puiw_preinvoice_swatch',
                    'desc_tip'=> sprintf(_x("You can create pre-defined color schemes %s and use them to load your favourie colors set.", "wc-setting", $this->td), sprintf("<a href='%s' target='_blank'>%s</a>", admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=color") ,__("here",$this->td))),
                    'custom_attributes' => array( 'swatches' => esc_js(get_option("puiw_color_swatches","")), ),
                    'options' => array(),
                  ),
                  'puiw_invoice_title' => array(
                    'name'              => _x("Invoice Title", "wc-setting", $this->td),
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Invoice Title", "wc-setting", $this->td)),
                    'type'              => 'text',
                    'id'                => 'puiw_invoice_title',
                    'default'           => _x("Invoice %s", "wc-setting",$this->td),
                    'desc'              => _x("You can use %s instead of Invoice Number", "wc-setting", $this->td),
                  ),
                  'puiw_font_size' => array(
                    'name'              => _x("Font size (px)", "wc-setting", $this->td),
                    'type'              => 'number',
                    'id'                => 'puiw_font_size',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Font size (px)", "wc-setting", $this->td)),
                    'default'           => '12',
                    'custom_attributes' => array( 'dir'  => 'ltr', 'step' => '1', 'min'  => '8', 'max'  => '99', )
                  ),
                  'puiw_invoice_prefix' => array(
                    'name'              => _x("Invoice Prefix", "wc-setting", $this->td),
                    'type'              => 'text',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Invoice Prefix", "wc-setting", $this->td)),
                    'id'                => 'puiw_invoice_prefix',
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_invoice_suffix' => array(
                    'name'              => _x("Invoice Suffix", "wc-setting", $this->td),
                    'type'              => 'text',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Invoice Suffix", "wc-setting", $this->td)),
                    'id'                => 'puiw_invoice_suffix',
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_invoice_start' => array(
                    'name'              => _x("Invoice Start Number", "wc-setting", $this->td),
                    'type'              => 'number',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Invoice Start Number", "wc-setting", $this->td)),
                    'desc_tip'          => _x("Invoices number will start from this number", "wc-setting", $this->td),
                    'id'                => 'puiw_invoice_start',
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_show_signatures' => array(
                    'name'     => _x("Show Signatures", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_show_signatures',
                    'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                  ),
                  'puiw_signature' => array(
                    'name'              => _x("Add Store Signature and Stamp", "wc-setting", $this->td),
                    'type'              => 'text',
                    'class'             => 'wc-select-uploader',
                    'desc'              => '',
                    'desc_tip'          => _x("Add your scanned signature or stamp to be added to invoices footer. (preferred size: 150x300 px)", "wc-setting", $this->td),
                    'id'                => 'puiw_signature',
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_watermark' => array(
                    'name'              => _x("Add Watermark to Invoices", "wc-setting", $this->td),
                    'type'              => 'text',
                    'class'             => 'wc-select-uploader',
                    'desc'              => '',
                    'desc_tip'          => _x("Add your watermark image to invoices (can use png too)", "wc-setting", $this->td),
                    'id'                => 'puiw_watermark',
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_watermark_opacity' => array(
                    'name'              => _x("Watermark opacity", "wc-setting", $this->td),
                    'type'              => 'number',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Watermark opacity", "wc-setting", $this->td)),
                    'desc_tip'          => _x("Watermark opacity scale <br>(0: transparent | 100: opaque)", "wc-setting", $this->td),
                    'id'                => 'puiw_watermark_opacity',
                    'default'           => '80',
                    'custom_attributes' => array( 'dir'  => 'ltr', 'step' => '1', 'min'  => '1', 'max'  => '100',
                    )
                  ),
                  'puiw_watermark_blend' => array(
                    'id'                => 'puiw_watermark_blend',
                    'name'              => _x("PDF Watermark blend mode", "wc-setting", $this->td),
                    'type'              => "select",
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Watermark opacity", "wc-setting", $this->td)),
                    'desc_tip'          => _x("Specify the blend mode for overlying watermark images", "wc-setting", $this->td),
                    'default'           => "Normal",
                    "options" => array(
                      "Normal"     => _x("Normal","wc-setting",$this->td),
                      "Multiply"   => _x("Multiply","wc-setting",$this->td),
                      "Screen"     => _x("Screen","wc-setting",$this->td),
                      "Overlay"    => _x("Overlay","wc-setting",$this->td),
                      "Darken"     => _x("Darken","wc-setting",$this->td),
                      "Lighten"    => _x("Lighten","wc-setting",$this->td),
                      "ColorDodge" => _x("ColorDodge","wc-setting",$this->td),
                      "ColorBurn"  => _x("ColorBurn","wc-setting",$this->td),
                      "HardLight"  => _x("HardLight","wc-setting",$this->td),
                      "SoftLight"  => _x("SoftLight","wc-setting",$this->td),
                      "Difference" => _x("Difference","wc-setting",$this->td),
                      "Exclusion"  => _x("Exclusion","wc-setting",$this->td),
                    )
                  ),
                  'puiw_invoices_footer' => array(
                    'name'              => _x("Invoices footer", "wc-setting", $this->td),
                    'type'              => 'textarea',
                    'id'                => 'puiw_invoices_footer',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Invoices footer", "wc-setting", $this->td)),
                    'desc_tip'          => _x("HTML tags are allowed. DO NOT ADD CSS here!", "wc-setting", $this->td),
                    'custom_attributes' => array( 'rows' => '5', )
                  ),
                  'puiw_custom_css_style' => array(
                    'name'              => _x("HTML Custom CSS", "wc-setting", $this->td),
                    'type'              => 'textarea',
                    'id'                => 'puiw_custom_css_style',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("HTML Invoices Custom CSS", "wc-setting", $this->td)),
                    'desc_tip'          => _x("You can add custom css here to be used in HTML Invoices", "wc-setting", $this->td),
                    'custom_attributes' => array( 'dir'  => 'ltr', 'rows' => '5', )
                  ),
                  'puiw_pdf_css_style' => array(
                    'name'              => _x("PDF Custom CSS", "wc-setting", $this->td),
                    'type'              => 'textarea',
                    'id'                => 'puiw_pdf_css_style',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("PDF Invoices Custom CSS", "wc-setting", $this->td)),
                    'desc_tip'          => _x("You can add custom css here to be used in PDF Invoices", "wc-setting", $this->td),
                    'custom_attributes' => array( 'dir'  => 'ltr', 'rows' => '5', )
                  ),
                  'puiw_theme_end' => array(
                    'type' => 'sectionend',
                    'id'   => 'puiw_theme_end'
                  ),
                )
              );
            break;
          case 'misc':
              $section_data = apply_filters( "puiw_setting_section_{$current_section}",
                  array(
                    'puiw_misc_title' => array(
                      'name' => _x("Miscellaneous Setting", "wc-setting", $this->td),
                      'type' => 'title',
                      'id'   => 'puiw_misc_title'
                    ),
                    'puiw_dark_mode' => array(
                      'name'     => _x("Use Dark-mode as Default", "wc-setting", $this->td),
                      'type'     => 'checkbox',
                      'default'  => 'no',
                      'id'       => 'puiw_dark_mode',
                      'desc' => _x("Check to use dark-mode as default color scheme for setting screen or leave unchecked to use default color scheme", "wc-setting", $this->td),
                    ),
                    'puiw_disable_wc_dashboard' => array(
                      'name'     => _x("Disable WC Dashboard", "wc-setting", $this->td),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_disable_wc_dashboard',
                      'desc' => _x("Check to disable new Dashboard introduced in Woocommerce 4.0 or leave unchecked to use it", "wc-setting", $this->td),
                      'desc_tip' => _x("Refresh page after setting saved.", "wc-setting", $this->td),
                    ),
                    'puiw_date_format' => array(
                      'name'              => _x("Date parsing template", "wc-setting", $this->td),
                      'type'              => 'text',
                      'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Date parsing template", "wc-setting", $this->td)),
                      'std'               => 'Y/m/d H:i', // WooCommerce < 2.0
                      'default'           => 'Y/m/d H:i', // WooCommerce >= 2.0
                      'desc'              => '<div style="display: none" id="dateformathelp"><div class="dateformathelp"><br>
                          <table>
                            <thead>
                              <tr>
                                <th>Character</th>
                                <th>Meaning</th>
                                <th>Example</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td colspan="3" class="head">
                                  <strong>Common date format characters and their values</strong>
                                </td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">d</code>
                                </td>
                                <td>day of the month with leading zeros</td>
                                <td>03 or 17</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">j</code>
                                </td>
                                <td>day of the month without leading zeros</td>
                                <td>3 or 17</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">D</code>
                                </td>
                                <td>day of the week as a three-letter abbreviation</td>
                                <td>Mon</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">l</code>
                                </td>
                                <td>full day of the week</td>
                                <td>Monday</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">m</code>
                                </td>
                                <td>month as a number with leading zeros</td>
                                <td>09 or 12</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">n</code>
                                </td>
                                <td>month as a number without leading zeros</td>
                                <td>9 or 12</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">M</code>
                                </td>
                                <td>month as a three-letter abbreviation</td>
                                <td>Sep</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">F</code>
                                </td>
                                <td>full month</td>
                                <td>September</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">y</code>
                                </td>
                                <td>two-digit year</td>
                                <td>18</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">Y</code>
                                </td>
                                <td>full year</td>
                                <td>2018</td>
                              </tr>
                              <tr>
                                <td colspan="3" class="head">
                                  <strong>Common time format characters and their values</strong>
                                </td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">g</code>
                                </td>
                                <td>hours in 12-hour format without leading zeros</td>
                                <td>1 or 12</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">h</code>
                                </td>
                                <td>hours in 12-hour format with leading zeros</td>
                                <td>01 or 12</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">G</code>
                                </td>
                                <td>hours in 24-hour format without leading zeros</td>
                                <td>1 or 13</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">H</code>
                                </td>
                                <td>hours in 24-hour format with leading zeros</td>
                                <td>01 or 13</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">a</code>
                                </td>
                                <td>am/pm in lowercase</td>
                                <td>am</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">A</code>
                                </td>
                                <td>am/pm in uppercase</td>
                                <td>AM</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">i</code>
                                </td>
                                <td>minutes with leading zeros</td>
                                <td>09 or 15</td>
                              </tr>
                              <tr>
                                <td>
                                  <code class="inline">s</code>
                                </td>
                                <td>seconds with leading zeros</td>
                                <td>05 or 30<br>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                          <strong>Here are some examples of date format with the result output.</strong>
                          <ul>
                            <li>
                              <code>F j, Y g:i a</code>
                              – November 6, 2010 12:50 am</li>
                            <li>
                              <code>F j, Y</code>
                              – November 6, 2010</li>
                            <li>
                              <code>F, Y</code>
                              – November, 2010</li>
                            <li>
                              <code>g:i a</code>
                              – 12:50 am</li>
                            <li>
                              <code>g:i:s a</code>
                              – 12:50:48 am</li>
                            <li>
                              <code>l, F jS, Y</code>
                              – Saturday, November 6th, 2010</li>
                            <li>
                              <code>M j, Y @ G:i</code>
                              – Nov 6, 2010 @ 0:50</li>
                            <li>
                              <code>Y/m/d \a\t g:i A</code>
                              – 2010/11/06 at 12:50 AM</li>
                            <li>
                              <code>Y/m/d \a\t g:ia</code>
                              – 2010/11/06 at 12:50am</li>
                            <li>
                              <code>Y/m/d g:i:s A</code>
                              – 2010/11/06 12:50:48 AM</li>
                            <li>
                              <code>Y/m/d</code>
                              – 2010/11/06</li>
                          </ul>
                          <p>
                            <a class="button button-primary" href="https://code.tutsplus.com/tutorials/working-with-date-and-time-in-php--cms-31768" target="_blank">Learn more at code.tutsplus.com ...</a>
                            <a class="button button-primary" href="https://wordpress.org/support/article/formatting-date-and-time/" target="_blank">Read more at wordpress.org ...</a>
                          </p>
                        </div></div>' . "<a class='thickbox' title='"._x("Some of the most commonly used date/time format characters and their values", "wc-setting", $this->td)."' href='#TB_inline?height=500&width=600&inlineId=dateformathelp' target='_blank'>"._x("View date format string examples", "wc-setting", $this->td)."</a>",
                      'id'                => 'puiw_date_format',
                      'custom_attributes' => array( 'dir' => 'ltr', )
                    ),
                    'puiw_date_shamsi' => array(
                      'name'     => _x("Convert Date to Jalali/Shamsi", "wc-setting", $this->td),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_date_shamsi',
                      'desc' => _x("Check to convert dates into Jalali/Shamsi format or leave unchecked to use default setting", "wc-setting", $this->td),
                    ),
                    'puiw_show_shipped_date' => array(
                      'name'     => _x("Show Shipped Date on Order details", "wc-setting", $this->td),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_shipped_date',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    ),
                    'puiw_show_shipping_serial' => array(
                      'name'     => _x("Show Shipping Track Serial on Order details", "wc-setting", $this->td),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_shipping_serial',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    ),
                    'puiw_force_persian_numbers' => array(
                      'name'     => _x("Force Persian/Faris Numbers?", "wc-setting", $this->td),
                      'type'     => 'radio',
                      'default'  => 'no',
                      'options'  => array(
                        'no'     => _x("No, Use Default English style", "wc-setting", $this->td),
                        'arabic' => _x("Yes, Use Eastern Arabic Numbers style", "wc-setting", $this->td),
                        'farsi'  => _x("Yes, Use Persian Numbers style", "wc-setting", $this->td),
                      ),
                      'id'       => 'puiw_force_persian_numbers',
                    ),
                    'puiw_misc_end' => array(
                      'type' => 'sectionend',
                      'id'   => 'puiw_misc_end'
                    ),
                  )
              );
              break;
          case 'integ':
              $section_data = apply_filters( "puiw_setting_section_{$current_section}",
                  array(
                    'puiw_integ_title' => array(
                      'id'   => 'puiw_integ_title',
                      'name' => _x("Integration Setting", "wc-setting", $this->td),
                      'type' => 'title',
                      'desc'  => sprintf(__('Integration with %s', $this->td ), "<a href='https://wordpress.org/plugins/woo-product-bundle/' target='_blank'>WPC Product Bundles</a>" ),
                    ),
                    'puiw_woosb_show_bundles' => array(
                      'id'       => 'puiw_woosb_show_bundles',
                      'name'     => _x("Show bundles", "wc-setting", $this->td),
                      'type'     => 'checkbox',
                      'default'  => 'no',
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    ),
                    'puiw_woosb_show_bundles_subtitle' => array(
                      'id'       => 'puiw_woosb_show_bundles_subtitle',
                      'name'     => _x("Show bundles subtitle", "wc-setting", $this->td),
                      'type'     => 'checkbox',
                      'default'  => 'no',
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    ),
                    'puiw_woosb_bundles_subtitle_prefix' => array(
                      'id'       => 'puiw_woosb_bundles_subtitle_prefix',
                      'name'     => _x("Bundles products subtitle prefix", "wc-setting", $this->td),
                      'type'     => 'text',
                      'default'  => _x("Bundled products:", "wc-setting", $this->td),
                    ),
                    'puiw_woosb_show_bundled_products' => array(
                      'id'       => 'puiw_woosb_show_bundled_products',
                      'name'     => _x("Show bundled products", "wc-setting", $this->td),
                      'type'     => 'checkbox',
                      'default'  => 'no',
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    ),
                    'puiw_woosb_show_bundled_subtitle' => array(
                      'id'       => 'puiw_woosb_show_bundled_subtitle',
                      'name'     => _x("Show bundled products subtitle", "wc-setting", $this->td),
                      'type'     => 'checkbox',
                      'default'  => 'no',
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", $this->td),
                    ),
                    'puiw_woosb_bundled_subtitle_prefix' => array(
                      'id'       => 'puiw_woosb_bundled_subtitle_prefix',
                      'name'     => _x("Bundled products subtitle prefix", "wc-setting", $this->td),
                      'type'     => 'text',
                      'default'  => _x("Bundled in:", "wc-setting", $this->td),
                    ),
                    'puiw_woosb_show_bundled_hierarchy' => array(
                      'id'       => 'puiw_woosb_show_bundled_hierarchy',
                      'name'     => _x("Show bundled products hierarchy", "wc-setting", $this->td),
                      'type'     => 'checkbox',
                      'default'  => 'no',
                      'desc'     => _x("Check to show hierarchy or leave unchecked to show normally", "wc-setting", $this->td),
                    ),
                    'puiw_integ_end' => array(
                      'type' => 'sectionend',
                      'id'   => 'puiw_integ_end'
                    ),
                  )
              );
              break;
          case 'barcode':
              $section_data = apply_filters( "puiw_setting_section_{$current_section}",
                  array(
                    'puiw_barcode_title' => array(
                      'name' => _x("Invoices Barcode Setting", "wc-setting", $this->td),
                      'type' => 'title',
                      'id'   => 'puiw_barcode_title'
                    ),
                    'puiw_show_barcode_id' => array(
                      'name'     => _x("Show Invoices Number Barcode", "wc-setting", $this->td),
                      'desc' => _x("Check to add or leave unchecked to hide", "wc-setting", $this->td),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_barcode_id',
                    ),
                    'puiw_show_shipping_ref_id' => array(
                      'name'     => _x("Show Shipping Track Serial Barcode", "wc-setting", $this->td),
                      'desc' => _x("Check to add or leave unchecked to hide", "wc-setting", $this->td),
                      // 'desc_tip' => _x("Shop manager enters Track ID manually", "wc-setting", $this->td),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_shipping_ref_id',
                    ),
                    // 'puiw_show_qr_code_id'                      => array(
                    //   'name'     => _x("Show Invoices QR Code", "wc-setting", $this->td),
                    //   'desc' => _x("Check to add or leave unchecked to hide", "wc-setting", $this->td),
                    //   'type'     => 'checkbox',
                    //   'id'       => 'puiw_show_qr_code_id',
                    // ),
                    'puiw_postal_stickey_label_for_store' => array(
                      'name'     => _x("Show Barcode in Sender Shipping slip", "wc-setting", $this->td),
                      'desc' => _x("Check to add or leave unchecked to hide", "wc-setting", $this->td),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_postal_stickey_label_for_store',
                    ),
                    'puiw_postal_stickey_label_for_customer' => array(
                      'name'     => _x("Show Barcode in Recipient Shipping slip", "wc-setting", $this->td),
                      'desc' => _x("Check to add or leave unchecked to hide", "wc-setting", $this->td),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_postal_stickey_label_for_customer',
                    ),
                    'puiw_barcode_end' => array(
                      'type' => 'sectionend',
                      'id'   => 'puiw_barcode_end'
                    ),
                  )
              );
              break;
          case 'email':
            $section_data = apply_filters( "puiw_setting_section_{$current_section}",
                array(
                  'puiw_email_title' => array(
                    'name' => _x("Invoices Email Setting", "wc-setting", $this->td),
                    'type' => 'title',
                    'id'   => 'puiw_automation_title'
                  ),
                  'puiw_email_subject' => array(
                    'name'              => _x("Email Subject", "wc-setting", $this->td),
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Email Subject", "wc-setting", $this->td)),
                    'type'              => 'text',
                    'default'           => _x("Order #%s invoice on ", "wc-setting", $this->td) . get_bloginfo( 'name', 'display' ),
                    'id'                => 'puiw_email_subject',
                    'desc'              => _x("You can use %s instead of Invoice Number", "wc-setting", $this->td),
                    'desc_tip'          => true,
                  ),
                  'puiw_email_from_name' => array(
                    'type'              => 'text',
                    'id'                => 'puiw_email_from_name',
                    'name'              => _x('"From" name', "wc-setting", $this->td),
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x('"From" name', "wc-setting", $this->td)),
                    'desc'              => __('How the sender name appears in outgoing emails', $this->td),
                    'default'           => esc_attr( get_bloginfo( 'name', 'display' ) ),
                    'desc_tip'          => true,
                  ),
                  'puiw_email_from_address' => array(
                    'type'              => 'text',
                    'id'                => 'puiw_email_from_address',
                    'name'              => _x('"From" address', "wc-setting", $this->td),
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x('"From" address', "wc-setting", $this->td)),
                    'desc'              => __('How the sender email appears in outgoing emails', $this->td),
                    'default'           => get_option('admin_email'),
                    'desc_tip'          => true,
                  ),
                  'puiw_send_invoices_via_email' => array(
                    'name'     => _x("Send Invoices via Email to Customer", "wc-setting", $this->td),
                    'type'     => 'radio',
                    'id'       => 'puiw_send_invoices_via_email',
                    'default'  => 'manual',
                    'options'  => array(
                      'manual'     => _x("Send Manually", "wc-setting", $this->td),
                      'automatic'  => _x("Send Automatically", "wc-setting", $this->td),
                    ),
                    'desc' => _x("Choose whether send HTML invoices via Email to customers automatically or manually", "wc-setting", $this->td),
                    'desc_tip' => true,
                  ),
                  'puiw_send_invoices_via_email_opt' => array(
                    'name'     => _x("Order Statuses for Automatic Sending to Customers", "wc-setting", $this->td),
                    'type'     => 'multiselect',
                    'class'    => 'chosen_select',
                    'id'       => 'puiw_send_invoices_via_email_opt',
                    'options'  => $this->wc_get_order_statuses(),
                    'default'  => "wc-completed",
                    'desc_tip' => _x("Send HTML Invoices via Email to customer when order status is among these selection. <br>Multiple selection allowed", "wc-setting", $this->td),
                  ),
                  'puiw_send_invoices_via_email_admin' => array(
                    'name'     => _x("Send Invoices via Email to Shop Managers", "wc-setting", $this->td),
                    'type'     => 'radio',
                    'id'       => 'puiw_send_invoices_via_email_admin',
                    'default'  => 'manual',
                    'options'  => array(
                      'manual'     => _x("Send Manually", "wc-setting", $this->td),
                      'automatic'  => _x("Send Automatically", "wc-setting", $this->td),
                    ),
                    'desc' => _x("Choose whether send HTML invoices via Email to customers automatically or manually", "wc-setting", $this->td),
                    'desc_tip' => true,
                  ),
                  'puiw_send_invoices_via_email_opt_admin' => array(
                    'name'     => _x("Order Statuses for Automatic Sending to Managers", "wc-setting", $this->td),
                    'type'     => 'multiselect',
                    'class'    => 'chosen_select',
                    'id'       => 'puiw_send_invoices_via_email_opt_admin',
                    'options'  => $this->wc_get_order_statuses(),
                    'default'  => "wc-completed",
                    'desc_tip' => _x("Send HTML Invoices via Email to Shop managers when order status is among these selection. <br>Multiple selection allowed", "wc-setting", $this->td),
                  ),
                  'puiw_send_invoices_via_email_shpmngrs' => array(
                    'name'     => _x("Shop Managers who will receive invoice mail", "wc-setting", $this->td),
                    'type'     => 'multiselect',
                    'class'    => 'chosen_select',
                    'id'       => 'puiw_send_invoices_via_email_shpmngrs',
                    'options'  => $this->get_wc_managers(),
                    'desc_tip' => _x("Send HTML Invoices via Email to Shop managers either manually through WC Orders screen or automatically. <br>Multiple selection allowed", "wc-setting", $this->td),
                  ),
                  'puiw_attach_pdf_invoices_to_mail' => array(
                    'name'     => _x("Attach PDF Invoices to mail", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_attach_pdf_invoices_to_mail',
                    'desc' => _x("Check to attach a PDF Invoice to WooCommerce Mails or leave unchecked to not", "wc-setting", $this->td),
                  ),
                  'puiw_email_end' => array(
                    'type' => 'sectionend',
                    'id'   => 'puiw_automation_end'
                ),
                )
              );
            break;
          case 'privacy':
            $section_data = apply_filters( "puiw_setting_section_{$current_section}",
                array(
                  'puiw_privacy_title' => array(
                    'name' => _x("Privacy Setting", "wc-setting", $this->td),
                    'type' => 'title',
                    'id'   => 'puiw_automation_title'
                  ),
                  'puiw_allow_guest_users_view_invoices' => array(
                    'name'     => _x("Allow Guest Users view invoices", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'default'  => 'no',
                    'id'       => 'puiw_allow_guest_users_view_invoices',
                    'desc'     => _x("Check to allow or leave unchecked to disallow", "wc-setting", $this->td),
                    'desc_tip' => _x("Allow logged out visitors be able to see invoices by having its URL", "wc-setting", $this->td),
                  ),
                  'puiw_allow_pdf_guest' => array(
                    'name'     => _x("Invoice Output for Guests", "wc-setting", $this->td),
                    'type'     => 'select',
                    'class'    => 'wc-enhanced-select',
                    'id'       => 'puiw_allow_pdf_guest',
                    'default'  => 'html',
                    'options' => array(
                      'html' => _x("Use HTML Only", "wc-setting", $this->td),
                      'pdf' => _x("Use PDF Only", "wc-setting", $this->td),
                      'both' => _x("User HTML/PDF", "wc-setting", $this->td),
                    ),
                  ),
                  'puiw_allow_users_have_invoices' => array(
                    'name'     => _x("Allow Customer/Users view invoices", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'default'  => 'yes',
                    'id'       => 'puiw_allow_users_have_invoices',
                    'desc'     => _x("Check to allow or leave unchecked to disallow", "wc-setting", $this->td),
                    'desc_tip' => _x("Allow logged in visitors be able to see their invoices by having its URL", "wc-setting", $this->td),
                  ),
                  'puiw_allow_pdf_customer' => array(
                    'name'     => _x("Invoice Output for Customers", "wc-setting", $this->td),
                    'type'     => 'select',
                    'class'    => 'wc-enhanced-select',
                    'id'       => 'puiw_allow_pdf_customer',
                    'default'  => 'both',
                    'options' => array(
                      'html' => _x("Use HTML Only", "wc-setting", $this->td),
                      'pdf' => _x("Use PDF Only", "wc-setting", $this->td),
                      'both' => _x("User HTML/PDF", "wc-setting", $this->td),
                    ),
                  ),
                  'puiw_allow_users_use_invoices' => array(
                    'name'     => _x("Show Invoices option in orders list", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'default'  => 'yes',
                    'id'       => 'puiw_allow_users_use_invoices',
                    'desc'     => _x("Check to allow or leave unchecked to disallow", "wc-setting", $this->td),
                    'desc_tip' => _x("Add 'Get Invoice' button to customer order list so they could have invoice too", "wc-setting", $this->td),
                  ),
                  'puiw_allow_users_use_invoices_criteria' => array(
                    'name'     => _x("Allowed Orders statuses", "wc-setting", $this->td),
                    'type'     => 'multiselect',
                    'class'    => 'chosen_select',
                    'default'  => "wc-completed",
                    'id'       => 'puiw_allow_users_use_invoices_criteria',
                    'options'  => $this->wc_get_order_statuses(),
                    'desc_tip' => _x("In which Order Status Customers can use 'Get Invoice' feature?<br>Multiple selection allowed", "wc-setting", $this->td),
                  ),
                  'puiw_privacy_end' => array(
                    'type' => 'sectionend',
                    'id'   => 'puiw_automation_end'
                ),
                )
              );
            break;
          case 'extras':
            $section_data = apply_filters( "puiw_setting_section_{$current_section}",
                array(
                  'puiw_extras_title' => array(
                    'name' => _x("Extras Setting", "wc-setting", $this->td),
                    'type' => 'title',
                    'id'   => 'puiw_automation_title'
                  ),
                  'puiw_quick_shop' => array(
                    'name'     => _x("Enable Quick Shop Feature", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_quick_shop',
                    'desc'     => _x("Check to Enable 'Quick Shop' Feature and integrate it with WPBakery Page Builder (if installed)", "wc-setting", $this->td),
                    'desc_tip' => _x("This will let users filter and shop all your products in one page. Use <code>[puiw_quick_shop]</code> shortcode or WPBakery Page Builder widget to display it", "wc-setting", $this->td),
                  ),
                  'puiw_allow_preorder_invoice' => array(
                    'name'     => _x("Pre-order Invoices", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_allow_preorder_invoice',
                    'desc' => _x("Check to allow users get pre-order invoices or leave unchecked to disallow", "wc-setting", $this->td),
                    'desc_tip' => _x("PRE-ORDER INVOICE means user does not place an order, but rather select products and get an invoice to be personally to present later in shopping, directly from your physical store.", "wc-setting", $this->td),
                  ),
                  'puiw_allow_preorder_emptycart' => array(
                    'name'     => _x("Clear Cart on Quick Buy", "wc-setting", $this->td),
                    'type'     => 'checkbox',
                    'id'       => 'puiw_allow_preorder_emptycart',
                    'desc'     => _x("Check to clear cart on pre-order invoice creation or leave unchecked to keep previous cart", "wc-setting", $this->td),
                  ),
                  'puiw_preorder_shopmngr_extra_note' => array(
                    'name'              => _x("Extra Notes on Pre-order by Shop Manager", "wc-setting", $this->td),
                    'type'              => 'textarea',
                    'id'                => 'puiw_preorder_shopmngr_extra_note',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Extra Notes on Pre-order by Shop Manager", "wc-setting", $this->td)),
                    'desc_tip'          => _x("You can add custom note here to be added into pre-orders", "wc-setting", $this->td),
                    'custom_attributes' => array('rows' => '5', )
                  ),
                  'puiw_preorder_customer_extra_note' => array(
                    'name'              => _x("Extra Notes on Pre-order on behalf of Customer", "wc-setting", $this->td),
                    'type'              => 'textarea',
                    'id'                => 'puiw_preorder_customer_extra_note',
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Extra Notes on Pre-order on behalf of Customer", "wc-setting", $this->td)),
                    'desc_tip'          => _x("You can add custom note here to be added into pre-orders on behalf of customer", "wc-setting", $this->td),
                    'custom_attributes' => array('rows' => '5', )
                  ),
                  'puiw_extras_end' => array(
                    'type' => 'sectionend',
                    'id'   => 'puiw_automation_end'
                ),
                )
              );
            break;
          case 'pdf':
            $section_data = apply_filters( "puiw_setting_section_{$current_section}",
                array(
                  'puiw_pdf_title' => array(
                    'name' => _x("PDF Invoices Setting", "wc-setting", $this->td),
                    'type' => 'title',
                    'id'   => 'puiw_automation_title'
                  ),
                  'puiw_pdf_size' => array(
                    'name'     => _x("PDF Page size", "wc-setting", $this->td),
                    'id'       => 'puiw_pdf_size',
                    'type'     => 'select',
                    'class'    => 'wc-enhanced-select',
                    'default'  => 'A4',
                    'options' =>
                      array(
                        'A0'  => _x("A Series: A0", "wc-setting", $this->td),
                        'A1'  => _x("A Series: A1", "wc-setting", $this->td),
                        'A2'  => _x("A Series: A2", "wc-setting", $this->td),
                        'A3'  => _x("A Series: A3", "wc-setting", $this->td),
                        'A4'  => _x("A Series: A4", "wc-setting", $this->td),
                        'A5'  => _x("A Series: A5", "wc-setting", $this->td),
                        'A6'  => _x("A Series: A6", "wc-setting", $this->td),
                        'A7'  => _x("A Series: A7", "wc-setting", $this->td),
                        'A8'  => _x("A Series: A8", "wc-setting", $this->td),
                        'A9'  => _x("A Series: A9", "wc-setting", $this->td),
                        'A10'  => _x("A Series: A10", "wc-setting", $this->td),
                        'B0'  => _x("B Series: B0", "wc-setting", $this->td),
                        'B1'  => _x("B Series: B1", "wc-setting", $this->td),
                        'B2'  => _x("B Series: B2", "wc-setting", $this->td),
                        'B3'  => _x("B Series: B3", "wc-setting", $this->td),
                        'B4'  => _x("B Series: B4", "wc-setting", $this->td),
                        'B5'  => _x("B Series: B5", "wc-setting", $this->td),
                        'B6'  => _x("B Series: B6", "wc-setting", $this->td),
                        'B7'  => _x("B Series: B7", "wc-setting", $this->td),
                        'B8'  => _x("B Series: B8", "wc-setting", $this->td),
                        'B9'  => _x("B Series: B9", "wc-setting", $this->td),
                        'B10'  => _x("B Series: B10", "wc-setting", $this->td),
                        'C0'  => _x("C Series: C0", "wc-setting", $this->td),
                        'C1'  => _x("C Series: C1", "wc-setting", $this->td),
                        'C2'  => _x("C Series: C2", "wc-setting", $this->td),
                        'C3'  => _x("C Series: C3", "wc-setting", $this->td),
                        'C4'  => _x("C Series: C4", "wc-setting", $this->td),
                        'C5'  => _x("C Series: C5", "wc-setting", $this->td),
                        'C6'  => _x("C Series: C6", "wc-setting", $this->td),
                        'C7'  => _x("C Series: C7", "wc-setting", $this->td),
                        'C8'  => _x("C Series: C8", "wc-setting", $this->td),
                        'C9'  => _x("C Series: C9", "wc-setting", $this->td),
                        'C10'  => _x("C Series: C10", "wc-setting", $this->td),
                        'Letter'  => _x("Letter", "wc-setting", $this->td),
                        'Legal'  => _x("Legal", "wc-setting", $this->td),
                        'Executive'  => _x("Executive", "wc-setting", $this->td),
                        'Folio'  => _x("Folio", "wc-setting", $this->td),
                        'Demy'  => _x("Demy", "wc-setting", $this->td),
                        'Royal'  => _x("Royal", "wc-setting", $this->td),
                      ),
                  ),
                  'puiw_pdf_orientation' => array(
                    'name'     => _x("PDF Page Orientation ", "wc-setting", $this->td),
                    'id'       => 'puiw_pdf_orientation',
                    'type'     => 'radio',
                    // 'class'    => 'wc-enhanced-select',
                    'default'  => 'P',
                    'options' =>
                      array(
                        'P'  => _x("Portrait", "wc-setting", $this->td),
                        'L'  => _x("Landscape", "wc-setting", $this->td),
                      ),
                  ),
                  'puiw_pdf_font' => array(
                    'name'     => _x("PDF Font", "wc-setting", $this->td),
                    'type'     => 'radio',
                    'default'  => 'iranyekanfa',
                    'options'  => array(
                      "dejavu"      => _x("DejaVuSans (Standard)","wc-setting",$this->td),
                      "danaen"      => _x("Dana (Standard)","wc-setting",$this->td),
                      "iransans"    => _x("IRANSans (Standard)","wc-setting",$this->td),
                      "iranyekanen" => _x("IRANYekan (Standard)","wc-setting",$this->td),
                      "danafa"      => _x("Dana (Farsi-Digits Support)","wc-setting",$this->td),
                      "iransansfa"  => _x("IRANSans (Farsi-Digits Support)","wc-setting",$this->td),
                      "iranyekanfa" => _x("IRANYekan (Farsi-Digits Support)","wc-setting",$this->td),
                    ),
                    'id'       => 'puiw_pdf_font',
                  ),
                  'puiw_pdf_end' => array(
                    'type' => 'sectionend',
                    'id'   => 'puiw_automation_end'
                ),
                )
              );
            break;
          default:
            $section_data = apply_filters( "puiw_setting_section_default",
                array(
                  'puiw_default_title' => array(
                    'name'              => _x("Store Details in Invoices", "wc-setting", $this->td),
                    'type'              => 'title',
                    'id'                => 'puiw_default_title'
                  ),
                  'puiw_store_name' => array(
                    'name'              => _x("Store Name", "wc-setting", $this->td),
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Store Name", "wc-setting", $this->td)),
                    'type'              => 'text',
                    'id'                => 'puiw_store_name',
                  ),
                  'puiw_store_logo' => array(
                    'name'              => _x("Store Logo", "wc-setting", $this->td),
                    'type'              => 'text',
                    'class'             => 'wc-select-uploader',
                    'desc_tip'          => _x("Select your store logo file", "wc-setting", $this->td),
                    'id'                => 'puiw_store_logo',
                    'css'               => 'display:none',
                    'custom_attributes' => array( 'dir' => 'ltr' , )
                  ),
                  'puiw_store_website' => array(
                    'name'              => _x("Store Website", "wc-setting", $this->td),
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Store Website", "wc-setting", $this->td)),
                    'type'              => 'text',
                    'id'                => 'puiw_store_website',
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_store_email' => array(
                    'name'              => _x("Store E-mail", "wc-setting", $this->td),
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Store E-mail", "wc-setting", $this->td)),
                    'type'              => 'email',
                    'id'                => 'puiw_store_email',
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_store_phone' => array(
                    'name'              => _x("Store Phone number", "wc-setting", $this->td),
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Store Phone number", "wc-setting", $this->td)),
                    'type'              => 'text',
                    'id'                => 'puiw_store_phone',
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_store_national_id' => array(
                    'name'              => _x("Store National ID", "wc-setting", $this->td),
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Store National ID", "wc-setting", $this->td)),
                    'type'              => 'text',
                    'id'                => 'puiw_store_national_id',
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_store_registration_number' => array(
                    'name'              => _x("Store Registration Number", "wc-setting", $this->td),
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Store Registration Number", "wc-setting", $this->td)),
                    'type'              => 'text',
                    'id'                => 'puiw_store_registration_number',
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_store_economical_number' => array(
                    'name'              => _x("Store Economical Number", "wc-setting", $this->td),
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Store Economical Number", "wc-setting", $this->td)),
                    'type'              => 'text',
                    'id'                => 'puiw_store_economical_number',
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_store_address' => array(
                    'name'              => _x("Store Address", "wc-setting", $this->td),
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Store Address", "wc-setting", $this->td)),
                    'type'              => 'textarea',
                    'id'                => 'puiw_store_address',
                    'desc_tip'          => _x("This Address will be used instead of Woocommerce store address in invoices, leave empty to use Woocommerce default address", "wc-setting", $this->td),
                  ),
                  'puiw_store_postcode' => array(
                    'name'              => _x("Store Postcode / ZIP", "wc-setting", $this->td),
                    'placeholder'       => sprintf(__("Enter %s here", $this->td), _x("Store Postcode / ZIP", "wc-setting", $this->td)),
                    'type'              => 'text',
                    'id'                => 'puiw_store_postcode',
                    'custom_attributes' => array( 'dir' => 'ltr', )
                  ),
                  'puiw_default_end' => array(
                    'type' => 'sectionend',
                    'id'   => 'puiw_default_end'
                  ),
                )
            );
            break;
          }
          return apply_filters("woocommerce_get_settings_{$this->id}", $section_data, $current_section);
      }
      public function output()
      {
        global $current_section, $hide_save_button, $PeproUltimateInvoice;

        switch ($current_section) {
          case 'debug':
            $hide_save_button = true;
            $this->print_debug_section();
          break;
          case 'color':
            $hide_save_button = true;
            wp_enqueue_script("jquery-confirm",     PEPROULTIMATEINVOICE_ASSETS_URL . "/js/jquery-confirm.min.js", array("jquery"));
            wp_enqueue_script("jquery-color",       PEPROULTIMATEINVOICE_ASSETS_URL . "/js/jquery.color.min.js", array("jquery"));
            wp_enqueue_style("jquery-confirm",      PEPROULTIMATEINVOICE_ASSETS_URL . "/css/jquery-confirm.min.css", array(), '1.0', 'all');
            wp_enqueue_style("jquery-minicolors",   PEPROULTIMATEINVOICE_ASSETS_URL . "/css/jquery.minicolors" . $this->debugEnabled(".css",".min.css"), array(), '1.0', 'all');
            wp_enqueue_script("jquery-minicolors",  PEPROULTIMATEINVOICE_ASSETS_URL . "/js/jquery.minicolors.min.js", array("jquery"));
            wp_enqueue_style( "color-scheme.css",   PEPROULTIMATEINVOICE_ASSETS_URL . '/admin/color-scheme' . $this->debugEnabled(".css",".min.css"));
            wp_register_script( "color-scheme.js",  PEPROULTIMATEINVOICE_ASSETS_URL . '/admin/color-scheme' . $this->debugEnabled(".js",".min.js"), array('jquery'), "1.1.0");
            wp_localize_script( "color-scheme.js", "msg", array(
              "delete"       => __("Delete",$this->td),
              "edit"         => __("Edit",$this->td),
              "apply"        => __("Apply",$this->td),
              "discard"      => __("Discard",$this->td),
              "export"       => __("Export",$this->td),
              "import"       => __("Import",$this->td),
              "commingsoon"  => __("Comming Soon ...",$this->td),
              "primary"      => __("Primary",$this->td),
              "secondary"    => __("Secondary",$this->td),
              "tertiary"     => __("Tertiary",$this->td),
              "click2change" => __("Click to change",$this->td),
              "importErr"    => __("Error importing data!",$this->td),
              "importErr2"   => __("Please check the data or contact support@pepro.dev",$this->td),
              "insertErr"    => __("Enter data here to Import.",$this->td),
              "swatchinp"    => __("Press ENTER to set name or ESC to discard",$this->td),
              ));
            wp_localize_script( "color-scheme.js", "_i18n", array(
              "td"         => "puiw_{$this->td}",
              "ajax"       => admin_url("admin-ajax.php"),
              "home"       => home_url(),
              "nonce"      => wp_create_nonce($this->td),
              "plugin_url" => PEPROULTIMATEINVOICE_URL,

              "errorTxt"   => _x("Error", "wc-setting-js", $this->td),
              "cancelTtl"  => _x("Canceled", "wc-setting-js", $this->td),
              "confirmTxt" => _x("Confirm", "wc-setting-js", $this->td),
              "successTtl" => _x("Success", "wc-setting-js", $this->td),
              "submitTxt"  => _x("Submit", "wc-setting-js", $this->td),
              "okTxt"      => _x("Okay", "wc-setting-js", $this->td),
              "txtCopy"    => _x("Copy to clipboard", "wc-setting-js", $this->td),
              "txtYes"     => _x("Yes", "wc-setting-js", $this->td),
              "txtImport"  => _x("Import", "wc-setting-js", $this->td),
              "txtNop"     => _x("No", "wc-setting-js", $this->td),
              "cancelbTn"  => _x("Cancel", "wc-setting-js", $this->td),
              "sendTxt"    => _x("Send to all", "wc-setting-js", $this->td),
              "closeTxt"   => _x("Close", "wc-setting-js", $this->td),

              "confirm_trash"       => _x("Are you sure you want to Delete This Color Schemes?<br>THIS CAN NOT BE UNDONE.", "wc-setting-js", $this->td),
              "confirm_delete"      => _x("Are you sure you want to Delete All Color Schemes?", "wc-setting-js", $this->td),
              "confirm_restore"     => _x("Are you sure you want to Restore All Color Schemes to Default?<br>THIS WILL DELETE ALL CURRENT ITEMS AND IT CAN NOT BE UNDONE.", "wc-setting-js", $this->td),
              ));
            wp_enqueue_script( "color-scheme.js");
            $this->print_color_schemes();
          break;
          case 'migrate':
            wp_enqueue_style( "color-scheme.css",   PEPROULTIMATEINVOICE_ASSETS_URL . '/admin/migrate-backup' . $this->debugEnabled(".css",".min.css"));
            wp_enqueue_script("jquery-confirm",     PEPROULTIMATEINVOICE_ASSETS_URL . "/js/jquery-confirm.min.js", array("jquery"));
            wp_enqueue_style("jquery-confirm",      PEPROULTIMATEINVOICE_ASSETS_URL . "/css/jquery-confirm.min.css", array(), '1.0', 'all');
            wp_register_script( "migrate-backup.js",  PEPROULTIMATEINVOICE_ASSETS_URL . '/admin/migrate-backup' . $this->debugEnabled(".js",".min.js"), array('jquery'), "1.1.0");
            wp_localize_script( "migrate-backup.js", "msg", array(
              "delete"       => __("Delete",$this->td),
              "edit"         => __("Edit",$this->td),
              "apply"        => __("Apply",$this->td),
              "discard"      => __("Discard",$this->td),
              "export"       => __("Export",$this->td),
              "import"       => __("Import",$this->td),
              "commingsoon"  => __("Comming Soon ...",$this->td),
              "primary"      => __("Primary",$this->td),
              "secondary"    => __("Secondary",$this->td),
              "tertiary"     => __("Tertiary",$this->td),
              "click2change" => __("Click to change",$this->td),
              "importErr"    => __("Error importing data!",$this->td),
              "exportErr"    => __("Error exporting data!",$this->td),
              "importErr2"   => __("Please check the data or contact support@pepro.dev",$this->td),
              "insertErr"    => __("Enter data here to Import.",$this->td),
              "swatchinp"    => __("Press ENTER to set name or ESC to discard",$this->td),
              ));
            wp_localize_script( "migrate-backup.js", "_i18n", array(
              "td"         => "puiw_{$this->td}",
              "ajax"       => admin_url("admin-ajax.php"),
              "home"       => home_url(),
              "nonce"      => wp_create_nonce($this->td),
              "plugin_url" => PEPROULTIMATEINVOICE_URL,
              "json_data"  => $PeproUltimateInvoice->change_default_settings("json"),
              "errorTxt"   => _x("Error", "wc-setting-js", $this->td),
              "cancelTtl"  => _x("Canceled", "wc-setting-js", $this->td),
              "cautiontl"  => _x("Caution", "wc-setting-js", $this->td),
              "confirmTxt" => _x("Confirm", "wc-setting-js", $this->td),
              "confirmMsg" => _x("Are you sure you want to continue?", "wc-setting-js", $this->td),
              "successTtl" => _x("Success", "wc-setting-js", $this->td),
              "reloadTxt"  => _x("Reload page", "wc-setting-js", $this->td),
              "submitTxt"  => _x("Submit", "wc-setting-js", $this->td),
              "okTxt"      => _x("Okay", "wc-setting-js", $this->td),
              "txtCopy"    => _x("Copy to clipboard", "wc-setting-js", $this->td),
              "txtYes"     => _x("Yes", "wc-setting-js", $this->td),
              "txtImport"  => _x("Import", "wc-setting-js", $this->td),
              "txtNop"     => _x("No", "wc-setting-js", $this->td),
              "cancelbTn"  => _x("Cancel", "wc-setting-js", $this->td),
              "sendTxt"    => _x("Send to all", "wc-setting-js", $this->td),
              "closeTxt"   => _x("Close", "wc-setting-js", $this->td),

              "confirm_trash"       => _x("Are you sure you want to Delete This Color Schemes?<br>THIS CAN NOT BE UNDONE.", "wc-setting-js", $this->td),
              "confirm_delete"      => _x("Are you sure you want to Delete All Color Schemes?", "wc-setting-js", $this->td),
              "confirm_restore"     => _x("Are you sure you want to Restore All Color Schemes to Default?<br>THIS WILL DELETE ALL CURRENT ITEMS AND IT CAN NOT BE UNDONE.", "wc-setting-js", $this->td),
              ));
            wp_enqueue_script( "migrate-backup.js");
            $hide_save_button = true;
            $this->print_migrate_backup();
          break;
          default:
            $settings = $this->get_settings($current_section);
            WC_Admin_Settings::output_fields($settings);
          break;
        }

      }
      public function print_debug_section()
      {
        ob_start();
        ?>
        <h2><?php echo _x("Debug Help", "wc-setting", $this->td);?></h2>
        <br>
        <table class='form-table'>
          <tbody>
            <tr valign="top">
              <th scope="row" class="titledesc"><?=__("Module", $this->td);?></th>
              <td><?=__("Status", $this->td);?></td>
            </tr>
            <?php
            $variable = apply_filters("puiw_debug_list_items", array(
              "mbstring"          => "extension",
              "mbregex"           => "extension",
              "mb_regex_encoding" => "function",
              "gd"                => "extension",
              "zlib"              => "extension",
              "bcmath"            => "extension",
              "xml"               => "extension",
              "curl"              => "extension",
            ));
            foreach ( $variable as $key => $value) {
              $status = ("extension" == $value) ?
                (extension_loaded($key) ? "<span style='color: green;'>".__("OK", $this->td)."</span>" : "<span style='color: red;'>".__("ERROR",$this->td)."</span>" ) :
                (function_exists($key) ? "<span style='color: green;'>".__("OK", $this->td)."</span>" : "<span style='color: red;'>".__("ERROR",$this->td)."</span>") ;
              ?>
              <tr valign="top">
                <th scope="row" class="titledesc" ><a href="https://www.google.com/search?q=php+<?=$key;?>" target="_blank"><?=$key;?></a></th>
                <td><?=$status;?></td>
              </tr>
              <?php
            }
            ?>
          </tbody>
        </table>
        <?php
        $html_output = ob_get_contents();
        ob_end_clean();
        echo apply_filters( "puiw_section_debug_html", $html_output);
      }
      public function print_color_schemes()
      {
        ob_start();
        ?>
        <h2><?php echo _x("Color Schemes", "wc-setting", $this->td);?></h2>
        <br>
        <div class="puiw_color_schemes_wrap">
          <textarea readonly="readonly" onclick="this.focus();this.select()" onfocus="this.focus();this.select();" id="puiw_color_schemes" style="display: none;" name="puiw_color_schemes" rows="8" cols="200"><?php echo esc_js(get_option("puiw_color_swatches",""));?></textarea>
          <br>
          <p class="puiw_color_schemes_tool">
            <a class="button button-primary swatches-save" href="#"><?php echo _x("Save Color Schemes","swatches-panel",$this->td);?></a>
            <span style="margin: 0.5rem;color: #ccc;"></span>
            <a class="button button-secondary swatches-add-new" href="#"><?php echo _x("Add New","swatches-panel",$this->td);?></a>
            <a class="button button-secondary swatches-delete-all" href="#"><?php echo _x("Delete All","swatches-panel",$this->td);?></a>
            <a class="button button-secondary swatches-restore-default" href="#"><?php echo _x("Restore Default","swatches-panel",$this->td);?></a>
            <span style="margin: 0.5rem;color: #ccc;"></span>
            <a class="button button-secondary swatches-export" href="#"><?php echo _x("Export","swatches-panel",$this->td);?></a>
            <a class="button button-secondary swatches-import show" href="#"><?php echo _x("Import","swatches-panel",$this->td);?></a>
          </p>
          <div class="puiw_color_schemes_workspace" empty="<?php echo __("No Color Scheme Found!",$this->td);?>"></div>
          <p class="puiw_color_schemes_tool">
             <a class="button button-primary swatches-save" href="#"><?php echo _x("Save Color Schemes","swatches-panel",$this->td);?></a>
             <span style="margin: 0.5rem;color: #ccc;"></span>
             <a class="button button-secondary swatches-add-new" href="#"><?php echo _x("Add New","swatches-panel",$this->td);?></a>
             <a class="button button-secondary swatches-delete-all" href="#"><?php echo _x("Delete All","swatches-panel",$this->td);?></a>
             <a class="button button-secondary swatches-restore-default" href="#"><?php echo _x("Restore Default","swatches-panel",$this->td);?></a>
             <span style="margin: 0.5rem;color: #ccc;"></span>
             <a class="button button-secondary swatches-export" href="#"><?php echo _x("Export","swatches-panel",$this->td);?></a>
             <a class="button button-secondary swatches-import show" href="#"><?php echo _x("Import","swatches-panel",$this->td);?></a>
          </p>
        </div>
        <?php
        $html_output = ob_get_contents();
        ob_end_clean();
        echo apply_filters( "puiw_section_color_html", $html_output);
      }
      public function print_migrate_backup()
      {
        ob_start();
        ?>
        <h2><?php echo _x("Migrate/Backup Settings", "wc-setting", $this->td);?></h2>
        <br>
        <div class="puiw_color_schemes_wrap">
          <div class="puiw_color_schemes_tool">
            <a class="button button-secondary" target="_blank" href="<?=add_query_arg(["ultimate-invoice-get"=>"yes", "nonce"=> wp_create_nonce("pepro-ultimate-invoice")], admin_url());?>"><?php echo _x("Export PHP","swatches-panel",$this->td);?></a>
            <a class="button button-secondary backup-export" href="#"><?php echo _x("Export JSON","swatches-panel",$this->td);?></a>
            <a class="button button-secondary backup-import show" href="#"><?php echo _x("Import JSON","swatches-panel",$this->td);?></a>
          </div>
          <h2 class="red"><?php echo _x("Danger Zone!", "wc-setting", $this->td);?></h2>
          <h3><?php echo _x("Do not use any of buttons below without knowing what your are doing!", "wc-setting", $this->td);?></h3>
          <div class="puiw_color_schemes_tool">
              <a class="button button-secondary btn-confirm" target="_blank" href="<?=add_query_arg(["ultimate-invoice-reset"=>"yes", "nonce"=> wp_create_nonce("pepro-ultimate-invoice")], admin_url());?>"><?php echo _x("FORCE RESET TO DEFAULT SETTINGS","swatches-panel",$this->td);?></a>
              <a class="button button-secondary btn-confirm" target="_blank" href="<?=add_query_arg(["ultimate-invoice-clear"=>"yes", "nonce"=> wp_create_nonce("pepro-ultimate-invoice")], admin_url());?>"><?php echo _x("FORCE CLEAR OUT ALL SETTINGS","swatches-panel",$this->td);?></a>
              <a class="button button-secondary btn-confirm" target="_blank" href="<?=add_query_arg(["ultimate-invoice-set"=>"yes", "nonce"=> wp_create_nonce("pepro-ultimate-invoice")], admin_url());?>"><?php echo _x("FILL OUT EMPTY SETTINGS WITH DEFAULT VALUES","swatches-panel",$this->td);?></a>
          </div>
        </div>
        <?php
        $html_output = ob_get_contents();
        ob_end_clean();
        echo apply_filters( "puiw_section_migrate_backup_html", $html_output);
      }
      public function save()
      {
        global $current_section;
        $settings = $this->get_settings($current_section);
        WC_Admin_Settings::save_fields($settings);
      }

      /* ========= extas ========= */

      /**
       * add scripts and styles to wc setting page
       *
       * @method wp_enqueue_scripts
       * @return null
       * @version 1.0.0
       * @since 1.0.0
       * @license https://pepro.dev/license Pepro.dev License
       */
      public function wp_enqueue_scripts()
      {
        global $PeproUltimateInvoice;
        wp_enqueue_media();
        add_thickbox();
        // due to lag between page load and css load, we load css directrly at line 70
        wp_enqueue_style("pepro-ultimate-invoice-wc-setting", PEPROULTIMATEINVOICE_ASSETS_URL . "/admin/wc_setting.css");
        wp_enqueue_style("wp-color-picker");
        wp_enqueue_script("wp-color-picker");
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-selectmenu');
        wp_register_script("pepro-ultimate-invoice-wc-setting", PEPROULTIMATEINVOICE_ASSETS_URL . "/admin/wc_setting"  . $this->debugEnabled(".js",".min.js"), array("jquery","wp-color-picker"));
        wp_localize_script( "pepro-ultimate-invoice-wc-setting", "_peproUltimateInvoice", array(
            "title"        => _x("Select image file", "wc-setting-js", $this->td),
            "btntext"      => _x("Use this image", "wc-setting-js", $this->td),
            "clear"        => _x("Clear", "wc-setting-js", $this->td),
            "currentlogo"  => _x("Current preview", "wc-setting-js", $this->td),
            "selectbtn"    => _x("Select image", "wc-setting-js", $this->td),
            "copied"       => _x("## copied to clipboard.", "wc-setting-js", $this->td),
            "zephyrfix"    => true, /* fix for upsulotion admin css */
            "plugin_url"   => PEPROULTIMATEINVOICE_URL,
            "themeData"    => $PeproUltimateInvoice->load_themes(1),
            "get_template" => get_option("puiw_template", "default"),
            "darkmode"     => get_option("puiw_dark_mode", "no"),
          )
        );
        wp_enqueue_script("pepro-ultimate-invoice-wc-setting");
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
        $users = get_users( array(
          "role__in" => array( "administrator", "shop_manager" ) )
        );
        foreach ($users as $user) {
          $_wc_managers[$user->user_email] = "$user->user_firstname $user->user_lastname ($user->user_email)";
        }
        return $_wc_managers;
      }
      /**
       * get all registered woocommerce payment statuses
       *
       * @method  wc_get_order_statuses
       * @return  array                    all registered woocommerce payment statuses
       * @access  public
       * @version 1.0.0
       * @since   1.0.0
       * @license https://pepro.dev/license Pepro.dev License
       */
      public function wc_get_order_statuses()
      {
        return wc_get_order_statuses();
      }
    }
    $settings[] = new PeproUltimateInvoice_WC_Setting();
    return $settings;
  }
}
