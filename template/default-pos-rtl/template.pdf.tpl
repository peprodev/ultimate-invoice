<p class="page body">
  <div class="center rows">
    <div><img height="64px" src="{{{store_logo}}}" /></div>
    <h2 style="margin: 0.3rem 0 0.7rem 0;"><strong>{{{store_name}}}</strong></h2>
    <table class="table-full">
      <tr>
        <td>
          <p dir="rtl">{{{store_address}}}</p>
          <p style="text-align: left;"><bdi dir="ltr">{{{store_phone}}}</bdi></p>
        </td>
        <td class="center" style="text-align: center;">
          <div class="center barcode rows" style="text-align: center;">
            <div><small><img alt="{{{invoice_id_en}}}" style="margin: 0.4rem; width: 3cm; height: 0.7cm" src="{{{invoice_barcode}}}" /></small></div>
          </div>
        </td>
      </tr>
    </table>
  </div>
  <table class="table-full">
    <tr><td><strong>مشتری:</strong></td><td><span>{{{customer_fullname}}}</span></td></tr>
    <tr><td><strong>موبایل:</strong></td><td><bdi dir="ltr">{{{customer_phone}}}</bdi></td></tr>
    <tr><td><strong>ایمیل:</strong></td><td><bdi dir="ltr">{{{customer_email}}}</bdi></td></tr>
    <tr><td><strong>تاریخ سفارش:</strong></td><td><bdi dir="ltr">{{{order_date_created}}}</bdi></td></tr>
    <tr><td><strong>شماره فاکتور: </strong></td><td><bdi dir="ltr">{{{invoice_id_en}}}</bdi></td></tr>
  </table>
  <table class="table-full-2n">
    <tr class="show_order_items bgONE">
      <th style="background: #ccc !important;" width="0.2cm" class="show_product_n">#</th>
      <th style="background: #ccc !important;" class="show_product_title_description">شرح کالا</th>
      <th style="background: #ccc !important; text-align: left;" class="show_product_total_price">تعداد</th>
    </tr>
    {{{invoice_products_list}}}
  </table>
  <table class="table-full">
    {{{invoice_final_totals}}}
    <tr>
      <th colspan="2">مجموع اقلام سفارش:</th><td colspan="1">{{{invoice_total_qty}}}</td>
    </tr>
    <tr>
      <th colspan="2" style="vertical-align: middle;"><strong style="font-size: 1.16rem;">جمع نهایی:</strong></th><td colspan="1" style="vertical-align: middle;"><strong style="font-size: 1.16rem;">{{{invoice_total}}}</strong></td>
    </tr>
    {{{order_notes_new}}}
    <tr class="show_custom_footer" style="{{{show_custom_footer_hc}}}">
      <div style="{{{show_custom_footer_dn}}}">
        <td class="show_custom_footer" colspan="3" style="vertical-align: middle; text-align: center; font-weight: bold; padding: 1rem; {{{show_custom_footer_dn}}}">{{{invoices_footer}}}</td>
      </div>
    </tr>
    <tr><td colspan="3" style="vertical-align: middle; text-align: center;">{{{thermal_copyright}}}</td></tr>
  </table>
</div>