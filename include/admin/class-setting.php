<?php
# @Last modified time: 2022/10/13 22:34:05

defined("ABSPATH") or die("Pepro Ultimate Invoice :: Unauthorized Access!");
add_filter("woocommerce_get_settings_pages", function ($pages) {
    if (class_exists("WC_Settings_Page")) {
      $pages[] = new class
      extends WC_Settings_Page{
          public $td;
          public function __construct() {
            global $PeproUltimateInvoice;
            $this->td = "pepro-ultimate-invoice";
            $this->id = "pepro_ultimate_invoice";
            $GLOBALS["puiw_settings_page"] = $this;
            $this->label = _x("Ultimate Invoice", "wc-setting", "pepro-ultimate-invoice");
            parent::__construct();

            add_action("woocommerce_settings_{$this->id}",       array($this, 'output'));
            add_action("woocommerce_settings_save_{$this->id}",  array($this, 'save'));
            add_action("woocommerce_sections_{$this->id}",       array($this, 'output_sections'));
            add_action("admin_enqueue_scripts", function () {
              if ($this->is_our_screen()) {
                $this->wp_enqueue_scripts();
              }
            });
            // ahead of every enqueued asset, so the page can still speak for itself
            // when something further down the queue never runs
            add_action("admin_print_scripts", function () {
              if ($this->is_our_screen()) {
                $this->print_boot_guard();
              }
            }, 1);
            add_filter("admin_body_class", function ($classes) {
              return $this->is_our_screen() ? "$classes puiw-settings-screen" : $classes;
            });
            // WooCommerce has no save rule for the field types we render ourselves, so a
            // field that posts nothing - a radio group whose stored value is no longer one
            // of the choices, a template folder that was deleted - arrives as null and is
            // written back as an empty string, wiping the setting. Returning null here
            // tells save_fields() to leave that option exactly as it is.
            add_filter("woocommerce_admin_settings_sanitize_option", function ($value, $option, $raw_value) {
              // a field shown disabled posts nothing; keep what is stored
              if (!empty($option["puiw_disabled"])) {
                return null;
              }
              // stylesheets are stored as written: wp_kses_post() on a textarea turned
              // every child selector's ">" into "&gt;" and broke the rule
              if (!empty($option["puiw_code"]) && null !== $raw_value) {
                return trim(str_ireplace(array("</style", "<style", "<script", "</script"), "", (string) $raw_value));
              }
              $ours = array("radio", "select", "puiw_template", "puiw_image", "puiw_html", "puiw_note", "puiw_skip");
              if (null === $raw_value && !empty($option["type"]) && in_array($option["type"], $ours, true)) {
                return null;
              }
              return $value;
            }, 10, 3);
            // links from the releases that had one page per section still work,
            // they are turned into the panel anchor and the query arg is dropped
            add_action("admin_init", function () {
              if (!$this->is_our_screen() || empty($_GET["section"])) {
                return;
              }
              $panel = sanitize_key(wp_unslash($_GET["section"]));
              $url   = remove_query_arg("section");
              if (isset($this->get_panels()[$panel])) {
                $url .= "#puiw-" . $panel;
              }
              wp_safe_redirect($url);
              exit;
            });


            if (isset($_GET["tab"]) && ($_GET["tab"] == $this->id)) {
              $PeproUltimateInvoice->update_footer_info();
              add_action("wp_before_admin_bar_render", array($PeproUltimateInvoice, 'wp_before_admin_bar_render_back'));
            }
          }
          public function debugEnabled($debug_true = true, $debug_false = false) {
            return apply_filters("puiw_debug_enabled", $debug_true);
          }
          /**
           * list the item meta keys and product attributes found on recent orders so the
           * shop manager can pick an existing key instead of guessing one
           *
           * @method get_item_meta_key_hints
           * @return array key => kind, for the field's chips
           * @version 1.1.0
           * @since 2.3.8
           * @license https://peprodev.com/license Pepro.dev License
           */
          public function get_item_meta_key_hints() {
            $keys = get_transient("puiw_known_item_meta_keys");
            if (false === $keys) {
              $keys = array();
              if (function_exists("wc_get_orders")) {
                $orders = wc_get_orders(array("limit" => 20, "orderby" => "date", "order" => "DESC"));
                foreach ($orders as $order) {
                  if (!is_a($order, "WC_Order")) {
                    continue;
                  }
                  foreach ($order->get_items() as $item) {
                    foreach ($item->get_meta_data() as $meta) {
                      $data = $meta->get_data();
                      $key  = isset($data["key"]) ? (string) $data["key"] : "";
                      // internal bookkeeping meta is never useful on an invoice
                      if ("" === $key || in_array($key, array("_reduced_stock", "_puiw_regular", "_puiw_sale", "_puiw_html", "_puiw_version"), true)) {
                        continue;
                      }
                      $keys[$key] = true;
                    }
                  }
                }
              }
              if (function_exists("wc_get_attribute_taxonomies")) {
                foreach (wc_get_attribute_taxonomies() as $tax) {
                  $keys["pa_" . $tax->attribute_name] = true;
                }
              }
              $keys = array_keys($keys);
              sort($keys);
              set_transient("puiw_known_item_meta_keys", $keys, HOUR_IN_SECONDS);
            }
            $chips = array();
            foreach (array_slice((array) $keys, 0, 40) as $key) {
              $chips[$key] = 0 === strpos($key, "pa_")
                ? _x("Product attribute", "wc-setting", "pepro-ultimate-invoice")
                : _x("Item meta", "wc-setting", "pepro-ultimate-invoice");
            }
            return $chips;
          }
          /**
           * a per-language template set under Translations wins over the one picked
           * here; say so above the picker, or picking a template looks like it does
           * nothing
           *
           * @method language_template_note
           * @return string empty when no language overrides the template
           */
          public function language_template_note() {
            global $PeproUltimateInvoice;
            if (empty($PeproUltimateInvoice->i18n)) {
              return "";
            }
            $main  = untrailingslashit((string) get_option("puiw_template", ""));
            $names = array();
            foreach ((array) $PeproUltimateInvoice->i18n->get_languages() as $code => $lang) {
              $own = untrailingslashit((string) $PeproUltimateInvoice->i18n->get_template_for($code));
              if ("" !== $own && $own !== $main) {
                $names[] = sprintf("%s: %s", $lang["name"], basename($own));
              }
            }
            if (!$names) {
              return "";
            }
            $clear = wp_nonce_url(add_query_arg("puiw-clear-lang-templates", 1, admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice#puiw-theme")), "puiw-clear-lang-templates");
            return sprintf(
              /* translators: %s: languages and their own template */
              _x("Some languages use their own template, set under Translations, so the one picked here does not apply to them: %s.", "wc-setting", "pepro-ultimate-invoice"),
              "<strong>" . esc_html(implode("، ", $names)) . "</strong>"
            ) . sprintf(' <a href="%s">%s</a>', esc_url($clear), esc_html(_x("Use this template for every language", "wc-setting", "pepro-ultimate-invoice")));
          }
          public function get_site_roles() {
            $roles = array();
            if (!function_exists("wp_roles")) {
              return $roles;
            }
            foreach (wp_roles()->roles as $slug => $role) {
              $roles[$slug] = translate_user_role($role["name"]);
            }
            return $roles;
          }
          /**
           * true while WooCommerce is rendering our own settings tab
           *
           * @method is_our_screen
           * @return bool
           */
          public function is_our_screen() {
            return isset($_GET["page"], $_GET["tab"]) && "wc-settings" === $_GET["page"] && $this->id === $_GET["tab"];
          }
          /**
           * sidebar groups, in the order a shop is set up
           *
           * @method get_section_groups
           * @return array
           */
          public function get_section_groups() {
            return apply_filters("puiw_settings_section_groups", array(
              "overview" => array(
                "label"    => _x("Overview", "wc-setting", "pepro-ultimate-invoice"),
                "sections" => array("dashboard", "allsettings"),
              ),
              "invoice" => array(
                "label"    => _x("Invoice", "wc-setting", "pepro-ultimate-invoice"),
                "sections" => array("general", "items", "theme", "color", "templates", "pdf", "barcode"),
              ),
              "workflow" => array(
                "label"    => _x("Workflow", "wc-setting", "pepro-ultimate-invoice"),
                "sections" => array("report", "email", "strings", "privacy"),
              ),
              "system" => array(
                "label"    => _x("System", "wc-setting", "pepro-ultimate-invoice"),
                "sections" => array("integ", "extras", "misc", "migrate"),
              ),
              "help" => array(
                "label"    => _x("Help", "wc-setting", "pepro-ultimate-invoice"),
                "sections" => array("changelog", "devdocs", "debug"),
              ),
              "upgrade" => array(
                "label"    => _x("Upgrade", "wc-setting", "pepro-ultimate-invoice"),
                "sections" => array("pro"),
              ),
            ));
          }
          /**
           * WooCommerce uses this for the ?section= nav and for its own links
           *
           * the tab is a single page now, so it stays empty and the sidebar
           * drives the panels instead
           *
           * @method get_sections
           * @return array
           */
          public function get_sections() {
            return array();
          }
          /**
           * the panels the sidebar lists, in sidebar order
           *
           * @method get_panels
           * @return array
           */
          public function get_panels() {
            $sections = array(
              'dashboard' => _x("Dashboard", "wc-setting", "pepro-ultimate-invoice"),
              'allsettings' => _x("All settings", "wc-setting", "pepro-ultimate-invoice"),
              'general' => _x("Store Details", "wc-setting", "pepro-ultimate-invoice"),
              'items'   => _x("Invoice Items", "wc-setting", "pepro-ultimate-invoice"),
              'pdf'     => _x("PDF Invoice", "wc-setting", "pepro-ultimate-invoice"),
              'theme'   => _x("Theming", "wc-setting", "pepro-ultimate-invoice"),
              'report'  => _x("Inventory", "wc-setting", "pepro-ultimate-invoice"),
              'email'   => _x("Email", "wc-setting", "pepro-ultimate-invoice"),
              'barcode' => _x("Barcode & QR", "wc-setting", "pepro-ultimate-invoice"),
              'privacy' => _x("Privacy", "wc-setting", "pepro-ultimate-invoice"),
              'color'   => _x("Color Schemes", "wc-setting", "pepro-ultimate-invoice"),
              'integ'   => _x("Integration", "wc-setting", "pepro-ultimate-invoice"),
              'extras'   => _x("Extra Features", "wc-setting", "pepro-ultimate-invoice"),
              'strings'   => _x("Translations", "wc-setting", "pepro-ultimate-invoice"),
              'templates' => _x("Template Editor", "wc-setting", "pepro-ultimate-invoice"),
              'misc'      => _x("Misc.", "wc-setting", "pepro-ultimate-invoice"),
              'migrate'   => _x("Migrate/Backup", "wc-setting", "pepro-ultimate-invoice"),
              'changelog' => _x("Changelog", "wc-setting", "pepro-ultimate-invoice"),
              'devdocs'   => _x("Developers", "wc-setting", "pepro-ultimate-invoice"),
              'debug'     => _x("Debug", "wc-setting", "pepro-ultimate-invoice"),
              'pro'       => _x("PRO &amp; Add-ons", "wc-setting", "pepro-ultimate-invoice"),
            );
            return apply_filters("puiw_settings_panels", $sections);
          }
          public function get_settings($current_section = "") {
            global $PeproUltimateInvoice;
            $i = $current_section;
            switch ($current_section) {
              case 'items':
                $item_meta_keys = $this->get_item_meta_key_hints();
                $section_data = apply_filters(
                  "puiw_setting_section_{$current_section}",
                  array(
                    "puiw_items_title" => array(
                      'puiw_tabs' => array(
                        'people' => _x("Store and customer", "wc-setting-tab", "pepro-ultimate-invoice"),
                        'order'  => _x("Order details", "wc-setting-tab", "pepro-ultimate-invoice"),
                        'rows'   => _x("Item rows", "wc-setting-tab", "pepro-ultimate-invoice"),
                        'totals' => _x("Prices and totals", "wc-setting-tab", "pepro-ultimate-invoice"),
                      ),
                      'name' => _x("Show/Hide items in Invoices", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'title',
                      'id' => 'puiw_items_title'
                    ),
                    'puiw_show_store_national_id' => array(
                      'puiw_tab' => 'people',
                      'name' => _x("Show Store National Id", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_store_national_id',
                    ),
                    'puiw_show_store_registration_number' => array(
                      'puiw_tab' => 'people',
                      'name' => _x("Show Store Registration Number", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_store_registration_number',
                    ),
                    'puiw_show_store_economical_number' => array(
                      'puiw_tab' => 'people',
                      'name' => _x("Show Store Economical Number", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_store_economical_number',
                    ),
                    'puiw_show_customer_address' => array(
                      'puiw_tab' => 'people',
                      'name' => _x("Show Customer Address", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_customer_address',
                    ),
                    'puiw_show_customer_phone' => array(
                      'puiw_tab' => 'people',
                      'name' => _x("Show Customer Phone", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_customer_phone',
                    ),
                    'puiw_show_customer_email' => array(
                      'puiw_tab' => 'people',
                      'name' => _x("Show Customer E-mail", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_customer_email',
                    ),
                    'puiw_show_customer_order_count' => array(
                      'puiw_tab' => 'people',
                      'name'     => _x("Show customer order count", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'     => _x("Check to print how many orders this customer has placed so far", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_customer_order_count',
                    ),
                    'puiw_show_user_uin' => array(
                      'puiw_tab' => 'people',
                      'name'     => _x("Activate User UIN feature & Show it", "wc-setting", "pepro-ultimate-invoice"),
                      'label'     => _x("Check to add or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'     => _x("Check to add or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("Show User UIN (Unique Identification Number) in invoice which will be received at checkout page (will save as 'billing_uin' user meta)", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_user_uin',
                    ),
                    'puiw_user_uin_required' => array(
                      'puiw_tab' => 'people',
                      'name'     => _x("User UIN is mandatory", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'     => _x("Check to require it at checkout, or leave unchecked so customers can complete it later from their account", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_user_uin_required',
                    ),
                    'puiw_user_uin_label' => array(
                      'puiw_tab' => 'people',
                      'name'        => _x("User UIN field label", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip'    => _x("Leave empty to use the default label", "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder' => _x("Unique Identification Number", "wc-setting", "pepro-ultimate-invoice"),
                      'type'        => 'text',
                      'id'          => 'puiw_user_uin_label',
                    ),
                    'puiw_postcode_display' => array(
                      'puiw_tab' => 'people',
                      'name'     => _x("Customer postcode placement", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("WooCommerce already prints the postcode inside the formatted address, which is why it can appear twice", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'select',
                      'class'    => 'wc-enhanced-select',
                      'id'       => 'puiw_postcode_display',
                      'options'  => array(
                        'both'    => _x("Both places", "wc-setting", "pepro-ultimate-invoice"),
                        'field'   => _x("Separate field", "wc-setting", "pepro-ultimate-invoice"),
                        'address' => _x("In the address", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    'puiw_show_shipping_address' => array(
                      'puiw_tab' => 'people',
                      'name' => _x("Address on Invoices", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'radio',
                      'default' => 'billing',
                      'options' => array(
                        "billing" => _x("Billing address", "wc-setting", "pepro-ultimate-invoice"),
                        "shipping" => _x("Shipping address", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                      'id'   => 'puiw_show_shipping_address',
                    ),
                    'puiw_address_display_method' => array(
                      'puiw_tab' => 'people',
                      'name'    => _x("Customer Address template", "wc-setting", "pepro-ultimate-invoice"),
                      'type'    => 'textarea',
                      'placeholder' => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Customer Address template", "wc-setting", "pepro-ultimate-invoice")),
                      'id'      => 'puiw_address_display_method',
                      'desc'    => _x("Use following tags and build your own addressing template.", "wc-setting", "pepro-ultimate-invoice"),
                      'puiw_chips' => array(
                        "[first_name]" => _x("First name", "wc-setting", "pepro-ultimate-invoice"),
                        "[last_name]"  => _x("Last name", "wc-setting", "pepro-ultimate-invoice"),
                        "[company]"    => _x("Company", "wc-setting", "pepro-ultimate-invoice"),
                        "[country]"    => _x("Country", "wc-setting", "pepro-ultimate-invoice"),
                        "[province]"   => _x("Province", "wc-setting", "pepro-ultimate-invoice"),
                        "[city]"       => _x("City", "wc-setting", "pepro-ultimate-invoice"),
                        "[address1]"   => _x("Address line 1", "wc-setting", "pepro-ultimate-invoice"),
                        "[address2]"   => _x("Address line 2", "wc-setting", "pepro-ultimate-invoice"),
                        "[po_box]"     => _x("Postcode", "wc-setting", "pepro-ultimate-invoice"),
                        "[email]"      => _x("E-mail", "wc-setting", "pepro-ultimate-invoice"),
                        "[phone]"      => _x("Phone", "wc-setting", "pepro-ultimate-invoice"),
                        "[uin]"        => _x("UIN", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    'puiw_show_order_date' => array(
                      'puiw_tab' => 'order',
                      'name' => _x("Show Order Date", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_order_date',
                    ),
                    'puiw_show_payment_method' => array(
                      'puiw_tab' => 'order',
                      'name' => _x("Show Payment method", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_payment_method',
                    ),
                    'puiw_show_shipping_method' => array(
                      'puiw_tab' => 'order',
                      'name' => _x("Show Shipping method", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_shipping_method',
                    ),
                    'puiw_transaction_ref_id' => array(
                      'puiw_tab' => 'order',
                      'name' => _x("Show Transaction Ref. ID", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_transaction_ref_id',
                    ),
                    'puiw_paid_date' => array(
                      'puiw_tab' => 'order',
                      'name' => _x("Show Paid Date", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_paid_date',
                    ),
                    'puiw_purchase_complete_date' => array(
                      'puiw_tab' => 'order',
                      'name' => _x("Show Purchase Completed Date", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_purchase_complete_date',
                    ),
                    'puiw_shipping_date' => array(
                      'puiw_tab' => 'order',
                      'name' => _x("Show Shipping Date", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_shipping_date',
                    ),
                    'puiw_order_status' => array(
                      'puiw_tab' => 'order',
                      'name' => _x("Show Order Status", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_order_status',
                    ),
                    'puiw_show_order_note' => array(
                      'puiw_tab' => 'order',
                      'name'    => _x("Show Order Note?", "wc-setting", "pepro-ultimate-invoice"),
                      'type'    => 'radio',
                      'id'      => 'puiw_show_order_note',
                      'default' => 'note_provided_by_customer',
                      'options' => array(
                        'hide_note_from_invoice'        => _x("Hide notes", "wc-setting", "pepro-ultimate-invoice"),
                        'note_provided_by_customer'     => _x("Customer note", "wc-setting", "pepro-ultimate-invoice"),
                        'note_provided_by_shop_manager' => _x("Manager note", "wc-setting", "pepro-ultimate-invoice"),
                        'note_provided_by_both'         => _x("Both notes", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    'puiw_show_order_note_slip' => array(
                      'puiw_tab' => 'order',
                      'name'    => _x("Show Order Note on Slip?", "wc-setting", "pepro-ultimate-invoice"),
                      'type'    => 'radio',
                      'id'      => 'puiw_show_order_note_slip',
                      'default' => 'note_provided_by_customer',
                      'options' => array(
                        'hide_note_from_invoice'        => _x("Hide notes", "wc-setting", "pepro-ultimate-invoice"),
                        'note_provided_by_customer'     => _x("Customer note", "wc-setting", "pepro-ultimate-invoice"),
                        'note_provided_by_shop_manager' => _x("Manager note", "wc-setting", "pepro-ultimate-invoice"),
                        'note_provided_by_both'         => _x("Both notes", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    'puiw_show_order_items' => array(
                      'puiw_tab' => 'rows',
                      'name' => _x("Show Order Items", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_order_items',
                    ),
                    'puiw_show_product_image' => array(
                      'puiw_tab' => 'rows',
                      'name' => _x("Show Product Image Column", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_product_image',
                    ),
                    'puiw_show_product_sku' => array(
                      'puiw_tab' => 'rows',
                      'name'     => _x("Show SKU Column", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_product_sku',
                    ),
                    'puiw_show_product_sku2' => array(
                      'puiw_tab' => 'rows',
                      'name'     => _x("Use ID when SKU's unavailable", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'     => _x("Check to use or leave unchecked to not", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_product_sku2',
                    ),
                    'puiw_show_product_weight' => array(
                      'puiw_tab' => 'rows',
                      'name'     => _x("Show Weight Column", "wc-setting", "pepro-ultimate-invoice"),
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_product_weight',
                    ),
                    'puiw_show_product_dimensions' => array(
                      'puiw_tab' => 'rows',
                      'name'     => _x("Show Dimensions Column", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_product_dimensions',
                    ),
                    'puiw_show_product_tax' => array(
                      'puiw_tab' => 'rows',
                      'name'     => _x("Show Tax Column", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_product_tax',
                    ),
                    'puiw_show_discount_precent' => array(
                      'puiw_tab' => 'rows',
                      'name'     => _x("Show Discount Column", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_discount_precent',
                    ),
                    'puiw_show_product_purchase_note' => array(
                      'puiw_tab' => 'rows',
                      'name' => _x("Show Product Purchase note", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_product_purchase_note',
                    ),
                    'puiw_show_item_discount' => array(
                      'puiw_tab' => 'rows',
                      'name'     => _x("Show original price and discount per item", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'     => _x("Check to print the pre-discount price when it differs from the paid price", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("Covers discounts applied by dynamic pricing plugins, which change the line item price instead of adding a coupon", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_item_discount',
                    ),
                    'puiw_item_extra_attributes' => array(
                      'puiw_tab' => 'rows',
                      'name'        => _x("Extra product data per item", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'        => $item_meta_keys
                        ? _x("Keys found on recent orders are listed below; pick one to add it.", "wc-setting", "pepro-ultimate-invoice")
                        : _x("No item meta was found on recent orders yet.", "wc-setting", "pepro-ultimate-invoice"),
                      'puiw_chips'  => $item_meta_keys,
                      'puiw_chips_mode' => 'list',
                      'puiw_stacked' => true,
                      'desc_tip'    => _x("Comma separated attribute or meta keys to print under each product, for example pa_warranty or _warranty_name", "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder' => "pa_warranty, _warranty_name",
                      'type'        => 'text',
                      'id'          => 'puiw_item_extra_attributes',
                    ),
                    'puiw_items_sorting' => array(
                      'puiw_tab' => 'rows',
                      'id'      => 'puiw_items_sorting',
                      'name'    => _x("Invoice Items Sorting", "wc-setting", "pepro-ultimate-invoice"),
                      'type'    => 'radio',
                      'default' => 'NONE',
                      'options' => array(
                        'NONE'         => _x("Default", "wc-setting", "pepro-ultimate-invoice"),
                        'ID'           => _x("By Item ID", "wc-setting", "pepro-ultimate-invoice"),
                        'PID'          => _x("By Product ID", "wc-setting", "pepro-ultimate-invoice"),
                        'SKU'          => _x("By SKU", "wc-setting", "pepro-ultimate-invoice"),
                        'QTY'          => _x("By Quantity", "wc-setting", "pepro-ultimate-invoice"),
                        'NAME'         => _x("By Name", "wc-setting", "pepro-ultimate-invoice"),
                        'PRICE'        => _x("By Price", "wc-setting", "pepro-ultimate-invoice"),
                        'TOTAL'        => _x("By Total", "wc-setting", "pepro-ultimate-invoice"),
                        'WEIGHT'       => _x("By Weight", "wc-setting", "pepro-ultimate-invoice"),
                        'SUBTOTAL'     => _x("By Subtotal", "wc-setting", "pepro-ultimate-invoice"),
                        'SUBTOTAL_TAX' => _x("By Subtotal Tax", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    'puiw_show_order_total' => array(
                      'puiw_tab' => 'totals',
                      'name' => _x("Show Order Total", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_order_total',
                    ),
                    'puiw_show_coupons_code_at_totals' => array(
                      'puiw_tab' => 'totals',
                      'type' => 'checkbox',
                      'name' => _x("Show Coupon(s) Code at Order totals", "wc-setting", "pepro-ultimate-invoice"),
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_coupons_code_at_totals',
                    ),
                    'puiw_show_coupons_amount_at_totals' => array(
                      'puiw_tab' => 'totals',
                      'type' => 'checkbox',
                      'name' => _x("Show Coupon(s) Amount at Order totals", "wc-setting", "pepro-ultimate-invoice"),
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_coupons_amount_at_totals',
                    ),
                    'puiw_show_coupons_discount_at_totals' => array(
                      'puiw_tab' => 'totals',
                      'type' => 'checkbox',
                      'name' => _x("Show Coupon(s) Discount at Order totals", "wc-setting", "pepro-ultimate-invoice"),
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_coupons_discount_at_totals',
                    ),
                    'puiw_show_coupons_description_at_totals' => array(
                      'puiw_tab' => 'totals',
                      'type' => 'checkbox',
                      'name' => _x("Show Coupon(s) Description at Order totals", "wc-setting", "pepro-ultimate-invoice"),
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'default' => 'yes',
                      'id'   => 'puiw_show_coupons_description_at_totals',
                    ),
                    'puiw_show_price_template' => array(
                      'puiw_tab' => 'totals',
                      'name'    => _x("Line item Price Display", "wc-setting", "pepro-ultimate-invoice"),
                      'type'    => 'radio',
                      'id'      => 'puiw_show_price_template',
                      'default' => 'show_both_regular_and_sale_price',
                      'options' => array(
                        'show_wc_price'                     => _x("Show WC_Order price (as shown in order details screen)", "wc-setting", "pepro-ultimate-invoice"),
                        'show_only_regular_price'           => _x("Show Current-live regular price", "wc-setting", "pepro-ultimate-invoice"),
                        'show_only_sale_price'              => _x("Show Current-live sale price", "wc-setting", "pepro-ultimate-invoice"),
                        'show_both_regular_and_sale_price'  => _x("Show Current-live regular/sale price", "wc-setting", "pepro-ultimate-invoice"),
                        'show_saved_regular_price'          => _x("Show Saved Line-item regular price", "wc-setting", "pepro-ultimate-invoice"),
                        'show_saved_sale_price'             => _x("Show Saved Line-item sale price", "wc-setting", "pepro-ultimate-invoice"),
                        'show_saved_regular_and_sale_price' => _x("Show Saved Line-item sale/regular price", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    'puiw_show_tax_display' => array(
                      'puiw_tab' => 'totals',
                      'name'    => _x("Line item Tax Display", "wc-setting", "pepro-ultimate-invoice"),
                      'type'    => 'radio',
                      'default' => 'labelamount',
                      'options' => array(
                        "amount"      => _x("Tax amount", "wc-setting", "pepro-ultimate-invoice"),
                        "labelamount" => _x("Tax name and amount", "wc-setting", "pepro-ultimate-invoice"),
                        "onlytotal"   => _x("Line tax total", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                      'id'   => 'puiw_show_tax_display',
                    ),
                    'puiw_show_discount_calc' => array(
                      'puiw_tab' => 'totals',
                      'name'     => _x("Line item Discount Calculation", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'radio',
                      'default'  => 'wcorder',
                      'desc_tip' => _x("Since ver. 1.8, both regular and sale price of products are saved into line-items meta in order to use later in discount calculation.", "wc-setting", "pepro-ultimate-invoice"),
                      'options'  => array(
                        "wcorder"   => _x("As WooCommerce shows it", "wc-setting", "pepro-ultimate-invoice"),
                        "liveprice" => _x("Current product prices", "wc-setting", "pepro-ultimate-invoice"),
                        "savepirce" => _x("Prices saved with the order", "wc-setting", "pepro-ultimate-invoice"),
                        // "advanced"  => _x("Based on Discounted Line-item saved sale/regular price", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                      'id'   => 'puiw_show_discount_calc',
                    ),
                    'puiw_show_discount_display' => array(
                      'puiw_tab' => 'totals',
                      'name'    => _x("Line item Discount Display", "wc-setting", "pepro-ultimate-invoice"),
                      'type'    => 'radio',
                      'default' => 'precnt',
                      'options' => array(
                        "value"  => _x("Discount amount", "wc-setting", "pepro-ultimate-invoice"),
                        "precnt" => _x("Discount percent", "wc-setting", "pepro-ultimate-invoice"),
                        "both"   => _x("Amount and percent", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                      'id'   => 'puiw_show_discount_display',
                    ),
                    'puiw_items_end' => array(
                      'type' => 'sectionend',
                      'id'   => 'puiw_items_end'
                    ),
                  )
                );
                break;
              case 'report':
                $section_data = apply_filters(
                  "puiw_setting_section_{$current_section}",
                  array(
                    'puiw_report_title' => array(
                      'name' => _x("Inventory Report Setting", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'title',
                      'id'   => 'puiw_report_title'
                    ),
                    'puiw_shelf_number_id' => array(
                      'name'     => _x("Shelf Storage Number ID in Inventory Report ", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_shelf_number_id',
                    ),
                    'puiw_show_product_sku_inventory' => array(
                      'name'     => _x("Show Product SKU in Inventory Report", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_product_sku_inventory',
                    ),
                    'puiw_show_product_sku2_inventory' => array(
                      'name'     => _x("Use ID when SKU's unavailable", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'     => _x("Check to use or leave unchecked to not", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_product_sku2_inventory',
                    ),
                    'puiw_show_product_image_inventory' => array(
                      'name' => _x("Show Product Image in Inventory Report", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_product_image_inventory',
                    ),
                    'puiw_show_product_weight_in_inventory' => array(
                      'name' => _x("Show Product Weight in Inventory Report", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_product_weight_in_inventory',
                    ),
                    'puiw_show_product_total_weight_in_inventory' => array(
                      'name' => _x("Show Product Total Weight in Inventory Report", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_product_total_weight_in_inventory',
                    ),
                    'puiw_show_product_dimensions_in_inventory' => array(
                      'name' => _x("Show Product Dimensions in Inventory Report", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_product_dimensions_in_inventory',
                    ),
                    'puiw_show_product_quantity_in_inventory' => array(
                      'name' => _x("Show Product Quantity in Inventory Report", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_product_quantity_in_inventory',
                    ),
                    'puiw_show_product_note_in_inventory' => array(
                      'name' => _x("Show Product Note in Inventory Report", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'checkbox',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'id'   => 'puiw_show_product_note_in_inventory',
                    ),
                    'puiw_show_order_note_inventory' => array(
                      'name' => _x("Show Order Notes in Inventory Report", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'select',
                      'id' => 'puiw_show_order_note_inventory',
                      'class' => 'wc-enhanced-select',
                      'default' => 'note_provided_by_customer',
                      'options' => array(
                        'hide_note_from_invoice' => _x("Hide notes", "wc-setting", "pepro-ultimate-invoice"),
                        'note_provided_by_customer' => _x("Customer note", "wc-setting", "pepro-ultimate-invoice"),
                        'note_provided_by_shop_manager' => _x("Manager note", "wc-setting", "pepro-ultimate-invoice"),
                        'note_provided_by_both' => _x("Both notes", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    'puiw_price_inventory_report' => array(
                      'name' => _x("Price in Inventory Report", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'select',
                      'id' => 'puiw_price_inventory_report',
                      'class' => 'wc-enhanced-select',
                      'default' => 'show_both_regular_and_sale_price',
                      'options' => array(
                        'hide_all_price'                    => _x("DO NOT SHOW Prices (HIDE)", "wc-setting", "pepro-ultimate-invoice"),
                        'show_only_regular_price'           => _x("Show Live Price (regular)", "wc-setting", "pepro-ultimate-invoice"),
                        'show_only_sale_price'              => _x("Show Live Price (sale)", "wc-setting", "pepro-ultimate-invoice"),
                        'show_both_regular_and_sale_price'  => _x("Show Live Price (sale & regular)", "wc-setting", "pepro-ultimate-invoice"),
                        'show_wc_price'                     => _x("Show Invoice Calculated price", "wc-setting", "pepro-ultimate-invoice"),

                      ),
                    ),
                    'puiw_report_end' => array(
                      'type' => 'sectionend',
                      'id'   => 'puiw_report_end'
                    ),
                  )
                );
                break;
              case 'theme':
                $lang_override_note = $this->language_template_note();
                $section_data = apply_filters(
                  "puiw_setting_section_{$current_section}",
                  array(
                    'puiw_theme_title' => array(
                      'name' => _x("Invoices Theming Setting", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'title',
                      'puiw_tabs' => array(
                        'invoice'    => _x("Invoice", "wc-setting-tab", "pepro-ultimate-invoice"),
                        'preinvoice' => _x("Pre-invoice", "wc-setting-tab", "pepro-ultimate-invoice"),
                        'text'       => _x("Title and numbering", "wc-setting-tab", "pepro-ultimate-invoice"),
                        'sign'       => _x("Signature and watermark", "wc-setting-tab", "pepro-ultimate-invoice"),
                        'css'        => _x("Custom CSS", "wc-setting-tab", "pepro-ultimate-invoice"),
                      ),
                      'id'   => 'puiw_theme_title'
                    ),
                    'puiw_theme_color' => array(
                      'puiw_tab' => 'invoice',
                      'name'    => _x("Theme Colors set", "wc-setting", "pepro-ultimate-invoice"),
                      'type'    => 'text',
                      'id'      => 'puiw_theme_color',
                      'class'   => 'wc-color-picker puiw_swatch_one',
                      'default' => 'teal',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Theme primary color", "wc-setting", "pepro-ultimate-invoice")),
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_theme_color2' => array(
                      'puiw_tab' => 'invoice',
                      'name'    => "",
                      'type'    => 'text',
                      'id'      => 'puiw_theme_color2',
                      'class'   => 'wc-color-picker puiw_swatch_two',
                      'default' => 'teal',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Theme secondary color", "wc-setting", "pepro-ultimate-invoice")),
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_theme_color3' => array(
                      'puiw_tab' => 'invoice',
                      'name'    => "",
                      'type'    => 'text',
                      'id'      => 'puiw_theme_color3',
                      'class'   => 'wc-color-picker puiw_swatch_three',
                      'default' => 'teal',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Theme tertiary color", "wc-setting", "pepro-ultimate-invoice")),
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_swatch' => array(
                      'puiw_tab' => 'invoice',
                      'name'    => "",
                      'type'    => 'select',
                      'class'   => 'swatch-select',
                      'desc_tip' => sprintf(_x("You can create pre-defined color schemes %s and use them to load your favourie colors set.", "wc-setting", "pepro-ultimate-invoice"), sprintf("<a href='%s' target='_blank'>%s</a>", admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=color"), __("here", "pepro-ultimate-invoice"))),
                      'id'      => 'puiw_swatch',
                      'custom_attributes' => array('swatches' => esc_js(get_option("puiw_color_swatches", "")),),
                      'options' => array(),
                    ),
                    'puiw_lang_template_note' => array(
                      'puiw_tab' => 'invoice',
                      'type'     => $lang_override_note ? 'puiw_note' : 'puiw_skip',
                      'id'       => 'puiw_lang_template_note',
                      'desc'     => $lang_override_note,
                    ),
                    'puiw_template' => array(
                      'puiw_tab' => 'invoice',
                      'name'    => _x("Default Theme", "wc-setting", "pepro-ultimate-invoice"),
                      'type'    => 'puiw_template',
                      'id'      => 'puiw_template',
                      'desc_tip' => _x("This theme will be used as default in Emails, HTML invoices, PDF invoices, Inventory Reports and Packing Slips.", "wc-setting", "pepro-ultimate-invoice"),
                      'default' => 'default-rtl',
                    ),
                    'puiw_template_direction' => array(
                      'puiw_tab' => 'invoice',
                      'name'     => _x("Page direction", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'radio',
                      'default'  => 'auto',
                      'options'  => array(
                        'auto' => _x("Normal, as the template is written", "wc-setting", "pepro-ultimate-invoice"),
                        'ltr'  => _x("Force left to right", "wc-setting", "pepro-ultimate-invoice"),
                        'rtl'  => _x("Force right to left", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                      'desc'     => _x("Applies to every template and output: web and PDF invoices, POS receipts, packing slips and inventory.", "wc-setting", "pepro-ultimate-invoice"),
                      'id'       => 'puiw_template_direction',
                    ),
                    'puiw_preinvoice_theme_color' => array(
                      'puiw_tab' => 'preinvoice',
                      'name'    => _x("Pre-Invoice Theme Colors set", "wc-setting", "pepro-ultimate-invoice"),
                      'type'    => 'text',
                      'id'      => 'puiw_preinvoice_theme_color',
                      'class'   => 'wc-color-picker puiw_preinvoice_swatch_one',
                      'default' => 'teal',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Theme primary color", "wc-setting", "pepro-ultimate-invoice")),
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_preinvoice_theme_color2' => array(
                      'puiw_tab' => 'preinvoice',
                      'name'    => "",
                      'type'    => 'text',
                      'id'      => 'puiw_preinvoice_theme_color2',
                      'class'   => 'wc-color-picker puiw_preinvoice_swatch_two',
                      'default' => 'teal',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Theme secondary color", "wc-setting", "pepro-ultimate-invoice")),
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_preinvoice_theme_color3' => array(
                      'puiw_tab' => 'preinvoice',
                      'name'    => "",
                      'type'    => 'text',
                      'id'      => 'puiw_preinvoice_theme_color3',
                      'class'   => 'wc-color-picker puiw_preinvoice_swatch_three',
                      'default' => 'teal',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Theme tertiary color", "wc-setting", "pepro-ultimate-invoice")),
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_preinvoice_swatch' => array(
                      'puiw_tab' => 'preinvoice',
                      'name'    => "",
                      'type'    => 'select',
                      'class'   => 'swatch-select',
                      'id'      => 'puiw_preinvoice_swatch',
                      'desc_tip' => sprintf(_x("You can create pre-defined color schemes %s and use them to load your favourie colors set.", "wc-setting", "pepro-ultimate-invoice"), sprintf("<a href='%s' target='_blank'>%s</a>", admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=color"), __("here", "pepro-ultimate-invoice"))),
                      'custom_attributes' => array('swatches' => esc_js(get_option("puiw_color_swatches", "")),),
                      'options' => array(),
                    ),
                    'puiw_preinvoice_template' => array(
                      'puiw_tab' => 'preinvoice',
                      'name'    => _x("Pre-Invoice Theme", "wc-setting", "pepro-ultimate-invoice"),
                      'type'    => 'puiw_template',
                      'id'      => 'puiw_preinvoice_template',
                      'desc_tip' => _x("This theme will be used for Pre-Invoice orders in Emails, HTML invoices, PDF invoices, Inventory Reports and Packing Slips.", "wc-setting", "pepro-ultimate-invoice"),
                      'default' => 'default-pre-invoice',
                    ),
                    'puiw_invoice_title' => array(
                      'puiw_tab' => 'text',
                      'name'              => _x("Invoice Title", "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Invoice Title", "wc-setting", "pepro-ultimate-invoice")),
                      'type'              => 'text',
                      'id'                => 'puiw_invoice_title',
                      'default'           => _x("Invoice %s", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'              => _x("You can use %s instead of Invoice Number", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_font_size' => array(
                      'puiw_tab' => 'text',
                      'name'              => _x("Font size (px)", "wc-setting", "pepro-ultimate-invoice"),
                      'type'              => 'number',
                      'id'                => 'puiw_font_size',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Font size (px)", "wc-setting", "pepro-ultimate-invoice")),
                      'default'           => '12',
                      'custom_attributes' => array('dir'  => 'ltr', 'step' => '1', 'min'  => '8', 'max'  => '99',)
                    ),
                    'puiw_invoice_prefix' => array(
                      'puiw_tab' => 'text',
                      'name'              => _x("Invoice Prefix", "wc-setting", "pepro-ultimate-invoice"),
                      'type'              => 'text',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Invoice Prefix", "wc-setting", "pepro-ultimate-invoice")),
                      'id'                => 'puiw_invoice_prefix',
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_invoice_suffix' => array(
                      'puiw_tab' => 'text',
                      'name'              => _x("Invoice Suffix", "wc-setting", "pepro-ultimate-invoice"),
                      'type'              => 'text',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Invoice Suffix", "wc-setting", "pepro-ultimate-invoice")),
                      'id'                => 'puiw_invoice_suffix',
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_invoice_start' => array(
                      'puiw_tab' => 'text',
                      'name'              => _x("Invoice Start Number", "wc-setting", "pepro-ultimate-invoice"),
                      'type'              => 'number',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Invoice Start Number", "wc-setting", "pepro-ultimate-invoice")),
                      'desc_tip'          => _x("Invoices number will start from this number", "wc-setting", "pepro-ultimate-invoice"),
                      'id'                => 'puiw_invoice_start',
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_invoices_footer' => array(
                      'puiw_tab' => 'text',
                      'name'              => _x("Invoices footer", "wc-setting", "pepro-ultimate-invoice"),
                      'type'              => 'textarea',
                      'id'                => 'puiw_invoices_footer',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Invoices footer", "wc-setting", "pepro-ultimate-invoice")),
                      'desc_tip'          => _x("HTML tags are allowed. DO NOT ADD CSS here!", "wc-setting", "pepro-ultimate-invoice"),
                      'custom_attributes' => array('rows' => '5',)
                    ),
                    'puiw_show_signatures' => array(
                      'puiw_tab' => 'sign',
                      'name'     => _x("Show Signatures", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_signatures',
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_signature' => array(
                      'puiw_tab' => 'sign',
                      'name'              => _x("Add Store Signature and Stamp", "wc-setting", "pepro-ultimate-invoice"),
                      'type'              => 'puiw_image',
                      'desc'              => '',
                      'desc_tip'          => _x("Add your scanned signature or stamp to be added to invoices footer. (preferred size: 150x300 px)", "wc-setting", "pepro-ultimate-invoice"),
                      'id'                => 'puiw_signature',
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_watermark' => array(
                      'puiw_tab' => 'sign',
                      'name'              => _x("Add Watermark to Invoices", "wc-setting", "pepro-ultimate-invoice"),
                      'type'              => 'puiw_image',
                      'desc'              => '',
                      'desc_tip'          => _x("Add your watermark image to invoices (can use png too)", "wc-setting", "pepro-ultimate-invoice"),
                      'id'                => 'puiw_watermark',
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_watermark_opacity' => array(
                      'puiw_tab' => 'sign',
                      'name'              => _x("Watermark opacity", "wc-setting", "pepro-ultimate-invoice"),
                      'type'              => 'number',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Watermark opacity", "wc-setting", "pepro-ultimate-invoice")),
                      'desc_tip'          => _x("Watermark opacity scale <br>(0: transparent | 100: opaque)", "wc-setting", "pepro-ultimate-invoice"),
                      'id'                => 'puiw_watermark_opacity',
                      'default'           => '80',
                      'custom_attributes' => array(
                        'dir'  => 'ltr',
                        'step' => '1',
                        'min'  => '1',
                        'max'  => '100',
                      )
                    ),
                    'puiw_watermark_blend' => array(
                      'puiw_tab' => 'sign',
                      'id'                => 'puiw_watermark_blend',
                      'name'              => _x("PDF Watermark blend mode", "wc-setting", "pepro-ultimate-invoice"),
                      'type'              => "select",
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Watermark opacity", "wc-setting", "pepro-ultimate-invoice")),
                      'desc_tip'          => _x("Specify the blend mode for overlying watermark images", "wc-setting", "pepro-ultimate-invoice"),
                      'default'           => "Normal",
                      "options" => array(
                        "Normal"     => _x("Normal", "wc-setting", "pepro-ultimate-invoice"),
                        "Multiply"   => _x("Multiply", "wc-setting", "pepro-ultimate-invoice"),
                        "Screen"     => _x("Screen", "wc-setting", "pepro-ultimate-invoice"),
                        "Overlay"    => _x("Overlay", "wc-setting", "pepro-ultimate-invoice"),
                        "Darken"     => _x("Darken", "wc-setting", "pepro-ultimate-invoice"),
                        "Lighten"    => _x("Lighten", "wc-setting", "pepro-ultimate-invoice"),
                        "ColorDodge" => _x("ColorDodge", "wc-setting", "pepro-ultimate-invoice"),
                        "ColorBurn"  => _x("ColorBurn", "wc-setting", "pepro-ultimate-invoice"),
                        "HardLight"  => _x("HardLight", "wc-setting", "pepro-ultimate-invoice"),
                        "SoftLight"  => _x("SoftLight", "wc-setting", "pepro-ultimate-invoice"),
                        "Difference" => _x("Difference", "wc-setting", "pepro-ultimate-invoice"),
                        "Exclusion"  => _x("Exclusion", "wc-setting", "pepro-ultimate-invoice"),
                      )
                    ),
                    'puiw_custom_css_style' => array(
                      'puiw_tab'          => 'css',
                      'puiw_code'         => 'css',
                      'name'              => _x("Web invoice", "wc-setting-css", "pepro-ultimate-invoice"),
                      'desc'              => _x("The invoice page customers open, and the copy in emails.", "wc-setting-css", "pepro-ultimate-invoice"),
                      'type'              => 'textarea',
                      'id'                => 'puiw_custom_css_style',
                      'custom_attributes' => array('dir' => 'ltr', 'rows' => '14', 'spellcheck' => 'false'),
                    ),
                    'puiw_pdf_css_style' => array(
                      'puiw_tab'          => 'css',
                      'puiw_code'         => 'css',
                      'name'              => _x("PDF invoice", "wc-setting-css", "pepro-ultimate-invoice"),
                      'desc'              => _x("The PDF invoice, its download and the copy attached to emails.", "wc-setting-css", "pepro-ultimate-invoice"),
                      'type'              => 'textarea',
                      'id'                => 'puiw_pdf_css_style',
                      'custom_attributes' => array('dir' => 'ltr', 'rows' => '14', 'spellcheck' => 'false'),
                    ),
                    'puiw_pos_css_style' => array(
                      'puiw_tab'          => 'css',
                      'puiw_code'         => 'css',
                      'name'              => _x("POS receipt", "wc-setting-css", "pepro-ultimate-invoice"),
                      'desc'              => _x("The roll receipt from the POS button.", "wc-setting-css", "pepro-ultimate-invoice"),
                      'type'              => 'textarea',
                      'id'                => 'puiw_pos_css_style',
                      'custom_attributes' => array('dir' => 'ltr', 'rows' => '14', 'spellcheck' => 'false'),
                    ),
                    'puiw_slips_css_style' => array(
                      'puiw_tab'          => 'css',
                      'puiw_code'         => 'css',
                      'name'              => _x("Packing slips", "wc-setting-css", "pepro-ultimate-invoice"),
                      'desc'              => _x("The printable sender and recipient labels.", "wc-setting-css", "pepro-ultimate-invoice"),
                      'type'              => 'textarea',
                      'id'                => 'puiw_slips_css_style',
                      'custom_attributes' => array('dir' => 'ltr', 'rows' => '14', 'spellcheck' => 'false'),
                    ),
                    'puiw_slips_pdf_css_style' => array(
                      'puiw_tab'          => 'css',
                      'puiw_code'         => 'css',
                      'name'              => _x("Packing slips PDF", "wc-setting-css", "pepro-ultimate-invoice"),
                      'desc'              => _x("The labels as a PDF.", "wc-setting-css", "pepro-ultimate-invoice"),
                      'type'              => 'textarea',
                      'id'                => 'puiw_slips_pdf_css_style',
                      'custom_attributes' => array('dir' => 'ltr', 'rows' => '14', 'spellcheck' => 'false'),
                    ),
                    'puiw_inventory_css_style' => array(
                      'puiw_tab'          => 'css',
                      'puiw_code'         => 'css',
                      'name'              => _x("Inventory report", "wc-setting-css", "pepro-ultimate-invoice"),
                      'desc'              => _x("The picking list for the warehouse.", "wc-setting-css", "pepro-ultimate-invoice"),
                      'type'              => 'textarea',
                      'id'                => 'puiw_inventory_css_style',
                      'custom_attributes' => array('dir' => 'ltr', 'rows' => '14', 'spellcheck' => 'false'),
                    ),
                    'puiw_theme_end' => array(
                      'type' => 'sectionend',
                      'id'   => 'puiw_theme_end'
                    ),
                  )
                );
                break;
              case 'misc':
                $table_date = '<div class="puiw-dialog" id="dateformathelp" hidden>
  <div class="dateformathelp">
  <br>
    <table class="wp-list-table widefat fixed striped table-view-list pages">
      <thead>
        <tr>
          <th colspan=3>Character</th>
          <th colspan=3>Meaning</th>
          <th colspan=3>Example</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <th class="head" colspan=9><strong>Common date format characters and their values</strong></th>
        </tr>
        <tr>
          <td><code class="inline">d</code></td>
          <td>day of the month with leading zeros</td>
          <td>03 or 17</td>
        </tr>
        <tr>
          <td><code class="inline">j</code></td>
          <td>day of the month without leading zeros</td>
          <td>3 or 17</td>
        </tr>
        <tr>
          <td><code class="inline">D</code></td>
          <td>day of the week as a three-letter abbreviation</td>
          <td>Mon</td>
        </tr>
        <tr>
          <td><code class="inline">l</code></td>
          <td>full day of the week</td>
          <td>Monday</td>
        </tr>
        <tr>
          <td><code class="inline">m</code></td>
          <td>month as a number with leading zeros</td>
          <td>09 or 12</td>
        </tr>
        <tr>
          <td><code class="inline">n</code></td>
          <td>month as a number without leading zeros</td>
          <td>9 or 12</td>
        </tr>
        <tr>
          <td><code class="inline">M</code></td>
          <td>month as a three-letter abbreviation</td>
          <td>Sep</td>
        </tr>
        <tr>
          <td><code class="inline">F</code></td>
          <td>full month</td>
          <td>September</td>
        </tr>
        <tr>
          <td><code class="inline">y</code></td>
          <td>two-digit year</td>
          <td>18</td>
        </tr>
        <tr>
          <td><code class="inline">Y</code></td>
          <td>full year</td>
          <td>2018</td>
        </tr>
        <tr>
          <th class="head" colspan=9><strong>Common time format characters and their values</strong></th>
        </tr>
        <tr>
          <td><code class="inline">g</code></td>
          <td>hours in 12-hour format without leading zeros</td>
          <td>1 or 12</td>
        </tr>
        <tr>
          <td><code class="inline">h</code></td>
          <td>hours in 12-hour format with leading zeros</td>
          <td>01 or 12</td>
        </tr>
        <tr>
          <td><code class="inline">G</code></td>
          <td>hours in 24-hour format without leading zeros</td>
          <td>1 or 13</td>
        </tr>
        <tr>
          <td><code class="inline">H</code></td>
          <td>hours in 24-hour format with leading zeros</td>
          <td>01 or 13</td>
        </tr>
        <tr>
          <td><code class="inline">a</code></td>
          <td>am/pm in lowercase</td>
          <td>am</td>
        </tr>
        <tr>
          <td><code class="inline">A</code></td>
          <td>am/pm in uppercase</td>
          <td>AM</td>
        </tr>
        <tr>
          <td><code class="inline">i</code></td>
          <td>minutes with leading zeros</td>
          <td>09 or 15</td>
        </tr>
        <tr>
          <td><code class="inline">s</code></td>
          <td>seconds with leading zeros</td>
          <td>05 or 30<br>
          </td>
        </tr>
      </tbody>
    </table>
    <br>
    <strong>Here are some examples of date format with the result output.</strong>
    <ul>
      <li>
        <code>F j, Y g:i a</code>
        – November 6, 2010 12:50 am
      </li>
      <li>
        <code>F j, Y</code>
        – November 6, 2010
      </li>
      <li>
        <code>F, Y</code>
        – November, 2010
      </li>
      <li>
        <code>g:i a</code>
        – 12:50 am
      </li>
      <li>
        <code>g:i:s a</code>
        – 12:50:48 am
      </li>
      <li>
        <code>l, F jS, Y</code>
        – Saturday, November 6th, 2010
      </li>
      <li>
        <code>M j, Y @ G:i</code>
        – Nov 6, 2010 @ 0:50
      </li>
      <li>
        <code>Y/m/d \a\t g:i A</code>
        – 2010/11/06 at 12:50 AM
      </li>
      <li>
        <code>Y/m/d \a\t g:ia</code>
        – 2010/11/06 at 12:50am
      </li>
      <li>
        <code>Y/m/d g:i:s A</code>
        – 2010/11/06 12:50:48 AM
      </li>
      <li>
        <code>Y/m/d</code>
        – 2010/11/06
      </li>
    </ul>
    <p>
      <a class="button button-primary" href="https://code.tutsplus.com/tutorials/working-with-date-and-time-in-php--cms-31768" target="_blank">Learn more at code.tutsplus.com ...</a>
      <a class="button button-primary" href="https://wordpress.org/support/article/formatting-date-and-time/" target="_blank">Read more at wordpress.org ...</a>
    </p>
  </div>
</div>';
                $section_data = apply_filters(
                  "puiw_setting_section_{$current_section}",
                  array(
                    'puiw_misc_title' => array(
                      'name' => _x("Miscellaneous Setting", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'title',
                      'id'   => 'puiw_misc_title'
                    ),
                    'puiw_disable_wc_dashboard' => array(
                      'name'     => _x("Disable WC Dashboard", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_disable_wc_dashboard',
                      'desc' => _x("Check to disable new Dashboard introduced in Woocommerce 4.0 or leave unchecked to use it", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("Refresh page after setting saved.", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_show_news' => array(
                      'name'     => _x("News and offers on the dashboard", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_news',
                      'default'  => 'yes',
                      'desc'     => _x("Show posts and offers from peprodev.com on the plugin dashboard", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("The panel asks peprodev.com for a small public file once a day and sends nothing about your shop. Untick to stop the request entirely.", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_date_format' => array(
                      'name'              => _x("Date parsing template", "wc-setting", "pepro-ultimate-invoice"),
                      'type'              => 'text',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Date parsing template", "wc-setting", "pepro-ultimate-invoice")),
                      'std'               => 'Y/m/d H:i', // WooCommerce < 2.0
                      'default'           => 'Y/m/d H:i', // WooCommerce >= 2.0
                      'desc'              => "<button type='button' class='button-link puiw-dialog-open' data-dialog='dateformathelp' data-title='" . esc_attr(_x("Some of the most commonly used date/time format characters and their values", "wc-setting", "pepro-ultimate-invoice")) . "'>" . _x("View date format string examples", "wc-setting", "pepro-ultimate-invoice") . "</button>" . $table_date,
                      'puiw_chips'        => array(
                        "Y/m/d H:i" => date_i18n("Y/m/d H:i"),
                        "Y-m-d"     => date_i18n("Y-m-d"),
                        "d/m/Y"     => date_i18n("d/m/Y"),
                        "j F Y"     => date_i18n("j F Y"),
                        "l, j F Y"  => date_i18n("l, j F Y"),
                        "H:i"       => date_i18n("H:i"),
                      ),
                      'id'                => 'puiw_date_format',
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_date_shamsi' => array(
                      'name'     => _x("Convert Date to Jalali/Shamsi", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_date_shamsi',
                      'desc' => _x("Check to convert dates into Jalali/Shamsi format or leave unchecked to use default setting", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_show_shipped_date' => array(
                      'name'     => _x("Show Shipped Date on Order details", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_shipped_date',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_show_shipping_serial' => array(
                      'name'     => _x("Show Shipping Track Serial on Order details", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_shipping_serial',
                      'desc' => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_invoice_language' => array(
                      'name'     => _x("Invoice language", "wc-setting", "pepro-ultimate-invoice"),
                      'default'  => 'auto',
                      'type'     => 'select',
                      'class'    => 'wc-enhanced-select',
                      'options'  => $this->invoice_language_options(),
                      'desc'     => _x("Every invoice, packing slip, receipt and email printout uses this language. An invoice printed in a left to right language also gets English digits and Gregorian dates.", "wc-setting", "pepro-ultimate-invoice"),
                      'id'       => 'puiw_invoice_language',
                    ),
                    'puiw_force_persian_numbers' => array(
                      'name'     => _x("Force Persian/Faris Numbers?", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'radio',
                      'default'  => 'no',
                      'options'  => array(
                        'no'     => _x("English digits", "wc-setting", "pepro-ultimate-invoice"),
                        'arabic' => _x("Arabic digits", "wc-setting", "pepro-ultimate-invoice"),
                        'farsi'  => _x("Persian digits", "wc-setting", "pepro-ultimate-invoice"),
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
                $section_data = apply_filters(
                  "puiw_setting_section_{$current_section}",
                  array(
                    'puiw_integ_title' => array(
                      'id'   => 'puiw_integ_title',
                      'name' => _x("Integration Setting", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'title',
                      'desc'  => sprintf(__('Integration with %s', "pepro-ultimate-invoice"), "<a href='https://wordpress.org/plugins/woo-product-bundle/' target='_blank'>WPC Product Bundles</a>"),
                    ),
                    'puiw_woosb_show_bundles' => array(
                      'id'       => 'puiw_woosb_show_bundles',
                      'name'     => _x("Show bundles", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'default'  => 'no',
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_woosb_show_bundles_subtitle' => array(
                      'id'       => 'puiw_woosb_show_bundles_subtitle',
                      'name'     => _x("Show bundles subtitle", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'default'  => 'no',
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_woosb_bundles_subtitle_prefix' => array(
                      'id'       => 'puiw_woosb_bundles_subtitle_prefix',
                      'name'     => _x("Bundles products subtitle prefix", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'text',
                      'default'  => _x("Bundled products:", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_woosb_show_bundled_products' => array(
                      'id'       => 'puiw_woosb_show_bundled_products',
                      'name'     => _x("Show bundled products", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'default'  => 'no',
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_woosb_show_bundled_subtitle' => array(
                      'id'       => 'puiw_woosb_show_bundled_subtitle',
                      'name'     => _x("Show bundled products subtitle", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'default'  => 'no',
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_woosb_bundled_subtitle_prefix' => array(
                      'id'       => 'puiw_woosb_bundled_subtitle_prefix',
                      'name'     => _x("Bundled products subtitle prefix", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'text',
                      'default'  => _x("Bundled in:", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_woosb_show_bundled_hierarchy' => array(
                      'id'       => 'puiw_woosb_show_bundled_hierarchy',
                      'name'     => _x("Show bundled products hierarchy", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'default'  => 'no',
                      'desc'     => _x("Check to show hierarchy or leave unchecked to show normally", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_integ_end' => array(
                      'type' => 'sectionend',
                      'id'   => 'puiw_integ_end'
                    ),
                  )
                );
                break;
              case 'barcode':
                $section_data = apply_filters(
                  "puiw_setting_section_{$current_section}",
                  array(
                    'puiw_barcode_title' => array(
                      'name' => _x("Invoices Barcode Setting", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'title',
                      'id'   => 'puiw_barcode_title'
                    ),
                    'puiw_show_barcode_id' => array(
                      'name'     => _x("Show Invoices Number Barcode", "wc-setting", "pepro-ultimate-invoice"),
                      'desc' => _x("Check to add or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_barcode_id',
                    ),
                    'puiw_show_shipping_ref_id' => array(
                      'name'     => _x("Show Shipping Track Serial Barcode", "wc-setting", "pepro-ultimate-invoice"),
                      'desc' => _x("Check to add or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      // 'desc_tip' => _x("Shop manager enters Track ID manually", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_show_shipping_ref_id',
                    ),
                    'puiw_show_qr_code_id' => array(
                      'name'     => _x("Invoice QR Code", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("Links to the online invoice", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'select',
                      'class'    => 'wc-enhanced-select',
                      'id'       => 'puiw_show_qr_code_id',
                      'options'  => array(
                        'no'   => _x("Hide", "wc-setting", "pepro-ultimate-invoice"),
                        'qr'   => _x("QR instead of barcode", "wc-setting", "pepro-ultimate-invoice"),
                        'both' => _x("Barcode and QR", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    'puiw_postal_stickey_label_for_store' => array(
                      'name'     => _x("Show Barcode in Sender Shipping slip", "wc-setting", "pepro-ultimate-invoice"),
                      'desc' => _x("Check to add or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_postal_stickey_label_for_store',
                    ),
                    'puiw_postal_stickey_label_for_customer' => array(
                      'name'     => _x("Show Barcode in Recipient Shipping slip", "wc-setting", "pepro-ultimate-invoice"),
                      'desc' => _x("Check to add or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
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
                $section_data = apply_filters(
                  "puiw_setting_section_{$current_section}",
                  array(
                    'puiw_email_title' => array(
                      'name' => _x("Invoices Email Setting", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'title',
                      'id'   => 'puiw_automation_title'
                    ),
                    'puiw_email_subject' => array(
                      'name'              => _x("Email Subject", "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Email Subject", "wc-setting", "pepro-ultimate-invoice")),
                      'type'              => 'text',
                      'default'           => _x("Order #%s invoice on ", "wc-setting", "pepro-ultimate-invoice") . get_bloginfo('name', 'display'),
                      'id'                => 'puiw_email_subject',
                      'desc'              => _x("You can use %s instead of Invoice Number", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip'          => true,
                    ),
                    'puiw_email_from_name' => array(
                      'type'              => 'text',
                      'id'                => 'puiw_email_from_name',
                      'name'              => _x('"From" name', "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x('"From" name', "wc-setting", "pepro-ultimate-invoice")),
                      'desc'              => __('How the sender name appears in outgoing emails', "pepro-ultimate-invoice"),
                      'default'           => esc_attr(get_bloginfo('name', 'display')),
                      'desc_tip'          => true,
                    ),
                    'puiw_email_from_address' => array(
                      'type'              => 'text',
                      'id'                => 'puiw_email_from_address',
                      'name'              => _x('"From" address', "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x('"From" address', "wc-setting", "pepro-ultimate-invoice")),
                      'desc'              => __('How the sender email appears in outgoing emails', "pepro-ultimate-invoice"),
                      'default'           => get_option('admin_email'),
                      'desc_tip'          => true,
                    ),
                    'puiw_send_invoices_via_email' => array(
                      'name'     => _x("Send Invoices via Email to Customer", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'radio',
                      'id'       => 'puiw_send_invoices_via_email',
                      'default'  => 'manual',
                      'options'  => array(
                        'manual'     => _x("Send Manually", "wc-setting", "pepro-ultimate-invoice"),
                        'automatic'  => _x("Send Automatically", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                      'desc' => _x("Choose whether send HTML invoices via Email to customers automatically or manually", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => true,
                    ),
                    'puiw_send_invoices_via_email_opt' => array(
                      'name'     => _x("Order Statuses for Automatic Sending to Customers", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'multiselect',
                      'class'    => 'chosen_select',
                      'id'       => 'puiw_send_invoices_via_email_opt',
                      'options'  => $this->wc_get_order_statuses(),
                      'default'  => "wc-completed",
                      'desc_tip' => _x("Send HTML Invoices via Email to customer when order status is among these selection. <br>Multiple selection allowed", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_send_invoices_via_email_admin' => array(
                      'name'     => _x("Send Invoices via Email to Shop Managers", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'radio',
                      'id'       => 'puiw_send_invoices_via_email_admin',
                      'default'  => 'manual',
                      'options'  => array(
                        'manual'     => _x("Send Manually", "wc-setting", "pepro-ultimate-invoice"),
                        'automatic'  => _x("Send Automatically", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                      'desc' => _x("Choose whether send HTML invoices via Email to customers automatically or manually", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => true,
                    ),
                    'puiw_send_invoices_via_email_opt_admin' => array(
                      'name'     => _x("Order Statuses for Automatic Sending to Managers", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'multiselect',
                      'class'    => 'chosen_select',
                      'id'       => 'puiw_send_invoices_via_email_opt_admin',
                      'options'  => $this->wc_get_order_statuses(),
                      'default'  => "wc-completed",
                      'desc_tip' => _x("Send HTML Invoices via Email to Shop managers when order status is among these selection. <br>Multiple selection allowed", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_send_invoices_via_email_shpmngrs' => array(
                      'name'     => _x("Shop Managers who will receive invoice mail", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'multiselect',
                      'class'    => 'chosen_select',
                      'id'       => 'puiw_send_invoices_via_email_shpmngrs',
                      'options'  => $this->get_wc_managers(),
                      'desc_tip' => _x("Send HTML Invoices via Email to Shop managers either manually through WC Orders screen or automatically. <br>Multiple selection allowed", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_attach_pdf_invoices_to_mail' => array(
                      'name'     => _x("Attach PDF Invoices to mail", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_attach_pdf_invoices_to_mail',
                      'desc' => _x("Check to attach a PDF Invoice to WooCommerce Mails or leave unchecked to not", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_email_end' => array(
                      'type' => 'sectionend',
                      'id'   => 'puiw_automation_end'
                    ),
                  )
                );
                break;
              case 'privacy':
                $section_data = apply_filters(
                  "puiw_setting_section_{$current_section}",
                  array(
                    'puiw_privacy_title' => array(
                      'name' => _x("Privacy Setting", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'title',
                      'id'   => 'puiw_automation_title'
                    ),
                    'puiw_allow_guest_users_view_invoices' => array(
                      'name'     => _x("Allow Guest Users view invoices", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'default'  => 'no',
                      'id'       => 'puiw_allow_guest_users_view_invoices',
                      'desc'     => _x("Check to allow or leave unchecked to disallow", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("Allow logged out visitors be able to see invoices by having its URL", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_allow_pdf_guest' => array(
                      'name'     => _x("Invoice Output for Guests", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'select',
                      'class'    => 'wc-enhanced-select',
                      'id'       => 'puiw_allow_pdf_guest',
                      'default'  => 'html',
                      'options' => array(
                        'html' => _x("HTML only", "wc-setting", "pepro-ultimate-invoice"),
                        'pdf' => _x("PDF only", "wc-setting", "pepro-ultimate-invoice"),
                        'both' => _x("HTML and PDF", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    'puiw_allow_users_have_invoices' => array(
                      'name'     => _x("Allow Customer/Users view invoices", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'default'  => 'yes',
                      'id'       => 'puiw_allow_users_have_invoices',
                      'desc'     => _x("Check to allow or leave unchecked to disallow", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("Allow logged in visitors be able to see their invoices by having its URL", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_allow_pdf_customer' => array(
                      'name'     => _x("Invoice Output for Customers", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'select',
                      'class'    => 'wc-enhanced-select',
                      'id'       => 'puiw_allow_pdf_customer',
                      'default'  => 'both',
                      'options' => array(
                        'html' => _x("HTML only", "wc-setting", "pepro-ultimate-invoice"),
                        'pdf' => _x("PDF only", "wc-setting", "pepro-ultimate-invoice"),
                        'both' => _x("HTML and PDF", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    'puiw_allow_users_use_invoices' => array(
                      'name'     => _x("Show Invoices option in orders list", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'default'  => 'yes',
                      'id'       => 'puiw_allow_users_use_invoices',
                      'desc'     => _x("Check to allow or leave unchecked to disallow", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("Add 'Get Invoice' button to customer order list so they could have invoice too", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_allow_users_use_invoices_criteria' => array(
                      'name'     => _x("Allowed Orders statuses", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'multiselect',
                      'class'    => 'chosen_select',
                      'default'  => "wc-completed",
                      'id'       => 'puiw_allow_users_use_invoices_criteria',
                      'options'  => $this->wc_get_order_statuses(),
                      'desc_tip' => _x("In which Order Status Customers can use 'Get Invoice' feature?<br>Multiple selection allowed", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_allowed_roles' => array(
                      'name'     => _x("Roles allowed to view and print invoices", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("Administrators and shop managers always have access. Customers always see their own orders.", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'multiselect',
                      'class'    => 'wc-enhanced-select',
                      'id'       => 'puiw_allowed_roles',
                      'options'  => $this->get_site_roles(),
                    ),
                    'puiw_privacy_end' => array(
                      'type' => 'sectionend',
                      'id'   => 'puiw_automation_end'
                    ),
                  )
                );
                break;
              case 'extras':
                $section_data = apply_filters(
                  "puiw_setting_section_{$current_section}",
                  array(
                    'puiw_extras_title' => array(
                      'name' => _x("Extras Setting", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'title',
                      'id'   => 'puiw_automation_title'
                    ),
                    'puiw_quick_shop' => array(
                      'name'     => _x("Enable Quick Shop Feature", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_quick_shop',
                      'desc'     => _x("Check to Enable 'Quick Shop' Feature and integrate it with WPBakery Page Builder (if installed)", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("This will let users filter and shop all your products in one page. Use <code>[puiw_quick_shop]</code> shortcode or WPBakery Page Builder widget to display it", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_allow_preorder_invoice' => array(
                      'name'     => _x("Pre-order Invoices", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_allow_preorder_invoice',
                      'desc' => _x("Check to allow users get pre-order invoices or leave unchecked to disallow", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("PRE-ORDER INVOICE means user does not place an order, but rather select products and get an invoice to be personally to present later in shopping, directly from your physical store.", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_allow_preorder_emptycart' => array(
                      'name'     => _x("Clear Cart on Quick Buy", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_allow_preorder_emptycart',
                      'desc'     => _x("Check to clear cart on pre-order invoice creation or leave unchecked to keep previous cart", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_preorder_shopmngr_extra_note' => array(
                      'name'              => _x("Extra Notes on Pre-order by Shop Manager", "wc-setting", "pepro-ultimate-invoice"),
                      'type'              => 'textarea',
                      'id'                => 'puiw_preorder_shopmngr_extra_note',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Extra Notes on Pre-order by Shop Manager", "wc-setting", "pepro-ultimate-invoice")),
                      'desc_tip'          => _x("You can add custom note here to be added into pre-orders", "wc-setting", "pepro-ultimate-invoice"),
                      'custom_attributes' => array('rows' => '5',)
                    ),
                    'puiw_preorder_customer_extra_note' => array(
                      'name'              => _x("Extra Notes on Pre-order on behalf of Customer", "wc-setting", "pepro-ultimate-invoice"),
                      'type'              => 'textarea',
                      'id'                => 'puiw_preorder_customer_extra_note',
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Extra Notes on Pre-order on behalf of Customer", "wc-setting", "pepro-ultimate-invoice")),
                      'desc_tip'          => _x("You can add custom note here to be added into pre-orders on behalf of customer", "wc-setting", "pepro-ultimate-invoice"),
                      'custom_attributes' => array('rows' => '5',)
                    ),
                    'puiw_thankyou_display' => array(
                      'name'     => _x("Order received page", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("Show the invoice itself right after payment instead of only a download button", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'select',
                      'class'    => 'wc-enhanced-select',
                      'id'       => 'puiw_thankyou_display',
                      'options'  => array(
                        'buttons' => _x("Buttons only", "wc-setting", "pepro-ultimate-invoice"),
                        'inline'  => _x("Invoice in page", "wc-setting", "pepro-ultimate-invoice"),
                        'both'    => _x("Buttons and invoice", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    'puiw_orders_column_more' => array(
                      'name'     => _x("Orders list options menu", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_orders_column_more',
                      'default'  => 'no',
                      'desc'     => _x("Show a fourth button in the orders list that opens the full options menu", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("Unchecked, the Ultimate Invoice column only shows the HTML, PDF and POS invoice links, each opening in a new tab", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_extras_end' => array(
                      'type' => 'sectionend',
                      'id'   => 'puiw_automation_end'
                    ),
                  )
                );
                break;
              case 'pdf':
                $font_names = array(
                        "dejavu"      => _x("DejaVuSans (Standard)", "wc-setting", "pepro-ultimate-invoice"),
                        "danaen"      => _x("Dana (Standard)", "wc-setting", "pepro-ultimate-invoice"),
                        "iransans"    => _x("IRANSans (Standard)", "wc-setting", "pepro-ultimate-invoice"),
                        "iranyekanen" => _x("IRANYekan (Standard)", "wc-setting", "pepro-ultimate-invoice"),
                        "danafa"      => _x("Dana (Farsi-Digits Support)", "wc-setting", "pepro-ultimate-invoice"),
                        "iransansfa"  => _x("IRANSans (Farsi-Digits Support)", "wc-setting", "pepro-ultimate-invoice"),
                        "iranyekanfa" => _x("IRANYekan (Farsi-Digits Support)", "wc-setting", "pepro-ultimate-invoice"),
                );
                require_once PEPROULTIMATEINVOICE_DIR . '/include/vendor/autoload.php';
                $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
                $fontData = $defaultFontConfig['fontdata'] ?? array();
                if ($fontData && !empty($fontData)) {
                  foreach ($fontData as $item => $val) {
                    if (!isset($font_names[$item])) {
                      // $font_names[$item] = $val[array_key_first($val)] . " ( " . _x("Built-in", "wc-setting", "pepro-ultimate-invoice") . (isset($val["useKashida"])?" - Native Arabic Support":"") . " )";
                    }
                  }
                }
                $section_data = apply_filters(
                  "puiw_setting_section_{$current_section}",
                  array(
                    'puiw_pdf_title' => array(
                      'name' => _x("PDF Invoices Setting", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'title',
                      'id'   => 'puiw_automation_title'
                    ),
                    'puiw_pdf_font' => array(
                      'name'    => _x("PDF Font", "wc-setting", "pepro-ultimate-invoice"),
                      'type'    => 'select',
                      // 'class'   => 'wc-enhanced-select',
                      'default' => 'iranyekanfa',
                      'options' => $font_names,
                      'id'      => 'puiw_pdf_font',
                    ),
                    'puiw_pdf_size' => array(
                      'name'     => _x("PDF-Invoice Page size", "wc-setting", "pepro-ultimate-invoice"),
                      'id'       => 'puiw_pdf_size',
                      'type'     => 'select',
                      'class'    => 'wc-enhanced-select',
                      'default'  => 'A4',
                      'options' =>
                      array(
                        'LABEL_80x100'  => _x("Label: 8 x 10 cm", "wc-setting", "pepro-ultimate-invoice"),
                        'LABEL_100x100' => _x("Label: 10 x 10 cm", "wc-setting", "pepro-ultimate-invoice"),
                        'LABEL_100x150' => _x("Label: 10 x 15 cm", "wc-setting", "pepro-ultimate-invoice"),
                        'A4'        => _x("A Series: A4", "wc-setting", "pepro-ultimate-invoice"),
                        'A5'        => _x("A Series: A5 (set bottom padding 14)", "wc-setting", "pepro-ultimate-invoice"),
                        'Letter'    => _x("Letter", "wc-setting", "pepro-ultimate-invoice"),
                        'Legal'     => _x("Legal", "wc-setting", "pepro-ultimate-invoice"),
                        'Executive' => _x("Executive", "wc-setting", "pepro-ultimate-invoice"),
                        'Folio'     => _x("Folio", "wc-setting", "pepro-ultimate-invoice"),
                        'Demy'      => _x("Demy", "wc-setting", "pepro-ultimate-invoice"),
                        'Royal'     => _x("Royal", "wc-setting", "pepro-ultimate-invoice"),
                        'A0'        => _x("A Series: A0", "wc-setting", "pepro-ultimate-invoice"),
                        'A1'        => _x("A Series: A1", "wc-setting", "pepro-ultimate-invoice"),
                        'A2'        => _x("A Series: A2", "wc-setting", "pepro-ultimate-invoice"),
                        'A3'        => _x("A Series: A3", "wc-setting", "pepro-ultimate-invoice"),
                        'A6'        => _x("A Series: A6", "wc-setting", "pepro-ultimate-invoice"),
                        'A7'        => _x("A Series: A7", "wc-setting", "pepro-ultimate-invoice"),
                        'A8'        => _x("A Series: A8", "wc-setting", "pepro-ultimate-invoice"),
                        'A9'        => _x("A Series: A9", "wc-setting", "pepro-ultimate-invoice"),
                        'A10'       => _x("A Series: A10", "wc-setting", "pepro-ultimate-invoice"),
                        'B0'        => _x("B Series: B0", "wc-setting", "pepro-ultimate-invoice"),
                        'B1'        => _x("B Series: B1", "wc-setting", "pepro-ultimate-invoice"),
                        'B2'        => _x("B Series: B2", "wc-setting", "pepro-ultimate-invoice"),
                        'B3'        => _x("B Series: B3", "wc-setting", "pepro-ultimate-invoice"),
                        'B4'        => _x("B Series: B4", "wc-setting", "pepro-ultimate-invoice"),
                        'B5'        => _x("B Series: B5", "wc-setting", "pepro-ultimate-invoice"),
                        'B6'        => _x("B Series: B6", "wc-setting", "pepro-ultimate-invoice"),
                        'B7'        => _x("B Series: B7", "wc-setting", "pepro-ultimate-invoice"),
                        'B8'        => _x("B Series: B8", "wc-setting", "pepro-ultimate-invoice"),
                        'B9'        => _x("B Series: B9", "wc-setting", "pepro-ultimate-invoice"),
                        'B10'       => _x("B Series: B10", "wc-setting", "pepro-ultimate-invoice"),
                        'C0'        => _x("C Series: C0", "wc-setting", "pepro-ultimate-invoice"),
                        'C1'        => _x("C Series: C1", "wc-setting", "pepro-ultimate-invoice"),
                        'C2'        => _x("C Series: C2", "wc-setting", "pepro-ultimate-invoice"),
                        'C3'        => _x("C Series: C3", "wc-setting", "pepro-ultimate-invoice"),
                        'C4'        => _x("C Series: C4", "wc-setting", "pepro-ultimate-invoice"),
                        'C5'        => _x("C Series: C5", "wc-setting", "pepro-ultimate-invoice"),
                        'C6'        => _x("C Series: C6", "wc-setting", "pepro-ultimate-invoice"),
                        'C7'        => _x("C Series: C7", "wc-setting", "pepro-ultimate-invoice"),
                        'C8'        => _x("C Series: C8", "wc-setting", "pepro-ultimate-invoice"),
                        'C9'        => _x("C Series: C9", "wc-setting", "pepro-ultimate-invoice"),
                        'C10'       => _x("C Series: C10", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    'puiw_pdf_size_slip' => array(
                      'name'     => _x("PDF-Slip Page size", "wc-setting", "pepro-ultimate-invoice"),
                      'id'       => 'puiw_pdf_size_slip',
                      'type'     => 'select',
                      'class'    => 'wc-enhanced-select',
                      'default'  => 'A4',
                      'options' =>
                      array(
                        'LABEL_80x100'  => _x("Label: 8 x 10 cm", "wc-setting", "pepro-ultimate-invoice"),
                        'LABEL_100x100' => _x("Label: 10 x 10 cm", "wc-setting", "pepro-ultimate-invoice"),
                        'LABEL_100x150' => _x("Label: 10 x 15 cm", "wc-setting", "pepro-ultimate-invoice"),
                        'A4'        => _x("A Series: A4", "wc-setting", "pepro-ultimate-invoice"),
                        'A5'        => _x("A Series: A5", "wc-setting", "pepro-ultimate-invoice"),
                        'Letter'    => _x("Letter", "wc-setting", "pepro-ultimate-invoice"),
                        'Legal'     => _x("Legal", "wc-setting", "pepro-ultimate-invoice"),
                        'Executive' => _x("Executive", "wc-setting", "pepro-ultimate-invoice"),
                        'Folio'     => _x("Folio", "wc-setting", "pepro-ultimate-invoice"),
                        'Demy'      => _x("Demy", "wc-setting", "pepro-ultimate-invoice"),
                        'Royal'     => _x("Royal", "wc-setting", "pepro-ultimate-invoice"),
                        'A0'        => _x("A Series: A0", "wc-setting", "pepro-ultimate-invoice"),
                        'A1'        => _x("A Series: A1", "wc-setting", "pepro-ultimate-invoice"),
                        'A2'        => _x("A Series: A2", "wc-setting", "pepro-ultimate-invoice"),
                        'A3'        => _x("A Series: A3", "wc-setting", "pepro-ultimate-invoice"),
                        'A6'        => _x("A Series: A6", "wc-setting", "pepro-ultimate-invoice"),
                        'A7'        => _x("A Series: A7", "wc-setting", "pepro-ultimate-invoice"),
                        'A8'        => _x("A Series: A8", "wc-setting", "pepro-ultimate-invoice"),
                        'A9'        => _x("A Series: A9", "wc-setting", "pepro-ultimate-invoice"),
                        'A10'       => _x("A Series: A10", "wc-setting", "pepro-ultimate-invoice"),
                        'B0'        => _x("B Series: B0", "wc-setting", "pepro-ultimate-invoice"),
                        'B1'        => _x("B Series: B1", "wc-setting", "pepro-ultimate-invoice"),
                        'B2'        => _x("B Series: B2", "wc-setting", "pepro-ultimate-invoice"),
                        'B3'        => _x("B Series: B3", "wc-setting", "pepro-ultimate-invoice"),
                        'B4'        => _x("B Series: B4", "wc-setting", "pepro-ultimate-invoice"),
                        'B5'        => _x("B Series: B5", "wc-setting", "pepro-ultimate-invoice"),
                        'B6'        => _x("B Series: B6", "wc-setting", "pepro-ultimate-invoice"),
                        'B7'        => _x("B Series: B7", "wc-setting", "pepro-ultimate-invoice"),
                        'B8'        => _x("B Series: B8", "wc-setting", "pepro-ultimate-invoice"),
                        'B9'        => _x("B Series: B9", "wc-setting", "pepro-ultimate-invoice"),
                        'B10'       => _x("B Series: B10", "wc-setting", "pepro-ultimate-invoice"),
                        'C0'        => _x("C Series: C0", "wc-setting", "pepro-ultimate-invoice"),
                        'C1'        => _x("C Series: C1", "wc-setting", "pepro-ultimate-invoice"),
                        'C2'        => _x("C Series: C2", "wc-setting", "pepro-ultimate-invoice"),
                        'C3'        => _x("C Series: C3", "wc-setting", "pepro-ultimate-invoice"),
                        'C4'        => _x("C Series: C4", "wc-setting", "pepro-ultimate-invoice"),
                        'C5'        => _x("C Series: C5", "wc-setting", "pepro-ultimate-invoice"),
                        'C6'        => _x("C Series: C6", "wc-setting", "pepro-ultimate-invoice"),
                        'C7'        => _x("C Series: C7", "wc-setting", "pepro-ultimate-invoice"),
                        'C8'        => _x("C Series: C8", "wc-setting", "pepro-ultimate-invoice"),
                        'C9'        => _x("C Series: C9", "wc-setting", "pepro-ultimate-invoice"),
                        'C10'       => _x("C Series: C10", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    'puiw_pdf_orientation' => array(
                      'name'     => _x("PDF Page Orientation ", "wc-setting", "pepro-ultimate-invoice"),
                      'id'       => 'puiw_pdf_orientation',
                      'type'     => 'radio',
                      // 'class'    => 'wc-enhanced-select',
                      'default'  => 'P',
                      'options' =>
                      array(
                        'P'  => _x("Portrait", "wc-setting", "pepro-ultimate-invoice"),
                        'L'  => _x("Landscape", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    "puiw_pdf_margin_header" => array(
                      "name" => _x("PDF Padding: Header ", "wc-setting", "pepro-ultimate-invoice"),
                      "id" => 'puiw_pdf_margin_header', 'type' => 'number', 'default' => '0',
                    ),
                    "puiw_pdf_margin_footer" => array(
                      "name" => _x("PDF Padding: Footer ", "wc-setting", "pepro-ultimate-invoice"),
                      "id" => 'puiw_pdf_margin_footer', 'type' => 'number', 'default' => '0',
                    ),
                    "puiw_pdf_margin_top" => array(
                      "name" => _x("PDF Padding: Top ", "wc-setting", "pepro-ultimate-invoice"),
                      "id" => 'puiw_pdf_margin_top', 'type' => 'number', 'default' => '0',
                    ),
                    "puiw_pdf_margin_right" => array(
                      "name" => _x("PDF Padding: Right ", "wc-setting", "pepro-ultimate-invoice"),
                      "id" => 'puiw_pdf_margin_right', 'type' => 'number', 'default' => '0',
                    ),
                    "puiw_pdf_margin_bottom" => array(
                      "name" => _x("PDF Padding: Bottom ", "wc-setting", "pepro-ultimate-invoice"),
                      "id" => 'puiw_pdf_margin_bottom', 'type' => 'number', 'default' => '0',
                    ),
                    "puiw_pdf_margin_left" => array(
                      "name" => _x("PDF Padding: Left ", "wc-setting", "pepro-ultimate-invoice"),
                      "id" => 'puiw_pdf_margin_left', 'type' => 'number', 'default' => '0',
                    ),
                    'puiw_pdf_doc_end' => array(
                      'type' => 'sectionend',
                      'id'   => 'puiw_pdf_doc_end'
                    ),
                    'puiw_slips_title' => array(
                      'name' => _x("Packing Slip / Postal Label", "wc-setting", "pepro-ultimate-invoice"),
                      'type' => 'title',
                      'id'   => 'puiw_slips_title'
                    ),
                    'puiw_slip_layout' => array(
                      'name'     => _x("Sender and recipient arrangement", "wc-setting", "pepro-ultimate-invoice"),
                      'desc_tip' => _x("Iran Post expects the sender on the right and the recipient on the left", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'select',
                      'class'    => 'wc-enhanced-select',
                      'id'       => 'puiw_slip_layout',
                      'options'  => array(
                        'sender-right' => _x("Sender first", "wc-setting", "pepro-ultimate-invoice"),
                        'sender-left'  => _x("Recipient first", "wc-setting", "pepro-ultimate-invoice"),
                        'stacked'      => _x("Stacked", "wc-setting", "pepro-ultimate-invoice"),
                      ),
                    ),
                    'puiw_slip_show_weight' => array(
                      'name'     => _x("Show parcel weight on the slip", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_slip_show_weight',
                    ),
                    'puiw_slip_show_barcode' => array(
                      'name'     => _x("Show barcode block on the slip", "wc-setting", "pepro-ultimate-invoice"),
                      'desc'     => _x("Check to show or leave unchecked to hide", "wc-setting", "pepro-ultimate-invoice"),
                      'type'     => 'checkbox',
                      'id'       => 'puiw_slip_show_barcode',
                    ),
                    'puiw_pdf_end' => array(
                      'type' => 'sectionend',
                      'id'   => 'puiw_automation_end'
                    ),
                  )
                );
                break;
              default:
                $section_data = apply_filters(
                  "puiw_setting_section_default",
                  array(
                    'puiw_default_title' => array(
                      'name'              => _x("Store Details in Invoices", "wc-setting", "pepro-ultimate-invoice"),
                      'type'              => 'title',
                      'id'                => 'puiw_default_title'
                    ),
                    'puiw_store_name' => array(
                      'name'              => _x("Store Name", "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Store Name", "wc-setting", "pepro-ultimate-invoice")),
                      'type'              => 'text',
                      'id'                => 'puiw_store_name',
                    ),
                    'puiw_store_logo' => array(
                      'name'              => _x("Store Logo", "wc-setting", "pepro-ultimate-invoice"),
                      'type'              => 'puiw_image',
                      'desc_tip'          => _x("Select your store logo file", "wc-setting", "pepro-ultimate-invoice"),
                      'id'                => 'puiw_store_logo',
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_store_website' => array(
                      'name'              => _x("Store Website", "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Store Website", "wc-setting", "pepro-ultimate-invoice")),
                      'type'              => 'text',
                      'id'                => 'puiw_store_website',
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_store_email' => array(
                      'name'              => _x("Store E-mail", "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Store E-mail", "wc-setting", "pepro-ultimate-invoice")),
                      'type'              => 'email',
                      'id'                => 'puiw_store_email',
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_store_phone' => array(
                      'name'              => _x("Store Phone number", "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Store Phone number", "wc-setting", "pepro-ultimate-invoice")),
                      'type'              => 'text',
                      'id'                => 'puiw_store_phone',
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_store_national_id' => array(
                      'name'              => _x("Store National ID", "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Store National ID", "wc-setting", "pepro-ultimate-invoice")),
                      'type'              => 'text',
                      'id'                => 'puiw_store_national_id',
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_store_registration_number' => array(
                      'name'              => _x("Store Registration Number", "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Store Registration Number", "wc-setting", "pepro-ultimate-invoice")),
                      'type'              => 'text',
                      'id'                => 'puiw_store_registration_number',
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_store_economical_number' => array(
                      'name'              => _x("Store Economical Number", "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Store Economical Number", "wc-setting", "pepro-ultimate-invoice")),
                      'type'              => 'text',
                      'id'                => 'puiw_store_economical_number',
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_store_address' => array(
                      'name'              => _x("Store Address", "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Store Address", "wc-setting", "pepro-ultimate-invoice")),
                      'type'              => 'textarea',
                      'id'                => 'puiw_store_address',
                      'desc_tip'          => _x("This Address will be used instead of Woocommerce store address in invoices, leave empty to use Woocommerce default address", "wc-setting", "pepro-ultimate-invoice"),
                    ),
                    'puiw_store_postcode' => array(
                      'name'              => _x("Store Postcode / ZIP", "wc-setting", "pepro-ultimate-invoice"),
                      'placeholder'       => sprintf(__("Enter %s here", "pepro-ultimate-invoice"), _x("Store Postcode / ZIP", "wc-setting", "pepro-ultimate-invoice")),
                      'type'              => 'text',
                      'id'                => 'puiw_store_postcode',
                      'custom_attributes' => array('dir' => 'ltr',)
                    ),
                    'puiw_default_end' => array(
                      'type' => 'sectionend',
                      'id'   => 'puiw_default_end'
                    ),
                  )
                );
                // a panel registered by an add-on has no case of its own, and handing it
                // the store fields would print them a second time. It starts empty and
                // fills itself through its own filter, exactly like the built-in sections.
                if ("" !== $current_section && "general" !== $current_section
                  && !in_array($current_section, $this->field_sections(), true)) {
                  $section_data = array();
                }
                if ("" !== $current_section && "general" !== $current_section) {
                  $section_data = apply_filters("puiw_setting_section_{$current_section}", $section_data);
                }
                break;
            }
            return apply_filters("woocommerce_get_settings_{$this->id}", $section_data, $current_section);
          }
          /**
           * sections that are a plain list of WooCommerce setting fields
           *
           * @method field_sections
           * @return array
           */
          public function field_sections() {
            // an add-on that registers a panel needs to join this list, or its fields
            // are never rendered and never saved
            return (array) apply_filters("puiw_settings_field_sections", array(
              "general", "items", "pdf", "theme", "report", "email", "barcode", "privacy", "integ", "extras", "misc",
            ));
          }
          /**
           * icon used for a section in the sidebar
           *
           * @method section_icon
           * @param  string $section
           * @return string
           */
          public function section_icon($section) {
            $icons = array(
              "dashboard" => "chart-line",
              "allsettings" => "list-magnifying-glass",
              "general" => "storefront",
              "items"   => "list-checks",
              "pdf"     => "file-pdf",
              "theme"   => "palette",
              "report"  => "clipboard-text",
              "email"   => "envelope-simple",
              "barcode" => "qr-code",
              "privacy" => "lock-key",
              "color"   => "swatches",
              "integ"   => "plugs-connected",
              "extras"  => "sparkle",
              "strings" => "translate",
              "misc"    => "dots-three-circle",
              "migrate"   => "database",
              "templates" => "code",
              "changelog" => "clock-counter-clockwise",
              "devdocs"   => "terminal-window",
              "debug"     => "bug",
              "pro"       => "crown-simple",
            );
            // so an add-on's own panel is not stuck with the fallback dots
            $icons = (array) apply_filters("puiw_settings_section_icons", $icons);
            return isset($icons[$section]) ? $icons[$section] : "dots-three-circle";
          }
          /**
           * the whole tab is one page now, the sidebar only swaps which panel is visible
           *
           * @method output
           */
          public function output() {
            global $current_section, $hide_save_button, $PeproUltimateInvoice;
            // our own save bar lives at the bottom of the page
            $hide_save_button = true;
            if (!empty($_GET["puiw-setup"])) {
              \peproulitmateinvoice\PeproUltimateInvoice_Setup::render();
              return;
            }

            $sections = $this->get_panels();
            $groups   = $this->get_section_groups();
            $fields   = $this->field_sections();
            $active   = isset($sections[$current_section]) ? $current_section : "dashboard";
            $is_pro   = (bool) apply_filters("puiw_is_pro", false);
            ?>
            <div class="puiw-masthead">
              <div class="puiw-masthead-id">
                <img class="puiw-masthead-mark" src="<?php echo esc_url(PEPROULTIMATEINVOICE_ASSETS_URL . "/img/ultimate-invoice-mark.svg"); ?>" alt="" />
                <span class="puiw-masthead-name"><?php echo esc_html(__("PeproDev Ultimate Invoice", "pepro-ultimate-invoice")); ?></span>
                <span class="puiw-badge <?php echo $is_pro ? "is-pro" : "is-free"; ?>">
                  <?php echo $is_pro ? \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("crown-simple") : ""; ?>
                  <?php echo $is_pro ? esc_html(_x("PRO", "wc-setting", "pepro-ultimate-invoice")) : esc_html(_x("FREE", "wc-setting", "pepro-ultimate-invoice")); ?>
                </span>
                <span class="puiw-masthead-version">v<?php echo esc_html($PeproUltimateInvoice->version); ?></span>
              </div>
              <a class="puiw-masthead-link" href="https://peprodev.com/pepro-woocommerce-ultimate-invoice/" target="_blank" rel="noopener">
                <?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("arrow-square-out"); ?>
                <span><?php echo esc_html(_x("Plugin page", "wc-setting", "pepro-ultimate-invoice")); ?></span>
              </a>
              <div class="puiw-savebar">
                <span class="puiw-savebar-status" role="status" aria-live="polite"></span>
                <?php /* WooCommerce gates its save on !empty($_POST["save"]), and a button
                         with no value posts an empty string, so without this the no-script
                         submit reloads the page and quietly drops every change */ ?>
                <button type="submit" name="save" value="1" class="button button-primary puiw-save">
                  <?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("floppy-disk"); ?>
                  <span><?php echo esc_html(_x("Save changes", "wc-setting", "pepro-ultimate-invoice")); ?></span>
                </button>
              </div>
            </div>
            <div class="puiw-bootcheck" id="puiw-bootcheck" role="alert"
              data-msg-broken="<?php echo esc_attr(_x("The settings script never started. A JavaScript error, another plugin, a script optimiser or a browser extension is stopping it.", "wc-setting", "pepro-ultimate-invoice")); ?>"
              data-msg-nojquery="<?php echo esc_attr(_x("jQuery is missing from this admin page, so nothing on this screen can run. A plugin or an optimiser removed it.", "wc-setting", "pepro-ultimate-invoice")); ?>">
              <?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("warning-circle"); ?>
              <div class="puiw-bootcheck-text">
                <strong><?php echo esc_html(_x("This page did not finish loading", "wc-setting", "pepro-ultimate-invoice")); ?></strong>
                <p class="puiw-bootcheck-msg"><?php echo esc_html(_x("JavaScript is turned off in this browser, so the settings screen cannot run.", "wc-setting", "pepro-ultimate-invoice")); ?></p>
                <pre class="puiw-bootcheck-err" dir="ltr" hidden></pre>
                <p class="puiw-bootcheck-hint"><?php echo esc_html(_x("Open the browser console to see the first error, then try again with other plugins disabled or with JavaScript optimisation turned off in the admin.", "wc-setting", "pepro-ultimate-invoice")); ?></p>
              </div>
              <button type="button" class="button puiw-bootcheck-copy" data-done="<?php echo esc_attr(_x("Copied", "wc-setting-js", "pepro-ultimate-invoice")); ?>"><?php echo esc_html(_x("Copy the error", "wc-setting-js", "pepro-ultimate-invoice")); ?></button>
            </div>
            <div class="puiw-settings" data-active="<?php echo esc_attr($active); ?>">
              <div class="puiw-settings-nav">
                <div class="puiw-nav-search">
                  <?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("magnifying-glass"); ?>
                  <input type="search" id="puiw-settings-search" autocomplete="off" placeholder="<?php echo esc_attr(_x("Search settings", "wc-setting", "pepro-ultimate-invoice")); ?>" />
                </div>
                <div class="puiw-nav-scroll">
                <?php foreach ($groups as $gid => $group) { ?>
                  <div class="puiw-nav-group" data-group="<?php echo esc_attr($gid); ?>">
                    <p class="puiw-nav-group-label"><?php echo esc_html($group["label"]); ?></p>
                    <ul class="puiw-nav-list">
                      <?php foreach ($group["sections"] as $slug) {
                        if (!isset($sections[$slug])) { continue; } ?>
                        <li>
                          <a href="#puiw-<?php echo esc_attr($slug); ?>" data-panel="<?php echo esc_attr($slug); ?>" class="<?php echo $slug === $active ? "is-active" : ""; ?>">
                            <?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get($this->section_icon($slug)); ?>
                            <span><?php echo wp_kses_post($sections[$slug]); ?></span>
                          </a>
                        </li>
                      <?php } ?>
                    </ul>
                  </div>
                <?php } ?>
                </div>
              </div>
              <div class="puiw-settings-body">
                <?php foreach ($sections as $slug => $label) { ?>
                  <div class="puiw-panel<?php echo $slug === $active ? " is-active" : ""; ?>" id="puiw-<?php echo esc_attr($slug); ?>" data-panel="<?php echo esc_attr($slug); ?>" data-label="<?php echo esc_attr(wp_strip_all_tags($label)); ?>">
                    <?php
                    if (in_array($slug, $fields, true)) {
                      \peproulitmateinvoice\PeproUltimateInvoice_Fields::render($this->get_settings($slug), $slug);
                    } elseif ("dashboard" === $slug) {
                      $this->print_dashboard_section();
                    } elseif ("allsettings" === $slug) {
                      $this->print_settings_overview($sections, $fields);
                    } elseif ("debug" === $slug) {
                      $this->print_debug_section();
                    } elseif ("color" === $slug) {
                      $this->print_color_schemes();
                    } elseif ("strings" === $slug) {
                      $this->print_translations_section();
                    } elseif ("templates" === $slug) {
                      $this->print_template_editor();
                    } elseif ("changelog" === $slug) {
                      $this->print_changelog_section();
                    } elseif ("devdocs" === $slug) {
                      $this->print_devdocs_section();
                    } elseif ("pro" === $slug) {
                      $this->print_pro_section();
                    } elseif ("migrate" === $slug) {
                      $this->print_migrate_backup();
                    } else {
                      do_action("puiw_settings_panel_{$slug}", $this);
                    }
                    ?>
                  </div>
                <?php } ?>
              </div>
            </div>
            <div class="puiw-modal" id="puiw-modal" hidden>
              <div class="puiw-modal-backdrop" data-close="1"></div>
              <div class="puiw-modal-box" role="dialog" aria-modal="true" aria-labelledby="puiw-modal-title">
                <div class="puiw-modal-head">
                  <h2 id="puiw-modal-title"></h2>
                  <button type="button" class="puiw-modal-close" data-close="1" aria-label="<?php echo esc_attr(_x("Close", "wc-setting-js", "pepro-ultimate-invoice")); ?>">
                    <?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("x-circle"); ?>
                  </button>
                </div>
                <div class="puiw-modal-body"></div>
              </div>
            </div>
            <?php
            wp_nonce_field("pepro-ultimate-invoice-settings", "puiw_settings_nonce");
          }
          /**
           * the sidebar replaces WooCommerce's section links
           *
           * @method output_sections
           */
          public function output_sections() {
          }
          /**
           * what the shop looks like through this plugin, and whether it is healthy
           *
           * @method print_dashboard_section
           */
          public function print_dashboard_section() {
            global $PeproUltimateInvoice;
            $icon  = function ($name) {
              return \peproulitmateinvoice\PeproUltimateInvoice_Icons::get($name);
            };
            $stats  = $this->order_stats();
            $checks = $this->health_checks();
            $tpl    = \peproulitmateinvoice\PeproUltimateInvoice_Themes::info($PeproUltimateInvoice->tpl->get_template());
            ob_start();
            ?>
            <h2><?php echo _x("Dashboard", "wc-setting", "pepro-ultimate-invoice"); ?></h2>
            <p class="puiw-panel-intro"><?php echo _x("A quick look at the orders this plugin issues invoices for, and whether anything needs your attention.", "wc-setting", "pepro-ultimate-invoice"); ?></p>

            <div class="puiw-stats">
              <?php foreach ($this->dashboard_stat_rows($stats) as $stat) { ?>
                <div class="puiw-stat">
                  <span class="puiw-stat-top">
                    <span class="puiw-stat-label"><?php echo esc_html($stat["label"]); ?></span>
                    <span class="puiw-stat-icon"><?php echo $icon($stat["icon"]); ?></span>
                  </span>
                  <span class="puiw-stat-value"><?php echo esc_html($stat["value"]); ?></span>
                  <?php if ("" !== $stat["change"][1]) { ?>
                    <span class="puiw-stat-change is-<?php echo esc_attr($stat["change"][0]); ?>"><?php echo $icon("up" === $stat["change"][0] ? "trend-up" : ("down" === $stat["change"][0] ? "trend-down" : "minus")); ?><?php echo esc_html($stat["change"][1]); ?> <em><?php echo esc_html(_x("vs the 30 days before", "wc-setting", "pepro-ultimate-invoice")); ?></em></span>
                  <?php } ?>
                </div>
              <?php } ?>
            </div>

            <div class="puiw-dash-row">
              <div class="puiw-card puiw-dash-chart">
                <h3><?php echo $icon("chart-bar"); ?><?php echo esc_html(_x("Sales, last 30 days", "wc-setting", "pepro-ultimate-invoice")); ?></h3>
                <?php echo $this->sales_chart($stats["days"]); ?>
              </div>
              <div class="puiw-card puiw-dash-statuses">
                <h3><?php echo $icon("chart-pie-slice"); ?><?php echo esc_html(_x("Orders by status", "wc-setting", "pepro-ultimate-invoice")); ?></h3>
                <?php $all = max(1, (int) $stats["total"]); $names = function_exists("wc_get_order_statuses") ? wc_get_order_statuses() : array(); ?>
                <ul class="puiw-statusbars">
                  <?php arsort($stats["statuses"]); foreach ($stats["statuses"] as $status => $count) { ?>
                    <li class="status-<?php echo esc_attr(str_replace("wc-", "", $status)); ?>">
                      <span class="puiw-statusbars-name"><?php echo esc_html(isset($names[$status]) ? $names[$status] : $status); ?></span>
                      <span class="puiw-statusbars-count"><?php echo esc_html(number_format_i18n($count)); ?></span>
                      <span class="puiw-statusbars-track"><i style="width:<?php echo esc_attr(round($count / $all * 100, 1)); ?>%"></i></span>
                    </li>
                  <?php } ?>
                  <?php if (empty($stats["statuses"])) { ?><li class="puiw-statusbars-empty"><?php echo esc_html(_x("No orders yet.", "wc-setting", "pepro-ultimate-invoice")); ?></li><?php } ?>
                </ul>
              </div>
            </div>

            <div class="puiw-card puiw-dash-health">
              <h3><?php echo $icon("heartbeat"); ?><?php echo esc_html(_x("Plugin status", "wc-setting", "pepro-ultimate-invoice")); ?></h3>
              <div class="puiw-health">
                <?php foreach ($checks as $check) { ?>
                  <div class="puiw-health-item is-<?php echo esc_attr($check["state"]); ?>">
                    <span class="puiw-health-icon"><?php echo $icon("ok" === $check["state"] ? "check-circle" : ("warn" === $check["state"] ? "warning-circle" : "x-circle")); ?></span>
                    <span class="puiw-health-text">
                      <strong><?php echo esc_html($check["label"]); ?></strong>
                      <em><?php echo wp_kses_post($check["detail"]); ?></em>
                    </span>
                  </div>
                <?php } ?>
              </div>
            </div>

            <div class="puiw-dash-grid">
              <div class="puiw-card">
                <h3><?php echo $icon("palette"); ?><?php echo esc_html(_x("Active template", "wc-setting", "pepro-ultimate-invoice")); ?></h3>
                <p class="puiw-dash-template">
                  <strong><?php echo esc_html($tpl["name"]); ?></strong>
                  <em><?php echo esc_html($tpl["author"]); ?> &middot; <?php echo esc_html($tpl["version"]); ?></em>
                </p>
                <p class="puiw-card-actions">
                  <a href="#puiw-theme" class="button" data-panel="theme"><?php echo esc_html(_x("Change template", "wc-setting", "pepro-ultimate-invoice")); ?></a>
                  <a href="#puiw-templates" class="button" data-panel="templates"><?php echo esc_html(_x("Template Editor", "wc-setting", "pepro-ultimate-invoice")); ?></a>
                </p>
              </div>
              <div class="puiw-card">
                <h3><?php echo $icon("magic-wand"); ?><?php echo esc_html(_x("Setup wizard", "wc-setting", "pepro-ultimate-invoice")); ?></h3>
                <p><?php echo esc_html(_x("Walk through the store details, the look, what to print and delivery in five short steps.", "wc-setting", "pepro-ultimate-invoice")); ?></p>
                <p class="puiw-card-actions">
                  <a href="<?php echo esc_url(\peproulitmateinvoice\PeproUltimateInvoice_Setup::url()); ?>" class="button button-primary"><?php echo esc_html(_x("Run the setup", "wc-setting", "pepro-ultimate-invoice")); ?></a>
                </p>
              </div>
            </div>

            <?php $this->print_news_widget(); ?>

            <p class="puiw-dash-sign">
              <a href="https://wordpress.org/plugins/pepro-ultimate-invoice/" target="_blank" rel="noopener">
                <img src="<?php echo esc_url(PEPROULTIMATEINVOICE_ASSETS_URL . "/img/ultimate-invoice-logo.svg"); ?>" alt="<?php echo esc_attr(__("PeproDev Ultimate Invoice", "pepro-ultimate-invoice")); ?>" />
              </a>
            </p>
            <?php
            $html_output = ob_get_contents();
            ob_end_clean();
            echo apply_filters("puiw_section_dashboard_html", $html_output);
          }
          /**
           * news and offers from peprodev.com
           *
           * One request a day at most, cached either way so a site that cannot reach us
           * is never slowed down, and nothing is sent: it is a plain GET of a public file.
           *
           * @method print_news_widget
           * @version 1.0.0
           * @since 3.2.5
           */
          public function print_news_widget() {
            if ("yes" !== get_option("puiw_show_news", "yes")) {
              return;
            }
            $feed = $this->read_news();
            if (empty($feed["posts"]) && empty($feed["deals"])) {
              return;
            }
            $icon = function ($name) {
              return \peproulitmateinvoice\PeproUltimateInvoice_Icons::get($name);
            };
            ?>
            <div class="puiw-dash-grid puiw-news">
              <?php if (!empty($feed["posts"])) { ?>
                <div class="puiw-card">
                  <h3><?php echo $icon("note"); ?><?php echo esc_html(_x("From the blog", "wc-setting", "pepro-ultimate-invoice")); ?></h3>
                  <ul class="puiw-newslist">
                    <?php foreach (array_slice((array) $feed["posts"], 0, 4) as $post) { ?>
                      <li>
                        <a href="<?php echo esc_url($post["url"]); ?>" target="_blank" rel="noopener">
                          <strong><?php echo esc_html($post["title"]); ?></strong>
                          <?php if (!empty($post["excerpt"])) { ?><em><?php echo esc_html($post["excerpt"]); ?></em><?php } ?>
                        </a>
                      </li>
                    <?php } ?>
                  </ul>
                </div>
              <?php } ?>
              <?php if (!empty($feed["deals"])) { ?>
                <div class="puiw-card puiw-card-deal">
                  <h3><?php echo $icon("gift"); ?><?php echo esc_html(_x("Offers", "wc-setting", "pepro-ultimate-invoice")); ?></h3>
                  <ul class="puiw-newslist">
                    <?php foreach (array_slice((array) $feed["deals"], 0, 3) as $deal) { ?>
                      <li>
                        <a href="<?php echo esc_url($deal["url"]); ?>" target="_blank" rel="noopener">
                          <strong><?php echo esc_html($deal["title"]); ?></strong>
                          <?php if (!empty($deal["note"])) { ?><em><?php echo esc_html($deal["note"]); ?></em><?php } ?>
                        </a>
                        <?php if (!empty($deal["badge"])) { ?><span class="puiw-deal-badge"><?php echo esc_html($deal["badge"]); ?></span><?php } ?>
                      </li>
                    <?php } ?>
                  </ul>
                </div>
              <?php } ?>
            </div>
            <?php
          }
          /**
           * fetch and cache the news file
           *
           * @method read_news
           * @return array posts, deals
           */
          public function read_news() {
            $cached = get_transient("puiw_news");
            if (is_array($cached)) {
              return $cached;
            }
            $url  = apply_filters("puiw_news_endpoint", "https://peprodev.com/api/plugin-news.json");
            $feed = array("posts" => array(), "deals" => array());
            $res  = wp_remote_get($url, array("timeout" => 8, "headers" => array("Accept" => "application/json")));
            if (!is_wp_error($res) && 200 === (int) wp_remote_retrieve_response_code($res)) {
              $json = json_decode((string) wp_remote_retrieve_body($res), true);
              if (is_array($json)) {
                foreach (array("posts", "deals") as $kind) {
                  foreach ((array) (isset($json[$kind]) ? $json[$kind] : array()) as $row) {
                    if (empty($row["title"]) || empty($row["url"])) {
                      continue;
                    }
                    $feed[$kind][] = array(
                      "title"   => sanitize_text_field($row["title"]),
                      "url"     => esc_url_raw($row["url"]),
                      "excerpt" => isset($row["excerpt"]) ? sanitize_text_field($row["excerpt"]) : "",
                      "note"    => isset($row["note"]) ? sanitize_text_field($row["note"]) : "",
                      "badge"   => isset($row["badge"]) ? sanitize_text_field($row["badge"]) : "",
                    );
                  }
                }
              }
            }
            // cached even when empty, so an unreachable site is asked once a day, not every load
            set_transient("puiw_news", $feed, DAY_IN_SECONDS);
            return $feed;
          }
          /**
           * order counts worth showing, cached so the panel never costs a query storm
           *
           * @method order_stats
           * @return array
           */
          public function order_stats() {
            // only numbers are cached: labels and money are formatted per request, in
            // that request's language
            $data = get_transient("puiw_dashboard_stats_v2");
            if (!is_array($data) || !isset($data["days"])) {
              $data = $this->collect_order_stats();
              set_transient("puiw_dashboard_stats_v2", $data, 15 * MINUTE_IN_SECONDS);
            }
            return $data;
          }
          /**
           * sales per day for the last 30 days, the 30 before them for comparison, and
           * how all orders split across statuses
           *
           * @method collect_order_stats
           * @return array
           */
          public function collect_order_stats() {
            $days = array();
            for ($i = 29; $i >= 0; $i--) {
              $days[gmdate("Y-m-d", strtotime("-{$i} days", current_time("timestamp")))] = array("revenue" => 0.0, "orders" => 0);
            }
            $data = array("days" => $days, "revenue" => 0.0, "orders" => 0, "prev_revenue" => 0.0, "prev_orders" => 0, "total" => 0, "statuses" => array());
            if (!function_exists("wc_get_orders")) {
              return $data;
            }
            $paid  = apply_filters("puiw_dashboard_paid_statuses", array("wc-processing", "wc-completed", "wc-on-hold"));
            $since = gmdate("Y-m-d", strtotime("-59 days", current_time("timestamp")));
            $start = gmdate("Y-m-d", strtotime("-29 days", current_time("timestamp")));
            foreach ((array) wc_get_orders(array("limit" => 2000, "status" => $paid, "date_created" => ">=" . $since, "type" => "shop_order")) as $order) {
              if (!is_a($order, "WC_Order") || !$order->get_date_created()) {
                continue;
              }
              $day   = $order->get_date_created()->date_i18n("Y-m-d");
              $total = (float) $order->get_total();
              if ($day >= $start) {
                $data["revenue"] += $total;
                $data["orders"]++;
                if (isset($data["days"][$day])) {
                  $data["days"][$day]["revenue"] += $total;
                  $data["days"][$day]["orders"]++;
                }
              } else {
                $data["prev_revenue"] += $total;
                $data["prev_orders"]++;
              }
            }
            foreach (wc_get_order_statuses() as $status => $label) {
              $count = (int) wc_orders_count(str_replace("wc-", "", $status));
              if ($count) {
                $data["statuses"][$status] = $count;
                $data["total"] += $count;
              }
            }
            return $data;
          }
          /**
           * one stat card: value, label and the change against the 30 days before
           *
           * @method dashboard_stat_rows
           * @param  array $data
           * @return array
           */
          public function dashboard_stat_rows($data) {
            $money  = function ($v) {
              return function_exists("wc_price") ? wp_strip_all_tags(wc_price($v)) : number_format_i18n($v);
            };
            $change = function ($now, $before) {
              if ($before <= 0) {
                return $now > 0 ? array("up", _x("new", "dashboard-change", "pepro-ultimate-invoice")) : array("flat", "");
              }
              $pct = ($now - $before) / $before * 100;
              return array($pct > 0.5 ? "up" : ($pct < -0.5 ? "down" : "flat"), sprintf("%s%%", number_format_i18n(abs($pct), 0)));
            };
            $aov      = $data["orders"] ? $data["revenue"] / $data["orders"] : 0;
            $prev_aov = $data["prev_orders"] ? $data["prev_revenue"] / $data["prev_orders"] : 0;
            return array(
              array("icon" => "currency-circle-dollar", "label" => _x("Sales, last 30 days", "wc-setting", "pepro-ultimate-invoice"), "value" => $money($data["revenue"]), "change" => $change($data["revenue"], $data["prev_revenue"])),
              array("icon" => "shopping-cart", "label" => _x("Paid orders, last 30 days", "wc-setting", "pepro-ultimate-invoice"), "value" => number_format_i18n($data["orders"]), "change" => $change($data["orders"], $data["prev_orders"])),
              array("icon" => "receipt", "label" => _x("Average order", "wc-setting", "pepro-ultimate-invoice"), "value" => $money($aov), "change" => $change($aov, $prev_aov)),
              array("icon" => "files", "label" => _x("All orders", "wc-setting", "pepro-ultimate-invoice"), "value" => number_format_i18n($data["total"]), "change" => array("flat", "")),
            );
          }
          /**
           * the last 30 days as an inline bar chart, no script and no library
           *
           * @method sales_chart
           * @param  array $days date => revenue, orders
           * @return string svg
           */
          public function sales_chart($days) {
            $w = 720; $h = 200; $pad = 24;
            $max = 0;
            foreach ($days as $d) {
              $max = max($max, $d["revenue"]);
            }
            $n    = max(1, count($days));
            $slot = ($w - 2 * $pad) / $n;
            $bar  = max(4, $slot * 0.62);
            $out  = sprintf('<svg class="puiw-chart" viewBox="0 0 %1$d %2$d" role="img" direction="ltr" aria-label="%3$s">', $w, $h + 28, esc_attr(_x("Sales, last 30 days", "wc-setting", "pepro-ultimate-invoice")));
            foreach (array(0.25, 0.5, 0.75, 1) as $g) {
              $y = $h - ($h - $pad) * $g;
              $out .= sprintf('<line class="puiw-chart-grid" x1="%1$d" x2="%2$d" y1="%3$.1f" y2="%3$.1f" />', $pad, $w - $pad, $y);
            }
            $i = 0;
            foreach ($days as $day => $d) {
              $bh = $max > 0 ? max($d["revenue"] > 0 ? 3 : 0, ($h - $pad) * $d["revenue"] / $max) : 0;
              $x  = $pad + $i * $slot + ($slot - $bar) / 2;
              $tip = sprintf("%s · %s · %s", date_i18n(get_option("date_format"), strtotime($day)), function_exists("wc_price") ? wp_strip_all_tags(wc_price($d["revenue"])) : $d["revenue"], sprintf(_n("%s order", "%s orders", $d["orders"], "pepro-ultimate-invoice"), number_format_i18n($d["orders"])));
              $out .= sprintf('<rect class="puiw-chart-slot" x="%1$.1f" y="%2$d" width="%3$.1f" height="%4$d"><title>%5$s</title></rect>', $pad + $i * $slot, 0, $slot, $h, esc_html($tip));
              $out .= sprintf('<rect class="puiw-chart-bar" x="%1$.1f" y="%2$.1f" width="%3$.1f" height="%4$.1f" rx="3"><title>%5$s</title></rect>', $x, $h - $bh, $bar, $bh, esc_html($tip));
              if (0 === ($n - 1 - $i) % 7) {
                $out .= sprintf('<text class="puiw-chart-label" x="%1$.1f" y="%2$d">%3$s</text>', $x + $bar / 2, $h + 18, esc_html(date_i18n("j M", strtotime($day))));
              }
              $i++;
            }
            $out .= sprintf('<line class="puiw-chart-axis" x1="%1$d" x2="%2$d" y1="%3$d" y2="%3$d" />', $pad, $w - $pad, $h);
            return $out . "</svg>";
          }
          /**
           * the handful of things that actually stop invoices working
           *
           * @method health_checks
           * @return array
           */
          public function health_checks() {
            global $PeproUltimateInvoice;
            $checks = array();
            $themes = "\peproulitmateinvoice\PeproUltimateInvoice_Themes";

            $template = $PeproUltimateInvoice->tpl->get_template();
            $checks[] = array(
              "label"  => _x("Invoice template", "wc-setting", "pepro-ultimate-invoice"),
              "state"  => is_readable("$template/default.cfg") ? "ok" : "bad",
              "detail" => is_readable("$template/default.cfg") ? basename($template) : _x("The selected template folder is missing.", "wc-setting", "pepro-ultimate-invoice"),
            );

            $gd = extension_loaded("gd");
            $checks[] = array(
              "label"  => _x("Barcode and QR rendering", "wc-setting", "pepro-ultimate-invoice"),
              "state"  => $gd ? "ok" : "warn",
              "detail" => $gd ? "GD " . (defined("GD_VERSION") ? GD_VERSION : "") : _x("PHP has no GD extension, barcodes cannot be drawn.", "wc-setting", "pepro-ultimate-invoice"),
            );

            $mbstring = extension_loaded("mbstring");
            $checks[] = array(
              "label"  => _x("PDF engine", "wc-setting", "pepro-ultimate-invoice"),
              "state"  => $mbstring ? "ok" : "bad",
              "detail" => $mbstring ? "mPDF" : _x("PHP has no mbstring extension, mPDF cannot run.", "wc-setting", "pepro-ultimate-invoice"),
            );

            $uploads  = wp_upload_dir();
            $writable = empty($uploads["error"]) && wp_is_writable($uploads["basedir"]);
            $checks[] = array(
              "label"  => _x("Uploads folder", "wc-setting", "pepro-ultimate-invoice"),
              "state"  => $writable ? "ok" : "bad",
              "detail" => $writable ? _x("Writable, PDFs and your own templates can be stored.", "wc-setting", "pepro-ultimate-invoice") : _x("Not writable, PDFs and template editing will fail.", "wc-setting", "pepro-ultimate-invoice"),
            );

            $zip = class_exists("ZipArchive");
            $checks[] = array(
              "label"  => _x("Template import and export", "wc-setting", "pepro-ultimate-invoice"),
              "state"  => $zip ? "ok" : "warn",
              "detail" => $zip ? "ZipArchive" : _x("PHP has no zip extension, templates cannot be zipped.", "wc-setting", "pepro-ultimate-invoice"),
            );

            $own = count($themes::user_themes());
            $checks[] = array(
              "label"  => _x("Your own templates", "wc-setting", "pepro-ultimate-invoice"),
              "state"  => "ok",
              "detail" => sprintf(_n("%d template of your own.", "%d templates of your own.", $own, "pepro-ultimate-invoice"), $own),
            );

            return apply_filters("puiw_health_checks", $checks);
          }
          /**
           * duplicate, edit, export and import invoice templates
           *
           * @method print_template_editor
           */
          public function print_template_editor() {
            global $PeproUltimateInvoice;
            $icons   = "\peproulitmateinvoice\PeproUltimateInvoice_Icons";
            $themes  = (array) $PeproUltimateInvoice->load_themes(1);
            ob_start();
            ?>
            <h2><?php echo _x("Template Editor", "wc-setting", "pepro-ultimate-invoice"); ?></h2>
            <p class="puiw-panel-intro"><?php echo _x("Duplicate a template to get your own editable copy in the uploads folder, then change its HTML and CSS here. Bundled templates stay untouched so an update never overwrites your work.", "wc-setting", "pepro-ultimate-invoice"); ?></p>
            <div class="puiw-te" data-nonce="<?php echo esc_attr(wp_create_nonce("pepro-ultimate-invoice")); ?>">
              <div class="puiw-te-toolbar">
                <button type="button" class="button button-primary puiw-te-import">
                  <?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("upload-simple"); ?>
                  <span><?php echo esc_html(_x("Import a template zip", "wc-setting", "pepro-ultimate-invoice")); ?></span>
                </button>
                <input type="file" class="puiw-te-file" accept=".zip,application/zip" />
              </div>
              <div class="puiw-te-themes">
                <?php foreach ($themes as $path => $theme) {
                  $info = \peproulitmateinvoice\PeproUltimateInvoice_Themes::info($path);
                  ?>
                  <div class="puiw-te-theme<?php echo $info["editable"] ? " is-editable" : ""; ?>" data-theme="<?php echo esc_attr($info["slug"]); ?>">
                    <span class="puiw-te-shot"><img src="<?php echo esc_url($theme["icon"]); ?>" alt="" loading="lazy" /></span>
                    <span class="puiw-te-meta">
                      <strong><?php echo esc_html($info["name"]); ?></strong>
                      <em><?php echo esc_html($info["author"]); ?> &middot; <?php echo esc_html($info["version"]); ?></em>
                      <?php if (!$info["editable"]) { ?><span class="puiw-te-tag"><?php echo esc_html(_x("bundled", "wc-setting", "pepro-ultimate-invoice")); ?></span><?php } ?>
                    </span>
                    <span class="puiw-te-actions">
                      <?php if ($info["editable"]) { ?>
                        <button type="button" class="button puiw-te-edit"><?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("code"); ?><span><?php echo esc_html(_x("Edit", "wc-setting", "pepro-ultimate-invoice")); ?></span></button>
                      <?php } ?>
                      <button type="button" class="button puiw-te-duplicate"><?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("copy"); ?><span><?php echo esc_html(_x("Duplicate", "wc-setting", "pepro-ultimate-invoice")); ?></span></button>
                      <button type="button" class="button puiw-te-export"><?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("download-simple"); ?><span><?php echo esc_html(_x("Export", "wc-setting", "pepro-ultimate-invoice")); ?></span></button>
                      <?php if ($info["editable"]) { ?>
                        <button type="button" class="button puiw-te-delete"><?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("trash"); ?><span class="screen-reader-text"><?php echo esc_html(_x("Delete", "wc-setting", "pepro-ultimate-invoice")); ?></span></button>
                      <?php } ?>
                    </span>
                  </div>
                <?php } ?>
              </div>
              <div class="puiw-te-workspace" hidden>
                <div class="puiw-te-head">
                  <div class="puiw-te-head-meta">
                    <label><span><?php echo esc_html(_x("Name", "wc-setting", "pepro-ultimate-invoice")); ?></span><input type="text" class="puiw-te-name" /></label>
                    <label><span><?php echo esc_html(_x("Author", "wc-setting", "pepro-ultimate-invoice")); ?></span><input type="text" class="puiw-te-author" /></label>
                    <label><span><?php echo esc_html(_x("Version", "wc-setting", "pepro-ultimate-invoice")); ?></span><input type="text" class="puiw-te-version" dir="ltr" /></label>
                    <label><span><?php echo esc_html(_x("Built for plugin", "wc-setting", "pepro-ultimate-invoice")); ?></span><input type="text" class="puiw-te-requires" dir="ltr" placeholder="<?php echo esc_attr($PeproUltimateInvoice->version); ?>" /></label>
                  </div>
                  <button type="button" class="button puiw-te-close"><?php echo esc_html(_x("Close editor", "wc-setting", "pepro-ultimate-invoice")); ?></button>
                </div>
                <div class="puiw-te-grid">
                  <ul class="puiw-te-files"></ul>
                  <div class="puiw-te-editor">
                    <div class="puiw-te-editor-bar">
                      <span class="puiw-te-current"></span>
                      <span class="puiw-te-status" role="status" aria-live="polite"></span>
                      <button type="button" class="button button-primary puiw-te-save">
                        <?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("floppy-disk-back"); ?>
                        <span><?php echo esc_html(_x("Save file", "wc-setting", "pepro-ultimate-invoice")); ?></span>
                      </button>
                    </div>
                    <textarea class="puiw-te-code" spellcheck="false"></textarea>
                  </div>
                </div>
              </div>
            </div>
            <?php
            $html_output = ob_get_contents();
            ob_end_clean();
            echo apply_filters("puiw_section_templates_html", $html_output);
          }
          /**
           * the readme changelog, rendered and searchable
           *
           * @method print_changelog_section
           */
          public function print_changelog_section() {
            global $PeproUltimateInvoice;
            ob_start();
            ?>
            <img class="puiw-wordmark" src="<?php echo esc_url(PEPROULTIMATEINVOICE_ASSETS_URL . "/img/ultimate-invoice-logo.svg"); ?>" alt="<?php echo esc_attr(__("PeproDev Ultimate Invoice", "pepro-ultimate-invoice")); ?>" />
            <h2><?php echo _x("Changelog", "wc-setting", "pepro-ultimate-invoice"); ?></h2>
            <p class="puiw-panel-intro"><?php printf(esc_html(_x("You are on version %s.", "wc-setting", "pepro-ultimate-invoice")), esc_html($PeproUltimateInvoice->version)); ?></p>
            <div class="puiw-filterbox">
              <?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("magnifying-glass"); ?>
              <input type="search" class="puiw-list-filter" data-list=".puiw-changelog" autocomplete="off" placeholder="<?php echo esc_attr(_x("Search the changelog", "wc-setting", "pepro-ultimate-invoice")); ?>" />
            </div>
            <div class="puiw-changelog">
              <?php
              $entries = $this->read_changelog();
              if (empty($entries)) { ?>
                <p><?php echo esc_html(_x("The changelog could not be read.", "wc-setting", "pepro-ultimate-invoice")); ?></p>
              <?php } else {
                foreach ($entries as $entry) { ?>
                  <details class="puiw-release" dir="ltr" data-search="<?php echo esc_attr($entry["title"] . " " . implode(" ", $entry["lines"])); ?>"<?php echo $entry["current"] ? " open" : ""; ?>>
                    <summary>
                      <span class="puiw-release-version"><?php echo esc_html($entry["version"]); ?></span>
                      <span class="puiw-release-date"><?php echo esc_html($entry["date"]); ?></span>
                      <?php if ($entry["current"]) { ?><span class="puiw-badge is-free"><?php echo esc_html(_x("installed", "wc-setting", "pepro-ultimate-invoice")); ?></span><?php } ?>
                    </summary>
                    <ul>
                      <?php foreach ($entry["lines"] as $line) { ?>
                        <li><?php echo wp_kses_post($line); ?></li>
                      <?php } ?>
                    </ul>
                  </details>
                <?php }
              } ?>
            </div>
            <?php
            $html_output = ob_get_contents();
            ob_end_clean();
            echo apply_filters("puiw_section_changelog_html", $html_output);
          }
          /**
           * parse the == Changelog == part of readme.txt
           *
           * @method read_changelog
           * @return array
           */
          public function read_changelog() {
            global $PeproUltimateInvoice;
            $file = PEPROULTIMATEINVOICE_DIR . "readme.txt";
            if (!is_readable($file)) {
              return array();
            }
            $body = (string) file_get_contents($file);
            $pos  = stripos($body, "== Changelog ==");
            if (false !== $pos) {
              $body = substr($body, $pos);
            }
            $entries = array();
            $current = null;
            foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
              $line = rtrim($line);
              if (preg_match('/^=\s*v?([0-9][0-9.]*)\s*(?:\|\s*(.*?))?\s*=$/', $line, $m)) {
                if ($current) {
                  $entries[] = $current;
                }
                $current = array(
                  "version" => $m[1],
                  "date"    => isset($m[2]) ? trim($m[2]) : "",
                  "title"   => $line,
                  "lines"   => array(),
                  "current" => version_compare($m[1], $PeproUltimateInvoice->version, "=="),
                );
                continue;
              }
              if (!$current || "" === trim($line)) {
                continue;
              }
              $current["lines"][] = ltrim($line, "-* ");
            }
            if ($current) {
              $entries[] = $current;
            }
            return $entries;
          }
          /**
           * every action and filter the plugin exposes, found in its own source
           *
           * @method print_devdocs_section
           */
          public function print_devdocs_section() {
            ob_start();
            $hooks = $this->collect_hooks();
            ?>
            <h2><?php echo _x("Developer reference", "wc-setting", "pepro-ultimate-invoice"); ?></h2>
            <p class="puiw-panel-intro"><?php echo _x("Every action and filter this plugin fires, read straight out of its source so the list can never go stale.", "wc-setting", "pepro-ultimate-invoice"); ?></p>
            <div class="puiw-filterbox">
              <?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("magnifying-glass"); ?>
              <input type="search" class="puiw-list-filter" data-list=".puiw-hooks" autocomplete="off" placeholder="<?php echo esc_attr(_x("Search hooks", "wc-setting", "pepro-ultimate-invoice")); ?>" />
              <span class="puiw-filterbox-count"><?php printf(esc_html(_x('%1$d filters, %2$d actions', "wc-setting", "pepro-ultimate-invoice")), count($hooks["filter"]), count($hooks["action"])); ?></span>
            </div>
            <div class="puiw-hooks">
              <?php foreach (array("filter" => _x("Filters", "wc-setting", "pepro-ultimate-invoice"), "action" => _x("Actions", "wc-setting", "pepro-ultimate-invoice")) as $kind => $label) { ?>
                <h3><?php echo esc_html($label); ?></h3>
                <div class="puiw-hooklist">
                  <?php foreach ($hooks[$kind] as $hook => $places) {
                    $where = $places[0];
                    ?>
                    <details class="puiw-hook" dir="ltr" data-search="<?php echo esc_attr($hook . " " . implode(" ", array_column($places, "file"))); ?>">
                      <summary>
                        <code><?php echo esc_html($hook); ?></code>
                        <span class="puiw-hook-where"><?php echo esc_html($where["file"] . ":" . $where["line"]); ?></span>
                      </summary>
                      <pre class="puiw-hook-sample"><?php echo esc_html($this->hook_sample($kind, $hook, $where)); ?></pre>
                      <p class="puiw-hook-links">
                        <?php foreach ($places as $place) { ?>
                          <a href="<?php echo esc_url($this->hook_source_url($place)); ?>" target="_blank" rel="noopener">
                            <?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("arrow-square-out"); ?>
                            <span><?php echo esc_html($place["file"] . ":" . $place["line"]); ?></span>
                          </a>
                        <?php } ?>
                      </p>
                    </details>
                  <?php } ?>
                </div>
              <?php } ?>
            </div>
            <?php
            $html_output = ob_get_contents();
            ob_end_clean();
            echo apply_filters("puiw_section_devdocs_html", $html_output);
          }
          /**
           * scan the plugin source for apply_filters/do_action calls
           *
           * @method collect_hooks
           * @return array
           */
          public function collect_hooks() {
            $cached = get_transient("puiw_hook_index");
            if (is_array($cached)) {
              return $cached;
            }
            $found = array("filter" => array(), "action" => array());
            $files = array_merge(
              array(PEPROULTIMATEINVOICE_DIR . "pepro-ultimate-invoice.php"),
              (array) glob(PEPROULTIMATEINVOICE_DIR . "include/admin/*.php")
            );
            foreach ($files as $file) {
              $body = (string) file_get_contents($file);
              $name = str_replace(PEPROULTIMATEINVOICE_DIR, "", $file);
              if (preg_match_all('/\b(apply_filters|do_action)\s*\(\s*["\']([a-z0-9_\/{}$\->]+)["\']([^;]*)/i', $body, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                  $kind = "apply_filters" === strtolower($match[1][0]) ? "filter" : "action";
                  $line = substr_count(substr($body, 0, $match[0][1]), "\n") + 1;
                  // count the arguments after the hook name to get the arity right
                  $args = 0;
                  $tail = (string) $match[3][0];
                  $depth = 0;
                  foreach (str_split($tail) as $char) {
                    if ("(" === $char || "[" === $char) { $depth++; }
                    elseif (")" === $char || "]" === $char) { if ($depth === 0) { break; } $depth--; }
                    elseif ("," === $char && 0 === $depth) { $args++; }
                  }
                  $found[$kind][$match[2][0]][] = array("file" => $name, "line" => $line, "args" => max(0, $args));
                }
              }
            }
            ksort($found["filter"]);
            ksort($found["action"]);
            set_transient("puiw_hook_index", $found, DAY_IN_SECONDS);
            return $found;
          }
          /**
           * a copy and paste example for one hook
           *
           * @method hook_sample
           * @param  string $kind filter or action
           * @param  string $hook
           * @param  array  $place file, line and argument count
           * @return string
           */
          public function hook_sample($kind, $hook, $place) {
            $args  = max(1, (int) $place["args"]);
            $names = array('$value', '$arg2', '$arg3', '$arg4', '$arg5', '$arg6');
            $list  = implode(", ", array_slice($names, 0, $args));

            if ("filter" === $kind) {
              return "add_filter( '{$hook}', function ( {$list} ) {\n"
                . "\t// change \$value and hand it back\n"
                . "\treturn \$value;\n"
                . "}, 10, {$args} );";
            }
            $list = $args > 0 ? implode(", ", array_slice($names, 0, $args)) : "";
            return "add_action( '{$hook}', function ( {$list} ) {\n"
              . "\t// run your own code here\n"
              . "}, 10, {$args} );";
          }
          /**
           * link straight to the line that fires a hook
           *
           * @method hook_source_url
           * @param  array $place
           * @return string
           */
          public function hook_source_url($place) {
            global $PeproUltimateInvoice;
            $base = apply_filters("puiw_source_repository", "https://github.com/peprodev/ultimate-invoice/blob/v" . $PeproUltimateInvoice->version);
            return sprintf("%s/%s#L%d", untrailingslashit($base), ltrim($place["file"], "/"), (int) $place["line"]);
          }
          /**
           * what the paid edition will add
           *
           * @method print_pro_section
           */
          public function print_pro_section() {
            $icon = function ($name) {
              return \peproulitmateinvoice\PeproUltimateInvoice_Icons::get($name);
            };
            $features = array(
              array("gift",            _x("Credit notes and refund documents", "wc-setting", "pepro-ultimate-invoice"),  _x("Issue a numbered credit note against any order, with its own series and its own template.", "wc-setting", "pepro-ultimate-invoice")),
              array("files",           _x("Bulk export to one archive", "wc-setting", "pepro-ultimate-invoice"),         _x("Pick a date range and get every invoice in it as one PDF or one zip, ready for the accountant.", "wc-setting", "pepro-ultimate-invoice")),
              array("globe",           _x("Official e-invoicing", "wc-setting", "pepro-ultimate-invoice"),               _x("Submit invoices to the tax authority and keep the reference number on the order.", "wc-setting", "pepro-ultimate-invoice")),
              array("paint-brush",     _x("More templates", "wc-setting", "pepro-ultimate-invoice"),                     _x("A growing library of invoice, slip and label designs, installed from inside the plugin.", "wc-setting", "pepro-ultimate-invoice")),
              array("plugs-connected", _x("Add-ons", "wc-setting", "pepro-ultimate-invoice"),                            _x("Dokan, WCFM, subscriptions and shipping carriers, each as a separate small add-on.", "wc-setting", "pepro-ultimate-invoice")),
              array("clock-counter-clockwise", _x("Priority support", "wc-setting", "pepro-ultimate-invoice"),           _x("A direct line to the people who wrote the plugin, with a response time we commit to.", "wc-setting", "pepro-ultimate-invoice")),
            );
            ob_start();
            ?>
            <div class="puiw-pro">
              <div class="puiw-pro-hero">
                <span class="puiw-badge is-pro"><?php echo $icon("crown-simple"); ?><?php echo esc_html(_x("PRO", "wc-setting", "pepro-ultimate-invoice")); ?></span>
                <h2><?php echo _x("PRO &amp; Add-ons", "wc-setting", "pepro-ultimate-invoice"); ?></h2>
                <p><?php echo _x("Everything here is being built. The free plugin stays free and keeps every feature it has today.", "wc-setting", "pepro-ultimate-invoice"); ?></p>
                <span class="puiw-pro-soon"><?php echo esc_html(_x("Coming soon", "wc-setting", "pepro-ultimate-invoice")); ?></span>
              </div>
              <div class="puiw-pro-grid">
                <?php foreach ($features as $feature) { ?>
                  <div class="puiw-pro-card">
                    <span class="puiw-pro-icon"><?php echo $icon($feature[0]); ?></span>
                    <strong><?php echo esc_html($feature[1]); ?></strong>
                    <p><?php echo esc_html($feature[2]); ?></p>
                  </div>
                <?php } ?>
              </div>
              <p class="puiw-pro-foot">
                <a class="button button-primary" href="https://peprodev.com/pepro-woocommerce-ultimate-invoice/" target="_blank" rel="noopener">
                  <?php echo $icon("arrow-square-out"); ?>
                  <span><?php echo esc_html(_x("Tell me when it is ready", "wc-setting", "pepro-ultimate-invoice")); ?></span>
                </a>
              </p>
            </div>
            <?php
            $html_output = ob_get_contents();
            ob_end_clean();
            echo apply_filters("puiw_section_pro_html", $html_output);
          }
          /**
           * per language invoice texts, template and settings
           *
           * @method print_translations_section
           */
          public function print_translations_section() {
            global $PeproUltimateInvoice;
            $i18n      = $PeproUltimateInvoice->i18n;
            $languages = $i18n->get_languages();
            $default   = $i18n->default_language();
            $templates = $PeproUltimateInvoice->load_themes(1);
            $parts     = $this->translation_parts($i18n->scan_template());
            ob_start();
            ?>
            <h2><?php echo _x("Invoice Translations", "wc-setting", "pepro-ultimate-invoice"); ?></h2>
            <p class="puiw-panel-intro">
              <?php echo _x("Every text printed on the invoice is listed below. Fill in the languages you sell in and the invoice will switch to them on its own, following Polylang when it is active.", "wc-setting", "pepro-ultimate-invoice"); ?>
            </p>
            <?php if (count($languages) < 2) { ?>
              <div class="puiw-notice">
                <?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("warning-circle"); ?>
                <span><?php echo _x("Only one language is installed. Install another language in Settings &rsaquo; General, or activate Polylang, to translate invoices.", "wc-setting", "pepro-ultimate-invoice"); ?></span>
              </div>
            <?php } ?>
            <?php do_action("puiw_translations_before", $this); ?>
            <div class="puiw-lang-tabs">
              <?php $first = true; foreach ($languages as $code => $lang) { ?>
                <a href="#" class="puiw-lang-tab<?php echo $first ? " is-active" : ""; ?>" data-lang="<?php echo esc_attr($code); ?>">
                  <?php echo esc_html($lang["name"]); ?>
                  <?php if ($code === $default) { ?><em><?php echo esc_html(_x("default", "wc-setting", "pepro-ultimate-invoice")); ?></em><?php } ?>
                </a>
              <?php $first = false; } ?>
            </div>
            <?php $first = true; foreach ($languages as $code => $lang) {
              $saved    = $i18n->get_strings($code);
              $per_lang = $i18n->get_settings($code);
              ?>
              <div class="puiw-lang-pane<?php echo $first ? " is-active" : ""; ?>" data-lang="<?php echo esc_attr($code); ?>">
                <div class="puiw-tabs puiw-strings-tabs">
                  <div class="puiw-tabbar" role="tablist">
                    <button type="button" class="puiw-tab is-active" role="tab" data-tab="general" aria-selected="true"><?php echo esc_html(_x("Template and settings", "wc-setting", "pepro-ultimate-invoice")); ?></button>
                    <?php foreach ($parts as $part => $info) { ?>
                      <button type="button" class="puiw-tab" role="tab" data-tab="<?php echo esc_attr($part); ?>" aria-selected="false"><?php echo esc_html($info["label"]); ?> <span class="puiw-tab-count"><?php echo esc_html(number_format_i18n($info["count"])); ?></span></button>
                    <?php } ?>
                  </div>
                  <div class="puiw-tabpane is-active" role="tabpanel" data-tab="general">
                    <div class="puiw-field">
                      <div class="puiw-field-label"><label for="puiw-lt-<?php echo esc_attr($code); ?>"><?php echo _x("Invoice Template", "wc-setting", "pepro-ultimate-invoice"); ?></label></div>
                      <div class="puiw-field-input">
                        <select id="puiw-lt-<?php echo esc_attr($code); ?>" name="puiw_lang_template[<?php echo esc_attr($code); ?>]">
                          <option value=""><?php echo esc_html(_x("Use the template from Theming", "wc-setting", "pepro-ultimate-invoice")); ?></option>
                          <?php
                          $chosen = $i18n->get_template_for($code);
                          foreach ((array) $templates as $path => $theme) {
                            $title = isset($theme["title"]) ? $theme["title"] : basename($path);
                            printf('<option value="%1$s" %3$s>%2$s</option>', esc_attr($path), esc_html($title), selected($chosen, $path, false));
                          }
                          ?>
                        </select>
                      </div>
                    </div>
                    <?php foreach ($i18n->option_keys() as $key) {
                      $value = isset($per_lang[$key]) ? $per_lang[$key] : ""; ?>
                      <div class="puiw-field">
                        <div class="puiw-field-label"><label for="puiw-ls-<?php echo esc_attr($code . "-" . $key); ?>"><?php echo esc_html($i18n->option_label($key)); ?></label></div>
                        <div class="puiw-field-input">
                          <input type="text" id="puiw-ls-<?php echo esc_attr($code . "-" . $key); ?>" name="puiw_lang_settings[<?php echo esc_attr($code); ?>][<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>" placeholder="<?php echo esc_attr(get_option($key, "")); ?>" />
                        </div>
                      </div>
                    <?php } ?>
                  </div>
                  <?php foreach ($parts as $part => $info) { ?>
                    <div class="puiw-tabpane" role="tabpanel" data-tab="<?php echo esc_attr($part); ?>" hidden>
                      <div class="puiw-strings-search">
                        <?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("magnifying-glass"); ?>
                        <input type="search" class="puiw-strings-filter" placeholder="<?php echo esc_attr(_x("Search…", "wc-setting", "pepro-ultimate-invoice")); ?>" aria-label="<?php echo esc_attr(_x("Search…", "wc-setting", "pepro-ultimate-invoice")); ?>" />
                      </div>
                      <?php foreach ($info["files"] as $file => $texts) { ?>
                        <section class="puiw-strings-group" data-file="<?php echo esc_attr($file); ?>">
                          <h4 class="puiw-strings-file"><span dir="ltr"><?php echo esc_html($file); ?></span> <span class="puiw-tab-count"><?php echo esc_html(number_format_i18n(count($texts))); ?></span></h4>
                          <?php foreach ($texts as $text) { ?>
                            <div class="puiw-field puiw-string-row">
                              <?php // a label written in English shows what it prints as today, so it can be found in Persian too
                              $now = translate($text, "pepro-ultimate-invoice"); ?>
                              <div class="puiw-field-label"><code><?php echo esc_html($text); ?></code><?php if ($now !== $text) { ?><span class="puiw-string-hint"><?php echo esc_html($now); ?></span><?php } ?></div>
                              <div class="puiw-field-input">
                                <input type="text" dir="auto" name="puiw_lang_strings[<?php echo esc_attr($code); ?>][<?php echo esc_attr($text); ?>]" value="<?php echo esc_attr(isset($saved[$text]) ? $saved[$text] : ""); ?>" placeholder="<?php echo esc_attr($text); ?>" aria-label="<?php echo esc_attr($text); ?>" />
                              </div>
                            </div>
                          <?php } ?>
                        </section>
                      <?php } ?>
                    </div>
                  <?php } ?>
                </div>
              </div>
            <?php $first = false; } ?>
            <?php
            $html_output = ob_get_contents();
            ob_end_clean();
            echo apply_filters("puiw_section_translations_html", $html_output);
          }
          /**
           * every panel and every setting on one page, searchable, each a link to the
           * field itself
           *
           * @method print_settings_overview
           * @param  array $sections slug => label
           * @param  array $field_panels the panels drawn from field arrays
           */
          public function print_settings_overview($sections, $field_panels) {
            $icon = function ($name) {
              return \peproulitmateinvoice\PeproUltimateInvoice_Icons::get($name);
            };
            $about = array(
              "color"     => _x("Saved sets of the three invoice colours", "wc-setting", "pepro-ultimate-invoice"),
              "strings"   => _x("Invoice texts, template and settings per language", "wc-setting", "pepro-ultimate-invoice"),
              "templates" => _x("Edit or copy the template files", "wc-setting", "pepro-ultimate-invoice"),
              "migrate"   => _x("Export, import, reset and the setup wizard", "wc-setting", "pepro-ultimate-invoice"),
              "changelog" => _x("What changed in each version", "wc-setting", "pepro-ultimate-invoice"),
              "devdocs"   => _x("Hooks, shortcodes and URLs for developers", "wc-setting", "pepro-ultimate-invoice"),
              "debug"     => _x("Server checks and a report for support", "wc-setting", "pepro-ultimate-invoice"),
              "pro"       => _x("The PRO modules and their state", "wc-setting", "pepro-ultimate-invoice"),
            );
            $skip  = array("title", "sectionend", "puiw_skip", "puiw_note", "puiw_html");
            $total = 0;
            ob_start();
            ?>
            <h2><?php echo esc_html(_x("All settings", "wc-setting", "pepro-ultimate-invoice")); ?></h2>
            <p class="puiw-panel-intro"><?php echo esc_html(_x("Every part of the settings on one page. Search for a setting and click it to go straight to it.", "wc-setting", "pepro-ultimate-invoice")); ?></p>
            <div class="puiw-strings-search puiw-ov-search">
              <?php echo $icon("magnifying-glass"); ?>
              <input type="search" class="puiw-ov-filter" placeholder="<?php echo esc_attr(_x("Search…", "wc-setting", "pepro-ultimate-invoice")); ?>" aria-label="<?php echo esc_attr(_x("Search…", "wc-setting", "pepro-ultimate-invoice")); ?>" />
              <span class="puiw-ov-count"></span>
            </div>
            <div class="puiw-ov-grid">
              <?php foreach ($sections as $slug => $label) {
                if (in_array($slug, array("dashboard", "allsettings"), true)) {
                  continue;
                }
                $items = array();
                if (in_array($slug, $field_panels, true)) {
                  $tabs = array();
                  foreach ((array) $this->get_settings($slug) as $field) {
                    if (!empty($field["puiw_tabs"])) {
                      $tabs = (array) $field["puiw_tabs"];
                    }
                    $type = isset($field["type"]) ? $field["type"] : "";
                    if (empty($field["id"]) || in_array($type, $skip, true) || empty($field["name"])) {
                      continue;
                    }
                    $tab = isset($field["puiw_tab"], $tabs[$field["puiw_tab"]]) ? $field["puiw_tab"] : "";
                    $items[] = array(
                      "id"   => $field["id"],
                      "name" => wp_strip_all_tags($field["name"]),
                      "tab"  => $tab,
                      "tabl" => $tab ? wp_strip_all_tags($tabs[$tab]) : "",
                      "desc" => wp_strip_all_tags(isset($field["desc"]) && is_string($field["desc"]) ? $field["desc"] : ""),
                    );
                  }
                }
                $total += count($items);
                ?>
                <section class="puiw-ov-card" data-panel-card="<?php echo esc_attr($slug); ?>">
                  <a class="puiw-ov-head" href="#puiw-<?php echo esc_attr($slug); ?>" data-panel="<?php echo esc_attr($slug); ?>">
                    <?php echo $icon($this->section_icon($slug)); ?>
                    <span><?php echo wp_kses_post($label); ?></span>
                    <?php if ($items) { ?><span class="puiw-tab-count"><?php echo esc_html(number_format_i18n(count($items))); ?></span><?php } ?>
                  </a>
                  <?php if ($items) { ?>
                    <ul class="puiw-ov-list">
                      <?php foreach ($items as $item) { ?>
                        <li class="puiw-ov-item" data-search="<?php echo esc_attr(strtolower($item["name"] . " " . $item["tabl"] . " " . $item["desc"] . " " . $item["id"])); ?>">
                          <a href="#puiw-<?php echo esc_attr($slug); ?>" data-panel="<?php echo esc_attr($slug); ?>" data-goto="<?php echo esc_attr($item["id"]); ?>" data-tab="<?php echo esc_attr($item["tab"]); ?>">
                            <span class="puiw-ov-name"><?php echo esc_html($item["name"]); ?></span>
                            <?php if ($item["tabl"]) { ?><span class="puiw-ov-tab"><?php echo esc_html($item["tabl"]); ?></span><?php } ?>
                          </a>
                        </li>
                      <?php } ?>
                    </ul>
                  <?php } else { ?>
                    <p class="puiw-ov-about" data-search="<?php echo esc_attr(strtolower(wp_strip_all_tags($label) . " " . (isset($about[$slug]) ? $about[$slug] : ""))); ?>"><?php echo esc_html(isset($about[$slug]) ? $about[$slug] : ""); ?></p>
                  <?php } ?>
                </section>
              <?php } ?>
            </div>
            <p class="puiw-switchboard-empty puiw-ov-empty" hidden><?php echo esc_html(_x("Nothing matches that.", "wc-setting", "pepro-ultimate-invoice")); ?></p>
            <?php
            $html = ob_get_clean();
            echo apply_filters("puiw_section_allsettings_html", str_replace("<span class=\"puiw-ov-count\"></span>", sprintf('<span class="puiw-ov-count" data-total="%1$d">%2$s</span>', $total, esc_html(number_format_i18n($total))), $html));
          }
          /**
           * template texts grouped by what they print on, each text listed once: the
           * first file it shows up in, in the order invoice, slips, inventory, other
           *
           * @method translation_parts
           * @param  array $strings file => texts
           * @return array part => label, count, files
           */
          protected function translation_parts($strings) {
            $parts = array(
              "invoice"   => array("label" => _x("Invoice", "wc-setting", "pepro-ultimate-invoice"), "files" => array()),
              "slips"     => array("label" => _x("Packing slips", "wc-setting", "pepro-ultimate-invoice"), "files" => array()),
              "inventory" => array("label" => _x("Inventory report", "wc-setting", "pepro-ultimate-invoice"), "files" => array()),
              "other"     => array("label" => _x("Other", "wc-setting", "pepro-ultimate-invoice"), "files" => array()),
            );
            foreach ((array) $strings as $file => $texts) {
              $part = false !== strpos($file, "slips") ? "slips" : (false !== strpos($file, "inventory") ? "inventory" : (0 === strpos($file, "template") ? "invoice" : "other"));
              $parts[$part]["files"][$file] = (array) $texts;
            }
            $seen = array();
            foreach ($parts as $part => $info) {
              foreach ($info["files"] as $file => $texts) {
                $keep = array();
                foreach ($texts as $text) {
                  if (!isset($seen[$text])) {
                    $seen[$text] = true;
                    $keep[] = $text;
                  }
                }
                if ($keep) {
                  $parts[$part]["files"][$file] = $keep;
                } else {
                  unset($parts[$part]["files"][$file]);
                }
              }
              $parts[$part]["count"] = array_sum(array_map("count", $parts[$part]["files"]));
              if (!$parts[$part]["count"]) {
                unset($parts[$part]);
              }
            }
            return $parts;
          }
          /**
           * what PHP gives us, what the shop looks like, and a report to paste to support
           *
           * @method print_debug_section
           */
          public function print_debug_section() {
            $icon = function ($name) {
              return \peproulitmateinvoice\PeproUltimateInvoice_Icons::get($name);
            };
            $requirements = $this->debug_requirements();
            $environment  = $this->debug_environment();
            $conflicts    = $this->asset_conflicts();
            $missing      = 0;
            foreach ($requirements as $row) {
              if (!$row["ok"]) {
                $missing++;
              }
            }
            ob_start();
            ?>
            <h2><?php echo _x("Diagnostics", "wc-setting", "pepro-ultimate-invoice"); ?></h2>
            <p class="puiw-panel-intro"><?php echo _x("What this server gives the plugin to work with. If something is not behaving, copy the report at the bottom and send it to us with your message.", "wc-setting", "pepro-ultimate-invoice"); ?></p>

            <?php if ($missing) { ?>
              <div class="puiw-notice">
                <?php echo $icon("warning-circle"); ?>
                <span><?php printf(esc_html(_n("%d requirement is not met on this server.", "%d requirements are not met on this server.", $missing, "pepro-ultimate-invoice")), $missing); ?></span>
              </div>
            <?php } else { ?>
              <div class="puiw-notice is-ok">
                <?php echo $icon("check-circle"); ?>
                <span><?php echo esc_html(_x("Every requirement is met on this server.", "wc-setting", "pepro-ultimate-invoice")); ?></span>
              </div>
            <?php } ?>

            <?php if ($conflicts) { ?>
              <h3><?php echo esc_html(_x("Admin assets", "wc-setting", "pepro-ultimate-invoice")); ?></h3>
              <p class="puiw-panel-intro"><?php echo esc_html(_x("Another plugin is changing the scripts this page loads. That is enough on its own to stop any WooCommerce settings screen from working.", "wc-setting", "pepro-ultimate-invoice")); ?></p>
              <div class="puiw-diag">
                <?php foreach ($conflicts as $conflict) { ?>
                  <div class="puiw-diag-row is-bad">
                    <span class="puiw-diag-icon"><?php echo $icon("warning-circle"); ?></span>
                    <span class="puiw-diag-value"><?php echo esc_html($conflict); ?></span>
                  </div>
                <?php } ?>
              </div>
            <?php } ?>

            <h3><?php echo esc_html(_x("PHP requirements", "wc-setting", "pepro-ultimate-invoice")); ?></h3>
            <div class="puiw-diag">
              <?php foreach ($requirements as $row) { ?>
                <div class="puiw-diag-row is-<?php echo $row["ok"] ? "ok" : "bad"; ?>">
                  <span class="puiw-diag-icon"><?php echo $icon($row["ok"] ? "check-circle" : "x-circle"); ?></span>
                  <span class="puiw-diag-key"><code dir="ltr"><?php echo esc_html($row["key"]); ?></code></span>
                  <span class="puiw-diag-value">
                    <?php echo esc_html($row["ok"] ? _x("available", "wc-setting", "pepro-ultimate-invoice") : _x("missing", "wc-setting", "pepro-ultimate-invoice")); ?>
                    <em><?php echo esc_html($row["hint"]); ?></em>
                  </span>
                </div>
              <?php } ?>
            </div>

            <h3><?php echo esc_html(_x("Environment", "wc-setting", "pepro-ultimate-invoice")); ?></h3>
            <div class="puiw-diag">
              <?php foreach ($environment as $label => $value) { ?>
                <div class="puiw-diag-row">
                  <span class="puiw-diag-key"><?php echo esc_html($label); ?></span>
                  <span class="puiw-diag-value"><bdi dir="auto"><?php echo esc_html($value); ?></bdi></span>
                </div>
              <?php } ?>
            </div>

            <h3><?php echo esc_html(_x("Support report", "wc-setting", "pepro-ultimate-invoice")); ?></h3>
            <p class="puiw-panel-intro"><?php echo esc_html(_x("Nothing here identifies your customers. It lists versions, extensions and the plugin's own settings that affect rendering.", "wc-setting", "pepro-ultimate-invoice")); ?></p>
            <div class="puiw-report">
              <textarea id="puiw-support-report" readonly rows="14" dir="ltr" spellcheck="false"><?php echo esc_textarea($this->debug_report()); ?></textarea>
              <p class="puiw-card-actions">
                <button type="button" class="button button-primary puiw-copy" data-copy="#puiw-support-report">
                  <?php echo $icon("copy-simple"); ?>
                  <span><?php echo esc_html(_x("Copy the report", "wc-setting", "pepro-ultimate-invoice")); ?></span>
                </button>
                <a class="button" href="<?php echo esc_url("mailto:support@peprodev.com?subject=" . rawurlencode("Ultimate Invoice " . $GLOBALS["PeproUltimateInvoice"]->version)); ?>">
                  <?php echo $icon("envelope-simple"); ?>
                  <span><?php echo esc_html(_x("E-mail support", "wc-setting", "pepro-ultimate-invoice")); ?></span>
                </a>
              </p>
            </div>
            <?php
            $html_output = ob_get_contents();
            ob_end_clean();
            echo apply_filters("puiw_section_debug_html", $html_output);
          }
          /**
           * the PHP pieces the plugin needs, with a hint when one is missing
           *
           * @method debug_requirements
           * @return array
           */
          public function debug_requirements() {
            $needs = apply_filters("puiw_debug_list_items", array(
              "mbstring"          => "extension",
              "mbregex"           => "extension",
              "mb_regex_encoding" => "function",
              "gd"                => "extension",
              "zlib"              => "extension",
              "bcmath"            => "extension",
              "xml"               => "extension",
              "curl"              => "extension",
              "zip"               => "extension",
            ));
            $hints = array(
              "mbstring"          => _x("PDF files cannot be built without it.", "wc-setting", "pepro-ultimate-invoice"),
              "mbregex"           => _x("mbstring was compiled without regex support, PDF text and images fail.", "wc-setting", "pepro-ultimate-invoice"),
              "mb_regex_encoding" => _x("Comes with mbstring regex support.", "wc-setting", "pepro-ultimate-invoice"),
              "gd"                => _x("Draws barcodes and converts WebP images for PDF output.", "wc-setting", "pepro-ultimate-invoice"),
              "zlib"              => _x("Compresses the generated PDF files.", "wc-setting", "pepro-ultimate-invoice"),
              "bcmath"            => _x("Precise price calculations.", "wc-setting", "pepro-ultimate-invoice"),
              "xml"               => _x("Parses the invoice templates.", "wc-setting", "pepro-ultimate-invoice"),
              "curl"              => _x("Downloads remote images used in invoices.", "wc-setting", "pepro-ultimate-invoice"),
              "zip"               => _x("Packs and unpacks template archives.", "wc-setting", "pepro-ultimate-invoice"),
            );
            $out = array();
            foreach ($needs as $key => $kind) {
              $out[] = array(
                "key"  => $key,
                "ok"   => "extension" === $kind ? extension_loaded($key) : function_exists($key),
                "hint" => isset($hints[$key]) ? $hints[$key] : "",
              );
            }
            return $out;
          }
          /**
           * versions and shop facts worth knowing when something misbehaves
           *
           * @method debug_environment
           * @return array
           */
          public function debug_environment() {
            global $PeproUltimateInvoice, $wpdb;
            $uploads = wp_upload_dir();
            $hpos    = class_exists("\Automattic\WooCommerce\Utilities\OrderUtil")
              && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            return apply_filters("puiw_debug_environment", array(
              _x("Plugin version", "wc-setting", "pepro-ultimate-invoice")      => $PeproUltimateInvoice->version,
              _x("WordPress version", "wc-setting", "pepro-ultimate-invoice")   => get_bloginfo("version"),
              _x("WooCommerce version", "wc-setting", "pepro-ultimate-invoice") => defined("WC_VERSION") ? WC_VERSION : "-",
              _x("PHP version", "wc-setting", "pepro-ultimate-invoice")         => PHP_VERSION,
              _x("Database", "wc-setting", "pepro-ultimate-invoice")            => $wpdb->db_version(),
              _x("Server", "wc-setting", "pepro-ultimate-invoice")              => isset($_SERVER["SERVER_SOFTWARE"]) ? sanitize_text_field(wp_unslash($_SERVER["SERVER_SOFTWARE"])) : "-",
              _x("Memory limit", "wc-setting", "pepro-ultimate-invoice")        => (string) ini_get("memory_limit"),
              _x("Max execution time", "wc-setting", "pepro-ultimate-invoice")  => (string) ini_get("max_execution_time"),
              _x("Locale", "wc-setting", "pepro-ultimate-invoice")              => get_locale() . (is_rtl() ? " (RTL)" : " (LTR)"),
              _x("High-Performance Order Storage", "wc-setting", "pepro-ultimate-invoice") => $hpos ? __("Enabled", "pepro-ultimate-invoice") : __("Disabled", "pepro-ultimate-invoice"),
              _x("Active theme", "wc-setting", "pepro-ultimate-invoice")        => wp_get_theme()->get("Name") . " " . wp_get_theme()->get("Version"),
              _x("Active plugins", "wc-setting", "pepro-ultimate-invoice")      => (string) count((array) get_option("active_plugins", array())),
              _x("Invoice template", "wc-setting", "pepro-ultimate-invoice")    => basename($PeproUltimateInvoice->tpl->get_template()),
              _x("Uploads writable", "wc-setting", "pepro-ultimate-invoice")    => wp_is_writable($uploads["basedir"]) ? __("Yes", "pepro-ultimate-invoice") : __("No", "pepro-ultimate-invoice"),
              _x("Temporary folder writable", "wc-setting", "pepro-ultimate-invoice") => wp_is_writable(trailingslashit($uploads["basedir"]) . "puiw-temp") ? __("Yes", "pepro-ultimate-invoice") : __("No", "pepro-ultimate-invoice"),
              _x("Bulk actions registered", "wc-setting", "pepro-ultimate-invoice") => get_transient("puiw_bulk_actions_present") ? __("Yes", "pepro-ultimate-invoice") : __("Not detected yet", "pepro-ultimate-invoice"),
              _x("WP_DEBUG", "wc-setting", "pepro-ultimate-invoice")            => (defined("WP_DEBUG") && WP_DEBUG) ? __("Enabled", "pepro-ultimate-invoice") : __("Disabled", "pepro-ultimate-invoice"),
            ));
          }
          /**
           * one plain text block a shop owner can paste into a support message
           *
           * @method debug_report
           * @return string
           */
          public function debug_report() {
            global $PeproUltimateInvoice;
            $lines = array("### PeproDev Ultimate Invoice support report", "");

            $lines[] = "[environment]";
            foreach ($this->debug_environment() as $label => $value) {
              $lines[] = sprintf("%-34s %s", wp_strip_all_tags($label) . ":", wp_strip_all_tags((string) $value));
            }

            $lines[] = "";
            $lines[] = "[php]";
            foreach ($this->debug_requirements() as $row) {
              $lines[] = sprintf("%-34s %s", $row["key"] . ":", $row["ok"] ? "ok" : "MISSING");
            }

            $lines[] = "";
            $lines[] = "[templates]";
            foreach ((array) $PeproUltimateInvoice->load_themes(1) as $path => $theme) {
              $lines[] = sprintf("%-34s %s %s", basename($path) . ":", $theme["name"], $theme["version"]);
            }

            // the settings that decide what an invoice looks like, no customer data
            $lines[] = "";
            $lines[] = "[settings]";
            $keys = apply_filters("puiw_debug_report_settings", array(
              "puiw_template", "puiw_preinvoice_template", "puiw_pdf_font", "puiw_pdf_size",
              "puiw_pdf_orientation", "puiw_date_shamsi", "puiw_date_format", "puiw_force_persian_numbers", "puiw_invoice_language", "puiw_template_direction",
              "puiw_show_qr_code_id", "puiw_show_invoices_id_barcode", "puiw_allow_pdf_customer",
              "puiw_allow_users_use_invoices", "puiw_allow_guest_users_view_invoices",
              "puiw_attach_pdf_invoices_to_mail", "puiw_send_invoices_via_email",
            ));
            foreach ($keys as $key) {
              $value = get_option($key, "");
              if (is_array($value)) {
                $value = implode(", ", $value);
              }
              if ("puiw_template" === $key || "puiw_preinvoice_template" === $key) {
                $value = basename((string) $value);
              }
              $lines[] = sprintf("%-34s %s", $key . ":", (string) $value);
            }

            $conflicts = $this->asset_conflicts();
            if ($conflicts) {
              $lines[] = "";
              $lines[] = "[admin assets]";
              foreach ($conflicts as $conflict) {
                $lines[] = "- " . wp_strip_all_tags($conflict);
              }
            }

            $lines[] = "";
            $lines[] = "[active plugins]";
            if (!function_exists("get_plugins")) {
              include_once ABSPATH . "wp-admin/includes/plugin.php";
            }
            $all = function_exists("get_plugins") ? get_plugins() : array();
            foreach ((array) get_option("active_plugins", array()) as $plugin) {
              $name = isset($all[$plugin]["Name"]) ? $all[$plugin]["Name"] : $plugin;
              $ver  = isset($all[$plugin]["Version"]) ? $all[$plugin]["Version"] : "";
              $lines[] = trim("- $name $ver");
            }

            return implode("\n", apply_filters("puiw_debug_report_lines", $lines));
          }
          /**
           * printed before anything the page enqueues: it marks the document as
           * script-capable, keeps the first error of the load, and puts back the global
           * WooCommerce passes into its own admin bundle by reference
           *
           * @method print_boot_guard
           * @version 1.0.0
           * @since 3.2.1
           */
          public function print_boot_guard() {
            ?>
<script id="puiw-boot-guard">
(function () {
  document.documentElement.className += " puiw-js";
  window.puiwBoot = { booted: false, errors: [] };
  window.addEventListener("error", function (e) {
    if (window.puiwBoot.errors.length > 4) { return; }
    window.puiwBoot.errors.push({ message: e.message || "", file: e.filename || "", line: e.lineno || 0 });
  }, true);
  /* woocommerce_admin.js takes its data as a call argument, so a dropped localisation
     throws before the file's own typeof guard is reached, and on sites that bundle the
     admin scripts together every script after it goes down with it */
  window.woocommerce_admin = window.woocommerce_admin || { urls: {}, strings: {}, nonces: {} };

  function report() {
    var box = document.getElementById("puiw-bootcheck");
    if (!box || window.puiwBoot.booted) { return; }
    var err  = null;
    for (var n = 0; n < window.puiwBoot.errors.length; n++) {
      /* a script served from another origin only ever reports "Script error.", so
         keep looking for one that names something */
      if (window.puiwBoot.errors[n].message) { err = window.puiwBoot.errors[n]; break; }
    }
    var note = box.querySelector(".puiw-bootcheck-msg");
    var code = box.querySelector(".puiw-bootcheck-err");
    var copy = box.querySelector(".puiw-bootcheck-copy");
    var text = window.jQuery ? box.getAttribute("data-msg-broken") : box.getAttribute("data-msg-nojquery");
    if (note) { note.textContent = text; }
    if (err && code) {
      code.textContent = err.message + (err.file ? "\n" + err.file + ":" + err.line : "");
      code.hidden = false;
    }
    if (copy) {
      copy.addEventListener("click", function () {
        var out = [];
        for (var i = 0; i < window.puiwBoot.errors.length; i++) {
          var e = window.puiwBoot.errors[i];
          out.push(e.message + (e.file ? " @ " + e.file + ":" + e.line : ""));
        }
        out.push(location.href, navigator.userAgent);
        var payload = out.join("\n");
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(payload);
        } else {
          window.prompt("", payload);
        }
        copy.textContent = copy.getAttribute("data-done");
      });
    }
    box.className += " is-visible";
  }
  document.addEventListener("DOMContentLoaded", function () { window.setTimeout(report, 2500); });
})();
</script>
            <?php
          }
          /**
           * a plugin that re-registers a handle another plugin already enqueued silently
           * drops the data wp_localize_script put on it, which is what takes a settings
           * screen down without leaving anything in the PHP log
           *
           * @method asset_conflicts
           * @return array
           * @version 1.0.0
           * @since 3.2.1
           */
          public function asset_conflicts() {
            $out = array();
            if (!function_exists("wp_scripts")) {
              return $out;
            }
            $scripts = wp_scripts();
            $watch   = array(
              "woocommerce_admin"                 => "woocommerce_admin",
              "pepro-ultimate-invoice-wc-setting" => "_peproUltimateInvoice",
              "color-scheme.js"                   => "msg",
              "migrate-backup.js"                 => "msg",
            );
            foreach ($watch as $handle => $object) {
              if (!isset($scripts->registered[$handle])) {
                continue;
              }
              $data = $scripts->get_data($handle, "data");
              if (!is_string($data) || false === strpos($data, $object)) {
                $out[] = sprintf(
                  /* translators: 1: script handle, 2: javascript variable name */
                  _x("%1\$s lost its %2\$s data before the page was printed.", "wc-setting", "pepro-ultimate-invoice"),
                  $handle,
                  $object
                );
              }
            }
            if (isset($scripts->registered["woocommerce_admin"]) && defined("WC_VERSION")) {
              $ver = (string) $scripts->registered["woocommerce_admin"]->ver;
              if ("" !== $ver && WC_VERSION !== $ver) {
                $out[] = sprintf(
                  /* translators: 1: version string found on the asset, 2: WooCommerce version */
                  _x("WooCommerce admin assets are being served as version %1\$s instead of %2\$s, so something is re-registering them.", "wc-setting", "pepro-ultimate-invoice"),
                  $ver,
                  WC_VERSION
                );
              }
            }
            if ($out) {
              $late = $this->late_asset_hooks();
              if ($late) {
                $out[] = sprintf(
                  /* translators: %s: comma separated list of plugin folder names */
                  _x("Touching the admin queue after WooCommerce on this screen: %s.", "wc-setting", "pepro-ultimate-invoice"),
                  implode(", ", $late)
                );
              }
            }
            return apply_filters("puiw_asset_conflicts", $out);
          }
          /**
           * which plugins get at admin_enqueue_scripts after WooCommerce has filled it,
           * so a support report points somewhere instead of just saying something is wrong
           *
           * @method late_asset_hooks
           * @return array
           * @version 1.0.0
           * @since 3.2.2
           */
          public function late_asset_hooks() {
            global $wp_filter;
            $found = array();
            if (empty($wp_filter["admin_enqueue_scripts"]) || !class_exists("ReflectionFunction")) {
              return $found;
            }
            $roots = array_filter(array(
              defined("WP_PLUGIN_DIR") ? WP_PLUGIN_DIR : "",
              defined("WPMU_PLUGIN_DIR") ? WPMU_PLUGIN_DIR : "",
            ));
            foreach ((array) $wp_filter["admin_enqueue_scripts"]->callbacks as $priority => $callbacks) {
              // WooCommerce fills the queue at the default priority
              if ((int) $priority <= 10) {
                continue;
              }
              foreach ((array) $callbacks as $callback) {
                $fn   = isset($callback["function"]) ? $callback["function"] : null;
                $file = "";
                try {
                  if (is_array($fn) && isset($fn[0], $fn[1])) {
                    $ref = new \ReflectionMethod(is_object($fn[0]) ? get_class($fn[0]) : (string) $fn[0], (string) $fn[1]);
                  } elseif ($fn instanceof \Closure || (is_string($fn) && function_exists($fn))) {
                    $ref = new \ReflectionFunction($fn);
                  } else {
                    continue;
                  }
                  $file = (string) $ref->getFileName();
                } catch (\Throwable $e) {
                  continue;
                }
                foreach ($roots as $root) {
                  if ("" === $file || 0 !== strpos($file, $root)) {
                    continue;
                  }
                  $slug = current(explode("/", ltrim(str_replace($root, "", $file), "/\\")));
                  if (in_array($slug, array("", "woocommerce", "pepro-ultimate-invoice"), true)) {
                    continue;
                  }
                  $found[$slug] = $slug;
                }
              }
            }
            sort($found);
            return $found;
          }
          /**
           * the choices for Invoice language: the two automatic ones, then every
           * language installed in WordPress by its own name
           *
           * @method invoice_language_options
           * @return array
           */
          protected function invoice_language_options() {
            $options = array(
              "auto" => _x("Automatic: a left to right template prints in English", "wc-setting", "pepro-ultimate-invoice"),
              "site" => _x("The order's language", "wc-setting", "pepro-ultimate-invoice"),
              "en"   => sprintf(_x("Always %s", "wc-setting", "pepro-ultimate-invoice"), "English (United States)"),
            );
            // wp_get_available_translations() would call wordpress.org on every page load
            foreach (get_available_languages() as $locale) {
              $name = class_exists("Locale") ? \Locale::getDisplayName($locale, $locale) : "";
              $options[$locale] = sprintf(_x("Always %s", "wc-setting", "pepro-ultimate-invoice"), $name ? "{$name} ({$locale})" : $locale);
            }
            return $options;
          }
          /**
           * the schemes every shop has; the editor shows them locked
           *
           * @method built_in_swatches
           * @return array
           */
          public static function built_in_swatches() {
            return array(
              array("n" => "Smoke", "p" => "#9E9E9E", "s" => "#A6A6A6", "t" => "#B3B3B3"),
              array("n" => "Mango", "p" => "#FFCC80", "s" => "#FCD59A", "t" => "#FFDFB0"),
              array("n" => "Gold",  "p" => "#FAEE84", "s" => "#FFF59D", "t" => "#FFF8B5"),
              array("n" => "Grass", "p" => "#A5D6A7", "s" => "#AFE0B1", "t" => "#C3EBC5"),
              array("n" => "Sea",   "p" => "#90CAF9", "s" => "#A6D5FC", "t" => "#B5DEFF"),
              array("n" => "Peach", "p" => "#EF9A9A", "s" => "#F5ABAB", "t" => "#F0BBBB"),
            );
          }
          public function print_color_schemes() {
            ob_start();
            ?>
            <h2><?php echo _x("Color Schemes", "wc-setting", "pepro-ultimate-invoice"); ?></h2>
            <p class="puiw-panel-intro"><?php echo _x("Saved sets of the three invoice colours, offered as cards under Theming. The built-in sets cannot be changed; add your own beside them. Changes are kept with the Save changes button at the top.", "wc-setting", "pepro-ultimate-invoice"); ?></p>
            <div class="puiw-schemes">
              <textarea id="puiw_color_schemes" name="puiw_color_schemes" hidden><?php echo esc_textarea(get_option("puiw_color_swatches", "")); ?></textarea>
              <div class="puiw-schemes-grid puiw-swatchcards" empty="<?php echo esc_attr__("No Color Scheme Found!", "pepro-ultimate-invoice"); ?>"></div>
              <div class="puiw_color_schemes_tool puiw-toolbar">
                <button type="button" class="button button-primary swatches-add-new"><?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("plus"); ?><span><?php echo esc_html(_x("Add New", "swatches-panel", "pepro-ultimate-invoice")); ?></span></button>
                <span class="puiw-toolbar-gap"></span>
                <button type="button" class="button swatches-export"><?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("download-simple"); ?><span><?php echo esc_html(_x("Export", "swatches-panel", "pepro-ultimate-invoice")); ?></span></button>
                <button type="button" class="button swatches-import"><?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("upload-simple"); ?><span><?php echo esc_html(_x("Import", "swatches-panel", "pepro-ultimate-invoice")); ?></span></button>
                <button type="button" class="button puiw-button-danger-outline swatches-delete-custom"><?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("trash"); ?><span><?php echo esc_html(_x("Delete my schemes", "swatches-panel", "pepro-ultimate-invoice")); ?></span></button>
              </div>
            </div>
            <?php
            $html_output = ob_get_contents();
            ob_end_clean();
            echo apply_filters("puiw_section_color_html", $html_output);
          }
          public function print_migrate_backup() {
            ob_start();
            ?>
            <h2><?php echo _x("Migrate/Backup Settings", "wc-setting", "pepro-ultimate-invoice"); ?></h2>
            <p class="puiw-panel-intro"><?php echo _x("Move this configuration to another shop, keep a copy before a big change, or put everything back the way it shipped.", "wc-setting", "pepro-ultimate-invoice"); ?></p>
            <div class="puiw-backup">
              <div class="puiw-cards">
                <div class="puiw-card">
                  <h3><?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("magic-wand"); ?><?php echo _x("Check after an update", "wc-setting", "pepro-ultimate-invoice"); ?></h3>
                  <p><?php echo _x("Convert settings saved by an older version and review the main choices in the setup wizard.", "wc-setting", "pepro-ultimate-invoice"); ?></p>
                  <p class="puiw-card-actions">
                    <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(add_query_arg("puiw-repair", 1, \peproulitmateinvoice\PeproUltimateInvoice_Setup::url()), "puiw-repair")); ?>"><?php echo _x("Check and run the setup", "wc-setting", "pepro-ultimate-invoice"); ?></a>
                  </p>
                </div>
                <div class="puiw-card">
                  <h3><?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("download-simple"); ?><?php echo _x("Export", "wc-setting", "pepro-ultimate-invoice"); ?></h3>
                  <p><?php echo _x("Take a copy of every setting on this page.", "wc-setting", "pepro-ultimate-invoice"); ?></p>
                  <p class="puiw-card-actions">
                    <button type="button" class="button button-secondary backup-export"><?php echo _x("Export JSON", "swatches-panel", "pepro-ultimate-invoice"); ?></button>
                    <button type="button" class="button button-secondary backup-export-php"><?php echo _x("Export PHP", "swatches-panel", "pepro-ultimate-invoice"); ?></button>
                  </p>
                </div>
                <div class="puiw-card">
                  <h3><?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("upload-simple"); ?><?php echo _x("Import", "wc-setting", "pepro-ultimate-invoice"); ?></h3>
                  <p><?php echo _x("Paste a copy taken from another shop to apply it here.", "wc-setting", "pepro-ultimate-invoice"); ?></p>
                  <p class="puiw-card-actions">
                    <button type="button" class="button button-primary backup-import"><?php echo _x("Import JSON", "swatches-panel", "pepro-ultimate-invoice"); ?></button>
                  </p>
                </div>
              </div>
              <div class="puiw-card is-danger">
                <h3><?php echo \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("warning-circle"); ?><?php echo _x("Danger Zone!", "wc-setting", "pepro-ultimate-invoice"); ?></h3>
                <p><?php echo _x("Do not use any of buttons below without knowing what your are doing!", "wc-setting", "pepro-ultimate-invoice"); ?></p>
                <p class="puiw-card-actions">
                  <button type="button" class="button button-secondary backup-danger" data-action="settings-reset" data-ask="<?php echo esc_attr(_x("Every setting of this plugin goes back to the value it shipped with. Your store details, texts and choices are overwritten.", "wc-setting", "pepro-ultimate-invoice")); ?>"><?php echo _x("FORCE RESET TO DEFAULT SETTINGS", "swatches-panel", "pepro-ultimate-invoice"); ?></button>
                  <button type="button" class="button button-secondary backup-danger" data-action="settings-clear" data-ask="<?php echo esc_attr(_x("Every setting of this plugin is emptied. Invoices will print with blank store details until you fill them in again.", "wc-setting", "pepro-ultimate-invoice")); ?>"><?php echo _x("FORCE CLEAR OUT ALL SETTINGS", "swatches-panel", "pepro-ultimate-invoice"); ?></button>
                  <button type="button" class="button button-secondary backup-danger" data-action="settings-fill" data-ask="<?php echo esc_attr(_x("Only the settings that are empty get their default value. Everything you filled in stays as it is.", "wc-setting", "pepro-ultimate-invoice")); ?>"><?php echo _x("FILL OUT EMPTY SETTINGS WITH DEFAULT VALUES", "swatches-panel", "pepro-ultimate-invoice"); ?></button>
                </p>
              </div>
            </div>
            <?php
            $html_output = ob_get_contents();
            ob_end_clean();
            echo apply_filters("puiw_section_migrate_backup_html", $html_output);
          }
          public function save() {
            $old_main = untrailingslashit((string) get_option("puiw_template", ""));
            foreach ($this->field_sections() as $section) {
              WC_Admin_Settings::save_fields($this->get_settings($section));
            }
            $this->save_language_settings($old_main);
            $this->save_color_swatches();
          }
          /**
           * the swatch editor keeps its JSON in a field named after the panel, not after
           * the option, and the panel is not one of the field sections, so the main save
           * button used to carry the value along and then throw it away
           *
           * @method save_color_swatches
           * @version 1.0.0
           * @since 3.2.5
           */
          public function save_color_swatches() {
            if (!isset($_POST["puiw_color_schemes"])) {
              return;
            }
            $raw = (string) wp_unslash($_POST["puiw_color_schemes"]);
            if ("" === trim($raw)) {
              return;
            }
            // anything that is not the editor's own JSON would break the swatch panel
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
              return;
            }
            $swatches = array();
            foreach ($decoded as $item) {
              if (!is_array($item) || empty($item["n"])) {
                continue;
              }
              $colors = array_map("sanitize_hex_color", array(
                isset($item["p"]) ? $item["p"] : "",
                isset($item["s"]) ? $item["s"] : "",
                isset($item["t"]) ? $item["t"] : "",
              ));
              if (in_array(null, $colors, true) || in_array("", $colors, true)) {
                continue;
              }
              $swatches[] = array("n" => sanitize_text_field($item["n"]), "p" => $colors[0], "s" => $colors[1], "t" => $colors[2]);
            }
            // the built-in schemes cannot be removed: put back any the posted list lacks
            $names = wp_list_pluck($swatches, "n");
            foreach (array_reverse(self::built_in_swatches()) as $scheme) {
              if (!in_array($scheme["n"], $names, true)) {
                array_unshift($swatches, $scheme);
              }
            }
            update_option("puiw_color_swatches", wp_json_encode($swatches));
          }
          /**
           * store the per language template, settings and texts
           *
           * @method save_language_settings
           */
          public function save_language_settings($old_main = "") {
            global $PeproUltimateInvoice;
            $known = $PeproUltimateInvoice->i18n->option_keys();

            $main    = untrailingslashit((string) get_option("puiw_template", ""));
            $changed = "" !== $old_main && $old_main !== $main;
            $templates = array();
            if (isset($_POST["puiw_lang_template"]) && is_array($_POST["puiw_lang_template"])) {
              foreach (wp_unslash($_POST["puiw_lang_template"]) as $lang => $template) {
                // "use the template from Theming" posts an empty value; normalising it
                // turned it into the Default template, which then overrode the template
                // picked under Theming for every language on every save
                if ("" === trim((string) $template)) {
                  continue;
                }
                $template = untrailingslashit((string) $PeproUltimateInvoice->i18n->normalise_template(sanitize_text_field($template)));
                if ("" === trim($template) || !is_readable("{$template}/default.cfg")) {
                  continue;
                }
                // a language "override" that is just the main template is no override, and
                // when the main template was changed in this same save, one that pointed at
                // the old main template was following it: let it follow the new one
                if ($template === $main || ($changed && $template === $old_main)) {
                  continue;
                }
                $templates[sanitize_key($lang)] = $template;
              }
            }
            update_option(\peproulitmateinvoice\PeproUltimateInvoice_I18n::OPT_TEMPLATE, $templates);

            $settings = array();
            if (isset($_POST["puiw_lang_settings"]) && is_array($_POST["puiw_lang_settings"])) {
              foreach (wp_unslash($_POST["puiw_lang_settings"]) as $lang => $values) {
                if (!is_array($values)) { continue; }
                $lang = sanitize_key($lang);
                foreach ($values as $key => $value) {
                  if (!in_array($key, $known, true)) { continue; }
                  $value = sanitize_text_field($value);
                  if ("" === trim($value)) { continue; }
                  $settings[$lang][$key] = $value;
                }
              }
            }
            update_option(\peproulitmateinvoice\PeproUltimateInvoice_I18n::OPT_SETTINGS, $settings);

            $strings = array();
            if (isset($_POST["puiw_lang_strings"]) && is_array($_POST["puiw_lang_strings"])) {
              foreach (wp_unslash($_POST["puiw_lang_strings"]) as $lang => $values) {
                if (!is_array($values)) { continue; }
                $lang = sanitize_key($lang);
                foreach ($values as $source => $target) {
                  $target = wp_kses_post($target);
                  if ("" === trim($target)) { continue; }
                  $strings[$lang][(string) $source] = $target;
                }
              }
            }
            update_option(\peproulitmateinvoice\PeproUltimateInvoice_I18n::OPT_STRINGS, $strings);
          }
          public function wp_enqueue_scripts() {
            global $PeproUltimateInvoice;
            wp_enqueue_media();
            // every panel is on the page at once, so every panel's assets load with it

            wp_enqueue_script("jquery-confirm",     PEPROULTIMATEINVOICE_ASSETS_URL . "/js/jquery-confirm.min.js", array("jquery"), PEPROULTIMATEINVOICE_VER);
            wp_enqueue_script("jquery-color",       PEPROULTIMATEINVOICE_ASSETS_URL . "/js/jquery.color.min.js", array("jquery"), PEPROULTIMATEINVOICE_VER);
            wp_enqueue_style("jquery-confirm",      PEPROULTIMATEINVOICE_ASSETS_URL . "/css/jquery-confirm.min.css", array(), PEPROULTIMATEINVOICE_VER, 'all');
            wp_enqueue_style("jquery-minicolors",   PEPROULTIMATEINVOICE_ASSETS_URL . "/css/jquery.minicolors" . $this->debugEnabled(".css", ".min.css"), array(), PEPROULTIMATEINVOICE_VER, 'all');
            wp_enqueue_script("jquery-minicolors",  PEPROULTIMATEINVOICE_ASSETS_URL . "/js/jquery.minicolors.min.js", array("jquery"), PEPROULTIMATEINVOICE_VER);
            wp_enqueue_style("color-scheme.css",   PEPROULTIMATEINVOICE_ASSETS_URL . '/admin/color-scheme' . $this->debugEnabled(".css", ".min.css"), array(), PEPROULTIMATEINVOICE_VER);
            \peproulitmateinvoice\PeproUltimateInvoice_Setup::enqueue_alwan();
            wp_register_script("color-scheme.js",  PEPROULTIMATEINVOICE_ASSETS_URL . '/admin/color-scheme' . $this->debugEnabled(".js", ".min.js"), array('jquery', 'puiw-alwan'), PEPROULTIMATEINVOICE_VER);
            wp_localize_script("color-scheme.js", "msg", array(
              "delete"       => __("Delete", "pepro-ultimate-invoice"),
              "edit"         => __("Edit", "pepro-ultimate-invoice"),
              "apply"        => __("Apply", "pepro-ultimate-invoice"),
              "discard"      => __("Discard", "pepro-ultimate-invoice"),
              "export"       => __("Export", "pepro-ultimate-invoice"),
              "import"       => __("Import", "pepro-ultimate-invoice"),
              "commingsoon"  => __("Comming Soon ...", "pepro-ultimate-invoice"),
              "primary"      => __("Primary", "pepro-ultimate-invoice"),
              "secondary"    => __("Secondary", "pepro-ultimate-invoice"),
              "tertiary"     => __("Tertiary", "pepro-ultimate-invoice"),
              "click2change" => __("Click to change", "pepro-ultimate-invoice"),
              "importErr"    => __("Error importing data!", "pepro-ultimate-invoice"),
              "importErr2"   => __("Please check the data or contact support@peprodev.com", "pepro-ultimate-invoice"),
              "insertErr"    => __("Enter data here to Import.", "pepro-ultimate-invoice"),
              "swatchinp"    => __("Press ENTER to set name or ESC to discard", "pepro-ultimate-invoice"),
              "icTrash"      => \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("trash"),
              "icEdit"       => \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("pencil-simple"),
              "icDiscard"    => \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("x"),
              "icApply"      => \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("check"),
            ));
            wp_localize_script("color-scheme.js", "_i18n", array(
              "td"         => "puiw_{$this->td}",
              "ajax"       => admin_url("admin-ajax.php"),
              "home"       => home_url(),
              "nonce"      => wp_create_nonce("pepro-ultimate-invoice"),
              "plugin_url" => PEPROULTIMATEINVOICE_URL,

              "errorTxt"   => _x("Error", "wc-setting-js", "pepro-ultimate-invoice"),
              "cancelTtl"  => _x("Canceled", "wc-setting-js", "pepro-ultimate-invoice"),
              "confirmTxt" => _x("Confirm", "wc-setting-js", "pepro-ultimate-invoice"),
              "successTtl" => _x("Success", "wc-setting-js", "pepro-ultimate-invoice"),
              "submitTxt"  => _x("Submit", "wc-setting-js", "pepro-ultimate-invoice"),
              "okTxt"      => _x("Okay", "wc-setting-js", "pepro-ultimate-invoice"),
              "txtCopy"    => _x("Copy to clipboard", "wc-setting-js", "pepro-ultimate-invoice"),
              "txtYes"     => _x("Yes", "wc-setting-js", "pepro-ultimate-invoice"),
              "txtImport"  => _x("Import", "wc-setting-js", "pepro-ultimate-invoice"),
              "txtNop"     => _x("No", "wc-setting-js", "pepro-ultimate-invoice"),
              "cancelbTn"  => _x("Cancel", "wc-setting-js", "pepro-ultimate-invoice"),
              "sendTxt"    => _x("Send to all", "wc-setting-js", "pepro-ultimate-invoice"),
              "closeTxt"   => _x("Close", "wc-setting-js", "pepro-ultimate-invoice"),

              "confirm_trash"       => _x("Are you sure you want to Delete This Color Schemes?<br>THIS CAN NOT BE UNDONE.", "wc-setting-js", "pepro-ultimate-invoice"),
              "confirm_delete"      => _x("Are you sure you want to Delete All Color Schemes?", "wc-setting-js", "pepro-ultimate-invoice"),
              "confirm_restore"     => _x("Are you sure you want to Restore All Color Schemes to Default?<br>THIS WILL DELETE ALL CURRENT ITEMS AND IT CAN NOT BE UNDONE.", "wc-setting-js", "pepro-ultimate-invoice"),
              /* translators: %s: scheme name */
              "confirm_trash_one"     => _x("Delete the %s scheme? Invoices that use it keep their colours.", "wc-setting-js", "pepro-ultimate-invoice"),
              "confirm_delete_custom" => _x("Delete every scheme you added? The built-in schemes stay.", "wc-setting-js", "pepro-ultimate-invoice"),
              "builtInTxt"            => _x("Built-in", "wc-setting-js", "pepro-ultimate-invoice"),
              "nameTxt"               => _x("Scheme name", "wc-setting-js", "pepro-ultimate-invoice"),
            ));
            wp_enqueue_script("color-scheme.js");

            wp_enqueue_style("migrate-backup.css", PEPROULTIMATEINVOICE_ASSETS_URL . '/admin/migrate-backup' . $this->debugEnabled(".css", ".min.css"), array(), PEPROULTIMATEINVOICE_VER);
            wp_enqueue_script("jquery-confirm",     PEPROULTIMATEINVOICE_ASSETS_URL . "/js/jquery-confirm.min.js", array("jquery"), PEPROULTIMATEINVOICE_VER);
            wp_enqueue_style("jquery-confirm",      PEPROULTIMATEINVOICE_ASSETS_URL . "/css/jquery-confirm.min.css", array(), PEPROULTIMATEINVOICE_VER, 'all');
            wp_register_script("migrate-backup.js",  PEPROULTIMATEINVOICE_ASSETS_URL . '/admin/migrate-backup' . $this->debugEnabled(".js", ".min.js"), array('jquery'), PEPROULTIMATEINVOICE_VER);
            // both scripts write window.msg and this one prints last, so it carries the
            // scheme editor's icons as well
            wp_localize_script("migrate-backup.js", "msg", array(
              "icTrash"   => \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("trash"),
              "icEdit"    => \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("pencil-simple"),
              "icDiscard" => \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("x"),
              "icApply"   => \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("check"),
              "delete"       => __("Delete", "pepro-ultimate-invoice"),
              "edit"         => __("Edit", "pepro-ultimate-invoice"),
              "apply"        => __("Apply", "pepro-ultimate-invoice"),
              "discard"      => __("Discard", "pepro-ultimate-invoice"),
              "export"       => __("Export", "pepro-ultimate-invoice"),
              "import"       => __("Import", "pepro-ultimate-invoice"),
              "commingsoon"  => __("Comming Soon ...", "pepro-ultimate-invoice"),
              "primary"      => __("Primary", "pepro-ultimate-invoice"),
              "secondary"    => __("Secondary", "pepro-ultimate-invoice"),
              "tertiary"     => __("Tertiary", "pepro-ultimate-invoice"),
              "click2change" => __("Click to change", "pepro-ultimate-invoice"),
              "importErr"    => __("Error importing data!", "pepro-ultimate-invoice"),
              "exportErr"    => __("Error exporting data!", "pepro-ultimate-invoice"),
              "importErr2"   => __("Please check the data or contact support@peprodev.com", "pepro-ultimate-invoice"),
              "insertErr"    => __("Enter data here to Import.", "pepro-ultimate-invoice"),
              "swatchinp"    => __("Press ENTER to set name or ESC to discard", "pepro-ultimate-invoice"),
            ));
            wp_localize_script("migrate-backup.js", "_i18n", array(
              "td"         => "puiw_{$this->td}",
              "ajax"       => admin_url("admin-ajax.php"),
              "home"       => home_url(),
              "nonce"      => wp_create_nonce("pepro-ultimate-invoice"),
              "plugin_url" => PEPROULTIMATEINVOICE_URL,
              "phpTitle"     => _x("Export as PHP", "wc-setting-js", "pepro-ultimate-invoice"),
              "downloadTxt"  => _x("Download", "wc-setting-js", "pepro-ultimate-invoice"),
              "fromFileTxt"  => _x("Or pick a .json file", "wc-setting-js", "pepro-ultimate-invoice"),
              "reloadingTxt" => _x("Reloading the settings…", "wc-setting-js", "pepro-ultimate-invoice"),
              "UnknownErr"   => _x("The request failed. Reload the page and try again.", "wc-setting-js", "pepro-ultimate-invoice"),
              "errorTxt"   => _x("Error", "wc-setting-js", "pepro-ultimate-invoice"),
              "cancelTtl"  => _x("Canceled", "wc-setting-js", "pepro-ultimate-invoice"),
              "cautiontl"  => _x("Caution", "wc-setting-js", "pepro-ultimate-invoice"),
              "confirmTxt" => _x("Confirm", "wc-setting-js", "pepro-ultimate-invoice"),
              "confirmMsg" => _x("Are you sure you want to continue?", "wc-setting-js", "pepro-ultimate-invoice"),
              "successTtl" => _x("Success", "wc-setting-js", "pepro-ultimate-invoice"),
              "reloadTxt"  => _x("Reload page", "wc-setting-js", "pepro-ultimate-invoice"),
              "submitTxt"  => _x("Submit", "wc-setting-js", "pepro-ultimate-invoice"),
              "okTxt"      => _x("Okay", "wc-setting-js", "pepro-ultimate-invoice"),
              "txtCopy"    => _x("Copy to clipboard", "wc-setting-js", "pepro-ultimate-invoice"),
              "txtYes"     => _x("Yes", "wc-setting-js", "pepro-ultimate-invoice"),
              "txtImport"  => _x("Import", "wc-setting-js", "pepro-ultimate-invoice"),
              "txtNop"     => _x("No", "wc-setting-js", "pepro-ultimate-invoice"),
              "cancelbTn"  => _x("Cancel", "wc-setting-js", "pepro-ultimate-invoice"),
              "sendTxt"    => _x("Send to all", "wc-setting-js", "pepro-ultimate-invoice"),
              "closeTxt"   => _x("Close", "wc-setting-js", "pepro-ultimate-invoice"),

              "confirm_trash"       => _x("Are you sure you want to Delete This Color Schemes?<br>THIS CAN NOT BE UNDONE.", "wc-setting-js", "pepro-ultimate-invoice"),
              "confirm_delete"      => _x("Are you sure you want to Delete All Color Schemes?", "wc-setting-js", "pepro-ultimate-invoice"),
              "confirm_restore"     => _x("Are you sure you want to Restore All Color Schemes to Default?<br>THIS WILL DELETE ALL CURRENT ITEMS AND IT CAN NOT BE UNDONE.", "wc-setting-js", "pepro-ultimate-invoice"),
            ));
            wp_enqueue_script("migrate-backup.js");

            // due to lag between page load and css load, we load css directrly at line 70
            wp_enqueue_style("pepro-ultimate-invoice-wc-setting", PEPROULTIMATEINVOICE_ASSETS_URL . "/admin/wc_setting.css", array(), PEPROULTIMATEINVOICE_VER);
            wp_enqueue_style("pepro-ultimate-invoice-settings", PEPROULTIMATEINVOICE_ASSETS_URL . "/admin/settings.css", array("pepro-ultimate-invoice-wc-setting"), PEPROULTIMATEINVOICE_VER);
            // the editor ships with WordPress, htmlhint and csslint come with it
            wp_enqueue_code_editor(array("type" => "text/html"));
            wp_enqueue_script("wp-theme-plugin-editor");
            wp_enqueue_style("wp-codemirror");
            wp_enqueue_style("wp-color-picker");
            wp_enqueue_script("wp-color-picker");
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-selectmenu');
            \peproulitmateinvoice\PeproUltimateInvoice_Setup::enqueue_alwan();
            wp_register_script("pepro-ultimate-invoice-wc-setting", PEPROULTIMATEINVOICE_ASSETS_URL . "/admin/wc_setting"  . $this->debugEnabled(".js", ".min.js"), array("jquery", "puiw-alwan"), PEPROULTIMATEINVOICE_VER, true);
            wp_localize_script(
              "pepro-ultimate-invoice-wc-setting",
              "_peproUltimateInvoice",
              array(
                "title"        => _x("Select image file", "wc-setting-js", "pepro-ultimate-invoice"),
                "btntext"      => _x("Use this image", "wc-setting-js", "pepro-ultimate-invoice"),
                "clear"        => _x("Clear", "wc-setting-js", "pepro-ultimate-invoice"),
                "currentlogo"  => _x("Current preview", "wc-setting-js", "pepro-ultimate-invoice"),
                "selectbtn"    => _x("Select image", "wc-setting-js", "pepro-ultimate-invoice"),
                "copied"       => _x("## copied to clipboard.", "wc-setting-js", "pepro-ultimate-invoice"),
                "zephyrfix"    => true, /* fix for upsulotion admin css */
                "plugin_url"   => PEPROULTIMATEINVOICE_URL,
                "themeData"    => $PeproUltimateInvoice->load_themes(1),
                "get_template" => get_option("puiw_template", "default"),
                "ajax"         => admin_url("admin-ajax.php"),
                "action"       => "puiw_{$this->td}",
                "nonce"        => wp_create_nonce("pepro-ultimate-invoice"),
                "saving"       => _x("Saving&hellip;", "wc-setting-js", "pepro-ultimate-invoice"),
                "saveError"    => _x("Settings could not be saved.", "wc-setting-js", "pepro-ultimate-invoice"),
                "currentUser"  => wp_get_current_user()->display_name,
                "iconHtml"     => \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("brackets-angle"),
                "iconCss"      => \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("paint-brush"),
                "iconCfg"      => \peproulitmateinvoice\PeproUltimateInvoice_Icons::get("file-text"),
                "teName"       => _x("Name", "wc-setting", "pepro-ultimate-invoice"),
                "teAuthor"     => _x("Author", "wc-setting", "pepro-ultimate-invoice"),
                "teCopy"       => _x("copy", "wc-setting", "pepro-ultimate-invoice"),
                "teDuplicate"  => _x("Duplicate", "wc-setting", "pepro-ultimate-invoice"),
                "teDiscard"    => _x("This file has unsaved changes. Discard them?", "wc-setting-js", "pepro-ultimate-invoice"),
                "cssOk"        => _x("No problems found", "wc-setting-js", "pepro-ultimate-invoice"),
                /* translators: 1: errors, 2: warnings */
                "cssIssues"    => _x('%1$s errors, %2$s warnings', "wc-setting-js", "pepro-ultimate-invoice"),
                "cssLine"      => _x("Line %s", "wc-setting-js", "pepro-ultimate-invoice"),
                "cssSnippets"  => _x("Snippets", "wc-setting-js", "pepro-ultimate-invoice"),
                /* translators: %s: number of stylesheets with errors */
                "cssSaveAsk"   => _x("%s stylesheet(s) have errors and may not apply. Save anyway?", "wc-setting-js", "pepro-ultimate-invoice"),
                "cssSnippetList" => array(
                  array(_x("Hide a column", "wc-setting-css", "pepro-ultimate-invoice"), "/* hide the SKU column; any show_… class works */\n.show_product_sku { display: none !important; }\n"),
                  array(_x("Bigger grand total", "wc-setting-css", "pepro-ultimate-invoice"), ".puiw-total-order_total { font-size: 14pt; font-weight: bold; }\n"),
                  array(_x("Table borders", "wc-setting-css", "pepro-ultimate-invoice"), "table td, table th { border: 1px solid #dcdcde; }\n"),
                  array(_x("Zebra rows", "wc-setting-css", "pepro-ultimate-invoice"), "tbody tr:nth-child(even) td { background: #f6f7f7; }\n"),
                  array(_x("Text size", "wc-setting-css", "pepro-ultimate-invoice"), "body, td, th { font-size: 10pt; }\n"),
                  array(_x("Keep totals on one page", "wc-setting-css", "pepro-ultimate-invoice"), "/* rows and total lines never split across two pages */\ntr, .puiw-total-line { page-break-inside: avoid; }\n"),
                  array(_x("Hide the print button", "wc-setting-css", "pepro-ultimate-invoice"), "@media print { .print-button { display: none !important; } }\n"),
                ),
                "teConfirmDelete" => _x("Delete this template for good?", "wc-setting-js", "pepro-ultimate-invoice"),
              )
            );
            wp_enqueue_script("pepro-ultimate-invoice-wc-setting");
          }
          public function get_wc_managers() {
            $_wc_managers = array();
            $users = get_users(
              array(
                "role__in" => array("administrator", "shop_manager")
              )
            );
            foreach ($users as $user) {
              $_wc_managers[$user->user_email] = "$user->user_firstname $user->user_lastname ($user->user_email)";
            }
            return $_wc_managers;
          }
          public function wc_get_order_statuses() {
            return wc_get_order_statuses();
          }
      };
    }
    return $pages;
  }
);