<?php
/*
 * Created on Sept 27, 2011
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
	if ($_GET['mode']=="california_dwc") {
		$mode = "california_dwc"; 
	}
} 
	

  
	if ($connection) {
		if ($debug) {  echo "[$mode]"; } 
		
		switch ($mode) {
		    case "california_dwc":
		        echo california_dwc();
		        break;
		    case "menu": 	
		    default:
                            echo pageheader('datadumps'); 
			    echo menu(); 
                            echo pagefooter();
		}
		
		$connection->close();
		
	} else { 
		$errormessage .= "Unable to connect to database. ";
	}
	
	if ($errormessage!="") {
		echo "<strong>Error: $errormessage</strong>";
	}

// ******* main code block ends here, supporting functions follow. *****

function menu() { 
   $returnvalue = "";

   $returnvalue .= "<div>";
   $returnvalue .= "<h2>Generate data dumps</h2>";
   $returnvalue .= "<ul>";
   $returnvalue .= "<li><a href='dumps.php?mode=california_dwc'>DarwinCore records for Specimens in California (csv file)</a></li>";
   $returnvalue .= "</ul>";
   $returnvalue .= "</div>";

   return $returnvalue;
}

function california_dwc() { 
	global $connection,$debug;

// From comments in php docs
if ( !function_exists('sys_get_temp_dir')) {
  function sys_get_temp_dir() {
      if( $temp=getenv('TMP') )        return $temp;
      if( $temp=getenv('TEMP') )        return $temp;
      if( $temp=getenv('TMPDIR') )    return $temp;
      $temp=tempnam(__FILE__,'');
      if (file_exists($temp)) {
          unlink($temp);
          return dirname($temp);
      }
      return null;
  }
}

    
   if ($debug) { echo "[" . sys_get_temp_dir() . "]<BR>"; } 
   $tempfilename = tempnam(realpath(sys_get_temp_dir()), "csv");
   if ($debug) { echo "[$tempfilename]<BR>"; } 
   $file = fopen($tempfilename,"w");

   $query = "select institution, collectioncode, collectionid, catalognumber, catalognumbernumeric, dc_type, basisofrecord, collectornumber, collector, sex, reproductiveStatus, preparations, verbatimdate, eventdate, year, month, day, startdayofyear, enddayofyear, startdatecollected, enddatecollected, habitat, highergeography, continent, country, stateprovince, islandgroup, county, island, municipality, locality, minimumelevationmeters, maximumelevationmeters, verbatimelevation, decimallatitude, decimallongitude, geodeticdatum, identifiedby, dateidentified, identificationqualifier, identificationremarks, identificationreferences, typestatus, scientificname, scientificnameauthorship, family, informationwitheld, datageneralizations, othercatalognumbers from dwc_search where stateprovince = 'California' ";
   if ($debug) { echo "[$query]<BR>"; } 
        $linearray = array ("institution","collectioncode","collectionid","catalognumber","catalognumbernumeric","dc_type","basisofrecord","collectornumber","collector","sex","reproductiveStatus","preparations","verbatimdate","eventdate","year","month","day","startdayofyear","enddayofyear","startdatecollected","enddatecollected","habitat","highergeography","continent","country","stateprovince","islandgroup","county","island","municipality","locality","minimumelevationmeters","maximumelevationmeters","verbatimelevation","decimallatitude","decimallongitude","geodeticdatum","identifiedby","dateidentified","identificationqualifier","identificationremarks","identificationreferences","typestatus","scientificname","scientificnameauthorship","family","informationwitheld","datageneralizations","othercatalognumbers" ) ;
        fputcsv($file,$linearray);
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($institution, $collectioncode, $collectionid, $catalognumber, $catalognumbernumeric, $dc_type, $basisofrecord, $collectornumber, $collector, $sex, $reproductiveStatus, $preparations, $verbatimdate, $eventdate, $year, $month, $day, $startdayofyear, $enddayofyear, $startdatecollected, $enddatecollected, $habitat, $highergeography, $continent, $country, $stateprovince, $islandgroup, $county, $island, $municipality, $locality, $minimumelevationmeters, $maximumelevationmeters, $verbatimelevation, $decimallatitude, $decimallongitude, $geodeticdatum, $identifiedby, $dateidentified, $identificationqualifier, $identificationremarks, $identificationreferences, $typestatus, $scientificname, $scientificnameauthorship, $family, $informationwitheld, $datageneralizations, $othercatalognumbers);
		$statement->store_result();
		while ($statement->fetch()) {
	            $linearray = array( "$institution","$collectioncode","$collectionid","$catalognumber","$catalognumbernumeric","$dc_type","$basisofrecord","$collectornumber","$collector","$sex","$reproductiveStatus","$preparations","$verbatimdate","$eventdate","$year","$month","$day","$startdayofyear","$enddayofyear","$startdatecollected","$enddatecollected","$habitat","$highergeography","$continent","$country","$stateprovince","$islandgroup","$county","$island","$municipality","$locality","$minimumelevationmeters","$maximumelevationmeters","$verbatimelevation","$decimallatitude","$decimallongitude","$geodeticdatum","$identifiedby","$dateidentified","$identificationqualifier","$identificationremarks","$identificationreferences","$typestatus","$scientificname","$scientificnameauthorship","$family","$informationwitheld","$datageneralizations","$othercatalognumbers" ) ;
                    fputcsv($file,$linearray);
                } 
	}
        fclose($file);

        // write header and send file to browser
        header("Content-Type: application/csv");
        header("Content-Disposition: attachment;Filename=HUH_dwc_california.csv");
        readfile($tempfilename);
        // remove temp file
        unlink($tempfilename);

}


mysqli_report(MYSQLI_REPORT_OFF);
 
?>
