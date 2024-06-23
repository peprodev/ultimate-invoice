<img src="https://ps.w.org/pepro-ultimate-invoice/assets/banner-772x250.png" style="border-radius: 7px;"/>
<img src="https://ps.w.org/pepro-ultimate-invoice/assets/banner-772x250-rtl.png" style="border-radius: 7px;"/>

**[PeproDev Ultimate Invoice](https://wordpress.org/plugins/pepro-ultimate-invoice/)**
==========================

The Most Advanced Invoice Plugin you were looking for! Create customizable PDF/HTML invoices for WooCommerce, attach to Email, Packing Slips, Shipping Labels, Shipping Tracking, Single-shop feature and ...

This plugin lets you to Generate Awesome Invoices for WooCommerce orders and:

-   Download PDF invoices
-   Email Styled Invoices
-   Attach PDF to WC Emails
-   Attach PDF to Invoices Emails
-   Restrict Invoices Options
-   Full Invoice Customizations
-   Make your own Invoice Template
-   Make your own PDF Invoice Template
-   Make your own Inventory Report Template
-   Make your own Packing Slips Template
-   Alter plugins via Action/Filter hooks
-   and .....


### Hot Features of Version 1.3.7 🔥 (2021-07-14 | 1400-04-23)
- Bulk Download Invoices PDF as ZIP Archive
- Bulk Print Invoices Inventory Reports
- Bulk Print Invoices Shipping Slips
- Export/Import Settings as JSON/PHP!


-----------------------

### Made by Developers for Developers
- [Github Wiki](https://github.com/peprodev/ultimate-invoice/wiki)
- Over 190 Filter Hook
- Over 15 Action Hook
- Fully Translatable
- Nice and Human Readable Variable Names
- Separated Classes for Different purposes
- Templates for HTML Invoice (tpl, css)
- Templates for PDF Invoice (tpl, css)
- Templates for Email Invoice (tpl, css)
- Templates for Inventory Report (tpl, css)
- Templates for Packing Slips(tpl, css)
- Change Email Sending Settings (from, name, and ...)
- Preserve email template style even in **Gmail**!
- and ....

### Invoice Items filtering
- Show / Hide Store National Id
- Show / Hide Store Registration Number
- Show / Hide Store Economical Number
- Show / Hide Customer Address
- Show / Hide Customer Phone
- Show / Hide Customer E-mail
- Show / Hide Order Date
- Show / Hide Payment method
- Show / Hide Shipping method
- Show / Hide Transaction Ref. ID
- Show / Hide Product Image
- Show / Hide Product Purchase note
- Show / Hide Order Items
- Show / Hide Order Total
- Show / Hide Product Weight
- Show / Hide Product Dimensions
- Show / Hide Product SKU
- Show / Hide Order Note
- and ....

### Extras
- Quick Shop Feature ( Purchase all products in one page )
- Visual Composer Widget
- Pre-order Invoices Status
- Built in DARK MODE Support (Auto, Manual)
- Disable WC Modern Dashboard
- Date parsing templates
- Jalali/Shamsi Date formats numbers?
- English, Eastern Arabic and Persian Numbers style
- RTL-ready
- Automated Email sending system
- Fully Woo-commerce integration
- and ...


<hr>

### **Developed by** [Pepro Development Group](https://pepro.dev/) for WooCommerce

*Current Version: 2.0.2* \| *Lead Developer:* [amirhp.com](https://amirhp.com)

----------

### ***Changelog***



#### Version 2.0.5 / 2024-06-23 / 1403-04-03
- Dev: added `puiw_generate_pdf_Mpdf_options` filter hook
- Dev: added `puiw_parse_pdf_template` filter hook

#### Version 2.0.4 / 2024-06-14 / 1403-03-25
- Fixed `Uncaught Error: Call to undefined method WP Post:get_id()`


#### Version 2.0.3 / 2024-06-13 / 1403-03-24
- Fix HPOS error of incompatibility


#### Version 2.0.2 / 2024-05-02 / 1403-02-13

- WooCommerce 8.8.3 Compatibility
- Added: Save Shipping ResID Ajax-button
- Enhanced: Security in Migration settings (Thanks to Darius S from patchstack.com)

#### Version 2.0.0 / 2024-04-02 / 1403-01-14
- WooCommerce HPOS Compatibility fix

#### Version 1.9.9 / 2024-03-19 / 1402-12-29
- Security patch 

#### Version 1.9.8 / 2024-03-07 / 1402-12-17
- Fix two Security Vulnerability - CVSS 3.1
- Thanks Abdi Pranata for reporting

#### Version 1.9.7 / 2024-01-20 / 1402-10-30
- Fix compatibility with High-Performance Order Storage
- Fix HPOS Orders screen Column not showing
- Fix HPOS Order screen metabox not showing

#### Version 1.9.6 / 2024-01-20 / 1402-10-30
- Fixed Error: `Deprecated: DateTime::_construct():`
- Add compatibility with High-Performance Order Storage


#### Version 1.9.5 / 2023-04-05 / 1402-01-16
- Remove previous generated pdf files
- Added Shortcode for Persian WooCommerce SMS (شورتکد برای پیامک ووکامرس فارسی)

#### Version 1.9.4 / 2023-03-01 / 1401-12-10
- Enqueue FontAwesome only on Required WC_Admin pages
- Some Bug Fixes on PRINT_CLASS:get_default_dynamic_params
- Show WordPress Error when mPDF fails to generate PDF files
- Allow Creating PDF files with **MORE THAN A MILLION** characters
- Applied Fixes on HTML Minification to Support 1.000.000+ characters
- \* The PHP function preg_replace() has a maximum string length it will parse (by default this is often about 100000 characters). Over this, PHP silently returns a null value. So long strings of code will be replaced by nothing!

#### Version 1.9.3 / 2022-11-07 / 1401-08-16
- Fixed Showing error on printing PDF

#### Version 1.9.2 / 2022-10-15 / 1401-07-23
- PDF Invoice Footer translated
- Now Order table rows fills PDF-page to the bottom
- DEV: You should update your custom Invoice Templates to the latest version
- DEV: Deprecated hook *puiw_printinvoice_pdf_footer* to *puiw_printinvoice_pdf_footer_new* with 3 arg


#### Version 1.9.0 / 2022-10-13 / 1401-07-21
- Watermark for PDFs added with Alpha and BlendMode Options
- Default Invoice Templates footer changed
- DEV: edited hook *puiw_printinvoice_pdf_footer*
- DEV: added hook *puiw_generate_pdf_page_size*
- DEV: added hook *puiw_generate_pdf_watermark_img*
- DEV: added hook *puiw_generate_pdf_watermark_alph*
- DEV: added hook *puiw_generate_pdf_watermark_size*
- DEV: added hook *puiw_generate_pdf_watermark_posin*
- DEV: added hook *puiw_generate_pdf_watermark_show*

#### Version 1.8.8 / 2022-10-13 / 1401-07-21
- DEV: added public function $PeproUltimateInvoice->make_pdf_file($order_id)
- Compatibility with TelegramBot (send PDF invoices on Chat/Group/Channel)
- To Buy TelegramBot plugin contact support@pepro.dev

#### Version 1.8.7 / 2022-09-11 / 1401-06-20
- Swap Packing Slips labels

#### Version 1.8.6 / 2022-09-03 / 1401-06-12
- Fixed Show/hide Shipping Date on Packing slips

#### Version 1.8.5 / 2022-09-03 / 1401-06-12
- Style Enhancement for PDF Packing Slips
- Fixed font size issue on printing pdf and html

#### Version 1.8.2 / 2022-08-15 / 1401-05-24
- Made _puiw_regular, _puiw_sale, _puiw_html hidden from admin area

#### Version 1.8.1 / 2022-08-03 / 1401-05-12
- Fixed discount percentage calculating

#### Version 1.8.0 🔥 / 2022-07-14 / 1401-04-23
- Added 4 ways of showing Coupons on total
- Added 7 ways of showing Line item's price
- Added 3 ways of showing Line item's tax
- Added 3 ways of calculating Line item's discount
- Added 3 ways of showing Line item's discount (Amount/Percentage)
- Added 3 line item meta, editable and viewable by admins (_puiw_regular, _puiw_sale, _puiw_html)
- Fixed some of Invoice templates styles
- Fixed checkout error caused by out-of-stock line items
- Fixed swatch dropdown showed wrong color-scheme
- Changed some options layout
- Changed order metabox layout
- Changed setting panel font, layout, responsiveness
- Changed checkboxes to iOS-like toggles
- Changed WC_Order behavior to save current-live line items sale/regular prices
- DEV: added public function *PeproUltimateInvoice_Template->get_show_tax_display*
- DEV: added public function *PeproUltimateInvoice_Template->get_show_coupons_code_at_totals*
- DEV: added public function *PeproUltimateInvoice_Template->get_show_coupons_description_at_totals*
- DEV: added public function *PeproUltimateInvoice_Template->get_show_coupons_discount_at_totals*
- DEV: added public function *PeproUltimateInvoice_Template->get_show_coupons_amount_at_totals*
- DEV: added public function *PeproUltimateInvoice_Template->get_show_discount_calc*
- DEV: added public function *PeproUltimateInvoice_Template->get_show_discount_display*


#### Version 1.7.4 / 2022-06-26 / 1401-04-05
- Fixed *Uncaught Error: Call to a member function get_id() on null*

#### Version 1.7.3 / 2022-06-07 / 1401-03-17
- Fixed total calculation
- Fixed wrong discount precent
- Fixed Checkout issue
- Added some CSS to invoice

#### Version 1.7.1
- Added Debug Info in Setting (admin.php?page=wc-settings&tab=pepro_ultimate_invoice&section=debug)
- DEV: added *puiw_debug_list_items* hook

#### Version 1.7.0 / 2022-05-30 / 1401-03-09
- Compatibility with WP v6 and WC v6.5
- Fixed Gateway Proccess issue
- Fixed Barcode Showing issue
- Fixed Backend CSS issue
- Fixed HTML Invoice watermark Issue

#### Version 1.5.0
- All libraries are updated

#### Version 1.4.6 / 2022-02-11 / 1400-11-22
- *Fixed Throwing Error on Creating PDF when no Logo is set for Store*

#### Version 1.4.5 / 2022-02-07 / 1400-11-18
- Show notice if WooCommerce is not installed
- Fix bug when WooCommerce was not installed

#### Version 1.4.4 / 2022-01-31 / 1400-11-11
- Change Packing Slips Receiver/Sender arrangements order

#### Version 1.4.3 / 2022-01-26 / 1400-11-06
- Fix Showing signature section regarding settings
- Fix Applying watermark opacity level (1-100)
- Fix some Translation

#### Version 1.4.2 / 2021-12-22 / 1400-10-01
- Fix Shipping date not calculated
- Fix Sending Error on Updating plugins

#### Version 1.4.1 / 2021-11-13 / 1400-08-22
- Fix: Auto-set pre-defined settings

#### Version 1.4.0 / 2021-10-02 / 1400-07-10
- HTML Invoice Check Requirements
- Bug fixes

#### Version 1.3.9 / 2021-09-15 / 1400-06-26
- Bug fixes
- Compatibility with AP Payment gateway (درگاه پرداخت آپ)
- DEV: Changed print invoice get query

#### Version 1.3.8 / 2021-07-28 / 1400-05-06
- Bug fixes

#### Version 1.3.7 / 2021-07-14 / 1400-04-23
- Added: Bulk Print Invoices Shipping Slips (Order Screen > Bulk Actions)
- Added: Bulk Print Invoices Inventory Reports (Order Screen > Bulk Actions)
- Added: Bulk Download PDF Invoices as ZIP Archive (Order Screen > Bulk Actions)
- Fixed: Wrong GMT/UTC date display

#### Version 1.3.5 / 2021-07-12 / 1400-04-21
- Fixed: Changed textdomain to pepro-ultimate-invoice
- Fixed: 'Store' Address was not properly translated
- Fixed: 'Customer' Address was not properly translated
- Fixed: Default template for RTL/Persian sites was not 'Default-RTL'
- Fixed: Wpbakery Page Builder widget not showing
- Fixed: Quick Buy feature would not work when Wpbakery Page Builder is disabled
- Added: Backup / Export & Import Settings as JSON
- Added: Option to Revert Settings to Default, Clear out Settings, Re-set Settings based on default values
- Added: More default Options based on site Locale
- Added: Export/Import Settings as JSON data
- Added: Export Settings as PHP Script to use in your child theme for customers
- Added: `pepro_ultimate_invoice_default_options` filter hook to alter default settings fields
- Added: `pepro_ultimate_invoice_reset_options_done` action hook to alter default settings fields
- Dev: Upgraded font-awesome to 5.15.3
- Dev: Changed function which returned WooCommerce store base-address
- Dev: new functional query string (for Developers usage only): /wp-admin/?ultimate-invoice-reset !DO NOT USE IF NOT SURE!
- Dev: new functional query string (for Developers usage only): /wp-admin/?ultimate-invoice-clear !DO NOT USE IF NOT SURE!
- Dev: new functional query string (for Developers usage only): /wp-admin/?ultimate-invoice-set
- Dev: new functional query string (for Developers usage only): /wp-admin/?ultimate-invoice-get
- Dev: Changed Setting panel javascript localize_script object as `_peproUltimateInvoice`
- Removed: function `clear_out_settings`, use `change_default_settings` instead

#### Version 1.3.4
- Fixed: Changed Default Invoice Access setting to prevent Pre-invoice 403 error
- Dev: added `puiw_printinvoice_check_user_has_access` filter Hook to alter Invoice Access
- Dev: Invoice Access function is more relible now

#### Version 1.3.2
- Fixed: Sorting problems
- Fixed: Error Create Inventory and Packing Slip Reports

#### Version1.3.0
- Added: Fully Compatibility with [WPC Product Bundles by WPClever](https://wpclever.net/downloads/product-bundles)
- Added: Fully Compatibility with [WooCommerce Extra Product Options by ThemeComplete](https://codecanyon.net/item/woocommerce-extra-product-options/7908619)
- Added: Fully Compatibility with *Any Standard* plugins that adds and shows Order item metas
- Added: Showing *Order item meta* after order item description
- Added: Sort order items by get_items_sorting
- Dev: Added get_items_sorting public function in PeproUltimateInvoice_Template
- Dev: Added puiw_get_items_sorting filter hook
- Dev: Added puiw_order_items_sort_by filter hook to change items sorting (PID, ID, SKU, QTY, NAME, PRICE, TOTAL, WEIGHT, SUBTOTAL, SUBTOTAL_TAX)
- Dev: Added puiw_order_items_sort_desc filter hook to change items ordering from ASC to DESC
- Dev: Added puiw_order_items_sort_by_force filter hook to hook into items ordering by your choice
- Dev: Added get_woosb_show_bundles public function in PeproUltimateInvoice_Template
- Dev: Added puiw_get_woosb_show_bundles_subtitle filter hook
- Dev: Added get_woosb_show_bundles_subtitle public function in PeproUltimateInvoice_Template
- Dev: Added puiw_get_woosb_show_bundles_subtitle filter hook
- Dev: Added get_woosb_show_bundled_products public function in PeproUltimateInvoice_Template
- Dev: Added puiw_get_woosb_show_bundled_products filter hook
- Dev: Added get_woosb_show_bundled_subtitle public function in PeproUltimateInvoice_Template
- Dev: Added puiw_get_woosb_show_bundled_subtitle filter hook
- Dev: Added get_woosb_show_bundled_hierarchy public function in PeproUltimateInvoice_Template
- Dev: Added puiw_get_woosb_show_bundled_hierarchy filter hook
- Dev: Added get_woosb_bundled_subtitle_prefix public function in PeproUltimateInvoice_Template
- Dev: Added puiw_get_woosb_bundled_subtitle_prefix filter hook
- Dev: Added get_woosb_bundles_subtitle_prefix public function in PeproUltimateInvoice_Template
- Dev: Added puiw_get_woosb_bundles_subtitle_prefix filter hook
- Dev: Added puiw_invoice_item_extra_classes filter hook for invoice items tr html el. class
- Dev: Added puiw_order_items filter hook to manipulate order items
- Dev: Added Integration Section in Settings
- Dev: Added Comment for print invoice class functions
- Fixed: Discount precent problem
- Fixed: Showing live price instead of actual order cost
- Fixed: Invoice Access problem
- Fixed: Default setting values changed
- Fixed: Clearing Setting on Uninstall problem
- Fixed: Translation & ReadMe
- Fixed: Font max size changed from 30 to 99
- Thanks to M. Mohsen Sobati for feature requests & reports

#### Version1.2.5
- Fixed Jalali Date Converter incompatibility with some themes
- Fixed Jalali Datepicker and Persian WooCommerce incompatibility

#### Version 1.2.4
- Fixed Item Wrong Price display in some cases
- Fixed Using Current Currency instead of Order Currency
- Fixed Theme Select in Advanced Invoice print from Orders metabox
- Fixed Color-Scheme Select in Advanced Invoice print from Orders metabox
- Fixed Color-Scheme Select in Setting > Theming section
- Fixed Using Default PDF Invoice template while Advanced Invoice printing
- Fixed PDF Generation link in HTML invoices

#### Version 1.2.0
- Compatibility with WordPress 5.7

#### Version 1.1.11

- Compatibility with New WooCommerce
- Compatibility with New WordPress

#### Version 1.1.10

- Default Template Translation fix

#### Version 1.1.9

- Added PDF Font Selector!
- Changed "Switch Color Scheme" button label to "Switch Dark-mode"
- Changed Support Email
- Added Minified CSS, JS Version
- Added Option to use Minified/Un-minified CSS, JS based on WP_DEBUG
- Changed minimum WooCommerce Required to version 4.4
- Fixed Signature showing despite it's option
- Fixed Showing Wrong Signature if no signature set
- Fixed Retrieving Email Addresses on Success/Error send

#### Version 1.1.8

- Fixed get customer full name
- Changed Plugin Icon and WordPress Banners
- Fix Showing product discount in PDF
- Fix Showing product weight in PDF
- Fix Showing product tax in PDF

#### Version 1.1.7

- Added Compatibility to Pepro WooCommerce Receipt Upload (available upon request to support@pepro.dev)
- Added Custom CSS Style for PDF Invoices
- Added Option to show/hide Paid Date
- Added Option to show/hide Purchase Complete Date
- Added Option to show/hide Shipping Date
- Added Option to show/hide Order Status
- Fixed Showing Wrong Discount Amount
- Added Showing Discount Percentage
- Added Option to get Current/Applied price in Invoices
- Fixed Translating WooCommerce Weight/Dimensions Units
- Fixed Force Positioning Currency and Price (now follows WooCommerce Setting)
- Added Compatibility to Templates to work with new updates options
- Fixed Templates Structure
- Fixed Order Status Naming
- Enhanced PDF Generation
- Fixed Images not loading in PDF
- Changed Templates default preview images

#### Version 1.1.6

- Fixed Dark-mode
- Fixed Barcode Generator
- Fixed LTR PDF Template Barcode Size
- Fixed PDF Font for EN sites
- Fixed Force WC Email Colors from Ultimate Invoice

#### Version 1.1.0

- Added Integration with Dokan (available upon request to support@pepro.dev)
- Added Multiple-template
- Added Templates Color scheme editor
- Added Separated template for pre-invoice
- Added Show/Hide Shipping Date on orders
- Added Show/Hide Shipping Track Code on orders
- Added Sub-menu under WooCommerce menu
- Added hooks so you can bundle your invoice template with your wordpress theme
- Added Readme details, typo fixed
- Fixed Unique Identification Number not saving for new users
- Fixed Color-picker on setting page

#### Version 1.0.3

- Added hook: pepro_ultimate_invoice_orders_column_data
- Added hook: pepro-ultimate-invoice-orders-action
- Removed Appsero integration

#### Version 1.0.2

- Directory Index Blocked for resources

#### Version 1.0.1

- readme update
- Appsero tracking integration
- cursor fix in setting page

#### Version 1.0

- initial release