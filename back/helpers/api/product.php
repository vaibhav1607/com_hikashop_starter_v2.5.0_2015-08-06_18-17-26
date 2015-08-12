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
class hikashopApiproductHelper {

	private $whitelist = array(
		'product_id', 'product_name', 'product_description', 'product_quantity', 'product_code', 'product_quantity', 'product_msrp', 'product_tax_id',
		'product_url', 'product_keywords', 'product_meta_description', 'product_canonical', 'product_page_title', 'product_alias',
		'product_weight', 'product_weight_unit', 'product_dimension_unit', 'product_width', 'product_length', 'product_height',
	);

	public function processRequest(&$helper, $url, $params, $data) {
		switch($url) {
			case '/product/:id':
				return $this->getProduct($helper, $url, $params, $data);
				break;
			case '/products/:id':
				return $this->getProducts($helper, $url, $params, $data);
		}
		return false;
	}

	protected function getProduct(&$helper, $url, $params, $data) {
		$config = hikashop_config();
		$db = JFactory::getDBO();
		$productClass = hikashop_get('class.product');

		$user_id = hikashop_loadUser(false);
		$product_id = (int)$params['params']['id'];
		$ids = array($product_id);

		if(empty($product_id))
			return false;

		$filters = array(
			'product_id' => 'product.product_id = ' . $product_id,
			'product_published' => 'product.product_published = 1',
			'product_type' => 'product.product_type IN (' . $db->Quote('main') . ',' . $db->Quote('variant') . ')'
		);
		hikashop_addACLFilters($filters, 'product_access', 'product');

		$query = 'SELECT product.*'.
			' FROM '.hikashop_table('product').' AS product '.
			' WHERE ('.implode(') AND (', $filters). ') ';

		$db->setQuery($query, 0, 1);
		$product = $db->loadObject();
		if(empty($product))
			return;

		$selected_variant_id = 0;
		if($product->product_type == 'variant')
			return false;

		$menu = '';
		if(!empty($helper->itemid))
			$menu .= '&Itemid=' . (int)$helper->itemid;

		$update_product = new stdClass();
		$update_product->product_id = $product_id;
		$update_product->product_hit = (int)$product->product_hit + 1;
		$update_product->product_last_seen_date = time();
		$productClass->save($update_product, true);

		$query = 'SELECT category.* '.
			' FROM '.hikashop_table('product_category').' AS product_category '.
			' LEFT JOIN '.hikashop_table('category').' AS category ON category.category_id = product_category.category_id '.
			' WHERE product_category.product_id = ' . (int)$product_id .
			' ORDER BY product_category.product_category_id ASC';
		$db->setQuery($query);
		$categories = $db->loadObjectList();

		$fieldsClass = hikashop_get('class.field');
		$product->categories = $categories;
		$fields = $fieldsClass->getFields('frontcomp', $product, 'product', 'checkout&task=state');

		$whitelist = $this->whitelist;
		if(!empty($fields))
			$whitelist = array_merge($whitelist, array_keys($fields));
		foreach($product as $key => $v) {
			if(!in_array($key, $whitelist))
				unset($product->$key);
		}

		$filters = array(
			'product_related.product_id = '.$product_id,
			'product_related.product_related_type = '.$db->Quote('options'),
			'product.product_published = 1',
			'product.product_sale_start = 0 OR product.product_sale_start <= '.time(),
			'product.product_sale_end = 0 OR product.product_sale_end > '.time()
		);
		hikashop_addACLFilters($filters, 'product_access', 'product');
		$query = 'SELECT product.* '.
			' FROM '.hikashop_table('product_related').' AS product_related '.
			' LEFT JOIN '.hikashop_table('product').' AS product ON product_related.product_related_id = product.product_id '.
			' WHERE ('.implode(') AND (', $filters).') '.
			' ORDER BY product_related.product_related_ordering ASC, product_related.product_related_id ASC';
		$db->setQuery($query);
		$product->options = $db->loadObjectList('product_id');
		if(!empty($product->options)) {
			foreach($product->options as &$product_option) {
				foreach($product_option as $key => $v) {
					if(!in_array($key, $whitelist))
						unset($product->$key);
				}
			}
			unset($product_option);
			$ids = array_merge($ids, array_keys($product->options));
		}

		$filters = array(
			'product_parent_id IN (' . implode(',', $ids) . ')'
		);
		hikashop_addACLFilters($filters, 'product_access');
		$query = 'SELECT * FROM '.hikashop_table('product').' WHERE (' . implode(') AND (', $filters) . ')';
		$db->setQuery($query);
		$variants = $db->loadObjectList('product_id');
		$product->variants = array();
		if(!empty($variants)) {
			foreach($variants as $variant) {
				$variant_product_parent_id = (int)$variant->product_parent_id;
				foreach($variant as $key => $v) {
					if(!in_array($key, $whitelist))
						unset($variant->$key);
				}
				if($variant_product_parent_id == $product_id)
					$product->variants[$variant->product_id] = $variant;
				if(!empty($product->options) && isset($product->options[ $variant_product_parent_id ])) {
					if(empty($product->options[ $variant_product_parent_id ]->variants))
						$product->options[ $variant_product_parent_id ]->variants = array();
					$product->options[ $variant_product_parent_id ]->variants[$variant->product_id] = $variant;
				}
			}
			$ids = array_merge($ids, array_keys($variants));
		}

		$sort = $config->get('characteristics_values_sorting');
		$characteristic_sort = 'characteristic_value';
		if($sort == 'old') $characteristic_sort = 'characteristic_id';
		elseif($sort == 'alias') $characteristic_sort = 'characteristic_alias';
		elseif($sort == 'ordering') $characteristic_sort = 'characteristic_ordering';

		$query = 'SELECT variant.*, characteristic.* '.
			' FROM '.hikashop_table('variant').' AS variant '.
			' LEFT JOIN '.hikashop_table('characteristic').' AS characteristic ON variant.variant_characteristic_id = characteristic.characteristic_id '.
			' WHERE variant.variant_product_id IN (' . implode(',', $ids) . ') ORDER BY variant.ordering ASC, characteristic.'.$characteristic_sort.' ASC';
		$db->setQuery($query);
		$characteristics = $db->loadObjectList();

		if(!empty($characteristics)) {
			$mainCharacteristics = array();
			foreach($characteristics as $characteristic) {
				if($product_id == $characteristic->variant_product_id) {
					if(empty($mainCharacteristics[$product_id]))
						$mainCharacteristics[$product_id] = array();
					if(empty($mainCharacteristics[$product_id][$characteristic->characteristic_parent_id]))
						$mainCharacteristics[$product_id][$characteristic->characteristic_parent_id] = array();
					$mainCharacteristics[$product_id][$characteristic->characteristic_parent_id][$characteristic->characteristic_id] = $characteristic;
				}
				if(!empty($element->options) && isset($product->options[ (int)$characteristic->variant_product_id ])) {
					if(empty($mainCharacteristics[$optionElement->product_id]))
						$mainCharacteristics[$optionElement->product_id] = array();
					if(empty($mainCharacteristics[$optionElement->product_id][$characteristic->characteristic_parent_id]))
						$mainCharacteristics[$optionElement->product_id][$characteristic->characteristic_parent_id] = array();
					$mainCharacteristics[$optionElement->product_id][$characteristic->characteristic_parent_id][$characteristic->characteristic_id] = $characteristic;
				}
			}

			JPluginHelper::importPlugin('hikashop');
			$dispatcher = JDispatcher::getInstance();
			$dispatcher->trigger('onAfterProductCharacteristicsLoad', array(&$product, &$mainCharacteristics, &$characteristics) );

			if(!empty($product->variants)) {
				$this->addCharacteristics($product, $mainCharacteristics, $characteristics, $selected_variant_id);
			}

			if(!empty($product->options)) {
				foreach($product->options as $k => $optionElement) {
					if(!empty($optionElement->variants)) {
						$this->addCharacteristics($product->options[$k], $mainCharacteristics, $characteristics, $selected_variant_id);
					}
				}
			}
		}

		JArrayHelper::toInteger($ids);

		$imageHelper = hikashop_get('helper.image');
		$query = 'SELECT * FROM '.hikashop_table('file').' AS file WHERE '.
			' file.file_ref_id IN (' . implode(',', $ids) . ') AND file.file_type = '.$db->Quote('product').' '.
			' ORDER BY file.file_ordering ASC, file.file_id ASC';
		$db->setQuery($query);
		$images = $db->loadObjectList();
		$product->images = array();
		foreach($images as $image) {
			$img = $imageHelper->getThumbnail($image->file_path);
			$d = array(
				'name' => $image->file_name,
				'description' => $image->file_description,
				'path' => hikashop_cleanURL($img->origin_url),
				'thumb' => hikashop_cleanURL($img->url),
			);
			if(isset($product->variants[$image->file_ref_id])){
				$product->variants[$image->file_ref_id]->images[] = $d;
			}else{
				$product->images[] = $d;
			}
		}


		$query = 'SELECT * FROM '.hikashop_table('file').' AS file WHERE '.
			' file.file_ref_id IN ('.implode(',', $ids).') AND file.file_type = '.$db->Quote('file').' '.
			' ORDER BY file.file_ref_id ASC, file.file_ordering ASC, file.file_id ASC';
		$db->setQuery($query);
		$files = $db->loadObjectList();
		$product->files = array();
		foreach($files as $file) {
			$d = array(
				'name' => $file->file_name,
				'description' => $file->file_description,
				'free_download' => (bool)$file->file_free_download,
			);
			if($file->file_free_download){
				$d['link'] = hikashop_cleanURL(hikashop_completeLink('product&task=download&file_id=' . $file->file_id.$menu));
			}
			if(isset($product->variants[$file->file_ref_id])){
				$product->variants[$file->file_ref_id]->files[] = $d;
			}else{
				$product->files[] = $d;
			}
		}

		$currencyClass = hikashop_get('class.currency');
		$zone_id = hikashop_getZone(null);
		$currency_id = hikashop_getCurrency();
		$discount_before_tax = (int)$config->get('discount_before_tax', 0);
		$currencyClass->getPrices($product, $ids, $currency_id, $main_currency, $zone_id, $discount_before_tax);
		$this->normalizeProductPrices($product);
		if(!empty($product->options)){
			foreach($product->options as $k => $v){
				$this->normalizeProductPrices($product->options[$k]);
			}
		}


		$product->categories = array();
		foreach($categories as $category) {
			if(empty($category->category_published))
				continue;
			$product->categories[] = array(
				'category_id' => (int)$category->category_id,
				'category_name' => $category->category_name,
			);
		}

		return $product;
	}

