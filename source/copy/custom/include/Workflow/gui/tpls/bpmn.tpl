<!doctype html>
<html>
	<head>
		<meta charset="utf-8">
		<meta http-equiv="content-type" content="text/html; charset=utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

	<style type="text/css">
		{literal}
		html, body, #canvas {
			height: 100%;
			padding: 0;
		}

		.diagram-note {
			background-color: rgba(66, 180, 21, 0.7);
			color: White;
			border-radius: 5px;
			font-family: Arial;
			font-size: 12px;
			padding: 5px;
			min-height: 16px;
			width: 80px;
			text-align: center;
		}

		.needs-discussion:not(.djs-connection) .djs-visual > :nth-child(1) {
			stroke: rgba(66, 180, 21, 0.7) !important; /* color elements as red */
		}
		
		/* Задает цвет фона */
		body {
			background-color: #dac5eb;
		}
		/* Задает цвет заполнения и окантовки элементов задача и гейт */
		.djs-shape:not([data-element-id^="lane"]) .djs-visual > :nth-child(1):not(.djs-label) {
			fill: #dac5eb;
			stroke: #660685;
		}
		/* Задает цвет линии перехода */
		.djs-connection .djs-visual > :nth-child(1) {
			stroke: #660685;
		}
		/* Задает цвет элемента на пройденном маршруте */
		.task-done:not(.djs-connection) .djs-visual > :nth-child(1) {
			stroke: #5ba0d0 !important;
		}
		/* Задает цвет перехода на пройденном маршруте */
		.route-done:not(.djs-shape) .djs-visual > :nth-child(1) {
			stroke: #5ba0d0 !important;
		}
		/* Задает цвет текущего элемента на маршруте */
		.current_task:not(.djs-connection) .djs-visual > :nth-child(1) {
			fill: #c98efa !important;
		}
		/* Задает цвет перехода, на который наведен курсор */
		.djs-connection.hover .djs-visual > :nth-child(1) {
			stroke: white !important;
		}
		/* Задает параметры пояснения к прохождению маршрута */
		.djs-overlay {
			text-align: center;
			font-family: arial, sans-serif;
			opacity: 0.7;
			filter: alpha(Opacity=70);
		}
		.djs-overlay span {
			background-color: #5ba0d0;
			border-radius: 9px;
			color: white;
			font-size: 12px;
			white-space: nowrap;
			padding: 2px;
			margin-right: 2px;
		}
		/* Пользователь выполнивший переход по умолчанию не показыается */
		.djs-overlay div > :nth-child(even) {
			display: none;
		}
		/* А если навести мышку на задачу, то пользователь показывается */
		.hover .djs-overlay {
			z-index: 1;
		}
		.hover .djs-overlay div > :nth-child(even) {
			display: inline !important;
		}
		{/literal}
	</style>

	<title>{$WF_NAME}</title>
</head>
	<body>
		{*<div id="canvas"></div>*}

		<script src="custom/include/Workflow/gui/js/jquery.js"></script>
		<script src="custom/include/Workflow/gui/js/jquery.mousewheel.js"></script>
		<script src="custom/include/Workflow/gui/js/lodash.js"></script>
		<script src="custom/include/Workflow/gui/js/sax.js"></script>
		<script src="custom/include/Workflow/gui/js/snap.svg.js"></script>
		<script src="custom/include/Workflow/gui/js/bpmn-viewer-custom.js"></script>
		{$MAINSCRIPT}
	</body>
</html>