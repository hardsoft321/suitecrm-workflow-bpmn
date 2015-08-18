<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @package Workflow-BPMN
 */
if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

require_once('modules/WFWorkflows/controller.php');
require_once("include/MVC/Controller/SugarController.php");
require_once('data/BeanFactory.php');
require_once('data/SugarBean.php');
require_once('custom/include/Workflow/WF_BPMN.php');

require_once('custom/include/Workflow/gui/bpmnProcess.php');

class CustomWFWorkflowsController extends WFWorkflowsController {
	function action_export_to_bpmn() {
		$file_name='WFWorkflow_'.date('Ymd_his'). '.bpmn';

		ob_start();

		header("Pragma: public");
		header("Cache-Control: maxage=1, post-check=0, pre-check=0");
		header("Content-type: application/force-download");
		header("content-disposition: attachment; filename=\"" . $file_name . "\";");
		header("Expires: 0");
		set_time_limit(0);

		if ( !empty($_REQUEST['uid']) ) {
			$recordIds = explode(',',$_REQUEST['uid']);
			foreach ( $recordIds as $recordId ) {
				$txt = WF_BPMN::Get_BPMN($recordId);
				echo $txt;

				break;  // выполняем только для первого
			}	
		}
		sugar_cleanup(true);
	}
	
