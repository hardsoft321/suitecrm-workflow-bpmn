<div id="bpmn_block">
	<form id='bpmn' name='bpmn'>
		<input type='submit' name='bpmn_btn_h' value='{sugar_translate label='LBL_BPMN_TITLE' module='WFWorkflows'}'
			onclick="SUGAR.util.openWindow('index.php?module=WFWorkflows&action=bpmn_h&record={$workflow.wf_id}&doc_id={$fields.id.value}&doc_module={$return_module}', 'bpmn', 'width=1920,height=1080,toolbar=0,location=0,directories=0,status=0,resizable=1,scrollbars=1'); return false;">
	</form>
</div>