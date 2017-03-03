<form method="post" id="authorize-payment-form" class="form-horizontal">
  <p><?php echo $this->text('To process your order we need to get your payment in advance'); ?></p>
  <div class="form-group">
    <div class="col-md-4">
      <input type="submit" name="pay" class="btn btn-default" value="<?php echo $this->text('Pay @amount', array('@amount' => $order['total_formatted'])); ?>">
    </div>
  </div>
</form>
<script>$(function(){$('#authorize-payment-form :submit').click();});</script>