<?php

class THistory extends TObjetStd {
/*
 * Gestion des équipements
 * */

    function __construct() {
        $this->set_table(MAIN_DB_PREFIX.'history');
        $this->add_champs('fk_object,fk_object_deleted,entity',array('type'=>'integer','index'=>true));
        $this->add_champs('key_value1','type=float;index;');
        $this->add_champs('fk_user', 'type=entier;');
        $this->add_champs('type_object,type_action,ref,table_element', array('type'=>'string','index'=>true));
		$this->add_champs('signature', array('type'=>'string','index'=>true, 'length'=>128)); // might be smaller
		$this->add_champs('object', array('type'=>'array'));
        $this->add_champs('date_entry','type=date;');
        $this->add_champs('what_changed',array('type'=>'text'));

        $this->_init_vars();

        $this->start();

	}
	
	function show_ref() {
		global $db,$user,$conf,$langs;

		dol_include_once('/'.$this->type_object.'/class/'.$this->type_object.'.class.php');

		$class = ucfirst($this->type_object);

		if($class=='Project_task') $class='Task';
		else if($class=='Order_supplier') $class='CommandeFournisseur';
		else if($class=='Invoice_supplier') $class='FactureFournisseur';

		if(!class_exists($class )) return $langs->trans('CantInstanciate').' : '.	$class;
        $object=new $class($db);

        $res = $object->fetch($this->fk_object);

		if($res<=0 || $object->id == 0) {
			$r = $langs->trans('WholeObjectDeleted', $langs->trans($this->type_object));
			if(!empty($this->ref))$r.=' '.$this->ref;
			
		}
		else if(method_exists($object, 'getNomUrl')) {
            $r = $object->getNomUrl(1);
        }

		return $r;
	}

	function setRef(&$object) {

		if(!empty($object->code_client)) $this->ref = $object->code_client;
		else if(!empty($object->facnumber)) $this->ref = $object->facnumber;
		else if(!empty($object->ref)) $this->ref = $object->ref;

	}
    function compare(&$newO, &$oldO)
    {
    	$this->what_changed = '';
        $this->what_changed .= $this->cmp($newO, $oldO);
    	$this->what_changed .= $this->cmp($newO->array_options, $oldO->array_options, true);
    }

    private function cmp(&$newO, &$oldO, $checkArrayOptions = false)
    {
        if(empty($newO) || empty($oldO)) return '';

        $diff = '';

        foreach($newO as $k=>$v)
        {
            if(!is_array($v) && !is_object($v))
            {

				if ($checkArrayOptions)
				{
					if($oldO[$k] !== $v && (!empty($v) || (!empty($oldO[$k]) &&  $oldO[$k] !== '0.000') ) )
	            	{
	            		// substr remove options_
	                    $diff.=substr($k, 8).' : '.$oldO[$k].' => '.$v."\n";
	                }
				}
				else
				{
					//isset($oldO->{$k}) => renvoi false sur $oldO->zip car défini à null
	                if(property_exists($oldO, $k) // vérifie que l'attribut exist
	                	&& !is_object($oldO->{$k})
	                	&& $oldO->{$k} !== $v
	                	&& (!empty($v) || (!empty($oldO->{$k}) &&  $oldO->{$k} !== '0.000' )   )
						)
	            	{
	                    $diff.=$k.' : '.$oldO->{$k}.' => '.$v."\n";
	                }
				}

            }

        }

        return $diff;
    }

