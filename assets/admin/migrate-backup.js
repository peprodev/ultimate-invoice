/*
 * @Author: Amirhossein Hosseinpour <https://amirhp.com>
 * @Date Created: 2021/07/12 16:58:32
 * @Last modified by: amirhp-com <its@amirhp.com>
 */

/* Migrate/Backup panel: export, import and the danger zone, all over ajax */
(function ($) {
  "use strict";
  var t = window._i18n || {}, m = window.msg || {};

  function request(wparam, extra) {
    return $.ajax({
      type: "POST",
      dataType: "json",
      url: t.ajax,
      data: $.extend({ action: t.td, nonce: t.nonce, wparam: wparam }, extra || {}),
    });
  }

  function fail(text) {
    $.alert({ title: t.errorTxt, content: text || t.UnknownErr || t.errorTxt, type: "red" });
  }

  function copy(text) {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text);
      return;
    }
    var $tmp = $("<textarea/>").val(text).css({ position: "fixed", top: "-1000px" }).appendTo(document.body);
    $tmp.get(0).select();
    try { document.execCommand("copy"); } catch (e) { /* nothing else to try */ }
    $tmp.remove();
  }

  function download(name, text, type) {
    var url = URL.createObjectURL(new Blob([text], { type: type }));
    $("<a/>").attr({ href: url, download: name }).appendTo(document.body).get(0).click();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }

  function stamp() {
    var d = new Date(), p = function (n) { return (n < 10 ? "0" : "") + n; };
    return d.getFullYear() + "-" + p(d.getMonth() + 1) + "-" + p(d.getDate());
  }

  /* a read-only copy of the settings, fetched fresh so a save made a minute ago is in it */
  function showExport(wparam, title, filename, type) {
    var jc = $.confirm({ title: title, content: "", buttons: {} });
    jc.showLoading();
    request(wparam).done(function (r) {
      if (!r || !r.success) { jc.close(); fail(r && r.data && r.data.msg); return; }
      var text = "string" === typeof r.data.data ? r.data.data : JSON.stringify(r.data.data, null, 2);
      jc.close();
      $.confirm({
        title: title,
        content: $("<textarea class='puiw-backup-text' readonly rows='12' dir='ltr' spellcheck='false'/>").val(text),
        boxWidth: "720px",
        buttons: {
          copy: { text: t.txtCopy, action: function () { copy(text); return false; } },
          save: { text: t.downloadTxt, action: function () { download(filename + "-" + stamp() + "." + (type === "json" ? "json" : "php"), text, type === "json" ? "application/json" : "text/plain"); return false; } },
        },
      });
    }).fail(function () { jc.close(); fail(); });
  }

  $(document).on("click", ".backup-export", function (e) {
    e.preventDefault();
    showExport("settings-json", m.export || t.exportTitle, "ultimate-invoice-settings", "json");
  });
  $(document).on("click", ".backup-export-php", function (e) {
    e.preventDefault();
    showExport("settings-php", t.phpTitle, "ultimate-invoice-settings", "php");
  });

  $(document).on("click", ".backup-import", function (e) {
    e.preventDefault();
    var $body = $("<div/>")
      .append($("<textarea class='puiw-backup-text' rows='10' dir='ltr' spellcheck='false'/>").attr("placeholder", m.insertErr))
      .append($("<label class='puiw-backup-file'/>").text(t.fromFileTxt).append("<input type='file' accept='.json,application/json'/>"));
    $body.on("change", "input[type=file]", function () {
      var file = this.files && this.files[0];
      if (!file) { return; }
      var reader = new FileReader();
      reader.onload = function () { $body.find("textarea").val(String(reader.result || "")); };
      reader.readAsText(file);
    });
    var jc = $.confirm({
      title: m.import || t.txtImport,
      content: $body,
      boxWidth: "720px",
      buttons: {
        no: { text: t.cancelbTn, keys: ["esc"] },
        yes: { text: t.txtImport, action: function () {
          var data;
          try { data = JSON.parse($body.find("textarea").val()); } catch (err) { data = null; }
          if (!data || "object" !== typeof data || $.isArray(data)) {
            fail((m.importErr || "") + "<br>" + (m.importErr2 || ""));
            return false;
          }
          jc.showLoading();
          request("import_options", { lparam: data }).done(function (r) {
            jc.close();
            if (r && r.success) { done(r.data.msg); } else { fail(r && r.data && r.data.msg); }
          }).fail(function () { jc.hideLoading(); fail(); });
          return false;
        } },
      },
    });
  });

  /* the danger zone: confirm, run in place, then reload this panel so every field shows the new values */
  $(document).on("click", ".backup-danger", function (e) {
    e.preventDefault();
    var $btn = $(this);
    var jc = $.confirm({
      title: $btn.text(),
      content: $btn.data("ask"),
      type: "red",
      buttons: {
        no: { text: t.txtNop, keys: ["esc"] },
        yes: { text: t.txtYes, action: function () {
          jc.showLoading();
          request($btn.data("action")).done(function (r) {
            jc.close();
            if (r && r.success) { done(r.data.msg); } else { fail(r && r.data && r.data.msg); }
          }).fail(function () { jc.hideLoading(); fail(); });
          return false;
        } },
      },
    });
  });

  function done(text) {
    // nothing here would know the new values without a fresh page, and the save bar
    // must not offer to save the stale ones back
    window.onbeforeunload = null;
    $(window).off("beforeunload");
    if (window.PuiwToast) { window.PuiwToast(text); }
    $.alert({ title: t.successTtl, content: text + "<br>" + t.reloadingTxt, type: "green" });
    setTimeout(function () {
      window.location.hash = "#puiw-migrate";
      window.location.reload();
    }, 1200);
  }
})(jQuery);
