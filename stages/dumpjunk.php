<?php 

class Dumpjunk {

	private $langConstraint;

	public function __construct() {
		
	}

	public function getAllFiles($bookID) {

		$allFiles = [];
		
		$folderPath = UNICODE_SRC . $bookID . '/';
		
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderPath));

		foreach($iterator as $file => $object) {

			if(preg_match('/.*' . $bookID . '\/\d.*\.xhtml$/',$file)) array_push($allFiles, $file);
		}

		sort($allFiles);

		return $allFiles;
	}

	public function extractJunk($bookID) {
		
		$allXhtmlFiles = $this->getAllFiles($bookID);

		$junkWords = [];
		$daWords = [];
		$falseWord = [];

		foreach($allXhtmlFiles as $xhtmlFile){

			$tempJunk = [];
			$tempFalse = [];
			$alphaNumeric = [];
			$finalWords = $this->getArrayOfWords(file_get_contents($xhtmlFile));
			$xhtmlFile = str_replace(constant('UNICODE_SRC'), '',$xhtmlFile); 
			
			foreach($finalWords as $word){
				
				// Alpha Numeric word
				if(preg_match('/್ಧ/u', $word))
					array_push($daWords, $word);
				
				if(preg_match('/^[+a-zA-Z0-9-,#:;.()@\\/*"‘’“&!”—?\'\[\]_]+$/u', $word)){
					array_push($alphaNumeric, $word);
					continue;
				}

				if($this->checkWord($word))
					array_push($tempFalse, $word);

				if($this->checkLetters($word))
					array_push($tempJunk, $word);
				
			}

			if($tempJunk){

				array_push($junkWords, $xhtmlFile);
				array_push($junkWords, "List of Junk Word");
				$junkWords = array_merge($junkWords,$tempJunk);
			}

			if($tempFalse){

				array_push($falseWord, $xhtmlFile);
				array_push($falseWord, "List of False Word");
				$falseWord = array_merge($falseWord,$tempFalse);
			}
		}

		// array_push($alphaNumeric, $xhtmlFile);
		// array_push($falseWord, "List of alphaNumeric Word");
		// $falseWord = array_merge($falseWord,$alphaNumeric);

		if(file_exists(RAW_SRC . $bookID . '/' . $bookID . ".junk.txt"))
			unlink(RAW_SRC . $bookID . '/' . $bookID . ".junk.txt");

		if($junkWords || $falseWord)
			file_put_contents(RAW_SRC . $bookID . '/' . $bookID . ".junk.txt", implode("\n",array_merge($junkWords, $falseWord)));
			
		if($daWords)
			file_put_contents(RAW_SRC . $bookID . '/' . $bookID . "daa.junk.txt", implode("\n",$daWords));

	}

	public function checkWord($word) {

		$vyanjana = $this->langConstraint['vyanjana'];
		$swara_endings = $this->langConstraint['swaraEndings'];
		$halanta = $this->langConstraint['halanta'];
		$yogavahagalu = $this->langConstraint['yogavahagalu'];
		$swara = $this->langConstraint['swara'];;
		$numeric = $this->langConstraint['numeric'];;
		
		// checking word start with swara endings
		if(preg_match("/^($swara_endings|$yogavahagalu|$halanta)/u", $word))
			return 1;

		// checking word contain more than one swara endings or yogavahagalu or halanta
		elseif(preg_match("/($swara_endings){2}|($yogavahagalu){2}|($halanta){2}/u", $word))
			return 1;
		
		// checking word contain yogavahagalu followed by swara endings
		elseif(preg_match("/($yogavahagalu)($swara_endings|$halanta)/u", $word))
			return 1;

		elseif(preg_match("/($swara_endings)($halanta)/u", $word))
			return 1;
			
		elseif(preg_match("/($halanta)($swara_endings)/u", $word))
			return 1;

		elseif(preg_match("/($swara)($swara_endings)/u", $word))
			return 1;

		elseif(preg_match("/($vyanjana|$swara_endings|$halanta|$yogavahagalu)($swara)/u", $word))
			return 1;

		elseif(preg_match("/[$numeric]+($yogavahagalu)|($yogavahagalu)[$numeric]+/u", $word))
			return 1;
		
		//Zero width space followed by swaraending or halanta or yogavahagalu should not occur
		elseif(preg_match("/​($swara_endings|$halanta|$yogavahagalu)/u", $word))
			return 1;

		else
			return 0;
	}

	public function checkLetters($word) {

		$characters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);

		foreach ($characters as $character) {
			

			// For zero width space character
			if ($this->_uniord($character) == 8203 || $this->_uniord($character) == 8204 || $this->_uniord($character) == 8205)	continue;

			if(!(($this->_uniord($character) >= $this->_uniord($this->langConstraint['range']['start'])) && ($this->_uniord($character) <= $this->_uniord($this->langConstraint['range']['end']))))
				if(!preg_match('/[(),\'"“”—\-\.‘’!?;=:#,*`¯\[\]\\/॥।]/u', $character))
					return 1;
		}
		
		return 0;	
	}

	public function sanityCheck($bookID) {

		$allXhtmlFiles = $this->getAllFiles($bookID);
		
		foreach($allXhtmlFiles as $xhtmlFile) {

			$xhtmlFileContents = file_get_contents($xhtmlFile);
			$xhtmlFileContents = explode('\n', $xhtmlFileContents);
			$data = '';
			$insideBody = 0;

			foreach ($xhtmlFileContents as $line) {

				if(preg_match('/<body/i', $line)){
					$insideBody = 1;
				}

				if($insideBody) {

					// Spacing between characters
					$line = preg_replace('/(॥|।)/', " $1 ", $line);

					//Right Spacing
					$line = preg_replace('/\h*(,|;|’|”)\h*/u', "$1 ", $line);
					
					//Left Spacing
					$line = preg_replace('/\h*(‘|“)\h*/u', " $1", $line);

					// Spacing between inline elements
					$line = preg_replace('/<strong>\h*(.*?)\h*<\/strong>/u', " <strong>$1</strong> ", $line);
					$line = preg_replace('/<span(.*?)>\h*(.*?)\h*<\/span>/u', " <span$1>$2</span> ", $line);
					$line = preg_replace('/<a(.*?)>(.*?)<\/a>/u', " <a$1>$2</a> ", $line);
					
					// Double Quote
					$line = preg_replace('/‘\h*‘/u', "“", $line);
					$line = preg_replace('/’\h*’/u', "”", $line);
					
					// Replace multiple space with single space
					$line = preg_replace('/\h+/u', ' ', $line);

					// Remove extra space
					$line = preg_replace('/\h+(\.|”|,|’)/u', "$1", $line); // space before '.' OR '”' tag
					$line = preg_replace('/\w(\()/u', " $1", $line); // space between bracket tag
					$line = preg_replace('/(\()\h*/u', "$1", $line); // space between BR tag
					$line = preg_replace('/\h*(\))/u', "$1", $line); // space between BR tag
					$line = preg_replace('/\h*<br\h*\/>\h*/u', '<br />', $line); // space between BR tag
					$line = preg_replace('/\h*(<h[1-6].*?>)\h*/u', "$1", $line); // space at starting of heading tags
					$line = preg_replace('/\h*(<\/h[1-6]>)\h*/u', "$1", $line); // space at ending of heading tags
					$line = preg_replace('/\h*(<(p|li|td|th|section).*?>)\h*/u', "$1", $line); // space at ending of heading tags
					$line = preg_replace('/\h*(<\/(p|li|td|th|section)>)\h*/u', "$1", $line); // space at ending of heading tags
					$line = preg_replace('/\h*<sup>\h*<a/u', "<sup><a", $line); // space at sup tag
					$line = preg_replace('/a>\h*<\/sup>\h*/u', "a></sup> ", $line); // space at sup tag
					$line = preg_replace('/<sup><a epub:type="noteref" href="999-aside.xhtml#(.*?)">.*?<\/a><\/sup>/u', "<sup><a epub:type=\"noteref\" href=\"999-aside.xhtml#$1\">*</a></sup>", $line); // Adding * for footnote marker

					// Final Modification
					$line = str_replace(' ‘ <strong>', " ‘<strong>", $line);
					$line = preg_replace("/ \n /u", "\n", $line);
				}

				$data .= $line;
			}

			file_put_contents($xhtmlFile, $data);
		}
	}

	public function getArrayOfWords($xhtmlFileContents)	{

		$xhtmlFileContents = preg_replace('/\h*<br\h*\/>\h*/u', ' ', $xhtmlFileContents);
		$xhtmlFileContents = strip_tags($xhtmlFileContents);
		// new file normalizations
		$xhtmlFileContents = str_replace('.', '. ', $xhtmlFileContents);
		$xhtmlFileContents = preg_replace('/,/', ', ', $xhtmlFileContents);
		$xhtmlFileContents = preg_replace('/\s+/', ' ', $xhtmlFileContents);
		$xhtmlFileContents = preg_replace('/ /', "\n", $xhtmlFileContents);
		$xhtmlFileContents = str_replace('–', '-', $xhtmlFileContents);
		$finalWords = explode("\n", $xhtmlFileContents);

		return $finalWords;
	}

	public function setLanguageContraint($language)	{

		$contentString = file_get_contents(JSON_PRECAST . 'language-details.json');
		$content = json_decode($contentString, true);

		if(isset($content[$language])){
			$this->langConstraint = $content[$language];
			return 1;
		}
		else
			return 0;
	}

	public function _uniord($c) {

		if (ord($c{0}) >=0 && ord($c{0}) <= 127)
			return ord($c{0});
		if (ord($c{0}) >= 192 && ord($c{0}) <= 223)
			return (ord($c{0})-192)*64 + (ord($c{1})-128);
		if (ord($c{0}) >= 224 && ord($c{0}) <= 239)
			return (ord($c{0})-224)*4096 + (ord($c{1})-128)*64 + (ord($c{2})-128);
		if (ord($c{0}) >= 240 && ord($c{0}) <= 247)
			return (ord($c{0})-240)*262144 + (ord($c{1})-128)*4096 + (ord($c{2})-128)*64 + (ord($c{3})-128);
		if (ord($c{0}) >= 248 && ord($c{0}) <= 251)
			return (ord($c{0})-248)*16777216 + (ord($c{1})-128)*262144 + (ord($c{2})-128)*4096 + (ord($c{3})-128)*64 + (ord($c{4})-128);
		if (ord($c{0}) >= 252 && ord($c{0}) <= 253)
			return (ord($c{0})-252)*1073741824 + (ord($c{1})-128)*16777216 + (ord($c{2})-128)*262144 + (ord($c{3})-128)*4096 + (ord($c{4})-128)*64 + (ord($c{5})-128);
		if (ord($c{0}) >= 254 && ord($c{0}) <= 255)    //  error
			return FALSE;
			
		return 0;
	}
}	
?>
