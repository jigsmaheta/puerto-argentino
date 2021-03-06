<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright (c) 2010 PhreeSoft, LLC                               |
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
//  Path: /admin/includes/classes/phreebooks.php
//

// Hook for including customization of product attributes
if (file_exists(DIR_FS_ADMIN . 'soap/extra_actions/extra_order_actions.php')) include (DIR_FS_ADMIN . 'soap/extra_actions/extra_order_actions.php');
// EOF - Hook for customization

class phreebooks {
	var $arrOutput = array();
	var $resParser;
	var $strXML;

	// class constructor
	function phreebooks() {
	}

	function parse($strInputXML) {
		global $messageStack;
//echo 'strInputXML = ' . $strInputXML . '<br>';
		$this->resParser = xml_parser_create ( "UTF-8" );
		xml_set_object($this->resParser, $this);
		xml_set_element_handler($this->resParser, "tagOpen", "tagClosed");
		xml_set_character_data_handler($this->resParser, "tagData");

		$this->strXmlData = xml_parse($this->resParser, $strInputXML);
		if(!$this->strXmlData) {
		    $strText = 'Zencart XML parse error: ' . xml_error_string(xml_get_error_code($this->resParser)) . ' at line ' . xml_get_current_line_number($this->resParser);
			$messageStack->add($strText, 'error');
			return false;
		}
		xml_parser_free($this->resParser);
		return $this->arrOutput;
	}

	function tagOpen($parser, $name, $attrs) {
		$tag=array("name"=>$name,"attrs"=>$attrs); 
		array_push($this->arrOutput,$tag);
	}

	function tagData($parser, $tagData) {
		if (trim($tagData) <> '') {
			if (isset($this->arrOutput[count($this->arrOutput)-1]['tagData'])) {
				$this->arrOutput[count($this->arrOutput)-1]['tagData'] .= $tagData;
			} else {
				$this->arrOutput[count($this->arrOutput)-1]['tagData']  = $tagData;
			}
		}
	}

	function tagClosed($parser, $name) {
		$this->arrOutput[count($this->arrOutput)-2]['children'][] = $this->arrOutput[count($this->arrOutput)-1];
		array_pop($this->arrOutput);
	}

	function getNodeData($tree, $search_array) {
		// $tree is an array path to the desired node element
		// will return false if tree path is invalid or is not found; node value (including null) if found.
		$node_data = false;
		$path_element = array_shift($tree);
		for ($i=0; $i<count($search_array); $i++) {
			if ($search_array[$i]['name'] == $path_element) {
				if (count($tree) > 0) {
					return $this->getNodeData($tree, $search_array[$i]['children']);
				} else {
					return $search_array[$i]['tagData'];
				}
			}
		}
		return false;
	}

	function submitXML($action = 'download', $data = '') {
		global $db, $messageStack;
		switch ($action) {
			case 'download': 
				$this->buildOrderDownloadXML($data);
				$this->strXML = utf8_encode($this->strXML);
				$url = MODULE_PHREEBOOKS_ORDER_DOWNLOAD_URL;
				break;
			default:
				$messageStack->add('Invalid action requested in PhreeBooks interface class. Aborting!', 'error');
				return false;
		}
//echo 'Zencart order array = '; print_r($data); echo '<br>';
//echo 'Submit XML string = <pre>' . $this->strXML . '</pre><br>';
		$this->response = $this->doCURLRequest('POST', $url . '?db=' . MODULE_PHREEBOOKS_ORDER_DOWNLOAD_DB, $this->strXML);
//echo 'XML response (at the ZenCart Side from PhreeBooks) => <pre>' . htmlspecialchars($this->response) . '</pre><br>' . chr(10);
		if (!$this->response) return false;
		if (!$this->parse($this->response)) return false;  // parse the response string, check for errors
//echo 'Parsed string = '; print_r($this->arrOutput); echo '<br>';
		$this->result = $this->getNodeData(array('ACCESSRESPONSE','RESULT'),     $this->arrOutput);
		$this->code   = $this->getNodeData(array('ACCESSRESPONSE','RESULTCODE'), $this->arrOutput);
		$this->text   = $this->getNodeData(array('ACCESSRESPONSE','RESULTTEXT'), $this->arrOutput);
		if ($this->code == 0) {
		  if (defined('MODULE_PHREEBOOKS_ORDER_DOWNLOAD_ORDER_STATUS') && MODULE_PHREEBOOKS_ORDER_DOWNLOAD_ORDER_STATUS && $data->id) {
		    // insert a new status in the order status table
		    $db->Execute("insert into " . TABLE_ORDERS_STATUS_HISTORY . " set 
			  orders_id = " . (int)$data->id . ", 
			  orders_status_id = " . MODULE_PHREEBOOKS_ORDER_DOWNLOAD_ORDER_STATUS . ", 
			  date_added = now(), 
			  customer_notified = '0', 
			  comments = '" . 'Order is in process.' . "'");
		    // update the status in the orders table
		    $db->Execute("update " . TABLE_ORDERS . " set 
			  orders_status = " . MODULE_PHREEBOOKS_ORDER_DOWNLOAD_ORDER_STATUS . ",
			  last_modified = now() 
			  where orders_id = " . (int)$data->id);
		  }
		  $messageStack->add($this->text, strtolower($this->result));
		  return true;
		} else {
		  $messageStack->add('Error # ' . $this->code . ' - ' . $this->text, strtolower($this->result));
		  return false;
		}
	}

	function doCURLRequest($method = 'GET', $url, $vars) {
		global $messageStack;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10); // times out after 10 seconds 
//		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		if (strtoupper($method) == 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
		}
		if (CURL_PROXY_REQUIRED == 'True') {
			curl_setopt ($ch, CURLOPT_HTTPPROXYTUNNEL, true);
			curl_setopt ($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
			curl_setopt ($ch, CURLOPT_PROXY, CURL_PROXY_SERVER_DETAILS);
		}
		$data = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);
		if ($data != '') {
			return $data;
		} else {
			$messageStack->add('ZenCart Interface cURL error: ' . $error, 'error');
			return false; 
		}
	}

