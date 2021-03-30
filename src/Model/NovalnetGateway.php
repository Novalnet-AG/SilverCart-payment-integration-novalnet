<?php
namespace SilverCart\NovalnetGateway\Model;

use SilverCart\Dev\Tools;
use SilverCart\Admin\Forms\GridField\GridFieldConfig_ExclusiveRelationEditor;
use SilverCart\Forms\FormFields\TextField;
use SilverCart\Model\Order\Order;
use SilverCart\Model\ShopEmail;
use SilverCart\Model\Payment\PaymentMethod;
use SilverCart\Model\Payment\PaymentStatus;
use SilverCart\NovalnetGateway\Model\NovalnetGatewayTranslation;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\Tab;
use SilverStripe\ORM\FieldType\DBMoney;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\DB;
use SilverStripe\Control\Cookie;

/**
 * Novalnet payment modul
 *
 * This module is used for real time processing
 * of Novalnet transaction of customers.
 *
 * This free contribution made by request
 * If you have found this script useful a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated
 *
 * @package Novalnet
 * @author Novalnet AG
 * @copyright Copyright by Novalnet
 * @license https://novalnet.de/payment-plugins/kostenlos/lizenz
 *
 */
class NovalnetGateway extends PaymentMethod
{
    const SESSION_KEY = 'Silvercart.NovalnetGateway';
    const BEFORE_PAYMENT_PROVIDER_IS_PROCESSED_SESSION_KEY = self::SESSION_KEY . '.ProcessedBeforePaymentProvider';
    const AFTER_PAYMENT_PROVIDER_IS_PROCESSED_SESSION_KEY = self::SESSION_KEY . '.ProcessedAfterPaymentProvider';
    /**
     * A list of possible payment channels.
     *
     * @var array
     */
    private static $possible_payment_channels = [
        'novalnetglobalconfiguration'=> 'Novalnet Global Configuration',
        'novalnetcreditcard'        => 'Credit/Debit Cards',
        'novalnetsepa'              => 'Direct Debit SEPA',
        'novalnetinvoice'           => 'Invoice',
        'novalnetprepayment'        => 'Prepayment',
        'novalnetcashpayment'       => 'Barzahlen/viacash',
        'novalnetsofort'            => 'Sofort',
        'novalnetpaypal'            => 'PayPal',
        'novalneteps'               => 'eps',
        'novalnetideal'             => 'iDEAL',
        'novalnetgiropay'           => 'giropay',
        'novalnetprzelewy24'        => 'Prezelewyz24',
    ];

    /**
     * classes attributes
     *
     * @var array
     */
    private static $db = [
        'PaymentChannel'  => 'Enum("novalnetglobalconfiguration,novalnetcreditcard,novalnetsepa,novalnetinvoice,novalnetprepayment,novalnetcashpayment,novalnetsofort,novalnetpaypal, novalneteps,novalnetideal,novalnetgiropay,novalnetprzelewy24", "novalnetglobalconfiguration")',
        'VendorId'                      => 'Int',
        'AuthCode'                      => 'Text',
        'ProductId'                     => 'Int',
        'TariffId'                      => 'Int',
        'AccessKey'                     => 'Text',
        'ManualTestingVendorScript'     => 'Int',
        'SendTo'                        => 'Text',
        'OnholdOrderStatus'             => 'Int',
        'OnholdOrderCancelStatus'       => 'Int',
        'Cc3dEnforce'                   => 'Int',
        'PaymentAction'                 => 'Enum("capture,authorize","capture")',
        'OnholdMinOrderAmount'          => 'Int',
        'InvoiceDueDate'                => 'Int',
        'EnableGuarantee'               => 'Int',
        'GuaranteeMinOrderAmount'       => 'Int',
        'PaymentGuaranteeForce'         => 'Int',
        'GuaranteePendingStatus'        => 'Int',
        'SepaDueDate'                   => 'Int',
        'PrepaymentDueDate'             => 'Int',
        'CashPaymentDuration'           => 'Int',
        'OrderCompletionStatus'         => 'Int',
        'CallbackStatus'                => 'Int',
        'PendingStatus'                 => 'Int',
    ];

    /**
     * 1:n relationships.
     *
     * @var array
     */
    private static $has_many = [
        'NovalnetGatewayTranslations' => NovalnetGatewayTranslation::class,
    ];

    /**
     * DB table name
     *
     * @var string
     */
    private static $table_name = 'SilvercartPaymentNovalnetGateway';

    /**
     * contains module name for display in the admin backend
     *
     * @var string
     */
    protected $moduleName = 'NovalnetGateway';

    /** @var array */
    private $nnSecuredParams = ['auth_code', 'product', 'tariff', 'amount', 'test_mode'];