	protected function normalizeProductPrices(&$product){
		if(empty($product->prices) || !count($product->prices)){
			if(isset($product->prices))
				unset($product->prices);
		}else{
			$this->normalizePrices($product->prices);
		}
		if(!empty($product->variants)){
			foreach($product->variants as $k => $v){
				if(empty($v->prices) || !count($v->prices)){
					if(isset($v->prices))
						unset($product->variants[$k]->prices);
				}else{
					$this->normalizePrices($product->variants[$k]->prices);
				}
			}
		}
	}

	protected function normalizePrices(&$prices){
		$currencyClass = hikashop_get('class.currency');
		if(empty($this->currencies)) {
			$this->currencies = array(); // TODO
		}
		foreach($prices as $k => $p) {
			$price = array(
				'price_value' => $p->price_value,
				'price' => $currencyClass->format($p->price_value, $p->price_currency_id),
				'price_value_with_tax' => $p->price_value_with_tax,
				'price_with_tax' => $currencyClass->format($p->price_value_with_tax, $p->price_currency_id),
				'price_min_quantity' => (int)$p->price_min_quantity,
				'price_currency_code' => @$this->currencies[ (int)$p->price_currency_id ]
			);
			$prices[$k] = $price;
		}
	}

	protected function addCharacteristics(&$product, &$mainCharacteristics, &$characteristics, $selected_variant_id) {
		$product->characteristics = @$mainCharacteristics[$product->product_id][0];
		if(!empty($product->characteristics) && is_array($product->characteristics)) {
			foreach($product->characteristics as $k => $characteristic) {
				if(!empty($mainCharacteristics[$product->product_id][$k])) {
					$product->characteristics[$k]->default = end($mainCharacteristics[$product->product_id][$k]);
				}
			}
		}
		if(empty($product->variants))
			return;

		foreach($characteristics as $characteristic) {
			foreach($product->variants as $k => $variant) {
				if($variant->product_id != $characteristic->variant_product_id)
					continue;

				$product->variants[$k]->characteristics[$characteristic->characteristic_parent_id] = $characteristic;
				$product->characteristics[$characteristic->characteristic_parent_id]->values[$characteristic->characteristic_id] = $characteristic;

				if($selected_variant_id && $variant->product_id == $selected_variant_id)
					$product->characteristics[$characteristic->characteristic_parent_id]->default = $characteristic;
			}
		}
		foreach($product->variants as $k => $variant) {
			$temp = array();
			foreach($product->characteristics as $k2 => $characteristic2) {
				if(!empty($variant->characteristics)) {
					foreach($variant->characteristics as $k3 => $characteristic3) {
						if($k2 == $k3) {
							$temp[$k3] = $characteristic3;
							break;
						}
					}
				}
			}
			$product->variants[$k]->characteristics = $temp;
		}
	}


