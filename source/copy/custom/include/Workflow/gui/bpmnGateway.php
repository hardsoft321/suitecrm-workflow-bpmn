<?php

require_once('custom/include/Workflow/gui/bpmnProcessElement.php');
require_once('custom/include/Workflow/gui/bpmnTask.php');

/**
 * Базовый класс гейтов
 * @abstract
 */
abstract class bpmnGateway extends bpmnProcessElement {
	/**
	 * @var bpmnTask Связанные задачи
	 */
	protected $tasks = array();
	/**
	 * Добавляет связь с задачей
	 * @param bpmnTask $task Связанная задача
	 * @abstract
	 */
	abstract public function addTask($task);
	
	/**
	 * @var integer Внутренняя ширина значка gateway на диаграмме 
	 */
	protected $innerGateWidth = 40;
	/**
	 * @var integer Внутренняя высота значка gateway на диаграмме 
	 */
	protected $innerGateHeight = 40;
	/**
	 * @var integer Внутренняя ширина значка event на диаграмме 
	 */
	protected $innerEventWidth = 30;
	/**
	 * @var integer Внутренняя высота значка event на диаграмме 
	 */
	protected $innerEventHeight = 30;
	
	public function getHeight() {
		// Если данный гейт является событием конца
		if (count($this->tasks) == 0) {
			return $this->padding['top'] + $this->innerEventHeight + $this->padding['bottom'];
		// Если данный гейт является обычным
		} elseif (count($this->tasks) > 1) {
			return $this->padding['top'] + $this->innerGateHeight + $this->padding['bottom'];
		// Если данный гейт является выражденным (на диаграмме не показывается)
		} else {
			return 0;
		}		
	}

	public function getWidth() {
		// Если данный гейт является событием конца
		if (count($this->tasks) == 0) {
			return $this->padding['left'] + $this->innerEventWidth + $this->padding['right'];
		// Если данный гейт является обычным
		} elseif (count($this->tasks) > 1) {
			return $this->padding['left'] + $this->innerGateWidth + $this->padding['right'];
		// Если данный гейт является выражденным (на диаграмме не показывается)
		} else {
			return 0;
		}		
	}

	public function setXY($x, $y) {
		$topPad = (integer)(($this->process->getHeight() - $this->getHeight()) / 2);
		parent::setXY($x, $y + $topPad);
		ksort($this->tasks);
	}
	
	/**
	 * Возвращаем id гейта только если есть более одной входящей/исходящей задачи
	 */
	public function getId() {
		// Если задач более одной, то полноценный гейт
		if (count($this->tasks) > 1) {
			return parent::getId();
		// Если задач нет, то конечный процесс
		} elseif (count($this->tasks) == 0) {
			return '_EndProcess_' . $this->process->getId();
		// Если задача всего одна, то вырожденный гейт
		} else {
			return null;
		}
	}
	
	/**
	 * Возвращает входящий или исходящий flow, должна быть перегружена
	 * @return string Наименование входящего или исходящего flow
	 * @abstract
	 */
	abstract public function getFlow();
	
	/**
	 * Возвращает данные для процесса
	 * @return string xml код
	 * @abstract
	 */
	abstract public function getProcess();

	public function getDiagram() {
		$bpmn = '';
		if (count($this->tasks) != 1) {
			$x = $this->x + $this->padding['left'];
			$y = $this->y + $this->padding['top'];
			if (count($this->tasks) == 0) {
				$width = $this->innerEventWidth;
				$height = $this->innerEventHeight;
				$marker = '';
			} else {
				$width = $this->innerGateWidth;
				$height = $this->innerGateHeight;
				$marker = ' isMarkerVisible = "true"';
			}

			$bpmn .= "\t\t\t" . '<bpmndi:BPMNShape bpmnElement="' . $this->getId() . '" id="di_' . $this->getId() . '"' . $marker . '>' . "\n";
			$bpmn .= "\t\t\t\t" . '<dc:Bounds height="' . $height . '" width="' . $width . '" x="' . $x . '" y="' . $y . '"/>' . "\n";
			$bpmn .= "\t\t\t" . '</bpmndi:BPMNShape>' . "\n";
		}
		
		return $bpmn;
	}
	
	/**
	 * Функция возвращает массив waypoints (x,y) для заданной связанной задачи
	 * @param bpmnTask $task Ссылка на связанную задачу
	 * @return array Массив waypoints (x,y)
	 * @abstract
	 */
	abstract public function getWayPoints($task);
}