    /** @var array */
    public $redirectPayments = ['novalnetsofort', 'novalnetideal', 'novalnetpaypal', 'novalneteps', 'novalnetgiropay', 'novalnetprzelewy24'];

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
        $this->beforeUpdateFieldLabels(function(&$labels) {
            $labels = array_merge(
                    $labels,
                    Tools::field_labels_for(self::class),
                    [
                        'NovalnetGatewayTranslations' => NovalnetGatewayTranslation::singleton()->plural_name(),
                        'PaymentChannel'            => _t(self::class . '.PAYMENT_CHANNEL', 'Payment Channel'),
                        'InfoMailSubject'           => _t(self::class . '.InfoMailSubject', 'payment information regarding your order'),
                        'VendorId'                  => _t(self::class . '.VendorId', 'Merchant ID'),
                        'VendorIdDesc'              => _t(self::class . '.VendorIdDesc', 'Enter Novalnet merchant ID'),
                        'AuthCode'                  => _t(self::class . '.AuthCode', 'Authentication Code'),
                        'AuthCodeDesc'              => _t(self::class . '.AuthCodeDesc', 'Enter Novalnet authentication code'),
                        'ProductId'                 => _t(self::class . '.ProductId', 'Project ID'),
                        'ProductIdDesc'             => _t(self::class . '.ProductIdDesc', 'Enter Novalnet project ID'),
                        'TariffId'                  => _t(self::class . '.TariffId', 'Tariff ID'),
                        'TariffIdDesc'              => _t(self::class . '.TariffIdDesc', 'Enter Tariff ID to match the preferred tariff plan you created at the Novalnet Merchant Administration portal for this project. Refer Novalnet Payment Gateway Installation Guide for further details'),
                        'AccessKey'                 => _t(self::class . '.AccessKey', 'Payment access key'),
                        'AccessKeyDesc'             => _t(self::class . '.AccessKeyDesc', 'Enter the Novalnet payment access key'),
                        'ManualTestingVendorScript' => _t(self::class . '.ManualTestingVendorScript', 'Allow manual testing of the Notification / Webhook URL'),
                        'ManualTestingVendorScriptDesc' => _t(self::class . '.ManualTestingVendorScriptDesc', 'Enable this to test the Novalnet Notification / Webhook URL manually. Disable this before setting your shop live to block unauthorized calls from external parties'),
                        'SendTo'                    => _t(self::class . '.SendTo', 'Send e-mail to'),
                        'SendToDesc'                => _t(self::class . '.SendToDesc', 'Notification / Webhook URL execution messages will be sent to this e-mail'),
                        'VendorScriptConfiguration' => _t(self::class . '.VendorScriptConfiguration', 'Notification / Webhook URL Setup'),
                        'VencorConfiguration'       => _t(self::class . '.VencorConfiguration', 'Novalnet Global Configuration'),
                        'OnholdStatusManagement'    => _t(self::class . '.OnholdStatusManagement', 'Order status management for on-hold transactions'),
                        'OnholdOrderStatus'         => _t(self::class . '.OnholdOrderStatus', 'Onhold order status'),
                        'OnholdOrderStatusDesc'     => _t(self::class . '.OnholdOrderStatusDesc', 'Status to be used for on-hold orders until the transaction is confirmed or cancelled'),
                        'OnholdOrderCancelStatus'   => _t(self::class . '.OnholdOrderCancelStatus', 'Order cancellation status'),
                        'OnholdOrderCancelStatusDesc'   => _t(self::class . '.OnholdOrderCancelStatusDesc', 'Status to be used when order is cancelled or fully refunded'),
                        'OrderConfirmationSubmitButtonTitle'    => _t(self::class . '.OrderConfirmationSubmitButtonTitle', 'Pay with Novalnet (over 100 payment methods worldwide)'),'novalnetcreditcardConfiguration'   => _t(self::class . '.novalnetcreditcardConfiguration', 'Credit/Debit Cards Configuration'),
                        'Creditcard3dEnforce'                          => _t(self::class . '.Creditcard3dEnforce', 'Enforce 3D secure payment outside EU'),
                        'Creditcard3dEnforceDesc'                      => _t(self::class . '.Creditcard3dEnforceDesc', 'By enabling this option, all payments from cards issued outside the EU will be authenticated via 3DS 2.0 SCA'),
                        'novalnetsepaConfiguration'   => _t(self::class . '.novalnetsepaConfiguration', 'Direct Debit SEPA Configuration'),
                        'SepaDueDate'                      => _t(self::class . '.SepaDueDate', 'Payment due date (in days)'),
                        'SepaDueDateDesc'                  => _t(self::class . '.SepaDueDateDesc', 'Number of days after which the payment is debited (must be between 2 and 14 days)'),
                        'EnableGuarantee'                  => _t(self::class . '.EnableGuarantee', 'Enable payment guarantee'),
                        'EnableGuaranteeDesc'              => _t(self::class . '.EnableGuaranteeDesc', 'Payment guarantee requirements:<br>
                                                                        Allowed countries: DE, AT, CH<br>
                                                                        Allowed currency: EUR<br>
                                                                        Minimum order amount: 9,99 EUR or more<br>
                                                                        Age limit: 18 years or more<br>
                                                                        The billing address must be the same as shipping address<br>                                                                    '),
                        'GuaranteeMinOrderAmount'  => _t(self::class . '.GuaranteeMinOrderAmount', 'Minimum order amount for payment guarantee'),
                        'GuaranteeMinOrderAmountDesc' => _t(self::class . '.GuaranteeMinOrderAmountDesc', 'Enter the minimum amount (in cents) for the transaction to be processed with payment guarantee. For example, enter 100 which is equal to 1,00. By default, the amount will be 9,99 EUR. '),
                        'PaymentGuaranteeForce'                     => _t(self::class . '.PaymentGuaranteeForce', 'Force non-guarantee payment'),
                        'PaymentGuaranteeForceDesc'                 => _t(self::class . '.PaymentGuaranteeForceDesc', 'Even if payment guarantee is enabled, payments will still be processed as non-guarantee payment if payment guarantee requirements are not met. Review the requirements under "Enable Payment Guarantee" in the installation guide.'),
                        'novalnetinvoiceConfiguration'   => _t(self::class . '.novalnetinvoiceConfiguration', 'Invoice Configuration'),
                        'InvoicePaymentDueDate'               => _t(self::class .'InvoicePaymentDueDate','Payment due date (in days)'),
                        'InvoicePaymentDueDateDesc'           => _t(self::class .'InvoicePaymentDueDateDesc','Number of days given to the buyer to transfer the amount to Novalnet (must be greater than 7 days). In case this field is empty, 14 days will be set as due date by default'),
                        'GuaranteePaymentPendingStatus' => _t(self::class .'GuaranteePaymentPendingStatus','Payment pending order status'),
                        'GuaranteePaymentPendingStatusDesc' => _t(self::class .'GuaranteePaymentPendingStatusDesc','Status to be used for pending transactions'),
                        'CallbackStatus'        => _t(self::class .'CallbackStatus','Callback / webhook order status'),
                        'CallbackStatusDesc'    => _t(self::class .'CallbackStatusDesc','Status to be used when callback script is executed for payment received by Novalnet'),
                        'novalnetprepaymentConfiguration'   => _t(self::class . '.novalnetprepaymentConfiguration', 'Prepayment Configuration'),
                        'PendingStatus'         => _t(self::class .'PendingStatus','Pending payment order status'),
                        'PendingStatusDesc'     => _t(self::class .'PendingStatusDesc','Status to be used for pending transactions'),
                        'OrderCompletionStatus' => _t(self::class .'OrderCompletionStatus', 'Completed order status'),
                        'OrderCompletionStatusDesc' => _t(self::class .'OrderCompletionStatusDesc', 'Status to be used for successful orders'),
                        'PrepaymentDueDate'         => _t(self::class . '.PrepaymentDueDate', 'Payment due date (in days)'),
                        'PrepaymentDueDateDesc'     => _t(self::class . '.PrepaymentDueDateDesc', 'Number of days after which the payment is debited (must be between 2 and 14 days)'),
                        'novalnetcashpaymentConfiguration'   => _t(self::class . '.novalnetcashpaymentConfiguration', 'Barzahlen/viacash Configuration'),
                        'CashPaymentSlipExpiryDate' => _t(self::class .'CashPaymentSlipExpiryDate','Slip expiry date (in days)'),
                        'CashPaymentSlipExpiryDateDesc' => _t(self::class .'CashPaymentSlipExpiryDateDesc','Number of days given to the buyer to pay at a store. In case this field is empty, 14 days will be set as slip expiry date by default.'),
                        'novalnetpaypalConfiguration'   => _t(self::class . '.novalnetpaypalConfiguration', 'PayPal Configuration'),
                        'novalnetsofortConfiguration'   => _t(self::class . '.novalnetsofortConfiguration', 'Sofort Configuration'),
                        'novalnetgiropayConfiguration'   => _t(self::class . '.novalnetgiropayConfiguration', 'giropay Configuration'),
                        'novalnetepsConfiguration'   => _t(self::class . '.novalnetepsConfiguration', 'eps Configuration'),
                        'novalnetidealConfiguration'   => _t(self::class . '.novalnetidealConfiguration', 'iDEAL Configuration'),
                        'novalnetprzelewy24Configuration'   => _t(self::class . '.novalnetprzelewy24Configuration', 'Przelewy24 Configuration'),
                        'TransactionDetails'    => _t(self::class .'TransactionDetails','Novalnet transaction details'),
                        'TransactionID'         => _t(self::class .'TransactionID','Novalnet transaction ID: '),
                        'TestMode'              => _t(self::class .'TestMode','Test order'),
                        'InvoiceComment'        => _t(self::class .'InvoiceComment','Please transfer the amount to the below mentioned account details of our payment processor Novalnet'),
                        'InvoiceDueDate'        => _t(self::class .'InvoiceDueDate','Due date: '),
                        'InvoiceAccountHolder'  => _t(self::class .'InvoiceAccountHolder','Account holder: '),
                        'InvoiceIban'           => _t(self::class .'InvoiceIban','IBAN: '),
                        'InvoiceBic'            => _t(self::class .'InvoiceBic','BIC: '),
                        'InvoiceBank'           => _t(self::class .'InvoiceBank','Bank: '),
                        'InvoiceAmount'         => _t(self::class .'InvoiceAmount','Amount: '),
                        'InvoiceMultiRefDescription' => _t(self::class .'InvoiceMultiRefDescription','Please use any one of the following references as the payment reference, as only through this way your payment is matched and assigned to the order:'),
                        'InvoicePaymentRef1'    => _t(self::class .'InvoicePaymentRef1','Payment Reference 1: '),
                        'InvoicePaymentRef2'    => _t(self::class .'InvoicePaymentRef2','Payment Reference 2: '),
                        'GuaranteeText'         => _t(self::class .'GuaranteeText','Your order is under verification and once confirmed, we will send you our bank details to where the order amount should be transferred. Please note that this may take upto 24 hours'),
                        'GuaranteeSepaText'     => _t(self::class .'GuaranteeSepaText','Your order is under verification and we will soon update you with the order status. Please note that this may take upto 24 hours'),
                        'PaymentAction'         => _t(self::class .'PaymentAction','Payment Action'),
                        'PaymentActionDesc'     => _t(self::class .'PaymentActionDesc','Choose whether or not the payment should be charged immediately. <b>Capture</b> completes the transaction by transferring the funds from buyer account to merchant account. <b>Authorize</b> verifies payment details and reserves funds to capture it later, giving time for the merchant to decide on the order'),
                        'SlipExpiryDate'        => _t(self::class .'SlipExpiryDate','Slip expiry date '),
                        'CashpaymentStore'      => _t(self::class .'CashpaymentStore','Store(s) near you'),
                        'GuaranteeComments'     => _t(self::class .'GuaranteeComments','This is processed as a guarantee payment'),
                        'GuaranteeErrorMsg'     => _t(self::class .'GuaranteeErrorMsg','The payment cannot be processed, because the basic requirements for the payment guarantee haven’t been met'),
                        'GuaranteeErrorMsgAmount'     => _t(self::class .'GuaranteeErrorMsgAmount','Minimum order amount must be '),
                        'GuguaranteeErrorMsgCountry'  => _t(self::class .'GuguaranteeErrorMsgCountry','Only Germany, Austria or Switzerland are allowed'),
                        'GuaranteeErrorMsgAddress'    => _t(self::class .'GuaranteeErrorMsgAddress','The shipping address must be the same as the billing address'),
                        'GuaranteeErrorMsgCurrency'   => _t(self::class .'GuaranteeErrorMsgCurrency','Only EUR currency allowed'),
                        'StatusCancelled'             => _t(self::class .'StatusCancelled','Cancelled'),
                        'MinimumOrderAmount'          => _t(self::class . '.MinimumOrderAmount', 'Minimum transaction amount for authorization'),
                        'MinimumOrderAmountDesc'       => _t(self::class . '.MinimumOrderAmountDesc', 'Transactions above this amount will be "authorized only" until you capture. Leave the field blank to authorize all transactions'),
                        'Capture'     => _t(self::class . '.Capture', 'Capture'),
                        'Authorize'   => _t(self::class . '.Authorize', 'Authorize'),
                        'SepaDueDateError'   => _t(self::class . '.SepaDueDateError', 'SEPA Due date is not valid'),
                        'GuaranteeMinOrderAmountError'   => _t(self::class . '.GuaranteeMinOrderAmountError', 'The minimum amount should be at least 9,99 EUR'),
                    ]
            );
        });
        return parent::fieldLabels($includerelations);
    }

