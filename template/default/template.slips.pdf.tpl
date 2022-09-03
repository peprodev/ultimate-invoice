<!DOCTYPE html>
<body dir="rtl">
  <div class="page body">
    <div class="flex-body">
      <div class="strippedbg sent">
        <div class="whitebg">
          <h2><strong>Sender </strong><img style="width: 60%; height: 0.6cm; float: right;" src="{{{store_postcode_barcode}}}"/></h2>
          <p><strong>{{{store_name}}}</strong></p>
          <p>Address: {{{store_address}}}</p>
          <p>PostCode: {{{store_postcode}}} | Phone: {{{store_phone}}}</p>
        </div>
      </div>
      <div class="strippedbg receive">
        <div class="whitebg">
          <h2><strong>Reciever </strong><img style="width: 60%; height: 0.6cm; float: right;" src="{{{customer_postcode_barcode}}}" /></h2>
          <p><strong>{{{customer_fullname}}}</strong></p>
          <p>Address: {{{customer_address}}}</p>
          <p>PostCode: {{{customer_postcode}}} | Phone: {{{customer_phone}}}</p>
        </div>
      </div>
      <div class="otherinfo fullwide">
        <div class="whitebg">
          <p><strong>Order ID: {{{invoice_id_en}}}</strong> | Weight: {{{invoice_total_weight}}} | Qty: {{{invoice_total_qty}}}</p>
          <p>Order date: {{{order_date_created}}}<span class="show_shipping_date"> | Shipping date: {{{order_date_shipped}}}</span> | Shipping method: {{{order_shipping_method}}}</p>
        </div>
      </div>
    </div>
  </div>
</body>