	protected function getProducts(&$helper, $url, $params, $data) {
		$config = hikashop_config();
		$db = JFactory::getDBO();

		$user_id = hikashop_loadUser(false);
		$category_id = (int)$params['params']['id'];
		$product_ids = null;

		if(!empty($data['ids'])) {
			if(!is_array($data['ids']))
				return false;
			$product_ids = $data['ids'];
			JArrayHelper::toInteger($product_ids);
		}

		if((int)$category_id <= 0)
			$category_id = 0;
		if(empty($category_id) && empty($product_ids)) {
			$categoryClass = hikashop_get('class.category');
			$category_id = 'product';
			$categoryClass->getMainElement($category_id);
		}

		if(!empty($category_id)) {
		}

		$start = 0;
		$limit = 50;
		if(isset($params['params']['pagination'])) {
			$start = ((int)$params['params']['pagination']['start']);
			$limit = ((int)$params['params']['pagination']['limit']);
			if($start <= 0)
				$start = 0;
			if($limit <= 0)
				$limit = 50;
		}

		$fieldsClass = hikashop_get('class.field');
		$fakeProduct = new stdClass();
		$fakeCategory = new stdClass();
		$fakeCategory->category_id = (int)$category_id;
		$fakeProduct->categories = array($fakeCategory);
		$fields = $fieldsClass->getFields('frontcomp', $fakeProduct, 'product', 'checkout&task=state');

		$whitelist = $this->whitelist;
		if(!empty($fields))
			$whitelist = array_merge($whitelist, array_keys($fields));

		$select = array(
			'product_all' => 'product.*'
		);
		$tables = array(
			'product' => hikashop_table('product').' AS product',
			'product_category:product' => 'INNER JOIN '.hikashop_table('product_category').' AS product_category ON product_category.product_id = product.product_id'
		);
		$where = array(
			'product_published' => 'product.product_published = 1',
			'product_type' => 'product.product_type = '.$db->Quote('main')
		);
		$order = '';

		if(!empty($category_id))
			$where['product_category'] = 'product_category.category_id = '.(int)$category_id;
		if(!empty($product_ids))
			$where['product_id'] = 'product.product_id IN (' . implode(',', $product_ids) . ')';

		$filters = null;
		if(hikashop_level(2) && !empty($category_id)) {
			$filterClass = hikashop_get('class.filter');
			$filters = $filterClass->getFilters((int)$category_id);
		}
		if(!empty($filters)) {
			foreach($filters as $filter) {
				$this->addProductFilter($filter, $data, $select, $tables, $where, $order, $whitelist);
			}
		}

		hikashop_addACLFilters($where, 'product_access', 'product');


		if(empty($order) && !empty($data['sort'])) {
			$dir = 'ASC';
			if(in_array($data['sort'], $whitelist))
				$order = 'ORDER BY product.' . $data['sort']. ' '.$dir;
		}

		$query = 'SELECT '.implode(', ', $select).' FROM '.implode(' ', $tables).' WHERE ('.implode(' ) AND (', $where).') '.$order;
		$db->setQuery($query, $start, $limit);
		$ret = $db->loadObjectList('product_id');

		if(empty($ret))
			return $ret;

		$product_ids = array_keys($ret);


		foreach($ret as &$product) {
			foreach($product as $key => $v) {
				if(!in_array($key, $whitelist))
					unset($product->$key);
			}
		}
		unset($product);

		foreach($ret as &$row) {
			$row->images = array();
			$row->prices = array();
		}
		unset($row);

		$imageHelper = hikashop_get('helper.image');
		$query = 'SELECT * FROM '.hikashop_table('file').' AS file WHERE '.
			' file.file_ref_id IN ('.implode(',', $product_ids).') AND file.file_type = '.$db->Quote('product').' '.
			' ORDER BY file.file_ref_id ASC, file.file_ordering ASC, file.file_id ASC';
		$db->setQuery($query);
		$images = $db->loadObjectList();

		foreach($images as $image) {
			$pid = (int)$image->file_ref_id;
			$img = $imageHelper->getThumbnail($image->file_path);
			$d = array(
				'name' => $image->file_name,
				'description' => $image->file_description,
				'path' => hikashop_cleanURL($img->origin_url),
				'thumb' => hikashop_cleanURL($img->url),
			);
			$ret[$pid]->images[] = $d;
		}

		$currencyClass = hikashop_get('class.currency');
		$zone_id = hikashop_getZone(null);
		$currency_id = hikashop_getCurrency();
		$currencyClass->getListingPrices($ret, $zone_id, $currency_id, 'all', $user_id);
		foreach($ret as &$product) {
			foreach($product->prices as $k => $p) {
				$price = array(
					'price_value' => $p->price_value,
					'price' => $currencyClass->format($p->price_value, $p->price_currency_id),
					'price_value_with_tax' => $p->price_value_with_tax,
					'price_with_tax' => $currencyClass->format($p->price_value_with_tax, $p->price_currency_id),
					'price_min_quantity' => (int)$p->price_min_quantity,
				);
				$product->prices[$k] = $price;
			}
		}
		unset($product);


		return $ret;
	}