    /**
     * returns CMS fields
     *
     * @return \SilverStripe\Forms\FieldList
     */
    public function getCMSFields() : FieldList
    {
        $this->beforeUpdateCMSFields(function(FieldList $fields) {
            if ($this->PaymentChannel == 'novalnetglobalconfiguration') {
                $this->getFieldsForNnGlobalConfig($fields);
                $this->getFieldsForNnVendorScriptConfig($fields);
                $this->getFieldsForNnOnholdOrderConfig($fields);
            } else {
                $this->getFieldsForNnPaymentConfiguation($fields);
            }
            $translations = GridField::create(
                    'NovalnetGatewayTranslations',
                    $this->fieldLabel('NovalnetGatewayTranslations'),
                    $this->NovalnetGatewayTranslations(),
                    GridFieldConfig_ExclusiveRelationEditor::create()
            );
            $fields->addFieldToTab('Root.Translations', $translations);

        });
        return parent::getCMSFieldsForModules();
    }
     /**
     * Adds the fields for the Novalnet global configuration
     *
     * @param FieldList $fields FieldList to add fields to
     *
     * @return void
     */
    protected function getFieldsForNnGlobalConfig($fields) : void
    {
        $tabBasicFieldList = FieldList::create();

        $filedDetails = ['VendorId' => 'VendorIdDesc', 'AuthCode' => 'AuthCodeDesc', 'ProductId' => 'ProductIdDesc', 'TariffId' => 'TariffIdDesc', 'AccessKey' => 'AccessKeyDesc'];

        foreach ( $filedDetails as $field => $desc) {
            $tabBasicFieldList->push(TextField::create($field,$this->fieldLabel($field))
            ->setDescription($this->fieldLabel($desc)));
        }

       $apiDataToggle = ToggleCompositeField::create(
                'VencorConfiguration',
                $this->fieldLabel('VencorConfiguration'),
                $tabBasicFieldList
        )->setHeadingLevel(4)->setStartClosed(true);

        $fields->addFieldToTab('Root.Basic', $apiDataToggle);
    }

    /**
     * Adds the fields for the Novalnet vendorscript configuration
     *
     * @param FieldList $fields FieldList to add fields to
     *
     * @return void
     */
    protected function getFieldsForNnVendorScriptConfig($fields) : void
    {
        $tabBasicFieldList = FieldList::create();

        $filedDetails = ['ManualTestingVendorScript' => 'ManualTestingVendorScriptDesc'];

        foreach ( $filedDetails as $field => $desc) {
            $tabBasicFieldList->push(CheckboxField::create($field, $this->fieldLabel($field))
                    ->setDescription($this->fieldLabel($desc)));
        }

        $tabBasicFieldList->push(TextField::create('SendTo',$this->fieldLabel('SendTo'))
                ->setDescription($this->fieldLabel('SendToDesc'))
        );

        $apiDataToggle = ToggleCompositeField::create(
                'VendorScriptConfiguration',
                $this->fieldLabel('VendorScriptConfiguration'),
                $tabBasicFieldList
        )->setHeadingLevel(4)->setStartClosed(true);

        $fields->addFieldToTab('Root.Basic', $apiDataToggle);
    }