/**
 * Класс входыщих гейтов
 */
class bpmnGatewayIn extends bpmnGateway {
	/**
	 * Для гейтов хостом является задача, а не процесс
	 * Переопределенный конструктор для входного гейта
	 * @param bpmnTask $task Ссылка на задачу
	 */
	public function __construct($task) {
		$id = $task->getId() . '_gate_to';
		parent::__construct($task, $id);
		$this->padding['top'] = 15;
		$this->padding['bottom'] = 15;
		$this->padding['right'] = 0;
		$this->padding['left'] = 20;
	}
	
	/**
	 * Для входа
	 * @return string Наименование входящего flow
	 */
	public function getFlow() {
		if (count($this->tasks) > 1) {
			return $this->process->getId() . '_from_gate';
		} else {
			return current($this->tasks)->getId() . '_to_' . $this->process->getId();
		}
	}

	public function getProcess() {
		$bpmn = '';
		if (count($this->tasks) > 1) {
			$bpmn .= "\t\t" . '<exclusiveGateway gatewayDirection="Converging" id="' . $this->getId() . '" name="' . $this->getName() . '">' . "\n";
			foreach ($this->tasks as &$task) {
				$bpmn .= "\t\t\t" . '<incoming>' . $task->getId() . '_to_' . $this->process->getId(). '</incoming>' . "\n";
			}
			$bpmn .= "\t\t\t" . '<outgoing>' . $this->process->getId() . '_from_gate' . '</outgoing>' . "\n";
			$bpmn .= "\t\t" . '</exclusiveGateway>' . "\n";
			$bpmn .= "\t\t" . '<sequenceFlow id="' . $this->process->getId() . '_from_gate' . '" sourceRef="' . $this->getId() . '" targetRef="' . $this->process->getId() . '" />' . "\n";
		}
		return $bpmn;
	}
	
	public function getDiagram() {
		$bpmn = parent::getDiagram();
		
		if (count($this->tasks) > 1) {
			$bpmn .= "\t\t\t" . '<bpmndi:BPMNEdge bpmnElement="' . $this->process->getId() . '_from_gate' . '" id="di_' . $this->process->getId() . '_from_gate' . '">' . "\n";
			$x1 = $this->x + $this->padding['left'] + $this->innerGateWidth;
			$y = $this->y + (integer)($this->getHeight() / 2);
			$x2 = $x1 + $this->padding['right'] + $this->process->getPadding('left');
			$bpmn .= "\t\t\t\t" . '<di:waypoint x="' . $x1 . '" y="' . $y . '"/>' . "\n";
			$bpmn .= "\t\t\t\t" . '<di:waypoint x="' . $x2 . '" y="' . $y . '"/>' . "\n";
			$bpmn .= "\t\t\t" . '</bpmndi:BPMNEdge>' . "\n";
		}
		
		return $bpmn;
	}

