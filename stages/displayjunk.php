<?php

	require_once 'constants.php';
	require_once 'dumpjunk.php';
	
	if(isset($argv[1]))	{
		
		$id = $argv[1];
	}
	else{
		
		echo "\n\tERROR: Please enter Book id\n\n";
		exit;
	}
	
	if(isset($argv[2]))	{
		
		$language = $argv[2];
	}
	else{
		
		echo "\n\tERROR: Please Specify language [kan, hin, tel]\n\n";
		exit;
	}
	
	
	$dumpjunk = new Dumpjunk();
	
	if(!$dumpjunk->setLanguageContraint($language))	{
		echo "\n\tERROR: Language Constraints not defined in JSON_PRECAST file\n\n";
		exit;
	}
	
	$dumpjunk->sanityCheck($id);
	$dumpjunk->extractJunk($id);
?>
