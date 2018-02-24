<?php

/**
 * @package Authorize.Net
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2017, Iurii Makukh <gplcart.software@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License 3.0
 */

namespace gplcart\modules\authorize;

use Exception;
use gplcart\core\Module;
use Omnipay\AuthorizeNet\SIMGateway;
use UnexpectedValueException;

/**
 * Main class for Authorize.Net module
 */
class Main
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
     * Order model instance
     * @var \gplcart\core\models\Order $order
     */
    protected $order;

    /**
     * Module class instance
     * @var \gplcart\core\Module
     */
    protected $module;

    /**
     * @param Module $module
     */
    public function __construct(Module $module)
    {
        $this->module = $module;
    }

    /**
     * Implements hook "route.list"
     * @param array $routes
     */
    public function hookRouteList(array &$routes)
    {
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
        try {
            $this->getGateway();
        } catch (Exception $ex) {
            $result = $ex->getMessage();
        }
    }

    /**
     * Implements hook "module.install.before"
     * @param mixed $result
     */
    public function hookModuleInstallBefore(&$result)
    {
        try {
            $this->getGateway();
        } catch (Exception $ex) {
            $result = $ex->getMessage();
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
            'title' => 'Authorize.Net',
            'template' => array('complete' => 'pay')
        );
    }

    /**
     * Implements hook "order.add.before"
     * @param array $order
     * @param \gplcart\core\models\Order $order_model
     */
    public function hookOrderAddBefore(array &$order, $order_model)
    {
        // Adjust order status before creation
        // We want to get payment in advance, so assign "awaiting payment" status
        if ($order['payment'] === 'authorize_sim') {
            $order['status'] = $order_model->getStatusAwaitingPayment();
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
     * @param \gplcart\core\models\Order $order_model
     * @param \gplcart\core\controllers\frontend\Controller $controller
     */
    public function hookOrderCompletePage(array $order, $order_model, $controller)
    {
        if ($order['payment'] === 'authorize_sim') {

            $this->data_order = $order;
            $this->order = $order_model;
            $this->controller = $controller;

            $this->processPurchase();
        }
    }

    /**
     * Get gateway instance
     * @return \Omnipay\AuthorizeNet\SIMGateway
     * @throws UnexpectedValueException
     */
    protected function getGateway()
    {
        /* @var $module \gplcart\modules\omnipay_library\Main */
        $module = $this->module->getInstance('omnipay_library');
        $gateway = $module->getGatewayInstance('AuthorizeNet_SIM');

        if (!$gateway instanceof SIMGateway) {
            throw new UnexpectedValueException('Gateway must be instance of Omnipay\AuthorizeNet\SIMGateway');
        }

        return $gateway;
    }

    /**
     * Process payment
     */
    protected function processPurchase()
    {
        if ($this->controller->isPosted('pay')) {
            $this->submitPurchase();
        } else if ($this->controller->isQuery('authorize_return')) {
            $this->response = $this->getGateway()->completePurchase($this->getPurchaseParams())->send();
            if ($this->controller->isQuery('cancel')) {
                $this->cancelPurchase();
            } else {
                $this->finishPurchase();
            }
        }
    }

    /**
     * Performs actions when a payment is canceled
     */
    protected function cancelPurchase()
    {
        $this->controller->setMessage($this->controller->text('Payment has been canceled'), 'warning');
        $gateway_message = $this->response->getMessage();
        if (!empty($gateway_message)) {
            $this->controller->setMessage($gateway_message, 'warning');
        }
    }

    /**
     * Handles submitted payment
     */
    protected function submitPurchase()
    {
        $gateway = $this->getGateway();
        $gateway->setApiLoginId($this->getSetting('apiLoginId'));
        $gateway->setHashSecret($this->getSetting('hashSecret'));
        $gateway->setTestMode((bool) $this->getSetting('testMode'));
        $gateway->setDeveloperMode((bool) $this->getSetting('testMode'));
        $gateway->setTransactionKey($this->getSetting('transactionKey'));

        $this->response = $gateway->purchase($this->getPurchaseParams())->send();

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
        $url = "checkout/complete/{$this->data_order['order_id']}";

        return array(
            'currency' => $this->data_order['currency'],
            'amount' => $this->data_order['total_formatted_number'],
            'returnUrl' => $this->controller->url($url, array('authorize_return' => true), true),
            'cancelUrl' => $this->controller->url($url, array('authorize_return' => true, 'cancel' => true), true)
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
        $data = array('status' => $this->getSetting('order_status_success'));
        $this->order->update($this->data_order['order_id'], $data);
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

        /* @var $model \gplcart\core\models\Transaction */
        $model = gplcart_instance_model('Transaction');
        return $model->add($transaction);
    }

    /**
     * Returns the current status for the payment method
     * @return bool
     */
    protected function getStatus()
    {
        return $this->getSetting('status')
            && $this->getSetting('apiLoginId')
            && $this->getSetting('hashSecret')
            && $this->getSetting('transactionKey');
    }

    /**
     * Returns a module setting
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function getSetting($name, $default = null)
    {
        return $this->module->getSettings('authorize', $name, $default);
    }

}