	/**
	 * Действие показывает горизонтальную диаграмму процесса на странице (без элементов SugarCRM)
	 */
	function action_bpmn_h() {
		global $app_strings, $mod_strings, $sugar_version, $sugar_config, $theme;
		global $db, $timedate;

		/**
		 * @var array Массив выполненных задач включая гейты
		 * 'task' => ID задачи
		 * 'rem' => array(
		 *   'num' => 'text' Порядковый номер и комментарий в пройденном маршруте
		 * )
		 */
		global $tasks_done;
		$tasks_done = array();
		global $task_num;
		$task_num = 1;
		/**
		 * @var array Массив выполненных переходов
		 */
		global $routes_done;
		$routes_done = array();
		
		/**
		 * @var string Текущая задача
		 */
		global $current_task;
		$current_task = '';
		
		/**
		 * Функция добавляет записи в массивы $tasks_done и $routes_done'
		 * @param string $task_from Наименование статуса, с которого был выполнен
		 * переход.
		 * @param string $task_to Наименование статуса, на который был выполнен
		 * переход.
		 * @param datetime $task_datetime Дата и время выполнения перехода
		 * @param string $task_user Выполнивший пользователь
		 */
		function add_route($task_from, $task_to, $task_datetime, $task_user) {
			add_task($task_from);
			add_task($task_to, $task_datetime, $task_user);
			
			if (!empty($task_from) && !empty($task_to)) {
				_add_route($task_from . '_to_' . $task_to);
			}
		}
		
		/**
		 * Внутренняя функция, которая непосредственно добавляет переход
		 * в массив $routes_done с контролем на уже существующую запись
		 * @param string $route Наименование перехода
		 */
		function _add_route($route) {
			global $routes_done;
			
			if (!in_array($route, $routes_done)) {
				$routes_done[] = $route;
			}
		}

		/**
		 * Внутренняя функция ищет в bpmn описании входящий и исходящий гейты для 
		 * заданного статуса и добавляет их в массив $tasks_done
		 * @param string $task Исследуемый статус
		 */
		function _getGateways($task) {
			global $xml;

			$name = $task . '_gate_to';
			$a = $xml->process->xpath('*[@id="' . $name . '"]');
			if (!empty($a)) {
				_add_task($name);
				_add_route($task . '_from_gate');
			}
			$name = $task . '_gate_from';
			$a = $xml->process->xpath('*[@id="' . $name . '"]');
			if (!empty($a)) {
				_add_task($name);
				_add_route($task . '_to_gate');
			}
		}
		
		/**
		 * Функция добавляет записи в массив $tasks_done
		 * @param string $task Статус, который нужно добавить в массив $tasks_done
		 * @param datetime $task_datetime Дата и время выполнения перехода
		 * @param string $task_user Выполнивший пользователь
		 */
		function add_task($task, $task_datetime = null, $task_user = null) {
			global $xml, $tasks_done, $bean;
			
			if (!empty($task)) {
				/**
				 * Проверим не является ли статус начальным
				 */
				$name = '_StartProcess_to_' . $task;
				$a = $xml->process->xpath('*[@id="' . $name . '"]');
				if (!empty($a)) {
					/**
					 * Если это самая первая задача, то она всегда основная
					 */
					if (empty($tasks_done)) {
						$task_datetime = $bean->date_entered;
						$task_user = $bean->created_by_name;
					}

					_add_task('_StartProcess');
					_add_route($name);
				}

				_add_task($task, $task_datetime, $task_user);
				_getGateways($task);

				/**
				 * Проверим не является ли статус конечным
				 */
				$name = $task . '_to__EndProcess_' . $task;
				$a = $xml->process->xpath('*[@id="' . $name . '"]');
				if (!empty($a)) {
					_add_task('_EndProcess_' . $task);
					_add_route($name);
				}
			}
		}

		/**
		 * Внутренняя функция, которая непосредственно добавляет задачу
		 * в массив $tasks_done с контролем на уже существующую запись
		 * @param string $task Наименование задачи
		 * @param datetime $task_datetime Дата и время выполнения перехода
		 * @param string $task_user Выполнивший пользователь
		 */
		function _add_task($task, $task_datetime = null, $task_user = null) {
			global $tasks_done, $task_num;
			
			if (!array_key_exists($task, $tasks_done)) {
				$tasks_done[$task] = array(
					'task' => $task,
					'rem' => array(),
				);
			}
			
			if (!empty($task_datetime) && !empty($task_user)) {
				$tasks_done[$task]['rem'][$task_num] = $task_datetime . ' ' . $task_user;
				$task_num++;
			}
		}

		/**
		 * Получим xml BPMN процесса для нашего маршрута
		 */
		//$xml_body = WF_BPMN::Get_BPMN($this->bean->id);
		$bpmn = new bpmnProcess($this->bean->id);
		$xml_body = $bpmn->getBPMN();
		//file_put_contents('test.bpmn', $xml_body);

		/**
		 * Определим размеры диаграммы
		 */
		$SizeX = $bpmn->getWidth() . 'px';
		$SizeY = $bpmn->getHeight() . 'px';

		/**
		 * Извлечем из запроса id записи и наменование модуля, над которой вызвали диаграмму
		 */
		$doc_id = $_REQUEST['doc_id'];
		$doc_module = $_REQUEST['doc_module'];
		
		/**
		 * Предразуем в объект SimpleXML для дальнейшей обработки
		 */
		global $xml;
		$xml = new SimpleXMLElement($xml_body);
		
		/**
		 * Преобразуем xml в json строку, чтобы сразу вставить в javascript на странице
		 */
		$xml_enc_body = json_encode($xml_body);
		
		if (!empty($doc_id) && !empty($doc_module)) {
			/**
			 * Обработаем отдельно текущий статус
			 */
			global $bean;
			if ($bean = BeanFactory::getBean($doc_module, $doc_id)) {
				/**
				 * Извлечем маршрут из таблицы аудита
				 */		
				$query = "select a.date_created, concat(b.first_name, ' ', b.last_name) created_by, a.before_value_string, a.after_value_string "
					. "from " . $bean->get_audit_table_name() . " a, users b "
					. "where (a.field_name = 'wf_status') and (a.parent_id = '" . $doc_id . "') and (b.id = a.created_by) "
					. "order by a.date_created";
				$res = $db->query($query);
				while ($row = $db->fetchByAssoc($res)) {
					add_route(
						$row['before_value_string'], 
						$row['after_value_string'],
						$timedate->asUser($timedate->fromDb($row['date_created'])),
						$row['created_by']
					);
				}

				/**
				 * Обработаем текущий статус
				 */
				if (!empty($bean->wf_status)) {
					/**
					 * Если стоим на начальном статусе (переходов не было, таблица
					 * аудита пуста, то обработаем его отдельно
					 */
					if (empty($tasks_done)) {
						add_task($bean->wf_status);
					}
					
					$gate_from = $bean->wf_status . '_gate_from';
					if (array_key_exists($gate_from, $tasks_done)) {
						$current_task = $gate_from;
					} else {
						$current_task = $bean->wf_status;
					}
					unset($gate_from);
				}
			}
		}
		$tasks_done_enc = json_encode($tasks_done);
		$routes_done_enc = json_encode($routes_done);
		$current_task_enc = json_encode($current_task);

		/**
		 * Основной сценарий JavaScript, который рисует диаграмму на странице 
		 * после загрузки в браузер
		 */
		$mainscript = <<<EOQ
<script type="text/javascript">
(function(BpmnViewer, $) {
	// create viewer
	//var bpmnViewer = new BpmnViewer({
	//	container: '#canvas',
	//	width: "{$SizeX}",
	//	height: "{$SizeY}"
	//});
	var bpmnViewer = new BpmnViewer();

	// import function
	function importXML(xml) {
		// import diagram
		bpmnViewer.importXML(xml, function(err) {
			if (err) {
				return console.error('could not import BPMN 2.0 diagram', err);
			}

			var canvas = bpmnViewer.get('canvas');
			var overlays = bpmnViewer.get('overlays');
				
			$.each(tasks_done, function(index, value) {
				canvas.addMarker(value.task, 'task-done');
		
				var StepMarker = '';
				$.each(value.rem, function(index, value) {
					StepMarker = StepMarker + '<span>' + index + '</span><span>' + value + '<br /></span>';
				});

				if (StepMarker != '') {
					StepMarker = '<div>' + StepMarker + '</div>';
					overlays.add(value.task, {
						position: {
							bottom: 0,
							left: 0
						},
						html: StepMarker
					});
				}
			});
		
			function setRouteMarker(item, i, arr) {
				canvas.addMarker(item, 'route-done');
			}
			routes_done.forEach(setRouteMarker);

			if (current_task != '') {
				canvas.addMarker(current_task, 'current_task');
			}
		
			// zoom to fit full viewport
			canvas.zoom('fit-viewport');
		});
	}

	var diagramXML = {$xml_enc_body};
	var tasks_done = {$tasks_done_enc};
	var routes_done = {$routes_done_enc};
	var current_task = {$current_task_enc};
	
	importXML(diagramXML);
})(window.BpmnJS, window.jQuery);</script>
EOQ;
		
	    $ss = new Sugar_Smarty();
        $ss->assign('SUGAR_VERSION', $sugar_version);
        $ss->assign('JS_CUSTOM_VERSION', $sugar_config['js_custom_version']);
        $ss->assign('VERSION_MARK', getVersionedPath(''));
        $ss->assign('THEME', $theme);
        $ss->assign('APP', $app_strings);
        $ss->assign('MOD', $mod_strings);
		$ss->assign('MAINSCRIPT', $mainscript);
		$ss->assign('WF_NAME', $this->bean->name);
		
		echo $ss->fetch('custom/include/Workflow/gui/tpls/bpmn.tpl');
		
		sugar_cleanup(TRUE);
	}