    /**
     * Adds the fields for the Novalnet onhold configuration
     *
     * @param FieldList $fields FieldList to add fields to
     *
     * @return void
     */
    protected function getFieldsForNnOnholdOrderConfig($fields) : void
    {
        $paymentStatus = PaymentStatus::get();
        $tabBasicFieldList = FieldList::create();

        $filedDetails = ['OnholdOrderStatus' => 'OnholdOrderStatusDesc', 'OnholdOrderCancelStatus' => 'OnholdOrderCancelStatusDesc'];

        foreach ( $filedDetails as $field => $desc) {
            $tabBasicFieldList->push(DropdownField::create($field,  $this->fieldLabel($field),  $paymentStatus->map('ID', 'Title'), $field)
            ->setDescription($this->fieldLabel($desc)));
        }

        $fields->removeByName('PaymentStatusID');
        $fields->removeByName('maxAmountForActivation');
        $fields->removeByName('minAmountForActivation');
        $fields->removeByName('LongPaymentDescription');
        $fields->removeByName('SumModifiers');
        $apiDataToggle = ToggleCompositeField::create(
                'OnholdStatusManagement',
                $this->fieldLabel('OnholdStatusManagement'),
                $tabBasicFieldList
        )->setHeadingLevel(4)->setStartClosed(true);

        $fields->addFieldToTab('Root.Basic', $apiDataToggle);
    }
    /**
     * Adds the fields for the Novalnet payment configuration
     *
     * @param FieldList $fields FieldList to add fields to
     *
     * @return void
     */
    protected function getFieldsForNnPaymentConfiguation($fields) : void
    {
        $paymentStatus = PaymentStatus::get();

        $tabBasicFieldList = FieldList::create();

        $tabBasicFieldList->push(DropdownField::create('OrderCompletionStatus',     $this->fieldLabel('OrderCompletionStatus'),  $paymentStatus->map('ID', 'Title'), $this->OrderCompletionStatus)
        ->setDescription($this->fieldLabel('OrderCompletionStatusDesc'))
        );

        if (in_array($this->PaymentChannel, ['novalnetprepayment', 'novalnetinvoice', 'novalnetcashpayment'])) {

            $tabBasicFieldList->push(DropdownField::create('CallbackStatus',     $this->fieldLabel('CallbackStatus'),  $paymentStatus->map('ID', 'Title'), $this->CallbackStatus)
            ->setDescription($this->fieldLabel('CallbackStatusDesc')));
        }

        $paymentAction  = [
                'capture'     => $this->fieldLabel('Capture'),
                'authorize'      => $this->fieldLabel('Authorize'),
            ];

        if (in_array($this->PaymentChannel, ['novalnetinvoice', 'novalnetcreditcard', 'novalnetsepa', 'novalnetpaypal'])) {
            $tabBasicFieldList->push( DropdownField::create('PaymentAction',  $this->fieldLabel('PaymentAction'), $paymentAction, $this->PaymentAction)
            ->setDescription($this->fieldLabel('PaymentActionDesc'))
            );
            $tabBasicFieldList->push(TextField::create('OnholdMinOrderAmount',$this->fieldLabel('MinimumOrderAmount'))
            ->setDescription($this->fieldLabel('MinimumOrderAmountDesc'))
            );
        }

        if (in_array($this->PaymentChannel, ['novalnetpaypal', 'novalnetprzelewy24'])) {
            $tabBasicFieldList->push(DropdownField::create('PendingStatus',     $this->fieldLabel('PendingStatus'),  $paymentStatus->map('ID', 'Title'), $this->PendingStatus)
            ->setDescription($this->fieldLabel('PendingStatusDesc'))
            );
        }

        if ($this->PaymentChannel == 'novalnetinvoice') {
            $tabBasicFieldList->push(TextField::create('InvoiceDueDate',$this->fieldLabel('InvoicePaymentDueDate'))
                    ->setDescription($this->fieldLabel('InvoicePaymentDueDateDesc'))
            );
        }

        if ($this->PaymentChannel == 'novalnetcreditcard') {
            $tabBasicFieldList->push(CheckboxField::create('Cc3dEnforce', $this->owner->fieldLabel('Creditcard3dEnforce'))
                        ->setDescription($this->fieldLabel('Creditcard3dEnforceDesc'))
            );
        }

        if ($this->PaymentChannel == 'novalnetsepa') {
            $tabBasicFieldList->push(TextField::create('SepaDueDate',$this->fieldLabel('SepaDueDate'))
                    ->setDescription($this->fieldLabel('SepaDueDateDesc'))
            );
        }

        if ($this->PaymentChannel == 'novalnetprepayment') {
            $tabBasicFieldList->push(TextField::create('PrepaymentDueDate',$this->fieldLabel('PrepaymentDueDate'))
                    ->setDescription($this->fieldLabel('PrepaymentDueDateDesc'))
            );
        }

        if (in_array($this->PaymentChannel, ['novalnetinvoice', 'novalnetsepa'])) {
            $tabBasicFieldList->push(CheckboxField::create('EnableGuarantee',$this->fieldLabel('EnableGuarantee'))
                    ->setDescription($this->fieldLabel('EnableGuaranteeDesc'))
            );
            $tabBasicFieldList->push(TextField::create('GuaranteeMinOrderAmount',$this->fieldLabel('GuaranteeMinOrderAmount'))
                    ->setDescription($this->fieldLabel('GuaranteeMinOrderAmountDesc'))
            );
            $tabBasicFieldList->push(CheckboxField::create('PaymentGuaranteeForce',$this->fieldLabel('PaymentGuaranteeForce'))
                    ->setDescription($this->fieldLabel('PaymentGuaranteeForceDesc'))
            );

            $tabBasicFieldList->push(DropdownField::create('GuaranteePendingStatus',     $this->fieldLabel('GuaranteePaymentPendingStatus'),     $paymentStatus->map('ID', 'Title'), $this->GuaranteePendingStatus)
                    ->setDescription($this->fieldLabel('GuaranteePaymentPendingStatusDesc'))
            );
        }

        if ($this->PaymentChannel == 'novalnetcashpayment') {
            $tabBasicFieldList->push(TextField::create('CashPaymentDuration',$this->fieldLabel('CashPaymentSlipExpiryDate'))
                ->setDescription($this->fieldLabel('CashPaymentSlipExpiryDateDesc'))
            );
        }

        $paymentStatusDataToggle = ToggleCompositeField::create(
                'PaymentStatus',
                $this->fieldLabel($this->PaymentChannel. 'Configuration'),
                $tabBasicFieldList
        )->setHeadingLevel(4)->setStartClosed(true);
        $fields->removeByName('PaymentStatusID');

        $fields->addFieldToTab('Root.Basic', $paymentStatusDataToggle);
    }

    /**
     * Creates and relates required order status and logo images.
     *
     * @return void
     *
     * @author Novalnet AG
     */
    public function requireDefaultRecords() : void
    {
        parent::requireDefaultRecords();
        $novalnetPayments = NovalnetGateway::get()->filter('OrderCompletionStatus', 0);
        if ($novalnetPayments->exists()) {
            foreach ($novalnetPayments as $novalnetPayment) {
                $novalnetPayment->OnholdOrderStatus = PaymentStatus::get()->filter('Code', 'open')->first()->ID;
                $novalnetPayment->OrderCompletionStatus = PaymentStatus::get()->filter('Code', 'open')->first()->ID;
                $novalnetPayment->GuaranteePendingStatus = PaymentStatus::get()->filter('Code', 'open')->first()->ID;
                $novalnetPayment->PendingStatus = PaymentStatus::get()->filter('Code', 'open')->first()->ID;
                $novalnetPayment->CallbackStatus = PaymentStatus::get()->filter('Code', 'paid')->first()->ID;
                $novalnetPayment->OnholdOrderCancelStatus = PaymentStatus::get()->filter('Code', 'canceled')->first()->ID;
                $novalnetPayment->write();
            }
        }

        $infoMail = ShopEmail::get()->filter('TemplateName', 'PaymentNovalnetTransactionInfo')->first();
        if (is_null($infoMail)
         || !$infoMail->exists()
        ) {
            $infoMail = ShopEmail::create();
            $infoMail->TemplateName = 'PaymentNovalnetTransactionInfo';
            $infoMail->Subject      = $this->fieldLabel('InfoMailSubject');
            $infoMail->write();
        }

    }

     /**
     * Called on before write.
     *
     * @return void
     */
    public function onBeforeWrite() : void
    {
        parent::onBeforeWrite();
    }

    /**
     * Set the title for the submit button on the order confirmation step.
     *
     * @return string
     */
    public function getOrderConfirmationSubmitButtonTitle() : string
    {
        return $this->fieldLabel('OrderConfirmationSubmitButtonTitle');
    }