	function buildOrderDownloadXML($data) { // builds download XML string for orders
		// clean up some fields
		$temp = explode(' ', $data->info['date_purchased']);
		$order_date = $temp[0]; // remove the time from the order date stamp
		$this->strXML = '<?xml version="1.0" encoding="UTF-8" ?>' . chr(10);
		$this->strXML .= '<AccessRequest>' . chr(10);
		$this->strXML .= '<AccessUserID>' . MODULE_PHREEBOOKS_ORDER_DOWNLOAD_USER . '</AccessUserID>' . chr(10);
		$this->strXML .= '<AccessPassword>' . MODULE_PHREEBOOKS_ORDER_DOWNLOAD_PW . '</AccessPassword>' . chr(10);
		$this->strXML .= '<AccessFunction>' . $type . '</AccessFunction>' . chr(10);
		$this->strXML .= '<PhreeBooksXMLVersion>1.00</PhreeBooksXMLVersion>' . chr(10);
		$this->strXML .= '<SalesOrderEntry>' . chr(10);
		$this->strXML .= '<OrderRequest>' . chr(10);
		$this->strXML .= '<RequestAction>New</RequestAction>' . chr(10);
		$this->strXML .= '<ReferenceName>' . $data->info['ip_address'] . '</ReferenceName>' . chr(10);
		$this->strXML .= '<Originator>' . chr(10);
		$this->strXML .= '<StoreID>' . MODULE_PHREEBOOKS_ORDER_DOWNLOAD_STORE_ID . '</StoreID>' . chr(10);
		$this->strXML .= '<SalesRepID>' . MODULE_PHREEBOOKS_ORDER_DOWNLOAD_SALES_REP_ID . '</SalesRepID>' . chr(10);
		$this->strXML .= '<SalesGLAccount>' . MODULE_PHREEBOOKS_ORDER_DOWNLOAD_SALES_GL_ACCOUNT . '</SalesGLAccount>' . chr(10);
		$this->strXML .= '<ReceivablesGLAccount>' . MODULE_PHREEBOOKS_ORDER_DOWNLOAD_AR_GL_ACCOUNT . '</ReceivablesGLAccount>' . chr(10);
		$this->strXML .= '</Originator>' . chr(10);
		$this->strXML .= '</OrderRequest>' . chr(10);
		$this->strXML .= '<SalesOrder>' . chr(10);
		$this->strXML .= '<OrderSummary>' . chr(10);
		$this->strXML .= '<SalesOrderID>' . MODULE_PHREEBOOKS_ORDER_DOWNLOAD_PREFIX . $data->id . '</SalesOrderID>' . chr(10);
		$this->strXML .= '<SalesOrderDate>' . $order_date . '</SalesOrderDate>' . chr(10);
		$this->strXML .= '<OrderTotal>' . $data->info['total'] . '</OrderTotal>' . chr(10);
		$this->strXML .= '<TaxTotal>' . $data->info['tax'] . '</TaxTotal>' . chr(10);
		$freight_totals = $this->getClassInfo('ot_shipping', $data->totals);
		$this->strXML .= '<ShippingTotal>' . $this->clean_value($freight_totals['text'], $data->info['currency']) . '</ShippingTotal>' . chr(10);
		$this->strXML .= '<ShippingCarrier>' . $freight_totals['title'] . '</ShippingCarrier>' . chr(10);
		$this->strXML .= '<ShippingMethod>' . $freight_totals['title'] . '</ShippingMethod>' . chr(10);
// TBD also need to include discounts, fees and other order total modules information
//  	$this->strXML .= '<OrderNotes>' . $data->info['ip_address'] . '</OrderNotes>' . chr(10);
		$this->strXML .= '</OrderSummary>' . chr(10);
		$this->strXML .= '<Payment>' . chr(10);
		$this->strXML .= '<CardHolderName>' . $data->billing['name'] . '</CardHolderName>' . chr(10);
		$this->strXML .= '<Method>' . $data->info['payment_method'] . '</Method>' . chr(10);
		$this->strXML .= '<Type>' . $data->info['cc_type'] . '</Type>' . chr(10);
		$this->strXML .= '<CardNumber>' . $data->info['cc_number'] . '</CardNumber>' . chr(10);
		$this->strXML .= '<ExpirationDate>' . $data->info['cc_expires'] . '</ExpirationDate>' . chr(10);
		$this->strXML .= '<CVV2Number>' . $data->info['cc_cvv'] . '</CVV2Number>' . chr(10);

		if (function_exists('zc_set_hint')) {
			$this->strXML .= '<Hint>' . zc_set_hint($data->id) . '</Hint>' . chr(10);
		}
		if (function_exists('zc_set_value')) {
			$temp = strtr(base64_encode(zc_set_value($data->id)), '+/=', '-_,');
			$this->strXML .= '<Encval>' . $temp . '</Encval>' . chr(10);
		}

		$this->strXML .= '</Payment>' . chr(10);
		$this->strXML .= '<Customer>' . chr(10);
		switch (PHREEBOOKS_DOWNLOAD_USER_ID_METHOD) {
			case 'Telephone':
				$customer_id = ereg_replace("[^0-9]", "", $data->customer['telephone']);
				break;
			case 'Email':
			default:
				$customer_id = $data->customer['email_address'];
		}
		$this->strXML .= '<CustomerID>' . $customer_id . '</CustomerID>' . chr(10);
		$this->strXML .= '<CompanyName>' . $data->customer['company'] . '</CompanyName>' . chr(10);
		$this->strXML .= '<Contact>' . $data->customer['name'] . '</Contact>' . chr(10);
		$this->strXML .= '<Telephone>' . $data->customer['telephone'] . '</Telephone>' . chr(10);
		$this->strXML .= '<Email>' . $data->customer['email_address'] . '</Email>' . chr(10);
		$this->strXML .= '<Address1>' . $data->customer['street_address'] . '</Address1>' . chr(10);
		$this->strXML .= '<Address2>' . $data->customer['suburb'] . '</Address2>' . chr(10);
		$this->strXML .= '<CityTown>' . $data->customer['city'] . '</CityTown>' . chr(10);
		$codes = $this->getCodes($data->customer['country'], $data->customer['state']);
		$this->strXML .= '<StateProvince>' . $codes['state'] . '</StateProvince>' . chr(10);
		$this->strXML .= '<PostalCode>' . $data->customer['postcode'] . '</PostalCode>' . chr(10);
		$this->strXML .= '<CountryCode>' . $codes['country'] . '</CountryCode>' . chr(10);
		$this->strXML .= '</Customer>' . chr(10);
		$this->strXML .= '<Billing>' . chr(10);
		$this->strXML .= '<CompanyName>' . $data->billing['company'] . '</CompanyName>' . chr(10);
		$this->strXML .= '<Contact>' . $data->billing['name'] . '</Contact>' . chr(10);
		$this->strXML .= '<Address1>' . $data->billing['street_address'] . '</Address1>' . chr(10);
		$this->strXML .= '<Address2>' . $data->billing['suburb'] . '</Address2>' . chr(10);
		$this->strXML .= '<CityTown>' . $data->billing['city'] . '</CityTown>' . chr(10);
		$codes = $this->getCodes($data->billing['country'], $data->billing['state']);
		$this->strXML .= '<StateProvince>' . $codes['state'] . '</StateProvince>' . chr(10);
		$this->strXML .= '<PostalCode>' . $data->billing['postcode'] . '</PostalCode>' . chr(10);
		$this->strXML .= '<CountryCode>' . $codes['country'] . '</CountryCode>' . chr(10);
		$this->strXML .= '</Billing>' . chr(10);
		$this->strXML .= '<Shipping>' . chr(10);
		$this->strXML .= '<CompanyName>' . $data->delivery['company'] . '</CompanyName>' . chr(10);
		$this->strXML .= '<Contact>' . $data->delivery['name'] . '</Contact>' . chr(10);
		$this->strXML .= '<Address1>' . $data->delivery['street_address'] . '</Address1>' . chr(10);
		$this->strXML .= '<Address2>' . $data->delivery['suburb'] . '</Address2>' . chr(10);
		$this->strXML .= '<CityTown>' . $data->delivery['city'] . '</CityTown>' . chr(10);
		$codes = $this->getCodes($data->delivery['country'], $data->delivery['state']);
		$this->strXML .= '<StateProvince>' . $codes['state'] . '</StateProvince>' . chr(10);
		$this->strXML .= '<PostalCode>' . $data->delivery['postcode'] . '</PostalCode>' . chr(10);
		$this->strXML .= '<CountryCode>' . $codes['country'] . '</CountryCode>' . chr(10);
		$this->strXML .= '</Shipping>' . chr(10);
		$this->strXML .= '<LineItems>' . chr(10);
		foreach($data->products as $item) {
			$this->strXML .= '<LineItemDetails>' . chr(10);
			$this->strXML .= '<ItemID>' . $this->find_sku($item['id'], $item['name']) . '</ItemID>' . chr(10);
			$this->strXML .= '<Description>' . $item['name'] . '</Description>' . chr(10);
			$this->strXML .= '<Quantity>' . $item['qty'] . '</Quantity>' . chr(10);
			$this->strXML .= '<UnitPrice>' . $item['price'] . '</UnitPrice>' . chr(10);
//			$this->strXML .= '<SalesTax>' . $item['tax'] . '</SalesTax>' . chr(10);
			$this->strXML .= '<SalesTaxPercent>' . $item['tax'] . '</SalesTaxPercent>' . chr(10);
			$this->strXML .= '<TotalPrice>' . ($item['qty'] * $item['price']) . '</TotalPrice>' . chr(10);
			$this->strXML .= '</LineItemDetails>' . chr(10);
		}
		$this->strXML .= '</LineItems>' . chr(10);
		$this->strXML .= '</SalesOrder>' . chr(10);
		$this->strXML .= '</SalesOrderEntry>' . chr(10);
		$this->strXML .= '</AccessRequest>' . chr(10);
		return true;
	}

// Misc function to format XML string properly
	function getCodes($country, $zone) {
		global $db;
		$codes = array();
		$country = $db->Execute("select countries_id, countries_iso_code_2 from " . TABLE_COUNTRIES . "
			where countries_name = '" . $country . "'");
		if ($country->RecordCount() < 1) { // not found, return original choices
		  $codes['country'] = $country_name;
		  $codes['state'] = $zone;
		  return $codes;
		}
		$codes['country'] = $country->fields['countries_iso_code_2'];
		$state = $db->Execute("select zone_code from " . TABLE_ZONES . "
			where zone_country_id = '" . $country->fields['countries_id'] . "' and zone_name = '" . $zone . "'");
		if ($state->RecordCount() < 1) {
		  $codes['state'] = $zone;
		} else {
		  $codes['state'] = $state->fields['zone_code'];
		}
		return $codes;
	}

	// finds the details of the order_total modules for a given class name
	function getClassInfo($className, $searchArray) {
		for ($i = 0; $i < count($searchArray); $i++) {
			if ($searchArray[$i]['class'] == $className) {
				return array('class' => $className, 'title' => $searchArray[$i]['title'], 'text' => $searchArray[$i]['text']);
			}
		}
		return false; // not found
	}

	function clean_value($number, $currency_type = DEFAULT_CURRENCY) {
		global $currencies;
	    // converts the number to standard float format (period as decimal, no thousands separator)
	    $temp  = str_replace($currencies->currencies[$currency_type]['thousands_point'], '', trim($number));
	    $value = str_replace($currencies->currencies[$currency_type]['decimal_point'], '.', $temp);
	    $value = ereg_replace("[^-0-9.]", "", $value);
	    return $value;
	}

	function find_sku($id, $name) {
		global $db;
		$result = $db->Execute("select phreebooks_sku from " . TABLE_PRODUCTS . " where products_id = '" . $id . "'");
		return ($result->fields['phreebooks_sku'] <> '') ? $result->fields['phreebooks_sku'] : $name;
	}
}
?>