<?php

require_once('custom/include/Workflow/gui/bpmnStep.php');

class bpmnPipe {
	/**
	 * @var integer Координата X объекта на диаграмме
	 */
	protected $x = 0;
	/**
	 * @var integer Координата Y объекта на диаграмме
	 */
	protected $y = 0;
	/**
	 * Функция устанавливает координаты объекта на диаграмме
	 * @param integer $x Координата X объекта на диаграмме
	 * @param integer $y Координата Y объекта на диаграмме
	 */
	public function setXY($x, $y) {
		$this->x = $x;
		$this->y = $y;
	}
	/**
	 * Функция возвращает Х координату объекта на диаграмме
	 * @return integer Координата
	 */
	public function getX() {
		return $this->x;
	}
	/**
	 * Функция возвращает Y координату объекта на диаграмме
	 * @return integer Координата
	 */
	public function getY() {
		return $this->y;
	}
}

/**
 * Класс используется для реализации вертикальной лимии перехода на диаграмме
 */
class bpmnVPipe extends bpmnPipe {
	/**
	 * @var integer Ширина вертикальной пайпы
	 */
	protected $width = 10;
	/**
	 * Функция возвращает ширину пайпы
	 * @return integer Ширина пайпы
	 */
	public function getWidth() {
		return $this->width;
	}
}

/**
 * Класс используется для реализации горизонтальной лимии перехода на диаграмме
 */
class bpmnHPipe extends bpmnPipe {
	/**
	 * @var integer Высота горизонтальной пайпы
	 */
	protected $height = 10;
	/**
	 * Функция возвращает ширину пайпы
	 * @return integer Ширина пайпы
	 */
	public function getHeight() {
		return $this->height;
	}
}