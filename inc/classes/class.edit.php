<?
require_once("class.ini.php");
$counter = 0;
class editTable extends initEdit {
	protected $controls				= array();
	public $selectSQL				= array();
	public $buttons					= array();
	public $params					= array();
	public $modal					= array();
	public $saveConfirm				= "";
	public $main_table_id			= "";
	public $SQL						= "";
	public $HTML					= "";
	private $beforeSaveArr			= array();
	public $readOnly				= false;
	public $table   = '';
	public $error   = '';
	protected $resource				= "";
	protected $action				= "";
	private $isSaved 				= false;
	protected $cell					= array();
	protected $template				= '';

	public function __construct($name) {
		parent::__construct();
		$this->resource 		= $name;
		$this->main_table_id 	= "main_" . $name;
		$this->template 		= '<div id="' . $this->main_table_id . '_default">[default]</div>';
		global $counter;
		$counter = 0;
		$this->acl = new stdClass();
		foreach ($this->types as $acl_type) {
			$this->acl->$acl_type = $this->checkAcl($this->resource, $acl_type);
		}
	}
	
	public function __get($data) {
		if ($data == 'db') {
			$this->db = Zend_Registry::get('db');
			return $this->db;
		}
		$this->$data = new cell($this->main_table_id);
		$this->cell[$data] = $this->$data;
       	return $this->$data;
	}

	/**
	 * set HTML layout for the form
	 * @param String $html
	 */
	public function setTemplate($html) {
		$this->template = $html;
	}
	
		
	/**
	 * 
	 * Add new control to the form
	 * @param string $name - field caption
	 * @param string $type - type of control (TEXT, LIST, RADIO, CHECKBOX, FILE)
	 * @param string/array $in - field attributes
	 * @param string $out - outside HTML
	 * @param string $default - value by default
	 * @param string $req - is field required
	 */
	public function addControl($name, $type, $in = "", $out = "", $default = "", $req = false) {
		global $counter;
		if (empty($this->cell['default'])) {
			$c = new cell($this->main_table_id);
			$c->addControl($name, $type, $in, $out, $default, $req);
			$this->cell['default'] = $c;
		} else {
			$temp = array(
				'name' 		=> $name, 
				'type' 		=> strtolower($type), 
				'in' 		=> $in, 
				'out' 		=> $out, 
				'default' 	=> $default, 
				'req' 		=> $req
			);
			$this->cell['default']->appendControl($temp);
		}
	}

	/**
	 * @param $name
	 * @param bool $collapsed
	 */
	public function addGroup($name, $collapsed = false) {
		global $counter;
		if (empty($this->cell['default'])) {
			$c = new cell($this->main_table_id);
			$c->addGroup($name, $collapsed);
			$this->cell['default'] = $c;
		} else {
			if ($collapsed) $collapsed = "*";
			if ($this->cell['default']->controls) {
				$this->cell['default']->setGroup(array($counter => $collapsed . $name));
			} else {
				$this->cell['default']->setGroup(array(0 => $collapsed . $name));
			}
		}
		
	}

	/**
	 * @param $va
	 * @param string $value
	 */
	function addParams($va, $value = '') {
		$this->params[$this->main_table_id][] = array('va' => $va, 'value' => $value);
	}

	/**
	 * @param $value
	 * @param string $action
	 */
	public function addButton($value, $action = '') {
		$this->buttons[$this->main_table_id][] = array('value' => $value, 'action' => $action);
	}

	/**
	 * @param string $html
	 */
	public function addButtonCustom($html = '') {
		$this->buttons[$this->main_table_id][] = array('html' => $html);
	}
	
	/**
	 * 
	 * Create button for switch fields, based on values Y/N
	 * @param string $field_name - name of field
	 * @param string $value - switch ON or OFF
	 */
	public function addButtonSwitch($field_name, $value) {
		$tpl = new Templater("core2/html/" . THEME . "/edit/button_switch.tpl");
		if ($value) {
			$tpl->assign('id="switch_off"', 'id="switch_off" class="hide"');
			$valueInput = 'Y';
		} else {
			$tpl->assign('id="switch_on"', 'id="switch_on" class="hide"');	
			$valueInput = 'N';			
		}		
		$id = $this->main_table_id . $field_name;
		$tpl->assign('{ID}', $id);
		$html  = '<input type="hidden" id="' . $id . 'hid" name="control[' . $field_name . ']" value="' . $valueInput . '"/>';
		$html .= $tpl->parse();
		$this->addButtonCustom($html);
	}

	/**
	 * @param $func
	 */
	function save($func) {
		$this->isSaved = true;
		// for javascript functions
		if (strpos($func, '(') !== false) {
			$this->beforeSaveArr[] = $func;
		} else {
			$this->addParams('file', $func);	
		}
	}

	/**
	 * @param $value
	 * @param string $type
	 * @param string $onclick
	 * @return string
	 */
	private function button($value, $type = "Submit", $onclick = "") {
		$id = uniqid();
		$out = '<input type="' . $type . '" class="button" value="' . $value . '" ' . ($onclick ? 'onclick="' . rtrim($onclick, ";") . '"' : '') . '/>';
		
		return $out;
	}

