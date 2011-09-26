<?php
/*
 * Created on Jun 8, 2010
 *
 * Copyright 2010 The President and Fellows of Harvard College
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
$debug=false;

include_once('connection_library.php');
include_once('specify_library.php');

if ($debug) { 
	mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT);
} else { 
	mysqli_report(MYSQLI_REPORT_OFF);
}

$connection = specify_connect();
$errormessage = "";

$mode = "menu";
 
if ($_GET['mode']!="")  {
	if ($_GET['mode']=="family_type_count") {
		$mode = "family_type_count"; 
	}
	if ($_GET['mode']=="family_type_count_summary") {
		$mode = "family_type_count_summary"; 
	}
} 
	
echo pageheader('qc'); 

// Only display if internal 
if (preg_match("/^140\.247\.98\./",$_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR']=='127.0.0.1') { 
						
  
	if ($connection) {
		if ($debug) {  echo "[$mode]"; } 
		
		switch ($mode) {
		    case "family_type_count":
		        echo family_type_count();
		        break;
		    case "family_type_count_summary":
		        echo family_type_count_summary();
		        break;
			case "menu": 	
			default:
				echo menu(); 
		}
		
		$connection->close();
		
	} else { 
		$errormessage .= "Unable to connect to database. ";
	}
	
	if ($errormessage!="") {
		echo "<strong>Error: $errormessage</strong>";
	}
	
	
    echo "<h3><a href='stats.php'>Database Statistics</a></h3>";						
	
} else {
	echo "<h2>Stats pages are available only within HUH</h2>"; 
}

echo pagefooter();

// ******* main code block ends here, supporting functions follow. *****

function menu() { 
   $returnvalue = "";

   $returnvalue .= "<div>";
   $returnvalue .= "<h2>Type Counts</h2>";
   $returnvalue .= "<ul>";
   $returnvalue .= "<li><a href='stats.php?mode=family_type_count'>Counts of number of types by family (slow)</a></li>";
   $returnvalue .= "<li><a href='stats.php?mode=family_type_count_summary'>Counts of number of types by family (from web search cache)</a></li>";
   $returnvalue .= "</ul>";
   $returnvalue .= "</div>";

   return $returnvalue;
}

function family_type_count() { 
	global $connection;
   $returnvalue = "";
    
   $query = "select name, highestchildnodenumber, nodenumber from taxon where rankid = 140 order by name ";
   if ($debug) { echo "[$query]<BR>"; } 
      $returnvalue .= "<h2>Counts of the number of specimens in each family that have a type status (other than just 'NotType').</h2>";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($family,$highestchildnodenumber,$nodenumber);
		$statement->store_result();
	        $returnvalue .= "<table>";
		while ($statement->fetch()) {
	            $returnvalue .= "<tr><td>$family</td>";
                    $query2 = "select count(distinct fragment.identifier) from taxon left join determination on taxon.taxonid = determination.taxonid left join fragment on determination.fragmentid = fragment.fragmentid where nodenumber >= ? and highestchildnodenumber <= ? and determination.typestatusname is not null and determination.typestatusname <> 'NotType';";
	            $statement2 = $connection->prepare($query2);
                    $statement2->bind_param("ii",$nodenumber,$highestchildnodenumber);
		    $statement2->bind_result($count);
		    $statement2->execute();
		    $statement2->store_result();
		    while ($statement2->fetch()) {
	                $returnvalue .= "<td>$count</td>";
		    }
	            $returnvalue .= "</tr>";
                }
	        $returnvalue .= "</table>";
	}

   return $returnvalue;
}
function family_type_count_summary() { 
	global $connection;
   $returnvalue = "";
    
   $query = "select distinct family from web_search where family is not null order by family ";
   if ($debug) { echo "[$query]<BR>"; } 
      $returnvalue .= "<h2>Counts of the number of specimens in each family that have a type status (other than just 'NotType').  Counts calculated from the web search cache table, may be slightly different than counts calculated from current data.</h2>";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($family);
		$statement->store_result();
	        $returnvalue .= "<table>";
		while ($statement->fetch()) {
	            $returnvalue .= "<tr><td>$family</td>";
                    $query2 = "select count(distinct barcode) from web_search where typestatus is not null and typestatus <> 'NotType' and family = ? ;";
	            $statement2 = $connection->prepare($query2);
                    $statement2->bind_param("s",$family);
		    $statement2->bind_result($count);
		    $statement2->execute();
		    $statement2->store_result();
		    while ($statement2->fetch()) {
	                $returnvalue .= "<td>$count</td>";
		    }
	            $returnvalue .= "</tr>";
                }
	        $returnvalue .= "</table>";
	}

   return $returnvalue;
}


mysqli_report(MYSQLI_REPORT_OFF);
 
?>
