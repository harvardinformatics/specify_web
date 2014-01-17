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

$passon = "image";
if ($_GET['mode']!="")  {
	if ($_GET['mode']=="search") {
		$mode = "search"; 
	}
	if ($_GET['mode']=="details") {
		$mode = "details"; 
        $passon = "imagedetails";
	}
} 

echo pageheader($passon); 
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
  global $connection;
  $result = "";
  $imagesetid = preg_replace('/[^0-9]/','',$_GET['imagesetid']);


  $sql = 'SELECT DISTINCT concat(ifnull(r.url_prefix,\'\'),iot.uri), '.
  ' concat(ifnull(rf.url_prefix,\'\'),iof.uri) '.
  'FROM IMAGE_SET i '.
  'LEFT JOIN IMAGE_OBJECT iot ON i.id = iot.image_set_id  ' .
  'LEFT JOIN REPOSITORY r on iot.repository_id = r.id ' .
  'LEFT JOIN IMAGE_OBJECT iof ON i.id = iof.image_set_id  ' .
  'LEFT JOIN REPOSITORY rf on iof.repository_id = rf.id ' .
  'WHERE i.id = ? and iot.object_type_id = 2  '.
  ' and iof.object_type_id = 4 ';
  $stmt = $connection->stmt_init();
  if ($stmt->prepare($sql)) { 
     $stmt->bind_param('i',$imagesetid);
     $stmt->execute();
     $stmt->bind_result($thumburi,$fulluri);
     $stmt->fetch();
     $stmt->close();
  }
  $uuid = "";
  $sql = "select uuid " .
         "from IMAGE_SET_collectionobject i left join fragment f on i.collectionobjectid = f.collectionobjectid left join guids g on f.fragmentid = g.primarykey where g.tablename = 'fragment' and i.imagesetid = ? ";
  $stmt = $connection->stmt_init();
  if ($stmt->prepare($sql)) { 
     if ($debug) { echo "[$sql]"; } 
     $stmt->bind_param('i',$imagesetid);
     $stmt->execute();
     $stmt->bind_result($uuid);
     $stmt->fetch();
     $stmt->close();
  }
  $sql = "select collectioncode, catalognumber, scientificname, scientificnameauthorship, country, stateprovince, locality, i.collectionobjectid " .
         "from dwc_search where fragmentguid = ? ";
  $stmt = $connection->stmt_init();
  if ($stmt->prepare($sql)) { 
     if ($debug) { echo "[$sql]"; } 
     $stmt->bind_param('i',"http://purl.oclc.org/net/edu.harvard.huh/guid/uuid/$uuid");
     $stmt->execute();
     $stmt->bind_result($collectioncode, $catalognumber, $scientificname, $author, $country, $stateprovince, $locality, $collectionobjectid);
     $stmt->fetch();
     $stmt->close();
  }
  $result .= "
          <script type=\"text/javascript\">
          // Featured Image Zoomer (w/ optional multizoom and adjustable power)- By Dynamic Drive DHTML code library (www.dynamicdrive.com)
          // Multi-Zoom code (c)2012 John Davenport Scheuer
          // as first seen in http://www.dynamicdrive.com/forums/
          // username: jscheuer1 - This Notice Must Remain for Legal Use
          // Visit Dynamic Drive at http://www.dynamicdrive.com/ for this script and 100s more
          
          jQuery(document).ready(function($){
          
              $('#image1').addimagezoom({ // 
                  zoomrange: [10, 20],
                  magnifiersize: [800,600],
                  magnifierpos: 'right',
                  cursorshade: true,
                  largeimage: '$fulluri' 
              })
              
          })
          
          </script>
  ";

  $result .= "<h2>Image details for [$imagesetid].</h2>\n";
  $result .= "<br clear='All'/>";
  $result .= "<div style='background-color:#F3F3F3; width:1075px; height:625px; border-color:#939393; border-width:1; border-style:solid;' >";

  $result .= "<img id='image1' border='0' src='$thumburi' style=' width:250px; height:338px; '>";
  $result .= "&nbsp;&nbsp;Mouse over image to zoom.<br/>\n";

  $result .= "</div>";
  $result .= "<a href='specimen_search.php?mode=details&id=$collectionobjectid'>$collectioncode $catalognumber</a><br/>";
  $result .= "<em>$scientificname</em> $authorship<br/>";
  $result .= "$country $stateprovince<br/>";
  $result .= "$locality<br/>";
  $result .= "<br clear='All'/>";

  return $result;
}

mysqli_report(MYSQLI_REPORT_OFF);
 
?>
