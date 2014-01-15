<?php
/*
 * Created on Jan 14, 2014
 *
 * Copyright 2014 The President and Fellows of Harvard College
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * @Author: Paul J. Morris  bdim@oeb.harvard.edu
 * 
 */
$debug=FALSE;

include_once('connection_library.php');
include_once('specify_library.php');
include_once('ajax/DataExplorer.php');

if ($debug) { 
	mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT);
} else { 
	mysqli_report(MYSQLI_REPORT_OFF);
}

$connection = specify_connect();
$errormessage = "";

$mode = "search";

if ($_GET['mode']!="")  {
	if ($_GET['mode']=="search") {
		$mode = "search"; 
	}
	if ($_GET['mode']=="details") {
		$mode = "details"; 
	}
} 

echo pageheader('image'); 
if ($connection) {
    if ($debug===TRUE) {  echo "[$mode]"; } 
		
	switch ($mode) {
		    case "details":
		        echo details();
		        break;
		    case "search":
		    default:
			    echo search(); 
	}
		
    $connection->close();
		
} else { 
    $errormessage .= "Unable to connect to database. ";
}
if ($errormessage!="") {
    echo "<strong>Error: $errormessage</strong>";
}
echo pagefooter();
	

// ******* main code block ends here, supporting functions follow. *****

function search() { 
   global $connection;
   $returnvalue = "";
   $start = preg_replace('/[^0-9]/','',$_GET['start']);
   if (strlen($start)==0) { $start = 0; }

   $dataexplorer = new DataExplorer(); 
   
   // obtain a list of type status terms with counts of images
   $tsList = array();
   foreach($dataexplorer->getTypestatusList() as $row => $tsArr){
       $tsList[] = (object)array( 'value' => $tsArr['value'], 'label' => $tsArr['label']);
   }
   $declareTypestatus = "var typestatus = ".json_encode($tsList)."; \n";

   // obtain a list of country names with counts of images
   $tsList = array();
   foreach($dataexplorer->getCountryList() as $row => $tsArr){
       $tsList[] = (object)array( 'value' => $tsArr['value'], 'label' => $tsArr['label']);
   }
   $declareCountry = "var country = ".json_encode($tsList)."; \n";

   // obtain a list of generic names with counts of images
   //$tsList = array();
   //foreach($dataexplorer->getGenusList() as $row => $tsArr){
   //    $tsList[] = (object)array( 'value' => $tsArr['value'], 'label' => $tsArr['label']);
   //}
   //$declareGenus = "var genus = ".json_encode($tsList)."; \n";

   // obtain a list of family names with counts of images
   $tsList = array();
   foreach($dataexplorer->getFamilyList() as $row => $tsArr){
       $tsList[] = (object)array( 'value' => $tsArr['value'], 'label' => $tsArr['label']);
   }
   $declareFamily = "var family = ".json_encode($tsList)."; \n";

   // create a place for the visualsearch widget.
   $returnvalue .= "<div>";
   $returnvalue .= "  <div class='visual_search'></div> ";
   $returnvalue .= "</div>";
   $returnvalue .= " 
                   <script type='text/javascript' charset='utf-8'> 
                     $declareTypestatus
                     $declareCountry
                     $declareFamily
                     $(document).ready(function() {
                       var visualSearch = VS.init({
                         container : $('.visual_search'),
                         query     : 'genus: Croton',
                         callbacks : {
                           search       : ( function(query, searchCollection) {
                              var \$focused = $(':focus');
                              \$focused.blur();
                              $.post('ajax/imagesearch.php', { 'query' : visualSearch.searchBox.value() , 'start' : '$start' }, function(result) {
                                   $('#results').html(result); 
                                   });
                              var \$focused = $(':focus');
                              \$focused.blur();
                           } ),
                           facetMatches : ( function(callback) {
                              callback([
                              'typestatus', 'genus', 'country', 'state', 'family'
                              ]);
                            } ),
                            valueMatches : ( function(facet, searchTerm, callback) {
                               switch (facet) {
                                 case 'typestatus':
                                   callback(typestatus);
                                   break;
                                 case 'country':
                                   callback(country);
                                   break;
                                 case 'family':
                                   callback(family);
                                   break;
                                 }
                           } )
                         } // end callbacks 
                       }); // end VS.init
                       $.post('ajax/imagesearch.php', { 'query' : visualSearch.searchBox.value() }, function(result) {
                           $('#results').html(result); 
                           });
                     }); // end document ready
                   </script>
";
   // create a place where the results will be displayed
   $returnvalue .= "<div id='results'>";
   $returnvalue .= "</div>";
   $returnvalue .= "<br clear='All'/><br/><br/><br/>";
   
   return $returnvalue;
}

function details() {
  $result = "";
  $imagesetid = preg_replace('/[^0-9]/','',$_GET['imagesetid']);

  $result .= "Image details for [$imagesetid].  Not implemented yet.";

  return $result;
}

mysqli_report(MYSQLI_REPORT_OFF);
 
?>