	protected function addProductFilter(&$filter, $data, &$select, &$tables, &$where, &$order, &$whitelist) {

		$filter_data = null;
		if(isset($data['filters']))
			$filter_data = @$data['filters'][$filter->filter_namekey];

		switch($filter->filter_type) {
			case 'instockcheckbox':
				if($filter_data == 'in_stock')
					$select['product_quantity'] = 'product.product_quantity != 0';
				break;

			case 'text':
				$filter_data = trim($filter_data);
				if(empty($filter_data))
					return false;
				if(!empty($filter->filter_options['max_char']) && strlen($filter_data) > (int)$filter->filter_options['max_char'])
					return false;

				if(empty($filter->filter_data) || in_array('all', $filter->filter_data)) {
					$searchField = array_merge($whitelist, array());
					$filter->filter_data = array('all');
				} else
					$searchField = $filter->filter_data;

				$key = implode(',', $filter->filter_data);

				$searchProcessing = 'any';
				if(isset($filter->filter_options['searchProcessing']))
					$searchProcessing = $filter->filter_options['searchProcessing'];

				if($searchProcessing == 'operators') {
					$searchProcessing = 'any';
					if(strpos($filter_data, ' ') !== false)
						$searchProcessing = 'any';

					if(strpos(trim($filter_data, '+'), '+') !== false) {
						$filter_data = str_replace('+', ' ', $filter_data);
						$searchProcessing = 'every';
					}

					$f = substr($filter_data, 0, 1);
					$l = substr($filter_data, -1, 1);
					if($f == $l && in_array($f, array('"', "'"))) {
						$searchProcessing = 'complete';
						$filter_data = trim($filter_data, $f);
					}
				}

				if($searchProcessing == 'exact') {
					$searchs = array();
					foreach($searchField as $column) {
						$searchs[] = 'product.'.$column.' = ' . $db->Quote($filter_data);
					}
					$where['filter_search:'.$key] = implode(' OR ', $searchs);
				} else if($searchProcessing == 'any') {
					$terms = explode(' ', $filter_data);
					foreach($terms as $term) {
						if(empty($term))
							continue;
						foreach($searchField as $column) {
							$searchs[] = 'product.'.$column.' LIKE \'%'.hikashop_getEscaped($term, true).'%\'';
						}
					}
					$where['filter_search:'.$key] = implode(' OR ', $searchs);
				} else {
					if($searchProcessing == 'complete')
						$terms = array($filter_data);
					else
						$terms = explode(' ', $filter_data);

					foreach($terms as $term) {
						if(empty($term))
							continue;
						$searchLocal = array();
						foreach($searchField as $column) {
							$searchLocal[] = 'product.'.$column.' LIKE \'%'.hikashop_getEscaped($term, true).'%\'';
						}
						$searchs[] = '('.implode(' OR ', $searchLocal).')';
					}
					$where['filter_search:'.$key] = implode(' AND ', $searchs);
				}
				break;

			case 'cursor':
				if(!is_array($filter_data) || count($filter_data) != 2 || ($filter_data[0] == $filter->filter_options['cursor_min'] && $filter_data[1] == $filter->filter_options['cursor_max']))
					return false;
				$filter_data[0] = (float)hikashop_toFloat($filter_data[0]);
				$filter_data[1] = (float)hikashop_toFloat($filter_data[0]);
				break;
		}

		switch($filter->filter_data) {
			case 'category':
				if(!is_array($filter_data))
					$filter_data = array($filter_data);
				JArrayHelper::toInteger($filter_data);
				$tables['product_category:filter'] = 'INNER JOIN '.hikashop_table('product_category').' AS product_category_filter ON product_category_filter.product_id = product.product_id';
				$where['product_category:filter'] = 'product_category_filter.category_id IN (' . implode(',', $filter_data) . ')';
				break;

			case 'information':
			case 'price':

				break;

			case 'manufacturers':
				if(!is_array($filter_data))
					$filter_data = array($filter_data);
				JArrayHelper::toInteger($filter_data);
				$where['manufacturer'] = 'product.product_manufacturer_id IN (' . implode(',', $filter_data) . ')';
				break;

			case 'characteristic':
				if(!is_array($filter_data))
					$filter_data = array($filter_data);
				JArrayHelper::toInteger($filter_data);
				$tables['product:parent'] = 'LEFT JOIN '.hikashop_table('product').' AS product_parent ON product.product_id = product_parent.product_parent_id';
				$tables['variant:parent'] = 'LEFT JOIN '.hikashop_table('variant').' AS variant_parent ON variant.variant_product_id = product_parent.product_id';
				$where['parent_characteristic'] = 'variant_parent.variant_characteristic_id IN (' . implode(',', $filter_data) . ')';
				$where['parent_quantity'] = 'variant_parent.product_quantity != 0';
				break;

			case 'quantity':
				if(!empty($filter_data))
					$select['product_quantity'] = 'product.product_quantity != 0';
				break;

			case 'custom_field':

				break;

			case 'sort':

				break;
		}

		if(false &&
		 (isset($infos[1]) && ($filter->filter_data=='sort' && $sort_by_price && (($infos[1]=='lth') || ($infos[1]=='htl')))|| $filter->filter_data=='price')) {
			$subfilters = array();
			$where = '';
			hikashop_addACLFilters($subfilters,'price_access','price'.$i,2,true);
			$subfilters[]='product'.$i.'.product_type=\'main\'';
			$where = ' WHERE '.implode(' AND ',$subfilters);
			$subquery ='SELECT * FROM '.hikashop_table('product').' AS product'.$i.' LEFT JOIN '.hikashop_table('price').' AS price'.$i.' ON product'.$i.'.product_id=price'.$i.'.price_product_id '.$where.' GROUP BY product'.$i.'.product_id ORDER BY price'.$i.'.price_min_quantity ASC';
			$a = '('.$subquery.') AS b';
		}
	}

