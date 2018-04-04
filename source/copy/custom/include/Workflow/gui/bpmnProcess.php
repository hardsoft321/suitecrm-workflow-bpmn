<?php

require_once('custom/include/Workflow/gui/bpmnBase.php');
require_once('data/BeanFactory.php');
require_once('custom/include/Workflow/gui/bpmnLane.php');
require_once('custom/include/Workflow/gui/bpmnTask.php');
require_once('custom/include/Workflow/gui/bpmnStep.php');
require_once('custom/include/Workflow/gui/bpmnFlow.php');

/**
 * Класс описывающий весь бизнес-процесс
 */
class bpmnProcess extends bpmnBase {
	/**
	 * @var array Массив ролей процесса
	 */
	protected $lines = array();
	/**
	 * Функция ищет роль по id
	 * @param string $id id роли вида lane_94e8488f-4409-9380-3b0d-549d3ebbebec
	 * @return bpmnLane Ссылка на найденную или созданную роль
	 */
	public function getLine($id) {
		foreach ($this->lines as &$value) {
			if ($value->getId() == $id) {
				return $value;
			}
		}
		$line = new bpmnLane($this, $id);
		$this->lines[] = $line;
		return $line;
	}
	/**
	 * Возвращает порядковый номер роли
	 * @param string $id id роли вида lane_94e8488f-4409-9380-3b0d-549d3ebbebec
	 * @return mixed Порядковый номер роли в массиве $lines или null
	 */
	public function getLineNum($id) {
		foreach ($this->lines as $key => &$value) {
			if ($value->getId() == $id) {
				return $key;
			}
		}
		return null;
	}
	
	/**
	 * @var array Массив шагов процесса 
	 */
	protected $steps = array();
	/**
	 * Возвращает текущий шаг бизнес-процесса
	 * @return bpmnStep Текущий шаг бизнес-процесса
	 */
	public function getCurStep() {
		// Если никакие шаги еще не определены
		if (empty($this->steps)) {
			// Создадим специальный нулевой шаг
			$this->steps[0] = new bpmnStep($this, 0);
			// Создадим специальную начальную задачу
			// $this->tasks[0] = new bpmnTask($this, '_StartProcess', 'Начало', $this->lines[0]); //TODO: перевод
			$this->tasks[0] = new bpmnTask($this, '_StartProcess', '', $this->lines[0]);
			// Создадим также первый шаг
			$this->steps[1] = new bpmnStep($this, 1);
		}
		return end($this->steps);
	}
	public function getStepByNum($num) {
		return $this->steps[$num];
	}

	/**
	 * @var array Массив статусов (задач)
	 */
	protected $tasks = array();
	/**
	 * Функция ищет задачу по уникальному наименованию
	 * @param string $id Уникальное наименование статуса
	 * @return mixed Ссылка на задачу или null
	 */
	public function getTask($id) {
		$task = null;

		foreach ($this->tasks as &$value) {
			if ($value->getId() == $id) {
				$task = $value;
				break;
			}
		}

		return $task;
	}
	
	/**
	 * @var array Массив переходов
	 */
	protected $flows = array();

	/**
	 * @var integer Ширина заголовка
	 */
	protected $innerLabelWidth = 30;
	/**
	 * Возвращает ширину заголовка
	 * @return integer Ширина заголовка
	 */
	public function getLabelWidth() {
		return $this->innerLabelWidth;
	}

	public function getHeight() {
		$height = 0;
		foreach ($this->lines as &$lane) {
			$height += $lane->getHeight();
		}
		return $height;
	}

	public function getWidth() {
		$width = 0;
		foreach ($this->steps as &$step) {
			$width += $step->getWidth();
		}
		
		$widthLaneLabel = 0;
		foreach ($this->lines as &$lane) {
			if ($widthLaneLabel < $lane->getLabelWidth()) {
				$widthLaneLabel = $lane->getLabelWidth();
			}
		}
		
		return $width + $widthLaneLabel + $this->innerLabelWidth;
	}
	
	public function setXY($x, $y) {
		parent::setXY($x, $y);
		
		$laneX = $this->x + $this->getLabelWidth();
		$laneY = $this->y;
		foreach ($this->lines as &$lane) {
			$lane->setXY($laneX, $laneY);
			$laneY += $lane->getHeight();
		}

		$widthLaneLabel = 0;
		foreach ($this->lines as &$lane) {
			if ($widthLaneLabel < $lane->getLabelWidth()) {
				$widthLaneLabel = $lane->getLabelWidth();
			}
		}
		$stepX = $laneX + $widthLaneLabel;
		$stepY = $this->y;
		foreach ($this->steps as &$step) {
			$step->setXY($stepX, $stepY);
			$stepX += $step->getWidth();
		}
	}