    /**
     * Returns whether the checkout is ready to call self::processBeforePaymentProvider().
     *
     * @param array $checkoutData Checkout data
     *
     * @return bool
     *
     * @author Novalnet AG
     */
    public function canProcessBeforePaymentProvider(array $checkoutData) : bool
    {
        return !$this->beforePaymentProviderIsProcessed();
    }

    /**
     * Returns whether the checkout is ready to call self::processAfterPaymentProvider().
     *
     * @param array $checkoutData Checkout data
     *
     * @return bool
     *
     * @author Novalnet AG
     */
    public function canProcessAfterPaymentProvider(array $checkoutData) : bool
    {
        $request = $this->getController()->getRequest();
        $getResponseData = $request->postVars();
        $processed     = false;
        if ((isset($getResponseData['tid']) && !is_null($getResponseData['tid'])) || (isset($getResponseData['status']) && !is_null($getResponseData['status']))) {
            $processed = true;
        }
        return $processed && $this->beforePaymentProviderIsProcessed() && !$this->afterPaymentProviderIsProcessed();
    }

    /**
     * Is called by default checkout right before placing an order.
     * If this returns false, the order won't be placed and the checkout won't be finalized.
     *
     * @param array $checkoutData Checkout data
     *
     * @return bool
     *
     * @author Novalnet AG
     */
    public function canPlaceOrder(array $checkoutData) : bool
    {
        return $this->beforePaymentProviderIsProcessed() && $this->afterPaymentProviderIsProcessed();
    }

    /**
     * Returns whether the checkout is ready to call self::processAfterOrder().
     *
     * @param \SilverCart\Model\Order\Order $order   Order
     * @param array $checkoutData Checkout data
     *
     * @return bool
     *
     * @author Novalnet AG
     */
    public function canProcessAfterOrder(Order $order, array $checkoutData) : bool
    {
        return $this->canPlaceOrder($checkoutData) && $order instanceof Order;
    }

    /**
     * Is called by default checkout right before placing an order.
     *
     * @param array $checkoutData Checkout data
     *
     * @return void
     *
     * @author Novalnet AG
     */
     protected function processBeforePaymentProvider(array $checkoutData) : void
     {
        if (in_array($this->PaymentChannel, ['novalnetinvoice','novalnetsepa'])) {
            if ($this->PaymentChannel == 'novalnetsepa') {
                if (!empty($this->SepaDueDate) && (!is_numeric($this->SepaDueDate) || $this->SepaDueDate < 2 || $this->SepaDueDate > 14)) {
                    $this->errorOccured = true;
                    $this->addError($this->fieldLabel('SepaDueDateError'));
                    return;
                }
             }

            $condition = $this->EnableGuarantee && !$this->PaymentGuaranteeForce;
            if ($condition) {
                if ($this->GuaranteeMinOrderAmount != '' && (!is_numeric($this->GuaranteeMinOrderAmount) || $this->GuaranteeMinOrderAmount < 999)) {
                    $this->errorOccured = true;
                    $this->addError($this->fieldLabel('GuaranteeMinOrderAmountError'));
                    return;
                }
                $isGuaranteePayment = $this->checkGuaranteePayment();
                if ($isGuaranteePayment !== true) {
                    $this->errorOccured = true;
                    $this->addError($isGuaranteePayment);
                    return;
                }
            }
        }

        $parameters    = $this->getBasicParameters($checkoutData);

        $response = $this->sendServerRequest($parameters, "https://paygate.novalnet.de/paygate.jsp");

        if (isset($response['status']) && $response['status'] != 100) {
            $this->errorOccured = true;
            $errorMessage = ($response['status_desc']) ? $response['status_desc'] : $response['status_text'];
            $this->addError($errorMessage);
            return;
        } else {
            $this->getController()->redirect($response['url']);
            Tools::Session()->set(self::BEFORE_PAYMENT_PROVIDER_IS_PROCESSED_SESSION_KEY, true);
            Tools::saveSession();
        }
    }

    /**
     * Is called right after returning to the checkout after being redirected to Novalnet.
     * During this step which will be saved to the session.
     *
     * @param array $checkoutData Checkout data
     *
     * @return void
     *
     * @author Novalnet AG
     */
    public function processAfterPaymentProvider(array $checkoutData) : void
    {
        $request = $this->getController()->getRequest();
        $getResponseData = $request->postVars();
          if (isset($getResponseData['status']) && $getResponseData['status'] != 100) {
            $this->errorOccured = true;
            $errorMessage = ($getResponseData['status_desc']) ? $getResponseData['status_desc'] : $getResponseData['status_text'];
            $this->addError($errorMessage);
            return;
        } else {
            if (($getResponseData['status'] != '100')|| (isset($getResponseData['tid']) && is_null($getResponseData['tid'])) || (isset($getResponseData['status']) && is_null($getResponseData['status']))) {
                $this->errorOccured = true;
                $errorMessage = ($getResponseData['status_desc']) ? $getResponseData['status_desc'] : $getResponseData['status_text'];
                $this->addError($errorMessage);
                return;
            } else {
                Tools::Session()->set(self::AFTER_PAYMENT_PROVIDER_IS_PROCESSED_SESSION_KEY, true);
                Tools::saveSession();
            }
        }
    }

    /**
     * Is called by default checkout right after placing an order.
     *
     * @param \SilverCart\Model\Order\Order $order  Order
     * @param array $checkoutData Checkout data
     *
     * @return void
     *
     * @author Novalnet AG
     */
    protected function processAfterOrder(Order $order, array $checkoutData) : void
    {
        $request = $this->getController()->getRequest();
        $response = $request->postVars();

        if (in_array($this->PaymentChannel, $this->redirectPayments) || !empty($response['cc_3d'])) {
            $response = $this->decodeData($response);
        }

        $transaction = NovalnetTransaction::create();
        $response['orderId']     = $order->ID;
        $response['orderNumber'] = $order->OrderNumber;
        $response['paymentChannel'] = $this->PaymentChannel;
        $transaction->updateOrder($response);
        $this->updatePaymentDetails($response, $order);
        $this->updatePostBackCall($response, $order);
        $this->clearSession();
    }

    /**
     * Resets the payment progress hold in session.
     *
     * @return void
     *
     * @author Novalnet AG
     */
    public function resetProgress() : void
    {
        Tools::Session()->set(self::BEFORE_PAYMENT_PROVIDER_IS_PROCESSED_SESSION_KEY, false);
        Tools::Session()->set(self::AFTER_PAYMENT_PROVIDER_IS_PROCESSED_SESSION_KEY, false);
        Tools::saveSession();
    }

    /**
     * Clears the Novalnet session data.
     *
     * @return void
     *
     * @author Novalnet AG
     */
    public function clearSession() : void
    {
        Tools::Session()->set(self::SESSION_KEY, null);
        Tools::saveSession();
    }

    /**
     * Returns whether self::processBeforePaymentProvider() is already processed.
     *
     * @return bool
     */
    protected function beforePaymentProviderIsProcessed() : bool
    {
        return (bool) Tools::Session()->get(self::BEFORE_PAYMENT_PROVIDER_IS_PROCESSED_SESSION_KEY);
    }

    /**
     * Returns whether self::processAfterPaymentProvider() is already processed.
     *
     * @return bool
     */
    protected function afterPaymentProviderIsProcessed() : bool
    {
        return (bool) Tools::Session()->get(self::AFTER_PAYMENT_PROVIDER_IS_PROCESSED_SESSION_KEY);
    }