	/**
	 * Функция возвращает массив waypoints (x,y) для заданной связанной задачи
	 * @param bpmnTask $prevtask Ссылка на связанную входящую задачу
	 * @return array Массив waypoints (x,y)
	 */
	public function getWayPoints($prevtask) {
		$res = array();
		
		// Приходится на гейт
		$cnt = count($this->tasks);
		if ($cnt > 1) {
			// Ищем обрабатываемую задачу в массиве задач по ключу
			$key = 1;
			foreach ($this->tasks as &$task) {
				if ($task->getId() == $prevtask->getId()) {
					break;
				}
				$key++;
			}
			// линия из центра значка гейта
			if ((($cnt == 3) && ($key == 2)) || (($cnt >= 5) && (($key == 3) || ($key >= 6))))
			{
				$x1 = $this->x;
				$x2 = $this->x + $this->padding['left'];
				$y = $this->y + (integer)($this->getHeight() / 2);
				$res[] = array('x' => $x1, 'y' => $y);
				$res[] = array('x' => $x2, 'y' => $y);
			// линия из вершины гейта	
			} elseif (($cnt >= 2) && ($key == 1)) 
			{
				$x1 = $this->x;
				$x2 = $this->x + (integer)($this->innerGateWidth / 2) + $this->padding['left'];
				$y1 = $this->y;
				$y2 = $this->y + $this->padding['top'];
				$res[] = array('x' => $x1, 'y' => $y1);
				$res[] = array('x' => $x2, 'y' => $y1);
				$res[] = array('x' => $x2, 'y' => $y2);
			// линия снизу гейта
			} elseif ((($cnt == 2) && ($key == 2)) || (($cnt == 3) && ($key == 3)) 
				|| (($cnt == 4) && ($key == 4)) || (($cnt >= 5)) && ($key == 5))
			{
				$x1 = $this->x;
				$x2 = $this->x + (integer)($this->innerGateWidth / 2) + $this->padding['left'];
				$y1 = $this->y + $this->getHeight();
				$y2 = $this->y + $this->getHeight() - $this->padding['bottom'];
				$res[] = array('x' => $x1, 'y' => $y1);
				$res[] = array('x' => $x2, 'y' => $y1);
				$res[] = array('x' => $x2, 'y' => $y2);
			// линия сверху от цента гейта
			} elseif (($cnt >= 4) && ($key == 2)) 
			{
				$x1 = $this->x;
				$x2 = $this->x + $this->padding['left'];
				$y1 = $this->y + $this->padding['top'];
				$y2 = $this->y + (integer)($this->getHeight() / 2);
				$res[] = array('x' => $x1, 'y' => $y1);
				$res[] = array('x' => $x2, 'y' => $y2);
			// линия снизу от цента гейта
			} elseif ((($cnt == 4) && ($key == 3)) || (($cnt >= 5) && ($key == 4)))
			{
				$x1 = $this->x;
				$x2 = $this->x + $this->padding['left'];
				$y1 = $this->y + (integer)($this->getHeight() / 2);
				$y2 = $this->y + $this->getHeight() - $this->padding['bottom'];
				$res[] = array('x' => $x1, 'y' => $y2);
				$res[] = array('x' => $x2, 'y' => $y1);
			}			
		// Приходится на сам квадратик задачи
		} else {
			$y = $this->process->getY() + (integer)($this->process->getHeight() / 2);
			$res[] = array(
				'x' => $this->process->getX() - $this->process->getInGateWidth(),
				'y' => $y,
			);
			$res[] = array(
				'x' => $this->process->getX() + $this->process->getPadding('left'),
				'y' => $y,
			);
		}
		
		return $res;
	}
	
	/**
	 * Добавляет связь с задачей
	 * @param bpmnTask $task Связанная задача
	 */
	public function addTask($task) {
		// Ссылка на бизнес-процесс
		$master = $this->process->getHostProcess();
		// Номер шага задачи для текущего гейта
		$curStepNum = $this->process->getStep()->getId();
		// Номер линии (роли) для текущего гейта
		
		// Номер шага для связанной задачи
		$nextStepNum = $task->getStep()->getId();
		// Номер линии для связанной задачи
		$nextLaneNum = $master->getLineNum($task->getLine()->getId());
		$step_tasks = $task->getStep()->getTasks();
		$i = 1;
		foreach ($step_tasks[$nextLaneNum] as &$lane_task) {
			if ($lane_task->getId() == $task->getId()) {
				$nextTaskNum = $i;
				break;
			}
			$i++;
		}
			
		// Обратный переход
		if ($nextStepNum > $curStepNum) {
			$nextStepNum = 99 - $nextStepNum; // обратный порядок сортировки для шагов 
			//$nextLaneNum = 99 - $nextLaneNum; // обратный порядок сортировки для линий
			//$nextTaskNum = 99 - $nextTaskNum; // обратный порядок сортировки для задач
		}
		
		$key = sprintf('%02u-%02u-%02u', $nextStepNum, $nextLaneNum, $nextTaskNum);
		$this->tasks[$key] = $task;
	}	
}

/**
 * Класс исходящих гейтов
 */
class bpmnGatewayOut extends bpmnGateway {
	/**
	 * Для гейтов хостом является задача, а не процесс
	 * Переопределенный конструктор для выходного гейта
	 * @param bpmnTask $task Ссылка на задачу
	 */
	public function __construct($task) {
		$id = $task->getId() . '_gate_from';
		parent::__construct($task, $id);
		$this->padding['top'] = 15;
		$this->padding['bottom'] = 15;
		$this->padding['right'] = 20;
	}

