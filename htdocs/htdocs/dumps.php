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

$genus = "Carex"; 
$genus = "China"; 

if ($_GET['mode']!="")  {
	if ($_GET['mode']=="california_dwc") {
		$mode = "california_dwc"; 
	}
	if ($_GET['mode']=="cultivated_dwc") {
		$mode = "cultivated_dwc"; 
	}
	if ($_GET['mode']=="rock_feng_dwc") {
		$mode = "rock_feng_dwc"; 
	}
	if ($_GET['mode']=="country_geo_dwc") {
		$mode = "country_geo_dwc"; 
		$country = preg_replace("/[^A-Za-z ]$/","",$_GET['country']);  
	}
	if ($_GET['mode']=="genus_dwc") {
		$mode = "genus_dwc"; 
		$genus = preg_replace("/[^A-Za-z]$/","",$_GET['genus']);  
	}

} 
	

  
	if ($connection) {
		if ($debug) {  echo "[$mode]"; } 
		
		switch ($mode) {
		    case "california_dwc":
		        echo california_dwc();
		        break;
		    case "cultivated_dwc":
		        echo cultivated_dwc();
		        break;
		    case "rock_feng_dwc":
		        echo rock_feng_collectors_dwc();
		        break;
		    case "genus_dwc":
		        echo genus_dwc($genus);
		        break;
		    case "country_geo_dwc":
		        echo country_geo_dwc($country);
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
   $returnvalue .= "<li><a href='dumps.php?mode=california_dwc'>DarwinCore records for Specimens in California, excluding FH (csv file)</a></li>";
   $returnvalue .= "<li><a href='dumps.php?mode=cultivated_dwc'>DarwinCore records for Cultivated genera, (csv file)</a></li>";
   $returnvalue .= "<li><a href='dumps.php?mode=rock_feng_dwc'>DarwinCore records for China collected by Rock or Feng, (csv file)</a></li>";
   $returnvalue .= "<li><a href='dumps.php?mode=country_geo_dwc&country=China'>DarwinCore records for China with georeferences, (csv file)</a></li>";
   $returnvalue .= "<li><a href='dumps.php?mode=genus_dwc&genus=Carex'>Darwin core records for Carex, (csv file)</a></li>";
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

   $query = "select institution, collectioncode, collectionid, catalognumber, catalognumbernumeric, dc_type, basisofrecord, collectornumber, collector, sex, reproductiveStatus, preparations, verbatimdate, eventdate, year, month, day, startdayofyear, enddayofyear, startdatecollected, enddatecollected, habitat, highergeography, continent, country, stateprovince, islandgroup, county, island, municipality, locality, minimumelevationmeters, maximumelevationmeters, verbatimelevation, decimallatitude, decimallongitude, geodeticdatum, identifiedby, dateidentified, identificationqualifier, identificationremarks, identificationreferences, typestatus, scientificname, scientificnameauthorship, family, informationwitheld, datageneralizations, othercatalognumbers from dwc_search where stateprovince = 'California' and collectioncode <> 'FH' ";

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
        header("Content-Type: application/csv; charset=utf-8");
        header("Content-Disposition: attachment;Filename=HUH_dwc_california.csv");
        header("Cache-Control: no-cache, must-revalidate"); 
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 
        readfile($tempfilename);
        // remove temp file
        unlink($tempfilename);

}


function rock_feng_collectors_dwc() { 
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

   $query = "select institution, collectioncode, collectionid, catalognumber, catalognumbernumeric, dc_type, basisofrecord, collectornumber, collector, sex, reproductiveStatus, preparations, verbatimdate, eventdate, year, month, day, startdayofyear, enddayofyear, startdatecollected, enddatecollected, habitat, highergeography, continent, country, stateprovince, islandgroup, county, island, municipality, locality, minimumelevationmeters, maximumelevationmeters, verbatimelevation, decimallatitude, decimallongitude, geodeticdatum, identifiedby, dateidentified, identificationqualifier, identificationremarks, identificationreferences, typestatus, scientificname, scientificnameauthorship, family, informationwitheld, datageneralizations, othercatalognumbers from dwc_search where (collector like '%J. F. Rock%' or collector like '%K. M. Feng%') and country = 'China' ";

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
        header("Content-Type: application/csv; charset=utf-8");
        header("Content-Disposition: attachment;Filename=HUH_dwc_rockfeng.csv");
        header("Cache-Control: no-cache, must-revalidate"); 
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 
        readfile($tempfilename);
        // remove temp file
        unlink($tempfilename);

}


function cultivated_dwc() { 
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

   $query = "select institution, collectioncode, collectionid, catalognumber, catalognumbernumeric, dc_type, basisofrecord, collectornumber, collector, sex, reproductiveStatus, preparations, verbatimdate, eventdate, year, month, day, startdayofyear, enddayofyear, startdatecollected, enddatecollected, habitat, highergeography, continent, country, stateprovince, islandgroup, county, island, municipality, locality, minimumelevationmeters, maximumelevationmeters, verbatimelevation, decimallatitude, decimallongitude, geodeticdatum, identifiedby, dateidentified, identificationqualifier, identificationremarks, identificationreferences, typestatus, scientificname, scientificnameauthorship, family, informationwitheld, datageneralizations, othercatalognumbers from dwc_search where ";
$query .= " scientificname like 'Aegilops %'";
$query .= " or scientificname like 'Agropyron %'";
$query .= " or scientificname like 'Allium %'";
$query .= " or scientificname like 'Ananas %'";
$query .= " or scientificname like 'Arachis %'";
$query .= " or scientificname like 'Armoracia %'";
$query .= " or scientificname like 'Artocarpus %'";
$query .= " or scientificname like 'Asparagus %'";
$query .= " or scientificname like 'Avena %'";
$query .= " or scientificname like 'Barbarea %'";
$query .= " or scientificname like 'Bertholletia %'";
$query .= " or scientificname like 'Beta %'";
$query .= " or scientificname like 'Brassica %'";
$query .= " or scientificname like 'Cajanus %'";
$query .= " or scientificname like 'Camellia %'";
$query .= " or scientificname like 'Capsicum %'";
$query .= " or scientificname like 'Carica %'";
$query .= " or scientificname like 'Carthamus %'";
$query .= " or scientificname like 'Chenopodium %'";
$query .= " or scientificname like 'Cicer %'";
$query .= " or scientificname like 'Citrullus %'";
$query .= " or scientificname like 'Citrus %'";
$query .= " or scientificname like 'Cocos %'";
$query .= " or scientificname like 'Coffea %'";
$query .= " or scientificname like 'Colocasia %'";
$query .= " or scientificname like 'Corylus %'";
$query .= " or scientificname like 'Crambe %'";
$query .= " or scientificname like 'Cucumis %'";
$query .= " or scientificname like 'Cucurbita %'";
$query .= " or scientificname like 'Cynara %'";
$query .= " or scientificname like 'Daucus %'";
$query .= " or scientificname like 'Digitaria %'";
$query .= " or scientificname like 'Dioscorea %'";
$query .= " or scientificname like 'Diplotaxis %'";
$query .= " or scientificname like 'Echinochloa %'";
$query .= " or scientificname like 'Elaeis %'";
$query .= " or scientificname like 'Elettaria %'";
$query .= " or scientificname like 'Eleusine %'";
$query .= " or scientificname like 'Elymus %'";
$query .= " or scientificname like 'Ensete %'";
$query .= " or scientificname like 'Eruca %'";
$query .= " or scientificname like 'Ficus %'";
$query .= " or scientificname like 'Fortunella %'";
$query .= " or scientificname like 'Fragaria %'";
$query .= " or scientificname like 'Glycine %'";
$query .= " or scientificname like 'Gossypium %'";
$query .= " or scientificname like 'Helianthus %'";
$query .= " or scientificname like 'Hordeum %'";
$query .= " or scientificname like 'Ilex %'";
$query .= " or scientificname like 'Ipomoea %'";
$query .= " or scientificname like 'Isatis %'";
$query .= " or scientificname like 'Juglans %'";
$query .= " or scientificname like 'Lablab %'";
$query .= " or scientificname like 'Lactuca %'";
$query .= " or scientificname like 'Lathyrus %'";
$query .= " or scientificname like 'Lens %'";
$query .= " or scientificname like 'Lepidium %'";
$query .= " or scientificname like 'Lupinus %'";
$query .= " or scientificname like 'Lycopersicon %'";
$query .= " or scientificname like 'Malus %'";
$query .= " or scientificname like 'Mangifera %'";
$query .= " or scientificname like 'Manihot %'";
$query .= " or scientificname like 'Medicago %'";
$query .= " or scientificname like 'Musa %'";
$query .= " or scientificname like 'Olea %'";
$query .= " or scientificname like 'Oryza %'";
$query .= " or scientificname like 'Panicum %'";
$query .= " or scientificname like 'Pennisetum %'";
$query .= " or scientificname like 'Persea %'";
$query .= " or scientificname like 'Phaseolus %'";
$query .= " or scientificname like 'Phoenix %'";
$query .= " or scientificname like 'Pimenta %'";
$query .= " or scientificname like 'Piper %'";
$query .= " or scientificname like 'Pistacia %'";
$query .= " or scientificname like 'Pisum %'";
$query .= " or scientificname like 'Poncirus %'";
$query .= " or scientificname like 'Potentilla %'";
$query .= " or scientificname like 'Prunus %'";
$query .= " or scientificname like 'Pyrus %'";
$query .= " or scientificname like 'Raphanobrassica %'";
$query .= " or scientificname like 'Raphanus %'";
$query .= " or scientificname like 'Ribes %'";
$query .= " or scientificname like 'Rorippa %'";
$query .= " or scientificname like 'Saccarhum %'";
$query .= " or scientificname like 'Secale %'";
$query .= " or scientificname like 'Sesamum %'";
$query .= " or scientificname like 'Setaria %'";
$query .= " or scientificname like 'Sinapis %'";
$query .= " or scientificname like 'Solanum %'";
$query .= " or scientificname like 'Sorghum %'";
$query .= " or scientificname like 'Spinacia %'";
$query .= " or scientificname like 'Theobroma %'";
$query .= " or scientificname like 'Tripsacum %'";
$query .= " or scientificname like 'Triticosecale %'";
$query .= " or scientificname like 'Triticum %'";
$query .= " or scientificname like 'Vavilovia %'";
$query .= " or scientificname like 'Vicia %'";
$query .= " or scientificname like 'Vigna %'";
$query .= " or scientificname like 'Vitellaria %'";
$query .= " or scientificname like 'Vitis %'";
$query .= " or scientificname like 'Xanthosoma %'";
$query .= " or scientificname like 'Zea %'";
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
        header("Content-Type: application/csv; charset=utf-8 ");
        header("Content-Disposition: attachment;Filename=HUH_dwc_cultivated.csv");
        header("Cache-Control: no-cache, must-revalidate"); 
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 
        readfile($tempfilename);
        // remove temp file
        unlink($tempfilename);

}


function country_geo_dwc($country) { 
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

   $query = "select institution, collectioncode, collectionid, catalognumber, catalognumbernumeric, dc_type, basisofrecord, collectornumber, collector, sex, reproductiveStatus, preparations, verbatimdate, eventdate, year, month, day, startdayofyear, enddayofyear, startdatecollected, enddatecollected, habitat, highergeography, continent, country, stateprovince, islandgroup, county, island, municipality, locality, minimumelevationmeters, maximumelevationmeters, verbatimelevation, decimallatitude, decimallongitude, geodeticdatum, identifiedby, dateidentified, identificationqualifier, identificationremarks, identificationreferences, typestatus, scientificname, scientificnameauthorship, family, informationwitheld, datageneralizations, othercatalognumbers from dwc_search where country = ? and decimallatitude is not null and decimallongitude is not null ";

   if ($debug) { echo "[$query]<BR>"; } 
        $linearray = array ("institution","collectioncode","collectionid","catalognumber","catalognumbernumeric","dc_type","basisofrecord","collectornumber","collector","sex","reproductiveStatus","preparations","verbatimdate","eventdate","year","month","day","startdayofyear","enddayofyear","startdatecollected","enddatecollected","habitat","highergeography","continent","country","stateprovince","islandgroup","county","island","municipality","locality","minimumelevationmeters","maximumelevationmeters","verbatimelevation","decimallatitude","decimallongitude","geodeticdatum","identifiedby","dateidentified","identificationqualifier","identificationremarks","identificationreferences","typestatus","scientificname","scientificnameauthorship","family","informationwitheld","datageneralizations","othercatalognumbers" ) ;
        fputcsv($file,$linearray);
	$statement = $connection->prepare($query);
        $statement->bind_param('s',$country);
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
        header("Content-Type: application/csv; charset=utf-8 ");
        header("Content-Disposition: attachment;Filename=HUH_dwc_country_geo.csv");
        header("Cache-Control: no-cache, must-revalidate"); 
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 
        readfile($tempfilename);
        // remove temp file
        unlink($tempfilename);

}

function genus_dwc($genus) { 
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

   $query = "select institution, collectioncode, collectionid, catalognumber, catalognumbernumeric, dc_type, basisofrecord, collectornumber, collector, sex, reproductiveStatus, preparations, verbatimdate, eventdate, year, month, day, startdayofyear, enddayofyear, startdatecollected, enddatecollected, habitat, highergeography, continent, country, stateprovince, islandgroup, county, island, municipality, locality, minimumelevationmeters, maximumelevationmeters, verbatimelevation, decimallatitude, decimallongitude, geodeticdatum, identifiedby, dateidentified, identificationqualifier, identificationremarks, identificationreferences, typestatus, scientificname, scientificnameauthorship, family, informationwitheld, datageneralizations, othercatalognumbers from dwc_search where scientificname like ? ";

   if ($debug) { echo "[$query]<BR>"; } 
        $linearray = array ("institution","collectioncode","collectionid","catalognumber","catalognumbernumeric","dc_type","basisofrecord","collectornumber","collector","sex","reproductiveStatus","preparations","verbatimdate","eventdate","year","month","day","startdayofyear","enddayofyear","startdatecollected","enddatecollected","habitat","highergeography","continent","country","stateprovince","islandgroup","county","island","municipality","locality","minimumelevationmeters","maximumelevationmeters","verbatimelevation","decimallatitude","decimallongitude","geodeticdatum","identifiedby","dateidentified","identificationqualifier","identificationremarks","identificationreferences","typestatus","scientificname","scientificnameauthorship","family","informationwitheld","datageneralizations","othercatalognumbers" ) ;
        fputcsv($file,$linearray);
	$statement = $connection->prepare($query);
        $genus = $genus . ' %';
        if ($debug) { echo "[$genus]<BR>"; } 
        $statement->bind_param('s',$genus);
	if ($statement) {
		$statement->execute();
                if ($debug) { echo "[$genus]<BR>"; } 
		$statement->bind_result($institution, $collectioncode, $collectionid, $catalognumber, $catalognumbernumeric, $dc_type, $basisofrecord, $collectornumber, $collector, $sex, $reproductiveStatus, $preparations, $verbatimdate, $eventdate, $year, $month, $day, $startdayofyear, $enddayofyear, $startdatecollected, $enddatecollected, $habitat, $highergeography, $continent, $country, $stateprovince, $islandgroup, $county, $island, $municipality, $locality, $minimumelevationmeters, $maximumelevationmeters, $verbatimelevation, $decimallatitude, $decimallongitude, $geodeticdatum, $identifiedby, $dateidentified, $identificationqualifier, $identificationremarks, $identificationreferences, $typestatus, $scientificname, $scientificnameauthorship, $family, $informationwitheld, $datageneralizations, $othercatalognumbers);
		$statement->store_result();
		while ($statement->fetch()) {
	            $linearray = array( "$institution","$collectioncode","$collectionid","$catalognumber","$catalognumbernumeric","$dc_type","$basisofrecord","$collectornumber","$collector","$sex","$reproductiveStatus","$preparations","$verbatimdate","$eventdate","$year","$month","$day","$startdayofyear","$enddayofyear","$startdatecollected","$enddatecollected","$habitat","$highergeography","$continent","$country","$stateprovince","$islandgroup","$county","$island","$municipality","$locality","$minimumelevationmeters","$maximumelevationmeters","$verbatimelevation","$decimallatitude","$decimallongitude","$geodeticdatum","$identifiedby","$dateidentified","$identificationqualifier","$identificationremarks","$identificationreferences","$typestatus","$scientificname","$scientificnameauthorship","$family","$informationwitheld","$datageneralizations","$othercatalognumbers" ) ;
                    fputcsv($file,$linearray);
                } 
	}
        fclose($file);

        // write header and send file to browser
        header("Content-Type: application/csv; charset=utf-8 ");
        header("Content-Disposition: attachment;Filename=HUH_dwc_genus.csv");
        header("Cache-Control: no-cache, must-revalidate"); 
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 
        readfile($tempfilename);
        // remove temp file
        unlink($tempfilename);

}


mysqli_report(MYSQLI_REPORT_OFF);
 
?>
