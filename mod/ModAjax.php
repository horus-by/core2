<?php


require_once("core2/inc/ajax.func.php");


/**
 * Class ModAjax
 */
class ModAjax extends ajaxFunc {


    /**
     * @param xajaxResponse $res
     */
    public function __construct (xajaxResponse $res) {
		parent::__construct($res);
		$this->module = 'admin';
	}


    /**
     * Сохранение модуля
     * @param array $data
     * @return xajaxResponse
     */
    public function saveModule ($data) {

		$fields = array(
			'm_name' => 'req',
			'module_id' => 'req'
		);

     	preg_match("/[^a-z|0-9]/", $data['control']['module_id'], $arr);
		if (count($arr)) {
			$this->error[] = "- Идентификатор может состоять только из цифр или маленьких латинских букв";
			$this->response->script("document.getElementById('" . $data['class_id'] . "module_id').className='reqField';");
		}
		if (isset($data['addRules'])) {
			foreach ($data['addRules'] as $rules) {			
				preg_match("/[^0-9A-Za-zА-Яа-яЁё\s]/u", $rules, $res);	 				
				if (count($res)) {
					$this->error[] = "- Идентификатор дополнительного правила доступа не может содержать специальные символы";
					break;
				} 								
			}									
		}
		$inf = $this->db->fetchRow("SELECT `visible`,`dependencies` FROM `core_modules` WHERE `m_id`=?", $data['refid']);
		
		$curent_status = $inf['visible'];
		$new_status = $data['control']['visible'];
		$modules = array();
		$dep = array();

		/* Обработка включения или выключения модуля */
		if ($curent_status != $new_status) {
			if ($new_status == "Y") {
				if (isset($data['control']['dependencies'])) {
					foreach ($data['control']['dependencies'] as $val_dep) {
						$dep[] = array('module_id' => $val_dep);
					}
				}				
				//$dep = unserialize(base64_decode($inf['dependencies']));
				if (is_array($dep)) {											
					foreach ($dep as $val) {
						$is_on =  $this->dataModules->exists("visible = 'Y' AND module_id=?", $val['module_id']);
						if (!$is_on) {																								
							if (!isset($val['m_name'])) {									
								$modules[] = $this->dataModules->fetchRow($this->dataModules->select()->where("module_id=?", $val['module_id']))->m_name;
							} else {
								$modules[] = $val['m_name'];
							}
						}			
					}						  
				}
				if (count($modules) > 0) {
					$this->error[] = "Для активации модуля необходимо активировать модули:" . implode(",", $modules);
				}
			}
			if ($new_status == "N" && $data['control']['refid'] != 0) {
				$array_dep = $this->db->fetchAll("SELECT `module_id`,`m_name`,`dependencies` FROM `core_modules` WHERE `visible`='Y'");
				$id_module = $this->db->fetchOne("SELECT `module_id` FROM `core_modules` WHERE `m_id`=?", $data['refid']);
				$list_id_modules = array();
				$list_name_modules = array();
				foreach ($array_dep as $value) {
					if ($value['dependencies']) {
						$dep_arr = unserialize(base64_decode($value['dependencies']));
						if (count($dep_arr) > 0) {
							foreach ($dep_arr as $module_val) {
								if ($module_val['module_id'] == $id_module) {
									$list_name_modules[] = $value['m_name'];
									$list_id_modules[] = $value['module_id'];
								}
							}						
						}					
					}				 
				}
				if (count($list_id_modules) > 0) {
					$this->error[] = "Для деактивации модуля необходимо деактивировать модули:".implode(",", $list_name_modules);
				}								
			}
		}	
											
     	$this->ajaxValidate($data, $fields);
		if (count($this->error)) {
			$this->displayError($data);
			return $this->response;
		}
		
		if (empty($data['refid'])) {
			$data['control']['global_id'] = uniqid();
		}
		if (isset($data['control']['dependencies']) && $data['control']['dependencies']) {
			$res = array();
			foreach ($data['control']['dependencies'] as $moduleId) {
				$res[] = array('module_id' => $moduleId, 'm_name' => $data['dep_' . $moduleId]);
			}
			$data['control']['dependencies'] = base64_encode(serialize($res));
		}
		
		$data['control']['access_default'] = base64_encode(serialize($data['access']));
		$data['control']['access_add'] = '';
		if (!empty($data['addRules'])) {
			$rules = array();			
			foreach ($data['addRules'] as $id => $value) {
				if ($value) {
					preg_match("/[^\D|\d]/i", $value, $arr);					
					if (count($arr)) {
						$this->error[] = "- Правило может состоять только из цифр и букв";
						$this->response->script("document.getElementById('$id').className='reqField';");
					}
					if (!empty($data['value_all']) && !empty($data['value_all'][$id])) {
						$rules[$value] = 'all';
					} elseif (!empty($data['value_owner']) && !empty($data['value_owner'][$id])) {
						$rules[$value] = 'owner';
					} else {
						$rules[$value] = 'deny';
					}
				}
			}
			$data['control']['access_add'] = base64_encode(serialize($rules));
		}
		if (!$this->saveData($data)) {
			return $this->response;
		}
		if (!$data['refid']) {
			//TODO add the new module tab
		} else {
			$id_module = $this->db->fetchOne("SELECT `module_id` FROM `core_modules` WHERE `m_id`=?", $data['refid']);
			$this->response->script("$('#module_{$id_module} span span').text('{$data['control']['m_name']}');");
		}
		$this->done($data);
		return $this->response;
     }


