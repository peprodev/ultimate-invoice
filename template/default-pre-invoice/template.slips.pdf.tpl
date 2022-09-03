<!DOCTYPE html>
<body dir="rtl">
  <div class="page body">
    <div class="flex-body">
      <div class="strippedbg sent">
        <div class="whitebg">
          <h2><strong>فرستنده </strong><img style="width: 60%; height: 0.6cm; float: left;" src="{{{store_postcode_barcode}}}"/></h2>
          <p><strong>{{{store_name}}}</strong></p>
          <p>آدرس: {{{store_address}}}</p>
          <p>کدپستی: {{{store_postcode}}} | تلفن: {{{store_phone}}}</p>
        </div>
      </div>
      <div class="strippedbg receive">
        <div class="whitebg">
          <h2><strong>گیرنده </strong><img style="width: 60%; height: 0.6cm; float: left;" src="{{{customer_postcode_barcode}}}" /></h2>
          <p><strong>{{{customer_fullname}}}</strong></p>
          <p>آدرس: {{{customer_address}}}</p>
          <p>کدپستی: {{{customer_postcode}}} | تلفن: {{{customer_phone}}}</p>
        </div>
      </div>

      <div class="otherinfo fullwide">
        <div class="whitebg">
          <p><strong>شناسه سفارش: {{{invoice_id_en}}}</strong> | وزن کل: {{{invoice_total_weight}}} | تعداد کل: {{{invoice_total_qty}}}</p>
          <p>تاریخ و ساعت سفارش: {{{order_date_created}}}<span class="show_shipping_method"> | تاریخ و ساعت تحویل: {{{order_date_shipped}}}</span> | روش حمل و نقل: {{{order_shipping_method}}}</p>
        </div>
      </div>

    </div>
  </div>
</body>
