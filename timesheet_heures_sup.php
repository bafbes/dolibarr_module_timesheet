<?php

require('config.php');

if(!$user->rights->timesheet->user->read) accessforbidden();

_action();

// Protection if external user
if ($user->societe_id > 0)
{
	accessforbidden();
}
var_dump($_REQUEST);
function _action() {
	global $user,$langs,$conf,$mysoc;

	$PDOdb=new TPDOdb;
	$timesheet = new TTimesheet;

	$date_deb=GETPOST('date_deb');
	$date_fin=GETPOST('date_fin');
	$userid=GETPOST('userid');

	if($date_deb) $date_deb = date('Y-m-d 00:00:00',dol_stringtotime($date_deb));
	if($date_fin) $date_fin = date('Y-m-d 00:00:00',dol_stringtotime($date_fin));
	
	$date_deb = (empty($date_deb)) ? date('Y-m-d 00:00:00',strtotime('last Monday')) : $date_deb ;
	$date_fin = (empty($date_fin)) ? date('Y-m-d 00:00:00',strtotime('next Sunday')) : $date_fin ;
	
	$timesheet->set_date('date_deb', $date_deb);
	$timesheet->set_date('date_fin', $date_fin);

	$timesheet->loadProjectTask($PDOdb, $userid,$date_deb,$date_fin);
	
	/*******************************************************************
	* ACTIONS
	*
	* Put here all code to do according to value of "action" parameter
	********************************************************************/
	
	$action=GETPOST('action');

	llxHeader('',$langs->trans('TimeshettUserTimes'),'','',0,0,array('/timesheet/js/timesheet.js.php'));

	
	if($action) {
		switch($action) {
			
		
			case 'edit'	:
			case 'edittime'	:
			case 'changedate' :
				
				_fiche($timesheet,GETPOST('action'),$date_deb,$date_fin);
				break;

			case 'savetime':
				
				$timesheet->savetimevalues($PDOdb,$_REQUEST);
				setEventMessage('TimeSheetSaved');
				
				$timesheet->loadProjectTask($PDOdb, $user->id);
				
				_fiche($timesheet,'edittime',$date_deb,$date_fin);
				break;
				
			
			case 'deleteligne':
				$timesheet->load($PDOdb, $_REQUEST['id']);
				
				$timesheet->deleteAllTimeForTaskUser($PDOdb, GETPOST('fk_task'), GETPOST('fk_user'));
			
				setEventMessage("Ligne de temps supprimée");
			
				$timesheet->load($PDOdb, $_REQUEST['id']);
				
			
				_fiche($timesheet,'view',$date_deb,$date_fin);
				break;
			
		}
		
	}
	else{
				
		
		_fiche($timesheet, 'edittime',$date_deb,$date_fin);
		
	}


	llxFooter();
	
}
	

