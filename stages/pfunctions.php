<?php

	function getAllFiles($bookID) {

		$allFiles = [];
		
		$folderPath = RAW_SRC . $bookID . '/Stage3a/';
		
	    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderPath));

	    foreach($iterator as $file => $object) {
	    	if(preg_match('/.*\.htm[l]?$/',$file)) array_push($allFiles, $file);
	    }

	    sort($allFiles);

		return $allFiles;
	}

	function process($bookID, $file) {

		$fileName = preg_replace('/.*\/(.*)\..*/', "$1", $file);
		$content = file($file);

		if (!file_exists(RAW_SRC . $bookID . '/Stage4/')) {
			mkdir(RAW_SRC . $bookID . '/Stage4/', 0775);
			echo "Stage4 directory created\n";
		}

		$fp = fopen(RAW_SRC . $bookID . '/Stage4/' . $fileName . '.xhtml', 'w');

		fwrite($fp, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
		fwrite($fp, '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" xmlns:epub="http://www.idpf.org/2007/ops">' . "\n");
		fwrite($fp, '<head>' . "\n");
		fwrite($fp, '<title></title>' . "\n");
		fwrite($fp, '<link rel="stylesheet" type="text/css" href="css/style.css" />' . "\n");
		fwrite($fp, '</head>' . "\n");
		fwrite($fp, '<body>' . "\n");

		$insideBody = false;
		$currentLevel = 0;
		$insideLevel2 = false;

		foreach ($content as $line) {

			if(preg_match('/<BODY>/', $line)) $insideBody = true;

			elseif($insideBody){

				if(preg_match('/<H1>(.*?)<\/H1>/', $line)) {

					for ($i=1; $i <= $currentLevel; $i++) fwrite($fp, '</section>' . "\n");

					fwrite($fp, '<section class="level1 numbered" epub:type="chapter" role="doc-chapter" id="id-">' . "\n");
					fwrite($fp, '<h1 class="level1-title" epub:type="title">' . trim(preg_replace('/<H1>(.*?)<\/H1>/', "$1", $line)) . '</h1>' . "\n");
					$currentLevel = 1;
					$insideLevel2 = false;
				}
				elseif(preg_match('/<H2>(.*?)<\/H2>/', $line)) {
					
					if($insideLevel2) fwrite($fp, '</section>' . "\n");

					fwrite($fp, '<section class="level2 numbered" id="id-.">' . "\n");
					fwrite($fp, '<h2 class="level2-title" epub:type="title">' . trim(preg_replace('/<H2>(.*?)<\/H2>/', "$1",  $line)) . '</h2>' . "\n");

					$currentLevel = 2;
					$insideLevel2 = true;
				}
				elseif(preg_match('/<P>(.*)<\/P>/i', $line)) {

					fwrite($fp, '<p>' . replaceTags(trim(preg_replace('/<P>(.*)<\/P>/i', "$1", $line))) . "</p>\n");
				}
				//~ elseif(preg_match('/<P>/i', $line)) {

					//~ fwrite($fp, "<p>\n");
				//~ }
				//~ elseif(preg_match('/<\/P>/i', $line)) {

					//~ fwrite($fp, "</p>\n");
				//~ }
				elseif(preg_match('/SECTION|HTML|BODY|<P\/>/i', $line)) {
					continue;
				}
				else {
					fwrite($fp, replaceTags(trim($line)) . "\n");
				}
			}
		}

		for ($i=1; $i <= $currentLevel; $i++) fwrite($fp, '</section>' . "\n");

		fwrite($fp, '</body>' . "\n");
		fwrite($fp, '</html>' . "\n");
		fclose($fp);
	}

	function replaceTags($line) {
		
		$line = preg_replace('/SPAN>/i', 'span>', $line);
		$line = preg_replace('/Sub>/i', 'sub>', $line);
		$line = str_replace('TABLE>', 'table>', $line);
		$line = str_replace('TR>', 'tr>', $line);
		$line = str_replace('TH>', 'th>', $line);
		$line = str_replace('TD>', 'td>', $line);
		$line = str_replace('LI>', 'li>', $line);
		$line = str_replace('UL>', 'ul>', $line);
		$line = str_replace('OL>', 'ol>', $line);
		$line = str_replace('DL>', 'dl>', $line);
		$line = str_replace('DT>', 'dt>', $line);
		$line = str_replace('DD>', 'dd>', $line);
		$line = str_replace('BR/>', 'br />', $line);
		$line = str_replace('B>', 'strong>', $line);
		$line = str_replace('I>', 'em>', $line);
		$line = str_replace('U>', 'u>', $line);
		$line = str_replace('<HR/>', '', $line);
		$line = preg_replace('/H(\d)>/', "h$1>", $line);

		$line = preg_replace('/(\d+):ಂ/', '${1}:0', $line);
		$line = preg_replace('/ಂ(\d+)/', '0${1}', $line);
		$line = preg_replace('/(\d+)ಂ/', '${1}0', $line);
		$line = preg_replace('/<SPAN class="en">([()!\-\.\':;"@©?—])(.*?)<\/SPAN>/i', "$1$2", $line);
		$line = preg_replace('/<Span class=\"normal\">(.*)<\/span>/i', "$1", $line);
		$line = str_replace('ಂಂ', '00', $line);
		
		$line = str_replace("\'", "'", $line);
		$line = str_replace("<B/>", '', $line);
		$line = preg_replace('/<p>\s+<\/p>/', '', $line);
		$line = str_replace('<I/>', '', $line);
		$line = str_replace('<strong></strong>', '', $line);

		//~ $line = str_replace('0', '೦', $line);
		//~ $line = str_replace('1', '೧', $line);
		//~ $line = str_replace('2', '೨', $line);
		//~ $line = str_replace('3', '೩', $line);
		//~ $line = str_replace('4', '೪', $line);
		//~ $line = str_replace('5', '೫', $line);
		//~ $line = str_replace('6', '೬', $line);
		//~ $line = str_replace('7', '೭', $line);
		//~ $line = str_replace('8', '೮', $line);
		//~ $line = str_replace('9', '೯', $line);

		$line = preg_replace('/<Sup(.*)>(.*)<\/Sup>/i', '<sup' . "$1" . '><a epub:type="noteref" href="999-aside.xhtml#id-">' . "$2" . '<\/a><\/sup>', $line);

		return $line;
	}
?>
