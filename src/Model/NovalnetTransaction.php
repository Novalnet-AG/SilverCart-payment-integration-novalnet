<?php

namespace SilverCart\NovalnetGateway\Model;

use SilverStripe\ORM\DataObject;

/**
 * additional information for orders via Novalnet
 * 
 * @package Novalnet
 * @author Novalnet AG
 * @copyright Copyright by Novalnet
 * @license https://novalnet.de/payment-plugins/kostenlos/lizenz
 *
 */
class NovalnetTransaction extends DataObject {

    /**
     * DB attributes
     * 
     * @var array
     */
    private static $db = [
        'OrderId'           => 'Int', 
        'OrderNumber'       => 'Varchar(128)',      
        'Tid'     			=> 'Varchar(50)',        
        'PaymentType'       => 'Varchar(50)',                
        'CustomerId'        => 'Varchar(128)',
        'GatewayStatus'     => 'Int'
    ];

    /**
     * DB table name
     *
     * @var string
     */
    private static $table_name = 'SilvercartPaymentNovalnetTransaction';
    
    
    /**
     * update an order
     *
     * @param array $data  array with order data
     * @param array $status  boolean
     *
     * @return void
     *     
     */
    public function updateOrder($data, $status = true) {
		if ($status == false) {
			$this->GatewayStatus  = $data['tid_status'];
		} else {			    
			$this->OrderId = $data['orderId'];
			$this->OrderNumber = $data['orderNumber']; 
			$this->Tid  = $data['tid'];
			$this->PaymentType  = $data['paymentChannel'];
			$this->GatewayStatus  = $data['tid_status'];
			$this->CustomerId  = $data['customer_no'];					
		}    
        $this->write();
    }
}
