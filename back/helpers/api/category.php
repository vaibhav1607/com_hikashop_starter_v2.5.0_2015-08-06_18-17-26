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
class hikashopApicategoryHelper {

	private $whitelist = array(
		'category_id', 'category_name', 'category_description', 'category_alias', 'category_keywords', 'category_meta_description', 'category_canonical', 'category_page_title',
	);

	public function processRequest(&$helper, $url, $params, $data) {
		switch($url) {
			case '/category/:id':
				return $this->getCategory($helper, $url, $params, $data);
				break;
			case '/categories/:id':
				return $this->getCategories($helper, $url, $params, $data);
		}
		return false;
	}

	protected function getCategory(&$helper, $url, $params, $data) {
		$config = hikashop_config();
		$db = JFactory::getDBO();
		$categoryClass = hikashop_get('class.category');

		$user_id = hikashop_loadUser(false);
		$category_id = (int)$params['params']['id'];

		if(empty($category_id))
			return false;

		$filters = array(
			'category_id' => 'category.category_id = ' . $category_id,
			'category_published' => 'category.category_published = 1',
		);
		hikashop_addACLFilters($filters, 'category_access', 'category');

		$query = 'SELECT category.*'.
			' FROM '.hikashop_table('category').' AS category '.
			' WHERE ('.implode(') AND (', $filters). ') ';

		$db->setQuery($query, 0, 1);
		$category = $db->loadObject();

		if(empty($category))
			return;

		$categoryClass->addAlias($category);
		$alias = $category->alias;

		$fieldsClass = hikashop_get('class.field');
		$fields = $fieldsClass->getFields('frontcomp', $category, 'category', 'checkout&task=state');

		$whitelist = $this->whitelist;
		if(!empty($fields))
			$whitelist = array_merge($whitelist, array_keys($fields));
		foreach($category as $key => $v) {
			if(!in_array($key, $whitelist))
				unset($category->$key);
		}

		$menu = '';
		if(!empty($helper->itemid)){
			$menu.='&Itemid='.(int)$helper->itemid;
		}
		$category->link = hikashop_cleanURL(hikashop_contentLink('category&task=listing&cid='.$category_id.'&name='.$alias.$menu,$category));

		$imageHelper = hikashop_get('helper.image');
		$query = 'SELECT * FROM '.hikashop_table('file').' AS file WHERE '.
			' file.file_ref_id = ' . $category_id . ' AND file.file_type = '.$db->Quote('category').' '.
			' ORDER BY file.file_ordering ASC, file.file_id ASC';
		$db->setQuery($query);
		$images = $db->loadObjectList();

		foreach($images as $image) {
			$img = $imageHelper->getThumbnail($image->file_path);
			$d = array(
				'name' => $image->file_name,
				'description' => $image->file_description,
				'path' => hikashop_cleanURL($img->origin_url),
				'thumb' => hikashop_cleanURL($img->url),
			);
			$category->images[] = $d;
		}
		return $category;
	}
}
