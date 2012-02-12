<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	Class fieldParent extends Field {

		private static $cache = array();
		private static $em = null;

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('Parent Field');
			$this->_required = true;
			$this->_showassociation = true;

			// Default settings
			$this->set('show_column', 'no');
			$this->set('show_association', 'yes');
			$this->set('required', 'yes');

			if(!isset(self::$em) && class_exists('EntryManager')) {
				self::$em = new EntryManager(Symphony::Engine());
			}
		}

		public function canToggle(){
			return false;
		}

		public function canFilter(){
			return true;
		}

		public function allowDatasourceOutputGrouping(){
			return false;
		}

		public function allowDatasourceParamOutput(){
			return true;
		}

		public function requiresSQLGrouping(){
			return false;
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		public function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`entry_id` int(11) unsigned NOT NULL,
					`relation_id` int(11) unsigned DEFAULT NULL,
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),
					KEY `relation_id` (`relation_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
			);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function getTree() {
			return $this->_loadItems();
		}
		
		private function _loadItems($parent_id=null) {
			$entry_id = $this->get('entry_id');
			$parent_field_id = $this->get('id');
			$identifying_field_id = $this->get('identifying_field_id');
			
			if(is_null($parent_id)) {
				$parent_id = 0;
			}
			
			$where = "p.relation_id = {$parent_id}";
			
			if($entry_id) {
				$where .= " AND e.entry_id != {$entry_id}";
			}
			
			$items = Symphony::Database()->fetch("SELECT e.entry_id AS id, e.value AS name FROM `tbl_entries_data_{$identifying_field_id}` AS e LEFT JOIN `tbl_entries_data_{$parent_field_id}` AS p ON p.entry_id = e.entry_id WHERE {$where}");
			
			foreach($items as &$item) {
				$item['children'] = $this->_loadItems($item['id']);
			}
			
			
			return $items;
		}
		
		private function _prepareOptions($tree, $depth=0) {
			$options = array();
			
			foreach($tree as $option) {
				if($this->get('entry_id') != $option['id']) {
					$options[] = $this->_prepareOption($option['id'], $option['name'], $depth);
				}
				
				if(!empty($option['children'])) {
					$options = array_merge($options, $this->_prepareOptions($option['children'], $depth+1));
				}
			}
			
			return $options;
		}
		
		private function _prepareOption($id, $name, $depth=0) {
			if($depth) {
				$name = ' ' . $name;
			}
		
			// indent value
			while($depth) {
				$name = '-' . $name;
				$depth--;
			}
			
			$selected = false;
			if($this->get('relation_id') == $id) {
				$selected = true;
			}
			
			return array($id, $selected, $name);
		}

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		
		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);
			
			$section_id = Administration::instance()->Page->_context[1];
			
			// related field
			$label = Widget::Label(__('Identifying field'), NULL);
			$fieldManager = new FieldManager(Symphony::Engine());
			$fields = $fieldManager->fetch(NULL, $section_id, 'ASC', 'sortorder', NULL, NULL, 'AND (type = "input")');
			$options = array(
				array('', false, __('None Selected'), ''),
			);
			$attributes = array(
				array()
			);
			if(is_array($fields) && !empty($fields)) {
				foreach($fields as $field) {
					$options[] = array($field->get('id'), ($field->get('id') == $this->get('identifying_field_id')), $field->get('label'));
				}
			};
			$label->appendChild(Widget::Select('fields['.$this->get('sortorder').'][identifying_field_id]', $options));
			if(isset($errors['identifying_field_id'])) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($label, $errors['identifying_field_id']));
			} else {
				$wrapper->appendChild($label);
			};
			
		}

		/**
		 * Save field settings in section editor.
		 */
		public function commit() {
			if(!parent::commit()) return false;

			$id = $this->get('id');
			$handle = $this->handle();

			if($id === false) return false;

			$fields = array(
				'field_id' => $id,
				'identifying_field_id' => $this->get('identifying_field_id')
			);

			return Symphony::Database()->insert($fields, "tbl_fields_{$handle}", true);
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$dl = new XMLElement('dl');
			
			$element_name = $this->get('element_name');
			
			// label
			$label = Widget::Label($this->get('label'));
			$wrapper->appendChild($label);
			
			$this->set('entry_id', $entry_id);
			$this->set('relation_id', $data['relation_id']);
			
			$tree = $this->getTree();
			$options = $this->_prepareOptions($tree);
			
			array_unshift($options, array('', false, '--' . __('None') . '--', ''));
			
			$entires = new XMLElement('dt');
			$entires->appendChild(
				Widget::Select("fields[$element_name][relation_id]", $options)
			);
			$dl->appendChild($entires);
			
			$wrapper->appendChild($dl);
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){
			$status = self::__OK__;

			if(empty($data)) return null;

			$result['relation_id'] = (int)$data['relation_id'];

			return $result;
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		

	}