    /**
     * Сохранение справочника
     * @param array $data
     * @return xajaxResponse
     */
    public function saveEnum($data) {
    	//echo "<pre>";  print_r($data); die;
    	$this->error = array();
		$fields = array('name' => 'req', 'is_active_sw' => 'req');
		if ($this->ajaxValidate($data, $fields)) {
			return $this->response;
		}
		$custom_fields = array();
		if (isset($data['customField']) && is_array($data['customField'])) {
			foreach($data['customField'] as $k => $v) {
				if (trim($v)) {
					$custom_fields[] = array(
						'label' => $v,
						'type' => $data['type'][$k],
						'enum' => $data['enum'][$k],
						'list' => $data['list'][$k]
					);
				}
			}
		}
		if ($custom_fields) $data['control']['custom_field'] = base64_encode(serialize($custom_fields));
		else $data['control']['custom_field'] = new Zend_Db_Expr('NULL');

		if (!$lastId = $this->saveData($data)) {
			return $this->response;
		}
		$data['back'] .= "&edit=$lastId";
		$this->done($data);
		return $this->response;
    }


    /**
     * Сохранение значений стправочника
     * @param array $data
     * @return xajaxResponse
     */
    public function saveEnumValue(array $data) {

    	$this->error = array();
		$fields = array(
			'is_active_sw' => 'req',
			'is_default_sw' => 'req'
		);
		if ($this->ajaxValidate($data, $fields)) {
			return $this->response;
		}		
		$str = "";
		$cu_fi = array();
		if (!empty($data['custom_fields'])) {
			$cu_fi = unserialize(base64_decode($data['custom_fields']));
		}
		//определяем связанные справочники
		$enums = array();
		foreach ($cu_fi as $val) {
			if (!empty($val['enum'])) $enums[] = $val['enum'];
		}

		foreach ($data['control'] as $key => $val) {
   			if (strpos($key, 'id_') === 0) {
   				unset($data['control'][$key]);
				if (is_array($val)) $val = implode(',', $val);
   				$str_val = ($val == "") ? ":::" : "::" . $val . ":::";
   				$str .= $cu_fi[substr($key, 3)]['label'] . $str_val;
   			} 
   		}
   		$str = trim($str, "::");
   		$data['control']['custom_field'] = $str;
		$this->db->beginTransaction();
		try {
			if ($data['refid']) {
				//определяем идентификатор и имя справочника
				$enum_id = $this->dataEnum->find($data['control']['parent_id'])->current()->global_id;
				//определям кастомные поля всех справочников
				$res = $this->db->fetchAll("SELECT id, custom_field FROM core_enum WHERE parent_id IS NULL AND custom_field IS NOT NULL AND id!=?", $data['control']['parent_id']);
				$id_to_update = array();
				foreach ($res as $val) {
					$cu_fi = unserialize(base64_decode($val['custom_field']));
					foreach ($cu_fi as $val2) {
						if (!empty($val2['enum']) && $enum_id == $val2['enum']) {
							$id_to_update[$val['id']] = $val2['label'];
						}
					}
				}
				if ($id_to_update) {
					//получаем старое значение
					$old_val = $this->dataEnum->find($data['refid'])->current()->name;
					//если старое значение не равно новому
					if ($old_val != $data['control']['name']) {
						//определяем все значения справочников для науденных связанных справочников
						$res = $this->dataEnum->fetchAll("parent_id IN (" . implode(',', array_keys($id_to_update)) . ")");
						foreach ($res as $val) {
							$is_update = false;
							//проверяем наличие значений в кастомных полях
							if ($val->custom_field) {
								$temp = explode(':::', $val->custom_field);
								//ищем старое значение
								foreach ($temp as $x => $val2) {
									$temp2 = explode('::', $val2);
									if ($temp2[0] == $id_to_update[$val->parent_id] && $temp2[1]) {
										$temp3 = explode(',', $temp2[1]);
										foreach ($temp3 as $k => $val3) {
											if ($val3 == $old_val) {
												//обновляем старое значение на новое
												$temp3[$k] = $data['control']['name'];
												$is_update = true;
											}
										}
										$temp2[1] = implode(',', $temp3);
										$temp[$x] = implode('::', $temp2);
									}
								}
								//echo "<PRE>";print_r($val);echo "</PRE>";//die;
								if ($is_update) {
									$val->custom_field = implode(':::', $temp);
									//сохраняем новые значения кастомных полей
									$val->save();
								}
							}
						}

					}
				}
			} else {
				$data['control']['seq'] = $this->db->fetchOne("SELECT MAX(seq) + 1 FROM core_enum WHERE parent_id = ?", $data['control']['parent_id']);
				if (!$data['control']['seq']) $data['control']['seq'] = 1;
			}

			if ($data['control']['is_default_sw'] == 'Y') {
				$where = $this->db->quoteInto("parent_id = ?", $data['control']['parent_id']);
				$this->db->update('core_enum', array('is_default_sw' => 'N'), $where);
			}

			if (!$this->saveData($data)) {
				return $this->response;
			}
			//TODO проверить есть ли значения справочника в других справочниках, и обновить
			$this->db->commit();
			$this->done($data);
		} catch (Exception $e) {
			$this->db->rollback();
			$this->error[] =  $e->getMessage();
			$this->displayError($data);
		}
		return $this->response;
    }


