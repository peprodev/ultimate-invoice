/**
 * The plugin's own dialog, standing in for jquery-confirm's $.confirm and $.alert
 * on the settings screen and the order screen. It understands the part of that API
 * the plugin uses: title, content, type ("red" for destructive), boxWidth, buttons as
 * { key: { text, btnClass, keys, action } } and, inside an action, this.$content,
 * showLoading(), hideLoading() and setBoxWidth(). An action that returns false keeps
 * the dialog open. The open hooks run once the call has returned, so a hook may use
 * the variable the dialog was assigned to.
 */
(function ($) {
  "use strict";
  if (!$) { return; }

  var open = [];

  function close(dlg) {
    if (dlg.closed) { return; }
    dlg.closed = true;
    if (typeof dlg.opts.onClose === "function") { dlg.opts.onClose.call(dlg); }
    dlg.$el.removeClass("is-open");
    setTimeout(function () { dlg.$el.remove(); }, 160);
    open = open.filter(function (d) { return d !== dlg; });
    $(document.body).toggleClass("puiw-dialog-open", open.length > 0);
    if (dlg.lastFocus && dlg.lastFocus.focus) { dlg.lastFocus.focus(); }
  }

  function dialog(opts) {
    opts = $.extend({ title: "", content: "", type: "", buttons: {} }, opts || {});
    var danger = opts.type === "red";
    var dlg = { lastFocus: document.activeElement, opts: opts };
    var $el = $('<div class="puiw-dialog-wrap" role="presentation"><div class="puiw-dialog-box" role="dialog" aria-modal="true"></div></div>');
    var $box = $el.find(".puiw-dialog-box").toggleClass("is-danger", danger);
    if (opts.boxWidth) { $box.css("max-width", opts.boxWidth); }
    var $head = $('<div class="puiw-dialog-head"></div>').appendTo($box);
    if (opts.title) { $('<h2 class="puiw-dialog-title"></h2>').html(opts.title).appendTo($head); }
    $('<button type="button" class="puiw-dialog-x" aria-label="Close">&times;</button>').appendTo($head).on("click", function () { close(dlg); });
    var $content = $('<div class="puiw-dialog-content"></div>').html(typeof opts.content === "function" ? "" : opts.content).appendTo($box);
    var $foot = $('<div class="puiw-dialog-foot"></div>').appendTo($box);
    dlg.$el = $el; dlg.$content = $content; dlg.close = function () { close(dlg); };
    dlg.setContent = function (html) { $content.html(html); };
    dlg.setTitle = function (html) { $head.find(".puiw-dialog-title").html(html); };
    // the sending step: buttons locked and a spinner over the content until the reply
    dlg.showLoading = function () { $box.addClass("is-loading").find(".puiw-dialog-foot .button, .puiw-dialog-x").prop("disabled", true); };
    dlg.hideLoading = function () { $box.removeClass("is-loading").find(".puiw-dialog-foot .button, .puiw-dialog-x").prop("disabled", false); };
    dlg.setBoxWidth = function (w) { $box.css("max-width", w); };
    $('<div class="puiw-dialog-spinner" aria-hidden="true"></div>').appendTo($box);

    var keys = {}, names = Object.keys(opts.buttons || {});
    // the last button is the one that does the thing; it gets the filled style
    names.forEach(function (name, i) {
      var b = opts.buttons[name];
      if (typeof b === "function") { b = { action: b }; }
      var primary = i === names.length - 1;
      var $b = $('<button type="button" class="button"></button>')
        .text(b.text || name)
        .addClass(primary ? (danger ? "puiw-button-danger" : "button-primary") : "")
        .appendTo($foot)
        .on("click", function () {
          var keep = b.action ? b.action.call(dlg) : undefined;
          if (keep !== false) { close(dlg); }
        });
      (b.keys || []).forEach(function (k) { keys[String(k).toLowerCase()] = $b; });
    });
    if (!names.length) {
      $('<button type="button" class="button button-primary"></button>').text((window._peproUltimateInvoice || {}).okTxt || "OK").appendTo($foot).on("click", function () { close(dlg); });
    }
    $el.on("click", function (e) { if (e.target === $el.get(0)) { close(dlg); } });
    $el.on("keydown", function (e) {
      if (e.key === "Escape") { e.preventDefault(); (keys.esc || { trigger: function () { close(dlg); } }).trigger("click"); }
      if (e.key === "Enter" && keys.enter && !$(e.target).is("textarea")) { e.preventDefault(); keys.enter.trigger("click"); }
    });

    $el.appendTo(document.body);
    open.push(dlg);
    $(document.body).addClass("puiw-dialog-open");
    setTimeout(function () {
      $el.addClass("is-open");
      var $first = $content.find("input, textarea, select").first();
      ($first.length ? $first : $foot.find(".button").last()).trigger("focus");
    }, 10);
    setTimeout(function () {
      ["onOpenBefore", "onContentReady", "onOpen"].forEach(function (hook) {
        if (typeof opts[hook] === "function" && !dlg.closed) { opts[hook].call(dlg); }
      });
    }, 0);
    return dlg;
  }

  $.confirm = function (opts) { return dialog(opts); };
  $.alert = function (opts) {
    if (typeof opts === "string") { opts = { content: opts }; }
    opts = $.extend({}, opts);
    if (!opts.buttons) { opts.buttons = {}; }
    return dialog(opts);
  };
  $.dialog = $.alert;
  window.PuiwDialog = dialog;
})(window.jQuery);
