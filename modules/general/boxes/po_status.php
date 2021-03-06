<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright (c) 2008, 2009, 2010 PhreeSoft, LLC                   |
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
//  Path: /modules/general/boxes/po_status.php
//

class po_status extends ctl_panel {

	// class constructor
	function po_status() {
		$this->module_id = 'po_status';
		$this->category = 'vendors';
		$this->title = CP_PO_STATUS_TITLE;
		$this->security = SECURITY_ID_PURCHASE_ORDER;
		$this->description = CP_PO_STATUS_DESCRIPTION;
	}

	function Install($column_id = 1, $row_id = 0) {
		global $db;
		if (!$row_id) $row_id = $this->get_next_row();
		$params['num_rows'] = '5';	// defaults to 5 rows to start
		$result = $db->Execute("insert into " . TABLE_USERS_PROFILES . " set 
			user_id = " . $_SESSION['admin_id'] . ", 
			page_id = '" . $this->page_id . "', 
			module_id = '" . $this->module_id . "', 
			column_id = " . $column_id . ", 
			row_id = " . $row_id . ", 
			params = '" . serialize($params) . "'");
	}

	function Remove() {
		global $db;
		$result = $db->Execute("delete from " . TABLE_USERS_PROFILES . " 
			where user_id = " . $_SESSION['admin_id'] . " and page_id = '" . $this->page_id . "' and module_id = '" . $this->module_id . "'");
	}

	function Output($params) {
		global $db, $currencies;
		// Build control box form data
		$control = '<div class="row">';
		$control .= '<div style="white-space:nowrap">' . TEXT_SHOW . TEXT_SHOW_NO_LIMIT;
		$control .= '<select name="num_rows" onchange="">';
		for ($i = 0; $i < MAX_NUM_PO_LIST; $i++) {
			$control .= '<option value="' . $i . '"' . ($params['num_rows'] == $i ? ' selected="selected"' : '') . '>' . $i . '</option>';
		}
		$control .= '</select> ' . TEXT_ITEMS . '&nbsp;&nbsp;&nbsp;&nbsp;';
		$control .= '<input type="submit" value="' . TEXT_SAVE . '" />';
		$control .= '</div></div>';

		// Build content box
		$sql = "select id, post_date, purchase_invoice_id, bill_primary_name, total_amount, currencies_code, currencies_value
			from " . TABLE_JOURNAL_MAIN . " 
			where journal_id = 4 and closed = '0' order by post_date DESC";
		if ($params['num_rows']) $sql .= " limit " . $params['num_rows'];
		$result = $db->Execute($sql);
		if ($result->RecordCount() < 1) {
			$contents = CP_PO_STATUS_NO_RESULTS;
		} else {
			while (!$result->EOF) {
			    $contents .= '<div style="float:right">' . $currencies->format_full($result->fields['total_amount'], true, $result->fields['currencies_code'], $result->fields['currencies_value']) . '</div>';
				$contents .= '<div>';
				$contents .= '<a href="' . html_href_link(FILENAME_DEFAULT, 'cat=orders&amp;module=orders&amp;oID=' . $result->fields['id'] . '&amp;jID=4&amp;action=edit', 'SSL') . '">';
				$contents .= $result->fields['purchase_invoice_id'] . ' - ';
				$contents .= gen_spiffycal_db_date_short($result->fields['post_date']);
				$name = (strlen($result->fields['bill_primary_name']) > 20) ? substr($result->fields['bill_primary_name'], 0, 20) . '...' : $result->fields['bill_primary_name'];
				$contents .= ' ' . htmlspecialchars($name);
				$contents .= '</a></div>' . chr(10);
				$result->MoveNext();
			}
		}
		return $this->build_div($this->title, $contents, $control);
	}

	function Update() {
		global $db;
		$params['num_rows'] = db_prepare_input($_POST['num_rows']);
		$column_id = db_prepare_input($_POST['column_id']);
		$row_id = db_prepare_input($_POST['row_id']);
		$admin_id = $_SESSION['admin_id'];
		$page_id = ($_GET['module']) ? $_GET['module'] : 'index';

		$db->Execute("update " . TABLE_USERS_PROFILES . " set 
				column_id = " . $column_id . ", 
				row_id = " . $row_id . ", 
				params = '" . serialize($params) . "' 
			where user_id = " . $admin_id . " and page_id = '" . $page_id . "' and module_id = '" . $this->module_id . "'");
	}

}
?>