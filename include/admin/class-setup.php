<?php
/*
 * @Author: Amirhossein Hosseinpour <https://amirhp.com>
 * @Date Created: 2026/10/19 15:10:00
 * @Last modified by: amirhp-com <its@amirhp.com>
 */

namespace peproulitmateinvoice;

defined("ABSPATH") or die("Pepro Ultimate Invoice :: Unauthorized Access!");

if (!class_exists("PeproUltimateInvoice_Setup")) {
  /**
   * Bringing an older install up to date, and the short setup that follows.
   *
   * repair() runs once per version change and only reshapes values the new
   * screens cannot read (a status list stored as one string, a choice that was
   * renamed, a template folder that moved). The wizard then walks the shop
   * through the settings that matter most; it opens by itself after a major
   * update or a fresh install and can be started again from the dashboard.
   */
  class PeproUltimateInvoice_Setup {

    const PENDING = "puiw_setup_pending";
    const REPORT  = "puiw_upgrade_report";

    /**
     * values that were renamed between versions: option => old => new
     *
     * @method value_maps
     * @return array
     */
    public static function value_maps() {
      return apply_filters("puiw_upgrade_value_maps", array(
        // 2.x stored yes/no; 3.x tells the QR code and the barcode apart, and yes
        // always printed both
        "puiw_show_qr_code_id"       => array("yes" => "both"),
        // 2.x stored yes for "use Persian digits"
        "puiw_force_persian_numbers" => array("yes" => "farsi"),
        // both were once saved as on/off switches by mistake; "yes" meant print them
        "puiw_show_order_note"       => array("yes" => "note_provided_by_both", "no" => "hide_note_from_invoice"),
        "puiw_show_shipping_address" => array("yes" => "billing", "no" => "billing"),
      ));
    }

    /**
     * every field definition the settings screen knows, keyed by option name
     *
     * @method fields
     * @return array
     */
    public static function fields() {
      static $fields = null;
      if (null !== $fields) {
        return $fields;
      }
      $fields = array();
      $page   = self::page();
      if (!$page) {
        return $fields;
      }
      foreach (array_unique(array_merge(array("general"), $page->field_sections())) as $section) {
        foreach ((array) $page->get_settings($section) as $field) {
          if (!empty($field["id"]) && !empty($field["type"]) && !in_array($field["type"], array("title", "sectionend"), true)) {
            $fields[$field["id"]] = $field + array("puiw_section" => $section);
          }
        }
      }
      return $fields;
    }

    /**
     * the settings page object, built on demand outside the settings screen
     *
     * @method page
     * @return object|null
     */
    public static function page() {
      if (empty($GLOBALS["puiw_settings_page"])) {
        if (!class_exists("WC_Admin_Settings") && defined("WC_ABSPATH")) {
          include_once WC_ABSPATH . "includes/admin/class-wc-admin-settings.php";
        }
        if (class_exists("WC_Admin_Settings")) {
          \WC_Admin_Settings::get_settings_pages();
        }
      }
      return isset($GLOBALS["puiw_settings_page"]) ? $GLOBALS["puiw_settings_page"] : null;
    }

    /**
     * major part of a version string, 0 when there is none
     */
    public static function major($version) {
      return (int) strtok((string) $version, ".");
    }

    /**
     * called on every version change with the version the shop came from
     *
     * @method upgrade
     * @param  string $from empty on a fresh install
     * @param  string $to
     */
    public static function upgrade($from, $to) {
      $report = array("from" => (string) $from, "to" => (string) $to, "time" => time(), "fixed" => array(), "open" => array());
      if ("" !== (string) $from) {
        $report = self::repair($report);
      }
      // a fresh install and a new major version both get the setup
      if ("" === (string) $from || self::major($from) < self::major($to)) {
        update_option(self::PENDING, "yes", false);
      }
      update_option(self::REPORT, $report, false);
    }

    /**
     * reshape what an older version stored into what this one reads
     *
     * @method repair
     * @param  array $report
     * @return array the report with what was fixed and what still needs a look
     */
    public static function repair($report = array()) {
      $report += array("fixed" => array(), "open" => array());

      // early 3.2 builds stored the first template in the list for every language
      // the first time the settings were saved, which pinned every invoice to Default
      // whatever was picked under Theming
      $langs = (array) get_option("puiw_lang_template", array());
      if ($langs && 1 === count(array_unique(array_map("untrailingslashit", $langs)))
        && in_array(basename(untrailingslashit((string) reset($langs))), array("default", "default-rtl"), true)) {
        delete_option("puiw_lang_template");
        $report["fixed"][] = array("id" => "puiw_lang_template", "label" => _x("Template per language", "setup", "pepro-ultimate-invoice"), "from" => basename((string) reset($langs)), "to" => _x("the template picked under Theming", "setup", "pepro-ultimate-invoice"));
      }
      $maps    = self::value_maps();
      foreach (self::fields() as $id => $field) {
        $stored = get_option($id, null);
        if (null === $stored) {
          continue;
        }
        $type    = $field["type"];
        $options = isset($field["options"]) && is_array($field["options"]) ? $field["options"] : array();
        $label   = isset($field["name"]) && "" !== $field["name"] ? wp_strip_all_tags($field["name"]) : $id;

        if (isset($maps[$id]) && is_scalar($stored) && isset($maps[$id][(string) $stored])) {
          update_option($id, $maps[$id][(string) $stored]);
          $report["fixed"][] = array("id" => $id, "label" => $label, "from" => (string) $stored, "to" => (string) $maps[$id][(string) $stored]);
          continue;
        }

        // a multi choice saved by 2.x as a single comma separated string
        if ("multiselect" === $type && !is_array($stored)) {
          $list = array_values(array_filter(array_map("trim", explode(",", (string) $stored)), "strlen"));
          update_option($id, $list);
          if ($list) {
            $names = array();
            foreach ($list as $one) {
              $names[] = isset($options[$one]) ? wp_strip_all_tags($options[$one]) : $one;
            }
            $report["fixed"][] = array("id" => $id, "label" => $label, "from" => (string) $stored, "to" => implode("، ", $names));
          }
          continue;
        }

        // a template folder that no longer exists where it was saved, e.g. after a
        // site move or a renamed bundled template
        if ("puiw_template" === $type && "" !== (string) $stored && !is_readable(trailingslashit((string) $stored) . "default.cfg")) {
          $found = class_exists("\\peproulitmateinvoice\\PeproUltimateInvoice_Themes")
            ? PeproUltimateInvoice_Themes::resolve(basename(untrailingslashit((string) $stored)))
            : "";
          if ($found && is_readable(trailingslashit($found) . "default.cfg")) {
            update_option($id, $found);
            $report["fixed"][] = array("id" => $id, "label" => $label, "from" => basename((string) $stored), "to" => basename($found));
          } else {
            $report["open"][] = array("id" => $id, "label" => $label, "value" => basename((string) $stored), "section" => $field["puiw_section"]);
          }
          continue;
        }

        // a single choice whose stored value is not one of today's choices
        if (in_array($type, array("select", "radio"), true) && $options && is_scalar($stored) && "" !== (string) $stored && !array_key_exists((string) $stored, $options)) {
          $report["open"][] = array("id" => $id, "label" => $label, "value" => (string) $stored, "section" => $field["puiw_section"]);
        }
      }
      return $report;
    }

    /**
     * the Alwan colour picker, self hosted, the same build and skin PigmentDev APS uses
     *
     * @method enqueue_alwan
     */
    public static function enqueue_alwan() {
      $url = PEPROULTIMATEINVOICE_ASSETS_URL;
      wp_register_script("alwan", "{$url}/vendor/alwan/alwan.min.js", array(), "2.3.1", true);
      wp_register_style("alwan", "{$url}/vendor/alwan/alwan.min.css", array(), "2.3.1");
      wp_register_script("puiw-alwan", "{$url}/admin/puiw-alwan.js", array("alwan"), PEPROULTIMATEINVOICE_VER, true);
      wp_register_style("puiw-alwan", "{$url}/admin/puiw-alwan.css", array("alwan"), PEPROULTIMATEINVOICE_VER);
      wp_localize_script("puiw-alwan", "puiwAlwanL10n", array("pick" => _x("Pick a colour", "wc-setting-js", "pepro-ultimate-invoice")));
      wp_enqueue_script("puiw-alwan");
      wp_enqueue_style("puiw-alwan");
      self::enqueue_dialog();
    }

    /**
     * our own confirm and alert dialog, standing in for jquery-confirm's
     *
     * @method enqueue_dialog
     */
    public static function enqueue_dialog() {
      $deps = wp_script_is("jquery-confirm", "registered") ? array("jquery", "jquery-confirm") : array("jquery");
      wp_enqueue_script("puiw-dialog", PEPROULTIMATEINVOICE_ASSETS_URL . "/admin/puiw-dialog.js", $deps, PEPROULTIMATEINVOICE_VER);
      wp_enqueue_style("puiw-dialog", PEPROULTIMATEINVOICE_ASSETS_URL . "/admin/puiw-dialog.css", array(), PEPROULTIMATEINVOICE_VER);
    }

    public static function is_pending() {
      return "yes" === get_option(self::PENDING, "");
    }

    public static function url($step = "") {
      $args = array("page" => "wc-settings", "tab" => "pepro_ultimate_invoice", "puiw-setup" => 1);
      if ("" !== $step) {
        $args["step"] = $step;
      }
      return add_query_arg($args, admin_url("admin.php"));
    }

    /**
     * the steps; each lists the options it shows, an add-on can add its own
     *
     * @method steps
     * @return array
     */
    public static function steps() {
      return apply_filters("puiw_setup_steps", array(
        "welcome" => array(
          "title"  => _x("Welcome", "setup", "pepro-ultimate-invoice"),
          "icon"   => "hand-waving",
          "fields" => array(),
        ),
        "store" => array(
          "title"  => _x("Your store", "setup", "pepro-ultimate-invoice"),
          "icon"   => "storefront",
          "intro"  => _x("What the top of every invoice says about you.", "setup", "pepro-ultimate-invoice"),
          "fields" => array("puiw_store_name", "puiw_store_logo", "puiw_store_website", "puiw_store_email", "puiw_store_phone", "puiw_store_address", "puiw_store_postcode", "puiw_store_national_id"),
        ),
        "look" => array(
          "title"  => _x("Look", "setup", "pepro-ultimate-invoice"),
          "icon"   => "palette",
          "intro"  => _x("Pick a template and its colours, then how numbers and dates are written.", "setup", "pepro-ultimate-invoice"),
          "fields" => array("puiw_template", "puiw_theme_color", "puiw_theme_color2", "puiw_theme_color3", "puiw_swatch", "puiw_pdf_font", "puiw_force_persian_numbers", "puiw_date_shamsi"),
        ),
        "content" => array(
          "title"  => _x("What to print", "setup", "pepro-ultimate-invoice"),
          "icon"   => "list-checks",
          "intro"  => _x("The details most shops decide on first. Everything else is in the settings.", "setup", "pepro-ultimate-invoice"),
          "fields" => array("puiw_show_product_image", "puiw_show_product_sku", "puiw_show_product_weight", "puiw_show_product_dimensions", "puiw_show_customer_phone", "puiw_show_customer_email", "puiw_show_payment_method", "puiw_show_shipping_method", "puiw_show_signatures", "puiw_show_qr_code_id", "puiw_show_shipping_address"),
        ),
        "delivery" => array(
          "title"  => _x("Delivery", "setup", "pepro-ultimate-invoice"),
          "icon"   => "envelope-simple",
          "intro"  => _x("When invoices go out by e-mail, and what customers can open from their account.", "setup", "pepro-ultimate-invoice"),
          "fields" => array("puiw_send_invoices_via_email", "puiw_send_invoices_via_email_opt", "puiw_attach_pdf_invoices_to_mail", "puiw_allow_users_have_invoices", "puiw_allow_pdf_customer"),
        ),
        "done" => array(
          "title"  => _x("Done", "setup", "pepro-ultimate-invoice"),
          "icon"   => "check-circle",
          "fields" => array(),
        ),
      ));
    }

    /**
     * the field definitions for one step, in the step's order
     */
    public static function step_fields($step) {
      $steps = self::steps();
      $all   = self::fields();
      $out   = array();
      foreach (isset($steps[$step]["fields"]) ? (array) $steps[$step]["fields"] : array() as $id) {
        if (isset($all[$id])) {
          $out[] = $all[$id];
        }
      }
      return $out;
    }

    /**
     * ajax: store one step's fields and nothing else
     *
     * @method ajax_save_step
     */
    public static function ajax_save_step() {
      if (!current_user_can("manage_woocommerce")) {
        wp_send_json_error(array("message" => __("Unauthorized Access Denied!", "pepro-ultimate-invoice")), 403);
      }
      check_ajax_referer("puiw-setup", "nonce");
      $step  = isset($_POST["step"]) ? sanitize_key(wp_unslash($_POST["step"])) : "";
      $form  = array();
      parse_str(isset($_POST["form"]) ? (string) wp_unslash($_POST["form"]) : "", $form);
      $fields = self::step_fields($step);
      if ($fields) {
        $keep  = $_POST;
        $_POST = wp_slash($form);
        \WC_Admin_Settings::save_fields($fields);
        $_POST = $keep;
      }
      if ("done" === $step || !empty($_POST["finish"])) {
        delete_option(self::PENDING);
      }
      do_action("puiw_setup_step_saved", $step, $form);
      wp_send_json_success(array("next" => self::url(self::next_step($step))));
    }

    public static function next_step($step) {
      $keys = array_keys(self::steps());
      $at   = array_search($step, $keys, true);
      return false === $at || !isset($keys[$at + 1]) ? end($keys) : $keys[$at + 1];
    }

    public static function previous_step($step) {
      $keys = array_keys(self::steps());
      $at   = array_search($step, $keys, true);
      return $at ? $keys[$at - 1] : "";
    }

    /**
     * a notice on every admin screen until the setup is done or put off
     *
     * @method notice
     */
    public static function notice() {
      if (!self::is_pending() || !current_user_can("manage_woocommerce") || !empty($_GET["puiw-setup"])) {
        return;
      }
      $report = (array) get_option(self::REPORT, array());
      $from   = isset($report["from"]) ? (string) $report["from"] : "";
      $text   = "" === $from
        ? __("PeproDev Ultimate Invoice is installed. A short setup gets your first invoice looking right.", "pepro-ultimate-invoice")
        : sprintf(
          /* translators: 1: old version, 2: new version */
          __("PeproDev Ultimate Invoice was updated from %1\$s to %2\$s. Your settings were carried over; a short setup walks you through what changed.", "pepro-ultimate-invoice"),
          $from,
          isset($report["to"]) ? $report["to"] : ""
        );
      printf(
        '<div class="notice notice-info puiw-setup-notice"><p>%1$s</p><p><a class="button button-primary" href="%2$s">%3$s</a> <a class="button-link" href="%4$s">%5$s</a></p></div>',
        esc_html($text),
        esc_url(self::url()),
        esc_html__("Start the setup", "pepro-ultimate-invoice"),
        esc_url(wp_nonce_url(add_query_arg("puiw-setup-dismiss", 1), "puiw-setup-dismiss")),
        esc_html__("Not now", "pepro-ultimate-invoice")
      );
    }

    public static function maybe_dismiss() {
      // the "check after an update" button: the same repair, on demand
      if (!empty($_GET["puiw-repair"]) && current_user_can("manage_woocommerce") && check_admin_referer("puiw-repair")) {
        $report = self::repair(array("from" => "", "to" => PEPROULTIMATEINVOICE_VER, "time" => time(), "manual" => true));
        update_option(self::REPORT, $report, false);
        wp_safe_redirect(self::url("welcome"));
        exit;
      }
      if (!empty($_GET["puiw-clear-lang-templates"]) && current_user_can("manage_woocommerce") && check_admin_referer("puiw-clear-lang-templates")) {
        delete_option("puiw_lang_template");
        wp_safe_redirect(admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice#puiw-theme"));
        exit;
      }
      if (!empty($_GET["puiw-setup-dismiss"]) && current_user_can("manage_woocommerce") && check_admin_referer("puiw-setup-dismiss")) {
        delete_option(self::PENDING);
        wp_safe_redirect(remove_query_arg(array("puiw-setup-dismiss", "_wpnonce")));
        exit;
      }
    }

    /**
     * the wizard, drawn inside the settings tab so it shares its styles and scripts
     *
     * @method render
     */
    public static function render() {
      $steps = self::steps();
      $step  = isset($_GET["step"]) ? sanitize_key(wp_unslash($_GET["step"])) : "welcome";
      if (!isset($steps[$step])) {
        $step = "welcome";
      }
      $keys    = array_keys($steps);
      $index   = array_search($step, $keys, true);
      $report  = (array) get_option(self::REPORT, array());
      $icon    = function ($name) {
        return PeproUltimateInvoice_Icons::get($name);
      };
      ?>
      <div class="puiw-settings puiw-setup" data-active="setup" data-step="<?php echo esc_attr($step); ?>">
        <div class="puiw-setup-head">
          <img class="puiw-setup-logo" src="<?php echo esc_url(PEPROULTIMATEINVOICE_ASSETS_URL . "/img/ultimate-invoice-mark.svg"); ?>" alt="" />
          <div>
            <h1><?php echo esc_html__("PeproDev Ultimate Invoice setup", "pepro-ultimate-invoice"); ?></h1>
            <p><?php printf(
              /* translators: 1: step number, 2: number of steps */
              esc_html__("Step %1\$s of %2\$s", "pepro-ultimate-invoice"),
              esc_html(number_format_i18n($index + 1)),
              esc_html(number_format_i18n(count($steps)))
            ); ?></p>
          </div>
          <a class="puiw-setup-exit" href="<?php echo esc_url(admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice")); ?>"><?php echo $icon("x"); ?><span><?php echo esc_html__("Exit setup", "pepro-ultimate-invoice"); ?></span></a>
        </div>

        <ol class="puiw-setup-steps">
          <?php foreach ($keys as $i => $key) {
            $state = $i < $index ? "is-done" : ($i === $index ? "is-current" : "");
            ?>
            <li class="<?php echo esc_attr($state); ?>">
              <a href="<?php echo esc_url(self::url($key)); ?>">
                <span class="puiw-setup-dot"><?php echo $i < $index ? $icon("check") : esc_html(number_format_i18n($i + 1)); ?></span>
                <span class="puiw-setup-label"><?php echo esc_html($steps[$key]["title"]); ?></span>
              </a>
            </li>
          <?php } ?>
        </ol>

        <div class="puiw-setup-card">
          <div class="puiw-setup-card-head">
            <span class="puiw-setup-card-icon"><?php echo $icon(isset($steps[$step]["icon"]) ? $steps[$step]["icon"] : "gear"); ?></span>
            <div>
              <h2><?php echo esc_html($steps[$step]["title"]); ?></h2>
              <?php if (!empty($steps[$step]["intro"])) { ?><p><?php echo esc_html($steps[$step]["intro"]); ?></p><?php } ?>
            </div>
          </div>

          <div class="puiw-setup-body">
            <?php
            if ("welcome" === $step) {
              self::render_welcome($report);
            } elseif ("done" === $step) {
              self::render_done();
            } else {
              do_action("puiw_setup_before_step_{$step}");
              PeproUltimateInvoice_Fields::render(self::step_fields($step), "setup-{$step}");
              do_action("puiw_setup_after_step_{$step}");
            }
            ?>
          </div>

          <div class="puiw-setup-foot">
            <?php if ($index > 0) { ?>
              <a class="button puiw-setup-back" href="<?php echo esc_url(self::url(self::previous_step($step))); ?>"><?php echo esc_html__("Back", "pepro-ultimate-invoice"); ?></a>
            <?php } ?>
            <span class="puiw-setup-status" role="status" aria-live="polite"></span>
            <?php if ("done" === $step) { ?>
              <button type="button" class="button button-primary puiw-setup-next" data-step="done" data-finish="1" data-go="<?php echo esc_url(admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice")); ?>"><?php echo esc_html__("Go to the settings", "pepro-ultimate-invoice"); ?></button>
            <?php } else { ?>
              <?php if ("welcome" !== $step) { ?>
                <a class="button-link puiw-setup-skip" href="<?php echo esc_url(self::url(self::next_step($step))); ?>"><?php echo esc_html__("Skip this step", "pepro-ultimate-invoice"); ?></a>
              <?php } ?>
              <button type="button" class="button button-primary puiw-setup-next" data-step="<?php echo esc_attr($step); ?>"><?php echo "welcome" === $step ? esc_html__("Let's start", "pepro-ultimate-invoice") : esc_html__("Save and continue", "pepro-ultimate-invoice"); ?></button>
            <?php } ?>
          </div>
        </div>
        <?php wp_nonce_field("puiw-setup", "puiw_setup_nonce", false); ?>
      </div>
      <?php
    }

    protected static function render_welcome($report) {
      $from  = isset($report["from"]) ? (string) $report["from"] : "";
      $fixed = isset($report["fixed"]) ? (array) $report["fixed"] : array();
      $open  = isset($report["open"]) ? (array) $report["open"] : array();
      ?>
      <div class="puiw-setup-welcome">
        <?php $manual = !empty($report["manual"]) && !empty($report["time"]) && (time() - (int) $report["time"]) < HOUR_IN_SECONDS; ?>
        <?php if ("" !== $from || $manual) { ?>
          <p class="puiw-setup-lead"><?php if ($manual) {
            echo esc_html__("Your saved settings were checked against this version. Anything in an older format has been converted.", "pepro-ultimate-invoice");
          } else { printf(
            /* translators: %s: the version the shop updated from */
            esc_html__("You came from version %s. Everything you had set is still here; the steps ahead show the settings that matter most so you can check them against the new screens.", "pepro-ultimate-invoice"),
            '<bdi dir="ltr">' . esc_html($from) . '</bdi>'
          ); } ?></p>
          <div class="puiw-setup-report">
            <div class="puiw-setup-stat is-ok">
              <strong><?php echo esc_html(number_format_i18n(count($fixed))); ?></strong>
              <span><?php echo esc_html__("settings converted to the new format", "pepro-ultimate-invoice"); ?></span>
            </div>
            <div class="puiw-setup-stat<?php echo $open ? " is-warn" : " is-ok"; ?>">
              <strong><?php echo esc_html(number_format_i18n(count($open))); ?></strong>
              <span><?php echo esc_html__("need a choice from you", "pepro-ultimate-invoice"); ?></span>
            </div>
          </div>
          <?php if ($fixed) { ?>
            <details class="puiw-setup-details">
              <summary><?php echo esc_html__("What was converted", "pepro-ultimate-invoice"); ?></summary>
              <ul>
                <?php foreach ($fixed as $row) { ?>
                  <li><strong><?php echo esc_html($row["label"]); ?></strong> <code dir="ltr"><?php echo esc_html($row["from"]); ?></code> → <code dir="ltr"><?php echo esc_html($row["to"]); ?></code></li>
                <?php } ?>
              </ul>
            </details>
          <?php } ?>
          <?php if ($open) { ?>
            <ul class="puiw-setup-open">
              <?php foreach ($open as $row) { ?>
                <li><?php echo PeproUltimateInvoice_Icons::get("warning-circle"); ?>
                  <span><strong><?php echo esc_html($row["label"]); ?></strong> <?php printf(
                    /* translators: %s: the stored value */
                    esc_html__("is set to %s, which this version no longer offers.", "pepro-ultimate-invoice"),
                    '<code dir="ltr">' . esc_html($row["value"]) . '</code>'
                  ); ?></span>
                  <a href="<?php echo esc_url(admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice#puiw-" . $row["section"])); ?>"><?php echo esc_html__("Choose again", "pepro-ultimate-invoice"); ?></a>
                </li>
              <?php } ?>
            </ul>
          <?php } ?>
        <?php } else { ?>
          <p class="puiw-setup-lead"><?php echo esc_html__("A few questions and your invoices are ready. Every answer can be changed later in the settings.", "pepro-ultimate-invoice"); ?></p>
        <?php } ?>
        <ul class="puiw-setup-points">
          <li><?php echo PeproUltimateInvoice_Icons::get("storefront"); ?><span><?php echo esc_html__("Your store details and logo", "pepro-ultimate-invoice"); ?></span></li>
          <li><?php echo PeproUltimateInvoice_Icons::get("palette"); ?><span><?php echo esc_html__("A template, its colours and the PDF font", "pepro-ultimate-invoice"); ?></span></li>
          <li><?php echo PeproUltimateInvoice_Icons::get("list-checks"); ?><span><?php echo esc_html__("What each invoice shows", "pepro-ultimate-invoice"); ?></span></li>
          <li><?php echo PeproUltimateInvoice_Icons::get("envelope-simple"); ?><span><?php echo esc_html__("When invoices are e-mailed", "pepro-ultimate-invoice"); ?></span></li>
        </ul>
      </div>
      <?php
    }

    protected static function render_done() {
      $orders = function_exists("wc_get_orders") ? wc_get_orders(array("limit" => 1, "return" => "ids", "orderby" => "date", "order" => "DESC")) : array();
      ?>
      <div class="puiw-setup-welcome">
        <p class="puiw-setup-lead"><?php echo esc_html__("That's it. Your invoices use the settings you just chose.", "pepro-ultimate-invoice"); ?></p>
        <div class="puiw-setup-links">
          <?php if ($orders) { ?>
            <a class="puiw-setup-link" href="<?php echo esc_url(home_url("?invoice-pdf=" . (int) $orders[0])); ?>" target="_blank" rel="noopener"><?php echo PeproUltimateInvoice_Icons::get("file-pdf"); ?><span><?php echo esc_html__("Open the latest order as a PDF", "pepro-ultimate-invoice"); ?></span></a>
            <a class="puiw-setup-link" href="<?php echo esc_url(home_url("?invoice=" . (int) $orders[0])); ?>" target="_blank" rel="noopener"><?php echo PeproUltimateInvoice_Icons::get("file-html"); ?><span><?php echo esc_html__("Open it as a web page", "pepro-ultimate-invoice"); ?></span></a>
          <?php } ?>
          <a class="puiw-setup-link" href="<?php echo esc_url(admin_url("admin.php?page=wc-settings&tab=pepro_ultimate_invoice#puiw-items")); ?>"><?php echo PeproUltimateInvoice_Icons::get("sliders-horizontal"); ?><span><?php echo esc_html__("Fine tune every detail", "pepro-ultimate-invoice"); ?></span></a>
          <a class="puiw-setup-link" href="<?php echo esc_url(self::url("welcome")); ?>"><?php echo PeproUltimateInvoice_Icons::get("arrow-counter-clockwise"); ?><span><?php echo esc_html__("Run the setup again", "pepro-ultimate-invoice"); ?></span></a>
        </div>
      </div>
      <?php
    }
  }
}
