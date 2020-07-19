<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/

  require('includes/application_top.php');

// if the customer is not logged on, redirect them to the login page
  if (!tep_session_is_registered('customer_id')) {
    $navigation->set_snapshot();
    tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
  }

// if there is nothing in the customers cart, redirect them to the shopping cart page
  if ($cart->count_contents() < 1) {
    tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
  }

// if no shipping method has been selected, redirect the customer to the shipping method selection page
  if (!tep_session_is_registered('shipping')) {
    tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
  }

// avoid hack attempts during the checkout procedure by checking the internal cartID
  if (isset($cart->cartID) && tep_session_is_registered('cartID')) {
    if ($cart->cartID != $cartID) {
      tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
    }
  }

// Stock Check
  if ( (STOCK_CHECK == 'true') && (STOCK_ALLOW_CHECKOUT != 'true') ) {
    $products = $cart->get_products();
    for ($i=0, $n=sizeof($products); $i<$n; $i++) {
      if (tep_check_stock($products[$i]['id'], $products[$i]['quantity'])) {
        tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
        break;
      }
    }
  }

// if no billing destination address was selected, use the customers own address as default
  if (!tep_session_is_registered('billto')) {
    tep_session_register('billto');
    $billto = $customer_default_address_id;
  } else {
// verify the selected billing address
    if ( (is_array($billto) && empty($billto)) || is_numeric($billto) ) {
      $check_address_query = tep_db_query("select count(*) as total from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$customer_id . "' and address_book_id = '" . (int)$billto . "'");
      $check_address = tep_db_fetch_array($check_address_query);

      if ($check_address['total'] != '1') {
        $billto = $customer_default_address_id;
        if (tep_session_is_registered('payment')) tep_session_unregister('payment');
      }
    }
  }


//-MS- Active Countries Added --- Added 10-05-14
  if( !tep_validate_active_countries($billto, 1) ) {
    $messageStack->add_session('checkout_address', 'Invalid Country for Billing');
    tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT_ADDRESS, '', 'SSL'));
  }
//-MS- Active Countries Added EOM


  require(DIR_WS_CLASSES . 'order.php');
  $order = new order;

  if (!tep_session_is_registered('comments')) tep_session_register('comments');
  if (isset($HTTP_POST_VARS['comments']) && tep_not_null($HTTP_POST_VARS['comments'])) {
    $comments = tep_db_prepare_input($HTTP_POST_VARS['comments']);
  }

  $total_weight = $cart->show_weight();
  $total_count = $cart->count_contents();

// load all enabled payment modules
  require(DIR_WS_CLASSES . 'payment.php');
  $payment_modules = new payment;

  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_PAYMENT);

  $breadcrumb->add(NAVBAR_TITLE_1, tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
  $breadcrumb->add(NAVBAR_TITLE_2, tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));

  require(DIR_WS_INCLUDES . 'template_top.php');
?>

<script><!--
var selected;

function selectRowEffect(object, buttonSelect) {
  if (!selected) {
    if (document.getElementById) {
      selected = document.getElementById('defaultSelected');
    } else {
      selected = document.all['defaultSelected'];
    }
  }

  if (selected) selected.className = 'moduleRow';
  object.className = 'moduleRowSelected';
  selected = object;

// one button is not an array
  if (document.checkout_payment.payment[0]) {
    document.checkout_payment.payment[buttonSelect].checked=true;
  } else {
    document.checkout_payment.payment.checked=true;
  }
}

function rowOverEffect(object) {
  if (object.className == 'moduleRow') object.className = 'moduleRowOver';
}

function rowOutEffect(object) {
  if (object.className == 'moduleRowOver') object.className = 'moduleRow';
}
//--></script>
<?php echo $payment_modules->javascript_validation(); ?>

<div class="page-header">
  <h1><?php echo HEADING_TITLE; ?></h1>
</div>


<!-- Move progress bar to top of page - 7/3/15 -->

<div class="contentText">
  <div class="stepwizard">
    <div class="stepwizard-row">
      <div class="stepwizard-step">
        <a href="<?php echo tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'); ?>">
          <button type="button" class="btn btn-default btn-circle">1</button>
        </a>
        <p>
          <a href="<?php echo tep_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'); ?>">
            <?php echo CHECKOUT_BAR_DELIVERY; ?>
          </a>
        </p>
      </div>
      <div class="stepwizard-step">
        <button type="button" class="btn btn-primary btn-circle">2</button>
        <p><?php echo CHECKOUT_BAR_PAYMENT; ?></p>
      </div>
      <div class="stepwizard-step">
        <button type="button" class="btn btn-default btn-circle" disabled="disabled">3</button>
        <p><?php echo CHECKOUT_BAR_CONFIRMATION; ?></p>
      </div>
    </div>
  </div>
</div>