	/**
	 * @return mixed
	 */
	public function makeTable() {
		if (!$this->isSaved) {
			$this->save('save.php');
		}	
		$authNamespace = Zend_Registry::get('auth');
		if (is_array($this->SQL)) {
			$arr = $this->SQL;
			$current = current($arr);
			if (!is_array($current)) {
				$current = array($current);
			}
			$arr_fields = array_keys($current);
		} else {
			$arr = $this->db->fetchAll($this->SQL);
		}
		if ($arr && is_array($arr) && is_array($arr[0])) {
			$k = 0;
			foreach ($arr[0] as $data) {
				$arr[0][$k] = $data;
				$k++;
			}
			
			reset($arr[0]);
			$refid = current($arr[0]);
		} else {
			$refid = 0;
		}
		if (!isset($arr_fields)) {
			$tmp_pos = strripos($this->SQL, "FROM ");
			// - IN CASE WE USE EDIT WITHOUT TABLE
			if ($tmp_pos === false) {
				$table = '';
				$arr_fields = explode("\n", str_replace("SELECT ", "", $this->SQL));
			} else {
				$prepare = substr($this->SQL, 0, $tmp_pos);
				$arr_fields = explode("\n", str_replace("SELECT ", "", $prepare));
				preg_match("/\W*([\w|-]*)\W*/",  substr($this->SQL, $tmp_pos + 4), $temp);
				$table = $temp[1];
			}
			if (empty($this->table)) {
				$this->table = $table;
			}
		}


		foreach ($arr_fields as $key => $value) {
			$value = trim(trim($value), ",");
			if (stripos($value, "AS ") !== false) {
				$arr_fields[$key] = substr($value, strripos($value, "AS ") + 3);
			} else {
				if (!$value) {
					unset($arr_fields[$key]);
					continue;
				}
				$arr_fields[$key] = $value;
			}
		}

		$select 		= 0;
		$modal 			= 0;
		$access_read 	= '';
		$access_edit 	= '';
		$keyfield 		= !empty($arr_fields[0]) ? trim($arr_fields[0], '`') : '';

		// CHECK FOR ACCESS
		if ($this->acl->read_owner) $access_read = 'owner';
		if ($this->acl->read_all) $access_read = 'all';
		if ($this->acl->edit_owner) $access_edit = 'owner';
		if ($this->acl->edit_all) $access_edit = 'all';

		if (!$access_read) {
			$this->noAccess();
			return;
		}
		elseif (!$access_edit) {
			$this->readOnly = true;
		}
		elseif ($refid) {
			if ($this->table) {
				$res = $this->db->fetchRow("SELECT * FROM `$this->table` WHERE `{$keyfield}`=? LIMIT 1", $refid);
				if ($access_edit == 'owner' || $access_read == 'owner') {
					if (!isset($res['author'])) {
						$this->noAccess();
						return;
					} elseif ($authNamespace->NAME !== $res['author']) {
						$this->readOnly = true;
					}
				}
			}
		}

		if (!$this->readOnly) {
			$onsubmit = "edit.onsubmit(this);";
			if ($this->saveConfirm) {
				$onsubmit .= "if(!confirm('{$this->saveConfirm}')){return false;};";
			}

			if (count($this->beforeSaveArr)) {
				foreach ($this->beforeSaveArr as $func) {
					if ($func) {
						$func = explode(";", $func);
						foreach ($func as $k => $fu) {
							if (strpos($fu, 'xajax_') !== false) {
								$funcName = explode('(', $fu);
								$funcName = substr($funcName[0], 6);
								$func[$k] = "xajax_post('$funcName', 'index.php?" . $_SERVER['QUERY_STRING'] . "', " . substr($fu, strpos($fu, '(', 1) + 1, -1) . ")";
							}
						}
						$onsubmit .= implode(";", $func) . ";return;";
					}
				}
			}
			$onsubmit .= "this.submit();return false;";
			
			$this->HTML .= "<form id=\"{$this->main_table_id}_mainform\" method=\"POST\" action=\"[_ACTION_]\" enctype=\"multipart/form-data\" onsubmit=\"$onsubmit\">";
			if ($this->action) {
				$this->HTML .= "<input type=\"hidden\" name=\"action\" value=\"$this->action\"/>";
			}
			$this->HTML .= "<input type=\"hidden\" name=\"class_id\" value=\"$this->main_table_id\"/>".
			"<input type=\"hidden\" name=\"resId\" value=\"$this->resource\"/>".
			"<input type=\"hidden\" name=\"back\" value=\"$this->back\"/>" .
			"<input type=\"hidden\" name=\"refid\" value=\"$refid\"/>" .
			"<input type=\"hidden\" name=\"table\" value=\"{$this->table}\"/>" .
			"<input type=\"hidden\" name=\"keyField\" value=\"$keyfield\"/>";

			if ($refid && $this->table) {
				$check = $this->db->fetchOne("SELECT 1 FROM core_controls WHERE tbl=? AND keyfield=? AND val=?",
					array($this->table, $keyfield, $refid)
				);
				$lastupdate = microtime();
				$auth = Zend_Registry::get('auth');
				if (!$check) {
					$this->db->insert('core_controls', array(
							'tbl' 		=> $this->table,
							'keyfield' 	=> $keyfield,
							'val' 		=> $refid,
							'lastuser' 	=> $auth->ID,
							'lastupdate' => $lastupdate
						)
					);
				} else {
					$this->db->query("UPDATE core_controls SET lastupdate=?, lastuser=? WHERE tbl=? AND keyfield=? AND val=?",
						array($lastupdate, $auth->ID, $this->table, $keyfield, $refid)
					);
				}
			}
			
			if (isset($this->params[$this->main_table_id]) && count($this->params[$this->main_table_id])) {
				foreach ($this->params[$this->main_table_id] as $key => $value) {
					$this->HTML .= "<input type=\"hidden\" name=\"{$value["va"]}\" id=\"{$this->main_table_id}_add_" . str_replace(array('[', ']'), '_', $value["va"]) . "\" value=\"{$value["value"]}\"/>";
				}
			}
		}
		$PrepareSave 	= "";
		$onload 		= "";
		
		$controlGroups	= array();
		
		if (!empty($this->cell)) {
			foreach ($this->cell as $cellId => $cellFields) {
				$groups 		= false;
				//echo "<PRE>";print_r($arr_fields);echo"</PRE>";//die();;
				//echo "<PRE>";print_r($arr);echo"</PRE>";//die();;
				$controls = $cellFields->controls[$this->main_table_id];
				if (!empty($controls)) {
					foreach ($controls as $key => $value) {
						$controlGroups[$cellId]['html'][$key] = '';
						if (!empty($value['group'])) {
							$groups 		= true;
							$temp = array();
							$temp['key'] = $key;
							$temp['collapsed'] = false;
							$temp['name'] = $value['group'];
							if (substr($value['group'], 0, 1) == "*") {
								$temp['collapsed'] = true;
								$temp['name'] = trim($value['group'], '*');
							}
							$controlGroups[$cellId]['group'][] = $temp;
						}
						//преобразование атрибутов в строку
						if (is_array($value['in'])) {
							$temp = " ";
							if (count($value['in'])) {
								foreach ($value['in'] as $attr => $val) {
									$temp .= $attr . '="' . $val . '" ';
								}
								$attrs = $temp;
							}
						} else {
							$attrs = $value['in'];
						}

						$sqlKey = $key + 1;
						if (!isset($arr_fields[$sqlKey])) {
							$arr_fields[$sqlKey] = '';
						}

						//Получение идентификатора текущего поля
						$field = trim(str_replace(array("'", "`"), "", $arr_fields[$sqlKey]));
						if (strtolower($field) == 'null') {
							$field = "field" . $sqlKey;
						}
						$fieldId = $this->main_table_id . $field;

						//обработка значение по умолчанию
						if ($value['default']) {
							if (!is_array($value['default'])) {
								$value['default'] = htmlspecialchars($value['default']);
							}
						}

						//присвоение значения из запроса, если запрос вернул результат
						if (isset($arr[0]) && isset($arr[0][0]) && isset($arr[0][$sqlKey])) {
							//$value['default'] = htmlspecialchars(stripslashes($arr[0][$sqlKey]));
							$value['default'] = htmlspecialchars($arr[0][$sqlKey]);
						}

						//если тип hidden то формируется только hidden поле формы
						if ($value['type'] == 'hidden') {
							$controlGroups[$cellId]['html'][$key] .= "<input id=\"" . $fieldId . "\" type=\"hidden\" name=\"control[$field]\" value=\"{$value['default']}\" />";
							continue;
						}

						//определяем, надо ли скрывать контрол
						$hide = '';
						if (strpos($value['type'], "_hidden") !== false) {
							$value['type'] = str_replace("_hidden", "", $value['type']);
							$hide = ' hide';
						}
						
						$value['type'] = str_replace("_default", "", $value['type']); //FIXME WTF
						
						$controlGroups[$cellId]['html'][$key] .= "<table class=\"editTable$hide\"" . ($field ? " id=\"{$this->resource}_container_$field\"" : "") . "><tr valign=\"top\"><td class=\"eFirstCell\" " . ($this->firstColWidth ? "style=\"width:{$this->firstColWidth};\"" : "") . ">";
						if ($value['req']) {
							$controlGroups[$cellId]['html'][$key] .= "<span class=\"requiredStar\">*</span>";
						}
						$controlGroups[$cellId]['html'][$key] .= $value['name'] . "</td><td" . ($field ? " id=\"{$this->resource}_cell_$field\"" : "") . ">";

						if ($value['type'] == 'protect' || $value['type'] == 'protected') {
							/*if (strpos($value['type'], '_email') !== false) {
								$this->HTML .= "<span id='".$fieldId."' ".$attrs."><a href='mailto:".$value['default']."'>".$value['default']."</a></span>";
							} elseif (strpos($value['type'], '_html') !== false) {
								$this->HTML .= "<span id='".$fieldId."' ".$attrs.">" . htmlspecialchars_decode($value['default']) . "</span>";
							} else {*/
								$controlGroups[$cellId]['html'][$key] .= "<span id=\"$fieldId\" {$attrs}>" . $value['default'] . "</span>";
							//}
						} elseif ($value['type'] == 'custom') {
							$controlGroups[$cellId]['html'][$key] .= $attrs;
						} elseif ($value['type'] == 'text' || $value['type'] == 'edit') {
							if ($this->readOnly) {
								$controlGroups[$cellId]['html'][$key] .= $value['default'];
							} else {
								$controlGroups[$cellId]['html'][$key] .= "<input class=\"input\" id=\"".$fieldId."\" type=\"text\" name=\"control[$field]\" ".$attrs." value=\"{$value['default']}\">";
							}
						} elseif ($value['type'] == 'number') {
							if ($this->readOnly) {
								$controlGroups[$cellId]['html'][$key] .= $value['default'];
							} else {
								$controlGroups[$cellId]['html'][$key] .= "<input class=\"input\" id=\"".$fieldId."\" type=\"text\" name=\"control[$field]\" ".$attrs." value=\"".$value['default']."\" onkeypress='return checkInt(event);'>";
							}
						} elseif ($value['type'] == 'file') {
							$controlGroups[$cellId]['html'][$key] .= "<input class=\"input\" id=\"".$fieldId."\" type=\"file\" name=\"control[$field]\" ".$attrs.">";
						} elseif ($value['type'] == 'link') {
							$controlGroups[$cellId]['html'][$key] .= "<span id='".$fieldId."' ".$attrs."><a href='".$value['default']."'>{$value['default']}</a></span>";
						} elseif ($value['type'] == 'search') {
							$controlGroups[$cellId]['html'][$key] .= '<input id="' . $fieldId . '" type="hidden" name="control[' . $field . ']" value="' . $value['default'] . '"/>';
						} elseif ($value['type'] == 'date' || $value['type'] == 'datetime') {
							if ($this->readOnly) {
								$day	= substr($value['default'], 8, 2);
								$month 	= substr($value['default'], 5, 2);
								$year 	= substr($value['default'], 0, 4);
								$insert = str_replace("dd", $day, strtolower($this->date_mask));
								$insert = str_replace("mm", $month, $insert);
								$insert = str_replace("yyyy", $year, $insert);
								$insert = str_replace("yy", $year, $insert);
								if ($value['type'] == 'datetime') {
									$h = substr($value['default'], 11, 2);
									$mi = substr($value['default'], 14, 2);
									$insert .= " $h:$mi";
								}
								$controlGroups[$cellId]['html'][$key] .= $insert;
							} else {
								$prefix = $fieldId;
								$beh = 'onblur="edit.dateBlur(\'' . $prefix . '\')" onkeypress="return checkInt(event);" onkeyup="edit.dateKeyup(\'' . $prefix . '\', this)"';
								$day	= '<input class="input" type="text" size="1" maxlength="2" autocomplete="OFF" id="' . $prefix . '_day" value="' . substr($value['default'], 8, 2) . '" ' . $beh . '/>';
								$month 	= '<input class="input" type="text" size="1" maxlength="2" autocomplete="OFF" id="' . $prefix . '_month" value="' . substr($value['default'], 5, 2) . '" ' . $beh . '/>';
								$year 	= '<input class="input" type="text" size="3" maxlength="4" autocomplete="OFF" id="' . $prefix . '_year" value="' . substr($value['default'], 0, 4) . '" ' . $beh . '/>';
								$insert = str_replace("dd", $day, strtolower($this->date_mask));
								$insert = str_replace("mm", $month, $insert);
								$insert = str_replace("yyyy", $year, $insert);
								$insert = str_replace("yy", $year, $insert);

								$tpl = new Templater2('core2/html/' . THEME . '/edit/datetime.tpl');
								$tpl->assign('[dt]', $insert);
								$tpl->assign('[prefix]', $prefix);
								$tpl->assign('name=""', 'name="control[' . $field . ']"');
								$tpl->assign('value=""', 'value="' . $value['default'] . '"');
								if ($value['type'] == 'datetime') {
									$h = substr($value['default'], 11, 2);
									$mi = substr($value['default'], 14, 2);
									$beh = 'onblur="edit.dateBlur(\'' . $prefix . '\')" onkeypress="return checkInt(event);" onkeyup="edit.timeKeyup(\'' . $prefix . '\', this)"';
									$tpl->datetime->assign('[h]', $h);
									$tpl->datetime->assign('[i]', $mi);
									$tpl->datetime->assign('onblur=""', $beh);
								}
								$controlGroups[$cellId]['html'][$key] .= $tpl->parse();
								if ($value['in']) {
									$controlGroups[$cellId]['html'][$key] .= "<script>edit.ev['{$prefix}'] = " . json_encode($value['in']) . ";</script>";
								} else {
									$controlGroups[$cellId]['html'][$key] .= "<script>delete edit.ev['{$prefix}'];</script>";
								}
								if ($value['type'] == 'date') {
									$controlGroups[$cellId]['html'][$key] .= "<script>edit.create_date('$prefix');</script>";
								} else {
									$controlGroups[$cellId]['html'][$key] .= "<script>edit.create_datetime('$prefix');</script>";
								}

							}
						} elseif ($value['type'] == 'daterange') {
							$dates = explode(" - ", $value['default']);
							//echo "<pre>"; print_r($value['default']); die;
							if ($this->readOnly) {
								$day	= substr($dates[0], 8, 2);
								$month 	= substr($dates[0], 5, 2);
								$year 	= substr($dates[0], 0, 4);
								$insert = str_replace("dd", $day, strtolower($this->date_mask));
								$insert = str_replace("mm", $month, $insert);
								$insert = str_replace("yyyy", $year, $insert);
								$insert = str_replace("yy", $year, $insert);
								$controlGroups[$cellId]['html'][$key] .= $insert;
								$day	= substr($dates[1], 8, 2);
								$month 	= substr($dates[1], 5, 2);
								$year 	= substr($dates[1], 0, 4);
								$insert = str_replace("dd", $day, strtolower($this->date_mask));
								$insert = str_replace("mm", $month, $insert);
								$insert = str_replace("yyyy", $year, $insert);
								$insert = str_replace("yy", $year, $insert);
								$controlGroups[$cellId]['html'][$key] .= ' - ' . $insert;
							} else {
								$prefix = $fieldId;
								for ($i = 0; $i <= 1; $i++) {
									$beh = 'onblur="dateBlur(\'' . $prefix . '\')" onkeypress="return checkInt(event);" onkeyup="dateKeyup(\'' . $prefix . '\', this)"';
									$controlGroups[$cellId]['html'][$key] .= "<div style=\"float:left\"><table><tr>";
									$day	= '<input class="input" type="text" size="1" maxlength="2" autocomplete="OFF" id="' . $prefix . '_day" value="' . substr($dates[$i], 8, 2) . '" ' . $beh . '/>';
									$month 	= '<input class="input" type="text" size="1" maxlength="2" autocomplete="OFF" id="' . $prefix . '_month" value="' . substr($dates[$i], 5, 2) . '" ' . $beh . '/>';
									$year 	= '<input class="input" type="text" size="3" maxlength="4" autocomplete="OFF" id="' . $prefix . '_year" value="' . substr($dates[$i], 0, 4) . '" ' . $beh . '/>';
									$insert = str_replace("dd", $day, strtolower($this->date_mask));
									$insert = str_replace("mm", $month, $insert);
									$insert = str_replace("yyyy", $year, $insert);
									$insert = str_replace("yy", $year, $insert);

									$tpl = new Templater2('core2/html/' . THEME . '/edit/datetime.tpl');
									$tpl->assign('[dt]', $insert);
									$tpl->assign('[prefix]', $prefix);
									$tpl->assign('name=""', 'name="control[' . $field . ']"');
									$tpl->assign('value=""', 'value="' . $dates[$i] . '"');

									$controlGroups[$cellId]['html'][$key] .= "<td style=\"padding:0\">{$tpl->parse()}</td>";
									$controlGroups[$cellId]['html'][$key] .= "</tr></table><script>edit.create_date('$prefix');</script></div>";
									if ($i == 0) {
										$prefix .= '_tru';
										$field .= '%tru';
										$controlGroups[$cellId]['html'][$key] .= '<div style="float:left;width:20px;"> <> </div>';
									}
								}
							}
						} elseif ($value['type'] == 'password') {
							if ($this->readOnly) {
								$controlGroups[$cellId]['html'][$key] .= "*****";
							} else {
								$disabled = '';
								$change = '';
								if ($value['default']) {
									$disabled = ' disabled="disabled" ';
									$change = '<input class="buttonSmall" type="button" onclick="edit.changePass(\'' . $fieldId . '\')" value="изменить"/>';
								}
								$controlGroups[$cellId]['html'][$key] .= "<input $disabled class=\"input\" id=\"" . $fieldId . "\" type=\"password\" name=\"control[$field]\" " . $attrs . " value=\"{$value['default']}\"/>";
								$controlGroups[$cellId]['html'][$key] .= " повторите <input $disabled class=\"input\" id=\"" . $fieldId . "2\" type=\"password\" name=\"control[$field%re]\" />".
								$change;
							}
						} elseif ($value['type'] == 'textarea') {
							if ($this->readOnly) {
								$controlGroups[$cellId]['html'][$key] .= "<pre><span>" . htmlspecialchars_decode($value['default']) . "</span></pre>";
							} else {
								$controlGroups[$cellId]['html'][$key] .= "<textarea id=\"" . $fieldId . "\" name=\"control[$field]\" ".$attrs.">{$value['default']}</textarea>";
							}
						}
						if (strpos($value['type'], 'fck') === 0) {
							if ($this->readOnly) {
								$controlGroups[$cellId]['html'][$key] .= "<div style=\"border:1px solid silver;width:100%;height:300px;overflow:auto\">" . htmlspecialchars_decode($value['default']) . "</div>";
							} else {
								$params = explode("_", $value['type']);

								if (in_array("basic", $params)) {
									$this->MCEConf['theme'] = 'advanced';
									$this->MCEConf['theme_advanced_buttons1'] = "bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,styleselect,formatselect,|,table,removeformat,code";
									$this->MCEConf['theme_advanced_buttons2'] = "";
									$this->MCEConf['theme_advanced_buttons3'] = "";
									$this->MCEConf['theme_advanced_buttons4'] = "";

								} elseif (in_array("basic2", $params)) {
									$this->MCEConf['theme'] = 'advanced';
									$this->MCEConf['theme_advanced_buttons1'] = "undo,redo,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,formatselect,|,table,link,unlink,anchor,image,cleanup,removeformat,code";
									$this->MCEConf['theme_advanced_buttons2'] = "";
									$this->MCEConf['theme_advanced_buttons3'] = "";
									$this->MCEConf['theme_advanced_buttons4'] = "";
								} elseif (in_array("simple", $params)) {
									$this->MCEConf['theme'] = 'simple';
								}

								//$this->MCEConf['document_base_url'] = "/" . trim(VPATH, "/") . "/";
								$mce_params = Zend_Json::encode($this->MCEConf);

								$id = "template_content" . $this->main_table_id . $key;
								$controlGroups[$cellId]['html'][$key] .= "<textarea id=\"" . $id . "\" name=\"control[$field]\" ".$attrs.">{$value['default']}</textarea>";
								$onload .= "mceSetup('" . $id . "', $mce_params);";
								$PrepareSave .= "document.getElementById('" . $id . "').value = tinyMCE.get('" . $id . "').getContent();";
							}
						} elseif ($value['type'] == 'radio') {
							$temp = array();
							if (is_array($this->selectSQL[$select])) {
								foreach ($this->selectSQL[$select] as $k => $v) {
									$temp[] = array($k, $v);
								}
							} else {
								if (isset($arr[0])) {
									$sql = $this->replaceTCOL($arr[0], $this->selectSQL[$select]);
								} else {
									$sql = $this->selectSQL[$select];
								}
								$data = $this->db->fetchAll($sql);
								foreach ($data as $values) {
									$temp[] = array(current($values), end($values));
								}
							}
							//READONLY FORK
							if ($this->readOnly) {
								foreach ($temp as $row) {
									if ($row[0] == $value['default']) {
										$controlGroups[$cellId]['html'][$key] .= $row[1];
										break;
									}
								}
							} else {
								foreach ($temp as $row) {
									$id = $this->main_table_id . rand();
									$controlGroups[$cellId]['html'][$key] .= "<label><input id=\"$id\" type=\"radio\" value='" . $row[0] . "' name=\"control[$field]\"";
									if ($row[0] == $value['default']) {
										$controlGroups[$cellId]['html'][$key] .= " checked=\"checked\"";
									}
									if (is_array($row[1])) {
										$row[1] = $row[1]['value'];
									}
									$controlGroups[$cellId]['html'][$key] .= " onclick=\"radioClick(this)\" {$attrs} />{$row[1]}</label>&nbsp;&nbsp;";
								}
							}
							$select++;
						} elseif ($value['type'] == 'checkbox') {
							$temp = array();
							if (is_array($this->selectSQL[$select])) {
								foreach ($this->selectSQL[$select] as $k=>$v) {
									$temp[] = array($k, $v);
								}
							} else {
								$data = $this->db->fetchAll($this->replaceTCOL($arr[0], $this->selectSQL[$select]));
								foreach ($data as $values) {
									$temp[] = array(current($values), end($values));
								}
							}
							$temp1 = is_array($value['default']) ? $value['default'] : explode(",", $value['default']);
							if ($this->readOnly) {
								foreach ($temp as $row) {
									if (in_array($row[0], $temp1)) {
										$controlGroups[$cellId]['html'][$key] .= "<div>{$row[1]}</div>";
									}
								}
							} else {
								foreach ($temp as $row) {
									$controlGroups[$cellId]['html'][$key] .= "<label><input type=\"checkbox\" value=\"{$row[0]}\" name=\"control[$field][]\"";
									if (in_array($row[0], $temp1)) {
										$controlGroups[$cellId]['html'][$key] .= " checked=\"checked\"";
									}
									$controlGroups[$cellId]['html'][$key] .= " {$attrs}/>";
									if (is_array($row[1])) {
										$row[1] = $row[1]['value'];
									}
									$controlGroups[$cellId]['html'][$key] .= $row[1] . "</label>";
								}
							}
							$select++;
						} elseif ($value['type'] == 'checkbox2') {
							$temp = array();
							if (is_array($this->selectSQL[$select])) {
								foreach ($this->selectSQL[$select] as $k=>$v) {
									$temp[] = array($k, $v);
								}
							} else {
								$data = $this->db->fetchAll($this->replaceTCOL($arr[0], $this->selectSQL[$select]));
								foreach ($data as $values) {
									$temp[] = array(current($values), end($values));
								}
							}
							$temp1 = is_array($value['default']) ? $value['default'] : explode(",", $value['default']);
							if ($this->readOnly) {
								foreach ($temp as $row) {
									if (in_array($row[0], $temp1)) {
										$controlGroups[$cellId]['html'][$key] .= "<div>{$row[1]}</div>";
									}
								}
							} else {
								foreach ($temp as $row) {
									$controlGroups[$cellId]['html'][$key] .= "<div><label><input type=\"checkbox\" value=\"{$row[0]}\" name=\"control[$field][]\"";
									if (in_array($row[0], $temp1)) {
										$controlGroups[$cellId]['html'][$key] .= " checked=\"checked\"";
									}
									$controlGroups[$cellId]['html'][$key] .= " {$attrs}/>";
									if (is_array($row[1])) {
										$row[1] = $row[1]['value'];
									}
									$controlGroups[$cellId]['html'][$key] .= $row[1] . "</label></div>";
								}
							}
							$select++;
						}
						if ($value['type'] == 'select' || $value['type'] == 'list' || $value['type'] == 'list_hidden' || $value['type'] == 'multilist') {
							$temp = array();

							if (is_array($this->selectSQL[$select])) {
								foreach ($this->selectSQL[$select] as $k => $v) {
									if (is_array($v)) {
										$temp[] = array_values($v);
									} else {
										$temp[] = array($k, $v);
									}
								}
							} else {
								if (isset($arr[0])) {
									$sql = $this->replaceTCOL($arr[0], $this->selectSQL[$select]);
								} else {
									$sql = $this->selectSQL[$select];
								}
								$data = $this->db->fetchAll($sql);
								foreach ($data as $values) {
									$temp[] = array_values($values);
								}
							}
							if (!is_array($value['default'])) {
								$value['default'] = explode(",", $value['default']);
							}
							if ($this->readOnly) {
								$out = '';
								foreach ($temp as $row) {
									$real_value = explode('"', $row[0]);
									$real_value = $real_value[0];
									if (in_array($real_value, $value['default'])) {
										$out = $row[1];
										break;
									}
								}
								$controlGroups[$cellId]['html'][$key] .= $out;
							} else {
								$controlGroups[$cellId]['html'][$key] .= "<select id=\"" . $fieldId . "\" name=\"control[$field]" . ($value['type'] == 'multilist' ? '[]" multiple="multiple"' : '"') . " {$attrs}>";
								$gr = "";
								foreach ($temp as $row) {
									if ((!isset($row[2]) || !$row[2]) && !is_array($row[1])) {
										$temp2 = explode(":::", $row[1]);
										$row[2] = isset($temp2[1]) ? $temp2[1] : '';
										$row[1] = $temp2[0];
									}
									if (isset($row[2]) && $row[2] && $gr != $row[2]) {
										if ($gr) $controlGroups[$cellId]['html'][$key] .= "</optgroup>";
										$controlGroups[$cellId]['html'][$key] .= "<optgroup label=\"{$row[2]}\">";
										$gr = $row[2];
									}
									$selected = "";
									$real_value = explode('"', $row[0]);
									$real_value = $real_value[0];
									if (in_array($real_value, $value['default'])) {
										$selected = 'selected="selected"';
									}

									if (is_array($row[1])) {
										$row[1] = $row[1]['value'];
									}
									$controlGroups[$cellId]['html'][$key] .= '<option value="' . $row[0] . '" ' . $selected . '>' . $row[1] . '</option>';
								}
								if ($gr) $controlGroups[$cellId]['html'][$key] .= "</optgroup>";
								$controlGroups[$cellId]['html'][$key] .= "</select>";
							}
							$select++;
						} elseif ($value['type'] == 'xfile' || $value['type'] == 'xfiles') {
							if ($this->readOnly) {
								$files = $this->db->fetchAll("SELECT id, filename FROM `{$this->table}_files` WHERE refid=?", $refid);
								if ($files) {
									foreach ($files as $value) {
										$controlGroups[$cellId]['html'][$key] .= "<div><a href='index.php?module=admin&loc=core&action=handler&fileid={$value['id']}&t={$this->table}'>{$value['filename']}</a></div>";
									}
								} else {
									$controlGroups[$cellId]['html'][$key] .= '<i>нет прикрепленных файлов</i>';
								}
							} else {
								$this->HTML = str_replace('[_ACTION_]', 'index.php?module=admin&loc=core&action=upload', $this->HTML);
								$params = explode("_", $value['type']);
								$auto = false;
								$ft = '';
								$options = array('dataType' => 'json');
								if (in_array("auto", $params)) {
									$options['autoUpload'] = true;
								}
								if (in_array("xfiles", $params)) {
									$xfile = "xfiles";
								} elseif (in_array("xfile", $params)) {
									$xfile = "xfile";
									$options['maxNumberOfFiles'] = 1;
								}
								if (is_array($value['in'])) {
									if (!empty($value['in']['id_hash'])) {
										$options['id_hash'] = true;
									}
									if (!empty($value['in']['acceptFileTypes'])) {
										$ft = str_replace(",", "|", $value['in']['acceptFileTypes']);
										$options['acceptFileTypes'] = "_FT_";
									}
								}

								$un = $fieldId;
								$controlGroups[$cellId]['html'][$key] .= '<input type="hidden" id="' . $fieldId . '" name="control[files|' . $field . ']"/>
								<input type="hidden" id="' . $fieldId . '_del" name="control[filesdel|' . $field . ']"/>
								<div id="fileupload-' . $un . '">
							        <div class="fileupload-buttonbar">
							        	<span class="btn fileinput-button">
											<button type="button" class="delete buttonSmall">Выбрать файл' . ($xfile == 'xfiles' ? 'ы' : '') . '</button>
											<input type="file" name="files[]" ' . ($xfile == 'xfiles' ? 'multiple' : '') . '>
                						</span>';
							            if ($xfile == 'xfiles' && (!isset($options['autoUpload']) || !$options['autoUpload'])) {
							                $controlGroups[$cellId]['html'][$key] .= '<button type="submit" class="start buttonSmall hide">Загрузить все</button>';
							            }
							            $controlGroups[$cellId]['html'][$key] .= '<button type="reset" class="cancel buttonSmall" style="display:none">Отменить</button>
							            	<button type="button" class="delete buttonSmall hide">Удалить</button>';
							            if ($xfile == 'xfiles') {
											$controlGroups[$cellId]['html'][$key] .= '<input type="checkbox" class="toggle hide">';
										}
										$controlGroups[$cellId]['html'][$key] .= '
										<div class="fileupload-progress fade">
											<!-- The global progress bar -->
											<div class="progress progress-success progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100">
												<div class="bar" style="width:0%;"></div>
											</div>
											<!-- The extended global progress information -->
											<div class="progress-extended">&nbsp;</div>
										</div>
									</div>
									<!-- The table listing the files available for upload/download -->
									<table role="presentation" class="table table-striped">
										<tbody class="files" data-toggle="modal-gallery" data-target="#modal-gallery"></tbody>
									</table>
								</div>
								
<!-- The template to display files available for upload -->
<script id="template-upload" type="text/x-tmpl">
{% for (var i=0, file; file=o.files[i]; i++) { %}
    <tr class="template-upload fade">
        <td>
            <span class="preview"></span>
        </td>
        <td>
            <p class="name">{%=file.name%}</p>
            {% if (file.error) { %}
                <div><span class="label label-danger">Error</span> {%=file.error%}</div>
            {% } %}
        </td>
        <td>
            <p class="size">{%=o.formatFileSize(file.size)%}</p>
            {% if (!o.files.error) { %}
                <div class="progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar progress-bar-success" style="width:0%;"></div></div>
            {% } %}
        </td>
        <td>
            {% if (!o.files.error && !i && !o.options.autoUpload) { %}
                <button class="btn btn-primary start buttonSmall">
                    <i class="glyphicon glyphicon-upload"></i>
                    <span>Старт</span>
                </button>
            {% } %}
            {% if (!i) { %}
                <button class="btn btn-warning cancel buttonSmall">
                    <i class="glyphicon glyphicon-ban-circle"></i>
                    <span>Отмена</span>
                </button>
            {% } %}
        </td>
    </tr>
{% } %}
</script>
<!-- The template to display files available for download -->
<script id="template-download" type="text/x-tmpl">
{% for (var i=0, file; file=o.files[i]; i++) { %}
    <tr class="template-download fade">
        {% if (file.error) { %}
            <td></td>
            <td class="name"><span>{%=file.name%}</span></td>
            <td class="size"><span>{%=o.formatFileSize(file.size)%}</span></td>
            <td class="error" colspan="2"><span class="label label-important">Error</span> {%=file.error%}</td>
        {% } else { %}
            <td>
				<span class="preview">
					{% if (file.thumbnail_url) { %}
						<a href="{%=file.url%}" title="{%=file.name%}" download="{%=file.name%}" data-gallery><img src="{%=file.thumbnail_url%}"></a>
					{% } %}
				</span>
			</td>
            <td class="name">
                <a href="{%=file.' . (!empty($options['id_hash']) ? 'id_hash' : 'url') . '%}" {% if (file.thumbnail_url) { %}target="_blank" {% } %} title="{%=file.name%}" rel="{%=file.thumbnail_url&&\'gallery\'%}" download="{%=file.name%}">{%=file.name%}</a>
            </td>
            <td class="size"><span>{%=o.formatFileSize(file.size)%}</span></td>
        {% } %}
        <td class="delete" data-service="{%=file.delete_service%}" data-id="{%=file.delete_id%}">
            <button class="btn buttonSmall">Удалить</button>
            <input class="toggle" type="checkbox" name="delete" value="1">
        </td>
    </tr>
{% } %}
</script>';
$controlGroups[$cellId]['html'][$key] .= "<script>
edit.xfiles['$un'] = {};
$(function () {
    'use strict';

    // Initialize the jQuery File Upload widget:
    $('#fileupload-{$un}').fileupload(" . str_replace('"_FT_"', "/(\.|\/)($ft)$/i", json_encode($options)) . ");
    $('#fileupload-{$un}').bind('fileuploaddone', function (e, data) {
    	var f = data.response().result.files[0];
    	$('#fileupload-$fieldId div.fileupload-buttonbar button.delete').removeClass('hide');
    	$('#fileupload-$fieldId div.fileupload-buttonbar input.toggle').removeClass('hide');
		edit.xfiles['$un'][f.name + '###' + f.size + '###' + f.type] = f;
    	var res = [];
    	for (var k in edit.xfiles['$un']) {
    		res.push(k);
    	};
    	$('#$fieldId').val(res.join('|'));
    }).bind('fileuploaddestroy', function (e, data) {
		var d = data.context.children('.delete');
		var ds = d.attr('data-service');
		var di = d.attr('data-id');
		if (ds) {
			delete edit.xfiles['$un'][ds]
			var res = [];
			for (var k in edit.xfiles['$un']) {
				res.push(k);
			};
			$('#{$fieldId}').val(res.join('|'));
		}
		if (di) {
			$('#{$fieldId}_del').val($('#{$fieldId}_del').val() + ',' + di);
		}
	}).bind('fileuploadchange', function (e, data) {
		$('#fileupload-$fieldId div.fileupload-buttonbar button.start').removeClass('hide');
	});

    // Load existing files:
	//$('#fileupload-{$un}').addClass('fileupload-processing');
	$.ajax({
		// Uncomment the following to send cross-domain cookies:
		//xhrFields: {withCredentials: true},
		url: $('#{$this->main_table_id}_mainform').prop('action') + '&refid=$refid&tbl={$this->table}&f=$field',
		dataType: 'json',
		context: $('#fileupload-{$un}')[0]
	}).always(function () {
		//$(this).removeClass('fileupload-processing');
	}).done(function (result) {
		if (result.files && result.files[0]) {
			$('#fileupload-$fieldId div.fileupload-buttonbar button.delete').removeClass('hide');
			$('#fileupload-$fieldId div.fileupload-buttonbar input.toggle').removeClass('hide');
		}
		$(this).fileupload('option', 'done').call(this, null, {result: result});
	});

});
</script>";

							}
						} elseif ($value['type'] == 'modal') {
							if ($this->readOnly) {
								$controlGroups[$cellId]['html'][$key] .= !empty($this->modal[$modal]['value']) ? $this->modal[$modal]['value'] : '';
							} else {
								if (is_array($value['in'])) {
									$options = $value['in']['options'];
									$temp = " ";
									foreach ($value['in'] as $attr => $val) {
										if ($attr == 'options') continue;
										$temp .= $attr . '="' . $val . '" ';
									}
									$attrs = $temp;
								} else {
									$options = '';
								}

								$modalHTML = '';
								if (!empty($this->modal[$modal]['iframe'])) {

									if (!is_array($this->modal[$modal]['iframe'])) {
										$this->modal[$modal]['iframe'] = array();
									}
									$modalHTML .= '<iframe ';
									foreach ($this->modal[$modal]['iframe'] as $attr => $attr_value) {
										if ($attr == 'src') {
											$options = ltrim($options, '{');
											$options = '{onShow: function (dialog) {
												document.getElementById(\'modal_' . $field . '\').childNodes[0].src=\'' . $attr_value . '\';
											},' . $options;
											$attr_value = '';
										}
										$modalHTML .= $attr . '="' . $attr_value . '" ';
									}
									$modalHTML .= '></iframe>';
								} elseif (!empty($this->modal[$modal]['html'])) {
									$modalHTML = $this->modal[$modal]['html'];
								}
								$controlGroups[$cellId]['html'][$key] .= '<table><tr><td>';
								if (!empty($this->modal[$modal]['textarea'])) {
									$controlGroups[$cellId]['html'][$key] .= '<textarea id="' . $fieldId . '_text" class="input" ' . $attrs . '>' . (!empty($this->modal[$modal]['value']) ? $this->modal[$modal]['value'] : '') . '</textarea>';
								} else {
									$controlGroups[$cellId]['html'][$key] .= '<input id="' . $fieldId . '_text" class="input"  type="text" ' . $attrs . ' value="' . (!empty($this->modal[$modal]['value']) ? $this->modal[$modal]['value'] : '') . '"/>';
								}
								$controlGroups[$cellId]['html'][$key] .= '</td><td><input type="button" class="buttonSmall" value="' . $this->classText['MODAL_BUTTON'] . '"
									onclick="' . (!empty($this->modal[$modal]['script']) ? trim($this->modal[$modal]['script'], ';') . ';' : '') . '$(\'#modal_' . $field . '\').modal(' . $options . ');"/>' .
									'<input id="' . $fieldId . '" name="control[' . $field . ']" type="hidden" value="' . (!empty($this->modal[$modal]['key']) ? $this->modal[$modal]['key'] : $value['default']) . '"/>'.
									'<script>var xxxx=""</script>' .
									'</td></tr></table>' .
									'<div id="modal_' . $field . '" style="display:none;" class="modal_window">' . $modalHTML . '</div>';
							}
							$modal++;
						}
						
						if (!empty($value['out'])) {
							$controlGroups[$cellId]['html'][$key] .= $value['out'];
						}
						$controlGroups[$cellId]['html'][$key] .= '</td></tr></table>';
					}
				}
			}
			
			//echo "<PRE>";print_r($controlGroups);echo"</PRE>";die();
			$fromReplace = array();
			$toReplace = array();
			$groupAction = false;
			foreach ($controlGroups as $cellId => $value) {
				$fromReplace[] = "[$cellId]";
				$html = '';
				if (!empty($value['group'])) {
					$groupAction = true;
					$html .= '<div class="accordion">';
					$ingroup = false;
					foreach ($value['html'] as $key => $control) {
						
						foreach ($value['group'] as $group) {
							if ($group['key'] == $key) {
								if ($ingroup) {
									$html .= '</div>';
								}
								$html .= '<h3><a href="#">' . $group['name'] . '</a></h3><div>';
								$ingroup = true;
								break;
							}
						}
						$html .= $control;
					}
					$html .= '</div></div>';
				} else {
					foreach ($value['html'] as $control) {
						$html .= $control;
					}
				}
				$toReplace[] = $html;
			}
			//echo "<textarea>";print_r($toReplace);echo"</textarea>";//die();
			$this->HTML .= str_replace($fromReplace, $toReplace, $this->template);
			if ($groupAction) {
				// if any GROUPS exists enable switcher
				$this->HTML .= '<script>
					  $(".accordion h3").click(function() {
							$(this).next().toggle();
							return false;
						});
					  </script>';
			}
		}
		//buttons area
		$this->HTML .= "<table width=\"100%\">
							<tr>
								<td align=\"right\">
									<div>";
		if (isset($this->buttons[$this->main_table_id]) && is_array($this->buttons[$this->main_table_id])) {			
			foreach ($this->buttons[$this->main_table_id] as $value) {
				if (!empty($value['value'])) {
					$this->HTML .= $this->button($value['value'], 'button', $value['action']);
				} elseif (!empty($value['html'])) {
					$this->HTML .= $value['html'];
				}
			}
		}
		
		if (!$this->readOnly) {
			$this->HTML .= $this->button($this->classText['SAVE'], "submit", "this.form.onsubmit();return false;");
		}
		$this->HTML .= 	"</div>
							</td>
						</tr>
					</table>";
		if (!$this->readOnly) {
			$this->HTML .= 	"</form><script>function PrepareSave() {" . $PrepareSave . "} $onload </script>";
		}
		$this->HTML .= 	"</br>";
	}

