<div class="page body">
  <table id="headertitles">
    <tr class="headtr">
      <td class="header-item-wrapper" style="padding: 0.3cm; width: 4cm;"><img height="64px" src="{{{store_logo}}}" /></td>
      <td class="header-item-wrapper" style="width: 70%; font-weight: bold; font-size: 1.7rem; text-align: right; padding: 0.1cm .5cm; display: block !important;">{{{invoice_title}}}</td>
      <td class="header-item-wrapper" style="width: 4cm;padding: 1rem;">
        <div class="show_invoices_id_barcode" if="show_invoices_id_barcode">
          <div>شماره فاکتور</div>
          <img alt='{{{invoice_id_en}}}' style="margin: .4rem;width: 4cm;height: 1cm;" src='{{{invoice_barcode}}}'/>
          {{{invoice_id_en}}}
        </div>
      </td>
      <td class="header-item-wrapper" style="width: 4cm;padding: 1rem;">
        <div class="show_shipping_ref_id" if="show_shipping_ref_id">
          <div>کد رهگیری مرسوله</div>
          <img alt='{{{invoice_track_id_en}}}' style="margin: .4rem;width: 4cm;height: 1cm;" src='{{{invoice_track_barcode}}}'/>
          {{{invoice_track_id_en}}}
        </div>
      </td>
    </tr>
  </table>
</div>
