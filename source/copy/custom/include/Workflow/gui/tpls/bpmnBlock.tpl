<div id="bpmn_block">
	<h4>{sugar_translate label='LBL_BPMN_TITLE' module='WFWorkflows'}</h4>
	<form id='bpmn' name='bpmn'
		style="margin-top: 5px;
			border-style: solid;
			border-width: 1px;
			border-color: #abc3d7;
			padding: 5px;
			padding-right: 0px;">
		<table border="0" margin="5"><tr margin="15">
        <td style="padding:5px">
			<input type='submit' name='bpmn_btn_h' value='{sugar_translate label='LBL_BPMN_H_BUTTON' module='WFWorkflows'}' onclick="SUGAR.util.openWindow('index.php?module=WFWorkflows&action=bpmn_h&record={$workflow.wf_id}&doc_id={$fields.id.value}&doc_module={$return_module}', 'bpmn', 'width=1920,height=1080,toolbar=0,location=0,directories=0,status=0,resizable=1,scrollbars=1'); return false;">
		</td>
        <td style="padding:5px">
			<input type='submit' name='bpmn_btn_v' value='{sugar_translate label='LBL_BPMN_V_BUTTON' module='WFWorkflows'}' onclick="SUGAR.util.openWindow('index.php?module=WFWorkflows&action=bpmn_v&record={$workflow.wf_id}&doc_id={$fields.id.value}&doc_module={$return_module}', 'bpmn', 'width=1920,height=1080,toolbar=0,location=0,directories=0,status=0,resizable=1,scrollbars=1'); return false;">
		</td>
		</tr></table>
	</form>
</div>