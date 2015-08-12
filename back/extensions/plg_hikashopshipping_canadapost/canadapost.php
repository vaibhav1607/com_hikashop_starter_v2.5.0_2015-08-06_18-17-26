<?php
/**
 * @package	HikaShop for Joomla!
 * @version	2.5.0
 * @author	hikashop.com
 * @copyright	(C) 2010-2015 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><?php

class plgHikashopshippingCANADAPOST extends hikashopShippingPlugin {
	var $canadapost_methods = array(
		array('key' => 1, 'name' => 'Priority Courier', 'countries' => 'CANADA'),
		array('key' => 2, 'name' => 'Xpresspost', 'countries' => 'CANADA'),
		array('key' => 3, 'name' => 'Regular', 'countries' => 'CANADA'),
		array('key' => 4, 'name' => 'Priority Worldwide USA', 'countries' => 'USA'),
		array('key' => 5, 'name' => 'Xpresspost USA', 'countries' => 'USA'),
		array('key' => 6, 'name' => 'Small Packets Air', 'countries' => 'USA'),
		array('key' => 7, 'name' => 'Small Packets Surface', 'countries' => 'USA'),
		array('key' => 8, 'name' => 'Priority Worldwide INTL', 'countries' => 'ALL'),
		array('key' => 9, 'name' => 'XPressPost International', 'countries' => 'ALL'),
		array('key' => 10, 'name' => 'Small Packets Air', 'countries' => 'ALL'),
		array('key' => 11, 'name' => 'Parcel Surface', 'countries' => 'ALL'),
		array('key' => 12, 'name' => 'Small Packets Surface', 'countries' => 'ALL'),
		array('key' => 13, 'name' => 'Expedited', 'countries' => 'ALL'),
	);
	var $convertUnit = array('kg' => 'KGS', 'lb' => 'LBS', 'cm' => 'CM', 'in' => 'IN', 'kg2' => 'kg', 'lb2' => 'lb', 'cm2' => 'cm', 'in2' => 'in', );
	var $limits = array(
			'CA' => array(
				'default' => array('x' => 2, 'y' => 2, 'z' => 2, 'length_girth' => 3, 'w' => 30)
			),
			'US' => array(
				'Priority Worldwide USA' => 'default',
				'Expedited' => array('x' => 2, 'y' => 2, 'z' => 2, 'length_girth' => 2.74, 'w' => 30),
				'Xpresspost USA' => array('x' => 1.5, 'y' => 1.5, 'z' => 1.5, 'length_girth' => 2.74, 'w' => 30),
				'Small Packets Air' => array('x' => 0.6, 'y' => 0.6, 'z' => 0.6, 'length_width_height' => 0.9, 'w' => 1),
				'default' => array('x' => 2, 'y' => 2, 'z' => 2, 'length_girth' => 3, 'w' => 30)
			),
			'default' => array(
				'default' => array('x' => 2, 'y' => 2, 'z' => 2, 'length_girth' => 3, 'w' => 30),
				'medium' => array('x' => 1.5, 'y' => 1.5, 'z' => 1.5, 'length_girth' => 3, 'w' => 30),
				'small' => array('x' => 0.6, 'y' => 0.6, 'z' => 0.6, 'length_width_height' => 0.9, 'w' => 2),
				'Priority Worldwide INTL' => 'default',
				'Expedited' => 'default',
				'XPressPost International' => 'medium',
				'Parcel Surface' => 'medium',
				'Small Packets Air' => 'small',
				'Small Packets Surface' => 'small',
			),
		);

	public $nbpackage = 0;

	var $multiple = true;
	var $name = 'canadapost';
	var $doc_form = 'canadapost';


	function shippingMethods(&$main) {
		$methods = array();
		if (!empty($main->shipping_params->methodsList)) {
			$main->shipping_params->methods = unserialize($main->shipping_params->methodsList);
		}
		if (!empty($main->shipping_params->methods)) {
			foreach ($main->shipping_params->methods as $key => $value) {
				$selected = null;
				foreach ($this->canadapost_methods as $canadapost) {
					if ($canadapost['name'] == $key)
						$selected = $canadapost;
				}
				if ($selected) {
					$methods[$main->shipping_id . '-' . $selected['key']] = $selected['name'];
				}
			}
		}
		return $methods;
	}

	function onShippingDisplay(&$order, &$dbrates, &$usable_rates, &$messages) {
			if(!hikashop_loadUser())
				return false;
			$local_usable_rates = array();
			$local_messages = array();
			$ret = parent::onShippingDisplay($order, $dbrates, $local_usable_rates, $local_messages);
			if($ret === false)
				return false;

			$currentShippingZone = null;
			$currentCurrencyId = null;
			$found = true;
			$usableWarehouses = array();
			$zoneClass = hikashop_get('class.zone');
			$zones = $zoneClass->getOrderZones($order);
			if (!function_exists('curl_init')) {
				$app = JFactory::getApplication();
				$app->enqueueMessage('The CANADAPOST shipping plugin needs the CURL library installed but it seems that it is not available on your server. Please contact your web hosting to set it up.', 'error');
				return false;
			}
			foreach ($local_usable_rates as $k => $rate) {
				if (!empty($rate->shipping_params->warehousesList)) {
					$rate->shipping_params->warehouses = unserialize($rate->shipping_params->warehousesList);
				} else {
					$messages['no_warehouse_configured'] = 'No warehouse configured in the CANADA POST shipping plugin options';
					continue;
				}
				foreach ($rate->shipping_params->warehouses as $warehouse) {
					if ((empty($warehouse->zone) && $warehouse->zip != '-') || (!empty($warehouse->zone) && in_array($warehouse->zone, $zones) && $warehouse->zip != '-')) {
						$usableWarehouses[] = $warehouse;
					}
				}
				if (empty($usableWarehouses)) {
					$messages['no_warehouse_configured'] = 'No available warehouse found for your location';
					continue;
				}
				if (!empty($rate->shipping_params->methodsList)) {
					$rate->shipping_params->methods = unserialize($rate->shipping_params->methodsList);
				} else {
					$messages['no_shipping_methods_configured'] = 'No shipping methods configured in the CANADA POST shipping plugin options';
					continue;
				}
				if ($order->weight <= 0 || $order->volume <= 0) {
					return true;
				}
				$data = null;
				if (empty($order->shipping_address)) {
					$messages['no_shipping_address_found'] = 'No shipping address entered';
					continue;
				}

				$this->shipping_currency_id = hikashop_getCurrency();
				$db = JFactory::getDBO();
				$query = 'SELECT currency_code FROM ' . hikashop_table('currency') . ' WHERE currency_id IN (' . $this->shipping_currency_id . ')';
				$db->setQuery($query);
				$this->shipping_currency_code = $db->loadResult();
				$cart = hikashop_get('class.cart');
				$null = null;
				$cart->loadAddress($null, $order->shipping_address->address_id, 'object', 'shipping');
				$currency = hikashop_get('class.currency');

				$receivedMethods = $this->_getBestMethods($rate, $order, $usableWarehouses, $null);
				if (empty($receivedMethods)) {
					$messages['no_rates'] = JText::_('NO_SHIPPING_METHOD_FOUND');
					continue;
				}
				$i = 0;
				$local_usable_rates = array();
				foreach ($receivedMethods as $method) {
					$local_usable_rates[$i] = (!HIKASHOP_PHP5) ? $rate : clone($rate);
					$local_usable_rates[$i]->shipping_price += $method['value'];
					$selected_method = '';
					$name = '';
					foreach ($this->canadapost_methods as $canadapost_method) {
						if ($canadapost_method['name'] == $method['name']) {
							$name = $canadapost_method['name'];
							$selected_method = $canadapost_method['key'];
						}
					}
					$local_usable_rates[$i]->shipping_name = $name;
					if(!empty($selected_method))
						$local_usable_rates[$i]->shipping_id .= '-' . $selected_method;

					if ($method['deliveryDate'] != 'www.canadapost.ca') {
						if (is_numeric($method['deliveryDate'])) {
							$timestamp = strtotime($method['deliveryDate']);
							$time = parent::displayDelaySECtoDAY($timestamp - strtotime('now'), 2);
							$local_usable_rates[$i]->shipping_description .= 'Estimated delivery date:  ' . $time;
						} else {
							$time = $method['deliveryDate'];
							$local_usable_rates[$i]->shipping_description .= 'Estimated delivery date:  ' . $time;
						}

					} else {
						$local_usable_rates[$i]->shipping_description .= ' ' . JText::_('NO_ESTIMATED_TIME_AFTER_SEND');
					}
					if ($rate->shipping_params->group_package == 1 && $this->nbpackage > 1)
						$local_usable_rates[$i]->shipping_description .= '<br/>' . JText::sprintf('X_PACKAGES', $this->nbpackage);
					$i++;
				}
				foreach ($local_usable_rates as $i => $rate) {
					$usable_rates[$rate->shipping_id] = $rate;
				}
			}
		}
	function getShippingDefaultValues(&$element){
		$element->shipping_name = 'CANADA POST';
		$element->shipping_description = '';
		$element->group_package = 0;
		$element->shipping_images = 'canadapost';
		$element->shipping_params->post_code = '';
		$element->shipping_currency_id = $this->main_currency;
	}
	function onShippingConfiguration(&$element) {
		$config = &hikashop_config();
		$app = JFactory::getApplication();
		$this->main_currency = $config->get('main_currency', 1);
		$currencyClass = hikashop_get('class.currency');
		$currency = hikashop_get('class.currency');
		$this->currencyCode = $currency->get($this->main_currency)->currency_code;
		$this->currencySymbol = $currency->get($this->main_currency)->currency_symbol;

		$this->canadapost = JRequest::getCmd('name', 'canadapost');
		$this->categoryType = hikashop_get('type.categorysub');
		$this->categoryType->type = 'tax';
		$this->categoryType->field = 'category_id';

		parent::onShippingConfiguration($element);
		$elements = array($element);
		$key = key($elements);
		if (!empty($elements[$key]->shipping_params->warehousesList)) {
			$elements[$key]->shipping_params->warehouse = unserialize($elements[$key]->shipping_params->warehousesList);
		}
		if (!empty($elements[$key]->shipping_params->methodsList)) {
			$elements[$key]->shipping_params->methods = unserialize($elements[$key]->shipping_params->methodsList);
		}
		if(empty($elements[$key]->shipping_params->merchant_ID)){
			$app->enqueueMessage(JText::sprintf('ENTER_INFO', 'Canada POST', JText::_('ATOS_MERCHANT_ID')));
		}
		if(empty($elements[$key]->shipping_params->warehouse[0]->zip)){
			$app->enqueueMessage(JText::sprintf('PLEASE_FILL_THE_FIELD',JText::_('POST_CODE')),'notice');
		}

		$js = '
		function deleteRow(divName,inputName,rowName){
			var d = document.getElementById(divName);
			var olddiv = document.getElementById(inputName);
			if(d && olddiv){
				d.removeChild(olddiv);
				document.getElementById(rowName).style.display=\'none\';
			}
			return false;
		}
		function deleteZone(zoneName){
			var d = document.getElementById(zoneName);
			if(d){
				d.innerHTML="";
			}
			return false;
		}
		';
	 	$js.="
			function checkAllBox(id, type){
				var toCheck = document.getElementById(id).getElementsByTagName('input');
				for (i = 0 ; i < toCheck.length ; i++) {
					if (toCheck[i].type == 'checkbox') {
						if(type == 'check'){
							toCheck[i].checked = true;
						}else{
							toCheck[i].checked = false;
						}
					}
				}
			}";

		if(!HIKASHOP_PHP5) {
			$doc =& JFactory::getDocument();
		} else {
			$doc = JFactory::getDocument();
		}
		$doc->addScriptDeclaration( "<!--\n".$js."\n//-->\n" );
	}

	function onShippingConfigurationSave(&$element) {

		$warehouses = JRequest::getVar('warehouse', array(), '', 'array');
		$cats = array();
		$methods = array();
		$db = JFactory::getDBO();
		$zone_keys = '';
		$app = JFactory::getApplication();

		if (isset($_REQUEST['data']['shipping_methods'])) {
			foreach ($_REQUEST['data']['shipping_methods'] as $method) {
				foreach ($this->canadapost_methods as $canadapostMethod) {
					$name = $canadapostMethod['name'];
					if ($name == $method['name']) {
						$obj = new stdClass();
						$methods[strip_tags($method['name'])] = '';
					}
				}
			}
		}else{
			$app->enqueueMessage(JText::sprintf('CHOOSE_SHIPPING_SERVICE'));
		}
		$element->shipping_params->methodsList = serialize($methods);

		if (!empty($warehouses)) {
			foreach ($warehouses as $id => $warehouse) {
				if (!empty($warehouse['zone']))
					$zone_keys .= 'zone_namekey=' . $db->Quote($warehouse['zone']) . ' OR ';
			}
			$zone_keys = substr($zone_keys, 0, -4);
			if (!empty($zone_keys)) {
				$query = ' SELECT zone_namekey, zone_id, zone_name_english FROM ' . hikashop_table('zone') . ' WHERE ' . $zone_keys;
				$db->setQuery($query);
				$zones = $db->loadObjectList();
			}
			foreach ($warehouses as $id => $warehouse) {
				$warehouse['zone_name'] = '';
				if (!empty($zones)) {
					foreach ($zones as $zone) {
						if ($zone->zone_namekey == $warehouse['zone'])
							$warehouse['zone_name'] = $zone->zone_id . ' ' . $zone->zone_name_english;
					}
				}

				if (@$_REQUEST['warehouse'][$id]['zip'] != '-' && @$_REQUEST['warehouse'][$id]['zip'] != '' && !empty($_REQUEST['warehouse'][$id]['zip'])) {
					$obj = new stdClass();
					$obj->name = strip_tags($_REQUEST['warehouse'][$id]['name']);
					$obj->zip = strip_tags($_REQUEST['warehouse'][$id]['zip']);
					$obj->zone = @strip_tags($_REQUEST['warehouse'][$id]['zone']);
					$obj->zone_name = $warehouse['zone_name'];
					$obj->units = strip_tags($_REQUEST['warehouse'][$id]['units']);
					$cats[] = $obj;
				}
			}
			$element->shipping_params->warehousesList = serialize($cats);
		}
		if (empty($cats)) {
			$obj = new stdClass();
			$obj->name = '';
			$obj->zip = '';
			$obj->zone = '';
			$void[] = $obj;
			$element->shipping_params->warehousesList = serialize($void);
		}
		return true;
	}

	function _getBestMethods(&$rate, &$order, &$usableWarehouses, $null) {
		$app = JFactory::getApplication();
		$db = JFactory::getDBO();
		$usableMethods = array();
		$query = 'SELECT zone_id, zone_code_2 FROM ' . hikashop_table('zone') . ' WHERE zone_id = 38';
		$db->setQuery($query);
		$warehouses_namekey = $db->loadObjectList();
		foreach ($usableWarehouses as $warehouse) {
			foreach ($warehouses_namekey as $zone) {
				if ($zone->zone_id == 38) {
					$warehouse->country_ID = $zone->zone_code_2;
				}
			}
		}
		foreach ($usableWarehouses as $k => $warehouse) {
			$usableWarehouses[$k]->methods = $this->_getShippingMethods($rate, $order, $warehouse, $null);
		}
		if (empty($usableWarehouses))
			return false;

		$method_available = '';

		foreach ($usableWarehouses as $k => $warehouse) {
			if (!empty($warehouse->methods)) {
				$j = 0;
				foreach ($rate->shipping_params->methods as $shipping_method => $empty) {
					$method_available[$j] = $shipping_method;
					$j++;
				}
				foreach ($warehouse->methods as $i => $method) {
					if (!in_array($method['name'], $method_available))
						unset($usableWarehouses[$k]->methods[$i]);
				}
			}
		}
		$bestPrice = 99999999;

		foreach ($usableWarehouses as $id => $warehouse) {
			if (!empty($warehouse->methods)) {
				foreach ($warehouse->methods as $method) {
					if ($method['value'] < $bestPrice) {
						$bestPrice = $method['value'];
						$bestWarehouse = $id;
					}
				}
			}
		}
		if (isset($bestWarehouse)) {
			return $usableWarehouses[$bestWarehouse]->methods;
		} else {
			$app->enqueueMessage('There is no warehouse usable for that location');
			return false;
		}
	}

	function _getShippingMethods(&$rate, &$order, &$warehouse, $null) {
		$data = array();
		$data['merchant_ID'] = $rate->shipping_params->merchant_ID;
		$data['turnaround_time'] = $rate->shipping_params->turnaround_time;
		$data['destCity'] = $null->shipping_address->address_city;
		$data['destState'] = $null->shipping_address->address_state;
		$data['destZip'] = $null->shipping_address->address_post_code;
		$data['destCountry'] = $null->shipping_address->address_country->zone_code_2;
		$data['units'] = $warehouse->units;
		$data['zip'] = $warehouse->zip;
		$data['XMLpackage'] = '';
		$data['destType'] = '';
		$data['XMLpackage'] = '
					<?xml version="1.0" ?>
					<eparcel>
					<language>EN</language>
					<ratesAndServicesRequest>
					<merchantCPCID> ' . $data['merchant_ID'] . ' </merchantCPCID>
					<fromPostalCode> ' . $data['zip'] . ' </fromPostalCode>
				';
		$data['weight'] = 0;
		$data['height'] = 0;
		$data['length'] = 0;
		$data['width'] = 0;
		$data['price'] = 0;
		$data['quantity'] = 0;
		$data['name'] = '';
		if (!empty($rate->shipping_params->turnaround_time))
			$data['XMLpackage'] .= '<turnAroundTime> ' . $data['turnaround_time'] . ' </turnAroundTime>';
		$data['XMLpackage'] .= ' <lineItems> ';
		$totalPrice = 0;

		if(isset($this->limits[ $null->shipping_address->address_country->zone_code_2 ]))
			$zone_limit = $this->limits[ $null->shipping_address->address_country->zone_code_2 ];
		else
			$zone_limit = $this->limits['default'];

		$limit = null;
		foreach($zone_limit as $key => $value) {
			if($limit === null && isset($rate->shipping_params->methods[$key]))
				$limit = $value;
		}
		if(is_string($limit)) {
			if(strpos($limit, ':') === false) {
				$limit = $zone_limit[ $limit ];
			} else {
				list($zone, $key) = explode(':', $limit, 2);
				$limit = $this->limits[$zone][$key];
			}
		}
		if($limit === null)
			$limit = $zone_limit['default'];

		if (!$rate->shipping_params->group_package || $rate->shipping_params->group_package == 0) {
			$limit['unit'] = 1;

			$packages = $this->getOrderPackage($order, array('weight_unit' => 'kg', 'volume_unit' => 'm', 'limit' => $limit, 'required_dimensions' => array('w','x','y','z')));

			if(empty($packages))
				return true;

			if(isset($packages['w']) || isset($packages['x']) || isset($packages['y']) || isset($packages['z'])){
				$data['weight'] = $packages['w'];
				$data['height'] = $packages['z'];
				$data['length'] = $packages['y'];
				$data['width'] = $packages['x'];
				$data['quantity'] = 1;
			}else{
				foreach($packages as $package){
					$data['weight'] = $package['w'];
					$data['height'] = $package['z'];
					$data['length'] = $package['y'];
					$data['width'] = $package['x'];
					$data['quantity'] = 1;
				}
			}
			$data['XMLpackage'] .= $this->_createPackage($data, $rate, $order);

			$data['XMLpackage'] .= '</lineItems>';
			$data['XMLpackage'] .= '<city> ' . $data['destCity'] . ' </city>';
			$data['XMLpackage'] .= '<provOrState> ' . $data['destState']->zone_name . ' </provOrState>';
			$data['XMLpackage'] .= '<country> ' . $data['destCountry'] . ' </country>
							<postalCode>' . $data['destZip'] . '</postalCode>
							';
			$data['XMLpackage'] .= '</ratesAndServicesRequest>
												</eparcel> ';
			$usableMethods = $this->_RequestMethods($data, $data['XMLpackage']);
			return $usableMethods;

		} else {
			$data['name'] = 'grouped package';
			$this->package_added = 0;
			$this->nbpackage = 0;

			$packages = $this->getOrderPackage($order, array('weight_unit' => 'kg', 'volume_unit' => 'm', 'limit' => $limit, 'required_dimensions' => array('w','x','y','z')));

			if(empty($packages))
				return true;

			if(isset($packages['w']) || isset($packages['x']) || isset($packages['y']) || isset($packages['z'])){
				$this->nbpackage++;
				$data['weight'] = $packages['w'];
				$data['height'] = $packages['z'];
				$data['length'] = $packages['y'];
				$data['width'] = $packages['x'];
				$data['quantity'] = $this->nbpackage;
			}else{
				foreach($packages as $package){
					$this->nbpackage++;
					$data['weight'] = $package['w'];
					$data['height'] = $package['z'];
					$data['length'] = $package['y'];
					$data['width'] = $package['x'];
					$data['quantity'] = $this->nbpackage;
				}
			}
			$data['XMLpackage'] .= $this->_createPackage($data, $rate, $order);


			$data['XMLpackage'] .= '</lineItems>';
			$data['XMLpackage'] .= '<city> ' . $data['destCity'] . ' </city>';
			$data['XMLpackage'] .= '<provOrState> ' . $data['destState']->zone_name . ' </provOrState>';
			$data['XMLpackage'] .= '<country> ' . $data['destCountry'] . ' </country>
						<postalCode>' . $data['destZip'] . '</postalCode>
						';
			$data['XMLpackage'] .= '</ratesAndServicesRequest>
											</eparcel> ';
			$usableMethods = $this->_RequestMethods($data, $data['XMLpackage']);
			return $usableMethods;
		}
	}

	function processPackageLimit($limit_key, $limit_value, $product, $qty, $package, $units) {
		switch ($limit_key) {
			case 'length_width_height':
				$divide = $product['x'] + $product['y'] + $product['z'];
				if(!$divide || $divide > $limit_value)
					return false;
				return (int)floor($limit_value / $divide);
				break;
			case 'length_girth':
				$divide = $product['z'] + ($product['x'] + $product['y']) * 2;
				if(!$divide || $divide > $limit_value)
					return false;
				return (int)floor($limit_value / $divide);
				break;
		}
		return parent::processPackageLimit($limit_key, $limit_value , $product, $qty, $package, $units);
	}

	function _createPackage(&$data, &$rate, &$order) {
		if (!empty($rate->shipping_params->weight_approximation)) {
			$data['weight'] = $data['weight'] + $data['weight'] * $rate->shipping_params->weight_approximation / 100;
		}
		if ($data['weight'] < 1) {
			$data['weight'] = 1;
		}
		if (!empty($rate->shipping_params->dim_approximation)) {
			$data['height'] = $data['height'] + $data['height'] * $rate->shipping_params->dim_approximation / 100;
			$data['length'] = $data['length'] + $data['length'] * $rate->shipping_params->dim_approximation / 100;
			$data['width'] = $data['width'] + $data['width'] * $rate->shipping_params->dim_approximation / 100;
		}
		$xml = '';
		$xml .= '	<item>
						 <quantity> ' . $data['quantity'] . ' </quantity>
						 <weight> ' . $data['weight'] . ' </weight>
						 <length> ' . $data['width'] . ' </length>
						 <width> ' . $data['length'] . ' </width>
						 <height> ' . $data['height'] . ' </height>
						 <description> ' . $data['name'] . ' </description>';
		if ($rate->shipping_params->readyToShip)
			$xml .= '<readyToShip/>';
		$xml .= '</item>';
		return $xml;
	}

	function _RequestMethods($data, $xml) {
		$app = JFactory::getApplication();
		$session = curl_init("cybervente.postescanada.ca");
		curl_setopt($session, CURLOPT_HEADER, 1);
		curl_setopt($session, CURLOPT_POST, 1);
		curl_setopt($session, CURLOPT_PORT, 30000);
		curl_setopt($session, CURLOPT_TIMEOUT, 30);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($session, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($session, CURLOPT_POSTFIELDS, $xml);
		$result = curl_exec($session);
		$error = curl_errno($session);
		$error_message = curl_error($session);
		curl_close($session);

		if($error) {
			$app->enqueueMessage('An error occurred. The connection to the Canada Post server could not be established: ' . $error_message);
			return false;
		}
		$xml_data = strstr($result, '<?');
		$xml = simplexml_load_string($xml_data);

		if (isset($xml->ratesAndServicesResponse->statusCode) && $xml->ratesAndServicesResponse->statusCode != 1) {
			$app->enqueueMessage('Error while sending XML to CANADA POST. Error code: ' . $xml->ratesAndServicesResponse->statusCode . '. Message: ' . $xml->ratesAndServicesResponse->statusMessage . '', 'error');
			return false;
		}
		$shipment = array();
		$i = 1;
		foreach($xml->ratesAndServicesResponse->product as $rate){
			$shipment[$i]['value'] = $rate->rate->__toString();
			$shipment[$i]['name'] = $rate->name->__toString();
			$shipment[$i]['shippingDate'] = $rate->shippingDate->__toString();
			$shipment[$i]['deliveryDate'] = $rate->deliveryDate->__toString();
			$shipment[$i]['deliveryDayOfWeek'] = $rate->deliveryDayOfWeek->__toString();
			$shipment[$i]['nextDayAM'] = $rate->nextDayAM->__toString();
			$shipment[$i]['status_code'] = $xml->ratesAndServicesResponse->statusCode->__toString();
			$shipment[$i]['status_message'] = $xml->ratesAndServicesResponse->statusMessage->__toString();
			$i++;
		}
		return $shipment;
	}
}
