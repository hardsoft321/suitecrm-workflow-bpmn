<?php

$manifest = array (
  'name' => 'Workflow-BPMN',
  'acceptable_sugar_versions' => array (),
  'acceptable_sugar_flavors' => array('CE','PRO','ENT'),
  'author' => 'hardsoft321.ru, idv',
  'description' => 'Диаграммы для Workflow-WF',
  'is_uninstallable' => true,
  'published_date' => '2015-08-17',
  'type' => 'module',
  'version' => '1.1.1',
  'dependencies' => array(
    array(
        'id_name' => 'Workflow-WF',
        'version' => '0'
    ),
  ),
);

$installdefs = array (
    'id' => 'Workflow-BPMN',
    'copy' => array (
      array(
          'from' => '<basepath>/source/copy',
          'to' => '.'
      ),
    ),
    'language' => array (
        array (
            'from' => '<basepath>/source/language/ru_ru.workflows_bpmn.php',
            'to_module' => 'WFWorkflows',
            'language' => 'ru_ru',
        ),
        array (
            'from' => '<basepath>/source/language/en_us.workflows_bpmn.php',
            'to_module' => 'WFWorkflows',
            'language' => 'en_us',
        ),
        array (
            'from' => '<basepath>/source/language/ge_ge.workflows_bpmn.php',
            'to_module' => 'WFWorkflows',
            'language' => 'ge_ge',
        ),
    ),
    'logic_hooks' => array (
        array (
            'module' => '',
            'hook' => 'wf_after_editform',
            'order' => 100,
            'description' => 'Show BPMN Buttons',
            'file' => 'custom/include/Workflow/gui/BpmnHooks.php',
            'class' => 'BpmnHooks',
            'function' => 'afterEditForm',
        ),
    ),
    'vardefs' => array (
        array (
            'from' => '<basepath>/source/vardefs/workflow_bpmn.php',
            'to_module' => 'WFWorkflows',
        ),
    ),
);
