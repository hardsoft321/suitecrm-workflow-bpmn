<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author hardsoft321
 * @package Workflow-BPMN
 */
class BpmnHooks
{
    /**
     * Выводит кнопки вызова горизотнальной и вертикальной диаграмм
     */
    public function afterEditForm($bean, $event, $arguments)
    {
        require_once 'include/Sugar_Smarty.php';
        $ss = new Sugar_Smarty();
        $ss->assign('workflow', $arguments);
        $ss->assign('fields', array(
            'id' => array('value' => $bean->id),
        ));
        $ss->assign('return_module', $bean->module_name);
        echo $ss->fetch('custom/include/Workflow/gui/tpls/bpmnBlock.tpl');
    }
}