	/**
	 * @return mixed
	 */
	public function showTable() {
		if ($this->acl->read_all || $this->acl->read_owner) {
				
			$this->HTML .= '<div id="' . $this->main_table_id . '_error" class="error" ' . ($this->error ? 'style="display:block"' : '') . '>' . $this->error . '</div>';
			$this->makeTable();
			$this->HTML = str_replace('[_ACTION_]', '', $this->HTML);
			echo $this->HTML;
		} else {
			$this->noAccess();
		}
		return;
	}

	/**
	 *
	 */
	private function noAccess() {
		echo $this->classText['noReadAccess'];
	}

	/**
	 * @param $row
	 * @param $tcol
	 * @return string
	 */
	private function replaceTCOL($row, $tcol) {
		$temp = explode("TCOL_", $tcol);
		$tres = "";
		foreach ($temp as $tkey=>$tvalue) {
			if ($tkey == 0) {
				$tres .= $tvalue;
			} else {
				$tres .= $row[substr($tvalue, 0, 2) * 1] . substr($tvalue, 2);
			}
		}
		return $tres;
	}

	/**
	 * @param $action
	 */
	public function setAction($action) {
		$this->action = $action;
	}
	
}

class cell {
	protected $controls			= array();
	protected $gr				= array();
	protected $main_table_id;
		