	protected function getFilterUnitSelect($filter, $type, $i = 0) {
		$weightHelper = hikashop_get('helper.weight');
		$volumeHelper = hikashop_get('helper.volume');
		$config =& hikashop_config();
		$defaulUnit='cm';
		if($type=='weight'){
			$infoType='b.product_weight';
			$unitType='b.product_weight_unit';
			$units=$weightHelper->conversion;
			$defaulUnit='kg';
		}else if($type=='volume'){
			$infoType='(b.product_width*b.product_length*b.product_height)';
			$unitType='b.product_dimension_unit';
			$units=$volumeHelper->conversion;
		}else if($type=='surface'){
			$infoType[]='b.product_width';
			$infoType[]='b.product_length';
			$unitType='b.product_dimension_unit';
			$units=$volumeHelper->conversionDimension;
		}else if($type=='height' || $type=='length' || $type=='width'){
			$unitType='b.product_dimension_unit';
			$units=$volumeHelper->conversionDimension;
			if($type=='height'){ $infoType='b.product_height';	}
			if($type=='length'){ $infoType='b.product_length';	}
			if($type=='width'){ $infoType='b.product_width';	}
		}elseif($type=='price'){
			return $this->getPriceSelect();
		}else{
			return '';
		}

		if(isset($filter->filter_options['information_unit'])){
			$selectedUnit=$filter->filter_options['information_unit'];
		}else{
			$selectedUnit=$defaulUnit;
		}
		$case=' case';
		foreach( $units as $key => $unit){
			$calculatedVal='';
			if($key==$selectedUnit){ $val=1; }
			else{ $val=$unit[$selectedUnit]; }
			if(is_array($infoType)){
				foreach($infoType as $type){
					$calculatedVal.='('.$type.'*'.$val.')*';
				}
				$calculatedVal=substr($calculatedVal,0,-1);
			}else{
				$calculatedVal=$infoType.'*'.$val;
			}
			$case .= ' when '.$unitType.' = \''.$key.'\' then '.$calculatedVal;
		}
		$case.= ' else '.$unitType.' end ';
		return $case;
	}

