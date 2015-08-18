<?php
/**
 * @package Workflow-BPMN
 */
require_once('include/MVC/View/views/view.list.php');
class CustomWFWorkflowsViewList extends ViewList 
{
	/**
	* @see ViewList::preDisplay()
	*/
	public function preDisplay()
	{
	parent::preDisplay();
	$this->lv->actionsMenuExtraItems[] = $this->buildBPMNItem();
	}
	/**
	* @return string HTML
	*/
	protected function buildBPMNItem()
	{
	global $app_strings;
	global $mod_strings;
return <<<EHTML
	<a class="menuItem" style="width: 150px;" href="#" onmouseover='hiliteItem(this,"yes");'
	onmouseout='unhiliteItem(this);'
	onclick="sugarListView.get_checks();
	if(sugarListView.get_checks_count() < 1) {
	alert('{$app_strings['LBL_LISTVIEW_NO_SELECTED']}');
	return false;
	}
	document.MassUpdate.action.value='export_to_bpmn';
	document.MassUpdate.submit();">{$mod_strings['LBL_EXPORT_WF_TO_BPMN']}</a>
EHTML;
	}
} 