	public function __construct($main_table_id) {
		$this->main_table_id 	= $main_table_id;
		
	}

	/**
	 * @param $name
	 * @param $type
	 * @param string $in
	 * @param string $out
	 * @param string $default
	 * @param bool $req
	 */
	public function addControl($name, $type, $in = "", $out = "", $default = "", $req = false) {
		global $counter;
		$temp = array(
			'name' 		=> $name, 
			'type' 		=> strtolower($type), 
			'in' 		=> $in, 
			'out' 		=> $out, 
			'default' 	=> $default, 
			'req' 		=> $req
		);
		$this->controls[$this->main_table_id][$counter] = $temp;
		
		if (!empty($this->gr[$counter])) {
			$this->controls[$this->main_table_id][$counter]['group'] = $this->gr[$counter];
		}
		$counter++;
	}

	/**
	 * @param $name
	 * @param bool $collapsed
	 */
	public function addGroup($name, $collapsed = false) {
		global $counter;
		if ($collapsed) $collapsed = "*";
		if (!$counter) $counter = 0;
		$this->gr[$counter] = $collapsed . $name;
	}
    
	public function __get($name) {
        return $this->$name;
    }

	/**
	 * @param $arr
	 */
	public function appendControl($arr) {
    	global $counter;
    	if (!$counter) $counter = 0;
    	if (!empty($this->gr[$counter])) {
			$arr['group'] = $this->gr[$counter];
		}
    	$this->controls[$this->main_table_id][$counter] = $arr;
    	$counter++;
    }

	/**
	 * @param $arr
	 */
	public function setGroup($arr) {
    	foreach ($arr as $k => $v) {
    		$this->gr[$k] = $v;
    	}
    	
    }
    
}