	protected function getFilterPriceSelect($price_table = 'price',$product_table = 'product') {
		$case=' case';
		$currentCurrency = hikashop_getCurrency();
		$unitType=$price_table.'.price_value';
		$currencyType = hikashop_get('type.currency');
		$currencyClass = hikashop_get('class.currency');
		$dstCurrency = $currencyClass->get($currentCurrency);
		$currencyType->load(0);
		$currencies = $currencyType->currencies;
		$config =& hikashop_config();
		$main_currency = $config->get('main_currency',1);
		if($config->get('price_with_tax')){
			$categoryClass=hikashop_get('class.category');
			$main = 'tax';
			$categoryClass->getMainElement($main);
			$tax_categories = $categoryClass->getChildren($main);
			$taxes = array();
			foreach($tax_categories as $tax_category){
				$taxes[$tax_category->category_id] = (float)$currencyClass->getTax(hikashop_getZone(),$tax_category->category_id);
			}
			$taxes[0] = 0;
		}
		foreach($currencies as $currency){

			$calculatedVal=$unitType;
			if($main_currency!=$currency->currency_id){
				if(bccomp($currency->currency_percent_fee,0,2)){
					$calculatedVal='('.$calculatedVal.'*'.(floatval($currency->currency_percent_fee+100)/100.0).')';
				}
				$calculatedVal='('.$calculatedVal.'/'.floatval($currency->currency_rate).')';
			}
			if($main_currency!=$currentCurrency){
				$calculatedVal='('.$calculatedVal.'*'.floatval($dstCurrency->currency_rate).')';
				if(bccomp($dstCurrency->currency_percent_fee,0,2)){
					$calculatedVal='('.$calculatedVal.'*'.(floatval($dstCurrency->currency_percent_fee+100)/100.0).')';
				}
			}else{
				$case .= ' when '.$price_table.'.price_currency_id IS NULL then 0';
			}
			if(!empty($taxes)){
				$ids=array();
				foreach($taxes as $id => $tax){
					if($id!=0){
						$ids[]=$id;
						$case .= ' when '.$price_table.'.price_currency_id = \''.$currency->currency_id.'\' and '.$product_table.'.product_tax_id = \''.$id.'\' then '.$calculatedVal.'+'.$calculatedVal.'*'.$tax;
					}
				}
				$case .= ' when '.$price_table.'.price_currency_id = \''.$currency->currency_id.'\' and '.$product_table.'.product_tax_id NOT IN (\''.implode('\',\'',$ids).'\') then '.$calculatedVal;
			}else{
				$case .= ' when '.$price_table.'.price_currency_id = \''.$currency->currency_id.'\' then '.$calculatedVal;
			}

		}
		$case.= ' end ';
		return $case;
	}
}
