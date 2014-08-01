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
$country = "China"; 
$barcode = "00267379";
$projectid = null;
$since = null;

if ($_GET['mode']!="")  {
	if ($_GET['mode']=="california_dwc") {
		$mode = "california_dwc"; 
	}
	if ($_GET['mode']=="ames_orchid_list") {
		$mode = "ames_orchid_list"; 
	}
	if ($_GET['mode']=="fungilichens") {
		$mode = "fungilichens"; 
	}
	if ($_GET['mode']=="cultivated_dwc") {
		$mode = "cultivated_dwc"; 
	}
	if ($_GET['mode']=="rock_feng_dwc") {
		$mode = "rock_feng_dwc"; 
	}
	if ($_GET['mode']=="country_geo_dwc") {
		$mode = "country_geo_dwc"; 
		$country = preg_replace("/[^A-Za-z ]/","",$_GET['country']);  
	}
	if ($_GET['mode']=="country_dwc") {
		$mode = "country_dwc"; 
		$country = preg_replace("/[^A-Za-z ]/","",$_GET['country']);  
	}
	if ($_GET['mode']=="genus_dwc") {
		$mode = "genus_dwc"; 
		$genus = preg_replace("/[^A-Za-z]/","",$_GET['genus']);  
	}
	if ($_GET['mode']=="barcode_dwc") {
		$mode = "barcode_dwc"; 
		$barcode = preg_replace("/[^0-9]/","",$_GET['barcode']);  
	}
	if ($_GET['mode']=="project") {
		$mode = "project_dwc"; 
		$projectid = preg_replace("/[^0-9]/","",$_GET['projectid']);  
	}
	if ($_GET['mode']=="imagefeed") {
		$mode = "imagefeed"; 
	}

        // Limit the search to records last updated more recently than the 
        // date provided.
        if ($_GET['since']!="")  {
		$since = preg_replace("/[^0-9\-]/","",$_GET['since']);  
        }

} 
if ($_POST['mode']!="")  {
	if ($_POST['mode']=="specimens_dwc") {
		$mode = "specimens_dwc"; 
	}
}
	

  
	if ($connection) {
		if ($debug) {  echo "[$mode]"; } 
		
		switch ($mode) {
		    case "specimens_dwc":
		        echo specimens_dwc();
		        break;
		    case "california_dwc":
		        echo california_dwc();
		        break;
		    case "ames_orchid_list":
		        echo ames_orchid_list();
		        break;
		    case "fungilichens":
		        echo fungilichens();
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
		    case "barcode_dwc":
		        echo barcode_dwc($barcode);
		        break;
		    case "country_geo_dwc":
                        $geoonly = TRUE;
		        echo country_geo_dwc($country,$geoonly);
		        break;
		    case "country_dwc":
                        $geoonly = FALSE;
		        echo country_geo_dwc($country,$geoonly);
		        break;
		    case "project_dwc":
		        echo project_dwc($projectid);
		        break;
		    case "imagefeed":
		        echo image_feed();
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
   $returnvalue .= "<h2>Download data</h2>";
   $returnvalue .= "<ul>";
   $returnvalue .= "<li><a href='http://firuta.huh.harvard.edu/ipt/resource.do?r=harvard_university_herbaria'>DarwinCore Archive of all HUH specimen records</a> via IPT</li>";
   $returnvalue .= "<li><a href='http://webprojects.huh.harvard.edu/authority_files/'>Botanist Authority Files</a></li>";
   $returnvalue .= "</ul>";
   $returnvalue .= "<h2>Generate data dumps</h2>";
   $returnvalue .= "<ul>";
   $returnvalue .= "<li><a href='dumps.php?mode=fungilichens'>DarwinCore records for Fungi and Lichens in FH (csv file)</a> optional parameter <a href='dumps.php?mode=fungilichens&since=2012-10-19'>&since=2012-10-19</a> limits to records modified after the date provided.</li>";
   $returnvalue .= "<li><a href='dumps.php?mode=california_dwc'>DarwinCore records for Specimens in California, excluding FH (csv file)</a></li>";
   $returnvalue .= "<li><a href='dumps.php?mode=cultivated_dwc'>DarwinCore records for Cultivated genera, (csv file)</a></li>";
   $returnvalue .= "<li><a href='dumps.php?mode=rock_feng_dwc'>DarwinCore records for China collected by Rock or Feng, (csv file)</a></li>";
   $returnvalue .= "<li><a href='dumps.php?mode=country_geo_dwc&country=China'>DarwinCore records for China with georeferences, (csv file)</a> required parameter &country= selects country.</li>";
   $returnvalue .= "<li><a href='dumps.php?mode=country_dwc&country=China&typestatus=Any'>DarwinCore records of Types from China, (csv file)</a> required parameter &country= selects country, optional parameter &typestatus=Any limits to types (and can limit to a particular type status e.g. <a href='dumps.php?mode=country_dwc&country=China&typestatus=Holotype'>&typestatus=Holotype</a>)</li>";
   $returnvalue .= "<li><a href='dumps.php?mode=genus_dwc&genus=Carex'>Darwin core records for Carex, (csv file)</a></li>";
   $returnvalue .= "<li><a href='dumps.php?mode=barcode_dwc&barcode=00267379'>Darwin core record for a barcode (csv file)</a></li>";
   $returnvalue .= "<li><a href='dumps.php?mode=ames_orchid_list'>List of AMES and other orchid specimens (csv file)</a></li>";
   $returnvalue .= "<li><a href='dumps.php?mode=project&projectid=4'>List of Metropolitan Flora Project Material (csv file)</a></li>";
   $returnvalue .= "<li><a href='dumps.php?mode=imagefeed'>NEVP Image Feed (csv file)</a></li>";
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

   $query = "select institution, collectioncode, collectionid, catalognumber, catalognumbernumeric, dc_type, basisofrecord, collectornumber, collector, sex, reproductiveStatus, preparations, verbatimdate, eventdate, year, month, day, startdayofyear, enddayofyear, startdatecollected, enddatecollected, habitat, highergeography, continent, country, stateprovince, islandgroup, county, island, municipality, locality, minimumelevationmeters, maximumelevationmeters, verbatimelevation, decimallatitude, decimallongitude, geodeticdatum, identifiedby, dateidentified, identificationqualifier, identificationremarks, identificationreferences, typestatus, scientificname, scientificnameauthorship, family, informationwitheld, datageneralizations, othercatalognumbers, timestamplastupdated from dwc_search where stateprovince = 'California' and collectioncode <> 'FH' ";

   if ($debug) { echo "[$query]<BR>"; } 
        $linearray = array ("institution","collectioncode","collectionid","catalognumber","catalognumbernumeric","dc_type","basisofrecord","collectornumber","collector","sex","reproductiveStatus","preparations","verbatimdate","eventdate","year","month","day","startdayofyear","enddayofyear","startdatecollected","enddatecollected","habitat","highergeography","continent","country","stateprovince","islandgroup","county","island","municipality","locality","minimumelevationmeters","maximumelevationmeters","verbatimelevation","decimallatitude","decimallongitude","geodeticdatum","identifiedby","dateidentified","identificationqualifier","identificationremarks","identificationreferences","typestatus","scientificname","scientificnameauthorship","family","informationwitheld","datageneralizations","othercatalognumbers","datelastupdated" ) ;
        fputcsv($file,$linearray);
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($institution, $collectioncode, $collectionid, $catalognumber, $catalognumbernumeric, $dc_type, $basisofrecord, $collectornumber, $collector, $sex, $reproductiveStatus, $preparations, $verbatimdate, $eventdate, $year, $month, $day, $startdayofyear, $enddayofyear, $startdatecollected, $enddatecollected, $habitat, $highergeography, $continent, $country, $stateprovince, $islandgroup, $county, $island, $municipality, $locality, $minimumelevationmeters, $maximumelevationmeters, $verbatimelevation, $decimallatitude, $decimallongitude, $geodeticdatum, $identifiedby, $dateidentified, $identificationqualifier, $identificationremarks, $identificationreferences, $typestatus, $scientificname, $scientificnameauthorship, $family, $informationwitheld, $datageneralizations, $othercatalognumbers, $datelastupdated);
		$statement->store_result();
		while ($statement->fetch()) {
	            $linearray = array( "$institution","$collectioncode","$collectionid","$catalognumber","$catalognumbernumeric","$dc_type","$basisofrecord","$collectornumber","$collector","$sex","$reproductiveStatus","$preparations","$verbatimdate","$eventdate","$year","$month","$day","$startdayofyear","$enddayofyear","$startdatecollected","$enddatecollected","$habitat","$highergeography","$continent","$country","$stateprovince","$islandgroup","$county","$island","$municipality","$locality","$minimumelevationmeters","$maximumelevationmeters","$verbatimelevation","$decimallatitude","$decimallongitude","$geodeticdatum","$identifiedby","$dateidentified","$identificationqualifier","$identificationremarks","$identificationreferences","$typestatus","$scientificname","$scientificnameauthorship","$family","$informationwitheld","$datageneralizations","$othercatalognumbers","$datelastupdated") ;
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


function country_geo_dwc($country,$geoonly) { 
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
   $wherebit = "";
   if ($geoonly===TRUE) { 
      $wherebit = " and decimallatitude is not null and decimallongitude is not null ";
   } 
   $typestatus = trim(preg_replace("/[^A-Za-z ]/","",$_GET['typestatus']));  
   if ($typestatus!="") { 
     switch ($typestatus) { 
         case "Drawing of type": $wherebit=" and typestatus = 'Drawing of type' "; break; 
         case "Epitype": $wherebit=" and typestatus = 'Epitype' "; break; 
         case "Holotype": $wherebit=" and typestatus = 'Holotype' "; break; 
         case "Isoepitype": $wherebit=" and typestatus = 'Isoepitype' "; break; 
         case "Isolectotype": $wherebit=" and typestatus = 'Isolectotype' "; break; 
         case "Isoneotype": $wherebit=" and typestatus = 'Isoneotype' "; break; 
         case "Isosyntype": $wherebit=" and typestatus = 'Isosyntype' "; break; 
         case "Isotype": $wherebit=" and typestatus = 'Isotype' "; break; 
         case "Lectotype": $wherebit=" and typestatus = 'Lectotype' "; break; 
         case "Neotype": $wherebit=" and typestatus = 'Neotype' "; break; 
         case "Not a type": $wherebit=" and typestatus = 'Not a type' "; break; 
         case "Photograph of type": $wherebit=" and typestatus = 'Photograph of type' "; break; 
         case "Syntype": $wherebit=" and typestatus = 'Syntype' "; break; 
         case "Type": $wherebit=" and typestatus = 'Type' "; break; 
         case "Type material": $wherebit=" and typestatus = 'Type material' "; break; 
         case "[Neosyntype]": $wherebit=" and typestatus = '[Neosyntype]' "; break;
         default:
           $wherebit = " and typestatus is not null ";
      }


   }

   $query = "select institution, collectioncode, collectionid, catalognumber, catalognumbernumeric, dc_type, basisofrecord, collectornumber, collector, sex, reproductiveStatus, preparations, verbatimdate, eventdate, year, month, day, startdayofyear, enddayofyear, startdatecollected, enddatecollected, habitat, highergeography, continent, country, stateprovince, islandgroup, county, island, municipality, locality, minimumelevationmeters, maximumelevationmeters, verbatimelevation, decimallatitude, decimallongitude, geodeticdatum, identifiedby, dateidentified, identificationqualifier, identificationremarks, identificationreferences, typestatus, scientificname, scientificnameauthorship, family, informationwitheld, datageneralizations, othercatalognumbers from dwc_search where country = ? $wherebit ";

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

   $query = "select fragmentguid, institution, collectioncode, collectionid, catalognumber, catalognumbernumeric, dc_type, basisofrecord, collectornumber, collector, sex, reproductiveStatus, preparations, verbatimdate, eventdate, year, month, day, startdayofyear, enddayofyear, startdatecollected, enddatecollected, habitat, highergeography, continent, country, stateprovince, islandgroup, county, island, municipality, locality, minimumelevationmeters, maximumelevationmeters, verbatimelevation, decimallatitude, decimallongitude, geodeticdatum, identifiedby, dateidentified, identificationqualifier, identificationremarks, identificationreferences, typestatus, scientificname, scientificnameauthorship, family, informationwitheld, datageneralizations, othercatalognumbers from dwc_search where scientificname like ? ";

   if ($debug) { echo "[$query]<BR>"; } 
        $linearray = array ("occurrenceID","institution","collectioncode","collectionid","catalognumber","catalognumbernumeric","dc_type","basisofrecord","collectornumber","collector","sex","reproductiveStatus","preparations","verbatimdate","eventdate","year","month","day","startdayofyear","enddayofyear","startdatecollected","enddatecollected","habitat","highergeography","continent","country","stateprovince","islandgroup","county","island","municipality","locality","minimumelevationmeters","maximumelevationmeters","verbatimelevation","decimallatitude","decimallongitude","geodeticdatum","identifiedby","dateidentified","identificationqualifier","identificationremarks","identificationreferences","typestatus","scientificname","scientificnameauthorship","family","informationwitheld","datageneralizations","othercatalognumbers" ) ;
        fputcsv($file,$linearray);
	$statement = $connection->prepare($query);
        $genus = $genus . ' %';
        if ($debug) { echo "[$genus]<BR>"; } 
        $statement->bind_param('s',$genus);
	if ($statement) {
		$statement->execute();
                if ($debug) { echo "[$genus]<BR>"; } 
		$statement->bind_result($fragmentguid,$institution, $collectioncode, $collectionid, $catalognumber, $catalognumbernumeric, $dc_type, $basisofrecord, $collectornumber, $collector, $sex, $reproductiveStatus, $preparations, $verbatimdate, $eventdate, $year, $month, $day, $startdayofyear, $enddayofyear, $startdatecollected, $enddatecollected, $habitat, $highergeography, $continent, $country, $stateprovince, $islandgroup, $county, $island, $municipality, $locality, $minimumelevationmeters, $maximumelevationmeters, $verbatimelevation, $decimallatitude, $decimallongitude, $geodeticdatum, $identifiedby, $dateidentified, $identificationqualifier, $identificationremarks, $identificationreferences, $typestatus, $scientificname, $scientificnameauthorship, $family, $informationwitheld, $datageneralizations, $othercatalognumbers);
		$statement->store_result();
		while ($statement->fetch()) {
	            $linearray = array("$fragmentguid","$institution","$collectioncode","$collectionid","$catalognumber","$catalognumbernumeric","$dc_type","$basisofrecord","$collectornumber","$collector","$sex","$reproductiveStatus","$preparations","$verbatimdate","$eventdate","$year","$month","$day","$startdayofyear","$enddayofyear","$startdatecollected","$enddatecollected","$habitat","$highergeography","$continent","$country","$stateprovince","$islandgroup","$county","$island","$municipality","$locality","$minimumelevationmeters","$maximumelevationmeters","$verbatimelevation","$decimallatitude","$decimallongitude","$geodeticdatum","$identifiedby","$dateidentified","$identificationqualifier","$identificationremarks","$identificationreferences","$typestatus","$scientificname","$scientificnameauthorship","$family","$informationwitheld","$datageneralizations","$othercatalognumbers" ) ;
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


function image_feed($since = null) { 
	global $connection,$debug;

        // width of thumbnail to produce
        $thumbwidth = 250;

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

   $query = "select uri, collectioncode, catalognumber, scientificname, highergeography, locality, collectornumber, collector, eventdate from IMAGE_SET s left join IMAGE_OBJECT o on s.id = o.image_set_id left join IMAGE_SET_collectionobject isc on s.ID = isc.imagesetid left join IMAGE_BATCH b on s.batch_id = b.id left join dwc_search d on isc.collectionobjectid = d.collectionobjectid  where project = 'NEVP TCN' and (barcodes is null or barcodes not like '%;%') and object_type_id = 4 and collectioncode is not null ";
   if ($since!=null) { 
      // $query .= " and timestamplastupdated > ? ";
   }
   if ($debug) { echo "[$query]<BR>"; } 
        $linearray = array ("iPlantGUID","ImageURI","ThumbnailURI","collectionCode","catalogNumber","scientificName","higherGeography","locality","collectorNumber","collector","eventDate");
        fputcsv($file,$linearray);
	$statement = $connection->prepare($query);
        $genus = $genus . ' %';
	if ($statement) {
		$statement->execute();
		$statement->bind_result($imageuri, $collectioncode, $catalognumber, $scientificname, $highergeography, $locality, $collectornumber, $collector, $eventdate);
		$statement->store_result();
		while ($statement->fetch()) {
                    $iplantguid = str_replace('http://bovary.iplantcollaborative.org/image_service/image/','',$imageuri);
                    $iplantguid = str_replace('?rotate=guess&format=jpeg,quality,100','',$iplantguid);
                    $thumburi = str_replace('?rotate=guess&format=jpeg,quality,100',"?rotate=guess&resize=$thumbwidth&format=jpeg,quality,100",$imageuri);
	            $linearray = array("$iplantguid","$imageuri","$thumburi","$collectioncode","$catalognumber","$scientificname","$highergeography","$locality","$collectornumber","$collector","$eventdate");
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

function project_dwc($projectid) { 
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

   $query = "select distinct fragmentguid, institution, collectioncode, collectionid, catalognumber, catalognumbernumeric, dc_type, basisofrecord, collectornumber, collector, sex, reproductiveStatus, preparations, verbatimdate, eventdate, year, month, day, startdayofyear, enddayofyear, startdatecollected, enddatecollected, habitat, highergeography, continent, country, stateprovince, islandgroup, county, island, municipality, locality, minimumelevationmeters, maximumelevationmeters, verbatimelevation, decimallatitude, decimallongitude, geodeticdatum, identifiedby, dateidentified, identificationqualifier, identificationremarks, identificationreferences, typestatus, scientificname, scientificnameauthorship, family, informationwitheld, datageneralizations, othercatalognumbers from dwc_search left join project_colobj on dwc_search.collectionobjectid = project_colobj.collectionobjectid where project_colobj.projectid = ?  ";

   if ($debug) { echo "[$query]<BR>"; } 
        $linearray = array ("occurrenceID","institution","collectioncode","collectionid","catalognumber","catalognumbernumeric","dc_type","basisofrecord","collectornumber","collector","sex","reproductiveStatus","preparations","verbatimdate","eventdate","year","month","day","startdayofyear","enddayofyear","startdatecollected","enddatecollected","habitat","highergeography","continent","country","stateprovince","islandgroup","county","island","municipality","locality","minimumelevationmeters","maximumelevationmeters","verbatimelevation","decimallatitude","decimallongitude","geodeticdatum","identifiedby","dateidentified","identificationqualifier","identificationremarks","identificationreferences","typestatus","scientificname","scientificnameauthorship","family","informationwitheld","datageneralizations","othercatalognumbers" ) ;
        fputcsv($file,$linearray);
	$statement = $connection->prepare($query);
        $genus = $genus . ' %';
        if ($debug) { echo "[$projectid]<BR>"; } 
        $statement->bind_param('i',$projectid);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($fragmentguid,$institution, $collectioncode, $collectionid, $catalognumber, $catalognumbernumeric, $dc_type, $basisofrecord, $collectornumber, $collector, $sex, $reproductiveStatus, $preparations, $verbatimdate, $eventdate, $year, $month, $day, $startdayofyear, $enddayofyear, $startdatecollected, $enddatecollected, $habitat, $highergeography, $continent, $country, $stateprovince, $islandgroup, $county, $island, $municipality, $locality, $minimumelevationmeters, $maximumelevationmeters, $verbatimelevation, $decimallatitude, $decimallongitude, $geodeticdatum, $identifiedby, $dateidentified, $identificationqualifier, $identificationremarks, $identificationreferences, $typestatus, $scientificname, $scientificnameauthorship, $family, $informationwitheld, $datageneralizations, $othercatalognumbers);
		$statement->store_result();
		while ($statement->fetch()) {
	            $linearray = array("$fragmentguid","$institution","$collectioncode","$collectionid","$catalognumber","$catalognumbernumeric","$dc_type","$basisofrecord","$collectornumber","$collector","$sex","$reproductiveStatus","$preparations","$verbatimdate","$eventdate","$year","$month","$day","$startdayofyear","$enddayofyear","$startdatecollected","$enddatecollected","$habitat","$highergeography","$continent","$country","$stateprovince","$islandgroup","$county","$island","$municipality","$locality","$minimumelevationmeters","$maximumelevationmeters","$verbatimelevation","$decimallatitude","$decimallongitude","$geodeticdatum","$identifiedby","$dateidentified","$identificationqualifier","$identificationremarks","$identificationreferences","$typestatus","$scientificname","$scientificnameauthorship","$family","$informationwitheld","$datageneralizations","$othercatalognumbers" ) ;
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


function barcode_dwc($barcode) { 
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

   $query = "select fragmentguid, institution, collectioncode, collectionid, catalognumber, catalognumbernumeric, dc_type, basisofrecord, collectornumber, collector, sex, reproductiveStatus, preparations, verbatimdate, eventdate, year, month, day, startdayofyear, enddayofyear, startdatecollected, enddatecollected, habitat, highergeography, continent, country, stateprovince, islandgroup, county, island, municipality, locality, minimumelevationmeters, maximumelevationmeters, verbatimelevation, decimallatitude, decimallongitude, geodeticdatum, identifiedby, dateidentified, identificationqualifier, identificationremarks, identificationreferences, typestatus, scientificname, scientificnameauthorship, family, informationwitheld, datageneralizations, othercatalognumbers from dwc_search where catalognumber like ? ";

   if ($debug) { echo "[$query]<BR>"; } 
        $linearray = array ("occurrenceID","institution","collectioncode","collectionid","catalognumber","catalognumbernumeric","dc_type","basisofrecord","collectornumber","collector","sex","reproductiveStatus","preparations","verbatimdate","eventdate","year","month","day","startdayofyear","enddayofyear","startdatecollected","enddatecollected","habitat","highergeography","continent","country","stateprovince","islandgroup","county","island","municipality","locality","minimumelevationmeters","maximumelevationmeters","verbatimelevation","decimallatitude","decimallongitude","geodeticdatum","identifiedby","dateidentified","identificationqualifier","identificationremarks","identificationreferences","typestatus","scientificname","scientificnameauthorship","family","informationwitheld","datageneralizations","othercatalognumbers" ) ;
        fputcsv($file,$linearray);
	$statement = $connection->prepare($query);
        $barcode = '%'.$barcode;
        if ($debug) { echo "[$genus]<BR>"; } 
        $statement->bind_param('s',$barcode);
	if ($statement) {
		$statement->execute();
                if ($debug) { echo "[$genus]<BR>"; } 
		$statement->bind_result($fragmentguid,$institution, $collectioncode, $collectionid, $catalognumber, $catalognumbernumeric, $dc_type, $basisofrecord, $collectornumber, $collector, $sex, $reproductiveStatus, $preparations, $verbatimdate, $eventdate, $year, $month, $day, $startdayofyear, $enddayofyear, $startdatecollected, $enddatecollected, $habitat, $highergeography, $continent, $country, $stateprovince, $islandgroup, $county, $island, $municipality, $locality, $minimumelevationmeters, $maximumelevationmeters, $verbatimelevation, $decimallatitude, $decimallongitude, $geodeticdatum, $identifiedby, $dateidentified, $identificationqualifier, $identificationremarks, $identificationreferences, $typestatus, $scientificname, $scientificnameauthorship, $family, $informationwitheld, $datageneralizations, $othercatalognumbers);
		$statement->store_result();
		while ($statement->fetch()) {
	            $linearray = array("$fragmentguid","$institution","$collectioncode","$collectionid","$catalognumber","$catalognumbernumeric","$dc_type","$basisofrecord","$collectornumber","$collector","$sex","$reproductiveStatus","$preparations","$verbatimdate","$eventdate","$year","$month","$day","$startdayofyear","$enddayofyear","$startdatecollected","$enddatecollected","$habitat","$highergeography","$continent","$country","$stateprovince","$islandgroup","$county","$island","$municipality","$locality","$minimumelevationmeters","$maximumelevationmeters","$verbatimelevation","$decimallatitude","$decimallongitude","$geodeticdatum","$identifiedby","$dateidentified","$identificationqualifier","$identificationremarks","$identificationreferences","$typestatus","$scientificname","$scientificnameauthorship","$family","$informationwitheld","$datageneralizations","$othercatalognumbers" ) ;
                    fputcsv($file,$linearray);
                } 
	}
        fclose($file);

        // write header and send file to browser
        header("Content-Type: application/csv; charset=utf-8 ");
        header("Content-Disposition: attachment;Filename=HUH_dwc_barcode.csv");
        header("Cache-Control: no-cache, must-revalidate"); 
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 
        readfile($tempfilename);
        // remove temp file
        unlink($tempfilename);

}
function fungilichens() { 
global $connection,$debug, $since;
    
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
   $collectionobjectids = "";

	$id = preg_replace("[^0-9,]","",$_POST['id']);
	if ($id!="") { 
		if (is_array($id)) { 
			$ids = $id;
		} else { 
			$ids[0] = $id;
		}
	}
   $comma = "";
   for ($i=0;$i<count($ids);$i++) { 
      $collectionobjectids .= "$comma$id";
      $comma = ",";
   }
   if ($since!=null && strlen($since)>0 and strlen($since)<11) { 
      $since = " and timestamplastupdated > '$since' ";
   } else { 
      $since = "";
   }

   $query = "select fragmentguid, institution, collectioncode, collectionid, catalognumber, catalognumbernumeric, dc_type, basisofrecord, collectornumber, collector, sex, reproductiveStatus, preparations, verbatimdate, eventdate, year, month, day, startdayofyear, enddayofyear, startdatecollected, enddatecollected, habitat, highergeography, continent, country, stateprovince, islandgroup, county, island, municipality, locality, minimumelevationmeters, maximumelevationmeters, verbatimelevation, decimallatitude, decimallongitude, geodeticdatum, identifiedby, dateidentified, identificationqualifier, identificationremarks, identificationreferences, typestatus, scientificname, scientificnameauthorship, family, informationwitheld, datageneralizations, othercatalognumbers, timestamplastupdated from dwc_search left join determination on temp_determinationid = determinationid left join taxon on determination.taxonid = taxon.taxonid where taxon.groupnumber = 'FungiLichens' and dwc_search.collectioncode = 'FH' $since ";

   if ($debug) { echo "[$query]<BR>"; } 
        $linearray = array ("occurrenceID","institutionCode","collectioncode","collectionid","catalognumber","catalognumbernumeric","dc_type","basisofrecord","collectornumber","collector","sex","reproductiveStatus","preparations","verbatimdate","eventdate","year","month","day","startdayofyear","enddayofyear","startdatecollected","enddatecollected","habitat","highergeography","continent","country","stateprovince","islandgroup","county","island","municipality","locality","minimumelevationmeters","maximumelevationmeters","verbatimelevation","decimallatitude","decimallongitude","geodeticdatum","identifiedby","dateidentified","identificationqualifier","identificationremarks","identificationreferences","typestatus","scientificname","scientificnameauthorship","family","informationwitheld","datageneralizations","othercatalognumbers","datelastupdated","rightsHolder","accessRights" ) ;
        fputcsv($file,$linearray);
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($fragmentguid, $institution, $collectioncode, $collectionid, $catalognumber, $catalognumbernumeric, $dc_type, $basisofrecord, $collectornumber, $collector, $sex, $reproductiveStatus, $preparations, $verbatimdate, $eventdate, $year, $month, $day, $startdayofyear, $enddayofyear, $startdatecollected, $enddatecollected, $habitat, $highergeography, $continent, $country, $stateprovince, $islandgroup, $county, $island, $municipality, $locality, $minimumelevationmeters, $maximumelevationmeters, $verbatimelevation, $decimallatitude, $decimallongitude, $geodeticdatum, $identifiedby, $dateidentified, $identificationqualifier, $identificationremarks, $identificationreferences, $typestatus, $scientificname, $scientificnameauthorship, $family, $informationwitheld, $datageneralizations, $othercatalognumbers, $datelastupdated);
		$statement->store_result();
		while ($statement->fetch()) {
	            $linearray = array( "$fragmentguid", "$institution","$collectioncode","$collectionid","$catalognumber","$catalognumbernumeric","$dc_type","$basisofrecord","$collectornumber","$collector","$sex","$reproductiveStatus","$preparations","$verbatimdate","$eventdate","$year","$month","$day","$startdayofyear","$enddayofyear","$startdatecollected","$enddatecollected","$habitat","$highergeography","$continent","$country","$stateprovince","$islandgroup","$county","$island","$municipality","$locality","$minimumelevationmeters","$maximumelevationmeters","$verbatimelevation","$decimallatitude","$decimallongitude","$geodeticdatum","$identifiedby","$dateidentified","$identificationqualifier","$identificationremarks","$identificationreferences","$typestatus","$scientificname","$scientificnameauthorship","$family","$informationwitheld","$datageneralizations","$othercatalognumbers","$datelastupdated","President and Fellows of Harvard College","http://kiki.huh.harvard.edu/databases/addenda.html#policy") ;
                    fputcsv($file,$linearray);
                } 
	}
        fclose($file);

        // write header and send file to browser
        header("Content-Type: application/csv; charset=utf-8");
        header("Content-Disposition: attachment;Filename=HUH_fungal_report.csv");
        header("Cache-Control: no-cache, must-revalidate"); 
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 
        readfile($tempfilename);
        // remove temp file
        unlink($tempfilename);


}

function ames_orchid_list() { 
global $connection,$debug, $since;
    
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
   $collectionobjectids = "";

	$id = preg_replace("[^0-9,]","",$_POST['id']);
	if ($id!="") { 
		if (is_array($id)) { 
			$ids = $id;
		} else { 
			$ids[0] = $id;
		}
	}
   $comma = "";
   for ($i=0;$i<count($ids);$i++) { 
      $collectionobjectids .= "$comma$id";
      $comma = ",";
   }
   if ($since!=null && strlen($since)>0 and strlen($since)<11) { 
      $since = " and timestamplastupdated > '$since' ";
   } else { 
      $since = "";
   }

   $query = "select  scientificname, scientificnameauthorship, collector, collectornumber, collectioncode, othercatalognumbers, catalognumber, typestatus from 
dwc_search where ( dwc_search.collectioncode = 'AMES' or family = 'Orchidaceae' ) $since order by collectioncode, catalognumber";

   if ($debug) { echo "[$query]<BR>"; } 
        $linearray = array ("scientificname","authorship","collector","collectornumber","herbarium","othernumbers","barcode","typestatus");
        fputcsv($file,$linearray);
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($scientificname, $scientificnameauthorship, $collector, $collectornumber, $collectioncode, $othercatalognumbers, $barcode, $typestatus);
		$statement->store_result();
		while ($statement->fetch()) {
	            $linearray = array( "$scientificname","$scientificnameauthorship","$collector","$collectornumber","$collectioncode","$othercatalognumbers","$barcode","$typestatus") ;
                    fputcsv($file,$linearray);
                } 
	}
        fclose($file);
if (!$debug) { 
        // write header and send file to browser
        header("Content-Type: application/csv; charset=utf-8");
        header("Content-Disposition: attachment;Filename=HUH_AMES_report.csv");
        header("Cache-Control: no-cache, must-revalidate"); 
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 
}
        readfile($tempfilename);
        // remove temp file
        unlink($tempfilename);

} 


function specimens_dwc() { 
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
   $collectionobjectids = "";

	$id = preg_replace("[^0-9,]","",$_POST['id']);
	if ($id!="") { 
		if (is_array($id)) { 
			$ids = $id;
		} else { 
			$ids[0] = $id;
		}
	}
   $comma = "";
   for ($i=0;$i<count($ids);$i++) { 
      $collectionobjectids .= "$comma$id";
      $comma = ",";
   }


   $query = "select fragmentguid, institution, collectioncode, collectionid, catalognumber, catalognumbernumeric, dc_type, basisofrecord, collectornumber, collector, sex, reproductiveStatus, preparations, verbatimdate, eventdate, year, month, day, startdayofyear, enddayofyear, startdatecollected, enddatecollected, habitat, highergeography, continent, country, stateprovince, islandgroup, county, island, municipality, locality, minimumelevationmeters, maximumelevationmeters, verbatimelevation, decimallatitude, decimallongitude, geodeticdatum, identifiedby, dateidentified, identificationqualifier, identificationremarks, identificationreferences, typestatus, scientificname, scientificnameauthorship, family, informationwitheld, datageneralizations, othercatalognumbers, timestamplastupdated from dwc_search where collectionobjectid in ($collectionobjectids) ";

   if ($debug) { echo "[$query]<BR>"; } 
        $linearray = array ("occurrenceID","institutionCode","collectioncode","collectionid","catalognumber","catalognumbernumeric","dc_type","basisofrecord","collectornumber","collector","sex","reproductiveStatus","preparations","verbatimdate","eventdate","year","month","day","startdayofyear","enddayofyear","startdatecollected","enddatecollected","habitat","highergeography","continent","country","stateprovince","islandgroup","county","island","municipality","locality","minimumelevationmeters","maximumelevationmeters","verbatimelevation","decimallatitude","decimallongitude","geodeticdatum","identifiedby","dateidentified","identificationqualifier","identificationremarks","identificationreferences","typestatus","scientificname","scientificnameauthorship","family","informationwitheld","datageneralizations","othercatalognumbers","datelastupdated" ) ;
        fputcsv($file,$linearray);
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($fragmentguid, $institution, $collectioncode, $collectionid, $catalognumber, $catalognumbernumeric, $dc_type, $basisofrecord, $collectornumber, $collector, $sex, $reproductiveStatus, $preparations, $verbatimdate, $eventdate, $year, $month, $day, $startdayofyear, $enddayofyear, $startdatecollected, $enddatecollected, $habitat, $highergeography, $continent, $country, $stateprovince, $islandgroup, $county, $island, $municipality, $locality, $minimumelevationmeters, $maximumelevationmeters, $verbatimelevation, $decimallatitude, $decimallongitude, $geodeticdatum, $identifiedby, $dateidentified, $identificationqualifier, $identificationremarks, $identificationreferences, $typestatus, $scientificname, $scientificnameauthorship, $family, $informationwitheld, $datageneralizations, $othercatalognumbers, $datelastupdated);
		$statement->store_result();
		while ($statement->fetch()) {
	            $linearray = array( "$fragmentguid","$institution","$collectioncode","$collectionid","$catalognumber","$catalognumbernumeric","$dc_type","$basisofrecord","$collectornumber","$collector","$sex","$reproductiveStatus","$preparations","$verbatimdate","$eventdate","$year","$month","$day","$startdayofyear","$enddayofyear","$startdatecollected","$enddatecollected","$habitat","$highergeography","$continent","$country","$stateprovince","$islandgroup","$county","$island","$municipality","$locality","$minimumelevationmeters","$maximumelevationmeters","$verbatimelevation","$decimallatitude","$decimallongitude","$geodeticdatum","$identifiedby","$dateidentified","$identificationqualifier","$identificationremarks","$identificationreferences","$typestatus","$scientificname","$scientificnameauthorship","$family","$informationwitheld","$datageneralizations","$othercatalognumbers","$datelastupdated") ;
                    fputcsv($file,$linearray);
                } 
	}
        fclose($file);

        // write header and send file to browser
        header("Content-Type: application/csv; charset=utf-8");
        header("Content-Disposition: attachment;Filename=HUH_specimenreport.csv");
        header("Cache-Control: no-cache, must-revalidate"); 
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 
        readfile($tempfilename);
        // remove temp file
        unlink($tempfilename);

}

mysqli_report(MYSQLI_REPORT_OFF);
 
?>
