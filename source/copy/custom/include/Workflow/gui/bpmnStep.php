<?php

require_once('custom/include/Workflow/gui/bpmnProcessElement.php');
require_once('custom/include/Workflow/gui/bpmnTask.php');
require_once('custom/include/Workflow/gui/bpmnPipe.php');

/**
 * Класс описывающий шаг (вертикальную дорожку) на маршруте
 */
class bpmnStep extends bpmnProcessElement {
	/**
	 * @var array Двумерный массив (номер роли и номер задачи) ссылок
	 * на задачи для текущего шага
	 */
	protected $tasks = array();
	/**
	 * Функция добавляет задачу в массив $tasks
	 * @param bpmnTask $task Ссылка на задачу
	 */
	public function addTask($task) {
		$this->tasks[$this->process->getLineNum($task->getLine()->getId())][] = $task;
	}
	/**
	 * Возвращает массив задач в разбивке по ролям
	 * @return array Массив задач
	 */
	public function getTasks() {
		return $this->tasks;
	}
	/**
	 * Функция возвращает порядковый номер задачи на шаге
	 * @param integer $task_id Идентификатор задачи
	 * @return mixed Порядковый номер задачи на шаге или null
	 */
	public function getTaskNum($task_id) {
		$i = 1;
		foreach ($this->tasks as &$lane) {
			foreach ($lane as &$task) {
				if ($task->getId() == $task_id) {
					return $i;
				}
				$i++;
			}
		}
		return null;
	}
	
	/**
	 * @var array Массив ссылок на вертикальные пайпы для текущего шага
	 */
	protected $pipes = array();
	/**
	 * Функция добавляет новую вертикальную пайпу к текущему шагу и возвращает ссылку
	 * @param string $key Ключ пайпы для последующей сортировки
	 * @return bpmnVPipe Созданная вертикальная пайпа
	 */
	public function getNewPipe($key) {
		$pipe = new bpmnVPipe();
		if (key_exists($key, $this->pipes)) {
			$aaa = 1;
		}
		$this->pipes[$key] = $pipe;
		return $pipe;
	}
	
	public function getHeight() {
		// Высота шага совпадает с высотой бизнес-процесса
		return $this->process->getHeight();
	}

	public function getWidth() {
		$width = 0;
		foreach ($this->tasks as &$lane) {
			foreach ($lane as &$task) {
				$taskWidth = $task->getWidth();
				if ($width < $taskWidth) {
					$width = $taskWidth;
				}
			}
		}
		foreach ($this->pipes as &$pipe) {
			$width += $pipe->getWidth();
		}
		return /*$this->padding['left'] +*/ $width /*+ $this->padding['right']*/;
	}
	
	public function setXY($x, $y) {
		parent::setXY($x, $y);
		
		foreach ($this->tasks as &$lane) {
			foreach ($lane as &$task) {
				if (!isset($taskY)) {
					$taskY = $task->getLine()->getY();
				}
				$task->setXY($x, $taskY);
				$taskY += $task->getHeight();
			}
			unset($taskY);
		}
		
		// x координата правого шага
		ksort($this->pipes);
		$right = $x + $this->getWidth();
		foreach (array_reverse($this->pipes) as $pipe) {
			$right -= $pipe->getWidth();
			$pipe->setXY($right, $y);
		}
	}
}