	/**
	 * Для выхода
	 * @return string Наименование исходящего flow
	 */
	public function getFlow() {
		if (count($this->tasks) > 1) {
			return $this->process->getId() . '_to_gate';
		} elseif (count($this->tasks) == 1) {
			return $this->process->getId() . '_to_' . current($this->tasks)->getId();
		} else {
			return $this->process->getId() . '_to__EndProcess_' . $this->process->getId();
		}
	}

	public function getProcess() {
		$bpmn = '';
		if (count($this->tasks) > 1) {
			$bpmn .= "\t\t" . '<sequenceFlow id="' . $this->process->getId() . '_to_gate' . '" sourceRef="' . $this->process->getId() . '" targetRef="' . $this->getId() . '" />' . "\n";
			$bpmn .= "\t\t" . '<exclusiveGateway gatewayDirection="Diverging" id="' . $this->getId() . '" name="' . $this->getName() . '">' . "\n";
			$bpmn .= "\t\t\t" . '<incoming>' . $this->process->getId() . '_to_gate' . '</incoming>' . "\n";
			foreach ($this->tasks as &$task) {
				$bpmn .= "\t\t\t" . '<outgoing>' . $this->process->getId() . '_to_' . $task->getId() . '</outgoing>' . "\n";
			}
			$bpmn .= "\t\t" . '</exclusiveGateway>' . "\n";
		} elseif (count($this->tasks) == 0) {
			$bpmn .= "\t\t" . '<sequenceFlow id="' . $this->process->getId() . '_to__EndProcess_' . $this->process->getId() . '" sourceRef="' . $this->process->getId() . '" targetRef="' . '_EndProcess_' . $this->process->getId() . '" />' . "\n";
			$bpmn .= "\t\t" . '<endEvent id="' . '_EndProcess_' . $this->process->getId() . '" name="Конец">' . "\n";
			$bpmn .= "\t\t\t" . '<incoming>' . $this->process->getId() . '_to__EndProcess_' . $this->process->getId() . '</incoming>' . "\n";
			$bpmn .= "\t\t" . '</endEvent>' . "\n";					
		}
		return $bpmn;		
	}

	public function getDiagram() {
		$bpmn = parent::getDiagram();
		
		if (count($this->tasks) != 1) {
			$id = (count($this->tasks) == 0 ? $this->process->getId() . '_to__EndProcess_' . $this->process->getId() : $this->process->getId() . '_to_gate');
			$bpmn .= "\t\t\t" . '<bpmndi:BPMNEdge bpmnElement="' . $id . '" id="di_' . $id . '">' . "\n";
			$x1 = $this->x - $this->process->getPadding('right');
			$x2 = $this->x + $this->padding['left'];
			$y = $this->y + (integer)($this->getHeight() / 2);
			$bpmn .= "\t\t\t\t" . '<di:waypoint x="' . $x1 . '" y="' . $y . '"/>' . "\n";
			$bpmn .= "\t\t\t\t" . '<di:waypoint x="' . $x2 . '" y="' . $y . '"/>' . "\n";
			$bpmn .= "\t\t\t" . '</bpmndi:BPMNEdge>' . "\n";
		}
		
		return $bpmn;
	}
	