    /**
     * Update the payment details
     *
     * @param array $requestData
     * @param object $order
     *
     * @author Novalnet AG
     */
    protected function updatePaymentDetails($requestData, $order)
    {
        if ($requestData['status'] == 100) {
            if (in_array($requestData['tid_status'], [91, 99, 98, 85])) {
                $onholdOrderStatus = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->OnholdOrderStatus;
                $order->setPaymentStatus(PaymentStatus::get()->byID($onholdOrderStatus));
            } elseif($requestData['tid_status'] == 75) {
                $order->setPaymentStatus(PaymentStatus::get()->byID($this->GuaranteePendingStatus));
            } elseif (in_array($requestData['payment_type'], ['GUARANTEED_INVOICE', 'GUARANTEED_DIRECT_DEBIT_SEPA'])) {
                $status = ($requestData['tid_status'] == 100 && $requestData['payment_type'] == 'GUARANTEED_INVOICE') ? $this->CallbackStatus : $this->OrderCompletionStatus;
                $order->setPaymentStatus(PaymentStatus::get()->byID($status));
            } elseif (in_array($requestData['tid_status'], [86, 90])) {
                $order->setPaymentStatus(PaymentStatus::get()->byID($this->PendingStatus));
            } else {
                $order->setPaymentStatus(PaymentStatus::get()->byID($this->OrderCompletionStatus));
            }
        }

        $message =  '';
        $message .=  $this->fieldLabel('TransactionDetails').PHP_EOL;
        $message .=  $this->fieldLabel('TransactionID'). $requestData['tid']. PHP_EOL;
        $message .=  ($requestData['test_mode'] == 1) ? $this->fieldLabel('TestMode'): ''.PHP_EOL;

        if (in_array($requestData['key'], ['40', '41'])) {
            $message .= PHP_EOL.$this->fieldLabel('GuaranteeComments');
        }

        if (in_array($requestData['payment_type'], ['INVOICE_START', 'GUARANTEED_INVOICE']) && $requestData['tid_status'] !=75) {
            $requestData['order_no'] = $order->OrderNumber;
            $requestData['product_id'] = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->ProductId;
            $message .= $this->prepareInvoiceComments($requestData);
        }

        if ($requestData['tid_status'] == 75) {
             $message .= ($requestData['key'] == '41') ? PHP_EOL.$this->fieldLabel('GuaranteeText') : PHP_EOL.$this->fieldLabel('GuaranteeSepaText');
        }

        if (isset($requestData['key']) && $requestData['key'] =='59') {
            $message  .= $this->getBarzahlenComments($requestData);
        }

        // send email with payment information to the customer
        ShopEmail::send(
            'PaymentNovalnetTransactionInfo',
            $order->CustomersEmail,
            [
                'Order' => $order,
                'Message' => ['comments' => $message],
            ]
        );

        DB::query(
             sprintf(
                  "UPDATE SilvercartOrder SET PaymentReferenceID= '".$requestData['tid']."', PaymentReferenceMessage ='".$message."'  WHERE OrderNumber = '%s'",
                  $order->OrderNumber
                )
        );
    }

    /**
     * Prepare invoice prepayment bank details
     *
     * @param  array $requestData
     * @return string
     *
     * @author Novalnet AG
     */
    public function prepareInvoiceComments($requestData, $break = '')
    {
        $message = '';
        $break = !empty($break) ? $break : PHP_EOL;
        $message .= $break.$break.$this->fieldLabel('InvoiceComment') . $break;
        if (!empty($requestData['due_date'])) {
            $message .= $break.$this->fieldLabel('InvoiceDueDate') . date('Y-m-d', strtotime($requestData['due_date'])).  $break;
        }
        $message .= $this->fieldLabel('InvoiceAccountHolder') . $requestData['invoice_account_holder']. $break;
        $message .= $this->fieldLabel('InvoiceIban') . $requestData['invoice_iban']. $break;
        $message .= $this->fieldLabel('InvoiceBic') . $requestData['invoice_bic']. $break;
        $message .= $this->fieldLabel('InvoiceBank'). $requestData['invoice_bankname'].' '.$requestData['invoice_bankplace']. $break;
        $message .= $this->fieldLabel('InvoiceAmount') . $this->formatedAmount($requestData).$break.$break;
        $message .= $this->fieldLabel('InvoiceMultiRefDescription').$break;
        $message .= $this->fieldLabel('InvoicePaymentRef1') . 'BNR-'.$requestData['product_id'].'-'.$requestData['order_no'].$break;
        $message .= $this->fieldLabel('InvoicePaymentRef2') . $requestData['tid'];

        return $message;
    }

    /**
     * Returns the Price formatted by locale.
     *
     * @param  array $requestData
     * @return string
     *
     * @author Novalnet AG
     */
    public function formatedAmount($requestData, $callback = false)
    {
        $formatedAmount = DBMoney::create();
        if ($callback == true)
            $requestData['amount']  = sprintf('%0.2f', $requestData['amount'] / 100);

        $formatedAmount->setAmount($requestData['amount']);
        $formatedAmount->setCurrency($requestData['currency']);

        return $formatedAmount->Nice();
    }

    /**
     * Decode the basic parameters
     *
     * @param  array $response
     * @return string
     *
     * @author Novalnet AG
     */
    public function decodeData($response)
    {
        $accessKey =  NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->AccessKey;
        foreach ($this->nnSecuredParams as $key) {
            $response[$key] = openssl_decrypt(base64_decode($response[$key]),"aes-256-cbc", $accessKey,true, $response['uniqid']);
        }
        return $response;
    }

    /**
     * Generate Novalnet Gateway parameters.
     *
     * @param array $checkoutData Checkout data
     *
     * @return array
     *
     * @author Novalnet AG
     */
    protected function getBasicParameters(array $checkoutData) : array
    {
        $paygateParams = [];
        $this->getVendorParams($paygateParams);
        $this->getBillingParams($checkoutData, $paygateParams);
        $this->getCommonParams($paygateParams);
        $this->getPaymentParams($paygateParams);
        $this->getSeamlessFormParams($paygateParams);
        $accesskey = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->AccessKey;
        $this->encodeParams($paygateParams, $accesskey);
        $paygateParams['hash'] = $this->generateHash($paygateParams, $accesskey);
        return $paygateParams;
    }

    /**
     * Assign Novalnet authentication Data
     *
     * @param array $paygateParams
     * @return null
     *
     * @author Novalnet AG
     */
    public function getVendorParams(&$paygateParams)
    {
        $paygateParams['vendor']    = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->VendorId;
        $paygateParams['auth_code'] = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->AuthCode;
        $paygateParams['product']   = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->ProductId;
        $paygateParams['tariff']    = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->TariffId;$paygateParams['test_mode'] = ($this->mode == 'Dev') ? '1' : '0';
    }

     /**
     * Get end-customer billing informations
     *
     * @param array $checkoutData
     * @param array $paygateParams
     * @return null
     *
     * @author Novalnet AG
     */
    public function getBillingParams($checkoutData, &$paygateParams)
    {
        $invoiceAddress = $this->getInvoiceAddress();

        $paygateParams['gender'] = 'u';
        $paygateParams['firstname'] = $invoiceAddress->FirstName;
        $paygateParams['lastname'] = $invoiceAddress->Surname;
        $customer = Security::getCurrentUser();
        $paygateParams['email'] = $customer->Email;
        $paygateParams['customer_no'] = $customer->CustomerNumber;
        $paygateParams['street'] = $invoiceAddress->Street;
        $paygateParams['house_no'] = $invoiceAddress->StreetNumber;
        $paygateParams['country_code'] = $invoiceAddress->Country()->ISO2;
        $paygateParams['city'] = $invoiceAddress->City;
        $paygateParams['zip'] = $invoiceAddress->Postcode;
        if ($invoiceAddress->Phone)
            $paygateParams['tel'] = $invoiceAddress->Phone;

        if ($invoiceAddress->Fax)
            $paygateParams['fax'] = $invoiceAddress->Fax;

        if ($customer->Birthday)
            $paygateParams['birth_date'] = $customer->Birthday;

        if ($invoiceAddress->Company)
            $paygateParams['company'] = $invoiceAddress->Company;
    }

