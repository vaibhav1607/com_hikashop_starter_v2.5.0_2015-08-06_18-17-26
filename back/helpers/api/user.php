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
class hikashopApiuserHelper {

	public function processRequest(&$helper, $url, $params, $data) {
		switch($url) {
			case '/user':
				return false;
			case '/user/auth':
				return $this->authentication($helper, $url, $params, $data);
			case '/user/create':
				return false;
			case '/user/update':
				return false;
			case '/user/require':
				return false;
		}
		return false;
	}


	protected function authentication(&$helper, $url, $params, $data) {
		return $helper->auth($data);
	}
}
