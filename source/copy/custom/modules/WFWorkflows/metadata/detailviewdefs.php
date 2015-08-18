<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author hardsoft321
 * @package Workflow-BPMN
 */

$viewdefs ['WFWorkflows'] = array (  'DetailView' => 
  array (
    'templateMeta' => array (
      'form' => array (
        'buttons' => array (
			'EDIT',
			'DUPLICATE',
			'DELETE',
			array (
				'customCode' => 
					'<input id="bpmn" class="button" type="button" '
					. 'value="{$MOD.LBL_BPMN_HL_BUTTON}" name="bpmn" accesskey="" title="{$MOD.LBL_BPMN_HL_BUTTON}" '
					. 'onclick="SUGAR.util.openWindow(\'index.php?module=WFWorkflows&action=bpmn_h&record={$id}\', \'bpmn\', \'menubar=0,toolbar=0,location=0,directories=0,status=0,resizable=0,scrollbars=0\');">',
			),
			array (
				'customCode' => 
					'<input id="bpmn" class="button" type="button" '
					. 'value="{$MOD.LBL_BPMN_VL_BUTTON}" name="bpmn" accesskey="" title="{$MOD.LBL_BPMN_VL_BUTTON}" '
					. 'onclick="SUGAR.util.openWindow(\'index.php?module=WFWorkflows&action=bpmn_v&record={$id}\', \'bpmn\', \'menubar=0,toolbar=0,location=0,directories=0,status=0,resizable=0,scrollbars=0\');">',
			),
        ),
      ),
      'maxColumns' => '2',
      'widths' => array (
        array (
          'label' => '10',
          'field' => '30',
        ),
        array (
          'label' => '10',
          'field' => '30',
        ),
      ),
      'useTabs' => false,
    ),

    'panels' => array (
      'lbl_information' => array (
        array ('wf_module', 'name'),
		array (
            array('name'=>'uniq_name'),
            array(
                'name'=>'status_field',
                'customCode'=>'{$fields.status_field.value}',
            ),
        ),
        array (
            array(
                'name'=>'bean_type',
                'customCode'=>'{$fields.bean_type.value}'
            )
        ),
      ),
    ),
  ),
);
?>
