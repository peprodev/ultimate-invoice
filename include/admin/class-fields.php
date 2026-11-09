<?php
/*
 * @Author: Amirhossein Hosseinpour <https://amirhp.com>
 * @Date Created: 2026/10/09 20:05:12
 * @Last modified by: amirhp-com <its@amirhp.com>
 */

namespace peproulitmateinvoice;

defined("ABSPATH") or die("Pepro Ultimate Invoice :: Unauthorized Access!");

if (!class_exists("PeproUltimateInvoice_Fields")) {
  /**
   * renders a WooCommerce settings array with our own markup
   *
   * the field definitions stay exactly as WC_Admin_Settings::save_fields() expects
   * them, only the html around them is ours
   */
  class PeproUltimateInvoice_Fields {

    /** @var string id of the section being rendered, used for the search box */
    protected static $section = "";

    /** @var int a select with this many choices or fewer becomes radio cards */
    protected static $card_choice_limit = 4;

    /** @var array tab key => label for the section being rendered */
    protected static $tabs = array();

    /** @var string the tab pane currently open, "" when none */
    protected static $pane = "";

    public static function render($settings, $section = "") {
      self::$section = $section;
      self::$tabs    = array();
      self::$pane    = "";
      $queue = array();
      // a settings array with a title and no sectionend used to leak an open div
      // and swallow every panel after it, so the wrapper is tracked here instead
      $open = false;

      $settings = array_values((array) $settings);
      for ($at = 0; $at < count($settings); $at++) {
        $field = $settings[$at];
        $type  = isset($field["type"]) ? $field["type"] : "";

        if ("title" === $type) {
          self::flush($queue);
          self::tab_to("");
          if ($open) {
            self::close_section();
          }
          self::open_section($field);
          self::$tabs = !empty($field["puiw_tabs"]) && is_array($field["puiw_tabs"]) ? $field["puiw_tabs"] : array();
          $open = true;
          continue;
        }
        if ("sectionend" === $type) {
          self::flush($queue);
          self::tab_to("");
          if ($open) {
            self::close_section();
            $open = false;
          }
          continue;
        }

        if ("puiw_skip" === $type) {
          continue;
        }
        // a block an add-on draws itself, a full row with no label column
        if ("puiw_html" === $type) {
          self::flush($queue);
          printf('<div class="puiw-field puiw-field-html is-stacked" data-field="%s">%s</div>', esc_attr(isset($field["id"]) ? $field["id"] : ""), isset($field["html"]) ? $field["html"] : "");
          continue;
        }

        // a long section is split into tabs; each field names the one it sits in
        $tab = isset($field["puiw_tab"], self::$tabs[$field["puiw_tab"]]) ? (string) $field["puiw_tab"] : "";
        if ($tab !== self::$pane) {
          self::flush($queue);
          self::tab_to($tab);
        }

        // three colour pickers and the scheme list after them read as one control
        if (self::is_color($field) && !empty($field["name"])) {
          $set = array($field);
          while (isset($settings[$at + 1]) && count($set) < 3 && self::is_color($settings[$at + 1]) && empty($settings[$at + 1]["name"])) {
            $set[] = $settings[++$at];
          }
          $swatch = null;
          if (isset($settings[$at + 1]["class"]) && false !== strpos((string) $settings[$at + 1]["class"], "swatch-select")) {
            $swatch = $settings[++$at];
          }
          self::flush($queue);
          self::color_set($field, $set, $swatch);
          continue;
        }

        if ("checkbox" === $type) {
          $queue[] = $field;
          continue;
        }
        self::flush($queue);
        self::field($field);
      }
      self::flush($queue);
      self::tab_to("");
      if ($open) {
        self::close_section();
      }
    }

    /**
     * close the open tab pane and open the next; the tab bar is printed when the
     * first pane of a section opens
     *
     * @method tab_to
     * @param  string $tab "" closes the tabs
     */
    protected static function tab_to($tab) {
      if ($tab === self::$pane) {
        return;
      }
      $first = "" === self::$pane;
      if (!$first) {
        echo "</div>";
      }
      if ("" === $tab) {
        echo "</div>";
        self::$pane = "";
        return;
      }
      if ($first) {
        $id = "puiw-tabs-" . substr(md5(self::$section . $tab), 0, 8);
        printf('<div class="puiw-tabs" id="%s"><div class="puiw-tabbar" role="tablist">', esc_attr($id));
        $n = 0;
        foreach (self::$tabs as $key => $label) {
          printf('<button type="button" class="puiw-tab%1$s" role="tab" data-tab="%2$s" aria-selected="%3$s">%4$s</button>',
            0 === $n ? " is-active" : "", esc_attr($key), 0 === $n ? "true" : "false", esc_html($label));
          $n++;
        }
        echo "</div>";
      }
      $is_first_tab = self::$tabs ? (string) key(self::$tabs) === $tab : $first;
      printf('<div class="puiw-tabpane%1$s" role="tabpanel" data-tab="%2$s"%3$s>', $is_first_tab ? " is-active" : "", esc_attr($tab), $is_first_tab ? "" : " hidden");
      self::$pane = $tab;
    }

    /**
     * checkboxes collected so far go out as one grid of cards
     *
     * @method flush
     * @param  array $queue
     */
    protected static function flush(&$queue) {
      if (empty($queue)) {
        return;
      }
      $cards = $queue;
      $queue = array();
      $id    = "puiw-cards-" . substr(md5(self::$section . $cards[0]["id"]), 0, 8);
      // a board of show/hide switches shares one hint; those cards drop the
      // redundant "Show" from their names
      $hints  = array_count_values(array_filter(array_map(array(__CLASS__, "card_hint"), $cards)));
      arsort($hints);
      $shared = $hints && reset($hints) >= 3 ? (string) key($hints) : "";
      ?>
      <div class="puiw-switchboard" id="<?php echo esc_attr($id); ?>">
        <?php if (count($cards) > 5) { ?>
          <div class="puiw-switchboard-search">
            <?php echo PeproUltimateInvoice_Icons::get("magnifying-glass"); ?>
            <input type="search" class="puiw-card-filter" autocomplete="off" placeholder="<?php echo esc_attr(_x("Search…", "wc-setting", "pepro-ultimate-invoice")); ?>" />
            <span class="puiw-switchboard-count"></span>
          </div>
        <?php } ?>
        <div class="puiw-switchboard-grid">
          <?php foreach ($cards as $field) { self::toggle_card($field, $shared); } ?>
        </div>
        <p class="puiw-switchboard-empty" hidden><?php echo esc_html(_x("Nothing matches that.", "wc-setting", "pepro-ultimate-invoice")); ?></p>
      </div>
      <?php
    }

    /**
     * one on/off setting as a card
     *
     * @method toggle_card
     * @param  array $field
     */
    protected static function toggle_card($field, $shared = "") {
      $id      = $field["id"];
      $value   = \WC_Admin_Settings::get_option($id, isset($field["default"]) ? $field["default"] : "no");
      $checked = "yes" === $value;
      $hint    = self::card_hint($field);
      $in_set  = "" !== $shared && $hint === $shared;
      $name    = isset($field["name"]) ? (string) $field["name"] : "";
      // a show/hide switch already says "show" in the board's own hint
      if ($in_set || 0 === strpos($id, "puiw_show_")) {
        $name = trim(preg_replace('/^(' . preg_quote(_x("Show", "wc-setting-toggle-prefix", "pepro-ultimate-invoice"), "/") . '|Show|نمایش)\s+/u', "", $name));
        $name = function_exists("mb_strtoupper") ? mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1) : ucfirst($name);
      }
      // shown but out of reach, such as WPML options while WPML is not installed
      $locked  = !empty($field["puiw_disabled"]);
      ?>
      <label class="puiw-card-toggle<?php echo $checked ? " is-on" : ""; ?><?php echo $locked ? " is-locked" : ""; ?>" for="<?php echo esc_attr($id); ?>">
        <input type="checkbox" name="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($id); ?>" value="1" <?php checked($checked); ?> <?php disabled($locked); ?> />
        <span class="puiw-card-toggle-switch" aria-hidden="true"></span>
        <span class="puiw-card-toggle-body">
          <span class="puiw-card-toggle-name"><?php echo esc_html($name); ?></span>
          <?php if ($hint) { ?><span class="puiw-card-toggle-hint"><?php echo esc_html(wp_strip_all_tags($hint)); ?></span><?php } ?>
        </span>
      </label>
      <?php
    }

    protected static function is_color($field) {
      return !empty($field["class"]) && false !== strpos((string) $field["class"], "wc-color-picker");
    }

    /**
     * primary, secondary and tertiary colour with the saved schemes as cards
     *
     * @method color_set
     * @param  array      $head   the first colour field, it names the row
     * @param  array      $colors the colour fields
     * @param  array|null $swatch the scheme select that fills them
     */
    protected static function color_set($head, $colors, $swatch) {
      $names = array(
        _x("Primary", "wc-setting", "pepro-ultimate-invoice"),
        _x("Secondary", "wc-setting", "pepro-ultimate-invoice"),
        _x("Tertiary", "wc-setting", "pepro-ultimate-invoice"),
      );
      $tip = !empty($head["desc_tip"]) && is_string($head["desc_tip"]) ? $head["desc_tip"] : "";
      ?>
      <div class="puiw-field puiw-field-colors is-stacked" data-field="<?php echo esc_attr($head["id"]); ?>">
        <div class="puiw-field-label">
          <label for="<?php echo esc_attr($head["id"]); ?>"><?php echo esc_html($head["name"]); ?></label>
          <?php if ($tip) { ?><span class="puiw-field-tip"><?php echo esc_html(wp_strip_all_tags($tip)); ?></span><?php } ?>
        </div>
        <div class="puiw-field-input">
          <?php if ($swatch) {
            $attrs = "";
            foreach ((array) (isset($swatch["custom_attributes"]) ? $swatch["custom_attributes"] : array()) as $key => $val) {
              $attrs .= sprintf(' %s="%s"', esc_attr($key), esc_attr($val));
            }
            // the select stays the store the scripts fill and read; the cards drive it
            printf('<select name="%1$s" id="%1$s" class="swatch-select puiw-swatch-store" data-colors="%2$s"%3$s hidden></select>',
              esc_attr($swatch["id"]), esc_attr(wp_json_encode(wp_list_pluck($colors, "id"))), $attrs);
            ?>
            <div class="puiw-swatchcards" data-for="<?php echo esc_attr($swatch["id"]); ?>"></div>
            <p class="puiw-field-desc"><a href="#puiw-color" data-panel="color"><?php echo esc_html(_x("Add or edit colour schemes", "wc-setting", "pepro-ultimate-invoice")); ?></a></p>
          <?php } ?>
          <?php // the schemes are the choice here; the colours themselves are edited under Colour Schemes ?>
          <div class="puiw-colorrow" hidden>
            <?php foreach ($colors as $n => $color) {
              $value = \WC_Admin_Settings::get_option($color["id"], isset($color["default"]) ? $color["default"] : "");
              ?>
              <?php // not a <label>: the WordPress picker hides the text of a label wrapping its input ?>
              <div class="puiw-colorpick">
                <span class="puiw-colorpick-name"><?php echo esc_html(isset($names[$n]) ? $names[$n] : ""); ?></span>
                <span class="puiw-colorpick-row"><input type="text" name="<?php echo esc_attr($color["id"]); ?>" id="<?php echo esc_attr($color["id"]); ?>" value="<?php echo esc_attr($value); ?>" class="<?php echo esc_attr($color["class"]); ?>" dir="ltr" data-scheme="<?php echo esc_attr($swatch ? $swatch["id"] : ""); ?>" data-puiw-color-label="<?php echo esc_attr(isset($names[$n]) ? $names[$n] : ""); ?>" aria-label="<?php echo esc_attr(isset($names[$n]) ? $names[$n] : ""); ?>" /></span>
              </div>
            <?php } ?>
          </div>
        </div>
      </div>
      <?php
    }

    /**
     * the line under a switch's name
     *
     * @method card_hint
     * @param  array $field
     * @return string
     */
    public static function card_hint($field) {
      foreach (array("desc_tip", "desc") as $key) {
        if (!empty($field[$key]) && is_string($field[$key])) {
          return $field[$key];
        }
      }
      return "";
    }

    protected static function open_section($field) {
      ?>
      <div class="puiw-fieldset">
        <?php if (!empty($field["name"])) { ?>
          <h2><?php echo esc_html($field["name"]); ?></h2>
        <?php } ?>
        <?php if (!empty($field["desc"])) { ?>
          <p class="puiw-panel-intro"><?php echo wp_kses_post($field["desc"]); ?></p>
        <?php } ?>
      <?php
    }

    protected static function close_section() {
      echo "</div>";
    }

    /**
     * every non checkbox field
     *
     * @method field
     * @param  array $field
     */
    protected static function field($field) {
      $type    = isset($field["type"]) ? $field["type"] : "text";
      $options = isset($field["options"]) && is_array($field["options"]) ? $field["options"] : array();
      // a short list of choices reads better as cards than as a dropdown
      if ("select" === $type && count($options) > 1 && (count($options) <= self::$card_choice_limit || !empty($field["puiw_cards"])) && empty($field["puiw_keep_select"])) {
        $type = "radio";
      }
      // and a short multi choice list as ticked cards, so every choice is visible
      if ("multiselect" === $type && count($options) > 0 && count($options) <= 16 && empty($field["puiw_keep_select"])) {
        $type = "puiw_checkcards";
      }
      // every field reads label | control; the template picker and a long set of
      // choice cards take the whole row with the label above, since squeezed into
      // the control column their texts wrap into four-line cards
      $stacked   = in_array($type, array("puiw_template", "puiw_note"), true)
        || (in_array($type, array("radio", "puiw_checkcards"), true) && count($options) > 4)
        || !empty($field["puiw_code"]);
      $code    = !empty($field["puiw_code"]) ? (string) $field["puiw_code"] : "";
      $id      = isset($field["id"]) ? $field["id"] : "";
      $default = isset($field["default"]) ? $field["default"] : "";
      $value   = "" === $id ? "" : \WC_Admin_Settings::get_option($id, $default);
      $tip     = !empty($field["desc_tip"]) && is_string($field["desc_tip"]) ? $field["desc_tip"] : "";
      $desc    = !empty($field["desc"]) ? $field["desc"] : "";
      $class   = isset($field["class"]) ? $field["class"] : "";
      $attrs   = "";
      if (!empty($field["custom_attributes"]) && is_array($field["custom_attributes"])) {
        foreach ($field["custom_attributes"] as $key => $val) {
          $attrs .= sprintf(' %s="%s"', esc_attr($key), esc_attr($val));
        }
      }
      if (!empty($field["placeholder"])) {
        $attrs .= sprintf(' placeholder="%s"', esc_attr($field["placeholder"]));
      }
      ?>
      <?php
      // a field an add-on owns carries puiw_pro; without the add-on it still shows,
      // marked with a crown and switched off, so a shop can see what PRO adds
      $is_pro_field = !empty($field["puiw_pro"]);
      $pro_locked   = ($is_pro_field && !apply_filters("puiw_is_pro", false)) || !empty($field["puiw_disabled"]);
      if ($pro_locked) {
        $attrs .= ' disabled="disabled"';
      }
      ?>
      <?php
      // a field that only matters for one choice of another field, such as a
      // provider's api key, says so and stays hidden until that choice is made
      $show_if = !empty($field["puiw_show_if"]) && is_array($field["puiw_show_if"]) ? $field["puiw_show_if"] : array();
      $show_on = $show_if ? sprintf(' data-show-if="%s"', esc_attr(wp_json_encode($show_if))) : "";
      if ($show_if) {
        foreach ($show_if as $other => $wanted) {
          $current = \WC_Admin_Settings::get_option($other, "");
          if (!in_array((string) $current, array_map("strval", (array) $wanted), true)) {
            $show_on .= " hidden";
            break;
          }
        }
      }
      ?>
      <div class="puiw-field puiw-field-<?php echo esc_attr($type); ?><?php echo $stacked ? " is-stacked" : ""; ?><?php echo $is_pro_field ? " is-pro-field" : ""; ?><?php echo $pro_locked ? " is-locked" : ""; ?><?php echo $code ? " is-code" : ""; ?>" data-field="<?php echo esc_attr($id); ?>"<?php echo $show_on; ?>>
        <?php if ("puiw_note" !== $type) { ?>
        <div class="puiw-field-label">
          <label for="<?php echo esc_attr($id); ?>">
            <?php echo esc_html(isset($field["name"]) ? $field["name"] : ""); ?>
            <?php if ($is_pro_field) { ?>
              <span class="puiw-pro-mark" title="<?php echo esc_attr(_x("A PRO feature", "wc-setting", "pepro-ultimate-invoice")); ?>"><?php echo PeproUltimateInvoice_Icons::get("crown-simple"); ?></span>
            <?php } ?>
          </label>
          <?php if ($tip) { ?><span class="puiw-field-tip"><?php echo esc_html(wp_strip_all_tags($tip)); ?></span><?php } ?>
        </div>
        <?php } ?>
        <div class="puiw-field-input">
          <?php
          switch ($type) {
            case "puiw_image":
              self::image_control($id, $value, $attrs);
              break;
            case "puiw_template":
              self::template_cards($id, $value, isset($field["puiw_filter"]) ? $field["puiw_filter"] : "");
              break;
            case "textarea":
              // a code field is a plain textarea until its tab is opened, then the editor takes over
              printf('<textarea name="%1$s" id="%1$s" rows="%2$d" class="puiw-textarea %3$s"%4$s%6$s>%5$s</textarea>',
                esc_attr($id), isset($field["rows"]) ? (int) $field["rows"] : 6, esc_attr(trim($class . ($code ? " puiw-code" : ""))), $attrs, esc_textarea($value),
                $code ? sprintf(' data-code="%s"', esc_attr($code)) : "");
              break;
            case "select":
            case "multiselect":
              $multiple = "multiselect" === $type;
              // a single choice is a plain select styled like every other control; the
              // select2 box WooCommerce adds came with its own narrow dropdown and font
              if (!$multiple) {
                $class = trim(str_replace(array("wc-enhanced-select", "chosen_select"), "", $class));
              }
              printf('<select name="%1$s%2$s" id="%1$s" class="%3$s"%4$s%5$s>',
                esc_attr($id), $multiple ? "[]" : "", esc_attr($class . ($multiple ? " wc-enhanced-select" : "")), $multiple ? " multiple" : "", $attrs);
              foreach ($options as $key => $label) {
                $selected = $multiple ? in_array((string) $key, array_map("strval", (array) $value), true) : ((string) $value === (string) $key);
                printf('<option value="%s"%s>%s</option>', esc_attr($key), $selected ? " selected" : "", esc_html($label));
              }
              echo "</select>";
              break;
            case "radio":
              printf('<div class="puiw-choicegrid" data-count="%d">', count($options));
              foreach ($options as $key => $label) {
                $on = (string) $value === (string) $key;
                printf('<label class="puiw-choice%1$s"><input type="radio" name="%2$s" value="%3$s"%4$s /><span class="puiw-choice-dot" aria-hidden="true"></span><span class="puiw-choice-text">%5$s</span></label>',
                  $on ? " is-on" : "", esc_attr($id), esc_attr($key), checked($on, true, false), esc_html($label));
              }
              echo "</div>";
              break;
            case "puiw_note":
              printf('<div class="puiw-note">%s<span>%s</span></div>', PeproUltimateInvoice_Icons::get("info"), wp_kses_post($desc));
              $desc = "";
              break;
            case "puiw_checkcards":
              $chosen = array_map("strval", (array) $value);
              printf('<div class="puiw-choicegrid is-multi" data-count="%d">', count($options));
              foreach ($options as $key => $label) {
                $on = in_array((string) $key, $chosen, true);
                printf('<label class="puiw-choice is-check%1$s"><input type="checkbox" name="%2$s[]" value="%3$s"%4$s%5$s /><span class="puiw-choice-dot" aria-hidden="true"></span><span class="puiw-choice-text">%6$s</span></label>',
                  $on ? " is-on" : "", esc_attr($id), esc_attr($key), checked($on, true, false), $pro_locked ? ' disabled="disabled"' : "", esc_html($label));
              }
              echo "</div>";
              break;
            case "number":
            case "password":
            case "email":
            case "color":
            case "text":
            default:
              printf('<input type="%1$s" name="%2$s" id="%2$s" value="%3$s" class="%4$s"%5$s />',
                esc_attr(in_array($type, array("number", "email", "color", "password"), true) ? $type : "text"),
                esc_attr($id), esc_attr($value), esc_attr($class), $attrs);
              break;
          }

          if (!empty($field["puiw_chips"]) && is_array($field["puiw_chips"])) {
            self::macros($id, $field["puiw_chips"], isset($field["puiw_chips_mode"]) ? $field["puiw_chips_mode"] : "insert");
          }
          if ($desc && "checkbox" !== $type) {
            printf('<p class="puiw-field-desc">%s</p>', wp_kses_post($desc));
          }
          ?>
        </div>
      </div>
      <?php
    }

    /**
     * the values a field accepts, as labelled tokens to insert; a flat list or
     * groups of lists ("Order" => array(token => label)), filterable once it
     * grows past a handful. "insert" drops the token at the cursor, "list" adds
     * it to a comma separated value once.
     *
     * @method macros
     * @param  string $id    the field the tokens belong to
     * @param  array  $chips
     * @param  string $mode  insert|list
     */
    public static function macros($id, $chips, $mode = "insert") {
      $groups = array();
      foreach ($chips as $key => $value) {
        if (is_array($value)) {
          $groups[(string) $key] = $value;
        } else {
          $groups[""][$key] = $value;
        }
      }
      $count = 0;
      foreach ($groups as $list) {
        $count += count($list);
      }
      if (!$count) {
        return;
      }
      $title = "list" === $mode
        ? _x("Pick to add", "wc-setting", "pepro-ultimate-invoice")
        : _x("Insert a value", "wc-setting", "pepro-ultimate-invoice");
      ?>
      <div class="puiw-macros<?php echo $count > 12 ? " is-collapsible" : ""; ?>" data-target="<?php echo esc_attr($id); ?>" data-mode="<?php echo esc_attr("list" === $mode ? "list" : "insert"); ?>">
        <div class="puiw-macros-head">
          <span class="puiw-macros-title"><?php echo PeproUltimateInvoice_Icons::get("brackets-curly"); ?><?php echo esc_html($title); ?></span>
          <?php if ($count > 8) { ?>
            <input type="search" class="puiw-macros-filter" autocomplete="off" placeholder="<?php echo esc_attr(_x("Search…", "wc-setting", "pepro-ultimate-invoice")); ?>" aria-label="<?php echo esc_attr(_x("Search values", "wc-setting", "pepro-ultimate-invoice")); ?>" />
          <?php } ?>
        </div>
        <div class="puiw-macros-body">
          <?php foreach ($groups as $group => $list) { ?>
            <div class="puiw-macros-group">
              <?php if ("" !== $group) { ?><span class="puiw-macros-group-label"><?php echo esc_html($group); ?></span><?php } ?>
              <div class="puiw-macros-list">
                <?php foreach ($list as $token => $label) {
                  printf(
                    '<button type="button" class="puiw-macro" data-insert="%1$s" data-search="%2$s" title="%3$s"><span class="puiw-macro-label">%4$s</span><code dir="ltr">%5$s</code></button>',
                    esc_attr($token),
                    esc_attr(strtolower($token . " " . $label . " " . $group)),
                    esc_attr(sprintf(_x("Insert %s", "wc-setting", "pepro-ultimate-invoice"), $token)),
                    esc_html($label),
                    esc_html($token)
                  );
                } ?>
              </div>
            </div>
          <?php } ?>
        </div>
        <?php if ($count > 12) { ?>
          <button type="button" class="button-link puiw-macros-more" data-less="<?php echo esc_attr(_x("Show fewer", "wc-setting", "pepro-ultimate-invoice")); ?>"><?php
            /* translators: %s: number of values */
            echo esc_html(sprintf(_x("Show all %s", "wc-setting", "pepro-ultimate-invoice"), number_format_i18n($count))); ?></button>
        <?php } ?>
      </div>
      <?php
    }

    /**
     * media library picker with the url, the button and the preview side by side
     *
     * @method image_control
     * @param  string $id
     * @param  string $value
     * @param  string $attrs
     */
    public static function image_control($id, $value, $attrs = "") {
      ?>
      <div class="puiw-imagepicker" data-target="<?php echo esc_attr($id); ?>">
        <span class="puiw-imagepicker-preview"<?php echo $value ? "" : " hidden"; ?>>
          <img src="<?php echo esc_url($value); ?>" alt="" />
        </span>
        <input type="url" name="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($value); ?>" class="puiw-imagepicker-input regular-text" dir="ltr"<?php echo $attrs; ?> />
        <button type="button" class="button puiw-imagepicker-pick">
          <?php echo PeproUltimateInvoice_Icons::get("image"); ?>
          <span><?php echo esc_html(_x("Choose image", "wc-setting", "pepro-ultimate-invoice")); ?></span>
        </button>
        <button type="button" class="button-link puiw-imagepicker-clear"<?php echo $value ? "" : " hidden"; ?> title="<?php echo esc_attr(_x("Clear", "wc-setting", "pepro-ultimate-invoice")); ?>">
          <?php echo PeproUltimateInvoice_Icons::get("x-circle"); ?>
        </button>
      </div>
      <?php
    }

    /**
     * invoice templates as pickable cards with their screenshot
     *
     * @method template_cards
     * @param  string $id
     * @param  string $value
     * @param  string $filter only show templates whose folder contains this
     */
    public static function template_cards($id, $value, $filter = "") {
      global $PeproUltimateInvoice;
      $templates = (array) $PeproUltimateInvoice->load_themes(1);
      $is_pro    = (bool) apply_filters("puiw_is_pro", false);
      // the chips a shop actually sorts by: reading direction, kind of paper, and
      // whether a template comes with PRO
      $facets = array(
        "rtl"     => _x("Right to left", "wc-setting", "pepro-ultimate-invoice"),
        "ltr"     => _x("Left to right", "wc-setting", "pepro-ultimate-invoice"),
        "sheet"   => _x("Sheet", "wc-setting", "pepro-ultimate-invoice"),
        "thermal" => _x("Thermal", "wc-setting", "pepro-ultimate-invoice"),
        "pos"     => _x("POS", "wc-setting", "pepro-ultimate-invoice"),
        "pro"     => _x("PRO", "wc-setting", "pepro-ultimate-invoice"),
      );
      ?>
      <div class="puiw-templatefilter">
        <span class="puiw-templatefilter-search">
          <?php echo PeproUltimateInvoice_Icons::get("magnifying-glass"); ?>
          <input type="search" class="puiw-templatesearch" autocomplete="off"
            placeholder="<?php echo esc_attr(_x("Search templates", "wc-setting", "pepro-ultimate-invoice")); ?>" />
        </span>
        <span class="puiw-templatefilter-chips">
          <?php foreach ($facets as $slug => $label) { ?>
            <button type="button" class="puiw-templatechip" data-facet="<?php echo esc_attr($slug); ?>">
              <?php echo "pro" === $slug ? PeproUltimateInvoice_Icons::get("crown-simple") : ""; ?>
              <?php echo esc_html($label); ?>
            </button>
          <?php } ?>
        </span>
      </div>
      <div class="puiw-templatecards" data-target="<?php echo esc_attr($id); ?>">
        <?php foreach ($templates as $path => $theme) {
          if ($filter && false === strpos((string) $theme["folder"], $filter)) {
            continue;
          }
          $checked = (string) $value === (string) $path;
          $tags    = isset($theme["tags"]) ? (array) $theme["tags"] : array();
          $dir     = isset($theme["direction"]) ? $theme["direction"] : "ltr";
          $pro     = !empty($theme["pro"]);
          $facet   = trim($dir . " " . implode(" ", $tags) . ($pro ? " pro" : ""));
          $haystack = strtolower(trim($theme["name"] . " " . $theme["author"] . " " . $theme["folder"] . " " . $facet));
          ?>
          <label class="puiw-templatecard<?php echo $checked ? " is-on" : ""; ?><?php echo $pro ? " is-pro" : ""; ?>"
            data-facets="<?php echo esc_attr(strtolower($facet)); ?>"
            data-search="<?php echo esc_attr($haystack); ?>">
            <input type="radio" name="<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($path); ?>" <?php checked($checked); ?> <?php disabled($pro && !$is_pro); ?> />
            <span class="puiw-templatecard-shot"><img src="<?php echo esc_url($theme["icon"]); ?>" alt="" loading="lazy" /></span>
            <?php if ($pro) { ?>
              <span class="puiw-templatecard-pro" title="<?php echo esc_attr(_x("Comes with PRO", "wc-setting", "pepro-ultimate-invoice")); ?>">
                <?php echo PeproUltimateInvoice_Icons::get("crown-simple"); ?>
              </span>
            <?php } ?>
            <span class="puiw-templatecard-meta">
              <strong><?php echo esc_html($theme["name"]); ?></strong>
              <em><?php echo esc_html($theme["author"]); ?> &middot; <?php echo esc_html($theme["version"]); ?></em>
              <span class="puiw-templatecard-tags">
                <i class="puiw-tag puiw-tag-dir"><?php echo esc_html(strtoupper($dir)); ?></i>
                <?php foreach (array_slice($tags, 0, 3) as $tag) { ?>
                  <i class="puiw-tag"><?php echo esc_html($tag); ?></i>
                <?php } ?>
              </span>
            </span>
            <span class="puiw-templatecard-check" aria-hidden="true"><?php echo PeproUltimateInvoice_Icons::get("check-circle"); ?></span>
          </label>
        <?php } ?>
        <p class="puiw-templatecards-empty" hidden><?php echo esc_html(_x("No template matches that.", "wc-setting", "pepro-ultimate-invoice")); ?></p>
      </div>
      <?php
    }
  }
}
