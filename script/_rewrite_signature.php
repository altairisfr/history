<?php

	require 'config.php';
	
	dol_include_once('/history/class/history.class.php');
	
	$PDOdb=new TPDOdb;
	
	$THistory = THistory::getHistory($PDOdb, 'payments', 0, false,0,'ASC') ;

	foreach($THistory as &$h) {
		
		$h->signature = $h->getSignatureRecursive($PDOdb);
		$h->save($PDOdb);
		
		echo $h->getId().' : '.$h->signature.'</br>';
		
	}
