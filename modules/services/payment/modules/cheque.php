<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright (c) 2009 PhreeSoft, LLC                               |
// | http://www.PhreeSoft.com                                        |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// |                                                                 |
// | The license that is bundled with this package is located in the |
// | file: /doc/manual/ch01-Introduction/license.html.               |
// | If not, see http://www.gnu.org/licenses/                        |
// +-----------------------------------------------------------------+
//  Path: /modules/services/payment/modules/cheque.php
//

  class cheque {
    var $code, $title, $description, $enabled;

// class constructor
    function cheque() {
      global $order;

      $this->code = 'cheque';
      $this->title = MODULE_PAYMENT_CHEQUE_TEXT_TITLE;
      $this->description = MODULE_PAYMENT_CHEQUE_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_PAYMENT_CHEQUE_SORT_ORDER;
      $this->enabled = ((MODULE_PAYMENT_CHEQUE_STATUS == 'True') ? true : false);

      if ((int)MODULE_PAYMENT_CHEQUE_ORDER_STATUS_ID > 0) {
        $this->order_status = MODULE_PAYMENT_CHEQUE_ORDER_STATUS_ID;
      }

          $this->payment_fields = implode(':', array($_POST['moneyorder_field_0']));
	  
    }

// class methods
    function javascript_validation() {
      return false;
    }

    function selection() {
	  global $order;
      return array(
	  	    'id' => $this->code,
            'module' => $this->title,
	        'fields' => array(
			    array('title' => MODULE_PAYMENT_CHEQUE_TEXT_REF_NUM,
					  'field' => html_input_field($this->code . '_field_0', $order->moneyorder_ref, 'size="33" maxlength="32"'))
			)
		);
    }

    function pre_confirmation_check() {
      return false;
    }

    function confirmation() {
      return array('title' => MODULE_PAYMENT_CHEQUE_TEXT_DESCRIPTION);
    }

    function process_button() {
      return false;
    }

    function before_process() {
      return false;
    }

    function after_process() {
      return false;
    }

    function check() {
      global $db;
      if (!isset($this->_check)) {
        $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_CHEQUE_STATUS'");
        $this->_check = $check_query->RecordCount();
      }
      return $this->_check;
    }

    function install() {
      global $db;
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable cheque Module', 'MODULE_PAYMENT_CHEQUE_STATUS', 'True', 'Do you want to accept cheque payments?', '6', '1', 'cfg_select_option(array(\'True\', \'False\'), ', now());");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Make Payable to:', 'MODULE_PAYMENT_CHEQUE_PAYTO', '', 'Who should payments be made payable to?', '6', '1', now());");
      $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_CHEQUE_SORT_ORDER', '10', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
    }

    function remove() {
      global $db;
      $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array('MODULE_PAYMENT_CHEQUE_STATUS', 
		'MODULE_PAYMENT_CHEQUE_SORT_ORDER', 
		'MODULE_PAYMENT_CHEQUE_PAYTO');
    }
  }
?>
