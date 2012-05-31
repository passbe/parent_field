<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	Class fieldParent extends Field {

		private static $cache = array();
		private static $em = null;
		
		private static $subfields = array(
			'input',
			'selectbox',
			'selectbox_link',
			'date',
			'checkbox',
			'order_entries'
		);

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();
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
		
		public function canPrePopulate() {
			return true;
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
				$where = "p.relation_id IS NULL";
			} else {
				$where = "p.relation_id = {$parent_id}";
			}
			
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
			if(!isset($_REQUEST['prepopulate'][$this->get('id')]) && $this->get('relation_id') == $id) {
				$selected = true;
			} else if (isset($_REQUEST['prepopulate'][$this->get('id')]) && $_REQUEST['prepopulate'][$this->get('id')] == $id) {
				$selected = true;
			}
			
			return array($id, $selected, $name);
		}

		public function fetchAssociatedEntryCount($value){
			return Symphony::Database()->fetchVar('count', 0, sprintf("
					SELECT COUNT(*) as `count`
					FROM `tbl_entries_data_%d`
					WHERE `relation_id` = %d
				",
				$this->get('id'), $value
			));
		}

		public function fetchAssociatedEntryIDs($value){
			return Symphony::Database()->fetchCol('entry_id', sprintf("
					SELECT `entry_id`
					FROM `tbl_entries_data_%d`
					WHERE `relation_id` = %d
				",
				$this->get('id'), $value
			));
		}

		public function fetchAssociatedEntrySearchValue($data, $field_id=NULL, $parent_entry_id=NULL){
			// We dont care about $data, but instead $parent_entry_id
			if(!is_null($parent_entry_id)) return $parent_entry_id;

			if(!is_array($data)) return $data;

			$searchvalue = Symphony::Database()->fetchRow(0, sprintf("
				SELECT `entry_id` FROM `tbl_entries_data_%d`
				WHERE `handle` = '%s'
				LIMIT 1",
				$field_id, addslashes($data['handle'])
			));

			return $searchvalue['entry_id'];
		}
		
		public function getIdentifyingFieldId() {
			return $this->get('identifying_field_id');
		}
		
		public function getParentsArray($entry_id) {
			$items = array();
			
			$fieldId = $this->get('id');
			
			$data = Symphony::Database()->fetchRow(0, "
				SELECT
					*
				FROM
					`tbl_entries_data_{$fieldId}` AS parent
				WHERE
					parent.entry_id = '{$entry_id}'
				LIMIT 1
			");

			if (!is_null($data['relation_id'])) {
				$next_parent = $data['relation_id'];

				while (
					$parent = Symphony::Database()->fetchRow(0, "
						SELECT
							*
						FROM
							`tbl_entries_data_{$fieldId}` AS parent
						WHERE
							parent.entry_id = '{$next_parent}'
						LIMIT 1
					")
				) {
					array_unshift($items, $parent['entry_id']);
					$next_parent = $parent['relation_id'];
				}
			}
			
			return $items;
		}
		
		private function _getParent($entry_id) {
			return self::$em->fetch($entry_id);
		}
		
		private function _getParents($entry_id, &$tree=array()) {
			$entry = self::$em->fetch($entry_id);
			
			if(!empty($entry)) {
				$tree = array(
					'object' => $entry[0],
					'children' => $tree
				);
				
				$parent_field = $entry[0]->getData($this->get('id'));
				
				if(isset($parent_field['relation_id'])) {
					$this->_getParents($parent_field['relation_id'], $tree);
				}
			}
		
			return $tree;
		}
		
		private function _parentsToXml($parent, &$wrapper) {
			if($parent['object'] instanceof Entry) {
				$parentXML = $this->_buildEntryXML($parent['object']);
				
				if(isset($parent['children'])) {
					$children = new XMLElement('children');
				
					$this->_parentsToXml($parent['children'], $children);
					
					$parentXML->appendChild($children);
				}
				
				$wrapper->appendChild($parentXML);
			}
		}
		
		private function _getChildren($entry_id, $recursive=false) {
			$children = Symphony::Database()->fetch("SELECT * FROM `tbl_entries_data_".$this->get('id')."` AS e WHERE e.relation_id = '{$entry_id}'");
		
			if(!empty($children)) {
				foreach($children as $child) {
					$entry = self::$em->fetch($child['entry_id']);
					
					$out['object'] = $entry[0];
					
					if($recursive) {
						$out['children'] = $this->_getChildren($child['entry_id'], true);
					}
					
					$items[] = $out;
				}
			}
		
			return $items;
		}
		
		private function _childrenToXml($items, &$wrapper) {
			if(empty($items)) return;
			
			foreach($items as $parent) {
				if($parent['object'] instanceof Entry) {
					$parentXML = $this->_buildEntryXML($parent['object']);
					
					if(isset($parent['children'])) {
						$children = new XMLElement('children');
					
						$this->_childrenToXml($parent['children'], $children);
						
						$parentXML->appendChild($children);
					}
					
					$wrapper->appendChild($parentXML);
				}
			}
		}
		
		private function _buildEntryXML(Entry $entry) {
			$entryXML = new XMLElement('entry', null, array('id' => $entry->get('id')));
			
			$section_id = $entry->get('section_id');
			
			$fields = self::$em->fieldManager->fetch(null, $section_id);// Possibly don't need all fields... Other DS's should probably do this
			
			// lets get some core fields that may be helpful
			//$fields = array_merge($fields, array(self::$em->fieldManager->fetch($this->get('identifying_field_id'), $section_id)));
			//$fields = array_merge($fields, self::$em->fieldManager->fetch(null, $section_id, null, 'sortorder', 'input'));
			
			foreach($fields as $field) {
				if(in_array($field->get('type'), self::$subfields) || $field->get('id') == $this->get('identifying_field_id')) {
					$field->appendFormattedElement($entryXML, $entry->getData($field->get('id')), false, null, $entry->get('id'));
				}
			}
		
			return $entryXML;
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
			
			if(!Symphony::Database()->insert($fields, "tbl_fields_{$handle}", true)) return false;
			
			$this->removeSectionAssociation($id);
			$this->createSectionAssociation(NULL, $id, $this->get('identifying_field_id'), $this->get('show_association') == 'yes' ? true : false);

			return true;
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
			
			if($data['relation_id'] == 0 || $data['relation_id'] == '') {
				$data['relation_id'] = null;
			}

			$result['relation_id'] = $data['relation_id'];

			return $result;
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/
	
		public function appendFormattedElement(&$wrapper, $data, $encode = false, $mode = null, $entry_id=null) {
			if(is_null($mode)) {
				$mode = 'parent';
			}

			$mode = General::createHandle($mode);
			
			
			
			if($mode == 'children-recursive') {
				$list = new XMLElement('children', null, array('mode' => 'recursive'));
			} else {
				$list = new XMLElement($mode);
			}
			
			switch($mode) {
				case 'parent';
					if(!is_array($data) || empty($data) || is_null($data['relation_id'])) return;
					$parent = $this->_getParent($data['relation_id']);
					
					if($parent[0]) {
						$parentXML = $this->_buildEntryXML($parent[0]);
						$list->appendChild($parentXML);
					}
				break;
				case 'parents';
					if(!is_array($data) || empty($data) || is_null($data['relation_id'])) return;
					$parents = $this->_getParents($data['relation_id']);
					$this->_parentsToXml($parents, $list);
				break;
				case 'children';
					$children = $this->_getChildren($entry_id);
					$this->_childrenToXml($children, $list);
				break;
				case 'children-recursive';
					$children = $this->_getChildren($entry_id, true);
					$this->_childrenToXml($children, $list);
				break;
				default:
				break;
			}

			$wrapper->appendChild($list);
		}
	
		public function fetchIncludableElements($break = false) {
			$name = $this->get('element_name');
		
			$includable = array(
				$name . ': parent',
				$name . ': parents',
				//$name . ': siblings',
				$name . ': children',
				$name . ': children (recursive)'
			);
			
			return $includable;
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation=false){
			$field_id = $this->get('id');
			
			if($data[0] == 'null') {
				$data[0] = 'sql:NULL';
			}

			if(preg_match('/^sql:\s*/', $data[0], $matches)) {
				$data = trim(array_pop(explode(':', $data[0], 2)));

				// Check for NOT NULL (ie. Entries that have any value)
				if(strpos($data, "NOT NULL") !== false) {
					$joins .= " LEFT JOIN
									`tbl_entries_data_{$field_id}` AS `t{$field_id}`
								ON (`e`.`id` = `t{$field_id}`.entry_id)";
					$where .= " AND `t{$field_id}`.relation_id IS NOT NULL ";

				}
				// Check for NULL (ie. Entries that have no value)
				else if(strpos($data, "NULL") !== false) {
					$joins .= " LEFT JOIN
									`tbl_entries_data_{$field_id}` AS `t{$field_id}`
								ON (`e`.`id` = `t{$field_id}`.entry_id)";
					$where .= " AND `t{$field_id}`.relation_id IS NULL ";

				}
			}
			else {
				$negation = false;
				if(preg_match('/^not:/', $data[0])) {
					$data[0] = preg_replace('/^not:/', null, $data[0]);
					$negation = true;
				}

				foreach($data as $key => &$value) {
					// for now, I assume string values are the only possible handles.
					// of course, this is not entirely true, but I find it good enough.
					if(!is_numeric($value) && !is_null($value)){
						$related_field_ids = $this->get('related_field_id');
						$id = null;

						foreach($related_field_ids as $related_field_id) {
							try {
								$return = Symphony::Database()->fetchCol("id", sprintf(
									"SELECT
										`entry_id` as `id`
									FROM
										`tbl_entries_data_%d`
									WHERE
										`handle` = '%s'
									LIMIT 1", $related_field_id, Lang::createHandle($value)
								));

								// Skipping returns wrong results when doing an AND operation, return 0 instead.
								if(!empty($return)) {
									$id = $return[0];
									break;
								}
							} catch (Exception $ex) {
								// Do nothing, this would normally be the case when a handle
								// column doesn't exist!
							}
						}

						$value = (is_null($id)) ? 0 : $id;
					}
				}

				if($andOperation) {
					$condition = ($negation) ? '!=' : '=';
					foreach($data as $key => $bit){
						$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
						$where .= " AND `t$field_id$key`.relation_id $condition '$bit' ";
					}
				}
				else {
					$condition = ($negation) ? 'NOT IN' : 'IN';
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
					$where .= " AND `t$field_id`.relation_id $condition ('".implode("', '", $data)."') ";
				}

			}

			return true;
		}
		

	}
