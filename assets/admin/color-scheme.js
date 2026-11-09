/*
 * @Author: Amirhossein Hosseinpour <https://amirhp.com>
 * @Date Created: 2021/07/12 16:58:32
 * @Last modified by: amirhp-com <its@amirhp.com>
 */

/* Colour schemes panel. The schemes live as JSON in textarea#puiw_color_schemes, which
   the main Save button sends with the rest of the page. The built-in schemes cannot be
   edited or removed; every change is announced so the Theming cards redraw at once. */
(function ($) {
  "use strict";
  var t = window._i18n || {}, m = window.msg || {};
  var BUILT_IN = [
    { n: "Smoke", p: "#9E9E9E", s: "#A6A6A6", t: "#B3B3B3" },
    { n: "Mango", p: "#FFCC80", s: "#FCD59A", t: "#FFDFB0" },
    { n: "Gold", p: "#FAEE84", s: "#FFF59D", t: "#FFF8B5" },
    { n: "Grass", p: "#A5D6A7", s: "#AFE0B1", t: "#C3EBC5" },
    { n: "Sea", p: "#90CAF9", s: "#A6D5FC", t: "#B5DEFF" },
    { n: "Peach", p: "#EF9A9A", s: "#F5ABAB", t: "#F0BBBB" },
  ];
  var KEYS = ["p", "s", "t"];
  var LABELS = [m.primary, m.secondary, m.tertiary];
  var isHex = function (c) { return /^#[0-9a-f]{6}$/i.test(String(c || "")); };
  var builtIn = function (name) { return BUILT_IN.some(function (b) { return b.n === name; }); };

  var $store, $grid, list = [];

  /* the saved list, with any missing built-in scheme put back first */
  function load() {
    var saved = [];
    try { saved = JSON.parse($store.val() || "[]"); } catch (e) { saved = []; }
    saved = $.grep($.isArray(saved) ? saved : [], function (v) {
      return v && v.n && isHex(v.p) && isHex(v.s) && isHex(v.t) && !builtIn(v.n);
    });
    return BUILT_IN.map(function (b) { return $.extend({}, b); }).concat(saved);
  }

  /* write the textarea, mark the page as changed, tell Theming */
  function commit() {
    var json = JSON.stringify(list);
    $store.val(json).trigger("change");
    $(document).trigger("puiw:swatches", [list]);
  }

  function chips(item) {
    var $c = $("<span class='puiw-swatchcard-chips'/>");
    KEYS.forEach(function (k, i) {
      $("<i/>").css("background", item[k]).attr("title", (LABELS[i] || "") + " " + item[k]).appendTo($c);
    });
    return $c;
  }

  function card(item, index) {
    var locked = builtIn(item.n);
    var $card = $("<div class='puiw-swatchcard puiw-scheme' tabindex='-1'/>").attr("data-index", index).toggleClass("is-builtin", locked);
    var $head = $("<span class='puiw-scheme-head'/>").appendTo($card);
    $("<span class='puiw-swatchcard-name'/>").text(item.n).appendTo($head);
    if (locked) {
      $("<span class='puiw-scheme-badge'/>").text(t.builtInTxt).appendTo($head);
    } else {
      $("<span class='puiw-scheme-tools'/>")
        .append($("<button type='button' class='puiw-scheme-btn scheme-edit'/>").attr({ title: m.edit, "aria-label": m.edit }).html(m.icEdit))
        .append($("<button type='button' class='puiw-scheme-btn scheme-trash'/>").attr({ title: m.delete, "aria-label": m.delete }).html(m.icTrash))
        .appendTo($head);
    }
    chips(item).appendTo($card);
    return $card;
  }

  function editor(item, index) {
    var $card = $("<div class='puiw-swatchcard puiw-scheme is-editing'/>").attr("data-index", index);
    $("<input type='text' class='puiw-scheme-name'/>").attr({ placeholder: t.nameTxt, "aria-label": t.nameTxt, maxlength: 40 }).val(item.n).appendTo($card);
    KEYS.forEach(function (k, i) {
      $("<label class='puiw-scheme-color'/>")
        .append($("<span/>").text(LABELS[i] || k))
        .append($("<input type='text' dir='ltr' maxlength='7' data-puiw-color='1'/>").attr({ "data-key": k, "data-puiw-color-label": LABELS[i] || k }).val(item[k]))
        .appendTo($card);
    });
    $("<span class='puiw-scheme-actions'/>")
      .append($("<button type='button' class='button scheme-cancel'/>").text(m.discard))
      .append($("<button type='button' class='button button-primary scheme-apply'/>").text(m.apply))
      .appendTo($card);
    return $card;
  }

  function render(editing) {
    $grid.empty();
    list.forEach(function (item, i) {
      $grid.append(i === editing ? editor(item, i) : card(item, i));
    });
    if (editing !== undefined && window.PuiwAlwan) {
      var $ed = $grid.find(".is-editing");
      window.PuiwAlwan.scan($ed.get(0));
      $ed.find(".puiw-scheme-name").trigger("focus");
    }
  }

  function closeEditors() {
    if (window.PuiwAlwan) { window.PuiwAlwan.close($grid.get(0)); }
  }

  function init() {
    $store = $("textarea#puiw_color_schemes");
    $grid = $(".puiw-schemes-grid");
    if (!$store.length || !$grid.length) { return; }
    list = load();
    render();
    // the stored value now carries the built-in schemes too, without marking the page changed
    $store.val(JSON.stringify(list));

    $grid.on("click", ".scheme-edit", function () {
      closeEditors();
      render(+$(this).closest(".puiw-scheme").data("index"));
    });
    $grid.on("click", ".scheme-trash", function () {
      var i = +$(this).closest(".puiw-scheme").data("index");
      $.confirm({
        title: m.delete,
        content: t.confirm_trash_one ? t.confirm_trash_one.replace("%s", $("<b/>").text(list[i].n).prop("outerHTML")) : t.confirm_trash,
        type: "red",
        buttons: {
          no: { text: t.txtNop, keys: ["esc"] },
          yes: { text: t.txtYes, action: function () { list.splice(i, 1); render(); commit(); } },
        },
      });
    });
    $grid.on("click", ".scheme-cancel", function () {
      closeEditors();
      if ($(this).closest(".puiw-scheme").data("new")) { list.pop(); }
      render();
    });
    $grid.on("click", ".scheme-apply", function () {
      var $ed = $(this).closest(".puiw-scheme");
      var i = +$ed.data("index");
      var name = $.trim($ed.find(".puiw-scheme-name").val());
      var next = { n: name };
      var ok = name && !builtIn(name) && !list.some(function (v, j) { return j !== i && v.n === name; });
      $ed.find("input[data-key]").each(function () {
        var v = $.trim($(this).val()).toUpperCase();
        if (!isHex(v)) { ok = false; $(this).addClass("is-invalid"); }
        next[$(this).data("key")] = v;
      });
      if (!ok) {
        $ed.find(".puiw-scheme-name").toggleClass("is-invalid", !name || builtIn(name) || list.some(function (v, j) { return j !== i && v.n === name; }));
        return;
      }
      closeEditors();
      list[i] = next;
      render();
      commit();
    });
    $grid.on("keydown", ".is-editing input", function (e) {
      if (e.key === "Enter") { e.preventDefault(); $(this).closest(".puiw-scheme").find(".scheme-apply").trigger("click"); }
      if (e.key === "Escape") { e.preventDefault(); $(this).closest(".puiw-scheme").find(".scheme-cancel").trigger("click"); }
    });
    $grid.on("input", ".is-editing input", function () { $(this).removeClass("is-invalid"); });

    $(document).on("click", ".swatches-add-new", function (e) {
      e.preventDefault();
      closeEditors();
      list.push({ n: "", p: "#886655", s: "#DD9977", t: "#EECCAA" });
      render(list.length - 1);
      $grid.find(".is-editing").data("new", 1).get(0).scrollIntoView({ block: "nearest", behavior: "smooth" });
    });

    $(document).on("click", ".swatches-delete-custom", function (e) {
      e.preventDefault();
      if (list.length === BUILT_IN.length) { return; }
      $.confirm({
        title: $(this).text(),
        content: t.confirm_delete_custom,
        type: "red",
        buttons: {
          no: { text: t.txtNop, keys: ["esc"] },
          yes: { text: t.txtYes, action: function () { list = load().slice(0, BUILT_IN.length); render(); commit(); } },
        },
      });
    });

    $(document).on("click", ".swatches-export", function (e) {
      e.preventDefault();
      var custom = JSON.stringify(list.slice(BUILT_IN.length));
      $.confirm({
        title: m.export,
        content: $("<textarea class='puiw-backup-text' readonly rows='8' dir='ltr'/>").val(custom),
        buttons: { ok: { text: t.okTxt } },
      });
    });

    $(document).on("click", ".swatches-import", function (e) {
      e.preventDefault();
      var $area = $("<textarea class='puiw-backup-text' rows='8' dir='ltr' spellcheck='false'/>").attr("placeholder", m.insertErr);
      $.confirm({
        title: m.import,
        content: $area,
        buttons: {
          no: { text: t.cancelbTn, keys: ["esc"] },
          yes: { text: t.txtImport, action: function () {
            var data;
            try { data = JSON.parse($area.val()); } catch (err) { data = null; }
            var added = $.grep($.isArray(data) ? data : [], function (v) {
              return v && v.n && isHex(v.p) && isHex(v.s) && isHex(v.t) && !builtIn(v.n);
            });
            if (!added.length) {
              $.alert({ title: t.errorTxt, content: m.importErr + "<br>" + m.importErr2, type: "red" });
              return false;
            }
            added.forEach(function (v) {
              var item = { n: v.n, p: v.p.toUpperCase(), s: v.s.toUpperCase(), t: v.t.toUpperCase() };
              var at = -1;
              list.forEach(function (x, j) { if (x.n === item.n) { at = j; } });
              if (at > -1) { list[at] = item; } else { list.push(item); }
            });
            render();
            commit();
          } },
        },
      });
    });
  }

  $(init);
})(jQuery);