	public function getId() {
		return 'Process_' . $this->id;
	}
	
	public function __construct($workflow_id = null) {
		$this->setup($workflow_id);
	}
	
	/**
	 * Функция, которая загружает все данные о маршруте из DB.
	 * @global type $db
	 * @param string $workflow_id ID маршрута
	 */
	public function setup($workflow_id) {
		global $db;
		
		if (!empty($workflow_id) 
			&& ($wf = BeanFactory::getBean('WFWorkflows', $workflow_id))) 
		{
			$this->name = $wf->name;
			$this->id = $wf->uniq_name;

			// Получим все переходы из начального статуса
			$query = "SELECT DISTINCT s2.id as status2_id
                FROM wf_events e12
                INNER JOIN wf_statuses s2 ON s2.id = e12.status2_id
                INNER JOIN wf_events e23 ON s2.id = e23.status1_id
                WHERE
                    (e12.status1_id IS NULL OR e12.status1_id = '')
                    AND e23.workflow_id = '{$wf->id}'
                    AND e12.deleted = 0
                    AND e23.deleted = 0
                ";
			$result = $db->query($query);
	        while($row = $db->fetchByAssoc($result)) {
				$this->process_event('', $row['status2_id']);
			}
			$event = BeanFactory::getBean('WFEvents');
			
			// Итерационно обработаем все последующие шаги
			// TODO нужно переделать кусок с использованнием $a
			$a = $this->getCurStep()->getTasks();
			while (!empty($this->steps)
				&& !empty($a)) 
			{
				$step = $this->getCurStep();
				$this->steps[] = new bpmnStep($this, $step->getId()+1);
				$tasks = $step->getTasks();
				foreach ($tasks as &$lane_tasks) {
					foreach ($lane_tasks as &$task) {
						$query = 'select a.* from wf_events a, wf_statuses b '
							. 'where (a.status1_id = b.id) and (b.uniq_name = "' . $task->getId() . '") '
							. 'and (a.workflow_id = \'' . $wf->id . '\') '
							. 'and (a.deleted = 0) and (b.deleted = 0) '
							. 'order by a.sort';
						$result = $db->query($query);
						while($row = $db->fetchByAssoc($result)) {
							$event->populateFromRow($row);
							$this->process_event($event->status1_id, $event->status2_id);
						}
					}
				}
				$a = $this->getCurStep()->getTasks();
			}
			$this->setXY(0, 0);
		}
	}
	
	/**
	 * Функция обработки перехода
	 * @param string $status1_id id статуса, с которорого идет переход
	 * @param string $status2_id id статуса, на который идет переход
	 */
	protected function process_event($status1_id, $status2_id) {
		// Получим конечный статус
		if ($status = BeanFactory::getBean('WFStatuses', $status2_id)) {
			$line = $this->getLine('lane_' . $status->role_id);
			$task2 = $this->getTask($status->uniq_name);
			// Если такой задачи еще нет, то добавим
			if (empty($task2)) {
				$task2 = new bpmnTask($this, $status->uniq_name, $status->name, $line);
				$this->tasks[] = $task2;
			}
		}
		// Если начальный статус пустой, то ищем специальную задачу _StartProcess
		if (empty($status1_id)) {
			$task1 = $this->tasks[0];
		} else {
			$status = BeanFactory::getBean('WFStatuses', $status1_id);
			$task1 = $this->getTask($status->uniq_name);
		}
		// Теперь добавим переход
		$this->flows[] = new bpmnFlow($this, $task1, $task2);
	}
	
	/**
	 * Возвращает BPMN описание загруженного маршрута
	 * @return string Описание BPMN в виде xml
	 */
	public function getBPMN() {
		$bpmn = $this->getHeader();
		$bpmn .= $this->getProcess();
		$bpmn .= $this->getDiagram();
		$bpmn .= $this->getFooter();
		
		return $bpmn;
	}
	
