/**
 * Alwan colour picker for the invoice settings, the setup wizard and the order box,
 * the same bootstrap PigmentDev APS uses.
 *
 * Progressive enhancement over colour inputs: the original <input> stays in
 * the DOM as the form value store (existing listeners keep working because
 * we re-fire "input"/"change" on it), and a styled swatch button opens a
 * minimal Alwan popover — picker + hue slider + hex field; no opacity,
 * swatches or copy button (self-hosted assets/vendor/alwan, v2.3.1). If the
 * library is missing or throws, the native input is left untouched.
 *
 * Targets: input.wc-color-picker and input[data-puiw-color]. Re-scan dynamic
 * markup with window.PuiwAlwan.scan(root).
 */
(function () {
  "use strict";

  var SELECTOR = "input.wc-color-picker, input[data-puiw-color]";

  function normHex(v, fallback) {
    v = (v || "").trim();
    if (v && v[0] !== "#") { v = "#" + v; }
    if (/^#[0-9a-fA-F]{3}$/.test(v)) { v = "#" + v[1] + v[1] + v[2] + v[2] + v[3] + v[3]; }
    return /^#[0-9a-fA-F]{6}$/.test(v) ? v.toLowerCase() : (fallback || "#000000");
  }

  /** Dark when an ancestor says so, else when the nearest painted background is dark. */
  function isDarkContext(el) {
    var flagged = el.closest("[data-puiw-theme]");
    if (flagged) { return flagged.getAttribute("data-puiw-theme") === "dark"; }
    var node = el;
    while (node && node.nodeType === 1) {
      var bg = window.getComputedStyle(node).backgroundColor;
      var m = bg && bg.match(/rgba?\(([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+))?/);
      if (m && (m[4] === undefined || parseFloat(m[4]) > 0.5)) {
        var lum = (0.2126 * m[1] + 0.7152 * m[2] + 0.0722 * m[3]) / 255;
        return lum < 0.45;
      }
      node = node.parentElement;
    }
    return false;
  }

  function eventHex(ev) {
    if (!ev) { return ""; }
    if (typeof ev.hex === "string") { return ev.hex; }
    if (typeof ev.hex === "function") { try { return ev.hex(); } catch (e) { return ""; } }
    if (typeof ev.value === "string") { return ev.value; }
    return "";
  }

  function attach(input) {
    if (!window.Alwan || input._puiwAlwan) { return; }
    input._puiwAlwan = true;

    var start = normHex(input.value, "#ffffff");
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "puiw-alwan-swatch";
    var label = input.id ? document.querySelector('label[for="' + input.id + '"]') : input.closest("label");
    var name = (input.getAttribute("data-puiw-color-label") || (label ? label.textContent : "") || "").trim();
    var accessibleName = (name ? name + ": " : "") + (window.puiwAlwanL10n && window.puiwAlwanL10n.pick ? window.puiwAlwanL10n.pick : "Pick a colour");
    btn.setAttribute("aria-label", accessibleName);
    btn.style.background = start;
    input.insertAdjacentElement("beforebegin", btn);
    input.classList.add("puiw-alwan-native");

    // A click on a wrapping <label> would open the OS colour dialog of a
    // native colour input — route it to the swatch so Alwan opens instead.
    if (label && input.type === "color") {
      label.addEventListener("click", function (e) {
        if (e.target === btn || btn.contains(e.target)) { return; }
        if (e.target.closest && e.target.closest(".alwan")) { return; }
        e.preventDefault();
        btn.click();
      });
    }

    var picker;
    try {
      picker = new window.Alwan(btn, {
        theme: isDarkContext(input) ? "dark" : "light",
        format: "hex",
        opacity: false,
        preview: false,
        copy: false,
        swatches: [],
        inputs: { hex: true },
        color: start,
        default: start,
        position: "bottom-start",
        margin: 6
      });
    } catch (e) {
      // Library failed — undo the enhancement; the native input still works.
      btn.remove();
      input.classList.remove("puiw-alwan-native");
      input._puiwAlwan = false;
      return;
    }

    // Alwan swaps the swatch for its own reference button (".alwan__ref") and
    // gives it an empty label; carry our class and accessible name over.
    var ref = input.previousElementSibling;
    if (ref && ref !== btn && ref.classList && ref.classList.contains("alwan__ref")) {
      ref.classList.add("puiw-alwan-swatch");
      ref.style.background = start;
      btn = ref;
    }
    function labelRef() {
      btn.setAttribute("aria-label", accessibleName);
      btn.setAttribute("title", accessibleName);
    }
    labelRef();
    input._puiwPicker = picker;
    try { picker.on("open", labelRef); picker.on("close", labelRef); } catch (e) { /* older Alwan */ }

    picker.on("color", function (ev) {
      var hex = normHex(eventHex(ev), "");
      if (!hex) { return; }
      btn.style.background = hex;
      if ((input.value || "").toLowerCase() === hex) { return; }
      input.value = hex;
      try { input.dispatchEvent(new Event("input", { bubbles: true })); } catch (e) { /* old browsers */ }
      try { input.dispatchEvent(new Event("change", { bubbles: true })); } catch (e) { /* old browsers */ }
    });

    // Writes from elsewhere (typing in the field, presets, resets) keep the
    // swatch and the picker in sync; the equality guard above stops loops.
    var sync = function () {
      var hex = normHex(input.value, "");
      if (!hex) { return; }
      btn.style.background = hex;
      try { picker.setColor(hex); } catch (e) { /* ignore */ }
    };
    input.addEventListener("change", sync);
    input.addEventListener("input", sync);
  }

  function scan(root) {
    (root || document).querySelectorAll(SELECTOR).forEach(attach);
  }
  function close(root) {
    (root || document).querySelectorAll(SELECTOR).forEach(function (input) {
      if (input._puiwPicker) { try { input._puiwPicker.close(); } catch (e) { /* ignore */ } }
    });
  }
  window.PuiwAlwan = { scan: scan, attach: attach, close: close };

  if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", function () { scan(document); }); } else { scan(document); }
})();