    /**
     * Get common params for Novalnet payment API request
     *
     * @param array $paygateParams
     * @return null
     *
     * @author Novalnet AG
     */
    public function getCommonParams(&$paygateParams)
    {
        $shoppingCart    = $this->getShoppingCart();
        $currentLocale   = Tools::current_locale();
        $parts = explode('_', $currentLocale);
        $paygateParams['amount'] = round((float) $shoppingCart->getAmountTotal()->getAmount(), 2)*100;
        $paygateParams['currency'] = $shoppingCart->getAmountTotal()->getCurrency();
        $paygateParams['lang'] = $parts[0];
        $paygateParams['system_name'] = 'silvercart';
        $paygateParams['remote_ip']   = $this->getRemoteAddress();
        $paygateParams['system_ip'] = $_SERVER['SERVER_ADDR'];
        $paygateParams['uniqid'] = $this->getRandomString();
        $paygateParams['implementation'] = 'ENC';
        $paygateParams['return_method'] = 'POST';
        $paygateParams['error_return_method'] = 'POST';
        $paygateParams['return_url'] = $this->getReturnLink();
        $paygateParams['error_return_url'] = $this->getReturnLink();
        $paygateParams['hook_url'] = $this->getNotificationLink();
        $paygateParams['input1'] = 'nn_sid';
        $paygateParams['inputval1'] = Cookie::get(session_name());
    }


    /**
     * Assign Novalnet payment data
     *
     * @param array $paygateParams
     * @return null
     *
     * @author Novalnet AG
     */
    public function getPaymentParams(&$paygateParams)
    {
        $paygateParams['key'] = $this->getPaymentId($this->PaymentChannel);
        $paygateParams['payment_type'] = $this->getPaymentType($this->PaymentChannel);

        if(in_array($this->PaymentChannel, ['novalnetcreditcard', 'novalnetsepa', 'novalnetinvoice', 'novalnetpaypal']) && $this->PaymentAction == 'authorize' && (empty($this->OnholdMinOrderAmount) || (is_numeric($this->OnholdMinOrderAmount) && $paygateParams['amount'] >= $this->OnholdMinOrderAmount))) {
            $paygateParams['on_hold'] = '1';
        }
        if ($this->PaymentChannel == 'novalnetcreditcard') {
            if (!empty($this->Cc3dEnforce))
                $paygateParams['cc_3d'] = $this->Cc3dEnforce;

         } elseif($this->PaymentChannel == 'novalnetsepa') {
                if ($this->EnableGuarantee) {
                    $guaranteePayment = $this->checkGuaranteePayment();
                    if ($guaranteePayment === true) {
                        $paygateParams['key'] = '40';
                        $paygateParams['payment_type'] = 'GUARANTEED_DIRECT_DEBIT_SEPA';
                    }
                }
                $paygateParams['sepa_due_date'] = (trim($this->SepaDueDate))
                    ? date('Y-m-d', strtotime('+' . trim($this->SepaDueDate) . ' days')) : '';

            } elseif ($this->PaymentChannel == 'novalnetinvoice') {
                $paygateParams['invoice_type'] = 'INVOICE';
                if ($this->EnableGuarantee) {
                    $guaranteePayment = $this->checkGuaranteePayment();
                    if ($guaranteePayment === true) {
                        $paygateParams['key'] = '41';
                        $paygateParams['payment_type'] = 'GUARANTEED_INVOICE';
                        unset($paygateParams['invoice_type']);
                    }
                }
                $paygateParams['due_date'] = (trim($this->InvoiceDueDate)) ? (trim($this->InvoiceDueDate)) : '';
            } elseif($this->PaymentChannel == 'novalnetprepayment') {
                $paygateParams['invoice_type'] = 'PREPAYMENT';
                $paygateParams['due_date'] = (!empty($this->PrepaymentDueDate) || $this->PrepaymentDueDate <= 28 || $this->PrepaymentDueDate >= 7)  ? $this->PrepaymentDueDate : '';
            } elseif($this->PaymentChannel == 'novalnetcashpayment') {

                $paygateParams['cp_due_date'] = (trim($this->CashPaymentDuration))
                    ? date('Y-m-d', strtotime('+' . trim($this->CashPaymentDuration) . ' days')) : '';
            }
    }

    /**
     * Check guarantee payment requirements
     *
     * @return boolean
     *
     * @author Novalnet AG
     */
    public function checkGuaranteePayment()
    {
        $shoppingCart    = $this->getShoppingCart();
        $amount = round((float) $shoppingCart->getAmountTotal()->getAmount(), 2)*100;

        $minAmount = $this->GuaranteeMinOrderAmount;

        $minAmount = ($minAmount) ? $minAmount : 999;

        $invoiceAddress = $this->getInvoiceAddress();
        $shippingAddress = $this->getShippingAddress();
        $currency = $shoppingCart->getAmountTotal()->getCurrency();

        $billingAddress = [
            $invoiceAddress->Street,
            $invoiceAddress->StreetNumber,
            $invoiceAddress->City,
            $invoiceAddress->Postcode,
            $invoiceAddress->Country()->ISO2
        ];
        $shippingAddress = [
            $shippingAddress->Street,
            $shippingAddress->StreetNumber,
            $shippingAddress->City,
            $shippingAddress->Postcode,
            $shippingAddress->Country()->ISO2
        ];

        if (in_array($invoiceAddress->Country()->ISO2, ['AT', 'CH', 'DE'])
            && ($shippingAddress === $billingAddress)
            && $amount >= $minAmount && $currency == 'EUR') {
            return true;
        } else {

            $errorMsg = $this->fieldLabel('GuaranteeErrorMsg') . PHP_EOL;
            if (!($amount >= $minAmount)) {
                $errorMsg .= $this->fieldLabel('GuaranteeErrorMsgAmount'). $minAmount. ' ' .$currency;
            }
            if (!in_array($invoiceAddress->Country()->ISO2, ['AT', 'CH', 'DE'])) {
                $errorMsg .= $this->fieldLabel('GuguaranteeErrorMsgCountry');
            }
            if ($shippingAddress !== $billingAddress) {
                $errorMsg .= $this->fieldLabel('GuaranteeErrorMsgAddress');
            }
            if ($currency != 'EUR') {
                $errorMsg .= $this->fieldLabel('GuaranteeErrorMsgCurrency');
            }

            return $errorMsg;
        }
     }

    /**
     * Get Seamless payment form customization params
     *
     * @param array $paygateParams
     * @return null
     *
     * @author Novalnet AG
     */
    public function getSeamlessFormParams(&$paygateParams)
    {
        $paygateParams['hfooter'] = '0';
        $paygateParams['skip_cfm'] = '1';
        $paygateParams['skip_suc'] = '1';
        $paygateParams['skip_sp'] = '1';
        $paygateParams['thide'] = '1';
        $paygateParams['purl'] = '1';
        $paygateParams['address_form'] = '0';
        $paygateParams['shide'] = '1';
        $paygateParams['lhide'] = '1';
        $paygateParams['chosen_only'] = '1';
    }

