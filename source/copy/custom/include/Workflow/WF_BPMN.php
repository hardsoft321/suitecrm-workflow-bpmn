<?php
 /******************************************
  *       Выгрузка маршрута в формат BPMN             *
  * @package Workflow-BPMN
  ******************************************/

class WF_BPMN {
	private static $w_statuses ;
	private static $w_workflows ;	
	private static $w_events ;
	private static $w_gateways ;
	private static $w_lanes ;
	private static $start_proc; // сюда запишем  начальный переход из StartEvent  на первый статус
	private static $end_proc = array();  // массив конечных статусов, сделаем им переход на EndEvent
	private static $maxlevel = 0;
	private static $di_arr = array();
	private static $coord_arr = array();
	
	private static $ret_str = '';

//--------------------------
//  Функция возвращает маршрут в формате BPMN в виде строки
//--------------------------
 public static function Get_BPMN($wf_id, $is_refresh = false) {
    //  wf_id  -  ID маршрута
    //  Если заполнено поле BPMN_TEXT - возвращаем его.  
	//  Если не заполнено - формируем текст, записываем в поле и возвращаем.
	//  Если is_refresh = true, то в любом случае заново формируем
	global $db;
	self::$w_workflows = array();
	self::$w_statuses = array();
	self::$w_events = array();
	self::$w_gateways = array();


	$bean = BeanFactory::getBean('WFWorkflows', $wf_id);

	self::$ret_str = html_entity_decode($bean->bpmn_text,ENT_QUOTES); // текст, записанный в поле таблицы

	if (empty(self::$ret_str) || $is_refresh) {
		self::$ret_str = '';
		self::Create_BPMN($bean);
//		$bean->bmpn_text = self::$ret_str ;
//		$bean->bmpn_modified = date('Y-m-d H:i:s') ;
//		$bean->save() ;

		$sql = 'update wf_workflows set bpmn_text = \''. self::$ret_str .
				'\', bpmn_modified = \''.date('Y-m-d H:i:s') . 
				'\' where id = \''.$wf_id.'\'' ;
		$db->query($sql);
	}
	return self::$ret_str;
}

//----------------------------------------
// Служебные функции


// добавляем строку 
protected static function add($add_str, $endstr = "\n" ){
	self::$ret_str .= ( $add_str . $endstr ) ;
}


//  проставляем элементам уровни, в зависимости от переходов
protected static function Sort_WF($root) {
	$level = self::$di_arr[$root]['sort'] ;
    if (self::$maxlevel < $level) {self::$maxlevel = $level;}
	foreach (self::$w_events as $evnt) {
	// ищем переходы с элемента root
		if ( $evnt['status_from']  == $root ) {
			if ( self::$di_arr[ $evnt['status_to']]['sort']  == -1 ) {
				self::$di_arr[ $evnt['status_to']]['sort'] = $level+1;
			//-------  Рекурсия !------------
				self::Sort_WF( $evnt['status_to']) ;
			//-------------------------------
			}
		}
	}
}

protected static function Create_BPMN($wfBean){
/************************************
 *  ПЕРВАЯ ЧАСТЬ  - заполняем массивы статусов, модулей и переходов
*************************************/

	global $db;

	self::$w_workflows[$wfBean->id] = array(
			'wf_module' => $wfBean->wf_module,
			'name' => $wfBean->name,
			'uniq_name' => $wfBean->uniq_name,
			'status_field' => $wfBean->status_field,
			'bean_type' => $wfBean->bean_type) ;

	// выберем все переходы и их статусы из текущего маршрута
 	$sql="select we.id, we.sort, we.filter_function, we.after_save,  ".
			"ws1.id  rid1, ws1.uniq_name rname1, ".
			"ws2.id  rid2, ws2.uniq_name rname2 ".
			"from wf_events we  ".
			"LEFT JOIN wf_statuses ws1 ON we.status1_id=ws1.id ".
			"LEFT JOIN wf_statuses ws2 ON we.status2_id=ws2.id ".
			"where we.workflow_id = '".$wfBean->id."' and we.deleted=0 ".
			"and (ws1.id is null or (ws1.wf_module='".$wfBean->wf_module."' and ws1.deleted=0)) ".
			"and (ws2.id is null or (ws2.wf_module='".$wfBean->wf_module."' and ws2.deleted=0)) ";
		
	$result = $db->query($sql);	
	while ( $row = $db->fetchByAssoc($result) ) {	
		// переход
		$event_name = ($row['rname1'] ? $row['rname1'] : "_StartProcess" ) .'_to_'.$row['rname2'] ;
		self::$w_events[$event_name] = array(
//			'wf_module' =>$wfBean->wf_module,  
//			'workflow' => $wfBean->uniq_name,  
			'status_from' =>($row['rname1'] ? $row['rname1'] : "_StartProcess" ), 
			'status_to' 	=>$row['rname2'],  
			'sort' 	=>$row['sort'],  
			'filter_function' 	=>$row['filter_function'],  
			'after_save' 	=>$row['after_save'],
		);
		// для статусов пока запомним ID и uniq_name,  остальное заполним позже
		if ($row['rid1']) {
			if (!array_key_exists($row['rid1'], self::$w_statuses)) {
				self::$w_statuses[$row['rid1']] = array('uniq_name'=>$row['rname1'], 'in'=>'', 'out'=>'')  ;
			}
			// нужен список входящих/исходящих переходов по статусам
			self::$w_statuses[$row['rid1']]['out'].= $event_name.';';
		}
		if (!array_key_exists($row['rid2'], self::$w_statuses)) {
			self::$w_statuses[$row['rid2']] = array('uniq_name'=>$row['rname2'], 'in'=>'', 'out'=>'');
		}
		self::$w_statuses[$row['rid2']]['in'].= $event_name.';';
		
		if (empty($row['rname1'])) {  // не заполнен первый статус - это начало процесса
		self::$start_proc =  $event_name ;}
	}

	// теперь заполним все параметры статусов
	foreach(self::$w_statuses as $w_key => &$w_stat) {
	$sql="select * from wf_statuses where wf_module='".$wfBean->wf_module."' and deleted=0 and id='".$w_key."'";	
		$stat = $db->fetchOne($sql);
		$s_in = $w_stat['in'];
		$s_out = $w_stat['out'];
		$in_cnt   = substr_count($s_in , ';') ; // кол-во входящих переходов
		$out_cnt = substr_count($s_out , ';') ;  // кол-во исходящих переходов
		if ($s_in)  {$s_in  = substr($s_in,  0, -1);} // отбрасываем последний символ ";"
		if ($s_out) {$s_out = substr($s_out, 0, -1);} 

		if ($stat) {
			// найдём наименование ролей по ID
			$r_name1 = null;
			$r_name2 = null;
			$rsql = "select name from acl_roles where id='{$stat['role_id']}' and deleted=0";
			$role = $db->fetchOne($rsql);
			if ($role) {
					$r_name1 = $role['name'];	
			}	
			if(isset($stat['role2_id'])) {
				$role = $db->fetchOne("select name from acl_roles where id='{$stat['role2_id']}' and deleted=0");
				if ($role) {
					$r_name2 = $role['name'];	
				}	
			}
			//-----------------------------------------------------------
			if ($in_cnt > 1) { // несколько входящих переходов
				// перехватываем у статуса его входящие события, назначаем их на gateway,  а статусу отдаём одно входящее - с gateway

				$tmp = $stat['uniq_name'].'_from_gate';
				self::$w_gateways[] = array('id' => $stat['uniq_name'].'_gate_to', 
											'type' => 'Converging', 
											'flow_in' => $s_in, 
											'flow_out' => $tmp ) ;
				// надо в переходах подменить  target 
				$a_in = explode(';', $s_in);
				foreach ($a_in as $r_in) {
					self::$w_events[$r_in]['status_to'] = $stat['uniq_name'].'_gate_to' ;
				}
				$s_in = $tmp ; 
				//-----------------------------------------------------------
				self::$w_events[$tmp] = array(
				//	'wf_module' =>$wfBean->wf_module,  
				//	'workflow' => $wfBean->uniq_name,  
					'status_from' =>$stat['uniq_name'].'_gate_to',  
					'status_to' 	=>$stat['uniq_name'],  
					'sort' 	=>'1',  
				);
			} 
			
			if ($out_cnt > 1) { // несколько исходящих переходов
				//  перехватываем у статуса его исходящие события, назначаем их на gateway,  а статусу отдаём одно исходящее - на gateway
				$tmp = $stat['uniq_name'].'_to_gate';
				self::$w_gateways[] = array('id' => $stat['uniq_name'].'_gate_from', 
											'type' => 'Diverging', 
											'flow_out' => $s_out, 
											'flow_in' => $tmp ) ;
				// надо в переходах подменить  source
				$a_out = explode(';', $s_out);
				foreach ($a_out as $r_out) {
					self::$w_events[$r_out]['status_from'] = $stat['uniq_name'].'_gate_from' ;
				}
				$s_out = $tmp ; 
				//-----------------------------------------------------------
				self::$w_events[$tmp] = array(
					//'wf_module' =>$wfBean->wf_module,  
					//'workflow' => $wfBean->uniq_name,  
					'status_from' =>$stat['uniq_name'],  
					'status_to' 	=>$stat['uniq_name'].'_gate_from',  
					'sort' 	=>'1',  
				);
			} 
			
			if (empty($s_out)) { // нет исходящих  переходов - это конечный статус,  запишем его в массив и сделаем переход на EndProcess
				$s_out = $stat['uniq_name'].'_to__EndProcess';
				self::$end_proc[] = $stat['uniq_name'] ;
				
				self::$w_events[$s_out] = array(
					//'wf_module' =>$wfBean->wf_module,  
					//'workflow' => $wfBean->uniq_name,  
					'status_from' =>$stat['uniq_name'],  
					'status_to' 	=>'_EndProcess',  
					'sort' 	=>'1',  
				);
				}

			$w_stat = array(
					//'wf_module' =>$wfBean->wf_module,  
					'uniq_name' =>  $stat['uniq_name'],
					'name' => $stat['name'],
					'role_name' =>  $r_name1,
					'role2_name' => $r_name2,
					'edit_role_type' => $stat['edit_role_type'],
					'front_assigned_list_function' => $stat['front_assigned_list_function'],
					'assigned_list_function' => $stat['assigned_list_function'],
					'confirm_list_function' => $stat['confirm_list_function'],
					'confirm_check_list_function' => $stat['confirm_check_list_function'],
					'flow_in' => $s_in,
					'flow_out' => $s_out,
					);

			// каждая роль - это будет Lane
			self::$w_lanes[$r_name1] = $stat['role_id'] ; 
			}
		}
		///-------------------------------
		//  создадим список элементов с сортировкой
		self::$di_arr['_StartProcess'] = array('tip'=>'StartEnd',  'sort' => '0');
		self::$di_arr['_EndProcess'] = array('tip'=>'StartEnd',  'sort' => '999');
		foreach (self::$w_statuses as $stat) {
			self::$di_arr[$stat['uniq_name']] = array('tip'=>'Task',   'sort' => '-1');
		}
		foreach (self::$w_gateways as $gate) {
			self::$di_arr[$gate['id']] = array('tip'=>'Gateway',   'sort' => '-1');
		}		


/************************************
 *  ВТОРАЯ ЧАСТЬ  - По заполненным массивам формируем текст
 *************************************/
 
	$lane_width = 180; // ширина колонки
	$elem_gap = 80;  // расстояние по вертикали между элементами
	$top_lev = 80; // верхний элемент
	$line_gap = 10; // промежуток между линиями, чтобы они не сливались
	$page_width = 600; // ширина страницы
	$page_heigth = $top_lev + (count(self::$w_statuses) + count(self::$w_gateways) +2)  * $elem_gap + $top_lev;

	// заголовок
	self::add( '<?xml version="1.0" encoding="UTF-8" standalone="yes"?> ') ;
	self::add( '<definitions xmlns="http://www.omg.org/spec/BPMN/20100524/MODEL"' ) ;
	self::add( '				xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"' ) ;
	self::add( '				xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"')  ;
	self::add( '				xmlns:di="http://www.omg.org/spec/DD/20100524/DI"')  ;  
	self::add( '				xmlns:tns="http://sourceforge.net/bpmn/definitions/_1418042429428"' )  ;
	self::add( '				xmlns:xsd="http://www.w3.org/2001/XMLSchema"' )  ;
	self::add( '				xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' )  ;
	self::add( '				xmlns:lab321="http://bpmn.sourceforge.net" exporter="SugarCRM" exporterVersion="6.5" expressionLanguage="http://www.w3.org/1999/XPath" id="_1419330332607"'); 
	self::add( '				targetNamespace="http://sourceforge.net/bpmn/definitions/_1418042429428" ' )  ;
	self::add( '				typeLanguage="http://www.w3.org/2001/XMLSchema" ' )  ;
	self::add( '				xsi:schemaLocation="http://www.omg.org/spec/BPMN/20100524/MODEL http://bpmn.sourceforge.net/schemas/BPMN20.xsd">' )  ;
		

	foreach(self::$w_workflows as $w_keywf => $w_work) {  // на самом деле маршрут только один
		self::add( '  <process id="'.$w_work['uniq_name'].'" isClosed="false" isExecutable="true" > ' );
		self::add( '    <extensionElements>');
        self::add( '        <lab321:page sizeX="'. $page_width.'" sizeY="'.$page_heigth .'"  />');
		self::add( '    </extensionElements>');

		/*  пока непонятно  как хранить,  оставим на будущее
			self::add( "<extensionElements> \n";
			self::add( "  <workflow module= \"{$w_work['wf_module']}\" status_field=\"{$w_work['status_field']}\" bean_type=\"{$w_work['bean_type']}\" /> \n";
			self::add( "</extensionElements> \n";

		self::add( '    <property id="module" itemSubjectRef="xsd:string" name="'.$w_work['wf_module'].'"/>');
		self::add( '    <property id="status_field" itemSubjectRef="xsd:string" name="'.$w_work['status_field'].'"/>' ;
		self::add( '    <property id="bean_type" itemSubjectRef="xsd:string" name="'.$w_work['bean_type'].'"/>' ;
		*/

	/*    laneset  тоже пока оставим
    self::add( '  <laneSet>'  ;
	foreach(self::$w_lanes as $w_key => $w_id) {
      self::add( '      <lane id="lane_'.$w_id.'" name="'.$w_key.'">'  ;
        //<flowNodeRef>_4</flowNodeRef>
      self::add( '  </lane>'  ;
	}


	self::add( '      <lane id="lane_ID" name="Lane_Name">'  ;
	foreach(self::$w_statuses as $w_key => $w_stat) {
		self::add( '          <flowNodeRef>'.$w_stat['uniq_name'].'</flowNodeRef>'  ;
	}
	foreach(self::$w_gateways as $w_key => $w_gate) {
			self::add( '          <flowNodeRef>'.$w_gate['id'].'</flowNodeRef>'  ;
	}
	self::add( '          <flowNodeRef>_StartProcess</flowNodeRef>'  ;
	self::add( '          <flowNodeRef>_EndProcess</flowNodeRef>'  ;
    self::add( '      </lane>'  ;
	self::add( '  </laneSet>'  ;
	*/
	
		foreach(self::$w_statuses as $w_key_st => $w_stats) {
		    self::add('    <task completionQuantity="1" id="'. $w_stats['uniq_name']. '" isForCompensation="false" name="' .$w_stats['name']. '" startQuantity="1"> ') ;

			// входящие/исходящие 
			if ($w_stats['flow_in']) {
				$a_in = explode(';',$w_stats['flow_in']);
				foreach ($a_in as $r_in) {
					self::add( "      <incoming>".$r_in."</incoming>");
				}
			}
			if ($w_stats['flow_out']) {
				$a_out = explode(';',$w_stats['flow_out']);
				foreach ($a_out as $r_out) {
					self::add( "      <outgoing>".$r_out."</outgoing>");
				}
			}
	
			self::add( '      <property id="'. $w_stats['uniq_name'].'_role_name" itemSubjectRef="xsd:string" name="'.$w_stats['role_name'].'"/> ' );
			self::add( '      <property id="'. $w_stats['uniq_name'].'_role2_name" itemSubjectRef="xsd:string" name="'.$w_stats['role2_name'].'"/>');
			self::add( '      <property id="'. $w_stats['uniq_name'].'_edit_role_type" itemSubjectRef="xsd:string" name="'.$w_stats['edit_role_type'].'"/>');
			self::add( '      <property id="'. $w_stats['uniq_name'].'_front_assigned_list_function" itemSubjectRef="xsd:string" name="'.$w_stats['front_assigned_list_function'].'"/>');
			self::add( '      <property id="'. $w_stats['uniq_name'].'_assigned_list_function" itemSubjectRef="xsd:string" name="'.$w_stats['assigned_list_function'].'"/>');
			self::add( '      <property id="'. $w_stats['uniq_name'].'_confirm_list_function" itemSubjectRef="xsd:string" name="'.$w_stats['confirm_list_function'].'"/>');			
			self::add( '      <property id="'. $w_stats['uniq_name'].'_confirm_check_list_function" itemSubjectRef="xsd:string" name="'.$w_stats['confirm_check_list_function'].'"/>');			

			self::add( "    </task>");	
			
		}
	
		foreach(self::$w_events as $w_key_ev => $w_evnt) {
			$st_from = ($w_evnt['status_from'] ? $w_evnt['status_from'] : "_StartProcess" );
			self::add( '    <sequenceFlow id="'.$w_key_ev.'" sourceRef="'.$st_from.'" targetRef="'.$w_evnt['status_to'].'">');
		    self::add( '      <documentation id="'.$w_key_ev.'_sort" textFormat="text/plain"><![CDATA[sort='.$w_evnt['sort'].']]></documentation>');
			if ($w_evnt['filter_function']) {
				self::add( '      <documentation id="'.$w_key_ev.'_filter" textFormat="text/plain"><![CDATA[filter_function='.$w_evnt['filter_function'].']]></documentation>'); 
			}
			if ($w_evnt['after_save']) {
				self::add( '      <documentation id="'.$w_key_ev.'_after" textFormat="text/plain"><![CDATA[after_save='.$w_evnt['after_save'].']]></documentation>'); 
			}
			self::add( "    </sequenceFlow> ");
		}	

		foreach(self::$w_gateways as $w_key_gt => $w_gate) {
			self::add( '    <exclusiveGateway gatewayDirection="'.$w_gate['type'].'" id="'. $w_gate['id'].'" name="" >');
      
			// входящие/исходящие 
			$a_in = explode(';',$w_gate['flow_in']);
			foreach ($a_in as $r_in) {
				self::add( "      <incoming>".$r_in."</incoming>");
			}
			
			$a_out = explode(';',$w_gate['flow_out']);
			foreach ($a_out as $r_out) {
				self::add( "      <outgoing>".$r_out."</outgoing>");
			}
			self::add( '    </exclusiveGateway>');
	 	}		
		
		self::add( '    <startEvent id="_StartProcess" isInterrupting="true" name="Start process" parallelMultiple="false">');
		self::add( '      <outgoing>'.self::$start_proc.'</outgoing>');
		self::add( '    </startEvent>' );

		self::add( '  	<endEvent id="_EndProcess" name="End process">');
		foreach (self::$end_proc as $end_pr) {
			self::add( '      <incoming>'.$end_pr.'_to__EndProcess </incoming>');
		}
		self::add( '   </endEvent>');
		self::add( '  </process> ');
	//--------------------------------------------------------	  
	//  дальше часть про графику

	// сортировка элементов по уровням,  в соответствии с переходами
		self::Sort_WF('_StartProcess') ;
		self::$di_arr['_EndProcess']['sort'] = self::$maxlevel+1;
		$srt = array();
		$srt_count = array();
		foreach (self::$di_arr as $ky => $di) {
			$srt[$ky] =  $di['sort'];
		}
		asort($srt);
		$srt_count = array_count_values($srt); // сколько элементов каждого уровня найдено
	  
		self::add( '  <bpmndi:BPMNDiagram id="LAB321_Diagram_1" name="DIAG_'.$w_work['uniq_name'].' " resolution="96.0">' );
		self::add( '    <bpmndi:BPMNPlane bpmnElement="'.$w_work['uniq_name'].'">');
		$ind = 0;
		$nowLev = -1;
     
		//$lanes_cnt = count(self::$w_lanes);
		//	$page_width = $lanes_cnt * $lane_width; // ширина страницы

/*
		$cnt=0;
		foreach (self::$w_lanes as $key => $id ) {
			self::add('      <bpmndi:BPMNShape bpmnElement="lane_'.$id.'" id="di_'.$id.'" isExpanded="true" isHorizontal="false">'  ;
			$xx = 20+$cnt*$lane_width;
			self::add('              <dc:Bounds height="1000" width="'.$lane_width.'" x="'. $xx.'" y="50"/>'  ;
			self::add('              <bpmndi:BPMNLabel>'  ;
			$xx = 40+$cnt*$lane_width;
			self::add('                <dc:Bounds height="23.609375" width="48.0" x="'. $xx.'" y="55"/>'  ;
			self::add('              </bpmndi:BPMNLabel>'  ;
			self::add('      </bpmndi:BPMNShape>'  ;
			$cnt++ ;
	}
*/	
		$indY = 0;
		foreach ($srt as $key => $k ) {  // идём по элементам в порядке возрастания уровня
			if ($nowLev != $k)  {$ind=1; $nowLev = $k;}
			else {$ind++ ;}
			$lev_gap = $page_width / ($srt_count[$k]+1) ; // расстояние между элементами данного уровня. Если один элемент - он будет посередине страницы
			$hh = 50; $ww=50;
			self::add('      <bpmndi:BPMNShape bpmnElement="'.$key.'" id="LAB321_'.$key.'" ' ,' ');
			if (self::$di_arr[$key]['tip'] == 'Gateway')  {self::add( ' isMarkerVisible = "true" ',' ');}
			self::add( '>' ) ;
			if (self::$di_arr[$key]['tip'] == 'StartEnd') {$hh = 30; $ww = 30;}
			if (self::$di_arr[$key]['tip'] == 'Task') 	  {$hh = 60; $ww = 100;}
			if (self::$di_arr[$key]['tip'] == 'Gateway')  {$hh = 40; $ww = 40;}
			$nowX = $lev_gap*$ind;
			$nowY = $top_lev+ $indY*$elem_gap;  
			// это координаты верхнего левого угла.  Выровняем по центру 
			$nowX = $nowX - floor($ww/2); 
			$nowY = $nowY - floor($hh/2); 
			self::add('        <dc:Bounds height="' .$hh. '" width="' .$ww.'" x="'.$nowX.'" y="'.$nowY.'"/>')   ;
			if (self::$di_arr[$key]['tip'] == 'StartEnd') {
				self::add( "        <bpmndi:BPMNLabel> ");
				$tmpx = $nowX+50 ;
				self::add('             <dc:Bounds height="20" width="60" x="'.$tmpx.'" y="'.$nowY.'"/>' ) ;
				self::add( "        </bpmndi:BPMNLabel> ");
			}
			self::add("      </bpmndi:BPMNShape>");

			//  В coord_arr будем хранить координаты ЦЕНТРА фигуры, и ПОЛОВИНУ её ширины-высоты
			//  lc, rc  -  количество переходов, выходящих слева,справа
			$hh = $hh/2;
			$ww = $ww/2;
			$nowX = $nowX+$ww;  
			$nowY = $nowY+$hh; 
			self::$coord_arr[$key] = array('x'=>$nowX, 'y'=>$nowY, 'h' => $hh, 'w' => $ww, 'lc'=>'0','rc'=>'0');

			$indY++ ;
		}
		//------------------------------------------------------
		// теперь переходы между элементами	
		$left = 1;
		$right = 1;
		$leftElem = 999;
		$rightElem = 0; // границы элементов
		foreach (self::$coord_arr as $coord) {
			if  ($leftElem > $coord['x']-$coord['w'] )    {$leftElem = $coord['x']-$coord['w'] ; }
			if  ($rightElem < $coord['x']+$coord['w'])  {$rightElem = $coord['x']+$coord['w'] ; 	}
		} // определили границы самого левого и самого правого элемента,  потом будем мимо них переходы проводить

		foreach(self::$w_events as $w_key => $w_evnt) {
			self::add('            <bpmndi:BPMNEdge bpmnElement="'.$w_key.'" id="LAB321_'.$w_key.'">')   ;
			$st_from = ($w_evnt['status_from'] ? $w_evnt['status_from'] : "_StartProcess" );
			$st_to = $w_evnt['status_to']; //   для краткости

			// 1.  Переход с уровня N на N+1  - просто переход
			if (self::$di_arr[$st_from]['sort']+1 == self::$di_arr[$st_to]['sort'] )  {
				$wpfx = self::$coord_arr[$st_from]['x'];
				$wpfy = self::$coord_arr[$st_from]['y'];
				$wptx = self::$coord_arr[$st_to]['x'];
				$wpty = self::$coord_arr[$st_to]['y'];
			// это координаты центров. Проверим взаимное расположение
				if ( abs($wpfx-$wptx) < 3 ) { // Х  почти на одном уровне
				// стрелка ВНИЗ [DC-D-TC]
					$wpfy+= self::$coord_arr[$st_from]['h']  ;
					$wpty-= self::$coord_arr[$st_to]['h'] ;
					self::add('                <di:waypoint x="'.$wpfx.'" y="'.$wpfy .'"/>')   ;
					self::add('                <di:waypoint x="'.$wptx .'" y="'.$wpty.'"/>' )  ;
				}
				elseif ( abs($wpfx-$wptx) <= self::$coord_arr[$st_from]['w'] ) { //  элемент source сдвинут относительно target меньше чем на половину ширины
					// стрелка ВНИЗ-ВПРАВО/ВЛЕВО-ВНИЗ   [DC-D-R-TC]
					$wpfy+= self::$coord_arr[$st_from]['h']  ;
					$wpty-= self::$coord_arr[$st_to]['h'] ;
					self::add('            <di:waypoint x="'.$wpfx.'" y="'.$wpfy .'"/>')   ;
					// поставим промежуточную точку
					$mdl_y = ($wpty + $wpfy)/2; 
					self::add('                <di:waypoint x="'.$wpfx.'" y="'.$mdl_y .'"/>' )  ;
					self::add('                <di:waypoint x="'.$wptx.'" y="'.$mdl_y .'"/>' )  ;
					self::add('                <di:waypoint x="'.$wptx .'" y="'.$wpty.'"/>' )  ;
				}	
				else { //  элемент source сдвинут относительно target больше чем на половину ширины
					// стрелка  ВПРАВО/ВЛЕВО-ВНИЗ   [RC-R-D-TC]  [LC-L-D-TC]
					if  ($wpfx < $wptx) { // [RC-R-D-TC]
						$wpfx+= self::$coord_arr[$st_from]['w'] ;
						self::add('                <di:waypoint x="'.$wpfx.'" y="'.$wpfy .'"/>' )  ;
						self::$coord_arr[$st_from]['rc']+=1;
						$wpfy+= $line_gap * self::$coord_arr[$st_from]['rc'] ;  // чтобы был небольшой уклон от горизонтали
					}
					else 	{ // [LC-L-D-TC]
						$wpfx-= self::$coord_arr[$st_from]['w'] ;
						self::add('                <di:waypoint x="'.$wpfx.'" y="'.$wpfy .'"/>' )  ;
						self::$coord_arr[$st_from]['lc']+=1;
						$wpfy+= $line_gap * self::$coord_arr[$st_from]['lc'];  // чтобы был небольшой уклон от горизонтали
					}
					$wpty-= self::$coord_arr[$st_to]['h'] ;
					self::add('                <di:waypoint x="'.$wptx.'" y="'.$wpfy .'"/>' )  ;
					self::add('                <di:waypoint x="'.$wptx .'" y="'.$wpty.'"/>' )  ;
				}
			}  

			// 2.  Переход с уровня N назад на любой уровень  - переход слева
			elseif (self::$di_arr[$st_from]['sort'] > self::$di_arr[$st_to]['sort'] ) {
				self::$coord_arr[$st_from]['lc']+=1;
				self::$coord_arr[$st_to]['lc']+=1;
				$shift = (self::$coord_arr[$st_from]['x'] - self::$coord_arr[$st_from]['w'] - $leftElem) + $left*20;
				$xf = self::$coord_arr[$st_from]['x']-self::$coord_arr[$st_from]['w']-$shift;  
				$yf = self::$coord_arr[$st_from]['y'] - self::$coord_arr[$st_from]['lc'] * $line_gap ; // уклон, чтобы не сливались линии
				$shift = (self::$coord_arr[$st_to]['x'] - self::$coord_arr[$st_to]['w'] - $leftElem) + $left*20;
				$xt = self::$coord_arr[$st_to]['x'] - self::$coord_arr[$st_to]['w']-$shift; 
				$yt = self::$coord_arr[$st_to]['y'] + self::$coord_arr[$st_to]['lc'] *$line_gap ; // уклон,  чтобы не сливались линии;
			
				$xf = ($xf<$xt ? $xt : $xf );
				$xt = ($xt<$xf ? $xf : $xt );
				$tmpx = self::$coord_arr[$st_from]['x'] - self::$coord_arr[$st_from]['w'] ;
				self::add('            <di:waypoint x="'.$tmpx .'" y="'.self::$coord_arr[$st_from]['y']  .'"/>' )  ;
				self::add('            <di:waypoint x="'.$xf .'" y="'.$yf .'"/>' )  ;
				self::add('            <di:waypoint x="'.$xt .'" y="'.$yt .'"/>' )  ;
				$tmpx = self::$coord_arr[$st_to]['x'] - self::$coord_arr[$st_to]['w'];
				self::add('            <di:waypoint x="'.$tmpx .'" y="'.self::$coord_arr[$st_to]['y']  .'"/>' )  ;
				$left++;
			}

			// 3.  Переход с уровня N вперёд больше чем на 1 уровень  - переход справа
			elseif (self::$di_arr[$st_to]['sort'] - self::$di_arr[$st_from]['sort'] > 1 ) {
				self::$coord_arr[$st_from]['rc']+=1;
				self::$coord_arr[$st_to]['rc']+=1;
				$shift = ($rightElem - (self::$coord_arr[$st_from]['x'] + self::$coord_arr[$st_from]['w'])  ) + $right*20;
				$xf = self::$coord_arr[$st_from]['x']+self::$coord_arr[$st_from]['w']+$shift;  ;
				$yf = self::$coord_arr[$st_from]['y'] + self::$coord_arr[$st_from]['rc']*$line_gap ; // чтобы не сливались линии;
				$shift = (self::$coord_arr[$st_to]['x']+self::$coord_arr[$st_to]['w'] + $rightElem) + $right*20;
				$xt = self::$coord_arr[$st_to]['x']+self::$coord_arr[$st_to]['w']+$shift; ;
				$yt = self::$coord_arr[$st_to]['y'] - self::$coord_arr[$st_to]['rc']*$line_gap ; // чтобы не сливались линии;

				$xf = ($xf>$xt ? $xt : $xf );
				$xt = ($xt>$xf ? $xf : $xt );
				$tmpx = self::$coord_arr[$st_from]['x'] + self::$coord_arr[$st_from]['w'] ;
				self::add('            <di:waypoint x="'.$tmpx .'" y="'.self::$coord_arr[$st_from]['y']  .'"/>')   ;
				self::add('            <di:waypoint x="'.$xf .'" y="'.$yf .'"/>' )  ;
				self::add('            <di:waypoint x="'.$xt .'" y="'.$yt .'"/>' )  ;
				$tmpx = self::$coord_arr[$st_to]['x']+self::$coord_arr[$st_to]['w'];
				self::add('            <di:waypoint x="'.$tmpx .'" y="'.self::$coord_arr[$st_to]['y']  .'"/>' )  ;
				$right++;
			}

			self::add('            </bpmndi:BPMNEdge>' )  ;
		}
	
		self::add( "    </bpmndi:BPMNPlane>");
		self::add( "  </bpmndi:BPMNDiagram>");
	}// workflow
	self::add('</definitions>');
}


    
}
?>