<?php echo tep_draw_form('checkout_payment', tep_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL'), 'post', 'class="form-horizontal" onsubmit="return check_form();"', true); ?>

<div class="contentContainer">

<?php
  if (isset($HTTP_GET_VARS['payment_error']) && is_object(${$HTTP_GET_VARS['payment_error']}) && ($error = ${$HTTP_GET_VARS['payment_error']}->get_error())) {
?>

  <div class="contentText">
    <?php echo '<strong>' . tep_output_string_protected($error['title']) . '</strong>'; ?>

    <p class="messageStackError"><?php echo tep_output_string_protected($error['error']); ?></p>
  </div>

<?php
  }
?>





<!-- Added for Vertual Merchant (Costco/Elavon) Credit Card Processing - 7-21-15 -->

<?php
  if (tep_session_is_registered('payment_error_return') && !empty($payment_error_return)) {
    $error = explode('|',$payment_error_return);
?>
    <tr>
      <td>
        <div style="border:thin solid #f9e045; background:#fdffcd; padding:10px; margin:10px">
          <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td width="5%" valign="top">
                <div align="center">
                  <?php echo tep_image(DIR_WS_IMAGES . 'cc_chargeit_error.gif'); ?>
                </div>
              </td>

              <td width="95%">
                <div style="font-family:Arial, Helvetica, sans-serif; font-weight:bold; font-size:13px; color:#ee350b">
                  <span style="font-size:14px">
                    <?php echo tep_output_string($error[0]); ?>
                  </span>
                  <p><?php echo tep_output_string($error[1]); ?></p>
                  <?php echo tep_output_string($error[2]) . tep_output_string($error[3]) . tep_output_string($error[4]); ?>
                </div>
              </td>
            </tr>
          </table>
        </div>
      </td>
    </tr>

<?php
// clear error
    $payment_error_return = '';
  }
?>

<!-- EOF Vertual Merchant -->







  <h2><?php echo TABLE_HEADING_BILLING_ADDRESS; ?></h2>

  <div class="contentText row">
    <div class="col-sm-8">

<?php
      if(!tep_session_is_registered('customer_is_guest')) {
?>

        <div class="alert alert-warning">
          <?php echo TEXT_SELECTED_BILLING_DESTINATION; ?>
          <div class="clearfix"></div>
          <div class="pull-right">
            <?php echo tep_draw_button(IMAGE_BUTTON_CHANGE_ADDRESS, 'glyphicon-home', tep_href_link(FILENAME_CHECKOUT_PAYMENT_ADDRESS, '', 'SSL')); ?>
          </div>
          <div class="clearfix"></div>
        </div>
      </div>

      <div class="col-sm-4">

<?php
      }
?>    

      <div class="panel panel-primary">
        <div class="panel-heading"><?php echo TITLE_BILLING_ADDRESS; ?></div>
        <div class="panel-body">
          <?php echo tep_address_label($customer_id, $billto, true, ' ', '<br />'); ?>
        </div>
      </div>
    </div>
  </div>

  <div class="clearfix"></div>

  <h2><?php echo TABLE_HEADING_PAYMENT_METHOD; ?></h2>

<?php
  $selection = $payment_modules->selection();

  if (sizeof($selection) > 1) {
?>

  <div class="contentText">
    <div class="alert alert-warning">
      <div class="row">
        <div class="col-xs-9">
          <?php echo TEXT_SELECT_PAYMENT_METHOD; ?>
        </div>
        <div class="col-xs-3 text-right">
          <?php echo '<strong>' . TITLE_PLEASE_SELECT . '</strong>'; ?>
        </div>
      </div>
    </div>
  </div>


<?php
    } else {
?>

  <div class="contentText">
    <div class="alert alert-info"><?php echo TEXT_ENTER_PAYMENT_INFORMATION; ?></div>
  </div>

<?php
    }
?>

  <div class="contentText">

<?php
  $radio_buttons = 0;
  for ($i=0, $n=sizeof($selection); $i<$n; $i++) {
    if (isset($quotes[$i]['error'])) {
?>
      <div class="contentText">
        <div class="alert alert-warning"><?php echo $selection[$i]['error']; ?></div>
      </div>

<?php
        } else {

?>

    <div class="form-group">
      <label class="control-label col-xs-9">
        <strong>
          <?php echo $selection[$i]['module']; ?>
        </strong>
      </label>
      <div class="col-xs-3 col-xs-pull-1 text-right">
        <label class="checkbox-inline">


<!-- Replace for Vertual Merchant - 7-21-15 
//<?php
//          if (sizeof($selection) > 1) {
//            echo tep_draw_radio_field('payment', $selection[$i]['id'], ($selection[$i]['id'] == $payment));
//          } else {
//            echo tep_draw_hidden_field('payment', $selection[$i]['id']);
//          }
//?>
-->


<?php
          if (sizeof($selection) > 1) {
            echo tep_draw_radio_field('payment', $selection[$i]['id'], ($selection[$i]['id'] == $payment), 'id="payment"');
          } else {
            echo tep_draw_hidden_field('payment', $selection[$i]['id'], 'id="payment"');
          }
?>

<!-- EOF Vertual Merchant replace -->




        </label>
      </div>
    </div>

<?php
    $radio_buttons++;
  }
}
?>

  </div>

  <hr>

  <div class="contentText">
    <div class="form-group">
      <label for="inputComments" class="control-label col-xs-4"><?php echo TABLE_HEADING_COMMENTS; ?></label>
      <div class="col-xs-8">
        <?php
        echo tep_draw_textarea_field('comments', 'soft', 60, 5, $comments, 'id="inputComments" placeholder="' . TABLE_HEADING_COMMENTS . '"');
        ?>
      </div>
    </div>
  </div>

  <div class="buttonSet">
    <span class="buttonAction">
      <?php echo tep_draw_button(IMAGE_BUTTON_CONTINUE, 'glyphicon-chevron-right', null, 'primary', null, 'btn-success'); ?>
    </span>
  </div>

  <div class="clearfix"></div>

</div>

</form>

<?php
  require(DIR_WS_INCLUDES . 'template_bottom.php');
  require(DIR_WS_INCLUDES . 'application_bottom.php');
?>