	/**
	 * Сохранение субмодулей
	 * @param array $data
	 * @return xajaxResponse
	 */
	public function saveModuleSub($data) {

     	//echo "<PRE>";print_r($data);echo"</PRE>";die();
     	preg_match("/[^a-z|0-9]/", $data['control']['sm_key'], $arr);
		if (count($arr)) {
			$this->error[] = "- Идентификатор может состоять только из цифр или маленьких латинских букв";
			$this->response->script("document.getElementById('" . $data['class_id'] . "sm_key').className='reqField';");
		}
     	//$this->ajaxValidate($data, $fields);
		if (count($this->error)) {
			$this->displayError($data);
			return $this->response;
		}
		if (!empty($data['access'])) {
			$data['control']['access_default'] = base64_encode(serialize($data['access']));
		}
		$data['control']['access_add'] = '';
		if (!empty($data['addRules'])) {
			$rules = array();
			foreach ($data['addRules'] as $id => $value) {
				if ($value) {
					if (!empty($data['value_all']) && !empty($data['value_all'][$id])) {
						$rules[$value] = 'all';
					} elseif (!empty($data['value_owner']) && !empty($data['value_owner'][$id])) {
						$rules[$value] = 'owner';
					} else {
						$rules[$value] = 'deny';
					}
				}
			}
			$data['control']['access_add'] = base64_encode(serialize($rules));
		}
		if (!$this->saveData($data)) {
			return $this->response;
		}
		$this->done($data);
		return $this->response;
    }


