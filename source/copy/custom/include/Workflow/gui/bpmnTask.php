<?php

require_once('custom/include/Workflow/gui/bpmnProcessElement.php');
require_once('custom/include/Workflow/gui/bpmnProcess.php');
require_once('custom/include/Workflow/gui/bpmnLane.php');
require_once('custom/include/Workflow/gui/bpmnStep.php');
require_once('custom/include/Workflow/gui/bpmnGateway.php');

/**
 * Класс описывающий статус (задачу) на маршруте
 */
class bpmnTask extends bpmnProcessElement {
	/**
	 * @var bpmnLane ссылка на роль
	 */
	protected $line;
	/**
	 * Возвращает ссылку на роль
	 * @return bpmnLine Ссылка на роль
	 */
	public function getLine() {
		return $this->line;
	}
	
	/**
	 * @var bpmnStep ссылка на шаг
	 */
	protected $step;
	/**
	 * Возвращает ссылку на шаг
	 * @return bpmnStep Ссылка на шаг
	 */
	public function getStep() {
		return $this->step;
	}

	/**
	 * @var bpmnGatewayIn Входящий гейт
	 */
	protected $inGateway;
	/**
	 * Добавить связанную входящую задачу
	 * @param bpmnTask $task Входящая задача
	 */
	public function addInGateTask($task) {
		$this->inGateway->addTask($task);
	}

	/**
	 * @var bpmnGatewayOut Исходящий гейт
	 */
	protected $outGateway;
	/**
	 * Добавить связанную исходящую задачу
	 * @param bpmnTask $task Исходящая задача
	 */
	public function addOutGateTask($task) {
		$this->outGateway->addTask($task);
	}

	/**
	 * @var integer Внутренняя ширина значка задачи на диаграмме 
	 */
	protected $innerTaskWidth = 100;
	/**
	 * @var integer Внутренняя высота значка задачи на диаграмме 
	 */
	protected $innerTaskHeight = 60;
	/**
	 * @var integer Внутренняя ширина значка event на диаграмме 
	 */
	protected $innerEventWidth = 30;
	/**
	 * @var integer Внутренняя высота значка event на диаграмме 
	 */
	protected $innerEventHeight = 30;

	public function getHeight() {
		$height = $this->padding['top'] + $this->padding['bottom'];
		if ($this->getId() != '_StartProcess') {
			$height += $this->innerTaskHeight;
			$heightInGate = $this->inGateway->getHeight();
			if ($height < $heightInGate) {
				$height = $heightInGate;
			}
		} else {
			$height += $this->innerEventHeight;
		}
		$heightOutGate = $this->outGateway->getHeight();
		if ($height < $heightOutGate) {
			$height = $heightOutGate;
		}
		return $height;
	}

	public function getWidth() {
		return $this->padding['left'] + $this->padding['right']
			+ ($this->getId() != '_StartProcess' ? $this->innerTaskWidth : $this->innerEventWidth)
			+ ($this->getId() != '_StartProcess' ? $this->inGateway->getWidth(): 0)
			+ $this->outGateway->getWidth();
	}
	
	public function getInGateWidth() {
		return ($this->id != '_StartProcess' ? $this->inGateway->getWidth(): 0);
	}

	public function setXY($x, $y) {
		$inGateWidth = ($this->getId() != '_StartProcess' ? $this->inGateway->getWidth(): 0);
		//$leftPad = (integer)(($this->step->getWidth() - $this->getWidth()) / 2);
		$leftPad = 0;
		//$width = ($this->getId() != '_StartProcess' ? $this->innerTaskWidth : $this->innerEventWidth) 
		//	+ $this->padding['left'] + $this->padding['right'];
		//$leftPad = (integer)(($this->step->getWidth() - $width) / 2) - $inGateWidth;
		
		parent::setXY($x + $leftPad + $inGateWidth, $y);
		
		$this->inGateway->setXY($x + $leftPad, $y);
		$this->outGateway->setXY($x + $leftPad + $inGateWidth 
			+ ($this->getId() != '_StartProcess' ? $this->innerTaskWidth : $this->innerEventWidth)
			+ $this->padding['left'] + $this->padding['right'], $y);
	}
	
