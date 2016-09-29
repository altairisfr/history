<?php

	define('INC_FROM_CRON_SCRIPT',true);
	require '../config.php';

	if(empty($conf->global->HISTORY_AUTHORITY_URL)) exit('HISTORY_AUTHORITY_URL not set');

	dol_include_once('/history/class/history.class.php');
	
	$PDOdb=new TPDOdb;
	
	$THistory =  THistory::getHistory($PDOdb, 'payments_not_certified', 0, false,0, 'ASC') ;
	
	$signature=THistory::getSignature();
	
	foreach($THistory as &$h) {
		
		$url = $conf->global->HISTORY_AUTHORITY_URL.'/history/script/authority.php?s='.$signature.'&b='.$h->signature;
		
		$res = file_get_contents($url);
		echo $h->signature. ' '.$res.'<br>';
		if($res === 'blockalreadyadded' || $res === 'blockadded') {
			
			$h->is_certified = 1;
			$h->save($PDOdb);
			
		}
		else {
		
			echo 'ImpossibleToContactAuthority '.$url;
			exit;
		}
		
		
	}
