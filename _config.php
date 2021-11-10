<?php

use SilverCart\Admin\Dev\ExampleData;
use SilverCart\Model\ShopEmail;

/***********************************************************************************************
 ***********************************************************************************************
 **                                                                                           ** 
 ** Registers the email template to send the Novalnet transaction information to the customer **
 ** after an order was placed using Novalnet payment methods.                                 ** 
 **                                                                                           ** 
 ***********************************************************************************************
 **********************************************************************************************/
ShopEmail::register_email_template('PaymentNovalnetTransactionInfo');

