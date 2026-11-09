/*
 * @Author: Amirhossein Hosseinpour <https://amirhp.com>
 * @Date Created: 2020/10/20 22:23:23
 * @Last modified by: amirhp-com <its@amirhp.com>
 * @Last modified time: 2025/12/12 15:17:06
 */

(function($) {
  $(document).ready(function() {

    $("body").addClass("ultinateinvoicebypeprodev")

    function show_toast(data = "Sample Toast!", bg = "", delay = 4500) {
      if (!$("toast").length) {
        $(document.body).append($("<toast>"));
      } else {
        $("toast").removeClass("active");
      }
      setTimeout(function() {
        $("toast").css("--toast-bg", bg).html(data).stop().addClass("active").delay(delay).queue(function() {
          $(this).removeClass("active").dequeue().off("click tap");
        }).on("click tap", function(e) {
          e.preventDefault();
          $(this).stop().removeClass("active");
        });
      }, 200);
    }
    var $success_color = "rgba(21, 139, 2, 0.8)";
    var $error_color = "rgba(139, 2, 2, 0.8)";
    var $info_color = "rgba(2, 133, 139, 0.8)";
    if (!$("toast").length) {
      $(document.body).append($("<toast>"));
    }

    function copy_clipboard(data) {
      var $temp = $("<textarea>");
      $("body").append($temp);
      $temp.val(data).select();
      document.execCommand("copy");
      $temp.remove();
    }

    $(".forminp.forminp-radio ul").each(function(i,j) {
      if ($(this).find("li").length > 7) {
        $(this).addClass("morefive")
      }
    });

    // reading the global directly throws when another plugin re-registers our handle
    // and drops the localised data, and that takes the whole ready handler with it,
    // including the binding on the save button
    if (window._peproUltimateInvoice && window._peproUltimateInvoice.zephyrfix) {
      $("link#us-core-admin-css").remove();
      $(".usof-colpick").remove();
    }

    $(document).on("click tap", "id", function(e) {
      e.preventDefault();
      var txt = $(this).text();
      copy_clipboard(txt);
      var copied = (window._peproUltimateInvoice || {}).copied || "##";
      show_toast(copied.replace(/##/gi, txt), $success_color);
    });


    if ($(".puiw_swatch_one").length) {
      $parent = $("input.puiw_swatch_one").parents("tr").first();
      $parent_two = $("input.puiw_swatch_two").parents("td").first();
      $parent_two_p = $("input.puiw_swatch_two").parents("tr").first();
      $parent_three = $("input.puiw_swatch_three").parents("td").first();
      $parent_three_p = $("input.puiw_swatch_three").parents("tr").first();
      $parent_two.appendTo($parent);
      $parent_three.appendTo($parent);
      $parent_two_p.remove();
      $parent_three_p.remove();
      $parent.addClass("notme");
    }

    if ($(".puiw_preinvoice_swatch_one").length) {
      $parent = $("input.puiw_preinvoice_swatch_one").parents("tr").first();
      $parent_two = $("input.puiw_preinvoice_swatch_two").parents("td").first();
      $parent_two_p = $("input.puiw_preinvoice_swatch_two").parents("tr").first();
      $parent_three = $("input.puiw_preinvoice_swatch_three").parents("td").first();
      $parent_three_p = $("input.puiw_preinvoice_swatch_three").parents("tr").first();
      $parent_two.appendTo($parent);
      $parent_three.appendTo($parent);
      $parent_two_p.remove();
      $parent_three_p.remove();
      $parent.addClass("notme");
    }


    /* colour fields get the Alwan picker (assets/admin/puiw-alwan.js), which fires
       input and change on the field itself */
    if (window.PuiwAlwan) { window.PuiwAlwan.scan(document); }


    $manual = $("input[name=puiw_send_invoices_via_email]:checked").val();
    if ($manual && "automatic" == $manual) {
      $("#puiw_send_invoices_via_email_opt").parents("tr").last().show();
    } else {
      $("#puiw_send_invoices_via_email_opt").parents("tr").last().hide();
    }
    $(document).on("change", "input[name=puiw_send_invoices_via_email]", function(e) {
      $manual = $("input[name=puiw_send_invoices_via_email]:checked").val();
      if ($manual && "automatic" == $manual) {
        $("#puiw_send_invoices_via_email_opt").parents("tr").last().show();
      } else {
        $("#puiw_send_invoices_via_email_opt").parents("tr").last().hide();
      }
    });


    $manual = $("input[name=puiw_send_invoices_via_email_admin]:checked").val();
    if ($manual && "automatic" == $manual) {
      $("#puiw_send_invoices_via_email_shpmngrs").parents("tr").last().show();
      $("#puiw_send_invoices_via_email_opt_admin").parents("tr").last().show();
    } else {
      $("#puiw_send_invoices_via_email_shpmngrs").parents("tr").last().hide();
      $("#puiw_send_invoices_via_email_opt_admin").parents("tr").last().hide();
    }
    $(document).on("change", "input[name=puiw_send_invoices_via_email_admin]", function(e) {
      $manual = $("input[name=puiw_send_invoices_via_email_admin]:checked").val();
      if ($manual && "automatic" == $manual) {
        $("#puiw_send_invoices_via_email_shpmngrs").parents("tr").last().show();
        $("#puiw_send_invoices_via_email_opt_admin").parents("tr").last().show();
      } else {
        $("#puiw_send_invoices_via_email_shpmngrs").parents("tr").last().hide();
        $("#puiw_send_invoices_via_email_opt_admin").parents("tr").last().hide();
      }
    });


    $manual = $("input#puiw_allow_users_use_invoices").prop("checked");
    if ($manual) {
      $("#puiw_allow_users_use_invoices_criteria").parents("tr").last().show();
    } else {
      $("#puiw_allow_users_use_invoices_criteria").parents("tr").last().hide();
    }
    $(document).on("change", "input#puiw_allow_users_use_invoices", function(e) {
      $manual = $(this).prop("checked");
      if ($manual) {
        $("#puiw_allow_users_use_invoices_criteria").parents("tr").last().show();
      } else {
        $("#puiw_allow_users_use_invoices_criteria").parents("tr").last().hide();
      }
    });


    $manual = $("input#puiw_allow_guest_users_view_invoices").prop("checked");
    if ($manual) {
      $("#puiw_allow_pdf_guest").parents("tr").last().show();
    } else {
      $("#puiw_allow_pdf_guest").parents("tr").last().hide();
    }
    $(document).on("change", "input#puiw_allow_guest_users_view_invoices", function(e) {
      $manual = $(this).prop("checked");
      if ($manual) {
        $("#puiw_allow_pdf_guest").parents("tr").last().show();
      } else {
        $("#puiw_allow_pdf_guest").parents("tr").last().hide();
      }
    });


    $manual = $("input#puiw_allow_preorder_invoice").prop("checked");
    if ($manual) {
      $("#puiw_allow_preorder_emptycart").parents("tr").last().show();
      $("#puiw_preorder_shopmngr_extra_note").parents("tr").last().show();
      $("#puiw_preorder_customer_extra_note").parents("tr").last().show();
    } else {
      $("#puiw_allow_preorder_emptycart").parents("tr").last().hide();
      $("#puiw_preorder_shopmngr_extra_note").parents("tr").last().hide();
      $("#puiw_preorder_customer_extra_note").parents("tr").last().hide();
    }
    $(document).on("change", "input#puiw_allow_preorder_invoice", function(e) {
      $manual = $(this).prop("checked");
      if ($manual) {
        $("#puiw_allow_preorder_emptycart").parents("tr").last().show();
        $("#puiw_preorder_shopmngr_extra_note").parents("tr").last().show();
        $("#puiw_preorder_customer_extra_note").parents("tr").last().show();
      } else {
        $("#puiw_allow_preorder_emptycart").parents("tr").last().hide();
        $("#puiw_preorder_shopmngr_extra_note").parents("tr").last().hide();
        $("#puiw_preorder_customer_extra_note").parents("tr").last().hide();
      }
    });


    $manual = $("input#puiw_allow_users_have_invoices").prop("checked");
    if ($manual) {
      $("#puiw_allow_pdf_customer").parents("tr").last().show();
      $("#puiw_allow_users_use_invoices").parents("tr").last().show();
      $manual = $("input#puiw_allow_users_use_invoices").prop("checked");
      if ($manual) {
        $("#puiw_allow_users_use_invoices_criteria").parents("tr").last().show();
      } else {
        $("#puiw_allow_users_use_invoices_criteria").parents("tr").last().hide();
      }
    } else {
      $("#puiw_allow_pdf_customer").parents("tr").last().hide();
      $("#puiw_allow_users_use_invoices").parents("tr").last().hide();
      $("#puiw_allow_users_use_invoices_criteria").parents("tr").last().hide();
    }
    $(document).on("change", "input#puiw_allow_users_have_invoices", function(e) {
      $manual = $(this).prop("checked");
      if ($manual) {
        $("#puiw_allow_pdf_customer").parents("tr").last().show();
        $("#puiw_allow_users_use_invoices").parents("tr").last().show();
        $manual = $("input#puiw_allow_users_use_invoices").prop("checked");
        if ($manual) {
          $("#puiw_allow_users_use_invoices_criteria").parents("tr").last().show();
        } else {
          $("#puiw_allow_users_use_invoices_criteria").parents("tr").last().hide();
        }
      } else {
        $("#puiw_allow_pdf_customer").parents("tr").last().hide();
        $("#puiw_allow_users_use_invoices").parents("tr").last().hide();
        $("#puiw_allow_users_use_invoices_criteria").parents("tr").last().hide();
      }
    });

    $("#puiw_send_invoices_via_email").parents("tr").last().css("border", "none");
    $("#puiw_send_invoices_via_email_admin").parents("tr").last().css("border", "none");





    // click a discovered meta key to append it to the extra-product-data field
    $(document).on("click tap", "a.puiw-metakey", function(e) {
      e.preventDefault();
      var key = $(this).data("key");
      var $field = $(this).closest("td").find("input[type=text]").first();
      if (!$field.length || !key) { return; }
      var parts = ($field.val() || "").split(",").map(function(v){ return v.trim(); }).filter(Boolean);
      if (parts.indexOf(String(key)) === -1) { parts.push(key); }
      $field.val(parts.join(", ")).trigger("change").focus();
    });




    $("[selecteditem]").each(function(index, val) {
      $(val).find("option").removeAttr("selected");
      $(val).find("option[value='" + $(val).attr("selecteditem") + "']").attr("selected");
      $(val).val($(val).attr("selecteditem"));
      $(val).trigger("change");
    });

    var DEFAULT_SWATACHES = `[{"n":"Smoke","p":"#9E9E9E","s":"#A6A6A6","t":"#B3B3B3"},{"n":"Mango","p":"#FFCC80","s":"#FCD59A","t":"#FFDFB0"},{"n":"Gold","p":"#FAEE84","s":"#FFF59D","t":"#FFF8B5"},{"n":"Grass","p":"#A5D6A7","s":"#AFE0B1","t":"#C3EBC5"},{"n":"Sea","p":"#90CAF9","s":"#A6D5FC","t":"#B5DEFF"},{"n":"Peach","p":"#EF9A9A","s":"#F5ABAB","t":"#F0BBBB"}]`;

    // only entries with a name and three colours can be drawn; anything else
    // (an older format, a hand edited value) falls back to the built in set
    function puiw_parse_swatches(raw) {
      var isHex = function (c) { return /^#[0-9a-f]{3,8}$/i.test(String(c || "")); };
      var list = [];
      try { list = $.parseJSON(raw); } catch (err) { list = []; }
      list = $.grep($.isArray(list) ? list : [], function (v) {
        return v && v.n && isHex(v.p) && isHex(v.s) && isHex(v.t);
      });
      // the built-in schemes are always offered, ahead of the shop's own
      var builtIn = $.parseJSON(DEFAULT_SWATACHES);
      var names = builtIn.map(function (b) { return b.n; });
      return builtIn.concat($.grep(list, function (v) { return names.indexOf(v.n) === -1; }));
    }

    function fillSwatchSelects() {
    $("style.puiw-swatch-styles").remove();
    $("select.swatch-select").each(function(index, select) {
      var swatches = $(select).attr("swatches");
      if (!swatches) {
        swatches = DEFAULT_SWATACHES;
      }
      $(select).empty();
      var dJSON = puiw_parse_swatches(swatches);
      styles = '';
      $.each(dJSON, function(index, val) {
        var $elidraw = Math.floor(Math.random() * Math.random()) + Date.now() + index++;
        var $elid = 'eu_puiw_' + $elidraw;
        el = $(select).append(`<option data-uniq="${$elid}" data-p="${val.p}" data-s="${val.s}" data-t="${val.t}" value="${$elid}">${val.n}</option>`);
        styles += `
           \n/* style for swatch -- item with color: ${val.p}, ${val.s}, ${val.t} | title: ${val.n}*/
           ul.select2-results__options li[id$='${$elid}']{
             background:rgb(255,255,255);
             background:linear-gradient(90deg, rgba(255,255,255,1) 70%, ${val.p} 70%, ${val.p} 80%, ${val.s} 80%, ${val.s} 90%, ${val.t} 90%, ${val.t} 100%) !important;
             background:-moz-linear-gradient(90deg, rgba(255,255,255,1) 70%, ${val.p} 70%, ${val.p} 80%, ${val.s} 80%, ${val.s} 90%, ${val.t} 90%, ${val.t} 100%);
             background:-webkit-linear-gradient(90deg, rgba(255,255,255,1) 70%, ${val.p} 70%, ${val.p} 80%, ${val.s} 80%, ${val.s} 90%, ${val.t} 90%, ${val.t} 100%);
             filter:progid:DXImageTransform.Microsoft.gradient(startColorstr="#ffffff",endColorstr="#1d8bfc",GradientType=1) !important;
           }
           [dir=rtl] ul.select2-results__options li[id$='${$elid}']{
             background:rgb(255,255,255);
             background:linear-gradient(-90deg, rgba(255,255,255,1) 70%, ${val.p} 70%, ${val.p} 80%, ${val.s} 80%, ${val.s} 90%, ${val.t} 90%, ${val.t} 100%) !important;
             background:-moz-linear-gradient(-90deg, rgba(255,255,255,1) 70%, ${val.p} 70%, ${val.p} 80%, ${val.s} 80%, ${val.s} 90%, ${val.t} 90%, ${val.t} 100%);
             background:-webkit-linear-gradient(-90deg, rgba(255,255,255,1) 70%, ${val.p} 70%, ${val.p} 80%, ${val.s} 80%, ${val.s} 90%, ${val.t} 90%, ${val.t} 100%);
             filter:progid:DXImageTransform.Microsoft.gradient(startColorstr="#ffffff",endColorstr="#1d8bfc",GradientType=1) !important;
           }
           ul.select2-results__options li[id$='${$elid}'].select2-results__option--highlighted{
             background:#0073aa;
             background:linear-gradient(90deg, #0073aa, white 70%, ${val.p} 70%, ${val.p} 80%, ${val.s} 80%, ${val.s} 90%, ${val.t} 90%, ${val.t} 100%) !important;
             background:-moz-linear-gradient(90deg, #0073aa, white 70%, ${val.p} 70%, ${val.p} 80%, ${val.s} 80%, ${val.s} 90%, ${val.t} 90%, ${val.t} 100%);
             background:-webkit-linear-gradient(90deg, #0073aa, white 70%, ${val.p} 70%, ${val.p} 80%, ${val.s} 80%, ${val.s} 90%, ${val.t} 90%, ${val.t} 100%);
             filter:progid:DXImageTransform.Microsoft.gradient(startColorstr="#ffffff",endColorstr="#1d8bfc",GradientType=1) !important;
           }
           [dir=rtl] ul.select2-results__options li[id$='${$elid}'].select2-results__option--highlighted{
             background:#0073aa;
             background:linear-gradient(-90deg, #0073aa, white 70%, ${val.p} 70%, ${val.p} 80%, ${val.s} 80%, ${val.s} 90%, ${val.t} 90%, ${val.t} 100%) !important;
             background:-moz-linear-gradient(-90deg, #0073aa, white 70%, ${val.p} 70%, ${val.p} 80%, ${val.s} 80%, ${val.s} 90%, ${val.t} 90%, ${val.t} 100%);
             background:-webkit-linear-gradient(-90deg, #0073aa, white 70%, ${val.p} 70%, ${val.p} 80%, ${val.s} 80%, ${val.s} 90%, ${val.t} 90%, ${val.t} 100%);
             filter:progid:DXImageTransform.Microsoft.gradient(startColorstr="#ffffff",endColorstr="#1d8bfc",GradientType=1) !important;
           }\n`;
      });
      $("body").append(`<style class="puiw-swatch-styles">${styles}</style>`);
    });
    }
    fillSwatchSelects();
    $("select.swatch-select").not(".puiw-swatch-store").addClass("wc-enhanced-select");
    $(document.body).trigger('wc-enhanced-select-init');

    /* colour schemes as cards: name on top, the three colours as chips below */
    function schemeColors($store) {
      return ($store.data("colors") || []).map(function (id) { return String($("#" + id).val() || "").toLowerCase(); });
    }
    function paintSchemes($store) {
      var now = schemeColors($store).join("|");
      $('.puiw-swatchcards[data-for="' + $store.attr("id") + '"] .puiw-swatchcard').each(function () {
        var c = $(this).data();
        $(this).toggleClass("is-on", [c.p, c.s, c.t].join("|").toLowerCase() === now);
      });
      // a scheme click writes three colours; repaint the previews once, not per colour
      if (!tintQueued) {
        tintQueued = true;
        (window.requestAnimationFrame || setTimeout)(function () { tintQueued = false; tintTemplates(); });
      }
    }
    var tintQueued = false;
    function buildSchemeCards() {
    $("select.puiw-swatch-store").each(function () {
      var $store = $(this);
      var $cards = $('.puiw-swatchcards[data-for="' + $store.attr("id") + '"]').empty();
      $store.find("option").each(function () {
        var o = $(this).data();
        $("<button type='button' class='puiw-swatchcard'></button>")
          .attr({ "data-p": o.p, "data-s": o.s, "data-t": o.t, "data-value": $(this).val(), title: $(this).text() })
          .append($("<span class='puiw-swatchcard-name'></span>").text($(this).text()))
          .append($("<span class='puiw-swatchcard-chips'></span>")
            .append($("<i></i>").css("background", o.p))
            .append($("<i></i>").css("background", o.s))
            .append($("<i></i>").css("background", o.t)))
          .appendTo($cards);
      });
      paintSchemes($store);
    });
    }
    buildSchemeCards();
    /* the Colour Schemes panel announces every change; redraw the cards here at once */
    $(document).on("puiw:swatches", function (e, list) {
      $("select.swatch-select").attr("swatches", JSON.stringify(list || []));
      fillSwatchSelects();
      buildSchemeCards();
    });
    $(document).on("click", ".puiw-swatchcards[data-for] .puiw-swatchcard", function (e) {
      e.preventDefault();
      var $store = $("#" + $(this).closest(".puiw-swatchcards").data("for"));
      var c = $(this).data();
      $store.val($(this).data("value"));
      $.each(schemeColors($store).length ? $store.data("colors") : [], function (i, id) {
        var hex = [c.p, c.s, c.t][i];
        $("#" + id).val(hex).trigger("change");
      });
      paintSchemes($store);
    });
    $(document).on("input change", ".wc-color-picker[data-scheme]", function () {
      var $store = $("#" + $(this).data("scheme"));
      if ($store.length) { paintSchemes($store); }
    });

    /* template previews take the chosen primary colour: the screenshots were taken
       with the light blue scheme, so shift their hue and saturation from there */
    function hexToHsl(hex) {
      var m = /^#?([0-9a-f]{6}|[0-9a-f]{3})$/i.exec(String(hex || ""));
      if (!m) { return null; }
      var h = m[1].length === 3 ? m[1].replace(/./g, "$&$&") : m[1];
      var r = parseInt(h.substr(0, 2), 16) / 255, g = parseInt(h.substr(2, 2), 16) / 255, b = parseInt(h.substr(4, 2), 16) / 255;
      var max = Math.max(r, g, b), min = Math.min(r, g, b), l = (max + min) / 2, d = max - min, hue = 0, sat = 0;
      if (d) {
        sat = l > .5 ? d / (2 - max - min) : d / (max + min);
        hue = max === r ? (g - b) / d + (g < b ? 6 : 0) : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
        hue *= 60;
      }
      return { h: hue, s: sat, l: l };
    }
    function tintTemplates() {
      /* defined in here, the scheme cards above call this before the script gets this far */
      var shotBase = hexToHsl("#90caf9");
      var tintSources = { puiw_template: "puiw_theme_color", puiw_preinvoice_template: "puiw_preinvoice_theme_color" };
      $(".puiw-templatecards").each(function () {
        var src = tintSources[$(this).data("target")];
        var hsl = src ? hexToHsl($("#" + src).val()) : null;
        var filter = "";
        if (hsl && shotBase) {
          filter = "hue-rotate(" + Math.round(hsl.h - shotBase.h) + "deg) saturate(" + Math.max(0, (hsl.s / shotBase.s)).toFixed(2) + ")";
          if (hsl.l < .45) { filter += " brightness(" + Math.max(.6, hsl.l / shotBase.l + .25).toFixed(2) + ")"; }
        }
        $(this).find(".puiw-templatecard-shot img").css("filter", filter);
        var colors = src ? [$("#" + src).val(), $("#" + src + "2").val(), $("#" + src + "3").val()] : [];
        $(this).find(".puiw-templatecard").each(function () {
          var $strip = $(this).find(".puiw-templatecard-colors");
          if (!colors.length) { $strip.remove(); return; }
          if (!$strip.length) { $strip = $("<span class='puiw-templatecard-colors'><i></i><i></i><i></i></span>").appendTo($(this).find(".puiw-templatecard-shot")); }
          $strip.find("i").each(function (i) { $(this).css("background", colors[i] || "transparent"); });
        });
      });
    }
    tintTemplates();

    $(document).on("change", "#puiw_swatch", function(e) {
      e.preventDefault();
      var me = $(this).find("option:selected").first();
      $("input#puiw_theme_color").val(me.data("p")).trigger("change");
      $("input#puiw_theme_color2").val(me.data("s")).trigger("change");
      $("input#puiw_theme_color3").val(me.data("t")).trigger("change");
    });

    $(document).on("change", "#puiw_preinvoice_swatch", function(e) {
      e.preventDefault();
      var me = $(this).find("option:selected").first();
      $("input#puiw_preinvoice_theme_color").val(me.data("p")).trigger("change");
      $("input#puiw_preinvoice_theme_color2").val(me.data("s")).trigger("change");
      $("input#puiw_preinvoice_theme_color3").val(me.data("t")).trigger("change");
    });


    /* ----------------------------------------------------------------------
     * one page settings: navigation, cards, pickers, dialogs and ajax save
     * ------------------------------------------------------------------- */

    var $settings = $(".puiw-settings");
    var i18n = window._peproUltimateInvoice || {};

    function showPanel(slug, push) {
      if (!slug || !$("#puiw-" + slug).length) { return; }
      $(".puiw-nav-list a").removeClass("is-active").filter('[data-panel="' + slug + '"]').addClass("is-active");
      $(".puiw-panel").removeClass("is-active").filter('[data-panel="' + slug + '"]').addClass("is-active");
      $settings.attr("data-active", slug);
      if (push && window.history && window.history.replaceState) {
        window.history.replaceState(null, "", "#puiw-" + slug);
      }
      $(document).trigger("puiw-panel-shown", [slug]);
    }

    /* clipboard api is https only, this is the fallback for plain http installs */
    function legacyCopy($src, done) {
      var $field = $src;
      var temporary = false;
      if (!$src.is("textarea, input")) {
        $field = $("<textarea/>").val($src.text())
          .css({ position: "fixed", top: "-1000px", opacity: 0 })
          .appendTo(document.body);
        temporary = true;
      }
      $field.get(0).select();
      try {
        document.execCommand("copy");
        done();
      } catch (err) {
        /* nothing else to try */
      }
      if (temporary) { $field.remove(); }
    }

    if ($settings.length) {
      /* sticky headings inside the panels sit just under the sticky masthead */
      function stickyTop() {
        var $m = $(".puiw-masthead");
        var top = ($("#wpadminbar").outerHeight() || 0) + ($m.outerHeight() || 0) + 12;
        document.documentElement.style.setProperty("--puiw-sticky-top", top + "px");
      }
      stickyTop();
      $(window).on("resize", stickyTop);

      var hash = (window.location.hash || "").replace(/^#puiw-/, "");
      if (hash) { showPanel(hash, false); }

      /* any link that names a panel switches to it: the sidebar, the masthead, and
         the shortcut buttons and cards inside the panels themselves */
      $(document).on("click", ".puiw-nav-list a, .puiw-masthead-links a[data-panel], .puiw-settings-body a[data-panel]", function (e) {
        if (!$("#puiw-" + $(this).data("panel")).length) { return; }
        e.preventDefault();
        showPanel($(this).data("panel"), true);
        $("html, body").animate({ scrollTop: 0 }, 150);
      });

      /* sidebar search looks at the section name and at everything inside it */
      $(document).on("input", "#puiw-settings-search", function () {
        var term = $.trim($(this).val()).toLowerCase();
        $(".puiw-nav-list a").each(function () {
          var slug = $(this).data("panel");
          var hay = ($(this).text() + " " + $("#puiw-" + slug).text()).toLowerCase();
          $(this).parent().toggleClass("is-hidden", !!term && hay.indexOf(term) === -1);
        });
        $(".puiw-nav-group").each(function () {
          $(this).toggleClass("is-hidden", $(this).find("li:not(.is-hidden)").length === 0);
        });
      });

      /* a switchboard filters its own cards */
      $(document).on("input", ".puiw-card-filter", function () {
        var term = $.trim($(this).val()).toLowerCase();
        var $board = $(this).closest(".puiw-switchboard");
        var shown = 0;
        $board.find(".puiw-card-toggle").each(function () {
          var hit = !term || $(this).text().toLowerCase().indexOf(term) !== -1;
          $(this).toggleClass("is-hidden", !hit);
          if (hit) { shown++; }
        });
        $board.find(".puiw-switchboard-empty").prop("hidden", shown !== 0);
        $board.find(".puiw-switchboard-count").text(term ? shown : "");
      });

      /* card state has to follow the checkbox, including keyboard toggling */
      $(document).on("change", ".puiw-card-toggle input[type=checkbox]", function () {
        $(this).closest(".puiw-card-toggle").toggleClass("is-on", this.checked);
      });

      $(document).on("change", ".puiw-choice input[type=checkbox]", function () {
        $(this).closest(".puiw-choice").toggleClass("is-on", this.checked);
      });

      $(document).on("change", ".puiw-choice input[type=radio]", function () {
        var name = this.name;
        $('.puiw-choice input[type=radio][name="' + name + '"]').each(function () {
          $(this).closest(".puiw-choice").toggleClass("is-on", this.checked);
        });
      });

      /* copy a textarea or any element's text to the clipboard */
      $(document).on("click", ".puiw-copy", function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $src = $($btn.data("copy"));
        if (!$src.length) { return; }
        var text = $src.is("textarea, input") ? $src.val() : $src.text();
        var done = function () {
          var $label = $btn.find("span").last();
          var old = $label.text();
          $label.text(i18n.copied || old);
          window.setTimeout(function () { $label.text(old); }, 1600);
        };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(done, function () { legacyCopy($src, done); });
        } else {
          legacyCopy($src, done);
        }
      });

      $(document).on("change", ".puiw-templatecard input[type=radio]", function () {
        var $group = $(this).closest(".puiw-templatecards");
        $group.find(".puiw-templatecard").removeClass("is-on");
        $(this).closest(".puiw-templatecard").addClass("is-on");
      });

      /* tabs inside a long section */
      $(document).on("click", ".puiw-tab", function (e) {
        e.preventDefault();
        var $tabs = $(this).closest(".puiw-tabs");
        var key = String($(this).data("tab"));
        $tabs.find(".puiw-tab").each(function () {
          var on = String($(this).data("tab")) === key;
          $(this).toggleClass("is-active", on).attr("aria-selected", on ? "true" : "false");
        });
        $tabs.children(".puiw-tabpane").each(function () {
          var on = String($(this).data("tab")) === key;
          $(this).toggleClass("is-active", on).prop("hidden", !on);
        });
      });

      /* stylesheets: one sub tab per output, each a CodeMirror editor with CSS lint,
         started only when its tab is first shown so the page itself stays light */
      var codeEditors = [];
      function codeStatus($wrap, found) {
        var errors = 0, warnings = 0;
        $.each(found || [], function (i, f) { if (f.severity === "error") { errors++; } else { warnings++; } });
        var $s = $wrap.find(".puiw-code-status").empty().toggleClass("is-bad", errors > 0).toggleClass("is-warn", !errors && warnings > 0);
        if (!errors && !warnings) {
          $s.text(i18n.cssOk || "OK");
        } else {
          $("<strong/>").text((i18n.cssIssues || "%1$s / %2$s").replace("%1$s", errors).replace("%2$s", warnings)).appendTo($s);
          var $list = $("<ul/>").appendTo($s);
          $.each(found.slice(0, 6), function (i, f) {
            $("<li/>").text((i18n.cssLine || "Line %s").replace("%s", (f.from && f.from.line + 1) || "?") + ": " + f.message).appendTo($list);
          });
        }
        $wrap.data("cssErrors", errors);
      }
      function startCodeEditor($field) {
        var $area = $field.find("textarea.puiw-code");
        if (!$area.length || $field.data("cm") || !(window.wp && wp.codeEditor && wp.codeEditor.initialize)) { return; }
        var $wrap = $field.find(".puiw-field-input");
        $("<div class='puiw-code-snippets'/>").append($("<span/>").text(i18n.cssSnippets || "Snippets"))
          .append($.map(i18n.cssSnippetList || [], function (sn, n) {
            return $("<button type='button' class='button puiw-code-snippet'/>").attr("data-n", n).text(sn[0]).get(0);
          }))
          .insertBefore($area);
        $("<div class='puiw-code-status' role='status' aria-live='polite'/>").insertAfter($area);
        var settings = $.extend(true, {}, wp.codeEditor.defaultSettings || {});
        settings.codemirror = $.extend({}, settings.codemirror, {
          mode: "css", lineNumbers: true, lineWrapping: true, indentUnit: 2, tabSize: 2,
          direction: "ltr", rtlMoveVisually: false,
          gutters: ["CodeMirror-lint-markers"],
          lint: { onUpdateLinting: function (found) { codeStatus($wrap, found); } },
        });
        var ed = wp.codeEditor.initialize($area.get(0), settings);
        var cm = ed.codemirror;
        cm.on("change", function () {
          cm.save();
          $area.trigger("change");
        });
        $field.data("cm", cm);
        codeEditors.push($wrap);
        codeStatus($wrap, []);
        setTimeout(function () { cm.refresh(); cm.performLint && cm.performLint(); }, 30);
      }
      function showCodeField($pane, id) {
        $pane.find(".puiw-code-tab").each(function () {
          var on = $(this).data("for") === id;
          $(this).toggleClass("is-active", on).attr("aria-selected", on ? "true" : "false");
        });
        $pane.find(".puiw-field.is-code").each(function () {
          var on = $(this).data("field") === id;
          $(this).prop("hidden", !on);
          if (on) { startCodeEditor($(this)); var cm = $(this).data("cm"); if (cm) { setTimeout(function () { cm.refresh(); }, 0); } }
        });
      }
      function setupCodePane($pane) {
        var $fields = $pane.find(".puiw-field.is-code");
        if (!$fields.length) { return; }
        if (!$pane.data("codeTabs")) {
          var $bar = $("<div class='puiw-code-tabs' role='tablist'/>");
          $fields.each(function () {
            $("<button type='button' class='puiw-code-tab' role='tab'/>")
              .attr("data-for", $(this).data("field"))
              .text($.trim($(this).find(".puiw-field-label label").first().text()))
              .appendTo($bar);
          });
          $bar.insertBefore($fields.first());
          $pane.data("codeTabs", 1);
        }
        var current = $pane.find(".puiw-code-tab.is-active").data("for") || $fields.first().data("field");
        showCodeField($pane, current);
      }
      $(document).on("click", ".puiw-code-tab", function (e) {
        e.preventDefault();
        showCodeField($(this).closest(".puiw-tabpane, .puiw-panel"), $(this).data("for"));
      });
      $(document).on("click", ".puiw-code-snippet", function (e) {
        e.preventDefault();
        var cm = $(this).closest(".puiw-field").data("cm");
        var sn = (i18n.cssSnippetList || [])[+$(this).data("n")];
        if (!cm || !sn) { return; }
        var doc = cm.getDoc(), cur = doc.getCursor();
        var text = (cur.ch ? "\n" : "") + sn[1];
        doc.replaceRange(text, cur);
        cm.focus();
      });
      // a pane with code fields sets them up the first time it is shown
      $(document).on("click", ".puiw-tab", function () {
        var $pane = $(this).closest(".puiw-tabs").children(".puiw-tabpane[data-tab='" + $(this).data("tab") + "']");
        if ($pane.find(".puiw-field.is-code").length) { setupCodePane($pane); }
      });
      $(document).on("puiw-panel-shown", function () {
        $(".puiw-panel.is-active .puiw-tabpane.is-active").each(function () {
          if ($(this).find(".puiw-field.is-code").length) { setupCodePane($(this)); }
        });
      });
      // saving a stylesheet with errors is allowed, but never by accident
      $(document).on("click", ".puiw-save", function (e) {
        var bad = $.grep(codeEditors, function ($w) { return $w.data("cssErrors") > 0; }).length;
        if (bad && !$(this).data("cssConfirmed")) {
          if (!window.confirm((i18n.cssSaveAsk || "%s").replace("%s", bad))) {
            e.preventDefault();
            e.stopImmediatePropagation();
          }
        }
      });

      /* setup wizard: each step saves only its own fields, then moves on */
      $(document).on("click", ".puiw-setup-next", function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $status = $(".puiw-setup-status");
        if ($btn.hasClass("is-busy")) { return; }
        $btn.addClass("is-busy").prop("disabled", true);
        $status.removeClass("is-error").text(i18n.saving || "");
        $.post(i18n.ajax, {
          action: "puiw_setup_step",
          nonce: $("#puiw_setup_nonce").val(),
          step: $btn.data("step"),
          finish: $btn.data("finish") ? 1 : 0,
          form: $("#mainform").serialize()
        }).done(function (res) {
          if (res && res.success) {
            window.onbeforeunload = null;
            window.location.href = $btn.data("go") || res.data.next;
            return;
          }
          $status.addClass("is-error").text((res && res.data && res.data.message) || i18n.saveError);
          $btn.removeClass("is-busy").prop("disabled", false);
        }).fail(function () {
          $status.addClass("is-error").text(i18n.saveError);
          $btn.removeClass("is-busy").prop("disabled", false);
        });
      });
      $(document).on("click", ".puiw-setup-back, .puiw-setup-skip, .puiw-setup-steps a, .puiw-setup-exit", function () {
        window.onbeforeunload = null;
      });

      /* fields that belong to one choice of another field, e.g. a provider's keys */
      function fieldValue(name) {
        var $radio = $('[name="' + name + '"]:radio');
        if ($radio.length) { return String($radio.filter(":checked").val() || ""); }
        var $box = $('[name="' + name + '"]:checkbox');
        if ($box.length) { return $box.is(":checked") ? "yes" : "no"; }
        return String($("#" + name).val() || "");
      }
      function applyShowIf() {
        $(".puiw-field[data-show-if]").each(function () {
          var rules = $(this).data("showIf") || {};
          var ok = true;
          $.each(rules, function (name, wanted) {
            var list = $.isArray(wanted) ? wanted.map(String) : [String(wanted)];
            if (list.indexOf(fieldValue(name)) === -1) { ok = false; }
          });
          $(this).prop("hidden", !ok);
        });
      }
      // only fields that a rule reads can change what is shown; colour pickers and the
      // scheme editor fire a change per drag step and used to rerun every rule
      var showIfNames = {};
      $(".puiw-field[data-show-if]").each(function () {
        $.each($(this).data("showIf") || {}, function (name) { showIfNames[name] = true; });
      });
      $(document).on("change", "#mainform :input", function () {
        if (showIfNames[this.name] || showIfNames[this.id]) { applyShowIf(); }
      });
      applyShowIf();

      /* macro pickers: drop a token at the cursor, or add it once to a comma list */
      $(document).on("click", ".puiw-macro", function (e) {
        e.preventDefault();
        var $box = $(this).closest(".puiw-macros");
        var $field = $("#" + $box.data("target"));
        if (!$field.length) { return; }
        var token = String($(this).data("insert"));
        var el = $field.get(0);
        if ($box.data("mode") === "list") {
          var parts = ($field.val() || "").split(",").map(function (v) { return $.trim(v); }).filter(Boolean);
          if (parts.indexOf(token) === -1) { parts.push(token); }
          $field.val(parts.join(", "));
        } else if (typeof el.selectionStart === "number") {
          var v = $field.val();
          var at = el.selectionStart;
          $field.val(v.slice(0, at) + token + v.slice(el.selectionEnd));
          el.selectionStart = el.selectionEnd = at + token.length;
        } else {
          $field.val(($field.val() || "") + token);
        }
        $field.trigger("input").trigger("change").focus();
        var $chip = $(this).addClass("is-done");
        setTimeout(function () { $chip.removeClass("is-done"); }, 700);
      });

      $(document).on("input", ".puiw-macros-filter", function () {
        var term = $.trim($(this).val()).toLowerCase();
        var $box = $(this).closest(".puiw-macros").toggleClass("is-filtering", !!term);
        $box.find(".puiw-macro").each(function () {
          $(this).prop("hidden", !!term && String($(this).data("search")).indexOf(term) === -1);
        });
        $box.find(".puiw-macros-group").each(function () {
          $(this).prop("hidden", $(this).find(".puiw-macro:not([hidden])").length === 0);
        });
      });

      $(document).on("click", ".puiw-macros-more", function (e) {
        e.preventDefault();
        var $box = $(this).closest(".puiw-macros").toggleClass("is-open");
        var $btn = $(this);
        if (!$btn.data("more")) { $btn.data("more", $btn.text()); }
        $btn.text($box.hasClass("is-open") ? $btn.data("less") : $btn.data("more"));
      });

      /* chips append their token to the field they belong to */
      $(document).on("click", ".puiw-chip", function (e) {
        e.preventDefault();
        var $field = $("#" + $(this).data("target"));
        if (!$field.length) { return; }
        var insert = String($(this).data("insert"));
        var el = $field.get(0);
        if (typeof el.selectionStart === "number") {
          var v = $field.val();
          var at = el.selectionStart;
          $field.val(v.slice(0, at) + insert + v.slice(el.selectionEnd));
          el.selectionStart = el.selectionEnd = at + insert.length;
        } else {
          $field.val(($field.val() || "") + insert);
        }
        $field.trigger("change").focus();
      });

      /* media library picker */
      $(document).on("click", ".puiw-imagepicker-pick", function (e) {
        e.preventDefault();
        var $wrap = $(this).closest(".puiw-imagepicker");
        var frame = wp.media({
          title: i18n.title,
          multiple: false,
          library: { type: "image" },
          button: { text: i18n.btntext }
        });
        frame.on("select", function () {
          var url = frame.state().get("selection").first().toJSON().url;
          $wrap.find(".puiw-imagepicker-input").val(url).trigger("change");
          $wrap.find(".puiw-imagepicker-preview").prop("hidden", false).find("img").attr("src", url);
          $wrap.find(".puiw-imagepicker-clear").prop("hidden", false);
        });
        frame.open();
      });

      $(document).on("click", ".puiw-imagepicker-clear", function (e) {
        e.preventDefault();
        var $wrap = $(this).closest(".puiw-imagepicker");
        $wrap.find(".puiw-imagepicker-input").val("").trigger("change");
        $wrap.find(".puiw-imagepicker-preview").prop("hidden", true).find("img").attr("src", "");
        $(this).prop("hidden", true);
      });

      $(document).on("input", ".puiw-imagepicker-input", function () {
        var $wrap = $(this).closest(".puiw-imagepicker");
        var url = $.trim($(this).val());
        $wrap.find(".puiw-imagepicker-preview").prop("hidden", !url).find("img").attr("src", url);
        $wrap.find(".puiw-imagepicker-clear").prop("hidden", !url);
      });

      /* language sub tabs inside the translations panel */
      /* All settings: search every panel's settings at once */
      $(document).on("input", ".puiw-ov-filter", function () {
        var term = $.trim($(this).val()).toLowerCase(), shown = 0;
        $(".puiw-ov-card").each(function () {
          var $card = $(this), any = false;
          var headHit = term && $card.find(".puiw-ov-head").text().toLowerCase().indexOf(term) > -1;
          $card.find(".puiw-ov-item").each(function () {
            var hit = !term || headHit || String($(this).data("search")).indexOf(term) > -1;
            $(this).prop("hidden", !hit);
            if (hit) { any = true; shown++; }
          });
          var $about = $card.find(".puiw-ov-about");
          if ($about.length) { any = !term || headHit || String($about.data("search")).indexOf(term) > -1; }
          $card.prop("hidden", !any);
        });
        var $count = $(".puiw-ov-count");
        $count.text(term ? shown + " / " + $count.data("total") : $count.data("total"));
        $(".puiw-ov-empty").prop("hidden", !!$(".puiw-ov-card:not([hidden])").length);
      });
      /* a setting in the list opens its panel and tab, then brings the field into view */
      $(document).on("click", ".puiw-ov-item a[data-goto]", function () {
        var id = $(this).data("goto"), tab = $(this).data("tab");
        setTimeout(function () {
          var $panel = $(".puiw-panel.is-active");
          if (tab) { $panel.find(".puiw-tab[data-tab='" + tab + "']").first().trigger("click"); }
          var $f = $panel.find("[data-field='" + id + "']").first();
          if (!$f.length) { $f = $panel.find("#" + id).closest(".puiw-field, .puiw-card-toggle, .puiw-field-colors"); }
          if (!$f.length) { return; }
          $f.get(0).scrollIntoView({ block: "center", behavior: "smooth" });
          $f.addClass("is-flash");
          setTimeout(function () { $f.removeClass("is-flash"); }, 1600);
        }, 60);
      });

      /* Translations: narrow a part's list to the texts that match, file headings included */
      $(document).on("input", ".puiw-strings-filter", function () {
        var term = $.trim($(this).val()).toLowerCase();
        var $pane = $(this).closest(".puiw-tabpane");
        $pane.find(".puiw-strings-group").each(function () {
          var any = false;
          $(this).find(".puiw-string-row").each(function () {
            var hit = !term || $(this).text().toLowerCase().indexOf(term) > -1 || String($(this).find("input").val() || "").toLowerCase().indexOf(term) > -1;
            $(this).prop("hidden", !hit);
            any = any || hit;
          });
          $(this).prop("hidden", !any);
        });
      });

      $(document).on("click", ".puiw-lang-tab", function (e) {
        e.preventDefault();
        var lang = $(this).data("lang");
        $(".puiw-lang-tab").removeClass("is-active");
        $(this).addClass("is-active");
        $(".puiw-lang-pane").removeClass("is-active").filter('[data-lang="' + lang + '"]').addClass("is-active");
      });

      /* changelog and hook list filters */
      $(document).on("input", ".puiw-list-filter", function () {
        var term = $.trim($(this).val()).toLowerCase();
        $($(this).data("list")).find("[data-search]").each(function () {
          var hit = !term || String($(this).data("search")).toLowerCase().indexOf(term) !== -1;
          $(this).prop("hidden", !hit);
          if (hit && term && this.tagName === "DETAILS") { this.open = true; }
        });
      });

      $(document).on("click", ".puiw-save", function (e) {
        if (!i18n.ajax) { return; }
        e.preventDefault();
        var $btn = $(this);
        var $status = $(".puiw-savebar-status");
        if ($btn.hasClass("is-busy")) { return; }
        $btn.addClass("is-busy");
        $status.removeClass("is-ok is-error").text(i18n.saving || "");
        $.post(i18n.ajax, {
          action: i18n.action,
          wparam: "save-settings",
          nonce: i18n.nonce,
          form: $("#mainform").serialize() + "&save=1"
        }).done(function (res) {
          if (res && res.success) {
            $status.addClass("is-ok").text(res.data.message);
            /* woocommerce arms a leave-page warning on the first edit and only drops it
               on a real form submit; saving over ajax never submits, so drop it here
               and arm it again on the next edit, since woocommerce will not */
            window.onbeforeunload = null;
            $("#mainform").off("input.puiwdirty change.puiwdirty").one("input.puiwdirty change.puiwdirty", ":input", function () {
              var warning = (window.woocommerce_settings_params || {}).i18n_nav_warning;
              if (warning) { window.onbeforeunload = function () { return warning; }; }
            });
          } else {
            $status.addClass("is-error").text((res && res.data && res.data.message) || i18n.saveError);
          }
        }).fail(function () {
          $status.addClass("is-error").text(i18n.saveError);
        }).always(function () {
          $btn.removeClass("is-busy");
        });
      });
    }

    /* ----------------------------------------------------------------------
     * our own dialog, WordPress thickbox is gone
     * ------------------------------------------------------------------- */

    var $modal = $("#puiw-modal");

    function openModal(title, $content) {
      $modal.find("#puiw-modal-title").text(title || "");
      $modal.find(".puiw-modal-body").empty().append($content);
      $modal.prop("hidden", false);
      $(document.body).addClass("puiw-modal-open");
    }

    function closeModal() {
      $modal.prop("hidden", true).find(".puiw-modal-body").empty();
      $(document.body).removeClass("puiw-modal-open");
    }

    $(document).on("click", ".puiw-dialog-open", function (e) {
      e.preventDefault();
      var $src = $("#" + $(this).data("dialog"));
      if (!$src.length) { return; }
      openModal($(this).data("title") || "", $src.children().clone());
    });

    $(document).on("click", "#puiw-modal [data-close]", closeModal);
    $(document).on("keydown", function (e) {
      if (e.key === "Escape" && $modal.length && !$modal.prop("hidden")) { closeModal(); }
    });

    /* ----------------------------------------------------------------------
     * template editor
     * ------------------------------------------------------------------- */

    var $te = $(".puiw-te");

    if ($te.length) {
      var editor = null;
      var current = { theme: "", file: "", dirty: false };

      function teAjax(wparam, data, done) {
        return $.post(i18n.ajax, $.extend({
          action: i18n.action,
          wparam: wparam,
          nonce: i18n.nonce
        }, data)).done(function (res) {
          if (res && res.success) {
            done(res.data);
          } else {
            teStatus((res && res.data && res.data.message) || i18n.saveError, true);
          }
        }).fail(function () {
          teStatus(i18n.saveError, true);
        });
      }

      /* the workspace is hidden until a template is opened, so anything the user
         must read goes into a notice at the top of the panel as well */
      function teNotice(text, isError) {
        var $box = $te.find(".puiw-te-notice");
        if (!$box.length) {
          $box = $('<div class="puiw-te-notice puiw-notice" />').prependTo($te);
        }
        $box.toggleClass("is-ok", !isError).text(text).show();
        if (!isError) {
          window.setTimeout(function () { $box.fadeOut(200); }, 3000);
        }
      }

      function teStatus(text, isError) {
        $te.find(".puiw-te-status").removeClass("is-ok is-error").addClass(isError ? "is-error" : "is-ok").text(text);
        if (isError || !$te.find(".puiw-te-workspace").is(":visible")) {
          teNotice(text, isError);
        }
      }

      function reloadToTemplates() {
        window.location.hash = "#puiw-templates";
        window.location.reload();
      }

      function fileIcon(type) {
        return type === "css" ? i18n.iconCss : (type === "cfg" ? i18n.iconCfg : i18n.iconHtml);
      }

      function renderFiles(files) {
        var $list = $te.find(".puiw-te-files").empty();
        $.each(files, function (name, meta) {
          $list.append(
            $("<li/>").append(
              $("<button/>", { type: "button", "data-file": name, "class": "puiw-te-file-btn" })
                .append(fileIcon(meta.type))
                .append($("<span/>").text(name))
            )
          );
        });
      }

      function mountEditor(contents, mode) {
        var $area = $te.find(".puiw-te-code");
        if (editor && editor.codemirror) {
          editor.codemirror.toTextArea();
          editor = null;
        }
        $area.val(contents);
        if (window.wp && wp.codeEditor && wp.codeEditor.initialize) {
          var settings = $.extend(true, {}, wp.codeEditor.defaultSettings || {});
          settings.codemirror = $.extend({}, settings.codemirror, {
            mode: mode,
            lineNumbers: true,
            lineWrapping: true,
            indentUnit: 2,
            tabSize: 2,
            direction: "ltr"
          });
          editor = wp.codeEditor.initialize($area.get(0), settings);
          editor.codemirror.on("change", function () { current.dirty = true; });
        } else {
          $area.off("input.puiwte").on("input.puiwte", function () { current.dirty = true; });
        }
        current.dirty = false;
      }

      function editorValue() {
        return editor && editor.codemirror ? editor.codemirror.getValue() : $te.find(".puiw-te-code").val();
      }

      function openFile(name, type) {
        teAjax("theme-read", { theme: current.theme, file: name }, function (data) {
          current.file = name;
          $te.find(".puiw-te-file-btn").removeClass("is-active").filter('[data-file="' + name + '"]').addClass("is-active");
          $te.find(".puiw-te-current").text(name);
          mountEditor(data.contents, type === "css" ? "text/css" : (type === "cfg" ? "text/plain" : "text/html"));
          teStatus("");
        });
      }

      $(document).on("click", ".puiw-te-edit", function () {
        var slug = $(this).closest(".puiw-te-theme").data("theme");
        teAjax("theme-open", { theme: slug }, function (data) {
          current.theme = slug;
          $te.find(".puiw-te-workspace").prop("hidden", false);
          $te.find(".puiw-te-name").val(data.theme.name);
          $te.find(".puiw-te-author").val(data.theme.author);
          $te.find(".puiw-te-version").val(data.theme.version);
          $te.find(".puiw-te-requires").val(data.meta.requires);
          renderFiles(data.files);
          var first = Object.keys(data.files)[0];
          if (first) { openFile(first, data.files[first].type); }
          $("html, body").animate({ scrollTop: $te.find(".puiw-te-workspace").offset().top - 60 }, 200);
        });
      });

      $(document).on("click", ".puiw-te-file-btn", function () {
        var name = $(this).data("file");
        if (current.dirty && !window.confirm(i18n.teDiscard)) { return; }
        openFile(name, String(name).split(".").pop());
      });

      $(document).on("click", ".puiw-te-save", function () {
        teAjax("theme-save", { theme: current.theme, file: current.file, contents: editorValue() }, function (data) {
          current.dirty = false;
          teStatus(data.message + " " + data.time);
        });
      });

      $(document).on("change", ".puiw-te-name, .puiw-te-author, .puiw-te-version, .puiw-te-requires", function () {
        teAjax("theme-meta", {
          theme: current.theme,
          name: $te.find(".puiw-te-name").val(),
          author: $te.find(".puiw-te-author").val(),
          ver: $te.find(".puiw-te-version").val(),
          requires: $te.find(".puiw-te-requires").val()
        }, function (data) { teStatus(data.message); });
      });

      $(document).on("click", ".puiw-te-close", function () {
        if (current.dirty && !window.confirm(i18n.teDiscard)) { return; }
        $te.find(".puiw-te-workspace").prop("hidden", true);
        current = { theme: "", file: "", dirty: false };
      });

      $(document).on("click", ".puiw-te-duplicate", function () {
        var $theme = $(this).closest(".puiw-te-theme");
        var slug = $theme.data("theme");
        var $form = $("<div/>")
          .append($("<label class='puiw-modal-field'/>").append($("<span/>").text(i18n.teName)).append($("<input type='text' class='puiw-dup-name'/>").val($theme.find("strong").text() + " " + i18n.teCopy)))
          .append($("<label class='puiw-modal-field'/>").append($("<span/>").text(i18n.teAuthor)).append($("<input type='text' class='puiw-dup-author'/>").val(i18n.currentUser || "")))
          .append($("<div class='puiw-modal-actions'/>").append($("<button type='button' class='button button-primary puiw-dup-go'/>").text(i18n.teDuplicate)));
        openModal(i18n.teDuplicate, $form);
        $form.find(".puiw-dup-go").on("click", function () {
          teAjax("theme-duplicate", {
            theme: slug,
            name: $form.find(".puiw-dup-name").val(),
            author: $form.find(".puiw-dup-author").val()
          }, function () { window.location.reload(); });
        });
      });

      $(document).on("click", ".puiw-te-export", function () {
        teAjax("theme-export", { theme: $(this).closest(".puiw-te-theme").data("theme") }, function (data) {
          window.location.href = data.url;
        });
      });

      $(document).on("click", ".puiw-te-delete", function () {
        if (!window.confirm(i18n.teConfirmDelete)) { return; }
        teAjax("theme-delete", { theme: $(this).closest(".puiw-te-theme").data("theme") }, function () {
          reloadToTemplates();
        });
      });

      $(document).on("click", ".puiw-te-import", function () { $te.find(".puiw-te-file").trigger("click"); });

      $(document).on("change", ".puiw-te-file", function () {
        if (!this.files || !this.files[0]) { return; }
        var form = new FormData();
        form.append("action", i18n.action);
        form.append("wparam", "theme-import");
        form.append("nonce", i18n.nonce);
        form.append("file", this.files[0]);
        $.ajax({ url: i18n.ajax, method: "POST", data: form, processData: false, contentType: false })
          .done(function (res) {
            if (res && res.success) { reloadToTemplates(); }
            else { teNotice((res && res.data && res.data.message) || i18n.saveError, true); }
          })
          .fail(function () { teNotice(i18n.saveError, true); });
      });
    }

    // ---- template picker: search box plus facet chips -------------------
    function filterTemplates($wrap) {
      var term   = ($wrap.find(".puiw-templatesearch").val() || "").toLowerCase().trim();
      var facets = $wrap.find(".puiw-templatechip.is-on").map(function () {
        return $(this).data("facet");
      }).get();
      var shown = 0;
      $wrap.find(".puiw-templatecard").each(function () {
        var $card = $(this);
        var hay   = ($card.data("search") || "") + "";
        var has   = (" " + ($card.data("facets") || "") + " ");
        var ok    = !term || hay.indexOf(term) !== -1;
        for (var i = 0; ok && i < facets.length; i++) {
          if (has.indexOf(" " + facets[i] + " ") === -1) { ok = false; }
        }
        $card.toggle(ok);
        if (ok) { shown++; }
      });
      $wrap.find(".puiw-templatecards-empty").prop("hidden", shown > 0);
    }

    $(document).on("input", ".puiw-templatesearch", function () {
      filterTemplates($(this).closest(".puiw-field, .puiw-panel"));
    });

    $(document).on("click", ".puiw-templatechip", function (e) {
      e.preventDefault();
      var $chip = $(this);
      // reading direction is either one or the other, so the pair behaves like radios
      if ($chip.data("facet") === "rtl" || $chip.data("facet") === "ltr") {
        $chip.siblings('[data-facet="rtl"],[data-facet="ltr"]').removeClass("is-on");
      }
      $chip.toggleClass("is-on");
      filterTemplates($chip.closest(".puiw-field, .puiw-panel"));
    });

    // reaching this line is what clears the banner at the top of the page
    if (window.puiwBoot) { window.puiwBoot.booted = true; }
    $("#puiw-bootcheck").removeClass("is-visible").attr("hidden", "hidden");

  });
})(jQuery);