	/**
	 * Действие показывает вертикальную диаграмму процесса на странице (без элементов SugarCRM)
	 */
	function action_bpmn_v() {
		global $app_strings, $mod_strings, $sugar_version, $sugar_config, $theme;
		global $db, $timedate;

		/**
		 * @var array Массив выполненных задач включая гейты
		 * 'task' => ID задачи
		 * 'rem' => array(
		 *   'num' => 'text' Порядковый номер и комментарий в пройденном маршруте
		 * )
		 */
		global $tasks_done;
		$tasks_done = array();
		global $task_num;
		$task_num = 1;
		/**
		 * @var array Массив выполненных переходов
		 */
		global $routes_done;
		$routes_done = array();
		
		/**
		 * @var string Текущая задача
		 */
		global $current_task;
		$current_task = '';

		/**
		 * Функция добавляет записи в массивы $tasks_done и $routes_done'
		 * @param string $task_from Наименование статуса, с которого был выполнен
		 * переход.
		 * @param string $task_to Наименование статуса, на который был выполнен
		 * переход.
		 * @param datetime $task_datetime Дата и время выполнения перехода
		 * @param string $task_user Выполнивший пользователь
		 */
		function add_route($task_from, $task_to, $task_datetime, $task_user) {
			add_task($task_from);
			add_task($task_to, $task_datetime, $task_user);
			
			if (!empty($task_from) && !empty($task_to)) {
				_add_route($task_from . '_to_' . $task_to);
			}
		}
		
		/**
		 * Внутренняя функция, которая непосредственно добавляет переход
		 * в массив $routes_done с контролем на уже существующую запись
		 * @param string $route Наименование перехода
		 */
		function _add_route($route) {
			global $routes_done;
			
			if (!in_array($route, $routes_done)) {
				$routes_done[] = $route;
			}
		}

		/**
		 * Внутренняя функция ищет в bpmn описании входящий и исходящий гейты для 
		 * заданного статуса и добавляет их в массив $tasks_done
		 * @param string $task Исследуемый статус
		 */
		function _getGateways($task) {
			global $xml;

			$name = $task . '_gate_to';
			$a = $xml->process->xpath('*[@id="' . $name . '"]');
			if (!empty($a)) {
				_add_task($name);
				_add_route($task . '_from_gate');
			}
			$name = $task . '_gate_from';
			$a = $xml->process->xpath('*[@id="' . $name . '"]');
			if (!empty($a)) {
				_add_task($name);
				_add_route($task . '_to_gate');
			}
		}
		
		/**
		 * Функция добавляет записи в массив $tasks_done
		 * @param string $task Статус, который нужно добавить в массив $tasks_done
		 * @param datetime $task_datetime Дата и время выполнения перехода
		 * @param string $task_user Выполнивший пользователь
		 */
		function add_task($task, $task_datetime = null, $task_user = null) {
			global $xml, $tasks_done, $bean;
			
			if (!empty($task)) {
				/**
				 * Проверим не является ли статус начальным
				 */
				$name = '_StartProcess_to_' . $task;
				$a = $xml->process->xpath('*[@id="' . $name . '"]');
				if (!empty($a)) {
					/**
					 * Если это самая первая задача, то она всегда основная
					 */
					if (empty($tasks_done)) {
						$task_datetime = $bean->date_entered;
						$task_user = $bean->created_by_name;
					}

					_add_task('_StartProcess');
					_add_route($name);
				}

				_add_task($task, $task_datetime, $task_user);
				_getGateways($task);

				/**
				 * Проверим не является ли статус конечным
				 */
				$name = $task . '_to__EndProcess';
				$a = $xml->process->xpath('*[@id="' . $name . '"]');
				if (!empty($a)) {
					_add_task('_EndProcess');
					_add_route($name);
				}
			}
		}

		/**
		 * Внутренняя функция, которая непосредственно добавляет задачу
		 * в массив $tasks_done с контролем на уже существующую запись
		 * @param string $task Наименование задачи
		 * @param datetime $task_datetime Дата и время выполнения перехода
		 * @param string $task_user Выполнивший пользователь
		 */
		function _add_task($task, $task_datetime = null, $task_user = null) {
			global $tasks_done, $task_num;
			
			if (!array_key_exists($task, $tasks_done)) {
				$tasks_done[$task] = array(
					'task' => $task,
					'rem' => array(),
				);
			}
			
			if (!empty($task_datetime) && !empty($task_user)) {
				$tasks_done[$task]['rem'][$task_num] = $task_datetime . ' ' . $task_user;
				$task_num++;
			}
		}
				
		/**
		 * Получим xml BPMN процесса для нашего маршрута
		 */
		$xml_body = WF_BPMN::Get_BPMN($this->bean->id, true);

		/**
		 * Извлечем из запроса id записи и наменование модуля, над которой вызвали диаграмму
		 */
		$doc_id = $_REQUEST['doc_id'];
		$doc_module = $_REQUEST['doc_module'];
		
		/**
		 * Предразуем в объект SimpleXML для дальнейшей обработки
		 */
		global $xml;
		$xml = new SimpleXMLElement($xml_body);
		
		/**
		 * Определим размеры диаграммы
		 */
		$ns = $xml->getNamespaces(true);
		$page = $xml->process->extensionElements->children($ns['lab321'])->page->attributes();
		$SizeX = (integer)$page['sizeX'];
		if (empty($SizeX)) {
			$SizeX = '100%';
		} else {
			$SizeX = $SizeX . 'px';
		}
		$SizeY = (integer)$page['sizeY'];
		if (empty($SizeY)) {
			$SizeY = '100%';
		} else {
			$SizeY = $SizeY . 'px';
		}

		/**
		 * Преобразуем xml в json строку, чтобы сразу вставить в javascript на странице
		 */
		$xml_enc_body = json_encode($xml_body);
		
		if (!empty($doc_id) && !empty($doc_module)) {
			/**
			 * Обработаем отдельно текущий статус
			 */
			global $bean;
			if ($bean = BeanFactory::getBean($doc_module, $doc_id)) {
				/**
				 * Извлечем маршрут из таблицы аудита
				 */		
				$query = "select a.date_created, concat(b.first_name, ' ', b.last_name) created_by, a.before_value_string, a.after_value_string "
					. "from " . $bean->get_audit_table_name() . " a, users b "
					. "where (a.field_name = 'wf_status') and (a.parent_id = '" . $doc_id . "') and (b.id = a.created_by) "
					. "order by a.date_created";
				$res = $db->query($query);
				while ($row = $db->fetchByAssoc($res)) {
					add_route(
						$row['before_value_string'], 
						$row['after_value_string'],
						$timedate->asUser($timedate->fromDb($row['date_created'])),
						$row['created_by']
					);
				}

				/**
				 * Обработаем текущий статус
				 */
				if (!empty($bean->wf_status)) {
					/**
					 * Если стоим на начальном статусе (переходов не было, таблица
					 * аудита пуста, то обработаем его отдельно
					 */
					if (empty($tasks_done)) {
						add_task($bean->wf_status);
					}
					
					$gate_from = $bean->wf_status . '_gate_from';
					if (array_key_exists($gate_from, $tasks_done)) {
						$current_task = $gate_from;
					} else {
						$current_task = $bean->wf_status;
					}
					unset($gate_from);
				}
			}
		}
		$tasks_done_enc = json_encode($tasks_done);
		$routes_done_enc = json_encode($routes_done);
		$current_task_enc = json_encode($current_task);

		/**
		 * Основной сценарий JavaScript, который рисует диаграмму на странице 
		 * после загрузки в браузер
		 */
		$mainscript = <<<EOQ
<script type="text/javascript">
(function(BpmnViewer, $) {
	// create viewer
	//var bpmnViewer = new BpmnViewer({
	//	container: '#canvas',
	//	width: "{$SizeX}",
	//	height: "{$SizeY}"
	//});
	var bpmnViewer = new BpmnViewer();

	// import function
	function importXML(xml) {
		// import diagram
		bpmnViewer.importXML(xml, function(err) {
			if (err) {
				return console.error('could not import BPMN 2.0 diagram', err);
			}

			var canvas = bpmnViewer.get('canvas');
			var overlays = bpmnViewer.get('overlays');
				
			$.each(tasks_done, function(index, value) {
				canvas.addMarker(value.task, 'task-done');
		
				var StepMarker = '';
				$.each(value.rem, function(index, value) {
					StepMarker = StepMarker + '<tr><td>' + index + '<td/><td>' + value + '<td/></tr>';
				});

				if (StepMarker != '') {
					StepMarker = '<table>' + StepMarker + '</table>';
					overlays.add(value.task, {
						position: {
							top: 0,
							right: -5
						},
						html: StepMarker
					});
				}
			});
		
			function setRouteMarker(item, i, arr) {
				canvas.addMarker(item, 'route-done');
			}
			routes_done.forEach(setRouteMarker);

			if (current_task != '') {
				canvas.addMarker(current_task, 'current_task');
			}
		
			// zoom to fit full viewport
			canvas.zoom('fit-viewport');
		});
	}

	var diagramXML = {$xml_enc_body};
	var tasks_done = {$tasks_done_enc};
	var routes_done = {$routes_done_enc};
	var current_task = {$current_task_enc};
	
	importXML(diagramXML);
})(window.BpmnJS, window.jQuery);</script>
EOQ;
		
	    $ss = new Sugar_Smarty();
        $ss->assign('SUGAR_VERSION', $sugar_version);
        $ss->assign('JS_CUSTOM_VERSION', $sugar_config['js_custom_version']);
        $ss->assign('VERSION_MARK', getVersionedPath(''));
        $ss->assign('THEME', $theme);
        $ss->assign('APP', $app_strings);
        $ss->assign('MOD', $mod_strings);
		$ss->assign('MAINSCRIPT', $mainscript);
		$ss->assign('WF_NAME', $this->bean->name);
		
		echo $ss->fetch('custom/include/Workflow/gui/tpls/bpmn_old.tpl');
		
		sugar_cleanup(TRUE);
	}
	
}