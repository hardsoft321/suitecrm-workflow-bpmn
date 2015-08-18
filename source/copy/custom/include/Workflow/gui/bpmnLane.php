<?php

require_once('custom/include/Workflow/gui/bpmnProcessElement.php');
require_once('data/BeanFactory.php');
require_once('custom/include/Workflow/gui/bpmnTask.php');
require_once('custom/include/Workflow/gui/bpmnPipe.php');

/**
 * Класс описывающий роль (дорожку)
 */
class bpmnLane extends bpmnProcessElement {
	/**
	 * @var array Двумерный массив (номер шага и номер задачи) ссылок
	 * на задачи для текущей роли
	 */
	private $tasks = array();
	/**
	 * Функция добавляет задачу в массив $tasks
	 * @param bpmnTask $task Ссылка на задачу
	 */
	public function addTask($task) {
		$this->tasks[$task->getStep()->getId()][] = $task;
	}
	
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
	
	/**
	 * @var array Массив ссылок на горизонтальные пайпы для линии
	 */
	protected $pipes = array();
	/**
	 * Функция добавляет новую горизонтальную пайпу к текущей линии и возвращает ссылку
	 * @param string $key Ключ пайпы для последующей сортировки
	 * @return bpmnVPipe Созданная вертикальная пайпа
	 */
	public function getNewPipe($key) {
		$pipe = new bpmnHPipe();
		$this->pipes[$key] = $pipe;
		return $pipe;
	}

		
	public function getHeight() {
		$height = 0;
		foreach ($this->tasks as &$step) {
			$stepHeight = 0;
			foreach ($step as &$task) {
				$stepHeight += $task->getHeight();
			}
			// Высота роли равна максимальной высоте шагов на ней
			if ($height < $stepHeight) {
				$height = $stepHeight;
			}
		}
		
		foreach ($this->pipes as &$pipe) {
			$height += $pipe->getHeight();
		}
		
		// Заглушка на случай если на роли не окажется ни одной задачт
		if ($height == 0) {
			$height = 20;
		}
		
		return /*$this->padding['top'] +*/ $height /*+ $this->padding['bottom']*/;
	}

	public function getWidth() {
		// Ширина роли совпадает с шириной бизнес-процесса
		return $this->process->getWidth() - $this->process->getLabelWidth();
	}

	public function setXY($x, $y) {
		parent::setXY($x, $y);
		
		// y координата низа линии
		ksort($this->pipes);
		$bottom = $y + $this->getHeight();
		foreach (array_reverse($this->pipes) as $pipe) {
			$bottom -= $pipe->getHeight();
			$pipe->setXY($x, $bottom);
		}
	}

	public function __construct($process, $id) {
		parent::__construct($process, $id);
		if ($role = BeanFactory::getBean('ACLRoles', substr($id, 5))) {
			$this->name = $role->name;
		}
	}
	
	/**
	 * Возвращает данные для процесса
	 * @return string xml код
	 */
	public function getProcess() {
		$bpmn = "\t\t\t" . '<lane id="' . $this->getId() . '" name="' . $this->getName() . '">' . "\n";
		foreach ($this->tasks as &$step) {
			foreach ($step as &$task) {
				$subId = $task->getSubTaskIds();
				foreach ($subId as $value) {
					$bpmn .= "\t\t\t\t" . '<flowNodeRef>' . $value . '</flowNodeRef>' . "\n";
				}
			}
		}
		$bpmn .= "\t\t\t" . '</lane>' . "\n";
		return $bpmn;
	}
	
	public function getDiagram() {
		$bpmn  = "\t\t\t" . '<bpmndi:BPMNShape bpmnElement="' . $this->getId() . '" id="di_' . $this->getId() . '" isExpanded="true" isHorizontal="true">' . "\n";
		$bpmn .= "\t\t\t\t" . '<dc:Bounds height="' . $this->getHeight() . '" width="' . $this->getWidth() . '" x="' . $this->x . '" y="' . $this->y . '"/>' . "\n";
		$bpmn .= "\t\t\t" . '</bpmndi:BPMNShape>' . "\n";
		
		return $bpmn;
	}
}