	/**
	 * Возвращает заголовок диаграммы
	 * @return string xml код
	 */
	protected function getHeader() {
		$bpmn = <<<BPMN
<?xml version="1.0" encoding="UTF-8" standalone="yes"?> 
<definitions xmlns="http://www.omg.org/spec/BPMN/20100524/MODEL"
	xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
	xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
	xmlns:di="http://www.omg.org/spec/DD/20100524/DI"
	xmlns:tns="http://sourceforge.net/bpmn/definitions/_1418042429428"
	xmlns:xsd="http://www.w3.org/2001/XMLSchema"
	xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	xmlns:lab321="http://bpmn.sourceforge.net" exporter="SugarCRM" exporterVersion="6.5" expressionLanguage="http://www.w3.org/1999/XPath" id="_1419330332607"
	targetNamespace="http://sourceforge.net/bpmn/definitions/_1418042429428" 
	typeLanguage="http://www.w3.org/2001/XMLSchema" 
	xsi:schemaLocation="http://www.omg.org/spec/BPMN/20100524/MODEL http://bpmn.sourceforge.net/schemas/BPMN20.xsd">
BPMN;
		$bpmn .= "\n\t" . '<collaboration id="COLLABORATION_1" isClosed="false">' . "\n";
		//$bpmn .= "\n\t" . '<collaboration id="COLLABORATION_1">' . "\n";
		$bpmn .= "\t\t" . '<participant id="' . $this->id. '" name="' . $this->getName() . '" processRef="' . $this->getId() . '">' . "\n";
		//$bpmn .= "\t\t\t" . '<participantMultiplicity maximum="1" minimum="0" />' . "\n";
		$bpmn .= "\t\t" . '</participant>' . "\n";
		$bpmn .= "\t" . '</collaboration>' . "\n";

		return $bpmn;
	}
	
	/**
	 * Возвращает подвал диаграммы
	 * @return string xml код
	 */
	protected function getFooter() {
		$bpmn = '</definitions>';
		
		return $bpmn;
	}
	
	/**
	 * Возвращает данные о процессе
	 * @return string xml код
	 */
	protected function getProcess() {
		$bpmn = '';
		
		// Вывод данных о процессе
		$bpmn .= "\t" . '<process id="' . $this->getId() . '" isClosed="false" isExecutable="true">' . "\n";
		//$bpmn .= "\t" . '<process id="' . $this->getId() . '" isExecutable="true">' . "\n";
		
		// Вывод данных о ролях
		$bpmn .= "\t\t" . '<laneSet>' . "\n";
		foreach ($this->lines as &$line) {
			$bpmn .= $line->getProcess();
		}
		$bpmn .= "\t\t" . '</laneSet>' . "\n";
		
		// Вывод данных о задачах и гейтах
		foreach ($this->tasks as &$task) {
			$bpmn .= $task->getProcess();
		}
		
		// Вывод данных о внешних flow
		foreach ($this->flows as &$flow) {
			$bpmn .= $flow->getProcess();
		}
		
		$bpmn .= "\t" . '</process>' . "\n";
		
		return $bpmn;
	}
	
	/**
	 * Возвращает данные о диаграмме процесса
	 * @return string xml код
	 */
	protected function getDiagram() {
		$bpmn = "\t" . '<bpmndi:BPMNDiagram id="LAB321_Diagram_1" name="DIAG_' . $this->id . '">' . "\n";
		$bpmn .= "\t\t" . '<bpmndi:BPMNPlane bpmnElement="COLLABORATION_1">' . "\n";

		$bpmn .= "\t\t\t" . '<bpmndi:BPMNShape bpmnElement="' . $this->id . '" id="di_' . $this->id . '" isExpanded="true" isHorizontal="true">' . "\n";
		$bpmn .= "\t\t\t\t" . '<dc:Bounds height="' . $this->getHeight() . '" width="' . $this->getWidth() . '" x="' . $this->x . '" y="' . $this->y . '"/>' . "\n";
		$bpmn .= "\t\t\t" . '</bpmndi:BPMNShape>' . "\n";

		foreach ($this->lines as &$lane) {
			$bpmn .= $lane->getDiagram();
		}
		
		foreach ($this->tasks as &$task) {
			$bpmn .= $task->getDiagram();
		}
		
		foreach ($this->flows as &$flow) {
			$bpmn .= $flow->getDiagram();
		}
		
		$bpmn .= "\t\t" . '</bpmndi:BPMNPlane>' . "\n";
		$bpmn .= "\t" . '</bpmndi:BPMNDiagram>' . "\n";
		
		return $bpmn;
	}
}
