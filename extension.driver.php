<?php

	Class extension_parent_field extends Extension{

		public function about(){
			return array(
				'name' => 'Field: Parent',
				'version' => '0.2',
				'release-date' => '12-02-2012',
				'author' => array(
					array(
						'name' => 'James West',
						'website' => 'http://www.jameswest.co.nz/',
						'email' => 'james@jameswest.co.nz'
					),
					array(
						'name' => 'Chilli',
						'website' => 'http://chil.li/',
						'email' => 'web@chil.li'
					)
				)
			);
		}
		
		public function getSubscribedDelegates(){
			return array(
				array(
				'page'		=> '/backend/',
				'delegate'	=> 'NavigationPreRender',
				'callback'	=> 'NavigationPreRender'
				)
			);
		}		

		public function install(){
			try{
				Symphony::Database()->query("
					CREATE TABLE IF NOT EXISTS `tbl_fields_parent` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`field_id` int(11) unsigned NOT NULL,
						`show_association` enum('yes','no') NOT NULL default 'yes',
						`identifying_field_id` int(11) unsigned NOT NULL,
						PRIMARY KEY  (`id`),
						KEY `field_id` (`field_id`)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
				");
			}
			catch(Exception $e){
				return false;
			}

			return true;
		}

		public function uninstall(){
			if(parent::uninstall() == true){
				Symphony::Database()->query("DROP TABLE `tbl_fields_parent`");
				return true;
			}

			return false;
		}

		public function update($previousVersion){
			try{
			}
			catch(Exception $e){
				// Discard
			}

			return true;
		}
		
		function NavigationPreRender($context){
			$sections_to_filter = $this->getSectionsWithParentField();
			
			foreach ($context['navigation'] as &$navigation_group ) {
				foreach ($navigation_group['children'] as &$navigation_item ) {
					if (isset($navigation_item['section']) && in_array($navigation_item['section']['id'], $sections_to_filter))
					{
						$section_id = $navigation_item['section']['id'];
						
						$field_name = Symphony::Database()->fetchCol("element_name", "SELECT * FROM tbl_fields WHERE `type` = 'parent' AND `parent_section` = $section_id");
						$navigation_item['link'] .= "?filter=$field_name[0]:0";
					}
				}
			}
			
			return $content;
		}
		
		function getSectionsWithParentField(){
			$sections = Symphony::Database()->fetchCol("parent_section", "SELECT * FROM tbl_fields WHERE `type` = 'parent'");
			return $sections;
		}
		
	}
