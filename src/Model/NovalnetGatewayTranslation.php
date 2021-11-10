<?php

namespace SilverCart\NovalnetGateway\Model;

use SilverCart\Dev\Tools;
use SilverCart\Model\Payment\PaymentMethodTranslation;
use SilverCart\NovalnetGateway\Model\NovalnetGateway;
use SilverStripe\Forms\FieldList;

/**
 * Translations for the multilingual attributes of NovalnetGateway.
 * 
 * @package Novalnet
 * @author Novalnet AG
 * @copyright Copyright by Novalnet
 * @license https://novalnet.de/payment-plugins/kostenlos/lizenz
 *
 */
class NovalnetGatewayTranslation extends PaymentMethodTranslation
{
  use \SilverCart\ORM\ExtensibleDataObject;
   
    /**
     * 1:1 or 1:n relationships.
     *
     * @var array
     */
    private static $has_one = [
        'NovalnetGateway' => NovalnetGateway::class,
    ];
    /**
     * DB table name
     *
     * @var string
     */
    private static $table_name = 'SilvercartPaymentNovalnetGatewayTranslation';
    
    /**
     * Returns the translated singular name of the object. If no translation exists
     * the class name will be returned.
     * 
     * @return string
     */
    public function singular_name() : string
    {
        return Tools::singular_name_for($this);
    }

    /**
     * Returns the translated plural name of the object. If no translation exists
     * the class name will be returned.
     * 
     * @return string
     */
    public function plural_name() : string
    {
        return Tools::plural_name_for($this);
    }
    
}
