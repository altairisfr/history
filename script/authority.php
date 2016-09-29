<?php
	
	define('INC_FROM_CRON_SCRIPT',true);
	require '../config.php';
	
	dol_include_once('/history/class/history.class.php');
	
	$PDOdb=new TPDOdb;
	
	$auth = new THistoryAutority;
	$auth->init_db_by_vars($PDOdb);
	
	$signature = GETPOST('s');
	$newblock = GETPOST('b');
	$hash = GETPOST('h');
	
	$auth->loadBy($PDOdb, $signature, 'signature');
	$auth->signature = $signature;
	
	if(!empty($hash)) {
		
		echo $auth->checkBlockchain($hash) ? 'hashisok' : 'hashisjunk';
			
	}
	elseif(!empty($newblock)){
		if($auth->checkBlock($newblock)) {
			$auth->addBlock($newblock);
			$auth->save($PDOdb);
			
			echo 'blockadded';
		}
		else{
				
			echo 'blockalreadyadded';
				
		}
	}
	else{
		echo 'idontunderstandwhatihavetodo';
	}
	
	
