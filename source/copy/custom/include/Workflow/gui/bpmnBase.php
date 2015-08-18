<?php

/**
 * Базовый класс для всех элементов bpmn диаграммы
 */
abstract class bpmnBase {
	/**
	 * @var string ID bpmn объекта
	 */
	protected $id = '';
	/**
	 * @var string Наименование bpmn объекта
	 */
	protected $name = '';

	/**
	 * Функция для получение ширины объекта диаграммы
	 * @return integer Ширина объекта в пикселях
	 * @abstract
	 */
	abstract public function getWidth();
	
	/**
	 * Функция для получения высоты объекта диаграммы
	 * @return integer Высота объекта в пикселях
	 * @abstract
	 */
	abstract public function getHeight();
	
	/**
	 * Функция возвращает ID bpmn объекта
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}
	
	/**
	 * Функция возвращает наименование bpmn объекта
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}
	
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
	
	/**
	 * Возвращает данные о диаграмме для процесса
	 * @return string xml код
	 */
	protected function getDiagram() {
		return '';
	}
}