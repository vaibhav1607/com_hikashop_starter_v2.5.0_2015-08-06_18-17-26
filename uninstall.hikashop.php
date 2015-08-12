<?php

function com_uninstall(){
	$uninstallClass = new hikashopUninstall();
	$uninstallClass->unpublishModules();
	$uninstallClass->unpublishPlugins();
}
class hikashopUninstall{
	var $db;
	function hikashopUninstall(){
		$this->db =& JFactory::getDBO();
		$this->db->setQuery("DELETE FROM `#__hikashop_config` WHERE `config_namekey` = 'li' LIMIT 1");
		$this->db->query();
	 	if(version_compare(JVERSION,'1.6.0','>=')){
			$this->db->setQuery("DELETE FROM `#__menu` WHERE link LIKE '%com_hikashop%'");
			$this->db->query();
		}
	}
	function unpublishModules(){
		$this->db->setQuery("UPDATE `#__modules` SET `published` = 0 WHERE `module` LIKE '%hikashop%'");
		$this->db->query();
	}
	function unpublishPlugins(){
		if(version_compare(JVERSION,'1.6.0','<')){
			$this->db->setQuery("UPDATE `#__plugins` SET `published` = 0 WHERE `element` LIKE '%hikashop%' AND `folder` NOT LIKE '%hikashop%'");
		}else{
			$this->db->setQuery("UPDATE `#__extensions` SET `enabled` = 0 WHERE `type` = 'plugin' AND `element` LIKE '%hikashop%' AND `folder` NOT LIKE '%hikashop%'");
		}
		$this->db->query();
	}
}