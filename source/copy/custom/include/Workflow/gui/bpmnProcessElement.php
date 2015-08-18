<?php

require_once('custom/include/Workflow/gui/bpmnBase.php');
require_once('custom/include/Workflow/gui/bpmnProcess.php');

/**
 * Базовый класс для всех элементов процесса
 */
abstract class bpmnProcessElement extends bpmnBase {
	/**
	 * @var bpmnProcess Ссылка на класс бизнес-процесса
	 */
	protected $process;
	/**
	 * Возвращает ссылку на $process
	 * @return bpmnProcessElement Возвращает ссылку на задачу для гейта или 
	 * ссылку на бизнес-процесс для остальных элементов
	 */
	public function getHostProcess() {
		return $this->process;
	}
	
	/**
	 * @var array Массив отступов элемента при отображении на диаграмме
	 */
	protected $padding = array(
		'left' => 10,
		'right' => 10,
		'top' => 10,
		'bottom' => 10,
	);
	/**
	 * Функция возвращает массив отступов или конкретный отступ
	 * @param type $param Наименование элемента отступа или пусто
	 * @return mixed Конкретный отступ из массива отступов или весь массив
	 */
	public function getPadding($param = null) {
		if ($param != null) {
			return $this->padding[$param];
		} else {
			return $this->padding;
		}
	}
	
	/**
	 * Конструктор
	 * @param bpmnProcess $process Ссылка на класс бизнес-процесса
	 * @param string $id id элемента
	 */
	public function __construct($process, $id) {
		$this->process = $process;
		$this->id = $id;
	}
}
