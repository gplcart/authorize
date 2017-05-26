<?php
/**
 * @package Worldpay
 * @author Iurii Makukh <gplcart.software@gmail.com>
 * @copyright Copyright (c) 2017, Iurii Makukh <gplcart.software@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License 3.0
 */
?>
<form method="post" enctype="multipart/form-data" class="form-horizontal">
  <input type="hidden" name="token" value="<?php echo $_token; ?>">
  <div class="panel panel-default">
    <div class="panel-body">
      <div class="form-group">
        <label class="col-md-2 control-label"><?php echo $this->text('Status'); ?></label>
        <div class="col-md-6">
          <div class="btn-group" data-toggle="buttons">
            <label class="btn btn-default<?php echo empty($settings['status']) ? '' : ' active'; ?>">
              <input name="settings[status]" type="radio" autocomplete="off" value="1"<?php echo empty($settings['status']) ? '' : ' checked'; ?>>
              <?php echo $this->text('Enabled'); ?>
            </label>
            <label class="btn btn-default<?php echo empty($settings['status']) ? ' active' : ''; ?>">
              <input name="settings[status]" type="radio" autocomplete="off" value="0"<?php echo empty($settings['status']) ? ' checked' : ''; ?>>
              <?php echo $this->text('Disabled'); ?>
            </label>
          </div>
          <div class="help-block">
            <?php echo $this->text('Disabled payment methods will be hidden on checkout page'); ?>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-2 control-label"><?php echo $this->text('Test mode'); ?></label>
        <div class="col-md-6">
          <div class="btn-group" data-toggle="buttons">
            <label class="btn btn-default<?php echo empty($settings['testMode']) ? '' : ' active'; ?>">
              <input name="settings[testMode]" type="radio" autocomplete="off" value="1"<?php echo empty($settings['testMode']) ? '' : ' checked'; ?>>
              <?php echo $this->text('Enabled'); ?>
            </label>
            <label class="btn btn-default<?php echo empty($settings['testMode']) ? ' active' : ''; ?>">
              <input name="settings[testMode]" type="radio" autocomplete="off" value="0"<?php echo empty($settings['testMode']) ? ' checked' : ''; ?>>
              <?php echo $this->text('Disabled'); ?>
            </label>
          </div>
          <div class="help-block">
            <?php echo $this->text('Test mode is intended for testing purposes and should be disabled to send real payments'); ?>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-2 control-label"><?php echo $this->text('Order status'); ?></label>
        <div class="col-md-4">
          <select name="settings[order_status_success]" class="form-control">
            <?php foreach ($statuses as $status_id => $status_name) { ?>
            <option value="<?php echo $this->e($status_id); ?>"<?php echo isset($settings['order_status_success']) && $settings['order_status_success'] == $status_id ? ' selected' : ''; ?>><?php echo $this->e($status_name); ?></option>
            <?php } ?>
          </select>
          <div class="help-block">
            <?php echo $this->text('The status will be assigned to an order after successful transaction'); ?>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-2 control-label"><?php echo $this->text('API login ID'); ?></label>
        <div class="col-md-4">
          <input name="settings[apiLoginId]" class="form-control" value="<?php echo isset($settings['apiLoginId']) ? $this->e($settings['apiLoginId']) : ''; ?>">
          <div class="help-block">
            <?php echo $this->text('The API Login ID is at least eight characters in length and intended to authenticate you in Authorize.net'); ?>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-2 control-label"><?php echo $this->text('Transaction ID'); ?></label>
        <div class="col-md-4">
          <input name="settings[transactionKey]" class="form-control" value="<?php echo isset($settings['transactionKey']) ? $this->e($settings['transactionKey']) : ''; ?>">
          <div class="help-block">
            <?php echo $this->text('Transaction key contains 16 alphanumeric characters and intended to authenticate you in Authorize.net'); ?>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="col-md-2 control-label"><?php echo $this->text('Hash secret'); ?></label>
        <div class="col-md-4">
          <input name="settings[hashSecret]" class="form-control" value="<?php echo isset($settings['hashSecret']) ? $this->e($settings['hashSecret']) : ''; ?>">
          <div class="help-block">
              <?php echo $this->text('The MD5 Hash secret allows the module to verify that the results of a transaction are actually from Authorize.Net'); ?>
          </div>
        </div>
      </div>
      <div class="form-group">
        <div class="col-md-4 col-md-offset-2">
          <div class="btn-toolbar">
            <a href="<?php echo $this->url('admin/module/list'); ?>" class="btn btn-default"><i class="fa fa-reply"></i> <?php echo $this->text('Cancel'); ?></a>
            <button class="btn btn-default save" name="save" value="1">
              <i class="fa fa-floppy-o"></i> <?php echo $this->text('Save'); ?>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>