<?php

namespace SilverCart\NovalnetGateway\Model;

use SilverStripe\ORM\DataObject;
use SilverCart\Dev\Tools;
use SilverCart\Model\ShopEmail;
use SilverStripe\Control\Email\Email;
use SilverStripe\ORM\DB;
use SilverCart\Model\Order\Order;
use SilverCart\Model\Payment\PaymentStatus;
use SilverCart\NovalnetGateway\Model\NovalnetGateway;
use SilverStripe\ORM\FieldType\DBMoney;
use SilverCart\Admin\Model\Config;
use SilverStripe\Security\Member;

/**
 * Novalnet callback script
 *
 * @package Novalnet
 * @author Novalnet AG
 * @copyright Copyright by Novalnet
 * @license https://novalnet.de/payment-plugins/kostenlos/lizenz
 *
 */
class NovalnetCallback extends DataObject {

    /**
     * DB attributes
     *
     * @var array
     */
    private static $db = [        
        'OrderNumber'       => 'Varchar(128)',
        'Amount'            => 'Int',
        'CallbackTId'       => 'Varchar(50)',
        'OrgTId'            => 'Varchar(50)',
        'PaymentType'       => 'Varchar(50)',                
    ];

    /**
     * DB table name
     *
     * @var string
     */
    private static $table_name = 'SilvercartPaymentNovalnetCallback';

    
    protected $_captureParams; // Get REQUEST param

      /**
     * Type of payment available - Level : 0
     *
     * @var array
     */
    private $aryPayments = ['CREDITCARD', 'INVOICE_START', 'DIRECT_DEBIT_SEPA', 'GUARANTEED_DIRECT_DEBIT_SEPA',
        'GUARANTEED_INVOICE', 'PAYPAL', 'ONLINE_TRANSFER', 'IDEAL', 'EPS', 'GIROPAY', 'PRZELEWY24', 'CASHPAYMENT'];

    /**
     * Type of Chargebacks available - Level : 1
     *
     * @var array
     */
    private $aryChargebacks = ['RETURN_DEBIT_SEPA', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK',
        'REFUND_BY_BANK_TRANSFER_EU', 'PAYPAL_BOOKBACK', 'PRZELEWY24_REFUND', 'REVERSAL', 'CASHPAYMENT_REFUND',
        'GUARANTEED_INVOICE_BOOKBACK', 'GUARANTEED_SEPA_BOOKBACK'];

    /**
     * Type of Credit entry payment and Collections available - Level : 2
     *
     * @var array
     */
    private $aryCollection = ['INVOICE_CREDIT', 'CREDIT_ENTRY_CREDITCARD', 'CREDIT_ENTRY_SEPA', 'DEBT_COLLECTION_SEPA',
        'DEBT_COLLECTION_CREDITCARD', 'ONLINE_TRANSFER_CREDIT', 'CASHPAYMENT_CREDIT',
        'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'];