    function show_whatChanged(&$PDOdb, $show_details = true, $show_restore = true) {
	global $conf,$user;

		$r = nl2br(htmlentities($this->what_changed));


		if(!empty($conf->global->HISTORY_STOCK_FULL_OBJECT_ON_DELETE)) {
			if($show_details && !empty($this->object)) $r.=' <a href="?type_object='.$this->type_object.'&id='.$this->fk_object.'&showObject='.$this->getId().'">'.img_view().'</a>';

			if($show_restore && !empty($user->rights->history->restore)) {
				$PDOdb->Execute("SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element.'_deletedhistory');
				if($obj=$PDOdb->Get_line()) {
					$r.=' <a href="?type_object='.$this->type_object.'&id='.$this->fk_object.'&restoreObject='.$this->getId().'">'.img_picto('Restore', 'refresh').'</a>';
				}

			}

		}


		return $r;

    }

    function show_action() {
        global $langs;
        $action='';

        $action = $langs->trans($this->type_action);
//var_dump($this);
        return $action;
    }

    function show_user() {
        global $db;

        $u=new User($db);
        $u->fetch($this->fk_user);

        return $u->getLoginUrl(1);

    }

    function save(&$PDOdb) {
		global $conf;
        if(empty($this->fk_user) || empty($this->fk_object) || empty($this->type_action) || empty($this->what_changed)) return false;

		$this->entity = $conf->entity;
		
		if(empty($this->signature)) $this->signature = $this->getSignatureRecursive($PDOdb );

        return parent::save($PDOdb);
    }

	
	function getSignatureRecursive(&$PDOdb, $from_id = 0){
		
		if($this->type_object === 'payment') {
			$signature = md5( $this->type_action . self::getSignature() . $this->key_value1  );	
			
			$THistory =  self::getHistory($PDOdb, 'payments', 0, true,0, 'ASC') ;
			//var_dump($THistory);
			foreach($THistory as $h) {
				
				if($from_id>0 && $h->rowid == $from_id) break; // on arrête sur un enregistrement précis pour recalculer une signature
				
				$signature = md5($signature. $this->type_action . $h->signature . $h->key_value1);
			}
			//var_dump($from_id,$signature);
			//exit($signature);
			return $signature;
		}
		
		return '';
	} 

	function checkSignature(&$PDOdb) {
		
		$signature = $this->getSignatureRecursive($PDOdb, $this->getId());
		//var_dump(array($signature , $this->signature));
		return ($signature === $this->signature);
		
	}

    static function getHistory(&$PDOdb, $type_object, $fk_object, $justTheMinimum = false, $limit = 0, $order = 'DESC') {
		global $conf;
        if($type_object == 'task') $type_object = 'project_task';
		if($type_object == 'invoice')$type_object = 'facture';

		if($type_object=='deletedElement') {
			$sql="SELECT rowid FROM ".MAIN_DB_PREFIX."history
	         WHERE  entity=".$conf->entity." AND  type_action LIKE '%DELETE%'
	         ORDER BY date_entry ".$order;

		}
		else if($type_object=='payments') {
			$sql="SELECT rowid,signature,key_value1 FROM ".MAIN_DB_PREFIX."history
	         WHERE entity=".$conf->entity." AND  type_action LIKE '%PAYMENT%'
	         ORDER BY date_entry ".$order;

		}
		else{
			$sql="SELECT rowid FROM ".MAIN_DB_PREFIX."history
	         WHERE type_object='".$type_object."' AND fk_object=".(int)$fk_object."
	         ORDER BY date_entry ".$order;

		}


		if($limit > 0 )$sql.=' LIMIT '.$limit;

        $Tab = $PDOdb->ExecuteAsArray($sql);

        $TRes=array();
        foreach($Tab as $row){

			if($justTheMinimum) {
				$TRes[] = $row;
			}
			else{
		        $h=new THistory;
		        $h->load($PDOdb, $row->rowid);
			
	
	            $TRes[] = $h;
				
			}

        }

        return $TRes;

    }
    static function addHistory(&$PDOdb, &$user, $type_object, $fk_object, $action, $what_changed = 'cf. action') {

            $h=new THistory;
            $h->fk_object = $fk_object;
            $h->what_changed = $what_changed;
            $h->type_action = $action;
            $h->fk_user = $user->id;
            $h->type_object = $type_object;
            $h->save($PDOdb);
    }

	static function restoreCopy(&$PDOdb,$id_to_restore) {

		$h=new THistory;
		if($h->load($PDOdb, $id_to_restore )){
			global $db,$langs;

			$table = MAIN_DB_PREFIX.$h->table_element;
			$backup_table = $table.'_deletedhistory';

			$obj = new TObjetStd;
			$obj->set_table($backup_table);
			$obj->init_vars_by_db($PDOdb);
			$obj->load($PDOdb, $h->fk_object_deleted);

			$obj2 = clone $obj;

			$PDOdb->Execute("set foreign_key_checks = 0");

			$obj2->set_table($table);
			$obj2->init_db_by_vars($PDOdb);
			$obj2->date_cre = $obj2->date_maj = time();
//			$PDOdb->debug = true;
			$PDOdb->insertMode ='REPLACE';
			$obj2->save($PDOdb);
//			exit;
			setEventMessage($langs->trans("DeletedObjectRestored"));
		}

	}

	static function makeCopy(&$PDOdb, &$object) {

		if(is_object($object) && !empty($object->table_element)){

			$table = MAIN_DB_PREFIX.$object->table_element;
			$backup_table = $table.'_deletedhistory';
			//$PDOdb->debug=true;
			$obj = new TObjetStd;
			$obj->set_table($table);
			$obj->init_vars_by_db($PDOdb);
			$obj->load($PDOdb, $object->id);

			$obj2 = clone $obj;

			$obj2->set_table($backup_table);
			$obj2->init_db_by_vars($PDOdb);
			$obj2->date_cre = $obj2->date_maj = time();

			$res = $PDOdb->Execute("INSERT INTO ".$backup_table." (rowid) VALUES (".$object->id.")");
			$obj2->save($PDOdb);

		}

		foreach($object as $k=>$v) {

			if(is_object($v) || is_array($v)) {
				self::makeCopy($PDOdb, $v);
			}

		}


	}
	
	static function getSignature() {
		global $db,$conf,$mysoc;
		
		if(empty($conf->global->HISTORY_DOLIBARR_SIGNATURE)) {
			
			$my_signature = md5(print_r($mysoc,true).time().rand(0,1000)); 
			
			dolibarr_set_const($db, 'HISTORY_DOLIBARR_SIGNATURE', $my_signature, '',0,'Signature numérique', $conf->entity);
			
			$conf->global->HISTORY_DOLIBARR_SIGNATURE = $my_signature;
		}
		
		return $conf->global->HISTORY_DOLIBARR_SIGNATURE;
	}


}
