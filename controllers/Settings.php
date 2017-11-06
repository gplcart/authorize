<?php

/**
 * @package Authorize.Net
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2017, Iurii Makukh <gplcart.software@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License 3.0
 */

namespace gplcart\modules\authorize\controllers;

use gplcart\core\models\Order as OrderModel,
    gplcart\core\models\Module as ModuleModel;
use gplcart\core\controllers\backend\Controller as BackendController;

/**
 * Handles incoming requests and outputs data related to Authorize.Net module
 */
class Settings extends BackendController
{

    /**
     * Module model instance
     * @var \gplcart\core\models\Module $module
     */
    protected $module;

    /**
     * Order model instance
     * @var \gplcart\core\models\Order $order
     */
    protected $order;

    /**
     * Constructor
     * @param ModuleModel $module
     * @param OrderModel $order
     */
    public function __construct(ModuleModel $module, OrderModel $order)
    {
        parent::__construct();

        $this->order = $order;
        $this->module = $module;
    }

    /**
     * Route page callback to display module settings form
     */
    public function editSettings()
    {
        $this->setTitleEditSettings();
        $this->setBreadcrumbEditSettings();

        $this->setData('statuses', $this->order->getStatuses());
        $this->setData('settings', $this->config->getFromModule('authorize'));

        $this->submitSettings();
        $this->outputEditSettings();
    }

    /**
     * Saves the submitted settings
     */
    protected function submitSettings()
    {
        if ($this->isPosted('save') && $this->validateSettings()) {
            $this->updateSettings();
        }
    }

    /**
     * Updates module settings
     */
    protected function updateSettings()
    {
        $this->controlAccess('module_edit');
        $this->module->setSettings('authorize', $this->getSubmitted());
        $this->redirect('admin/module/list', $this->text('Settings have been updated'), 'success');
    }

    /**
     * Validates module settings
     * @return bool
     */
    protected function validateSettings()
    {
        $this->setSubmitted('settings');
        $this->setSubmittedBool('status');
        return !$this->hasErrors('settings');
    }

    /**
     * Set title on the edit module settings page
     */
    protected function setTitleEditSettings()
    {
        $vars = array('%name' => $this->text('Authorize'));
        $title = $this->text('Edit %name settings', $vars);
        $this->setTitle($title);
    }

    /**
     * Set breadcrumbs on the edit module settings page
     */
    protected function setBreadcrumbEditSettings()
    {
        $breadcrumbs = array();

        $breadcrumbs[] = array(
            'text' => $this->text('Dashboard'),
            'url' => $this->url('admin')
        );

        $breadcrumbs[] = array(
            'text' => $this->text('Modules'),
            'url' => $this->url('admin/module/list')
        );

        $this->setBreadcrumbs($breadcrumbs);
    }

    /**
     * Render and output the edit module settings page
     */
    protected function outputEditSettings()
    {
        $this->output('authorize|settings');
    }

}
