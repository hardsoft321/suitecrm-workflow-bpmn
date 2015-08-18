<?php

require_once('custom/include/Workflow/gui/bpmnTask.php');
require_once('custom/include/Workflow/gui/bpmnProcess.php');
require_once('custom/include/Workflow/gui/bpmnPipe.php');

/**
 * Класс перехода
 */
class bpmnFlow {
	/**
	 * @var bpmnProcess Бизнес-процесс
	 */
	protected $process;
	/**
	 * @var bpmnTask Начальная задача
	 */
	protected $fromTask;
	/**
	 * @var bpmnTask Конечная задача
	 */
	protected $toTask;
	/**
	 * @var bpmnVPipe Ссылка на вертикальную пайпу
	 */
	protected $VPipe;
	/**
	 * @var bpmnHPipe Ссылка на горизонтальну пайпу при необходимости реверсного перехода
	 */
	protected $rHPipe;
	/**
	 * @var bpmnVPipe Ссылка на вертикальную пайпу при необходимости реверсного перехода
	 */
	protected $rVPipe;

	/**
	 * Конструктор
	 * @param bpmnProcess $process Ссылка на бизнес-процесс
	 * @param bpmnTask $fromTask Ссылка на начальную задачу
	 * @param bpmnTask $toTask Ссылка на конечную задачу
	 */	
	public function __construct($process, $fromTask, $toTask) {
		$this->process = $process;
		$this->fromTask = $fromTask;
		$this->toTask = $toTask;

		$fromStepNum = $fromTask->getStep()->getId();
		$fromTaskNum = $fromTask->getStep()->getTaskNum($fromTask->getId());
		$toStepNum = $toTask->getStep()->getId();
		$toTaskNum = $toTask->getStep()->getTaskNum($toTask->getId());
		$toLaneNum = $process->getLineNum($toTask->getLine()->getId());

		// Если переход реверсный, то запросим три пайпы
		if ($fromTask->getStep()->getId() >= $toTask->getStep()->getId()) {
			$key = sprintf('1-%02u-%02u-%02u-%02u', $toLaneNum, 99-$toStepNum, 99-$toTaskNum, 99-$fromTaskNum);
			$this->VPipe = $fromTask->getStep()->getNewPipe($key);
			
			$key = sprintf('%02u-%02u-%02u', 99-$toTaskNum, $fromStepNum, 99-$fromTaskNum);
			$this->rHPipe = $toTask->getLine()->getNewPipe($key);
			
			$rstep = $toTask->getHostProcess()->getStepByNum($toTask->getStep()->getId()-1);
			$key = sprintf('9-99-%02u-%02u-%02u', $toTaskNum, 99-$fromStepNum, $fromTaskNum);
			$this->rVPipe = $rstep->getNewPipe($key);
		} else {
			$fromLaneNum = $process->getLineNum($fromTask->getLine()->getId());
			$toLaneNum = $process->getLineNum($toTask->getLine()->getId());
			$toTaskNum = $toTask->getStep()->getTaskNum($toTask->getId());
			if ($fromLaneNum > $toLaneNum) {
				$key = sprintf('5-%02u-%02u-%02u-00', $fromTaskNum, $toLaneNum, $toTaskNum);
			} else {
				$key = sprintf('5-%02u-%02u-%02u-00', $fromTaskNum, 99-$toLaneNum, 99-$toTaskNum);
			}
			$this->VPipe = $fromTask->getStep()->getNewPipe($key);
		}
		
		$this->fromTask->addOutGateTask($toTask);
		$this->toTask->addInGateTask($fromTask);
	}

	/**
	 * Возвращает данные для процесса
	 * @return string xml код
	 */	
	public function getProcess() {
		$source = end($this->fromTask->getSubTaskIds());
		reset($this->toTask->getSubTaskIds());
		$target = current($this->toTask->getSubTaskIds());
		$bpmn = "\t\t" . '<sequenceFlow id="' . $this->fromTask->getId() . '_to_' . $this->toTask->getId() . '" sourceRef="' . $source . '" targetRef="' . $target . '" />' . "\n";
		return $bpmn;
	}
	
	public function getDiagram() {
		$in = $this->fromTask->getOutWayPoints($this->toTask);
		$out = $this->toTask->getInWayPoints($this->fromTask);
		
		$pipe = array();
		$a = end($in);
		$x1 = $a['x'];
		$y1 = $a['y'];
		$x2 = $out[0]['x'];
		$y2 = $out[0]['y'];
		// Точка на вертикальной пайпе
		$x3 = $this->VPipe->getX();
		$pipe[] = array('x' => $x3, 'y' => $y1);
		// Если переход прямой
		if ($this->fromTask->getStep()->getId() < $this->toTask->getStep()->getId()) {
			$pipe[] = array('x' => $x3, 'y' => $y2);		
		// Если переход обратный
		} else {
			// Точка на горизонтальной реверсной пайпе
			//$y3 = $this->rHPipe->getY() + (integer)($this->rHPipe->getHeight() / 2);
			$y3 = $this->rHPipe->getY();
			$pipe[] = array('x' => $x3, 'y' => $y3);
			// Точка на вертикальной реверсной пайпе
			//$x4 = $this->rVPipe->getX() + (integer)($this->rVPipe->getWidth() / 2);
			$x4 = $this->rVPipe->getX();
			$pipe[] = array('x' => $x4, 'y' => $y3);
			$pipe[] = array('x' => $x4, 'y' => $y2);
		}
		
		$waypoints = array_merge($in, $pipe, $out);
		
		$id = $this->fromTask->getId() . '_to_' . $this->toTask->getId();
		$bpmn  = "\t\t\t" . '<bpmndi:BPMNEdge bpmnElement="' . $id . '" id="di_' . $id . '">' . "\n";
		foreach ($waypoints as &$waypoint) {
			$bpmn .= "\t\t\t\t" . '<di:waypoint x="' . $waypoint['x'] . '" y="' . $waypoint['y'] . '"/>' . "\n";
		}
		$bpmn .= "\t\t\t" . '</bpmndi:BPMNEdge>' . "\n";
		
		return $bpmn;
	}
}