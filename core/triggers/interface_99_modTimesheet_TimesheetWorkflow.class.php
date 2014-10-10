<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		core/triggers/interface_99_modMyodule_Mytrigger.class.php
 * 	\ingroup	mymodule
 * 	\brief		Sample trigger
 * 	\remarks	You can create other triggers by copying this one
 * 				- File name should be either:
 * 					interface_99_modMymodule_Mytrigger.class.php
 * 					interface_99_all_Mytrigger.class.php
 * 				- The file must stay in core/triggers
 * 				- The class name must be InterfaceMytrigger
 * 				- The constructor method must be named InterfaceMytrigger
 * 				- The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class InterfaceTimesheetWorkflow
{

    private $db;

    /**
     * Constructor
     *
     * 	@param		DoliDB		$db		Database handler
     */
    public function __construct($db)
    {
        $this->db = &$db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "Triggers of this module are empty functions."
            . "They have no effect."
            . "They are provided for tutorial purpose only.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'timesheet@timesheet';
    }

    /**
     * Trigger name
     *
     * 	@return		string	Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * 	@return		string	Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Trigger version
     *
     * 	@return		string	Version of trigger file
     */
    public function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') {
            return $langs->trans("Development");
        } elseif ($this->version == 'experimental')

                return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else {
            return $langs->trans("Unknown");
        }
    }

    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "run_trigger" are triggered if file
     * is inside directory core/triggers
     *
     * 	@param		string		$action		Event action code
     * 	@param		Object		$object		Object
     * 	@param		User		$user		Object user
     * 	@param		Translate	$langs		Object langs
     * 	@param		conf		$conf		Object conf
     * 	@return		int						<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function run_trigger($action, $object, $user, $langs, $conf)
    {
        // Put here code you want to execute when a Dolibarr business events occurs.
        // Data and type of action are stored into $object and $action
        // Users
        if ($action == 'LINEBILL_DELETE') {
			$this->db->query('UPDATE '.MAIN_DB_PREFIX.'timesheet
			SET fk_facture=0,fk_facture_ligne=0,status=1
			WHERE fk_facture_ligne = '.$object->rowid);
			
            dol_syslog(
                "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->rowid
            );
        }
		elseif ($action == 'LINEBILL_INSERT') {
			$fk_timesheet = (int)GETPOST('fk_timesheet');

			if($fk_timesheet>0){

				define('INC_FROM_DOLIBARR', true);
				dol_include_once('/timesheet/config.php');
				global $db;
				
				$PDOdb = new TPDOdb;

				$timesheet = new TTimesheet;
				$timesheet->load($PDOdb,'',$_REQUEST['fk_timesheet']);

				$facture = new Facture($db);
				$facture->fetch($object->fk_facture);

				list($pu_ht,$description) = $timesheet->_makeFactureLigne($PDOdb);
				//var_dump($pu_ht, $description);exit;
				$timesheet->fk_facture = $facture->id;
				$timesheet->fk_facture_ligne = $object->rowid;
				
				$timesheet->status = 2;
				
				$timesheet->save($PDOdb);
				
				$desc = $timesheet->libelleFactureLigne." : <br>";
				$desc .= $description;
				
				$object->subprice = $pu_ht;
				$object->desc = $desc;
				$object->total_ht = $pu_ht;
				$object->update($user,1);

			}
		}

        return 0;
    }
}