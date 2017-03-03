<?php

/**
 * @package Authorize.Net
 * @author Iurii Makukh <gplcart.software@gmail.com> 
 * @copyright Copyright (c) 2017, Iurii Makukh <gplcart.software@gmail.com> 
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License 3.0 
 */

namespace gplcart\modules\authorize;

use gplcart\core\Config;
use gplcart\core\models\Order as OrderModel,
    gplcart\core\models\Language as LanguageModel,
    gplcart\core\models\Transaction as TransactionModel;
use gplcart\modules\omnipay_library\OmnipayLibrary as OmnipayLibraryModule;

/**
 * Main class for Authorize.Net module
 */
class Authorize
{

    /**
     * The current order
     * @var array
     */
    protected $data_order;

    /**
     * Omnipay response instance
     * @var object
     */
    protected $response;

    /**
     * Frontend controller instance
     * @var \gplcart\core\controllers\frontend\Controller $controller
     */
    protected $controller;

    /**
     * Gateway Omnipay instance
     * @var \Omnipay\AuthorizeNet\SIMGateway $gateway
     */
    protected $gateway;

    /**
     * Order model instance
     * @var \gplcart\core\models\Order $order
     */
    protected $order;

    /**
     * Transaction model instance
     * @var \gplcart\core\models\Transaction $transaction
     */
    protected $transaction;

    /**
     * Language model instance
     * @var \gplcart\core\models\Language $language
     */
    protected $language;

    /**
     * Config class instance
     * @var \gplcart\core\Config $config
     */
    protected $config;

    /**
     * Omnipay library module instance
     * @var \gplcart\modules\omnipay_library\OmnipayLibrary
     */
    protected $omnipay_library_module;

    /**
     * Constructor
     * @param Config $config
     * @param LanguageModel $language
     * @param OrderModel $order
     * @param TransactionModel $transaction
     * @param OmnipayLibraryModule $omnipay_library_module
     */
    public function __construct(Config $config, LanguageModel $language,
            OrderModel $order, TransactionModel $transaction,
            OmnipayLibraryModule $omnipay_library_module)
    {
        $this->order = $order;
        $this->config = $config;
        $this->language = $language;
        $this->transaction = $transaction;

        $this->omnipay_library_module = $omnipay_library_module;
        $this->gateway = $this->omnipay_library_module->getGatewayInstance('AuthorizeNet_SIM');
    }

    /**
     * Module info
     * @return array
     */
    public function info()
    {
        return array(
            'core' => '1.x',
            'name' => 'Authorize.Net',
            'version' => '1.0.0-alfa.1',
            'description' => 'Provides Authorize.Net SIM payment method',
            'author' => 'Iurii Makukh <gplcart.software@gmail.com>',
            'license' => 'GNU General Public License 3.0',
            'dependencies' => array('omnipay_library' => '>= 1.0'),
            'configure' => 'admin/module/settings/authorize',
            'settings' => $this->getDefaultSettings()
        );
    }

    /**
     * Returns an array of default module settings
     * @return array
     */
    protected function getDefaultSettings()
    {
        return array(
            'status' => true,
            'order_status_success' => $this->order->getStatusProcessing(),
            // Gateway specific params
            'testMode' => true,
            'developerMode' => false,
            'hashSecret' => '',
            'apiLoginId' => '',
            'transactionKey' => ''
        );
    }

    /**
     * Implements hook "route.list"
     * @param array $routes 
     */
    public function hookRouteList(array &$routes)
    {
        // Module settings page
        $routes['admin/module/settings/authorize'] = array(
            'access' => 'module_edit',
            'handlers' => array(
                'controller' => array('gplcart\\modules\\authorize\\controllers\\Settings', 'editSettings')
            )
        );
    }

    /**
     * Implements hook "module.enable.before"
     * @param mixed $result
     */
    public function hookModuleEnableBefore(&$result)
    {
        $this->validateGateway($result);
    }

    /**
     * Implements hook "module.install.before"
     * @param mixed $result
     */
    public function hookModuleInstallBefore(&$result)
    {
        $this->validateGateway($result);
    }

    /**
     * Checks the gateway object is loaded
     * @param mixed $result
     */
    protected function validateGateway(&$result)
    {
        if (!is_object($this->gateway)) {
            $result = $this->language->text('Unable to load @name gateway', array('@name' => 'Authorize.Net'));
        }
    }

    /**
     * Implements hook "payment.methods"
     * @param array $methods 
     */
    public function hookPaymentMethods(array &$methods)
    {
        $methods['authorize_sim'] = array(
            'module' => 'authorize',
            'image' => 'image/icon.png',
            'status' => $this->getStatus(),
            'title' => $this->language->text('Authorize.Net'),
            'template' => array('complete' => 'pay')
        );
    }