	/**
	 * Функция возвращает массив waypoints (x,y) для заданной связанной задачи
	 * @param bpmnTask $nexttask Ссылка на связанную входящую задачу
	 * @return array Массив waypoints (x,y)
	 */
	public function getWayPoints($nexttask) {
		$res = array();
		
		// Первая точка
		// Приходится на гейт
		$cnt = count($this->tasks);
		if ($cnt > 1) {
			// Ищем обрабатываемую задачу в массиве задач по ключу
			$key = 1;
			foreach ($this->tasks as &$task) {
				if ($task->getId() == $nexttask->getId()) {
					break;
				}
				$key++;
			}
			// линия из центра значка гейта
			if ((($cnt == 3) && ($key == 2)) || (($cnt >= 5) && (($key == 3) || ($key >= 6))))
			{
				$x1 = $this->x + $this->getWidth() - $this->padding['right'];
				$x2 = $this->x + $this->getWidth();
				$y = $this->y + (integer)($this->getHeight() / 2);
				$res[] = array('x' => $x1, 'y' => $y);
				$res[] = array('x' => $x2, 'y' => $y);
			// линия из вершины гейта	
			} elseif (($cnt >= 2) && ($key == 1))
			{
				$x1 = $this->x + (integer)($this->innerGateWidth / 2) + $this->padding['left'];
				$x2 = $this->x + $this->getWidth();
				$y1 = $this->y + $this->padding['top'];
				$y2 = $this->y;
				$res[] = array('x' => $x1, 'y' => $y1);
				$res[] = array('x' => $x1, 'y' => $y2);
				$res[] = array('x' => $x2, 'y' => $y2);
			// линия с низу гейта
			} elseif ((($cnt == 2) && ($key == 2)) || (($cnt == 3) && ($key == 3)) 
				|| (($cnt == 4) && ($key == 4)) || (($cnt >= 5)) && ($key == 5))
			{
				$x1 = $this->x + (integer)($this->innerGateWidth / 2) + $this->padding['left'];
				$x2 = $this->x + $this->getWidth();
				$y1 = $this->y + $this->getHeight() - $this->padding['bottom'];
				$y2 = $this->y + $this->getHeight();
				$res[] = array('x' => $x1, 'y' => $y1);
				$res[] = array('x' => $x1, 'y' => $y2);
				$res[] = array('x' => $x2, 'y' => $y2);
			// линия сверху от цента гейта
			} elseif (($cnt >= 4) && ($key == 2)) 
			{
				$x1 = $this->x + $this->getWidth() - $this->padding['right'];
				$x2 = $this->x + $this->getWidth();
				$x3 = $x2 - (integer)($this->padding['right'] / 2);
				$y1 = $this->y + (integer)($this->getHeight() / 2);
				$y2 = $this->y + $this->padding['top'];
				$res[] = array('x' => $x1, 'y' => $y1);
				$res[] = array('x' => $x3, 'y' => $y2);
				$res[] = array('x' => $x2, 'y' => $y2);
			// линия снизу от цента гейта
			} elseif ((($cnt == 4) && ($key == 3)) || (($cnt >= 5) && ($key == 4)))
			{
				$x1 = $this->x + $this->getWidth() - $this->padding['right'];
				$x2 = $this->x + $this->getWidth();
				$x3 = $x2 - (integer)($this->padding['right'] / 2);
				$y1 = $this->y + (integer)($this->getHeight() / 2);
				$y2 = $this->y + $this->getHeight() - $this->padding['bottom'];
				$res[] = array('x' => $x1, 'y' => $y1);
				$res[] = array('x' => $x3, 'y' => $y2);
				$res[] = array('x' => $x2, 'y' => $y2);
			}			
		// Приходится на сам квадратик задачи
		} else {
			$y = $this->process->getY() + (integer)($this->process->getHeight() / 2);
			$x1 = $this->process->getX() 
				+ $this->process->getWidth() 
				- $this->process->getInGateWidth()
				- $this->process->getPadding('right');
			$x2 = $x1 + $this->process->getPadding('right');
			$res[] = array('x' => $x1, 'y' => $y);
			$res[] = array('x' => $x2, 'y' => $y);
		}
		
		return $res;
	}

	/**
	 * Добавляет связь с задачей
	 * @param bpmnTask $task Связанная задача
	 */
	public function addTask($task) {
		// Ссылка на бизнес-процесс
		$master = $this->process->getHostProcess();
		// Номер шага задачи для текущего гейта
		$curStepNum = $this->process->getStep()->getId();
		// Номер линии (роли) для текущего гейта
		
		// Номер шага для связанной задачи
		$nextStepNum = $task->getStep()->getId();
		// Номер линии для связанной задачи
		$nextLaneNum = $master->getLineNum($task->getLine()->getId());
		$step_tasks = $task->getStep()->getTasks();
		$i = 1;
		foreach ($step_tasks[$nextLaneNum] as &$lane_task) {
			if ($lane_task->getId() == $task->getId()) {
				$nextTaskNum = $i;
				break;
			}
			$i++;
		}
			
		// Обратный переход
		if ($nextStepNum <= $curStepNum) {
			$nextStepNum = 50+$nextStepNum; // обратный порядок сортировки для шагов
			$nextLaneNum = 99-$nextLaneNum; // обратный порядок сортировки для линий
			//$nextTaskNum = 99 - $nextTaskNum; // обратный порядок сортировки для задач
		}
		
		$key = sprintf('%02u-%02u-%02u', $nextLaneNum, $nextStepNum, $nextTaskNum);
		$this->tasks[$key] = $task;
	}
}