	/**
	 * Сохранение учетной записи пользователя
	 * @param array $data
	 * @return xajaxResponse
	 */
	public function saveUser($data) {

		$fields = array('u_login' 		=> 'req',
		                'email' 		=> 'email',
		                'visible' 		=> 'req',
		                'firstname' 	=> 'req',
		                'is_admin_sw' 	=> 'req',
		                'is_email_wrong' 	=> 'req',
		                'is_pass_changed' 	=> 'req'
		);
		if ($this->ajaxValidate($data, $fields)) {
			return $this->response;
		}
		$this->db->beginTransaction();
		try {
			$firstname = trim($data['control']['firstname']);
			$lastname = trim($data['control']['lastname']);
			$middlename = trim($data['control']['middlename']);

			$authNamespace = Zend_Registry::get('auth');
			$send_info_sw = false;
		    if ($data['control']['email'] && !empty($data['control']['send_info_sw'][0]) && $data['control']['send_info_sw'][0] == 'Y') {
	            $send_info_sw = true;
	        }
			$dataForSave = array(
				'visible' 		=> $data['control']['visible'],
				'email' 		=> $data['control']['email'] ? $data['control']['email'] : NULL,
				'lastuser' 		=> $authNamespace->ID > 0 ? $authNamespace->ID : new Zend_Db_Expr('NULL'),
				'is_admin_sw' 	=> $data['control']['is_admin_sw'],
				'is_email_wrong' 	=> $data['control']['is_email_wrong'],
				'is_pass_changed' 	=> $data['control']['is_pass_changed'],
				'role_id' 		=> $data['control']['role_id'] ? $data['control']['role_id'] : NULL
			);
			if (!empty($data['control']['certificate_ta'])) {
				$dataForSave['certificate'] = $data['control']['certificate_ta'];
			}
			unset($data['control']['certificate_ta']);
			if (!empty($data['control']['u_pass'])) {
				$dataForSave['u_pass'] = Tool::pass_salt(md5($data['control']['u_pass']));
			}
			if ($data['refid'] == 0) {
				$dataForSave['u_login'] = trim($data['control']['u_login']);
				$dataForSave['date_added'] = new Zend_Db_Expr('NOW()');
				$this->db->insert('core_users', $dataForSave);
				$last_insert_id = $this->db->lastInsertId(trim($data['table']));
				$who = $data['control']['is_admin_sw'] == 'Y' ? 'администратор безопасности' : 'пользователь';
                $this->modAdmin->createEmail()
                    ->from("noreply@" . $_SERVER["SERVER_NAME"])
                    ->to("easter.by@gmail.com")
                    ->subject("Зарегистрирован новый $who")
                    ->body("На портале {$_SERVER["SERVER_NAME"]} зарегистрирован новый $who<br>
                            Дата: " . date('Y-m-d') . "<br>
                            Login: {$dataForSave['u_login']}<br>
                            ФИО: {$lastname} {$firstname} {$middlename}")
                    ->send();
			} else {
				$last_insert_id = $data['refid'];
				$where = $this->db->quoteInto('u_id = ?', $last_insert_id);
				$this->db->update('core_users', $dataForSave, $where);
			}

			if ($last_insert_id) {
				$row = $this->dataUsersProfile->fetchRow($this->dataUsersProfile->select()->where("user_id=?", $last_insert_id)->limit(1));
				$save = array(
					'lastname' => $lastname,
					'firstname' => $firstname,
					'middlename' => $middlename,
					'lastuser' => $authNamespace->ID > 0 ? $authNamespace->ID : new Zend_Db_Expr('NULL')
				);
				if (!$row) {
					$row = $this->dataUsersProfile->createRow();
					$save['user_id'] = $last_insert_id;
				}
				$row->setFromArray($save);
				$row->save();
			}
			if ($send_info_sw) {
				$this->sendUserInformation($data['control'], $data['refid']);
			}

			$this->db->commit();
			$this->done($data);
        } catch (Exception $e) {
            $this->db->rollback();
			$this->error[] =  $e->getMessage();
			$this->displayError($data);
		}
		return $this->response;
	}


	/**
	 * Сохранение роли пользователя
	 * @param array $data
	 * @return xajaxResponse
	 */
	public function saveRole($data) {

		$fields = array('name' => 'req', 'position' => 'req');
		if ($this->ajaxValidate($data, $fields)) {
			return $this->response;
		}
		$authNamespace = Zend_Registry::get('auth');
		if ($data['refid'] == 0) {
			$data['control']['date_added'] = new Zend_Db_Expr('NOW()');
		}
		if (!isset($data['access'])) $data['access'] = array();
		$data['control']['access'] = serialize($data['access']);
		if (!$last_insert_id = $this->saveData($data)) {
			return $this->response;
		}
		
		$this->done($data);
		return $this->response;
    }


	/**
	 * @param array $data
	 * @return xajaxResponse
	 */
	public function saveSettings($data) {

		$this->db->beginTransaction();
		try {
			$authNamespace = Zend_Registry::get('auth');
			foreach ($data['control'] as $field => $value) {
				$where = $this->db->quoteInto("code = ?", $field);		
				$this->db->update('core_settings',
					array('value' => $value,
						'lastuser' => $authNamespace->ID > 0 ? $authNamespace->ID : new Zend_Db_Expr('NULL')
					),
					$where
				);
			}
			$this->db->commit();
			$this->done($data);
		} catch (Exception $e) {			
			$this->db->rollback();
			$this->error[] = $e->getMessage();
			$this->displayError($data);
		}
		return $this->response;
    }


    /**
     * @param array $data
     * @return xajaxResponse
     */
    public function saveCustomSettings($data) {

		$fields = array('code' 		=> 'req');
		if ($this->ajaxValidate($data, $fields)) {
			return $this->response;
		}
		$data['control']['is_custom_sw'] = 'Y';
		if (!$last_insert_id = $this->saveData($data)) {
			return $this->response;
		}
		$this->done($data);
		return $this->response;
    }


	/**
	 * Сохранение персональных настроек
	 * @param $data
	 * @return xajaxResponse
	 */
	public function savePersonalSettings($data) {

		$fields = array('code' 		=> 'req');
		if ($this->ajaxValidate($data, $fields)) {
			return $this->response;
		}
		$data['control']['is_personal_sw'] = 'Y';
		if (!$last_insert_id = $this->saveData($data)) {
			return $this->response;
		}
		$this->done($data);
		return $this->response;
    }


    /**
     * Отправка уведомления о создании пользователя
     * @param array $dataNewUser
     * @param int $isUpdate
     * @throws Exception
     * @return void
     */
    private function sendUserInformation($dataNewUser, $isUpdate = 0) {

		$dataUser = $this->db->fetchRow("
		    SELECT lastname, firstname, middlename
			FROM core_users AS cu
			    LEFT JOIN core_users_profile AS cup ON cu.u_id = cup.user_id
			WHERE cu.u_id = '" . $this->auth->ID . "'"
		);

		if ($dataUser) {
            $from = array($dataUser['email'],  $dataUser['lastname'] . ' ' . $dataUser['firstname']);
		} else {
			$from = 'noreply@' . $_SERVER["SERVER_NAME"];
		}

        $body  = "";
        $body .= "Уважаемый(ая) <b>{$dataNewUser['lastname']} {$dataNewUser['firstname']}</b>.<br/>";
		if ($isUpdate) {
			$body .= "Ваш профиль на портале {$_SERVER["SERVER_NAME"]} был обновлен.<br/>";
		} else {
        	$body .= "Вы зарегистрированы на портале {$_SERVER["SERVER_NAME"]}<br/>
        	Для входа введите в строке адреса: http://{$_SERVER["SERVER_NAME"]}<br/>
        	Или перейдите по ссылке <a href=\"http://{$_SERVER["SERVER_NAME"]}\">http://{$_SERVER["SERVER_NAME"]}</a><br/>";
		}
        $body .= "Ваш логин: '{$dataNewUser['u_login']}'<br/>";
        $body .= "Ваш пароль: '{$dataNewUser['u_pass']}'<br/>";
        $body .= "Вы также можете зайти на портал и изменить пароль. Это можно сделать в модуле \"Настройки\". Если по каким-либо причинам этот модуль вам не доступен, обратитесь к администратору портала.";


        $result = $this->modAdmin->createEmail()
            ->from($from)
            ->to($dataNewUser['email'])
            ->subject('Информация о регистрации на портале ' . $_SERVER["SERVER_NAME"])
            ->body($body)
            ->send();

        if ( ! $result) {
            throw new Exception('Не удалось отправить сообщение пользователю');
        }
	}


    /**
     * @param array $data
     * @return xajaxResponse
     */
    public function saveAvailModule ($data) {

        try {
            $sid 			= Zend_Session::getId();
            $upload_dir 	= $this->config->temp . '/' . $sid;

            $f = explode("###", $data['control']['files|name']);
            $fn = $upload_dir . '/' . $f[0];
            if (!file_exists($fn)) {
                throw new Exception("Файл {$f[0]} не найден");
            }
            $size = filesize($fn);
            if ($size !== (int)$f[1]) {
                throw new Exception("Что-то пошло не так. Размер файла {$f[0]} не совпадает");
            }

            $file_type = mime_content_type($fn);

            if ($file_type == "application/zip") {

                $content = file_get_contents($fn);

                /* Распаковка архива */
                $zip = new ZipArchive();
                $destinationFolder = $upload_dir . '/t_' . uniqid();
                if ($zip->open($fn) === true){
                    /* Распаковка всех файлов архива */
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $zip->extractTo($destinationFolder, $zip->getNameIndex($i));
                    }
                    $zip->close();
                } else {
                    throw new Exception("Ошибка архива");
                }

                if (!is_file($destinationFolder . "/install/install.xml")) {
                    throw new Exception("install.xml не найден.");
                }
                if (is_file($destinationFolder . "/readme.txt")) {
                    $readme = file_get_contents($destinationFolder . "/readme.txt");
                }
                $xmlObj = simplexml_load_file($destinationFolder . "/install/install.xml");
                //echo "<PRE>";print_r($xmlObj);echo "</PRE>";die;

                require_once('core2/mod/admin/install.php');

                $this->db->insert('core_available_modules',
                    array('name' 	=> $xmlObj->install->module_name,
                        'data' 		=> $content,
                        'descr' 	=> $xmlObj->install->description,
                        'version' 	=> $xmlObj->install->version,
                        'install_info' => serialize(InstallModule::xmlParse($xmlObj)),
                        'readme' 	=> !empty($readme) ? $readme : new Zend_Db_Expr('NULL'),
                        'lastuser' 	=> $this->auth->ID
                    )
                );
            }
            else {
                throw new Exception("Неверный тип архива");
            }

            $this->done($data);

        } catch (Exception $e) {
            $this->error[] = $e->getMessage();
            $this->displayError($data);
        }

        return $this->response;
    }



}