function _fiche(&$timesheet, $mode='view', $date_deb="",$date_fin="") {
	
	global $langs,$db,$conf,$user;
	$PDOdb = new TPDOdb;
	$date_deb = (empty($date_deb)) ? date('Y-m-d 00:00:00',strtotime('last Monday')) : $date_deb ;
	$date_fin = (empty($date_fin)) ? date('Y-m-d 00:00:00',strtotime('next Sunday')) : $date_fin ;
	
	print dol_get_fiche_head(timesheetPrepareHead( $timesheet, 'timesheet') , 'fiche', $langs->trans('TimeshettUserTimes'));

	$form=new TFormCore();
	$doliform = new Form($db);
	
	if($mode != "edittime"){
		$form->Set_typeaff($mode);
	}
	else{
		$form->Set_typeaff("view");
	}
	
	
	$TBS=new TTemplateTBS();
	$TBS->TBS->protect=false;
	$TBS->TBS->noerr=true;
	
	//Construction du nombre de colonne correspondant aux jours
	$TJours = array(); //Tableau des en-tête pour les jours de la période
	$TFormJours = array(); //Formulaire de saisis nouvelle ligne de temps
	$TligneJours = array(); //Tableau des lignes de temps déjà existante
	
	$TJours = $timesheet->loadTJours(); 

	$form2=new TFormCore($_SERVER['PHP_SELF'],'formtime','POST');

	//transformation de $TJours pour jolie affichage
	foreach ($TJours as $key => $value) {
		$TKey = explode('-', $key);
		$TJoursVisu[$TKey[2].'/'.$TKey[1]] = $value;
	}
	
	//Charger les lignes existante dans le timeSheet
	
	if($mode!='new' && $mode!='edit'){
			
		if($mode=='edittime')$form2->Set_typeaff('edit');
		else $form2->Set_typeaff('view');

		list($TligneTimesheet,$THidden) = $timesheet->loadLines($PDOdb,$TJours,$doliform,$form2,$mode, true, true);
		
		$TligneTimesheetNew = array();
		
		// Pour l'affichage des lignes
		foreach($TligneTimesheet as $line_tab) {
			
			if($line_tab['id_consultant'] > 0)
				$TligneTimesheetNew[$line_tab['id_consultant']] = array(
																	'consultant' => $line_tab['consultant']
																	,'total' => $TligneTimesheetNew[$line_tab['id_consultant']]['total']
																				+ $line_tab['total']
																	,'total_hsup' => 35
																	,'total_hsup_remunerees' => $form2->texte("", "TTimesUser[".$line_tab['id_consultant']."][total_hsup_remunerees]", 36, $pTaille)
																	,'total_hsup_rattrapees' => $form2->texte("", "TTimesUser[".$line_tab['id_consultant']."][total_hsup_rattrapees]", 36, $pTaille)
																);

		}
		
		// Création des hidden :
		/*foreach($TligneTimesheetNew as $id_user => $line_tab) {
			
			if($id_user > 0) {
			
				$THidden[] .= $form2->hidden("TTimesUser[".$id_user."][remunerees]", $TligneTimesheetNew[$line_tab['id_consultant']]['total_hsup_remunerees']);
				$THidden[] .= $form2->hidden("TTimesUser[".$id_user."][rattrapees]", $TligneTimesheetNew[$line_tab['id_consultant']]['total_hsup_rattrapees']);
				
			}
		}*/

		/*$THidden[] = $form2->hidden("TTimesUser[".$line_tab['id_consultant']."][remunerees]", $TligneTimesheetNew[$line_tab['id_consultant']]['total_hsup_remunerees']);
		$THidden[] = $form->hidden("TTimesUser[".$line_tab['id_consultant']."][rattrapees]", $TligneTimesheetNew[$line_tab['id_consultant']]['total_hsup_rattrapees']);*/
		
		$TligneTimesheet = $TligneTimesheetNew;

		$hour_per_day = !empty($conf->global->TIMESHEET_WORKING_HOUR_PER_DAY) ? $conf->global->TIMESHEET_WORKING_HOUR_PER_DAY : 8;
		$nb_second_per_day = $hour_per_day * 3600;
		
		foreach($TligneTimesheet as $cle => $val){
			//$TligneTimesheet[$cle]['total_jours'] = round(convertSecondToTime($val['total_jours'],'allhourmin',$nb_second_per_day)/24);
			$TligneTimesheet[$cle]['total'] = convertSecondToTime($val['total'],'all', $nb_second_per_day);
		}
	}
	$TBS=new TTemplateTBS();
	
	if($mode=='edittime'){
		$form2->Set_typeaff('edit');
	}
	else{
		$form->Set_typeaff("view");
	}
	
	echo $form2->hidden('id', $timesheet->rowid);
	
	if ($mode=='edittime'){
		echo $form2->hidden('action', 'savetime');
	}
	
	echo $form2->hidden('entity', $conf->entity);
	

	foreach($TJours as $date=>$jour){
		$TFormJours['temps'.$date] = $form2->timepicker('', 'temps[0]['.$date.']', '',5);
	}
	
	$form->Set_typeaff("edit");
	
	$date = date_create(date($date_deb));
	$date_deb = date_format($date, 'd/m/Y');
	
	$date = date_create(date($date_fin));
	$date_fin = date_format($date, 'd/m/Y');
	
	if($mode!='new' && $mode != "edit"){
		/*
		 * Affichage tableau de saisie des temps
		 */
		$disabled = 0;
		if(!$user->rights->timesheet->all->read) $disabled = true;

		print $TBS->render('tpl/fiche_saisie_heures_sup.tpl.php'
			,array(
				'ligneTimesheet'=>$TligneTimesheet,
				'lignejours'=>$TligneJours,
				'jours'=>$TJours,
				'joursVisu'=>$TJoursVisu,
				'formjour'=>$TFormJours
				,'THidden'=>$THidden
			)
			,array(
				'timesheet'=>array(
					'rowid'=>0
					,'id'=>$timesheet->rowid
					,'services'=>$doliform->select_produits_list('','serviceid_0','1')
					,'consultants'=>(($user->rights->timesheet->all->read) ? $doliform->select_dolusers('','userid_0') : $form2->hidden('userid_0', $user->id).$user->getNomUrl(1))
					,'commentaireNewLine'=>$form2->texte('', 'lineLabel_0', '', 30,255)
				)
				,'view'=>array(
					'mode'=>$mode
					,'nbChamps'=>count($asset->TField)
					,'head'=>dol_get_fiche_head(timesheetPrepareHead($asset)  , 'field', $langs->trans('AssetType'))
					,'onglet'=>dol_get_fiche_head(array()  , '', $langs->trans('AssetType'))
					,'righttoedit'=>($user->rights->timesheet->user->add && $timesheet->status<2)
					,'TimesheetYouCantIsEmpty'=>addslashes( $langs->transnoentitiesnoconv('TimesheetYouCantIsEmpty') )
					,'date_deb'=>$form->calendrier('', "date_deb", $date_deb)
					,'date_fin'=>$form->calendrier('', "date_fin", $date_fin)
					,'liste_user'=>$doliform->select_dolusers(((GETPOST('userid')) ? GETPOST('userid') : $user->id),'userid',0,'',$disabled)
					,'tous'=>(GETPOST('userid') == 0) ? 'true' : 'false'
				)
				
			)
			
		);
	}
	 
	echo $form2->end_form();
}

?>