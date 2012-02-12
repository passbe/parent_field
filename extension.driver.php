<?php

	Class extension_parent_field extends Extension{

		public function about(){
			return array(
				'name' => 'Field: Parent',
				'version' => '0.1',
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
	}
