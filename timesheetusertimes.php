<?php

require('config.php');

if(!$user->rights->timesheet->user->read) accessforbidden();

$hookmanager = new HookManager($db);
$hookmanager->initHooks('timesheetusertimescard');

_action();

// Protection if external user
if ($user->societe_id > 0)
{
	accessforbidden();
}

function _action() {
	global $user,$langs,$conf,$mysoc,$hookmanager;

	$PDOdb=new TPDOdb;
	$timesheet = new TTimesheet;

	$date_deb=GETPOST('date_deb');
	$date_fin=GETPOST('date_fin');
	$userid=(int)GETPOST('userid');
	if(!$userid && !$user->rights->timesheet->all->read)$userid = $user->id;


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
	$idTimesheet = GETPOST('id', 'int');

	if(! empty($idTimesheet)) { // load() remonté pour être effectué avant le hook
		$timesheet->load($PDOdb, $idTimesheet);
	}

	$parameters = array();
	$reshook = $hookmanager->executeHooks('doActions', $parameters, $timesheet, $action);
	if($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

	if(empty($reshook)) {

		if($action) {
			switch($action) {
				
				case 'view' :
				case 'changedate' :

					_fiche($timesheet,'changedate',$date_deb,$date_fin,$userid);
					break;
				
				case 'edit'	:
				case 'edittime'	:

					_fiche($timesheet,'edittime',$date_deb,$date_fin,$userid);
					break;

				case 'savetime':

					$timesheet->savetimevalues($PDOdb,$_REQUEST);
					setEventMessage('TimeSheetSaved');

					$timesheet->loadProjectTask($PDOdb, $userid,$date_deb,$date_fin);

					_fiche($timesheet,'edittime',$date_deb,$date_fin,$userid);
					break;

				
				case 'deleteligne':
					$timesheet->deleteAllTimeForTaskUser($PDOdb, GETPOST('fk_task'), GETPOST('fk_user'));
				
					setEventMessage($langs->trans('LineDeleted'));
				
					$timesheet->load($PDOdb, $idTimesheet);
				
					_fiche($timesheet,GETPOST('mode'),$date_deb,$date_fin,$userid);
					break;
				
			}
			
		}
		else{
			
			_fiche($timesheet, 'changedate',$date_deb,$date_fin);
			
		}
	} else {
		_fiche($timesheet, 'changedate',$date_deb, $date_fin);
	}
	
}

function getLangTranslate() {
	global $langs;
	
	$Tab=array();
	foreach($langs->tab_translate as $k=>$v) {
		$Tab[$k] = utf8_decode($v);
	}
	
	return $Tab;
	
}
	
	
function _liste() {
	global $langs,$db,$user,$conf;

	$langs->Load('timesheet@timesheet');

	llxHeader('',$langs->trans('TimeshettUserTimes'),'','',0,0,array('/timesheet/js/timesheet.js.php'));

	$TPDOdb=new TPDOdb;
	$TTimesheet = new TTimesheet;

	$sql = "SELECT DISTINCT t.rowid, p.ref, s.nom, t.fk_project, t.fk_societe, t.status, t.date_deb, t.date_fin
			FROM ".MAIN_DB_PREFIX."timesheet as t
				LEFT JOIN ".MAIN_DB_PREFIX."projet as p ON (p.rowid = t.fk_project)
				LEFT JOIN ".MAIN_DB_PREFIX."projet_task as pt ON (pt.fk_projet = p.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (s.rowid = t.fk_societe)
			WHERE t.entity = ".$conf->entity."
			ORDER BY t.date_cre DESC";

	$THide = array(
			'ref',
			'nom'
		);

	$r = new TSSRenderControler($TTimesheet);
	
	$r->liste($TPDOdb, $sql, array(
		'limit'=>array(
			'nbLine'=>'30'
		)
		,'subQuery'=>array()
		,'link'=>array(
			'fk_societe'=>'<a href="'.dol_buildpath('/societe/soc.php?socid=@fk_societe@',2).'">'.img_picto('','object_company.png','',0).' @nom@</a>'
			,'fk_project'=>'<a href="'.dol_buildpath('/projet/'.((float) DOL_VERSION >= 3.7 ? 'card.php' : 'fiche.php').'?id=@fk_project@',2).'">'.img_picto('','object_project.png','',0).' @ref@</a>'
			,'rowid'=>'<a href="'.dol_buildpath('/timesheet/timesheet.php?id=@rowid@',2).'">'.img_picto('','object_calendar.png','',0).' @rowid@</a>'
		)
		,'translate'=>array(
			'status'=>$TTimesheet->TStatus		
		)
		,'hide'=>$THide
		,'type'=>array(
			'date_deb'=>'date'
			,'date_fin'=>'date'
		)
		,'liste'=>array(
			'titre'=>$langs->trans('ListTimesheet')
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','previous.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> 0
			,'messageNothing'=>$langs->trans('AnyTimesheet')
			,'picto_search'=>img_picto('','search.png', '', 0)
		)
		,'title'=>array(
			'date_deb'=>'Date début période'
			,'date_fin'=>'Date fin période'
			
			,'fk_project'=>'Projet'
			,'fk_societe'=>'Société'
			,'rowid'=>'Identifiant'
			,'status'=>$langs->trans('Status')
		)
	));

	if($user->rights->timesheet->user->edit){
		echo '<div class="tabsAction">';
		echo '<a class="butAction" href="?action=new">'.$langs->trans('CreateTimesheet').'</a>';
		echo '</div>';
	}
	
	$TPDOdb->close();

	llxFooter();

}
function _fiche(&$timesheet, $mode='view', $date_deb="",$date_fin="",$userid_selected=0) {

	global $langs,$db,$conf,$user,$hookmanager;
	$PDOdb = new TPDOdb;
	$date_deb = (empty($date_deb)) ? date('Y-m-d 00:00:00',strtotime('last Monday')) : $date_deb ;
	$date_fin = (empty($date_fin)) ? date('Y-m-d 00:00:00',strtotime('next Sunday')) : $date_fin ;
	
	llxHeader('',$langs->trans('TimeshettUserTimes'),'','',0,0,array('/timesheet/js/timesheet.js.php'));

	print dol_get_fiche_head(timesheetPrepareHead( $timesheet, 'timesheet') , 'fiche', $langs->trans('TimeshettUserTimes'));

	$form=new TFormCore();
	$doliform = new Form($db);

	$form->hidden('mode', $mode);
	
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
		
		if(GETPOST('userid')){
			$lastuser = $user;
			$user->fetch(GETPOST('userid'));
		}
		list($TligneTimesheet,$THidden) = $timesheet->loadLines($PDOdb,$TJours,$doliform,$form2,$mode, true);
		
		if(GETPOST('userid')) $user = $lastuser;
		
		$hour_per_day = !empty($conf->global->TIMESHEET_WORKING_HOUR_PER_DAY) ? $conf->global->TIMESHEET_WORKING_HOUR_PER_DAY : 8;
		$nb_second_per_day = $hour_per_day * 3600 * 3600;
		
		foreach($TligneTimesheet as $cle => $val){
			//$TligneTimesheet[$cle]['total_jours'] = round(convertSecondToTime($val['total_jours'],'allhourmin',$nb_second_per_day)/24);
			$TligneTimesheet[$cle]['total'] = convertSecondToTime($val['total'],'allhourmin', $nb_second_per_day);
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
	
	//pre($TligneTimesheet,true);

	$freemode = empty($conf->global->TIMESHEET_USE_SERVICES);
	$TTasks = _getTasks($PDOdb);

	if($freemode){
		?>
		<script type="text/javascript">
			$(document).ready(function(){
				$('tr[id=0] select[name^=serviceid_], tr[id=0] select[name^=userid_]').change(function(){

					tache = $('tr[id=0] select[name^=serviceid_]');
					user = $('tr[id=0] select[name^=userid_]');

					$(tache).attr('name','serviceid_'+$(tache).find(':selected').val());
					$(user).attr('name','userid_'+$(user).find(':selected').val());

					$('tr[id=0] input[id^=temps_]').each(function(i) {
						name = $(this).attr('name');
						temp = name.substr(-12);
						name = 'temps['+$(tache).find(':selected').val()+'_'+$(user).find(':selected').val()+']'+temp;
						$(this).attr('name',name);
					});
				});

			});
		</script>
		<?php
	}
	
	if($mode!='new' && $mode != "edit"){
		$formProjets = new FormProjets($db);
		/*
		 * Affichage tableau de saisie des temps
		 */
		
		print $TBS->render('tpl/fiche_saisie_usertimes.tpl.php'
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
					,'services'=>$freemode ? $form2->combo_sexy('', 'serviceid_0', $TTasks, '') : $doliform->select_produits_list('','serviceid_0','1')
					,'consultants'=>(($user->rights->timesheet->all->read) ? $doliform->select_dolusers($user,'userid_0') : $form2->hidden('userid_0', $user->id).$user->getNomUrl(1))
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
					,'liste_user'=>(!$user->rights->timesheet->all->read) ? '' : $doliform->select_dolusers( -1,'userid')
					,'tous'=>(GETPOST('userid') == 0) ? 'true' : 'false'
					,'userid_selected'=>$userid_selected
					,'freemode'=>$freemode
				)
				,'langs'=>$langs
			)
			
		);
	}
	 
	echo $form2->end_form();

	if($mode == 'edittime' && ! empty($conf->absence->enabled) && empty($conf->global->TIMESHEET_RH_NO_CHECK)) {
		?>
	<script type="text/javascript">
		$(document).ready(function() {
			$('#userid_0').change(function() {
				var date_deb = '<?php echo $timesheet->get_date('date_deb', 'd/m/Y'); ?>';
				var date_fin = '<?php echo $timesheet->get_date('date_fin', 'd/m/Y'); ?>';
				var userid = parseInt($(this).val());

				if(userid > 0) {
					$.ajax({
						method: 'GET'
						, url: '<?php echo dol_buildpath('/timesheet/script/interface.php', 1); ?>'
						, data: {
							get: 'get_emploi_du_temps'
							, fk_user: userid
							, date_deb: date_deb
							, date_fin: date_fin
						}
						, dataType: 'json'
						, success: function(data) {console.log(data);
							for(var i = 0; i < data.length; i++) {
								var elem = $('input#temps_0__' + data[i].date + '_');
								if(elem.length > 0) {
									elem.val(data[i].time);
								}
							}
						}
					});
				}
			});
		});
	</script>

<?php
	}

	$parameters = array();
	$hookmanager->executeHooks('afterCard', $parameters, $timesheet, $mode); // pas 'addMoreActionsButtons' car boutons ajoutés plus haut via TBS

	llxFooter();
}

function _fiche_visu_project(&$timesheet, $mode){
	global $db;

	if($mode=='edit' || $mode=='new') {
		ob_start();
		$html=new FormProjets($db);
		$html->select_projects($timesheet->fk_societe, $timesheet->fk_project, 'fk_project');


		return ob_get_clean();

	}
	else {
		if($timesheet->fk_project > 0) {
			require_once(DOL_DOCUMENT_ROOT.'/projet/class/project.class.php');

			$project = new Project($db);
			$project->fetch($timesheet->fk_project);
			
			return $project->getNomUrl(1);
			
		} else {
			return $langs->trans('NotDefined');
		}
	}
}

function _fiche_visu_societe(&$timesheet, $mode) {
	global $db;

	if($mode=='edit' || $mode=='new') {
		ob_start();

		$html=new Form($db);
		echo $html->select_company($timesheet->fk_societe,'fk_societe','',1,0,1);

		?>
		<script type="text/javascript">
			
			$('#fk_societe').change(function() {
				
				_select_other_project();
				
			});
			
			function _select_other_project() {
				
				$('#timesheet-project-list').load('<?php echo $_SERVER['PHP_SELF'] ?>?action=new&fk_societe='+$('#fk_societe').val()+' #timesheet-project-list');
				
			}
			
		</script>
		
		
		<?php

		return ob_get_clean();

	}
	else {
		if($timesheet->fk_societe > 0) {
			require_once(DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php');

			$soc = new Societe($db);
			$soc->fetch($timesheet->fk_societe);

			return '<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$timesheet->fk_societe.'" style="font-weight:bold;">'.img_picto('','object_company.png', '', 0).' '.$soc->nom.'</a>';
		} else {
			return $langs->trans('NotDefined');
		}
	}
}

function _getTasks(&$PDOdb)
{
	$TRes = array(0 => '');

	$sql = "SELECT t.rowid, t.ref, t.label, p.ref as ref_projet, p.title as title_projet";
	$sql.= " FROM ".MAIN_DB_PREFIX."projet_task t";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."projet p on (t.fk_projet = p.rowid)";
	$sql.= " WHERE p.fk_statut = 1";

	$TTasks = $PDOdb->ExecuteAsArray($sql);

	foreach($TTasks as $task) {
		$TRes[$task->rowid]  = $task->ref_projet . ' - ' . $task->title_projet . ' - ' . $task->ref . ' - ' . $task->label;
	}

	return $TRes;
}

?>
