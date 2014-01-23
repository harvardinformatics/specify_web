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
	if ($_GET['mode']=="qc") {
		$mode = "qc"; 
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
		    case "qc":
                echo "<h2>Image Batch Quality Control</h2>";
		        echo batch_qc();
		        break;
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
  $sql = "select collectioncode, catalognumber, scientificname, scientificnameauthorship, country, stateprovince, locality, collectionobjectid " .
         "from dwc_search where fragmentguid = ? ";
  $stmt = $connection->stmt_init();
  if ($stmt->prepare($sql)) { 
     if ($debug) { echo "[$sql]"; } 
     $guid = "http://purl.oclc.org/net/edu.harvard.huh/guid/uuid/$uuid";
     $stmt->bind_param('s',$guid);
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

function batch_qc() { 
  global $connection;

  $batchid = preg_replace('/[^0-9]/','',$_GET['batchid']);
  if (strlen($batchid>0)) { 
     return batch_details($batchid);
  } else { 
     return list_batches();
  }

}

function batch_details($batchid) { 
  global $connection;
  $result = "<br/>";

  $sql = "select s.id, b.production_date, b.batch_name, b.remarks, b.project, l.name, s.active_flag, s.description, s.owner, s.copyright, s.remarks, s.caption from IMAGE_BATCH b left join IMAGE_LAB l on lab_id = l.id left join IMAGE_SET s on b.id = s.batch_id where b.id = ? ";
  $stmt = $connection->stmt_init();
  if ($stmt->prepare($sql)) { 
    $stmt->bind_param('i',$batchid);
    $stmt->execute();
    $stmt->bind_result($imagesetid, $date, $name, $remarks, $project, $lab, $activeflag, $description, $owner, $copyright, $setremarks,$caption);
    $row = 0;
    $links = "";
    $stmt->store_result();
    $subresult = "";
    while ($stmt->fetch()) { 
       $setcount++;
       if ($row==0) { 
          $result .= "<strong>Batch:</strong> <a href='image_search.php?mode=qc&batchid=$batchid'>$name</a><br/><strong>Date:</strong> $date<br/><strong>Project:</strong> $project.<br/><strong>Facility:</strong> $lab<br/><strong>Remarks:</strong> $remarks</br>\n";
       } 
       $links = "";
       $sql = "select distinct j.collectionobjectid, f.text1, f.identifier, p.identifier from IMAGE_SET_collectionobject j left join fragment f on j.collectionobjectid = f.collectionobjectid left join preparation p on f.preparationid = p.preparationid where j.imagesetid = ? ";
       $stmt1 = $connection->stmt_init();
       if ($stmt1->prepare($sql)) { 
          $stmt1->bind_param('i',$imagesetid);
          $stmt1->execute();
          $stmt1->bind_result($collectionobjectid, $collectioncode, $fbarcode, $pbarcode);
          $stmt1->store_result();
          while ($stmt1->fetch()) { 
            $collcount++;
            $links .= "<a href='specimen_search.php?mode=details&id[]=$collectionobjectid'>$collectioncode $fbarcode $pbarcode</a>";
          }
          $stmt1->close();
       }
       $images = ""; 
       $sql = "select url_prefix, uri, r.name, t.name from IMAGE_OBJECT i left join REPOSITORY r on i.repository_id = r.id left join IMAGE_OBJECT_TYPE t on i.object_type_id = t.id where image_set_id = ?";
       $stmt1 = $connection->stmt_init();
       if ($stmt1->prepare($sql)) { 
          $stmt1->bind_param('i',$imagesetid);
          $stmt1->execute();
          $stmt1->bind_result($prefix,$url,$repository,$type);
          $stmt1->store_result();
          while ($stmt1->fetch()) { 
            $imagecount++;
            $images .= "$repository <a href='$prefix$url'>$type</a> $url<br/>";
            if ($type=="Thumbnail") { 
               $images .= "<img src='$prefix$url'><br/>"; 
            }
          }
          $stmt1->close();
       }
       if ($activeflag==1) {  $active = "Image set:"; } else { $active = "Image set <strong>(Inactive)</strong>:"; } 
       $subresult .= "<a href='image_search.php?mode=details&imagesetid=$imagesetid'>$active</a> $caption $description $links $owner $copyright $remarks $setremarks<br/>$images<br/>";
       $row++;
    } 
    $result .= "Specimens: $collcount<br/>Image Sets: $setcount<br/>Images: $imagecount<br/><br/>$subresult";
    $stmt->close();
  } else { 
    $result .= "Query Error: " . $connection->error . $stmt->error;
  }

  return $result;

} 

function list_batches() { 
  global $connection;
  $result = "";

  $sql = "select count(s.id), b.id, b.production_date, b.batch_name, b.remarks, b.project, l.name from IMAGE_BATCH b left join IMAGE_LAB l on lab_id = l.id left join IMAGE_SET s on b.id = s.batch_id group by b.id, production_date, batch_name, remarks, project, l.name order by b.production_date desc";
  $stmt = $connection->stmt_init();
  if ($stmt->prepare($sql)) { 
    $stmt->execute();
    $stmt->bind_result($specimencount, $batchid, $date, $name, $remarks, $project, $lab);
    while ($stmt->fetch()) { 
       $result .= "Batch: <a href='image_search.php?mode=qc&batchid=$batchid'>$name</a> on $date with $specimencount sheets.  Project: $project. Lab: $lab. $remarks</br>\n";
    } 
    $stmt->close();
  } else { 
    $result .= "Query Error: " . $connection->error . $stmt->error;
  }

  return $result;
}

mysqli_report(MYSQLI_REPORT_OFF);
 
?>