    /**
     * Types of payment available
     *
     * @var array
     */
    private $paymentTypes = [
        'novalnetcreditcard' => ['CREDITCARD', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK', 'CREDIT_ENTRY_CREDITCARD',
            'DEBT_COLLECTION_CREDITCARD'],
        'novalnetsepa' => ['DIRECT_DEBIT_SEPA', 'GUARANTEED_SEPA_BOOKBACK', 'RETURN_DEBIT_SEPA', 'DEBT_COLLECTION_SEPA',
            'CREDIT_ENTRY_SEPA', 'REFUND_BY_BANK_TRANSFER_EU', 'GUARANTEED_DIRECT_DEBIT_SEPA'],
        'novalnetideal' => ['IDEAL', 'REFUND_BY_BANK_TRANSFER_EU', 'ONLINE_TRANSFER_CREDIT', 'REVERSAL', 'CREDIT_ENTRY_DE',
            'DEBT_COLLECTION_DE'],
        'novalnetsofort' => ['ONLINE_TRANSFER', 'REFUND_BY_BANK_TRANSFER_EU', 'ONLINE_TRANSFER_CREDIT', 'REVERSAL',
            'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'],
        'novalnetpaypal' => ['PAYPAL', 'PAYPAL_BOOKBACK'],
        'novalnetprepayment' => ['INVOICE_START', 'INVOICE_CREDIT', 'REFUND_BY_BANK_TRANSFER_EU'],
        'novalnetcashpayment' => ['CASHPAYMENT', 'CASHPAYMENT_CREDIT', 'CASHPAYMENT_REFUND'],
        'novalnetinvoice' => ['INVOICE_START', 'INVOICE_CREDIT', 'GUARANTEED_INVOICE', 'REFUND_BY_BANK_TRANSFER_EU',
            'GUARANTEED_INVOICE_BOOKBACK', 'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'],
        'novalneteps' => ['EPS', 'ONLINE_TRANSFER_CREDIT', 'REFUND_BY_BANK_TRANSFER_EU', 'REVERSAL', 'CREDIT_ENTRY_DE',
            'DEBT_COLLECTION_DE'],
        'novalnetgiropay' => ['GIROPAY', 'ONLINE_TRANSFER_CREDIT', 'REFUND_BY_BANK_TRANSFER_EU', 'REVERSAL',
            'CREDIT_ENTRY_DE', 'DEBT_COLLECTION_DE'],
        'novalnetprzelewy24' => ['PRZELEWY24', 'PRZELEWY24_REFUND']];


    /**
     * i18n for field labels
     *
     * @param boolean $includerelations a boolean value to indicate if the labels returned include relation fields
     *
     * @return array
     *
     * @author Novalnet AG
     */
    public function fieldLabels($includerelations = true) : array
    {
        return array_merge(
            parent::fieldLabels($includerelations),
            array(

                'CriticalErrorMessage1'     => _t(self::class . '.CriticalErrorMessage1', 'Critical error on shop system '),
                'CriticalErrorMessage2'     => _t(self::class . '.CriticalErrorMessage2', ' : order not found for TID: '),
                'CriticalMessageSubject'    => _t(self::class . '.CriticalMessageSubject', 'Dear Technic team,<br/><br/>Please evaluate this transaction and contact our payment module team at Novalnet.<br/><br/>'),
                'MerchantId'    			=> _t(self::class . '.MerchantId', 'Merchant ID: '),
                'ProjectId'    				=> _t(self::class . '.ProjectId', 'Project ID: '),                
                'TidStatus'   				=> _t(self::class . '.TidStatus', 'TID status: '),
                'OrderNo'    				=> _t(self::class . '.OrderNo', 'Order no: '),
                'PaymentType'    			=> _t(self::class . '.PaymentType', 'Payment type: '),
                'Email'    					=> _t(self::class . '.Email', 'E-mail: '),
                'Regards'    				=> _t(self::class . '.Regards', '<br/><br/>Regards,<br/>Novalnet Team'),                                
                'TransactionDetails'    	=> _t(self::class . '.TransactionDetails', 'Novalnet transaction details'),                                
                'TransactionID'    			=> _t(self::class . '.TransactionID', 'Novalnet transaction ID: '),                                
                'TestMode'    				=> _t(self::class . '.TestMode', 'Test order'),                                
                'GuaranteePayment' 			=> _t(self::class . 'GuaranteePayment','This is processed as a guarantee payment'),               
                'OrderConfirmation' 		=> _t(self::class . 'OrderConfirmation','Order Confirmation - Your Order '),               
                'OrderConfirmation1' 		=> _t(self::class . 'OrderConfirmation1',' with '),               
                'OrderConfirmation2' 		=> _t(self::class . 'OrderConfirmation2',' has been confirmed'),               
                'OrderConfirmation3' 		=> _t(self::class . 'OrderConfirmation3','We are pleased to inform you that your order has been confirmed'),               
                'PaymentInformation' 		=> _t(self::class . 'PaymentInformation','Payment Information:'),               
            )
        );
    }