	/**
	 * Конструктор
	 * @param bpmnProcess $process Ссылка на бизнес-процесс
	 * @param string $id Уникальное наименование статуса
	 * @param string $name Наименование статуса
	 * @param bpmnLane $line Ссылка на роль
	 */
	public function __construct($process, $id, $name, $line) {
		parent::__construct($process, $id);
		$this->padding['top'] = 20 + ($id == '_StartProcess' ? 15 : 0);
		$this->padding['bottom'] = 20 + ($id == '_StartProcess' ? 15 : 0);
		$this->padding['left'] = 20;
		
		$this->name = $name;
		
		$this->line = $line;
		$this->step = $process->getCurStep();
		
		$this->line->addTask($this);
		$this->step->addTask($this);
		
		$this->inGateway = new bpmnGatewayIn($this);
		$this->outGateway = new bpmnGatewayOut($this);
	}
	
	/**
	 * Возвращает массив id самой задачи и гейтов при их наличиии
	 * @return array Массив id
	 */
	public function getSubTaskIds() {
		$res = array();
		
		if ($this->getId() != '_StartProcess') {
			$id = $this->inGateway->getId();
			if (!empty($id)) {
				$res[] = $id;
			}
		}

		$res[] = $this->getId();

		$id = $this->outGateway->getId();
		if (!empty($id)) {
			$res[] = $id;
		}		
		
		return $res;
	}
		
	/**
	 * Возвращает данные для процесса
	 * @return string xml код
	 */
	public function getProcess() {
		$bpmn = '';
		$id = $this->getId();
		if ($id == '_StartProcess') {
			$bpmn .= "\t\t" . '<startEvent id="' . $id . '" isInterrupting="true" name="' . $this->getName() . '" parallelMultiple="false">' . "\n";
			$bpmn .= "\t\t\t" . '<outgoing>' . $this->outGateway->getFlow() . '</outgoing>' . "\n";
			$bpmn .= "\t\t" . '</startEvent>' . "\n";
			$bpmn .= $this->outGateway->getProcess();
		} else {
			$bpmn .= $this->inGateway->getProcess();
			$bpmn .= "\t\t" . '<task completionQuantity="1" id="' . $id . '" isForCompensation="false" name="' . $this->getName() . '" startQuantity="1">' . "\n";
			$bpmn .= "\t\t\t" . '<incoming>' . $this->inGateway->getFlow() . '</incoming>' . "\n";
			$bpmn .= "\t\t\t" . '<outgoing>' . $this->outGateway->getFlow() . '</outgoing>' . "\n";
			$bpmn .= "\t\t" . '</task>' . "\n";
			$bpmn .= $this->outGateway->getProcess();
		}
		return $bpmn;
	}
	
	public function getDiagram() {
		$bpmn = '';
	
		$x = $this->x + $this->padding['left'];
		$y = $this->y + $this->padding['top'];
		if ($this->getId() != '_StartProcess') {
			$bpmn .= $this->inGateway->getDiagram();
			$width = $this->innerTaskWidth;
			$height = $this->innerTaskHeight;
		} else {
			$width = $this->innerEventWidth;
			$height = $this->innerEventHeight;
		}

		$bpmn .= "\t\t\t" . '<bpmndi:BPMNShape bpmnElement="' . $this->getId() . '" id="di_' . $this->getId() . '">' . "\n";
		$bpmn .= "\t\t\t\t" . '<dc:Bounds height="' . $height . '" width="' . $width . '" x="' . $x . '" y="' . $y . '"/>' . "\n";
		$bpmn .= "\t\t\t" . '</bpmndi:BPMNShape>' . "\n";

		$bpmn .= $this->outGateway->getDiagram();

		return $bpmn;
	}
	
	/**
	 * Функция возвращает waypoints для своего входящего гейта
	 * @param bpmnTask $prevtask Предыдущая задача
	 * @return array Массив waypoints (x,y)
	 */
	public function getInWayPoints($prevtask) {
		return $this->inGateway->getWayPoints($prevtask);
	}
	
	/**
	 * Функция возвращает waypoints для своего исходящего гейта
	 * @param bpmnTask $prevtask Следующая задача
	 * @return array Массив waypoints (x,y)
	 */
	public function getOutWayPoints($nexttask) {
		return $this->outGateway->getWayPoints($nexttask);
	}
}