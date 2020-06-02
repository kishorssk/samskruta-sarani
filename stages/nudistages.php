<?php

class Stages{

	public function __construct() {
		
	}

	public function processFiles($bookID) {


		$allFiles = $this->getAllFiles($bookID);

		foreach($allFiles as $file){
	
			$this->process($bookID,$file);		
		}
	
	}

	public function getAllFiles($bookID) {

		$allFiles = [];
		
		$folderPath = RAW_SRC . $bookID . '/Stage1/';
		
	    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderPath));

	    foreach($iterator as $file => $object) {
	    	
	    	if(preg_match('/.*\.htm[l]?$/',$file)) array_push($allFiles, $file);
	    }

	    sort($allFiles);

		return $allFiles;
	}


	public function process($bookID,$file) {

		// stage1.html : Input html from adobe acrobat
		$rawHTML = file_get_contents($file);

		// Process html to strip off unwanted tags and elements
		$processedHTML = $this->processRawHTML($rawHTML);

		// stage2.html : Output html for conversion		
		$baseFileName = basename($file);

		if (!file_exists(RAW_SRC . $bookID . '/Stage2/')) {
			mkdir(RAW_SRC . $bookID . '/Stage2/', 0775);
			echo "Stage2 directory created\n";
		}

		$fileName = RAW_SRC . $bookID . '/Stage2/' . $baseFileName;

		// $processedHTML = html_entity_decode($processedHTML, ENT_QUOTES);
		file_put_contents($fileName, $processedHTML);

		// Convert APS data to Unicode retaining html tags
		
		$unicodeHTML = $this->parseHTML($processedHTML);

		$unicodeHTML = preg_replace("/><p>/i", ">\n<P>", $unicodeHTML);
		$unicodeHTML = preg_replace("/<\/p></i", "</P>\n<", $unicodeHTML);

		// stage3.html : Output Unicode html with tags, english retained as it is
		if (!file_exists(RAW_SRC . $bookID . '/Stage3a/')) {
			mkdir(RAW_SRC . $bookID . '/Stage3a/', 0775);
			echo "Stage3a directory created\n";
		}

		$fileName = RAW_SRC . $bookID . '/Stage3a/' . $baseFileName;	

		$unicodeHTML = html_entity_decode($unicodeHTML);

		file_put_contents($fileName, $unicodeHTML);

		if(file_exists(RAW_SRC . $bookID . '/Stage3/' . $baseFileName)) {

			$unicodeHTML = preg_replace('/<sup>.*?<\/sup>/i', ' ', $unicodeHTML);
			$strippedHTML = strip_tags($unicodeHTML);
			// new file normalizations
			$strippedHTML = str_replace('.', '. ', $strippedHTML);
			$strippedHTML = preg_replace('/\s+/', ' ', $strippedHTML);
			$strippedHTML = preg_replace('/ /', "\n", $strippedHTML);
			$strippedHTML = str_replace('–', '-', $strippedHTML);

			$fileNameAfter = RAW_SRC . $bookID . '/Stage3a/' . $baseFileName . '.after.txt';	
			file_put_contents($fileNameAfter, $strippedHTML);

			$oldHTML = file_get_contents(RAW_SRC . $bookID . '/Stage3/' . $baseFileName);

			// remove 200c character
			$oldHTML = str_replace('‌', '', $oldHTML);
			file_put_contents(RAW_SRC . $bookID . '/Stage3/' . $baseFileName, $oldHTML);

			$oldHTML = preg_replace('/<sup>.*?<\/sup>/i', ' ', $oldHTML);
			$oldHTML = strip_tags($oldHTML);
			$oldHTML = preg_replace('/\s+/', ' ', $oldHTML);
			$oldHTML = preg_replace('/ /', "\n", $oldHTML);

			$fileNameBefore = RAW_SRC . $bookID . '/Stage3a/' . $baseFileName . '.before.txt';	
			file_put_contents($fileNameBefore, $oldHTML);

			$fileNameDiff = RAW_SRC . $bookID . '/Stage3a/' . $baseFileName . '.diff';	
			exec('diff ' . $fileNameBefore . ' ' . $fileNameAfter . ' > ' . $fileNameDiff);
			exec('rm ' . $fileNameBefore);
			exec('rm ' . $fileNameAfter);
		}
	}

	public function parseHTML($html) {

		$dom = new DOMDocument("1.0");
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;

		$dom->loadXML($html);
		$xpath = new DOMXpath($dom);

		foreach($xpath->query('//text()') as $text_node) {

			if(preg_replace('/\s+/', '', $text_node->nodeValue) === '') continue; 

			if($text_node->parentNode->hasAttribute('class'))
				if($text_node->parentNode->getAttribute('class') == 'en')
					 continue;

			// echo $text_node->nodeValue . "\n";		
			$text_node->nodeValue = $this->nudi2Unicode($text_node->nodeValue);
			 // echo $text_node->nodeValue . "\n";		

		}

		return $dom->saveXML();
	}

	public function processRawHTML($text) {

		$text = preg_replace('/<!--.*/', "", $text);
		$text = preg_replace('/`/', "'", $text);
		$text = preg_replace('/“/', '"', $text);
		$text = preg_replace('/”/', '"', $text);
		$text = preg_replace('/’/', "'", $text);
		$text = preg_replace('/‘/', "'", $text);

		$text = str_replace("\n", "", $text);
		$text = str_replace("\r", "", $text);
		$text = preg_replace('/&([^a-zA-Z])/', "&amp;$1", $text);

		$text = preg_replace('/(<[a-zA-Z])/', "\n$1", $text);
		$text = preg_replace('/>/', ">\n", $text);

		// Special cases that need to be retained
		$text = preg_replace('/<SPAN .*font.*(times|Sylfaen|Tahoma|Arial|Garamond|Helvetica|Palatino Linotype).*>/i', '<SPAN-class="en">', $text);
		$text = preg_replace('/<SPAN .*font-weight.*bold.*>/i', '<SPAN-class="bold">', $text);
		$text = preg_replace('/<SPAN .*font-style.*italic.*>/i', '<SPAN-class="italic">', $text);
		$text = preg_replace('/<SPAN .*font-weight:normal.*?>/i', '<SPAN>', $text);
		

		// General cases that need to be deleted
		$text = preg_replace('/<([a-zA-Z0-9]+) .*\/>/i', "<$1-/>", $text);
		$text = preg_replace('/<([a-zA-Z0-9]+) .*>/i', "<$1>", $text);

		// Special cases - reverted to original form
		$text = str_replace('<SPAN-', '<SPAN ', $text);
		//~ $text = str_replace('</SPAN', '</SPAN ', $text);
		$text = str_replace('-/>', ' />', $text);

		// Remove unecessary tags
		$text = preg_replace("/\n<IMG.*/i", "", $text);

		
		// Clean up and indent file	

		$text = preg_replace("/\n+/", "\n", $text);
		$text = preg_replace("/[ \t]+/", " ", $text);

		$text = preg_replace("/</", "\n<", $text);
		$text = preg_replace("/>\n/", ">", $text);
		$text = preg_replace("/\n+/", "\n", $text);

		$text = preg_replace("/<Span class=\"bold\">(.*)\n<\/span>/i", "<strong>$1</strong>", $text);
		$text = preg_replace("/<Span class=\"italic\">(.*)\n<\/span>/i", "<em>$1</em>", $text);
		//~ $text = preg_replace("/<Span>/i", "<SPAN>", $text);
		//~ $text = preg_replace("/<\/Span>/i", "</SPAN>", $text);

		$text = preg_replace("/[\n]*<Span class=\"en\">(.*)\n<\/span>[\n]*/i", "<SPAN class=\"en\">$1</SPAN>", $text);
		$text = preg_replace("/[\n]*<Span>(.*)\n<\/span>[\n]*/i", "$1", $text);
		
		$text = preg_replace("/<Span>/i", "<SPAN>", $text);
		$text = preg_replace("/<\/Span>/i", "</SPAN>", $text);

		$text = preg_replace("/\n<(Span|Sup|Sub|Strong|em|I|B|U)/i", "<$1", $text);
		// $text = preg_replace("/<(Span|Sup|Sub|Strong|em)>\n/i", "<$1>", $text);
		$text = preg_replace("/\n<\/(Span|Sup|Sub|Strong|em|I|B|U)>/i", "</$1>", $text);
		$text = preg_replace("/<\/(Span|Sup|Sub|Strong|em|I|B|U)>\n/i", "</$1>", $text);

		$text = preg_replace("/(<I>)/i", "$1", $text);
		$text = preg_replace("/\n(<\/I>)/i", "$1", $text);
		
		$text = preg_replace("/<P>\n/i", "<P>", $text);
		$text = preg_replace("/\n<\/P>/i", "</P>", $text);
		
		$text = preg_replace("/<LI>\n/i", "<LI>", $text);
		$text = preg_replace("/\n<\/LI>/i", "</LI>", $text);
		
		$text = preg_replace("/<LI>/i", "<LI>", $text);
		$text = preg_replace("/<\/LI>/i", "</LI>", $text);

		$text = preg_replace("/\n(<H\d>)/i", "$1", $text);
		$text = preg_replace("/\n(<\/H\d>)/i", "$1", $text);

		$text = preg_replace("/(<BR\/>)\n/i", "$1", $text);
		
		$text = str_replace("\n ", " ", $text);

		$text = str_replace("<DIV", "<SECTION", $text);
		$text = str_replace("</DIV>", "</SECTION>", $text);

		// Special case to handle nested en
		$text = preg_replace('/<SPAN class="en">(.*?)<([a-zA-Z]+)>(.*?)<\/\2>(.*?)<\/SPAN>/i', "<SPAN class=\"en\">$1<$2 class=\"en\">$3</$2>$4</SPAN>", $text);

		$text = preg_replace("/<SPAN class=\"en\">(.*)<\/SPAN>\n/", "<strong>$1</strong>", $text);
		$text = str_replace("</strong><strong>", "", $text);

		$text = preg_replace("/<head>/i", "\n<HEAD>", $text);
		$text = preg_replace("/<\/head>/i", "</HEAD>\n", $text);

		// Remove head items
		$text = preg_replace("/<(STYLE|META|HEAD).*\n/i", "", $text);
		$text = preg_replace("/<\/(STYLE|META|HEAD).*\n/i", "", $text);

		$text = preg_replace("/<body>/i", "<BODY>", $text);
		$text = preg_replace("/<\/body>/i", "</BODY>", $text);

		$text = preg_replace("/<p>/i", "<P>", $text);
		$text = preg_replace("/<\/p>/i", "</P>", $text);

		$text = preg_replace("/<b>/i", "<B>", $text);
		$text = preg_replace("/<\/b>/i", "</B>", $text);
		
		$text = preg_replace("/<i>/i", "<I>", $text);
		$text = preg_replace("/<\/i>/i", "</I>", $text);

		$text = preg_replace("/<u>/i", "<U>", $text);
		$text = preg_replace("/<\/u>/i", "</U>", $text);
		
		$text = preg_replace("/<h6>/i", "<H6>", $text);
		$text = preg_replace("/<\/h6>/i", "</H6>", $text);
		
		$text = preg_replace("/<br>/", "<BR />", $text);
		$text = preg_replace("/<hr>/", "<HR />", $text);

		$text = str_replace("&nbsp;", " ", $text);

		return $text;
	}

	public function APS2Unicode($text) {

		$tmpTXTFile = TMP_FILES . "tmp.txt";
		$tmpHTMLFile = TMP_FILES . "tmp.html";

		file_put_contents($tmpTXTFile, $text . "\n");

		exec("perl to_uni.pl " . $tmpTXTFile . " " . $tmpHTMLFile, $out, $err);

		if($err){
			echo $err;
			print_r( $out );
		}

		$outputString = rtrim(file_get_contents($tmpHTMLFile),"\n");
		$outputString = str_replace('﻿', '', $outputString);
		return $outputString;
	}	

	public function nudi2Unicode($text) {

		// ya group
		$text = str_replace('AiÀiï', 'ಯ್​', $text);
		$text = str_replace('AiÀÄ', 'ಯ', $text);
		$text = str_replace('AiÀiÁ', 'ಯಾ', $text);
		$text = str_replace('¬Ä', 'ಯಿ', $text);
		$text = str_replace('AiÉÄ', 'ಯೆ', $text); 
		$text = str_replace('AiÉÆ', 'ಯೊ', $text);
		$text = str_replace('AiÀiË', 'ಯೌ', $text);
		
		//ma group
		$text = str_replace('ªÀiï', 'ಮ್', $text);
		$text = str_replace('ªÀiË', 'ಮೌ', $text);
		$text = str_replace('ªÀÄ', 'ಮ', $text);
		$text = str_replace('ªÀiÁ', 'ಮಾ', $text);
		$text = str_replace('ªÉÄ', 'ಮೆ', $text);
		$text = str_replace('ªÉÆ', 'ಮೊ', $text);
		$text = str_replace('«Ä', 'ಮಿ', $text);
		
		// jjha group
		$text = str_replace('gÀhÄ', 'ಝ', $text);
		$text = str_replace('gÀhiÁ', 'ಝಾ', $text);
		$text = str_replace('gÉhÄ', 'ಝೆ', $text);
		$text = str_replace('gÉhÆ', 'ಝೊ', $text);
		$text = str_replace('jhÄ', 'ಝಿ', $text);

		//dha group
		$text = str_replace('zs', 'ಧ್', $text);
		$text = str_replace('¢ü', 'ಧಿ', $text);

		//Dha group
		$text = str_replace('qs', 'ಢ್', $text);
		$text = str_replace('rü', 'ಢಿ', $text);
		
		//pha group
		$text = str_replace('¥s', 'ಫ್', $text);
		$text = str_replace('¦ü', 'ಫಿ', $text);

		//ha group
		$text = str_replace('¨s', 'ಭ್', $text);
		$text = str_replace('©ü', 'ಭಿ', $text);

		// RRi group
		$text = str_replace('IÄ', 'ಋ', $text);
		$text = str_replace('IÆ', 'ೠ', $text);

		// Lookup ---------------------------------------------
		$text = str_replace('!', '!', $text);
		$text = str_replace('"', '"', $text);// tbh
		$text = str_replace('#', '#', $text);
		$text = str_replace('$', '$', $text);
		$text = str_replace('%', '%', $text);
		$text = str_replace('&', '&', $text);
		$text = str_replace("'", '’', $text);
		$text = str_replace('(', '(', $text);
		$text = str_replace(')', ')', $text);
		$text = str_replace('*', '*', $text);
		$text = str_replace('+', '+', $text);
		$text = str_replace(',', ',', $text);
		$text = str_replace('-', '-', $text);
		$text = str_replace('.', '.', $text);
		$text = str_replace('/', '/', $text);
		$text = str_replace('0', '೦', $text);
		$text = str_replace('1', '೧', $text);
		$text = str_replace('2', '೨', $text);
		$text = str_replace('3', '೩', $text);
		$text = str_replace('4', '೪', $text);
		$text = str_replace('5', '೫', $text);
		$text = str_replace('6', '೬', $text);
		$text = str_replace('7', '೭', $text);
		$text = str_replace('8', '೮', $text);
		$text = str_replace('9', '೯', $text);
		$text = str_replace(':', ':', $text);
		$text = str_replace(';', ';', $text);
		$text = str_replace('<', '<', $text);
		$text = str_replace('=', '=', $text);
		$text = str_replace('>', '>', $text);
		$text = str_replace('?', '?', $text);
		$text = str_replace('@', '@', $text);
		$text = str_replace('A', 'ಂ', $text);
		$text = str_replace('B', 'ಃ', $text);
		$text = str_replace('C', 'ಅ', $text);
		$text = str_replace('D', 'ಆ', $text);
		$text = str_replace('E', 'ಇ', $text);
		$text = str_replace('F', 'ಈ', $text);
		$text = str_replace('G', 'ಉ', $text);
		$text = str_replace('H', 'ಊ', $text);
		//~ $text = str_replace('I', '', $text); //handled above in RRi group
		$text = str_replace('J', 'ಎ', $text);
		$text = str_replace('K', 'ಏ', $text);
		$text = str_replace('L', 'ಐ', $text);
		$text = str_replace('M', 'ಒ', $text);
		$text = str_replace('N', 'ಓ', $text);
		$text = str_replace('O', 'ಔ', $text); 
		$text = str_replace('P', 'ಕ್', $text);
		$text = str_replace('Q', 'ಕಿ', $text);
		$text = str_replace('R', 'ಖ', $text);
		$text = str_replace('S', 'ಖ್', $text);
		$text = str_replace('T', 'ಖಿ', $text);
		$text = str_replace('U', 'ಗ್', $text);
		$text = str_replace('V', 'ಗಿ', $text);
		$text = str_replace('W', 'ಘ್', $text);
		$text = str_replace('X', 'ಘಿ', $text);
		$text = str_replace('Y', 'ಙ', $text);
		$text = str_replace('Z', 'ಚ್', $text);
		$text = str_replace('[', '[', $text);
		$text = str_replace("\\", '\\', $text);
		$text = str_replace(']', ']', $text);
		$text = str_replace('^', '^', $text);
		$text = str_replace('_', '_', $text);
		$text = str_replace('`', '‘', $text);
		$text = str_replace('a', 'ಚಿ', $text);
		$text = str_replace('b', 'ಛ್', $text);
		$text = str_replace('c', 'ಛಿ', $text);
		$text = str_replace('d', 'ಜ', $text);
		$text = str_replace('e', 'ಜ್', $text);
		$text = str_replace('f', 'ಜಿ', $text);
		$text = str_replace('g', 'ರ್', $text);
		//~ $text = str_replace('h', '', $text); //pre processing (ya Jha)
		//~ $text = str_replace('i', '', $text); //pre processing (ya Jha)
		$text = str_replace('j', 'ರಿ', $text);
		$text = str_replace('k', 'ಞ', $text); // pre processing
		$text = str_replace('l', 'ಟ', $text); 
		$text = str_replace('m', 'ಟ್', $text);
		$text = str_replace('n', 'ಟಿ', $text); 
		$text = str_replace('o', 'ಠ್', $text);
		$text = str_replace('p', 'ಠಿ', $text);
		$text = str_replace('q', 'ಡ್', $text);
		$text = str_replace('r', 'ಡಿ', $text);
		//~ $text = str_replace('s', '', $text); //pre processing
		$text = str_replace('t', 'ಣ', $text);
		$text = str_replace('u', 'ಣ್', $text);
		$text = str_replace('v', 'ತ್', $text);
		$text = str_replace('w', 'ತಿ', $text);
		$text = str_replace('x', 'ಥ್', $text);
		$text = str_replace('y', 'ಥಿ', $text);
		$text = str_replace('z', 'ದ್', $text);
		$text = str_replace('{', '{', $text);
		$text = str_replace('|', '|', $text); 
		$text = str_replace('}', '}', $text);
		$text = str_replace('~', '~', $text);
		// $text = str_replace(' ', '', $text); // tbh (no break space)
		$text = str_replace('¢', 'ದಿ', $text);
		$text = str_replace('£', 'ನ್', $text);
		$text = str_replace('¤', 'ನಿ', $text);
		$text = str_replace('¥', 'ಪ್', $text);
		$text = str_replace('¦', 'ಪಿ', $text);
		$text = str_replace('§', 'ಬ', $text);
		$text = str_replace('¨', 'ಬ್', $text);
		$text = str_replace('©', 'ಬಿ', $text);
		$text = str_replace('ª', 'ವ್', $text);
		$text = str_replace('«', 'ವಿ', $text);
		//~ $text = str_replace('¬', '', $text); //handled above in ya group (yi)
		$text = str_replace('®', 'ಲ', $text);
		$text = str_replace('¯', 'ಲ್', $text);
		$text = str_replace('°', 'ಲಿ', $text);
		$text = str_replace('±', 'ಶ್', $text);
		$text = str_replace('²', 'ಶಿ', $text);
		$text = str_replace('µ', 'ಷ್', $text);
		$text = str_replace('¶', 'ಷಿ', $text);
		$text = str_replace('¸', 'ಸ್', $text);
		$text = str_replace('¹', 'ಸಿ', $text);
		$text = str_replace('º', 'ಹ್', $text);
		$text = str_replace('»', 'ಹಿ', $text);
		$text = str_replace('¼', 'ಳ್', $text);
		$text = str_replace('½', 'ಳಿ', $text);
		$text = str_replace('¾', 'ಱ', $text);
		$text = str_replace('¿', 'ೞ', $text);
		$text = str_replace('À', 'ಅ', $text); // replacing a kara to swara 'a'
		$text = str_replace('Á', 'ಾ', $text);//kA
		$text = str_replace('Â', 'ಿ', $text);//ki
		$text = str_replace('Ä', 'ು', $text);//ku
		$text = str_replace('Å', 'ು', $text);//ku
		$text = str_replace('Æ', 'ೂ', $text);//kU
		$text = str_replace('Ç', 'ೂ', $text);//kU
		$text = str_replace('È', 'ೃ', $text);//kaq
		$text = str_replace('É', 'ೆ', $text);//ke
		$text = str_replace('Ê', 'ೈ', $text);//kai
		$text = str_replace('Ë', 'ೌ', $text);
		$text = str_replace('Ì', '್ಕ', $text);
		$text = str_replace('Í', '್ಖ', $text);
		$text = str_replace('Î', '್ಗ', $text);
		$text = str_replace('Ï', '್ಘ', $text);
		$text = str_replace('Ð', '್ಙ', $text);
		$text = str_replace('Ñ', '್ಚ', $text);
		$text = str_replace('Ò', '್ಛ', $text);
		$text = str_replace('Ó', '್ಜ', $text);
		$text = str_replace('Ô', '್ಝ', $text);
		$text = str_replace('Õ', '್ಞ', $text);
		$text = str_replace('Ö', '್ಟ', $text);
		$text = str_replace('×', '್ಠ', $text);
		$text = str_replace('Ø', '್ಡ', $text);
		$text = str_replace('Ù', '್ಢ', $text);
		$text = str_replace('Ú', '್ಣ', $text);
		$text = str_replace('Û', '್ತ', $text);
		$text = str_replace('Ü', '್ಥ', $text);
		$text = str_replace('Ý', '್ದ', $text);
		$text = str_replace('Þ', '್ಧ', $text);
		$text = str_replace('ß', '್ನ', $text);
		$text = str_replace('à', '್ಪ', $text);
		$text = str_replace('á', '್ಫ', $text);
		$text = str_replace('â', '್ಬ', $text);
		$text = str_replace('ã', '್ಭ', $text);
		$text = str_replace('ä', '್ಮ', $text);
		$text = str_replace('å', '್ಯ', $text);
		$text = str_replace('æ', '್ರ', $text);
		$text = str_replace('ç', '್ರ', $text);
		$text = str_replace('è', '್ಲ', $text);
		$text = str_replace('é', '್ವ', $text);
		$text = str_replace('ê', '್ಶ', $text);
		$text = str_replace('ë', '್ಷ', $text);
		$text = str_replace('ì', '್ಸ', $text);
		$text = str_replace('í', '್ಹ', $text);
		$text = str_replace('î', '್ಳ', $text);
		$text = str_replace('ï', '್​', $text);
		$text = str_replace('ð', 'ð', $text);//arka vottu
		$text = str_replace('ñ', 'ೄ', $text);
		$text = str_replace('ò', 'ನ್​', $text);
		$text = str_replace('ó', '಼', $text);
		$text = str_replace('ô', 'ô', $text);//tbh
		$text = str_replace('õ', 'õ', $text);//tbh
		$text = str_replace('ø', 'ೃ', $text);
		$text = str_replace('ù', '್ಱ', $text);
		$text = str_replace('ú', '್ೞ', $text);
		$text = str_replace('û', '಼', $text);
		//~ $text = str_replace('ü', '', $text);//tbh
		$text = str_replace('ý', 'ಽ', $text);
		//~ $text = str_replace('Œ', '', $text);//tbh
		//~ $text = str_replace('Š', '', $text);//tbh
		//~ $text = str_replace('¯', '', $text);//tbh
		$text = str_replace('‘', '‘', $text);
		$text = str_replace('’', '’', $text);
		$text = str_replace('“', '“', $text);
		$text = str_replace('”', '”', $text);
		$text = str_replace('„', 'ಽ', $text);
		$text = str_replace('†', '।', $text);
		$text = str_replace('‡', '॥', $text);
		//~ $text = str_replace('‰', '', $text);//tbh
		//~ $text = str_replace('‹', '', $text);//tbh

		// Special cases

		//remove ottu spacer
		$text = str_replace('ö', '', $text);//ottu spacer
		$text = str_replace('÷', '', $text);//ottu spacer


		// Swara
		$text = preg_replace('/್[ಅ]/u', '', $text);
		$text = preg_replace('/್([ಾಿೀುೂೃೄೆೇೈೊೋೌ್])/u', "$1", $text);

		$text = str_replace('ೊ', 'ೊ', $text);//ko
		$text = str_replace('ೆೈ', 'ೈ', $text);//kai

		$swara = "ಅ|ಆ|ಇ|ಈ|ಉ|ಊ|ಋ|ಎ|ಏ|ಐ|ಒ|ಓ|ಔ";
		$vyanjana = "ಕ|ಖ|ಗ|ಘ|ಙ|ಚ|ಛ|ಜ|ಝ|ಞ|ಟ|ಠ|ಡ|ಢ|ಣ|ತ|ಥ|ದ|ಧ|ನ|ಪ|ಫ|ಬ|ಭ|ಮ|ಯ|ರ|ಱ|ಲ|ವ|ಶ|ಷ|ಸ|ಹ|ಳ|ೞ";
		$swaraJoin = "ಾ|ಿ|ೀ|ು|ೂ|ೃ|ೄ|ೆ|ೇ|ೈ|ೊ|ೋ|ೌ|ಂ|ಃ|್";

		$syllable = "($vyanjana)($swaraJoin)|($vyanjana)($swaraJoin)|($vyanjana)|($swara)";

		$text = preg_replace("/($swaraJoin)್($vyanjana)/u", "್$2$1", $text);
		$text = preg_replace("/್​್($vyanjana)/u", "್$1್​", $text);


		$text = str_replace('ೊ', 'ೊ', $text);//ko
		$text = str_replace('ೆೈ', 'ೈ', $text);//kai

		$text = str_replace('ಿÃ', 'ೀ', $text);//kiV
		$text = str_replace('ೆÃ', 'ೇ', $text);//keV
		$text = str_replace('ೊÃ', 'ೋ', $text);//koV		
		
		$text = str_replace('್​ð', '್ð', $text);//halanta+zwj+R = halanta+R
		

		$text = preg_replace("/($swaraJoin)್($vyanjana)/u", "್$2$1", $text);
		
		// First pass of repha inversion
		$text = preg_replace("/($syllable)/u", "$1zzz", $text);
		$text = preg_replace("/್zzz/u", "್", $text);
		$text = preg_replace("/್ð/u", "್zzzð", $text);
		$text = preg_replace("/zzz([^z]*?)zzzð/u", "zzzರ್zzz" . "$1", $text);
		$text = str_replace("zzz", "", $text);

		$text = str_replace('ೊ', 'ೊ', $text);//ko
		$text = str_replace('ೆೈ', 'ೈ', $text);//kai

		$text = str_replace('ಿÃ', 'ೀ', $text);//kiV
		$text = str_replace('ೆÃ', 'ೇ', $text);//keV
		$text = str_replace('ೊÃ', 'ೋ', $text);//koV		

		// Second pass of repha inversion
		$text = preg_replace("/($syllable)/u", "$1zzz", $text);
		$text = preg_replace("/್zzz/u", "್", $text);
		$text = preg_replace("/್ð/u", "್zzzð", $text);
		$text = preg_replace("/zzz([^z]*?)zzzð/u", "zzzರ್zzz" . "$1", $text);
		$text = str_replace("zzz", "", $text);

		$text = str_replace('ೊ', 'ೊ', $text);//ko
		$text = str_replace('ೆೈ', 'ೈ', $text);//kai

		$text = str_replace('ಿÃ', 'ೀ', $text);//kiV
		$text = str_replace('ೆÃ', 'ೇ', $text);//keV
		$text = str_replace('ೊÃ', 'ೋ', $text);//koV	

		$text = str_replace('ಿ Ã', 'ೀ', $text);//kiV
		$text = str_replace('ೆ Ã', 'ೇ', $text);//keV
		$text = str_replace('ೊ Ã', 'ೋ', $text);//koV	

		// Final replacements
		$text = str_replace(' ್', '್', $text);
		$text = str_replace('||', '॥', $text);
		$text = str_replace('|', '।', $text);
		
		$text = str_replace('<', '&lt;', $text);
		$text = str_replace('>', '&gt;', $text);

		//~ $text = preg_replace('/’(.*?)’/', '‘$1’', $text);
		
		// echo $result . "\n"; 
		return $text;
	}

	public function sample() {

		$tmpTXTFile = "/home/sriranga/projects/Nagpur_Ashram_ebook/Test/test.txt";
		$tmpHTMLFile = "/home/sriranga/projects/Nagpur_Ashram_ebook/Test/test.html";

		exec("perl /home/sriranga/projects/Nagpur_Ashram_ebook/to_uni.pl " . $tmpTXTFile . " " . $tmpHTMLFile, $out, $err);

		if($err){
        echo $err;
        print_r( $out );
		}

	}

	public function APS2UnicodeNew($contents) {

		$middleJoiner = "&|=|B|d|g|Q|s|t|w|W|x|X|z|Z|¸|Ç|ê|Ë|ï|Ï|Ò|Ò|ü|b|@";
		$rightJoiner = "r";

		// Cleanup
		$contents = preg_replace("/($middleJoiner)\\1/u", "$1", $contents);
		$contents = preg_replace("/($rightJoiner)\\1/u", "$1", $contents);
		$contents = preg_replace("/($middleJoiner)Ç/u", "Ç$1", $contents);
		$contents = preg_replace("/B($middleJoiner)/u", "$1B", $contents);
		$contents = preg_replace("/b($middleJoiner)/u", "$1b", $contents);

		//~ Danda is inherent and need not be specifically present in the following cases
		$contents = str_replace('eA', 'A', $contents);
		$contents = str_replace('eE', 'E', $contents);
		$contents = str_replace('eR', 'R', $contents);
		$contents = str_replace('e\\', '\\', $contents);
		$contents = str_replace('ea', 'a', $contents);
		$contents = str_replace('ef', 'f', $contents);
		$contents = str_replace('eq', 'q', $contents);
		$contents = str_replace('er', 'r', $contents);
		$contents = str_replace('e|', '|', $contents);
		$contents = str_replace('eé', 'é', $contents);

		//~ ka, va, pa, pha, kta, kra, kna, kva, kka case

		$contents = str_replace('keä', 'क्', $contents);
		$contents = str_replace('Jeä', 'क्', $contents);
		$contents = str_replace('›eä', 'क्र्', $contents);
		$contents = str_replace('$eä', 'क्र्', $contents);
		$contents = str_replace('Äeä', 'क्न्', $contents);
		$contents = str_replace('×eä', 'क्व्', $contents);
		$contents = str_replace('òeä', 'क्त्', $contents);
		$contents = str_replace('Heä', 'फ्', $contents);
		$contents = str_replace('heä', 'फ्', $contents);
		$contents = str_replace('øeä', 'फ्र्', $contents);

		$contents = preg_replace("/(k|›|\\$|Ä|×|ò|J|H|h|ø)e(($middleJoiner)($middleJoiner)|($middleJoiner))â/", "$1eâ$2", $contents);

		$contents = str_replace('keâ', 'क', $contents);
		$contents = str_replace('Jeâ', 'क', $contents);
		$contents = str_replace('›eâ', 'क्र', $contents);
		$contents = str_replace('$eâ', 'क्र', $contents);
		$contents = str_replace('Äeâ', 'क्न', $contents);
		$contents = str_replace('×eâ', 'क्व', $contents);
		$contents = str_replace('òeâ', 'क्त', $contents);
		$contents = str_replace('Heâ', 'फ', $contents);
		$contents = str_replace('heâ', 'फ', $contents);
		$contents = str_replace('øeâ', 'फ्र', $contents);

		$contents = str_replace('×e¹', 'क्क', $contents);
		$contents = str_replace('F&', 'ई', $contents);
		$contents = str_replace('FË', 'ईं', $contents);
		$contents = str_replace('Gâ', 'T', $contents);

		 
		//~ ऋ case
		$contents = str_replace('$e+', 'ऋ', $contents);
		$contents = str_replace('$e&+', 'र्ऋ', $contents);
		$contents = str_replace('$eb+', 'ऋं', $contents);


		//Lookup
		$contents = str_replace('!', '!', $contents);
		$contents = str_replace('"', 'ठ', $contents);
		$contents = str_replace('#', 'क्ष्', $contents);
		$contents = str_replace('$', 'त्र्', $contents);
		$contents = str_replace('%', 'ज्ञ्', $contents);
		$contents = str_replace('&', '&', $contents);
		$contents = str_replace("'", "'", $contents);
		$contents = str_replace('(', '(', $contents);
		$contents = str_replace(')', ')', $contents);
		$contents = str_replace('*', 'ङ', $contents);
		//~ $contents = str_replace('+', '', $contents);
		$contents = str_replace(',', ',', $contents);
		//~ $contents = str_replace('-', '-', $contents);
		$contents = str_replace('.', '.', $contents);
		$contents = str_replace('/', '/', $contents);
		$contents = str_replace('0', '०', $contents);
		$contents = str_replace('1', '१', $contents);
		$contents = str_replace('2', '२', $contents);
		$contents = str_replace('3', '३', $contents);
		$contents = str_replace('4', '४', $contents);
		$contents = str_replace('5', '५', $contents);
		$contents = str_replace('6', '६', $contents);
		$contents = str_replace('7', '७', $contents);
		$contents = str_replace('8', '८', $contents);
		$contents = str_replace('9', '९', $contents);
		$contents = str_replace(':', 'ः', $contents);
		$contents = str_replace(';', ';', $contents);
		$contents = str_replace('<', 'ष्', $contents);
		$contents = str_replace('=', 'ृ', $contents);
		$contents = str_replace('>', '्न', $contents);
		$contents = str_replace('?', '?', $contents);
		$contents = str_replace('@', 'ॅ', $contents);
		$contents = str_replace('A', '&ीं', $contents);
		$contents = str_replace('B', 'ँ', $contents);
		$contents = str_replace('C', 'ण्', $contents);
		$contents = str_replace('D', 'अ्', $contents);
		//~ $contents = str_replace('E', 'E', $contents);//fं
		$contents = str_replace('F', 'इ', $contents);
		$contents = str_replace('G', 'उ', $contents);
		$contents = str_replace('H', 'प्', $contents); // Defaulted to pa
		$contents = str_replace('I', 'घ्', $contents);
		$contents = str_replace('J', 'व्', $contents); // Defaulted to va
		$contents = str_replace('K', 'ख्', $contents);
		$contents = str_replace('L', 'थ्', $contents);
		$contents = str_replace('M', 'श्', $contents);
		$contents = str_replace('N', 'ऱ्', $contents);
		$contents = str_replace('O', 'ध्', $contents);
		$contents = str_replace('P', 'झ्', $contents);
		$contents = str_replace('Q', 'ैं', $contents);
		$contents = str_replace('R', 'ीं', $contents);
		$contents = str_replace('S', 'ए', $contents);
		$contents = str_replace('T', 'ऊ', $contents);
		$contents = str_replace('U', 'ळ', $contents);
		$contents = str_replace('V', 'न्न्', $contents);
		$contents = str_replace('W', 'ें', $contents);
		$contents = str_replace('X', '&ें', $contents);
		$contents = str_replace('Y', 'भ्', $contents);
		$contents = str_replace('Z', '&ैः', $contents);
		$contents = str_replace('[', 'ड', $contents);
		//~ $contents = str_replace("\\", '\\', $contents);//र्fं
		$contents = str_replace(']', ']', $contents); //Handled in post processing
		$contents = str_replace('^', '्र', $contents);
		$contents = str_replace('_', 'ञ्', $contents);
		//~ $contents = str_replace('`', '`', $contents);
		$contents = str_replace('a', '&ी', $contents);
		$contents = str_replace('b', 'ं', $contents);
		$contents = str_replace('c', 'म्', $contents);
		$contents = str_replace('d', '्', $contents);
		$contents = str_replace('e', 'e', $contents); //???? Handled in post processing
		//~ $contents = str_replace('f', 'f', $contents); //f Handled in post processing
		$contents = str_replace('g', 'ु', $contents);
		$contents = str_replace('h', 'प्', $contents); // Defaulted to pa; pha handled above
		$contents = str_replace('i', 'ग्', $contents);
		$contents = str_replace('j', 'र', $contents);
		$contents = str_replace('k', 'व्', $contents);  //ka handled in pre processing;  // Defaulted to va
		$contents = str_replace('l', 'त्', $contents);
		$contents = str_replace('m', 'स्', $contents);
		$contents = str_replace('n', 'ह', $contents);
		$contents = str_replace('o', 'द', $contents);
		$contents = str_replace('p', 'ज्', $contents);
		$contents = str_replace('q', 'f', $contents);// consider this as f (ikara)
		$contents = str_replace('r', 'ी', $contents);
		$contents = str_replace('s', 'े', $contents);
		$contents = str_replace('t', 'ू', $contents);
		$contents = str_replace('u', 'ल्', $contents);
		$contents = str_replace('v', 'न्', $contents);
		$contents = str_replace('w', 'ै', $contents);
		$contents = str_replace('x', '&े', $contents);
		$contents = str_replace('y', 'ब्', $contents);
		$contents = str_replace('z', '&ै', $contents);
		$contents = str_replace('{', 'ढ', $contents);
		//~ $contents = str_replace('|', '|' , $contents);//fर्
		$contents = str_replace('}', 'ल', $contents);
		$contents = str_replace('~', '।', $contents);
		$contents = str_replace('¡', 'ख्र्', $contents);
		$contents = str_replace('¢', 'ह्य्', $contents);
		$contents = str_replace('£', 'ह्व', $contents);
		$contents = str_replace('¤', 'रू', $contents);
		$contents = str_replace('¥', 'ह्ल', $contents);
		$contents = str_replace('¦', '¦', $contents); //Left as it is as no use case found
		$contents = str_replace('§', 'श्च्', $contents);
		$contents = str_replace('©', 'द्म्', $contents);
		$contents = str_replace('ª', 'ट्ठ', $contents);
		$contents = str_replace('«', 'ग्र्', $contents);
		$contents = str_replace('®', 'रु', $contents);
		$contents = str_replace('°', 'ष्ट', $contents);
		$contents = str_replace('¶', '¶', $contents); //Left as it is as no use case found
		$contents = str_replace('¸', 'ॄ', $contents);
		$contents = str_replace('¹', '¹', $contents); //Handled in post processing kka case
		$contents = str_replace('º', 'ड्ढ', $contents);
		$contents = str_replace('»', 'ज्र्', $contents);
		$contents = str_replace('¼', 'ल्ल', $contents);
		$contents = str_replace('½', 'छ्व', $contents);
		$contents = str_replace('¿', 'ङ्क', $contents);
		$contents = str_replace('À', 'ञ्ज्', $contents);
		$contents = str_replace('Á', 'ङ्ग', $contents);
		$contents = str_replace('Â', 'दृ', $contents);
		$contents = str_replace('Ã', 'ञ्च्', $contents);
		$contents = str_replace('Ä', 'व्न्', $contents); //Handled in pre processing
		$contents = str_replace('Å', 'द्य्', $contents);
		$contents = str_replace('Æ', 'द्भ', $contents);
		$contents = str_replace('Ç', '्र', $contents);
		$contents = str_replace('È', 'ङ्ख', $contents);
		$contents = str_replace('É', 'द्व', $contents);
		$contents = str_replace('Ê', '॰', $contents); //May be a notation for shortform; left as it is
		$contents = str_replace('Ë', '&ं', $contents);
		$contents = str_replace('Ì', ']', $contents); //Dot below case handled at the bottom
		$contents = str_replace('Í', '्रू', $contents);
		$contents = str_replace('Î', '्रु', $contents);
		$contents = str_replace('Ï', 'ु', $contents);
		$contents = str_replace('Ñ', 'ढ्ढ', $contents);
		$contents = str_replace('Ò', 'ू', $contents);
		$contents = str_replace('Ó', 'ऽ', $contents);
		$contents = str_replace('Ô', 'ॐ', $contents);
		$contents = str_replace('Õ', 'श्व्', $contents);
		$contents = str_replace('Ö', 'ह्न', $contents);
		$contents = str_replace('×', '×', $contents); //Handled in post processing kka case
		$contents = str_replace('Ø', 'प्र्', $contents);
		$contents = str_replace('Ù', 'य्', $contents);
		$contents = str_replace('Ú', 'छ', $contents);
		$contents = str_replace('Û', 'च्', $contents);
		$contents = str_replace('Ü', 'ह्र', $contents);
		$contents = str_replace('Ý', 'ङ्क्त', $contents);
		$contents = str_replace('Þ', 'द्द्र', $contents);
		$contents = str_replace('ß', 'श्र्', $contents);
		$contents = str_replace('à', 'ळ्', $contents);
		$contents = str_replace('á', 'e', $contents); // Considered as danda
		$contents = str_replace('â', 'â', $contents); // क and फ case 
		$contents = str_replace('ã', 'झ्र्', $contents); //???? verify
		$contents = str_replace('ä', 'ä', $contents); // right side glyph of ka in case of conjuncts
		$contents = str_replace('å', 'ह्', $contents);
		$contents = str_replace('æ', 'द्ध', $contents);
		$contents = str_replace('ç', 'श्', $contents);
		$contents = str_replace('è', 'लृ', $contents);
		//~ $contents = str_replace('é', 'é', $contents); //fं
		$contents = str_replace('ê', '्ल', $contents);
		$contents = str_replace('ë', 'श्', $contents);
		$contents = str_replace('ì', 'e', $contents); // Considered as danda
		$contents = str_replace('í', 'e', $contents); // Considered as danda
		$contents = str_replace('î', 'î', $contents);
		$contents = str_replace('ï', 'ï', $contents); //mostly LR, left as it is
		$contents = str_replace('ð', 'ष्ट्व', $contents);
		$contents = str_replace('ñ', 'ड्ड', $contents);
		$contents = str_replace('ò', 'त्त्', $contents);
		$contents = str_replace('ó', 'ट्ट', $contents);
		$contents = str_replace('ô', 'द्ब', $contents);
		$contents = str_replace('õ', 'द्र', $contents);
		$contents = str_replace('ö', 'द्द', $contents);
		$contents = str_replace('÷', 'ङ्क्ष', $contents);
		$contents = str_replace('ø', 'प्र्', $contents);
		$contents = str_replace('ù', 'हृ', $contents);
		$contents = str_replace('ú', 'ठ्ठ', $contents);
		$contents = str_replace('û', 'द्ग', $contents);
		$contents = str_replace('ü', '्र', $contents);
		$contents = str_replace('ý', 'ॠ', $contents);
		$contents = str_replace('þ', 'ह्ण', $contents);
		$contents = str_replace('ÿ', 'ह्म्', $contents);
		$contents = str_replace('Œ', 'स्त्र्', $contents);
		$contents = str_replace('œ', 'स्र्', $contents);
		$contents = str_replace('Š', 'ः', $contents);
		$contents = str_replace('š', 'ट', $contents);
		$contents = str_replace('Ÿ', '्य्', $contents);
		$contents = str_replace('ƒ', 'e', $contents); // Considered as danda
		//~ $contents = str_replace('–', '–', $contents);
		//~ $contents = str_replace('—', '—', $contents);
		$contents = str_replace('‘', '‘', $contents);
		$contents = str_replace('’', '’', $contents);
		$contents = str_replace('“', 'ङ्ख', $contents);
		$contents = str_replace('”', 'ङ्ग', $contents);
		$contents = str_replace('„', 'ष्ट', $contents);
		$contents = str_replace('†', 'e', $contents); // Considered as danda
		$contents = str_replace('‡', 'e', $contents); // Considered as danda
		$contents = str_replace('…', 'ष्ठ', $contents);
		$contents = str_replace('‰', 'ष्ठ', $contents);
		$contents = str_replace('‹', 'ङ्घ', $contents);
		$contents = str_replace('›', 'व्र्', $contents); //ka and va case
		$contents = str_replace('™', 'रू', $contents);


		$contents = str_replace('्ee', 'ा', $contents);
		$contents = str_replace('्e', '', $contents);
		$contents = str_replace('e', 'ा', $contents);
		$contents = str_replace('ाै', 'ौ', $contents);
		$contents = str_replace('ाे', 'ो', $contents);
		$contents = str_replace('्ंा' , 'ं', $contents);
		$contents = str_replace('अा', 'आ', $contents);
		$contents = str_replace('अो', 'ओ', $contents);
		$contents = str_replace('अौ', 'औ', $contents);
		$contents = str_replace('आॅ', 'ऑ', $contents);
		$contents = str_replace('अॅ', 'ॲ', $contents);
		$contents = str_replace('एे', 'ऐ', $contents);
		$contents = str_replace('एॅ', 'ऍ', $contents);
		$contents = str_replace('ाॅ', 'ॉ', $contents);

		//~ Post processing
		$contents = str_replace(']न', 'ऩ', $contents);
		$contents = str_replace(']र', 'ऱ', $contents);
		$contents = str_replace(']ळ', 'ऴ', $contents);
		$contents = str_replace(']क', 'क़', $contents);
		$contents = str_replace(']ख', 'ख़', $contents);
		$contents = str_replace(']ग', 'ग़', $contents);
		$contents = str_replace(']ज', 'ज़', $contents);
		$contents = str_replace(']ड', 'ड़', $contents);
		$contents = str_replace(']ढ', 'ढ़', $contents);
		$contents = str_replace(']फ', 'फ़', $contents);
		$contents = str_replace(']य', 'य़', $contents);

		$contents = str_replace('±', '+', $contents);
		$contents = str_replace('²', '×', $contents);
		$contents = str_replace('³', '%', $contents);
		$contents = str_replace('´', '÷', $contents);
		$contents = str_replace('μ', '*', $contents);
		$contents = str_replace('•', '=', $contents);
		$contents = str_replace(']', '.', $contents);
		$contents = str_replace('`', '‘', $contents);
		$contents = str_replace('‘‘', '“', $contents);
		$contents = str_replace('\'', '’', $contents);
		$contents = str_replace('’’', '”', $contents);
		$contents = str_replace('।।', '॥', $contents);

		$contents = str_replace(' ञ् ', ' — ', $contents);

		$contents = $this->invertIkara($contents);
		$contents = $this->invertRepha($contents);

		$contents = str_replace('ेा', 'ाे', $contents);
		$contents = str_replace('ाै', 'ौ', $contents);
		$contents = str_replace('ाे', 'ो', $contents);
		$contents = str_replace('्ंा' , 'ं', $contents);
		$contents = str_replace('्ी' , 'ी', $contents);
		$contents = str_replace(' ः' , ' :', $contents);

		return $contents;
	}

	public function invertRepha($text) {

		$vyanjana = "क|ख|ग|घ|ङ|च|छ|ज|झ|ञ|ट|ठ|ड|ढ|ण|त|थ|द|ध|न|ऩ|प|फ|ब|भ|म|य|र|ऱ|ल|ळ|ऴ|व|श|ष|स|ह|क़|ख़|ग़|ज़|ड़|ढ़|फ़|य़";
		$swara = "अ|आ|इ|ई|उ|ऊ|ऋ|ऌ|ऍ|ऎ|ए|ऐ|ऑ|ऒ|ओ|औ";
		$swaraJoin = "ा|ि|ी|ु|ू|ृ|ॄ|ॅ|ॆ|े|ै|ॉ|ॊ|ो|ौ|्|ं|ः|ऽ" ; 

		$syllable = "($vyanjana)($swaraJoin)($swaraJoin)($swaraJoin)|($vyanjana)($swaraJoin)($swaraJoin)|($vyanjana)($swaraJoin)|($vyanjana)|($swara)";
		
		$text = 'zzz' . preg_replace("/($syllable|\s|[[:punct:]])/u", "$1zzz", $text);
		$text = preg_replace("/्zzz/u", "्", $text);
		
		$text = str_replace("zzz&", "&zzz", $text);
		$text = preg_replace("/z([^z]*?)&z/u", "z&$1z", $text);
		$text = str_replace("&", "र्", $text);
		$text = str_replace("zzz", "", $text);

		return($text);
	}

	public function invertIkara($text) {

		$vyanjana = "क|ख|ग|घ|ङ|च|छ|ज|झ|ञ|ट|ठ|ड|ढ|ण|त|थ|द|ध|न|ऩ|प|फ|ब|भ|म|य|र|ऱ|ल|ळ|ऴ|व|श|ष|स|ह|क़|ख़|ग़|ज़|ड़|ढ़|फ़|य़";
		$swara = "अ|आ|इ|ई|उ|ऊ|ऋ|ऌ|ऍ|ऎ|ए|ऐ|ऑ|ऒ|ओ|औ";
		$swaraJoin = "ा|ि|ी|ु|ू|ृ|ॄ|ॅ|ॆ|े|ै|ॉ|ॊ|ो|ौ" ; 

		$compoundConjunct = "($vyanjana)्($vyanjana)्($vyanjana)्($vyanjana)्($vyanjana)|($vyanjana)्($vyanjana)्($vyanjana)्($vyanjana)|($vyanjana)्($vyanjana)्($vyanjana)|($vyanjana)्($vyanjana)";
		$swaraConjunct = "($compoundConjunct)($swaraJoin)|($vyanjana)($swaraJoin)|($compoundConjunct)";

		$syllable = "($swaraConjunct)|($vyanjana)|($swara)";
		
		$text = preg_replace("/($syllable)/u", "$1zzz", $text);
		$text = preg_replace("/्zzz/u", "्", $text);

		$text = preg_replace("/([E\\\\f|é])([^z]*?)zzz/u", "$2" . "$1zzz", $text);

		$text = str_replace("zzz", "", $text);
		
		$text = str_replace('E', 'िं', $text);
		$text = str_replace('\\', '&िं', $text);
		$text = str_replace('f', 'ि', $text);
		$text = str_replace('|', 'ि&', $text);
		$text = str_replace('é', 'िं', $text);
		
		return($text);
	}

	public function shreelipi2Unicode($contents) {

		$middleJoiner = "C|…|N";
		// $rightJoiner = "r";

		// // Cleanup
		// $contents = preg_replace("/($middleJoiner)\\1/u", "$1", $contents);
		// $contents = preg_replace("/($rightJoiner)\\1/u", "$1", $contents);
		// $contents = preg_replace("/($middleJoiner)Ç/u", "Ç$1", $contents);
		// $contents = preg_replace("/B($middleJoiner)/u", "$1B", $contents);
		// $contents = preg_replace("/b($middleJoiner)/u", "$1b", $contents);

		// //~ Danda is inherent and need not be specifically present in the following cases
		// $contents = str_replace('eA', 'A', $contents);
		// $contents = str_replace('eE', 'E', $contents);
		// $contents = str_replace('eR', 'R', $contents);
		// $contents = str_replace('e\\', '\\', $contents);
		// $contents = str_replace('ea', 'a', $contents);
		// $contents = str_replace('ef', 'f', $contents);
		// $contents = str_replace('eq', 'q', $contents);
		// $contents = str_replace('er', 'r', $contents);
		// $contents = str_replace('e|', '|', $contents);
		// $contents = str_replace('eé', 'é', $contents);

		//~ ka, va, pa, pha, kta, kra, kna, kva, kka case

		$contents = str_replace('fl', 'ﬂ', $contents);
		$contents = str_replace('˘CT', 'ड़े', $contents);

		$contents = str_replace(' ́', 'ह', $contents);
		$contents = str_replace(' ∂', 'ई', $contents);
		$contents = str_replace(' ', ' इ', $contents);
		$contents = str_replace('>>', '>', $contents);
		$contents = str_replace('*T', 'T*', $contents);
		$contents = str_replace('CT', 'TC', $contents);
		$contents = str_replace('…T', 'T…', $contents);
		$contents = str_replace('ØT', 'TØ', $contents);
		$contents = str_replace('NT', 'TN', $contents);
		$contents = str_replace('MT', 'TM', $contents);
		$contents = str_replace('ºT', 'Tº', $contents);
		$contents = str_replace('∂T', 'T∂', $contents);
		$contents = str_replace('}T', 'T}', $contents);
		$contents = str_replace('NT', 'TN', $contents);
		$contents = str_replace('N>', '>N', $contents);
		$contents = str_replace('Ø>', '>Ø', $contents);
		
		$contents = str_replace('•T', 'क', $contents);
		$contents = str_replace('πT', 'क्व', $contents);
		$contents = str_replace('VT', 'फ', $contents);
		$contents = str_replace('IT', 'क्त', $contents);
		$contents = str_replace('•–T', 'क्र', $contents);
		$contents = str_replace('G•–', 'क्रि', $contents);
		$contents = str_replace('‹T', 'ऋ', $contents);
		$contents = str_replace('q>', 'ठ', $contents);
		$contents = str_replace('n>', 'छ', $contents);
		$contents = str_replace('^>', 'ट', $contents);
		$contents = str_replace('u>', 'ट्ट', $contents);

//		$contents = str_replace('¨>', 'ट्र', $contents);
	//	$contents = str_replace('>¨', 'ट्र', $contents);

		$contents = str_replace('‘>', 'ड', $contents);
		$contents = str_replace('˙>', 'ढ', $contents);
		$contents = str_replace('iT', 'रू', $contents);
		// $contents = str_replace('JeT', 'क्', $contents);
		// $contents = str_replace('›eT', 'क्र्', $contents);
		// $contents = str_replace('$eT', 'क्र्', $contents);
		// $contents = str_replace('ÄeT', 'क्न्', $contents);
		// $contents = str_replace('×eT', 'क्व्', $contents);
		// $contents = str_replace('òeT', 'क्त्', $contents);
		// $contents = str_replace('HeT', 'फ्', $contents);
		// $contents = str_replace('heT', 'फ्', $contents);
		// $contents = str_replace('øeT', 'फ्र्', $contents);

		//~ $contents = preg_replace("/(k|›|\\$|Ä|×|ò|J|H|h|ø)e(($middleJoiner)($middleJoiner)|($middleJoiner))â/", "$1eâ$2", $contents);

		// $contents = str_replace('keâ', 'क', $contents);
		// $contents = str_replace('Jeâ', 'क', $contents);
		// $contents = str_replace('›eâ', 'क्र', $contents);
		// $contents = str_replace('$eâ', 'क्र', $contents);
		// $contents = str_replace('Äeâ', 'क्न', $contents);
		// $contents = str_replace('×eâ', 'क्व', $contents);
		// $contents = str_replace('òeâ', 'क्त', $contents);
		// $contents = str_replace('Heâ', 'फ', $contents);
		// $contents = str_replace('heâ', 'फ', $contents);
		// $contents = str_replace('øeâ', 'फ्र', $contents);

		// $contents = str_replace('×e¹', 'क्क', $contents);
		// $contents = str_replace('F&', 'ई', $contents);
		// $contents = str_replace('FË', 'ईं', $contents);
		// $contents = str_replace('Gâ', 'T', $contents);

		 
		// //~ ऋ case
		// $contents = str_replace('$e+', 'ऋ', $contents);
		// $contents = str_replace('$e&+', 'र्ऋ', $contents);
		// $contents = str_replace('$eb+', 'ऋं', $contents);


		//Lookup
		// $contents = str_replace('!', '!', $contents);
		$contents = str_replace('"', '‘', $contents);
		$contents = str_replace('#', 'ः', $contents);
		$contents = str_replace('$', '।', $contents);
		// $contents = str_replace('%', 'ज्ञ्', $contents);
		// $contents = str_replace('&', '&', $contents);
		$contents = str_replace("'", "’", $contents);
		// $contents = str_replace('(', '(', $contents);
		// $contents = str_replace(')', ')', $contents);
		$contents = str_replace('*', 'ं', $contents);
		// //~ $contents = str_replace('+', '', $contents);
		// $contents = str_replace(',', ',', $contents);
		// //~ $contents = str_replace('-', '-', $contents);
		// $contents = str_replace('.', '.', $contents);
		// $contents = str_replace('/', '/', $contents);
		$contents = str_replace('0', '०', $contents);
		$contents = str_replace('1', '१', $contents);
		$contents = str_replace('2', '२', $contents);
		$contents = str_replace('3', '३', $contents);
		$contents = str_replace('4', '४', $contents);
		$contents = str_replace('5', '५', $contents);
		$contents = str_replace('6', '६', $contents);
		$contents = str_replace('7', '७', $contents);
		$contents = str_replace('8', '८', $contents);
		$contents = str_replace('9', '९', $contents);
		// $contents = str_replace(':', 'ः', $contents);
		// $contents = str_replace(';', ';', $contents);
		$contents = str_replace('<', '<', $contents);
		// $contents = str_replace('=', 'ृ', $contents);
		$contents = str_replace('>', 'ठ', $contents);
		// $contents = str_replace('?', '?', $contents);
		// $contents = str_replace('@', 'ॅ', $contents);
		$contents = str_replace('A', 'श्', $contents);
		$contents = str_replace('B', 'भ', $contents);
		$contents = str_replace('C', 'े', $contents);
		$contents = str_replace('D', 'न', $contents);
		$contents = str_replace('E', 'न्', $contents);//fं
		// $contents = str_replace('F', 'इ', $contents);
		$contents = str_replace('G', '<', $contents);
		$contents = str_replace('H', 'ँ', $contents);
		// $contents = str_replace('I', 'घ्', $contents);
		$contents = str_replace('J', 'ल', $contents);
		// $contents = str_replace('K', 'ख्', $contents);
		$contents = str_replace('L', 'ण', $contents);
		$contents = str_replace('M', 'ॅ', $contents);
		$contents = str_replace('N', 'ु', $contents);
		// $contents = str_replace('O', 'ध्', $contents);
		$contents = str_replace('P', 'स', $contents);
		// $contents = str_replace('Q', 'ैं', $contents);
		$contents = str_replace('R', 'ू', $contents);
		$contents = str_replace('S', 'ग्न', $contents);
		$contents = str_replace('T', 'क', $contents);
		$contents = str_replace('U', 'ल', $contents);
		// $contents = str_replace('V', 'न्न्', $contents);
		// $contents = str_replace('W', 'ें', $contents);
		$contents = str_replace('X', 'द्ध', $contents);
		$contents = str_replace('Y', '∂', $contents);
		// $contents = str_replace('Z', '&ैः', $contents);
		$contents = str_replace('[', 'क्ष', $contents);
		// //~ $contents = str_replace("\\", '\\', $contents);//र्fं
		// $contents = str_replace(']', ']', $contents); //Handled in post processing
		// $contents = str_replace('^', 'ट', $contents);
		$contents = str_replace('_', 'त्र', $contents);
		// //~ $contents = str_replace('`', '`', $contents);
		$contents = str_replace('a', 'ी', $contents);
		$contents = str_replace('b', 'ा', $contents);
		$contents = str_replace('c', 'त्त', $contents);
		// $contents = str_replace('d', '्', $contents);
		$contents = str_replace('e', 'ल्', $contents);
		// //~ $contents = str_replace('f', 'f', $contents); //f Handled in post processing
		$contents = str_replace('g', 'श्र', $contents);
		$contents = str_replace('h', 'स्', $contents);
		// $contents = str_replace('i', 'ग्', $contents);
		// $contents = str_replace('j', 'र', $contents);
		$contents = str_replace('k', 'ब', $contents);
		// $contents = str_replace('l', 'त्', $contents);
		$contents = str_replace('m', 'ब्', $contents);
		// $contents = str_replace('n', 'ह', $contents);
		$contents = str_replace('o', 'ै', $contents);
		// $contents = str_replace('p', 'ज्', $contents);
		// $contents = str_replace('q', 'f', $contents);// consider this as f (ikara)
		// $contents = str_replace('r', 'ी', $contents);
		$contents = str_replace('s', 'क्', $contents);
		// $contents = str_replace('t', 'ू', $contents);
		// $contents = str_replace('u', 'ल्', $contents);
		$contents = str_replace('v', 'ख्', $contents);
		// $contents = str_replace('w', 'ै', $contents);
		// $contents = str_replace('x', '&े', $contents);
		$contents = str_replace('y', 'ए', $contents);
		// $contents = str_replace('z', '&ै', $contents);
		$contents = str_replace('{', 'त्त्', $contents);
		$contents = str_replace('|', 'म्' , $contents);//fर्
		$contents = str_replace('}', 'ं', $contents);
		$contents = str_replace('~', 'श', $contents);
		// $contents = str_replace('¡', 'ख्र्', $contents);
		$contents = str_replace('¢', 'ज्', $contents);
		$contents = str_replace('£', 'द', $contents);
		$contents = str_replace('¤', 'न्न', $contents);
		$contents = str_replace('¥', 'हृ', $contents);
		// $contents = str_replace('¦', '¦', $contents); //Left as it is as no use case found
		// $contents = str_replace('§', 'श्च्', $contents);
		// $contents = str_replace('©', 'द्म्', $contents);
		$contents = str_replace('ª', 'थ्', $contents);
		$contents = str_replace('«', 'ध्', $contents);
		$contents = str_replace('®', 'भ्', $contents);
		// $contents = str_replace('°', 'ष्ट', $contents);
		$contents = str_replace('¶', 'उ', $contents);
		// $contents = str_replace('¸', 'ॄ', $contents);
		// $contents = str_replace('¹', '¹', $contents); //Handled in post processing kka case
		$contents = str_replace('º', 'ै', $contents);
		$contents = str_replace('»', 'त', $contents);
		// $contents = str_replace('¼', 'ल्ल', $contents);
		// $contents = str_replace('½', 'छ्व', $contents);
		$contents = str_replace('¿', 'अ', $contents);
		$contents = str_replace('À', 'द्य', $contents);
		// $contents = str_replace('Á', 'ङ्ग', $contents);
		// $contents = str_replace('Â', 'दृ', $contents);
		$contents = str_replace('Ã', 'ह्म', $contents);
		// $contents = str_replace('Ä', 'व्न्', $contents); //Handled in pre processing
		// $contents = str_replace('Å', 'द्य्', $contents);
		$contents = str_replace('Æ', 'ज', $contents);
		$contents = str_replace('Ç', 'च्च', $contents);
		$contents = str_replace('È', 'ु', $contents);
		$contents = str_replace('É', 'ज्ज', $contents);
		// $contents = str_replace('Ê', '॰', $contents); //May be a notation for shortform; left as it is
		$contents = str_replace('Ë', '्', $contents);
		$contents = str_replace('Ì', 'प्', $contents);
		// $contents = str_replace('Í', '्रू', $contents);
		$contents = str_replace('Î', 'व', $contents);
		$contents = str_replace('Ï', 'घ्', $contents);
		$contents = str_replace('Ñ', 'ल्ल', $contents);
		$contents = str_replace('Ò', 'थ', $contents);
		$contents = str_replace('Ó', 'म', $contents);
		$contents = str_replace('Ô', 'क्ष्', $contents);
		$contents = str_replace('Õ', 'स्त्र', $contents);
		$contents = str_replace('Ö', 'ह्न', $contents);
		// $contents = str_replace('×', '×', $contents); //Handled in post processing kka case
		$contents = str_replace('Ø', 'ू', $contents);
		$contents = str_replace('Ù', 'द्व', $contents);
		$contents = str_replace('Ú', 'ी', $contents);
		// $contents = str_replace('Û', 'च्', $contents);
		// $contents = str_replace('Ü', 'ह्र', $contents);
		// $contents = str_replace('Ý', 'ङ्क्त', $contents);
		// $contents = str_replace('Þ', 'द्द्र', $contents);
		$contents = str_replace('ß', 'ज्ञ', $contents);
		$contents = str_replace('à', 'ह्व', $contents);
		// $contents = str_replace('á', 'e', $contents); // Considered as danda
		// $contents = str_replace('â', 'â', $contents); // क and फ case 
		// $contents = str_replace('ã', 'झ्र्', $contents); //???? verify
		// $contents = str_replace('ä', 'ä', $contents); // right side glyph of ka in case of conjuncts
		// $contents = str_replace('å', 'ह्', $contents);
		$contents = str_replace('æ', 'द्र', $contents);
		// $contents = str_replace('ç', 'श्', $contents);
		// $contents = str_replace('è', 'लृ', $contents);
		// //~ $contents = str_replace('é', 'é', $contents); //fं
		// $contents = str_replace('ê', '्ल', $contents);
		// $contents = str_replace('ë', 'श्', $contents);
		// $contents = str_replace('ì', 'e', $contents); // Considered as danda
		// $contents = str_replace('í', 'e', $contents); // Considered as danda
		// $contents = str_replace('î', 'î', $contents);
		// $contents = str_replace('ï', 'ï', $contents); //mostly LR, left as it is
		// $contents = str_replace('ð', 'ष्ट्व', $contents);
		// $contents = str_replace('ñ', 'ड्ड', $contents);
		// $contents = str_replace('ò', 'त्त्', $contents);
		// $contents = str_replace('ó', 'ट्ट', $contents);
		// $contents = str_replace('ô', 'द्ब', $contents);
		// $contents = str_replace('õ', 'द्र', $contents);
		// $contents = str_replace('ö', 'द्द', $contents);
		$contents = str_replace('÷', 'श्च', $contents);
		// $contents = str_replace('ø', 'प्र्', $contents);
		// $contents = str_replace('ù', 'हृ', $contents);
		// $contents = str_replace('ú', 'ठ्ठ', $contents);
		// $contents = str_replace('û', 'द्ग', $contents);
		// $contents = str_replace('ü', '्र', $contents);
		// $contents = str_replace('ý', 'ॠ', $contents);
		// $contents = str_replace('þ', 'ह्ण', $contents);
		$contents = str_replace('ÿ', 'च्', $contents);
		// $contents = str_replace('Œ', 'स्त्र्', $contents);
		$contents = str_replace('œ', 'त्', $contents);
		// $contents = str_replace('Š', 'ः', $contents);
		// $contents = str_replace('š', 'ट', $contents);
		$contents = str_replace('Ÿ', 'र', $contents);
		$contents = str_replace('ƒ', 'प्त', $contents); // Considered as danda
		$contents = str_replace('–', '्र', $contents);
		$contents = str_replace('—', 'व्', $contents);
		// $contents = str_replace('‘', '‘', $contents);
		// $contents = str_replace('’', '’', $contents);
		$contents = str_replace('“', 'दृ', $contents);
		$contents = str_replace('”', 'द्द', $contents);
		$contents = str_replace('„', '<', $contents);
		$contents = str_replace('†', '', $contents); // Considered as danda
		$contents = str_replace('‡', 'य', $contents);
		$contents = str_replace('…', 'ृ', $contents);
		$contents = str_replace('‰', 'ठ्य', $contents);
		// $contents = str_replace('‹', 'ङ्घ', $contents);
		$contents = str_replace('›', 'ढ़', $contents); //ka and va case
		$contents = str_replace('™', 'च', $contents);
		$contents = str_replace('˜', 'ष्', $contents);
		$contents = str_replace('⁄', 'ख', $contents);
		$contents = str_replace('∂', '∂', $contents);
		$contents = str_replace('ﬂ', 'घ', $contents);
		$contents = str_replace('˘', 'ड़', $contents);
		$contents = str_replace('≥', 'ग', $contents);
		$contents = str_replace('·', 'ष', $contents);
		$contents = str_replace(' ́', 'ह', $contents);
		$contents = str_replace('˛', 'ष्', $contents);
		$contents = str_replace('˝', 'ष्', $contents);
		$contents = str_replace('¬', 'ग्', $contents);
		$contents = str_replace('ı', 'रु', $contents);
		$contents = str_replace('', 'ऊ', $contents);
		$contents = str_replace('∑', 'ण्', $contents);
		$contents = str_replace('Ω', 'झ', $contents);
		$contents = str_replace('¨', '्र', $contents);
		$contents = str_replace('•े', 'के', $contents);
		$contents = preg_replace("/•\h*(ा|ु|ू|ृ|े|ै|ो|ौ|ं)/", 'क' . "$1", $contents);


		// $contents = str_replace('्ee', 'ा', $contents);
		// $contents = str_replace('्e', '', $contents);
		// $contents = str_replace('e', 'ा', $contents);
		$contents = str_replace('ाै', 'ौ', $contents);
		$contents = str_replace('ाे', 'ो', $contents);
		$contents = str_replace('्ंा' , 'ं', $contents);
		$contents = str_replace('ंे' , 'ें', $contents);
		$contents = str_replace('ँू' , 'ूँ', $contents);
		$contents = str_replace('अा', 'आ', $contents);
		$contents = str_replace('अो', 'ओ', $contents);
		$contents = str_replace('अौ', 'औ', $contents);
		$contents = str_replace('आॅ', 'ऑ', $contents);
		$contents = str_replace('अॅ', 'ॲ', $contents);
		$contents = str_replace('एे', 'ऐ', $contents);
		$contents = str_replace('एॅ', 'ऍ', $contents);
		$contents = str_replace('ाॅ', 'ॉ', $contents);

		// //~ Post processing
		// $contents = str_replace(']न', 'ऩ', $contents);
		// $contents = str_replace(']र', 'ऱ', $contents);
		// $contents = str_replace(']ळ', 'ऴ', $contents);
		// $contents = str_replace(']क', 'क़', $contents);
		// $contents = str_replace(']ख', 'ख़', $contents);
		// $contents = str_replace(']ग', 'ग़', $contents);
		// $contents = str_replace(']ज', 'ज़', $contents);
		// $contents = str_replace(']ड', 'ड़', $contents);
		// $contents = str_replace(']ढ', 'ढ़', $contents);
		// $contents = str_replace(']फ', 'फ़', $contents);
		// $contents = str_replace(']य', 'य़', $contents);

		$contents = str_replace('±', 'प', $contents);
		// $contents = str_replace('²', '×', $contents);
		// $contents = str_replace('³', '%', $contents);
		$contents = str_replace('´', 'ह', $contents);
		$contents = str_replace('μ', 'ध', $contents);
		//~ $contents = str_replace('•', 'क', $contents);
		// $contents = str_replace(']', '.', $contents);
		// $contents = str_replace('`', '‘', $contents);
		// $contents = str_replace('‘‘', '“', $contents);
		// $contents = str_replace('\'', '’', $contents);
		// $contents = str_replace('’’', '”', $contents);
		$contents = str_replace('।।', '॥', $contents);

		// $contents = str_replace(' ञ् ', ' — ', $contents);

		$contents = $this->invertIkaraShreeLipi($contents);
		$contents = $this->invertRephaShreeLipi($contents);

		// $contents = str_replace('ेा', 'ाे', $contents);
		// $contents = str_replace('ाै', 'ौ', $contents);
		// $contents = str_replace('ाे', 'ो', $contents);
		// $contents = str_replace('्ंा' , 'ं', $contents);
		// $contents = str_replace('्ी' , 'ी', $contents);
		// $contents = str_replace(' ः' , ' :', $contents);

		return $contents;
	}
	
	public function invertIkaraShreeLipi($text) {

		$vyanjana = "क|ख|ग|घ|ङ|च|छ|ज|झ|ञ|ट|ठ|ड|ढ|ण|त|थ|द|ध|न|ऩ|प|फ|ब|भ|म|य|र|ऱ|ल|ळ|ऴ|व|श|ष|स|ह|क़|ख़|ग़|ज़|ड़|ढ़|फ़|य़";
		$swara = "अ|आ|इ|ई|उ|ऊ|ऋ|ऌ|ऍ|ऎ|ए|ऐ|ऑ|ऒ|ओ|औ";
		$swaraJoin = "ा|ि|ी|ु|ू|ृ|ॄ|ॅ|ॆ|े|ै|ॉ|ॊ|ो|ौ" ; 

		$compoundConjunct = "($vyanjana)्($vyanjana)्($vyanjana)्($vyanjana)्($vyanjana)|($vyanjana)्($vyanjana)्($vyanjana)्($vyanjana)|($vyanjana)्($vyanjana)्($vyanjana)|($vyanjana)्($vyanjana)";
		$swaraConjunct = "($compoundConjunct)($swaraJoin)|($vyanjana)($swaraJoin)|($compoundConjunct)";

		$syllable = "($swaraConjunct)|($vyanjana)|($swara)";
		
		$text = preg_replace("/($syllable)/u", "$1zzz", $text);
		$text = preg_replace("/्zzz/u", "्", $text);

		$text = preg_replace("/(<)([^z]*?)zzz/u", "$2" . "$1zzz", $text);

		$text = str_replace("zzz", "", $text);
		$text = str_replace("<", "ि", $text);
				
		return($text);
	}
	
	public function invertRephaShreeLipi($text) {

		$vyanjana = "क|ख|ग|घ|ङ|च|छ|ज|झ|ञ|ट|ठ|ड|ढ|ण|त|थ|द|ध|न|ऩ|प|फ|ब|भ|म|य|र|ऱ|ल|ळ|ऴ|व|श|ष|स|ह|क़|ख़|ग़|ज़|ड़|ढ़|फ़|य़";
		$swara = "अ|आ|इ|ई|उ|ऊ|ऋ|ऌ|ऍ|ऎ|ए|ऐ|ऑ|ऒ|ओ|औ";
		$swaraJoin = "ा|ि|ी|ु|ू|ृ|ॄ|ॅ|ॆ|े|ै|ॉ|ॊ|ो|ौ|्|ं|ः|ऽ" ; 

		$syllable = "($vyanjana)($swaraJoin)($swaraJoin)($swaraJoin)|($vyanjana)($swaraJoin)($swaraJoin)|($vyanjana)($swaraJoin)|($vyanjana)|($swara)";
		
		$text = 'zzz' . preg_replace("/($syllable|\s|[[:punct:]])/u", "$1zzz", $text);
		$text = preg_replace("/्zzz/u", "्", $text);
		
		$text = str_replace("zzz∂", "∂zzz", $text);
		$text = preg_replace("/z([^z]*?)∂z/u", "z∂$1z", $text);
		$text = str_replace("∂", "र्", $text);
		$text = str_replace("zzz", "", $text);

		return($text);
	}
}

?>