    /**
     * Processes the callback script
     *
     * @param array $aryCaptureParams     
     * @return null
     */
    public function startProcess($aryCaptureParams)
    {
        $this->_captureParams     = array_map('trim', $aryCaptureParams);
       		
        // Validate the request params
        if (!$this->validateCaptureParams()) {
            return;
        }

        $nntransHistory = $this->getOrderReference();
        // Check getOrderReference returns false value
        if (!$nntransHistory) {
            return;
        }

        // Handle transaction cancellation
        if ($this->transactionCancellation($nntransHistory))
            return false;

        if (!empty($this->_captureParams['order_no']) && $nntransHistory->OrderNumber != $this->_captureParams['order_no']) {
            return $this->debugMessage('Novalnet callback received. Order no is not valid', $this->_captureParams['order_no']);
        } elseif (empty($nntransHistory->PaymentType) || !in_array($this->_captureParams['payment_type'], $this->paymentTypes[$nntransHistory->PaymentType])) {
            return $this->debugMessage('Novalnet callback received. Payment Type [' . $this->_captureParams['payment_type'] . '] is not valid');
        }
       $callbacklogParams = [
            'callback_tid' => $this->_captureParams['tid'],
            'org_tid' => $nntransHistory->transactionId,
            'callback_amount' => $aryCaptureParams['amount'],
            'order_no' => $nntransHistory->OrderNumber,
            'payment_type' => $this->_captureParams['payment_type'],
            'gatewayStatus' => $this->_captureParams['tid_status'],
            'date' => date('Y-m-d H:i:s'),            
        ];

        if (in_array($aryCaptureParams['payment_type'], $this->aryPayments)) {
            $this->availablePayments($nntransHistory, $callbacklogParams);
        } elseif (in_array($aryCaptureParams['payment_type'], $this->aryChargebacks)) {
            $this->chargebackPayments($nntransHistory);
        } elseif (in_array($aryCaptureParams['payment_type'], $this->aryCollection)) {
            $this->collectionsPayments($nntransHistory, $callbacklogParams);
        }
    }

    /**
     * Validate the required params to process callback
     *
     * @return boolean
     */
    protected function validateCaptureParams()
    {
		// Validate Authenticated IP
        $realHostIp = gethostbyname('pay-nn.de');
        if (empty($realHostIp)) {
            return $this->debugMessage('Novalnet HOST IP missing');
        }

		$gatewayClass = new NovalnetGateway();
        $callerIp = $gatewayClass->getRemoteAddress();
		
		$manualTestingVendorScript = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->ManualTestingVendorScript;
		
        if ($callerIp != $realHostIp && !$manualTestingVendorScript) {
            return $this->debugMessage('Unauthorised access from the IP [' . $callerIp . ']');
        }

        if (!array_filter($this->_captureParams)) {
            return $this->debugMessage('No params passed over!');
        }
        
        $hParamsRequired = array('vendor_id', 'tid', 'payment_type', 'status', 'tid_status');

        $this->_captureParams['shop_tid'] = $this->_captureParams['tid'];
        if (in_array($this->_captureParams['payment_type'], array_merge($this->aryChargebacks, $this->aryCollection))) {
            array_push($hParamsRequired, 'tid_payment');
            $this->_captureParams['shop_tid'] = $this->_captureParams['tid_payment'];
        }

        $error = '';
        foreach ($hParamsRequired as $v) {
            if (!isset($this->_captureParams[$v]) || ($this->_captureParams[$v] == '')) {
                $error .= 'Required param (' . $v . ') missing! <br>';
            } elseif (in_array($v, ['tid', 'signup_tid', 'tid_payment'])
                && !preg_match('/^[0-9]{17}$/', $this->_captureParams[$v])) {
                $error .= 'Novalnet callback received. TID [' . $this->_captureParams[$v] . '] is not valid.';
            }
        }
        if (!empty($error)) {
            return $this->debugMessage($error, '');
        }
        return true;
    }