    /**
     * Returns the current status for the payment method
     * @return bool
     */
    protected function getStatus()
    {
        return $this->setting('status')//
                && $this->setting('apiLoginId')//
                && $this->setting('hashSecret')//
                && $this->setting('transactionKey');
    }

    /**
     * Returns a module setting
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function setting($name, $default = null)
    {
        return $this->config->module('authorize', $name, $default);
    }

    /**
     * Implements hook "order.add.before"
     * @param array $order
     */
    public function hookOrderAddBefore(array &$order)
    {
        // Adjust order status before creation
        // We want to get payment in advance, so assign "awaiting payment" status
        if ($order['payment'] === 'authorize_sim') {
            $order['status'] = $this->order->getStatusAwaitingPayment();
        }
    }

    /**
     * Implements hook "order.checkout.complete"
     * @param string $message
     * @param array $order
     */
    public function hookOrderCompleteMessage(&$message, $order)
    {
        if ($order['payment'] === 'authorize_sim') {
            $message = ''; // Hide default message
        }
    }

    /**
     * Implements hook "order.complete.page"
     * @param array $order
     * @param \gplcart\core\controllers\frontend\Controller $controller
     * @return null
     */
    public function hookOrderCompletePage(array $order, $controller)
    {
        if ($order['payment'] !== 'authorize_sim') {
            return null;
        }

        $this->data_order = $order;
        $this->controller = $controller;

        if ($this->controller->isPosted('pay')) {
            $this->submit();
            return null;
        }

        if (!$this->controller->isQuery('authorize_return')) {
            return null;
        }

        $this->response = $this->gateway->completePurchase($this->getPurchaseParams())->send();

        if ($this->controller->isQuery('cancel')) {
            $this->cancelPurchase();
            return null;
        }

        $this->finishPurchase();
    }

    /**
     * Performs actions when a payment is canceled
     */
    protected function cancelPurchase()
    {
        $message = $this->controller->text('Payment has been canceled');
        $this->controller->setMessage($message, 'warning');

        $gateway_message = $this->response->getMessage();

        if (!empty($gateway_message)) {
            $this->controller->setMessage($gateway_message, 'warning');
        }
    }

    /**
     * Handles submitted payment
     */
    protected function submit()
    {
        $this->gateway->setApiLoginId($this->setting('apiLoginId'));
        $this->gateway->setHashSecret($this->setting('hashSecret'));
        $this->gateway->setTestMode((bool) $this->setting('testMode'));
        $this->gateway->setDeveloperMode((bool) $this->setting('testMode'));
        $this->gateway->setTransactionKey($this->setting('transactionKey'));

        $this->response = $this->gateway->purchase($this->getPurchaseParams())->send();

        if ($this->response->isRedirect()) {
            $this->response->redirect();
        } else if (!$this->response->isSuccessful()) {
            $this->redirectError();
        }
    }

    /**
     * Returns an array of purchase parameters
     * @return array
     */
    protected function getPurchaseParams()
    {
        return array(
            'currency' => $this->data_order['currency'],
            'amount' => $this->data_order['total_formatted_number'],
            'cancelUrl' => $this->controller->url("checkout/complete/{$this->data_order['order_id']}", array('authorize_return' => true, 'cancel' => true), true),
            'returnUrl' => $this->controller->url("checkout/complete/{$this->data_order['order_id']}", array('authorize_return' => true), true)
        );
    }

    /**
     * Performs final actions on success payment
     */
    protected function finishPurchase()
    {
        if ($this->response->isSuccessful()) {
            $this->updateOrderStatus();
            $this->addTransaction();
            $this->redirectSuccess();
        } else if ($this->response->isRedirect()) {
            $this->response->redirect();
        } else {
            $this->redirectError();
        }
    }

    /**
     * Redirect on error payment
     */
    protected function redirectError()
    {
        $this->controller->redirect('', $this->response->getMessage(), 'warning', true);
    }

    /**
     * Redirect on successful payment
     */
    protected function redirectSuccess()
    {
        $vars = array(
            '@num' => $this->data_order['order_id'],
            '@status' => $this->order->getStatusName($this->data_order['status'])
        );

        $message = $this->controller->text('Thank you! Payment has been made. Order #@num, status: @status', $vars);
        $this->controller->redirect('/', $message, 'success', true);
    }

    /**
     * Update order status after successful transaction
     */
    protected function updateOrderStatus()
    {
        $data = array(
            'status' => $this->setting('order_status_success'));
        $this->order->update($this->data_order['order_id'], $data);

        // Load fresh data
        $this->data_order = $this->order->get($this->data_order['order_id']);
    }

    /**
     * Adds a transaction
     */
    protected function addTransaction()
    {
        $transaction = array(
            'total' => $this->data_order['total'],
            'order_id' => $this->data_order['order_id'],
            'currency' => $this->data_order['currency'],
            'payment_method' => $this->data_order['payment'],
            'gateway_transaction_id' => $this->response->getTransactionReference()
        );

        $this->transaction->add($transaction);
    }

}
