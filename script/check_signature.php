<?php

	define('INC_FROM_CRON_SCRIPT',true);
	require '../config.php';

	if(empty($conf->global->HISTORY_AUTHORITY_URL)) exit('HISTORY_AUTHORITY_URL not set');
	
	dol_include_once('/history/class/history.class.php');
	
	$PDOdb=new TPDOdb;
	
	$THistory =  THistory::getHistory($PDOdb, 'payments_just_certified', 0, true,0, 'ASC') ;

	$auth=new THistoryAutority;
	$auth->signature = THistory::getSignature();
	//var_dump($THistory);
	foreach($THistory as &$h) {
	//echo $h->signature;
		$auth->blockchain.=$h->signature;	
			
	}
	
	$hash = $auth->getBlockchainHash();
	
	$url = $conf->global->HISTORY_AUTHORITY_URL.'/history/script/authority.php?s='.$auth->signature.'&h='.$hash;
	//	echo $url.'<br>';
	$res = file_get_contents($url);
	
	echo $res;