    /**
     * Get order reference from the novalnetGatewayOrder table on shop database
     *
     * @return array
     */
    protected function getOrderReference()
    {
        if (!empty($this->_captureParams['order_no'])) {
			$nntransHistory = NovalnetTransaction::get()->filter('OrderNumber', $this->_captureParams['order_no'])->first();
		} else {
			$nntransHistory = NovalnetTransaction::get()->filter('Tid', $this->_captureParams['shop_tid'])->first();
		}

        if (empty($nntransHistory)) {
            list($subject, $message) = $this->NotificationMessage();
            ShopEmail::send_email('technic@novalnet.de', $subject, $message);
            $this->debugMessage($message);
            return false;
        }
        
        $amount = Order::get()->filter('OrderNumber', $nntransHistory->OrderNumber)->first()->AmountTotalAmount;
        
        $query          = "SELECT SUM(amount) AS paid_amount FROM SilvercartPaymentNovalnetCallback WHERE OrderNumber = '". $nntransHistory->OrderNumber."'";
        $result         = DB::query($query);
        $paid_amount = $result->first()['paid_amount'];
        $nntransHistory->amount = $amount*100;
        $nntransHistory->paidAmount = $paid_amount;   
                 
       return $nntransHistory;

    }

    /**
     * Initial level payment process
     *
     * @param object $nntransHistory
     * @param array $callbacklogParams
     * @return string
     */
    protected function availablePayments($nntransHistory, $callbacklogParams)
    {
		$gatewayClass = new NovalnetGateway();
        $formatedAmount = $gatewayClass->formatedAmount($this->_captureParams, true);
        
        if ($this->_captureParams['payment_type'] == 'PAYPAL') {
            return $this->handleInitialLevelPaypal($nntransHistory, $callbacklogParams);
        } elseif ($this->_captureParams['payment_type'] == 'PRZELEWY24') {
            if ($this->_captureParams['tid_status'] == '100') {
                 if ($nntransHistory->paidAmount == 0) {
                    // Full Payment paid Update order status due to full payment
                    $callbackComments = 'Novalnet Callback Script executed successfully for the TID: ' . $this->_captureParams['tid'] . ' with amount ' . $formatedAmount. ' on ' . date('Y-m-d H:i:s');
                    $this->setCallbackComments(['comments' => $callbackComments, 'order_no' => $nntransHistory->OrderNumber]);
                    $this->insertCallbackOrder($callbacklogParams);
                    $this->sendNotifyMail(['comments' => $callbackComments, 'order_no' => $nntransHistory->OrderNumber]);
                    return $this->debugMessage($callbackComments, $nntransHistory->OrderNumber);
                } else {
                    return $this->debugMessage("Novalnet callback received. Order already paid.", $nntransHistory->OrderNumber);
                }

            } elseif ($this->_captureParams['tid_status'] != '86') {
               $message = (($this->_captureParams['status_message'])
                    ? $this->_captureParams['status_message'] : (($this->_captureParams['status_text'])
                        ? $this->_captureParams['status_text'] : (($this->_captureParams['status_desc'])
                            ? $this->_captureParams['status_desc']
                            : 'Payment was not successful. An error occurred.')));
                $callbackComments = 'The transaction has been cancelled due to: ' . $message;

                $this->setCallbackComments(['comments' => $callbackComments, 'order_no' => $nntransHistory->OrderNumber]);
                // Logcallback process
                $this->insertCallbackOrder($callbacklogParams);
                // Send notification mail to Merchant
                $this->sendNotifyMail(['comments' => $callbackComments, 'order_no' => $nntransHistory->OrderNumber]);
                return $this->debugMessage($callbackComments, $nntransHistory->OrderNumber);
            }
        } elseif (in_array(
            $this->_captureParams['payment_type'], ['INVOICE_START', 'GUARANTEED_INVOICE', 'DIRECT_DEBIT_SEPA', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'CREDITCARD']) && in_array($nntransHistory->GatewayStatus, [75, 91, 99, 98]) && in_array($this->_captureParams['tid_status'], [91, 99, 100]) && $this->_captureParams['status'] == '100') {
            return $this->handleInitialLevelPayments($nntransHistory, $callbacklogParams);
        } else {
            return $this->debugMessage('Novalnet callback received. Payment type ( ' . $this->_captureParams['payment_type'] . ' ) is
                not applicable for this process!', $nntransHistory->OrderNumber);
        }
    }

     /**
     * Level 2 payment process
     *
     * @param object $nntransHistory
     * @param array $callbacklogParams
     * @return string
     */
    protected function collectionsPayments($nntransHistory, $callbacklogParams)
    {
		$gatewayClass = new NovalnetGateway();
        $formatedAmount = $gatewayClass->formatedAmount($this->_captureParams, true);
        // Credit entry payment and Collections available
        if ($nntransHistory->paidAmount < $nntransHistory->amount && in_array($this->_captureParams['payment_type'], ['INVOICE_CREDIT', 'CASHPAYMENT_CREDIT', 'ONLINE_TRANSFER_CREDIT'])
        ) {
            $paidAmount = (empty($nntransHistory->paidAmount) ? 0 : $nntransHistory->paidAmount) + $this->_captureParams['amount'];
            $callbackComments = PHP_EOL. 'Novalnet Callback Script executed successfully for the TID: ' . $this->_captureParams['tid_payment'] . ' with amount ' . $formatedAmount. ' on ' . date('Y-m-d H:i:s') . '. Please refer PAID transaction in our Novalnet Merchant Administration with the TID: ' . $this->_captureParams['tid'];
            $this->setCallbackComments(['comments' => $callbackComments, 'order_no' => $nntransHistory->OrderNumber]);
            $callbacklogParams['callback_amount'] = $paidAmount;
            $this->insertCallbackOrder($callbacklogParams);
            $CallbackStatus = NovalnetGateway::get()->filter('PaymentChannel', $nntransHistory->PaymentType)->first()->CallbackStatus;         
            if ($paidAmount >= $nntransHistory->amount
                && $this->_captureParams['payment_type'] != 'ONLINE_TRANSFER_CREDIT') {
                    $this->setPaymentStatus($nntransHistory, $CallbackStatus);
                     $message = $this->getTransactionDetails();
                    DB::query(
				sprintf(
                    "UPDATE SilvercartOrder SET PaymentReferenceMessage= '".$message."'  WHERE OrderNumber = '%s'",
                    $nntransHistory->OrderNumber
				));	
            }
            $this->sendNotifyMail(['comments' => $callbackComments, 'order_no' => $nntransHistory->OrderNumber]);
            return $this->debugMessage($callbackComments, $nntransHistory->OrderNumber);
        } else {
            return $this->debugMessage('Novalnet Callback script received. Order already Paid', $nntransHistory->OrderNumber);
        }
    }

     /**
     * Handle initial level payments
     *
     * @param object $nntransHistory
     * @param array $callbacklogParams
     * @return string
     */
    protected function handleInitialLevelPayments($nntransHistory, $callbacklogParams)
    {
        $callbackComments = '';
        if (in_array($this->_captureParams['tid_status'], [99, 91]) && $nntransHistory->GatewayStatus == 75) {
            $callbackComments = PHP_EOL.'The transaction status has been changed from pending to on hold for the TID:'. $this->_captureParams['shop_tid']. ' on '. date('Y-m-d H:i:s');
            $OnholdOrderStatus = NovalnetGateway::get()->filter('PaymentChannel', $nntransHistory->PaymentType)->first()->OnholdOrderStatus;         
            $this->setPaymentStatus($nntransHistory, $OnholdOrderStatus);
        } elseif ($this->_captureParams['tid_status'] == 100 && in_array($nntransHistory->GatewayStatus, [75, 91, 99, 98])) {
            $callbackComments = PHP_EOL. 'The transaction has been confirmed on '. date('Y-m-d H:i:s');  
            $CallbackStatus = ($nntransHistory->PaymentType == 'novalnetinvoice' && $this->_captureParams['tid_status'] == 100 && $nntransHistory->GatewayStatus ==75) ?  NovalnetGateway::get()->filter('PaymentChannel', $nntransHistory->PaymentType)->first()->CallbackStatus : NovalnetGateway::get()->filter('PaymentChannel', $nntransHistory->PaymentType)->first()->OrderCompletionStatus;         
            $this->setPaymentStatus($nntransHistory, $CallbackStatus);
        }
		if (!empty($callbackComments)) {
			if ($this->_captureParams['tid_status'] == '100' && $this->_captureParams['payment_type'] == 'GUARANTEED_INVOICE') {      
				$gatewayClass = new NovalnetGateway();
				$message =  '';
				$message .=  $this->getTransactionDetails();	
				$message .= PHP_EOL.$this->fieldLabel('GuaranteePayment').PHP_EOL;
				$this->_captureParams['amount'] = sprintf('%0.2f', $this->_captureParams['amount'] / 100);
				$this->_captureParams['invoice_account_holder'] = !empty($this->_captureParams['invoice_account_holder']) ? $this->_captureParams['invoice_account_holder'] : 'Novalnet AG';
				$message .= $gatewayClass->prepareInvoiceComments($this->_captureParams);
				DB::query(
				sprintf(
                    "UPDATE SilvercartOrder SET PaymentReferenceMessage= '".$message."'  WHERE OrderNumber = '%s'",
                    $nntransHistory->OrderNumber
				));	
				$this->sendNotificationMail(['order_no' => $nntransHistory->OrderNumber]);			
			}    
			
            $this->insertCallbackOrder($callbacklogParams);
            $this->setCallbackComments(['comments' => $callbackComments, 'order_no' => $nntransHistory->OrderNumber]);
            // Send notification mail to Merchant
            $this->sendNotifyMail(['comments' => $callbackComments, 'order_no' => $nntransHistory->OrderNumber]);
            return $this->debugMessage($callbackComments, $nntransHistory->OrderNumber);
        } else {
            return $this->debugMessage(
                'Novalnet callback received. Payment type ( ' . $this->_captureParams['payment_type'] . ' ) is
                not applicable for this process!',
                $nntransHistory->OrderNumber
            );
        }
    }

    /**
     * Level 1 payment process
     *
     * @param object $nntransHistory
     * @return string
     */
    protected function chargebackPayments($nntransHistory)
    {
		$gatewayClass = new NovalnetGateway();
        $formatedAmount = $gatewayClass->formatedAmount($this->_captureParams, true);
        		
          //Level 1 payments - Type of Chargebacks
        $chargebackComments = PHP_EOL.'Chargeback executed successfully for the TID: ' . $this->_captureParams['tid_payment'] . ' amount ' . $formatedAmount . ' on ' . date('Y-m-d H:i:s') . '. The subsequent TID: ' . $this->_captureParams['tid'];

        $bookbackComments = PHP_EOL.'Refund/Bookback executed successfully for the TID: '. $this->_captureParams['tid_payment']. ' amount ' . $formatedAmount . '. The subsequent TID: ' . $this->_captureParams['tid'];

        $callbackComments = (in_array(
            $this->_captureParams['payment_type'],
            ['PAYPAL_BOOKBACK',
                'CREDITCARD_BOOKBACK',
                'PRZELEWY24_REFUND',
                'GUARANTEED_INVOICE_BOOKBACK',
                'GUARANTEED_SEPA_BOOKBACK',
                'CASHPAYMENT_REFUND',
                'REFUND_BY_BANK_TRANSFER_EU']
        ))
            ? $bookbackComments : $chargebackComments;
         $this->setCallbackComments(['comments' => $callbackComments, 'order_no' => $nntransHistory->OrderNumber]);

         $this->sendNotifyMail(['comments' => $callbackComments, 'order_no' => $nntransHistory->OrderNumber]);

        return $this->debugMessage($callbackComments, $nntransHistory->OrderNumber);
    }

    /**
     * Initial level Payment
     *
     * @param object $nntransHistory
     * @param array $callbacklogParams
     * @return string
     */
    protected function handleInitialLevelPaypal($nntransHistory, $callbacklogParams)
    {
        if ($this->_captureParams['tid_status'] == 100 && in_array($nntransHistory->GatewayStatus, [85, 90])) {
            $callbackComments = 'Novalnet Callback Script executed successfully for the TID: ' . $this->_captureParams['tid'] . ' with amount ' . sprintf('%0.2f', $this->_captureParams['amount'] / 100) . ' on ' . date('Y-m-d H:i:s');
            $this->setPaymentStatus($nntransHistory, $callbacklogParams['completion_status']);
            $this->setCallbackComments(['comments' => $callbackComments, 'order_no' => $nntransHistory->OrderNumber]);
            $this->sendNotifyMail(['comments' => $callbackComments, 'order_no' => $nntransHistory->OrderNumber]);
            return $this->debugMessage($callbackComments, $nntransHistory->OrderNumber);
        } else {
            return $this->debugMessage('Novalnet callback received. Order already paid.', $nntransHistory->OrderNumber);
        }
    }

    /**
     * Set callback comments
     *
     * @param string $comments
     * @return null
     */
    protected function setCallbackComments($data)
    {
        DB::query(
            sprintf(
                    "UPDATE SilvercartOrder SET PaymentReferenceMessage=CONCAT(IF(PaymentReferenceMessage IS NULL, '', PaymentReferenceMessage), '".$data['comments']."' ) WHERE OrderNumber = '%s'",
                    $data['order_no']
            )
         );
		 $nnOrder = NovalnetTransaction::get()->filter('OrderNumber', $data['order_no'])->first();
         if ($nnOrder instanceof NovalnetTransaction
                && $nnOrder->exists()
            ) {
                $nnOrder->updateOrder(
                    $this->_captureParams,
                   false
                );
		 }
    }
    
    /**
     * Set transaction comments
     *
     * @return string
     */
    protected function getTransactionDetails()
	{
		$message =  '';
		$message .=  $this->fieldLabel('TransactionDetails').PHP_EOL;
        $message .=  $this->fieldLabel('TransactionID'). $this->_captureParams['tid']. PHP_EOL;
		$message .=  ($this->_captureParams['test_mode'] == 1) ? $this->fieldLabel('TestMode') : ''.PHP_EOL;
		return $message;
	}

    /**
     * Handle TRANSACTION_CANCELLATION payment
     *
     * @param object $nntransHistory
     * @return boolean
     *
     */
    protected function transactionCancellation($nntransHistory)
    {
         if ($this->_captureParams['payment_type'] == 'TRANSACTION_CANCELLATION') {
             $transactionCancelComments = PHP_EOL.'The transaction has been cancelled on '.date('Y-m-d H:i:s');             
             $this->setCallbackComments(['comments' => $transactionCancelComments, 'order_no' => $nntransHistory->OrderNumber]);
             $this->sendNotifyMail(['comments' => $transactionCancelComments, 'order_no' => $nntransHistory->OrderNumber]);
             $onholdCancelStatus = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->OnholdOrderCancelStatus;
             $this->setPaymentStatus($nntransHistory, $onholdCancelStatus);
             $this->debugMessage($transactionCancelComments);
            return true;
        }
    }

     /**
     * Display the error message
     *
     * @param string $errorMsg
     * @param string $orderNo
     * @return null
     */
    protected function debugMessage($errorMsg, $orderNo = null)
    {
        if ($orderNo) {
            $errorMsg = 'message=' . $errorMsg . '&ordernumber=' . $orderNo;
        } else {
            $errorMsg = 'message=' . $errorMsg;
        }
        echo $errorMsg;
        return;
    }

     /**
     * Insert callback details
     *
     * @param int $requestData
     * @param array $requestData array with order data
     *
     * @return void
     *
     */
    protected function insertCallbackOrder($requestData)
    {
		$this->OrderNumber = $requestData['order_no'];
        $this->Amount  = $requestData['callback_amount'];
        $this->CallbackTId  = $requestData['callback_tid'];
        $this->OrgTId  = $requestData['org_tid'];
        $this->PaymentType   = $requestData['payment_type'];        
        $this->write();
    }

     /**
     * Set Payment status
     *
     * @param object $nntransHistory
     * @param int $status
     *
     * @return void
     *
     */
    protected function setPaymentStatus($nntransHistory, $status)
    {
        $order = Order::get()->byID($nntransHistory->OrderId);
            if ($order instanceof Order
                && $order->exists()
            ) {
                $order->setPaymentStatus(PaymentStatus::get()->byID($status));
           }
    }

     /**
     * Critical message
     *
     * @return void
     *
     */
    protected function NotificationMessage()
    {
        $sShopName = 'Notification message';
        $sSubject  = $this->fieldLabel('CriticalErrorMessage1').$sShopName. $this->fieldLabel('CriticalErrorMessage2') . $this->_captureParams['shop_tid'];
        $sMessage  = $this->fieldLabel('CriticalMessageSubject');
        $sMessage .= $this->fieldLabel('MerchantId') . $this->_captureParams['vendor_id'] . PHP_EOL;
        $sMessage .= $this->fieldLabel('ProjectId') . $this->_captureParams['product_id'] . PHP_EOL;
        $sMessage .= 'TID: ' . $this->_captureParams['shop_tid'] . PHP_EOL;
        $sMessage .= $this->fieldLabel('TidStatus') . $this->_captureParams['tid_status'] . PHP_EOL;
        $sMessage .= $this->fieldLabel('OrderNo') . $this->_captureParams['order_no'] . PHP_EOL;
        $sMessage .= $this->fieldLabel('PaymentType') . $this->_captureParams['payment_type'] . PHP_EOL;
        $sMessage .= $this->fieldLabel('Email') . $this->_captureParams['email'] . PHP_EOL;
        $sMessage .= $this->fieldLabel('Regards');
        return [$sSubject, $sMessage];
    }

    /**
     * Send Notification mail
     *
     * @parama array $data
     * @return void
     *
     */
    protected function sendNotifyMail($data)
    {
        $mailSubject = 'Novalnet Callback script notification - Order No : ' . $data['order_no'];
        $recipient = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->SendTo;        
        $content = $data['comments'];
        if (Email::is_valid_address($recipient)) {
            ShopEmail::send_email($recipient, $mailSubject, $content);
        } else {
            return 'Mail not sent';
        }
    }
    
    /**
     * Send payment notification mail
     *     
     * @param string $data     
     *
     */
    protected function sendNotificationMail($data)
    {
        $config  = Config::getConfig();      
        $customerEmail = Order::get()->filter('OrderNumber', $data['order_no'])->first()->CustomersEmail;

        $firstName = Member::get()->filter('Email', $customerEmail)->first()->FirstName;
        $lastName = Member::get()->filter('Email', $customerEmail)->first()->Surname;
        $gatewayClass = new NovalnetGateway();
		$break = '<br>';
		$message =  '';
		$message .= $this->fieldLabel('TransactionDetails').$break;
        $message .= $this->fieldLabel('TransactionID'). $this->_captureParams['tid']. $break;
		$message .= ($this->_captureParams['test_mode'] == 1) ? $this->fieldLabel('TestMode') : ''.$break;	
		$message .= $break.$this->fieldLabel('GuaranteePayment').$break;
		$message .= $gatewayClass->prepareInvoiceComments($this->_captureParams, $break);	
		
        $subject = $this->fieldLabel('OrderConfirmation') . $this->_captureParams['order_no'] . $this->fieldLabel('OrderConfirmation1')  .$config->ShopName. $this->fieldLabel('OrderConfirmation2') ;
        $emailContent    = '<body style="background:#F6F6F6; font-family:Verdana, Arial, Helvetica, sans-serif; font-size:14px; margin:0; padding:0;">
                                    <div style="width:55%;height:auto;margin: 0 auto;background:rgb(247, 247, 247);border: 2px solid rgb(223, 216, 216);border-radius: 5px;box-shadow: 1px 7px 10px -2px #ccc;">
                                        <div style="min-height: 300px;padding:20px;">
                                            <table cellspacing="0" cellpadding="0" border="0" width="100%">

                                                <tr><b>Dear Mr./Ms./Mrs.</b> '.$firstName.' '.$lastName.' </tr></br></br>

                                                <tr>'.$this->fieldLabel('OrderConfirmation3').'</tr></br></br>
                                                <tr>'. $this->fieldLabel('PaymentInformation') .'</br>
                                                '.$message.'
                                                </tr></br>

                                            </table>
                                        </div>
                                        <div style="width:100%;height:20px;background:#00669D;"></div>
                                    </div>
                                </body>';
           ShopEmail::send_email($customerEmail, $subject, $emailContent);		
	}
}
