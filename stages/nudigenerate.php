<?php

require_once 'constants.php';
require_once 'nudistages.php';

$stages = new stages;

$id = $argv[1];
$stage = 1;

$stages->processFiles($id);

?>
