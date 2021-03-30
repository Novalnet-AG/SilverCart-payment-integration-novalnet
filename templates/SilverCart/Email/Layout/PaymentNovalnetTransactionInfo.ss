<% with $Order %>
<br /><br />
<p><%t SilverCart\Model\ShopEmail.ThankYouForYourOrder 'Thank you for your order at our shop.' %></p>
<table>
    <colgroup>
      <col width="25%"></col>
      <col width="75%"></col>
   </colgroup>
    <tr>
        <td><strong><%t SilverCart\Model\Pages\Page.ORDER_DATE 'Order date' %>:</strong></td>
        <td>{$Created.Nice}</td>
    </tr>
    <tr>
        <td><strong><%t SilverCart\Model\Order\NumberRange.ORDERNUMBER 'Ordernumber' %>:</strong></td>
        <td>{$OrderNumber}</td>
    </tr>  
</table>

<p><%t SilverCart\Model\ShopEmail.TRANSACTIONINFO 'Transaction Info' %></p>
{$Message}

<% end_with %>
<% with $Message %>
{$comments}
<% end_with %>
<p><%t SilverCart\Model\ShopEmail.REGARDS 'Best regards' %>,</p>
<p><%t SilverCart\Model\ShopEmail.YOUR_TEAM 'Your SilverCart ecommerce team' %></p>
