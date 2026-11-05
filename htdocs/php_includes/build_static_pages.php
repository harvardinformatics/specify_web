<?php
/*
 * Created on Dec 3, 2009
 *
 */
$debug=true;

$targetdirectory = "/var/www/htdocs/specify_web/";

include_once('connection_library.php');
include_once('specify_library.php');

$connection = specify_connect();
$errormessage = "";

$mode = "rebuild";

if ($connection) { 
	
	switch ($mode) {
		
	   case "rebuild":
	      // PHP 8: fwrite()/fclose() on a failed fopen() (false) throw a TypeError, so check each file.
	      $pages = array(
	         "familylist.html" => "families",
	         "countrylist.html" => "countries",
	         "sitestatistics.html" => "stats"
	      );
	      foreach ($pages as $page => $content) {
	         $targetfile = $targetdirectory.$page;
	         $file = fopen($targetfile,"w");
	         if ($file === false) {
	            $errormessage .= "Unable to open $targetfile for writing. ";
	            continue;
	         }
	         fwrite($file, pageheader($mode));
	         fwrite($file, $content == "stats" ? stats() : browse($content));
	         fwrite($file, pagefooter());
	         fclose($file);
	      }
	   break;

	   default:
	      $errormessage = "Undefined search mode"; 
	}
	
   $connection->close();
	
} else { 
	$errormessage .= "Unable to connect to database. ";
}
 
if ($errormessage!="") {
   echo "<strong>Error: $errormessage</strong>";
}

 
// ******* main code block ends here. *****
// stats() and browse() are provided by specify_library.php.  The local copies that
// used to be here redeclared those functions (a fatal error) and have been removed.

?>
