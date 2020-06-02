<?php

	require_once 'constants.php';
	require_once 'pfunctions.php';

	$bookID = $argv[1];
	
	$allFiles = getAllFiles($bookID);

	foreach($allFiles as $file){

		process($bookID, $file);
	}
	
?>