    /**
     * Is this payment method allowed for a total amount?
     *
     * @param int $amount Amount to be checked
     *
     * @return bool
     *
     * @author Novalnet AG
     */
    public function isAvailableForAmount($amount)
    {
        $isAvailable = parent::isAvailableForAmount($amount);

        if ($this->PaymentChannel == 'novalnetglobalconfiguration')  {
            $isAvailable = false;
        }
        $vendorId = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->VendorId;
        $authCode = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->AuthCode;
        $productId = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->ProductId;
        $tariffId = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->TariffId;
        $accessKey = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->AccessKey;

        if (empty($vendorId) || empty($authCode) || empty($productId) || empty($tariffId) || empty($accessKey)) {
            $this->Log('isAvailableForAmount','');
            $this->Log('isAvailableForAmount','Please fill in all the mandatory fields');
            $isAvailable =  false;
        }

        return $isAvailable;

    }

    /**
     * Performs CURL request
     *
     * @param array  $paymentData
     * @param string $url
     * @return mixed
     *
     * @author Novalnet AG
     */
    public function sendServerRequest($paymentData, $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($paymentData));
        $GatewayTimeout = NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->GatewayTimeout;
        // Custom CURL time-out.
        curl_setopt($ch, CURLOPT_TIMEOUT, 240);

        $response    = curl_exec($ch);
        $responseData = [];
        parse_str($response, $responseData);

        return $responseData;
    }

     /**
     * Generate unique string.
     *
     * @return string
     *
     * @author Novalnet AG
     */
    public function getRandomString()
    {
        $randomwordarray = ['8','7','6','5','4','3','2','1','9','0','9','7','6','1','2','3','4','5','6','7','8','9','0'];
        shuffle($randomwordarray);
        return substr(implode('', $randomwordarray), 0, 16);
    }

     /**
     * Encode the config parameters before transaction.
     *
     * @param array $paygateParams
     * @param string $key
     * @return null
     *
     * @author Novalnet AG
     */
    public function encodeParams(&$paygateParams, $key)
    {
        foreach ($this->nnSecuredParams as $value) {
            try {
                $paygateParams[$value] = htmlentities(base64_encode(openssl_encrypt(
                    $paygateParams[$value],
                    "aes-256-cbc",
                    $key,
                    true,
                    $paygateParams['uniqid']
                )));
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        }
    }

    /**
     * Generate the 32 digit hash code
     *
     * @param array $data
     * @param string $key
     * @return string
     *
     * @author Novalnet AG
     */
    public function generateHash($data, $key)
    {
        $str = '';
        $hashFields = [
            'auth_code',
            'product',
            'tariff',
            'amount',
            'test_mode',
            'uniqid'
        ];
        foreach ($hashFields as $value) {
            $str .= $data[$value];
        }
        return hash('sha256', $str . strrev($key));
    }

    /**
     * Send postback to Novalnet server
     *
     * @param array $request
     * @param object $order
     * @return
     *
     * @author Novalnet AG
     */
    public function updatePostBackCall($request, $order)
    {
        $params = [
         'vendor'      => NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->VendorId,
         'auth_code'   => NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->AuthCode,
         'product'     => NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->ProductId,
         'tariff'      => NovalnetGateway::get()->filter('PaymentChannel', 'novalnetglobalconfiguration')->first()->TariffId,
         'key'         => !empty($request['key']) ?  $request['key']: $request['payment_id'],
         'tid'         => $request['tid'],
         'status'      => $request['status'],
         'order_no'    => $order->OrderNumber,
        ];

        if ($params['key'] == 27){
            $params['invoice_ref'] = 'BNR-'.$params['product'].'-'.$params['order_no'];
        }
        $this->sendServerRequest($params, 'https://payport.novalnet.de/paygate.jsp');
    }

     /**
     * Get Novalnet payment method id/key
     *
     * @param string $code
     * @return string
     *
     * @author Novalnet AG
     */
    public function getPaymentId($code)
    {
        $paymentId = [
            'novalnetcreditcard' => '6',
            'novalnetsepa' => '37',
            'novalnetprepayment' => '27',
            'novalnetinvoice' => '27',
            'novalnetsofort' => '33',
            'novalnetpaypal' => '34',
            'novalnetideal' => '49',
            'novalneteps' => '50',
            'novalnetcashpayment' => '59',
            'novalnetgiropay' => '69',
            'novalnetprzelewy24' => '78',
        ];
        return $paymentId[$code];
    }

    /**
     * Get Novalnet payment method type
     *
     * @param string $code
     * @return string
     *
     * @author Novalnet AG
     */
    public function getPaymentType($code)
    {
        $paymentType = [
            'novalnetcreditcard' => 'CREDITCARD',
            'novalnetsepa' => 'DIRECT_DEBIT_SEPA',
            'novalnetprepayment' => 'INVOICE_START',
            'novalnetinvoice' => 'INVOICE_START',
            'novalnetsofort' => 'ONLINE_TRANSFER',
            'novalnetpaypal' => 'PAYPAL',
            'novalnetideal' => 'IDEAL',
            'novalneteps' => 'EPS',
            'novalnetcashpayment' => 'CASHPAYMENT',
            'novalnetgiropay' => 'GIROPAY',
            'novalnetprzelewy24' => 'PRZELEWY24',
        ];
        return $paymentType[$code];
    }

    /**
     * Retrieves the original end-customer address with and without proxy
     *
     * @return string
     *
     * @author Novalnet AG
     */
    public function getRemoteAddress()
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key)
        {
            if (array_key_exists($key, $_SERVER) === true)
            {
                foreach (explode(',', $_SERVER[$key]) as $ip)
                {
                    return $ip;
                }
            }
        }
    }

    /**
     * Forms comments for barzhalan nearest store details
     *
     * @param array $requestData
     *
     * @return string
     *
     * @author Novalnet AG
     */
    protected function getBarzahlenComments($requestData)
    {
        $storeCounts = 1;
        foreach ($requestData as $sKey => $sValue)
        {
            if (strpos($sKey, 'nearest_store_street') !== false)
               $storeCounts++;
        }

        $dueDate = !empty($requestData['cp_due_date']) ? $requestData['cp_due_date']: $requestData['due_date'];
        $barzahlenComments = PHP_EOL. $this->fieldLabel('SlipExpiryDate') . date('d.m.Y', strtotime($dueDate));
        if($storeCounts !=1)
            $barzahlenComments .= PHP_EOL . $this->fieldLabel('CashpaymentStore') . PHP_EOL;

        for ($i = 1; $i < $storeCounts; $i++)
        {
            $barzahlenComments .= $requestData['nearest_store_title_' . $i] . PHP_EOL;
            $barzahlenComments .= $requestData['nearest_store_street_' . $i ] . PHP_EOL;
            $barzahlenComments .= $requestData['nearest_store_city_' . $i ] . PHP_EOL;
            $barzahlenComments .= $requestData['nearest_store_zipcode_' . $i ] . PHP_EOL;
            $barzahlenComments .= $requestData['nearest_store_country_' . $i ].PHP_EOL;
            $break = PHP_EOL;
            if ( ($storeCounts -2) < $i )
            $break ='';
        }

        return $barzahlenComments;
    }

    /**
     * Is called when a payment provider sends a background notification to the shop.
     *
     * @param HTTPRequest $request Request data
     *
     * @return
     *
     * @author Novalnet AG
     */
    protected function processNotification(HTTPRequest $request) : void
    {
        $getRequestData = $request->postVars();
        $callbackProcess = NovalnetCallback::create();
        $callbackProcess->startProcess($getRequestData);
    }
}
