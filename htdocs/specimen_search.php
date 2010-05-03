<?php
/*
 * Created on Dec 3, 2009
 *
 */
$debug=true;

include_once('connection_library.php');

$connection = specify_connect();
$errormessage = "";

$mode = "search";



function barcode_to_catalog_number($aBarcode) {
  $LOCALLENGTH = 9;    // HUH Barcode is a zero padded string of length 9 
  $returnvalue = $aBarcode;
  if (strlen($returnvalue) < $LOCALLENGTH) { 
     $returnvalue = str_pad($returnvalue, $LOCALLENGTH, "0", STR_PAD_LEFT);
  }
  return $returnvalue;
}


if ($_GET['browsemode']!="")  {
   if ($_GET['browsemode']=="families") {
   	   $mode = "browse_families"; 
   }
   if ($_GET['browsemode']=="countries") {
   	   $mode = "browse_countries"; 
   }
}

if ($_GET['mode']!="")  {
   if ($_GET['mode']=="details") {
   	   $mode = "details"; 
   }
}

echo pageheader($mode); 

if ($connection) { 
	
	
	switch ($mode) {
		
	   case "browse_families":
	      browse("families");
	   break;

	   case "browse_countries":
	      browse("countries");
	   break;
		 
	   case "details":
	      details();
	   break;
	   
	   case "search":
              search();	   
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

echo pagefooter();
 
// ******* main code block ends here, supporting functions follow. *****


/** given some date and some precision where precision is 0,1,2,or 3, 
**  this will return the date as a string truncated to the appropriate precision
**  @date = dash separated date year - month - day
**  @precision = 0,1,2,3 where 0 = none, 1 = full, 2 = month, 3 = year
**  @return = date as a truncated text string
**/
function transformDate($date, $precision) { 
   if ($precision <= 0 || $precision > 3)
      return "";

   if (!preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $date))
     return "";

  if ($precision == 1)
     return $date;
  
  $parts = explode("-",$date);
  
  if ($precision == 2)
    return $parts[0]."-".$parts[1];

  if ($precision == 3)
    return $parts[0];
  
  return "";
}


/** given some date and some precision where precision is 0,1,2,or 3, 
**  this will return the date as a string truncated to the appropriate precision
**  in the text form  99, Month 9999  day, month year
**  @date = dash separated date year - month - day
**  @precision = 0,1,2,3 where 0 = none, 1 = full, 2 = month, 3 = year
**  @return = date as a truncated text string 
**/
function transformDateText($date, $precision) { 
   if ($precision <= 0 || $precision > 3)
      return "";

   if (!preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $date))
     return "";

  if ($precision == 1)
     return date("d, F Y",strtotime($date));
  
  if ($precision == 2)
     return date("F Y",strtotime($date));

  if ($precision == 3)
     return date("Y",strtotime($date));
  
  return "";
}
 
function browse($target = "families") { 
global $connection, $errormessage;

$FAMILYRANKID = 140;  // Magic Value. Check taxontreedefitem to make sure that the RankID for Name=Family is 
                      // 140 in your specify instance.  This is the default, and is recommended, 
                      // but could differ if you imported your own taxon tree.  
$COUNTRYRANKID = 200; // Magic Value.  geography.rankid = 200 is the default for countries in Specify, and
                      // is the recommended value, but could differ.  

if ($target == "countries") { 
   $sql = "select count(g.Name) as ct, g.Name
              from (select CollectionObjectID, CO.CollectionMemberID, 
	                CE.LocalityID, LOC.GeographyID, GEO.NodeNumber  
	            from collectionobject as CO 
		         inner join collectingevent as CE on CE.CollectingEventID = CO.CollectingEventID 
			 inner join locality LOC on CE.LocalityID = LOC.LocalityID 
			 inner join geography as GEO on GEO.GeographyID = LOC.GeographyID
		   ) as COLGEO, 
		   geography as g 
	      where 
	         g.rankid = $COUNTRYRANKID and 
             COLGEO.nodenumber between g.nodenumber and g.HighestChildNodeNumber 
              group by g.Name 
	      order by g.Name";
    $field = "country";
} else { 
   // Browse Families 
   // Fastest, but only returns number of specimens identified to rank of family.
   $sql = "select count(*) as ct, name from taxon left join determination on taxon.taxonid = determination.taxonid where taxon.rankid = $FAMILYRANKID group by taxon.name";
   // Comprehensive, but slow - takes about 11 minutes on HUH data.
   // Includes All families, including those that have no determinations, and 
   // counts of all collection objects identified within each family.
   $sql =  "select count(taxon2.taxonid) as ct, t.name " .
	   "from taxon as taxon2, " .
	   "   taxon as t left join determination on t.taxonid = determination.taxonid " .
	   "where t.rankid = $FAMILYRANKEID " .
	   "  and taxon2.nodenumber > t.nodenumber " .
	   "  and taxon2.nodenumber < t.highestchildnodenumber " .
	   "group by t.name";  

// Faster, and relatively comprehensive, takes about 2 minutes on HUH data.
// Includes all families that have specimens identified to or within them.  
   $sql = "select count(t.Name) as ct, t.Name 
             from 
   	     (select det.CollectionObjectID, tax.NodeNumber 
	       from determination as det 
	       inner join taxon as tax on tax.taxonid = det.taxonid
	      ) as COLTAX, 
	      taxon as t 
	   where 
	     t.rankid = $FAMILYRANKID and 
	     COLTAX.nodenumber between t.nodenumber and t.HighestChildNodeNumber
	   group by t.Name 
	   order by t.Name"; 
   $field = "ht";
} 

	$statement = $connection->prepare($sql);
	if ($statement) { 
		$statement->execute();
		$statement->bind_result($ct, $name);
		$statement->store_result();
		if ($statement->num_rows > 0 ) {
			while ($statement->fetch()) { 
				echo "<a href='specimen_search.php?mode=search&$field=$name'>$name</a> ($ct) <BR>";
			}
			
		} else {
			$errormessage .= "No matching results. ";
		}
  	    $statement->close();
	} else { 
	    $errormessage .= $connection->error; 
    } 

} 
 
function details() { 
global $connection, $errormessage, $debug;
   $id = $_GET['id'];
   if (is_array($id)) { 
     $ids = $id;
   } else { 
     $ids[0] = $id;
   }
   $oldid = "";
   foreach($ids as $value) { 
       $id = substr(preg_replace("[^0-9]","",$value),0,20);
       // Might be duplicates next to each other in list of checkbox records from search results 
       // (from there being more than one current determination/typification for a specimen, or
       // from there being two specimens on one sheet).  
       if ($oldid!=$id)  { 
          echo "[$id]";
	  $wherebit = " collectionobject.collectionobjectid = ? ";
	  $query = "select distinct locality.geographyid geoid, locality.localityname, locality.lat1text, locality.lat2text, locality.long1text, locality.long2text, locality.datum, locality.latlongmethod, fragment.catalognumber, collectionobject.collectionobjectid, collectionobject.fieldnumber, collectionobject.remarks, collectingevent.verbatimdate, collectingevent.startdate, collectingevent.enddate, locality.maxelevation, locality.minelevation, collectingevent.startdateprecision, collectingevent.enddateprecision from collectionobject left join fragment on collectionobject.collectionobjectid = fragment.collectionobjectid left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join locality on collectingevent.localityid = locality.localityid  where $wherebit";
         if ($debug) { echo "[$query]<BR>"; } 
         $statement = $connection->prepare($query);
	 if ($statement) {
           $statement->bind_param("i",$id);
	   $statement->execute();
	   //$statement->bind_result($country, $locality, $FullName, $geoid, $CatalogNumber, $CollectionObjectID, $state);
	   $statement->bind_result($geoid, $lname, $lat1text, $lat2text, $long1text, $long2text, $datum, $latlongmethod, $CatalogNumber, $CollectionObjectID, $fieldnumber, $specimenRemarks, $verbatimdate, $startDate, $endDate, $maxElevation, $minElevation, $startdateprecision, $enddateprecision);
	   $statement->store_result();
           echo "<table>";
           while ($statement->fetch()) {
               if ($debug) { echo "[$CollectionObjectID]"; } 
	       //$query = "select gloc.name locality, a.localityname lname, a.geoid, a.catalognumber, a.collectionobjectid, a.remarks, a.startdate, a.enddate, a.maxelevation, a.minelevation, a.lat1text, a.lat2text, a.long1text, a.long2text, a.datum, a.latlongmethod, a.startdateprecision, a.enddateprecision, a.verbatimdate, a.fieldnumber from (select distinct locality.geographyid geoid, locality.localityname, locality.lat1text, locality.lat2text, locality.long1text, locality.long2text, locality.datum, locality.latlongmethod, fragment.catalognumber, collectionobject.collectionobjectid, collectionobject.fieldnumber, collectionobject.remarks, collectingevent.verbatimdate, collectingevent.startdate, collectingevent.enddate, locality.maxelevation, locality.minelevation, collectingevent.startdateprecision, collectingevent.enddateprecision from collectionobject left join fragment on collectionobject.collectionobjectid = fragment.collectionobjectid left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join locality on collectingevent.localityid = locality.localityid  where $wherebit) a left join geography gloc on a.geoid = gloc.geographyid";
	       $query = "select gloc.name locality, a.geoid from (select distinct locality.geographyid geoid from collectionobject left join fragment on collectionobject.collectionobjectid = fragment.collectionobjectid left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join locality on collectingevent.localityid = locality.localityid  where $wherebit) a left join geography gloc on a.geoid = gloc.geographyid";
               if ($debug) { echo "[$query]<BR>"; } 
               $statement_hg = $connection->prepare($query);
	       if ($statement_hg) {
                    $statement_hg->bind_param("i",$id);
	            $statement_hg->execute();
	            $statement_hg->bind_result($locality, $geoid);
	            $statement_hg->store_result();
	            while ($statement_hg->fetch()) { 
                    }
	       }	    

	      // **** Harvard University Herbaria Specific ***
	      // Acronym for herbarium is stored in fragment.text1
              $acronym = "";
              $query = "select text1 from fragment where fragment.collectionobjectid = ? ";
              if ($debug===true) {  echo "[$query]<BR>"; }
	      $statement_geo = $connection->prepare($query);
	      if ($statement_geo) { 
	         $statement_geo->bind_param("i",$CollectionObjectID);
                 $statement_geo->execute();
	         $statement_geo->bind_result($text1);
	         $statement_geo->store_result();
		 $separator = "";
	         while ($statement_geo->fetch()) { 
		     $acronym = "$acronym$separator$text1";
		     $separator=",";   // which probably shouldn't be needed.
	         }
	      } else { 
	        echo "Error: " . $connection->error;
	      }
	      // Accession number for specimen in in otheridentifier typed by Remarks=accession
	      $accession = "";
              $query = "select identifier, institution from otheridentifier where remarks = 'accession' and collectionobjectid = ? ";
              if ($debug===true) {  echo "[$query]<BR>"; }
	      $statement_acc = $connection->prepare($query);
	      if ($statement_acc) { 
	         $statement_acc->bind_param("i",$CollectionObjectID);
                 $statement_acc->execute();
	         $statement_acc->bind_result($identifier,$institution);
	         $statement_acc->store_result();
	         while ($statement_acc->fetch()) { 
                     $accession .= "<tr><td class='cap'>Accession</td><td class='val'>$institution $identifier</td></tr>";
	         }
	      } else { 
	        echo "Error: " . $connection->error;
	      }
              // **** End HUH Specific Block *****************
	      // *********************************************
	      $geography = "";
	      $country = "";
	      $state = "";
              $query = "select g.rankid, g.name from geography g where g.highestchildnodenumber >= ? and g.nodenumber<= ? order by g.rankid";
              if ($debug===true) {  echo "[$query]<BR>"; }
	      $statement_geo = $connection->prepare($query);
	      if ($statement_geo) { 
	         $statement_geo->bind_param("ii",$geoid,$geoid);
                 $statement_geo->execute();
	         $statement_geo->bind_result($geoRank,$geoName);
	         $statement_geo->store_result();
		 $separator = "";
	         while ($statement_geo->fetch()) { 
	            $geography .= $separator.$geoName;
		    $separator = ": ";
  	  	    if ($geoRank == "200") { $country = $geoName; } 
		    if ($geoRank == "300") { $state = $geoName; } 
	         }
	      } else { 
	        echo "Error: " . $connection->error;
	      }
	      $query = "select fullname, typeStatusName, determinedDate, isCurrent, determination.remarks from fragment left join determination on fragment.fragmentid = determination.fragmentid left join taxon on determination.taxonid = taxon.taxonid where fragment.collectionobjectid = ? order by typeStatusName, isCurrent, determinedDate"; 
              if ($debug===true) {  echo "[$query]<BR>"; }
	      $statement_det = $connection->prepare($query);
	      $determinationHistory = "";
	      if ($statement_det) { 
	         $statement_det->bind_param("i",$CollectionObjectID);
                 $statement_det->execute();
	         $statement_det->bind_result($fullName, $typeStatusName, $determinedDate, $isCurrent, $determinationRemarks );
	         $statement_det->store_result();
		 $separator = "";
	         while ($statement_det->fetch()) { 
		    if (trim($typeStatusName)=="") { $det = "Determination"; } else {$det = $typeStatusName; } 
                    $determinationHistory.= "<tr><td class='cap'>$det</td><td class='val'>$fullName</td></tr>";
		    if (trim($determinedDate)!="") { 
                       $determinationHistory.= "<tr><td class='cap'>DateDetermined</td><td class='val'>$determinedDate</td></tr>";
		    }
		    if (trim($determinationRemarks)!="") { 
                       $determinationHistory.= "<tr><td class='cap'>Remarks</td><td class='val'>$determinationRemarks</td></tr>";
		    }
	         }
	      } else {
	        echo "Error: " . $connection->error;
	      }
	      $collector = "";
	      $comma = "";
	      $query = "select agentvariant.name from collectionobject left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join collector on collectingevent.collectingeventid = collector.collectingeventid left join agent on collector.agentid = agent.agentid left join agentvariant on agent.agentid = agentvariant.agentid where agentvariant.vartype = 4 and collectionobjectid = ? ";
              if ($debug===true) {  echo "[$query]<BR>"; }
	      $statement_det = $connection->prepare($query);
	      if ($statement_det) { 
	         $statement_det->bind_param("i",$CollectionObjectID);
                 $statement_det->execute();
	         $statement_det->bind_result($collectorName);
	         $statement_det->store_result();
		 $separator = "";
	         while ($statement_det->fetch()) { 
		     $collector .= "$comma$collectorName";
		     $comma = "; ";
	         }
	      } else {
	        echo "Error: " . $connection->error;
	      }
	      $query = "select fragment.sex, fragment.phenology, preptype.name from fragment left join preparation on fragment.preparationid = preparation.preparationid left join preptype on preparation.preptypeid = preptype.preptypeid where collectionobjectid = ?";
              if ($debug===true) {  echo "[$query]<BR>"; }
	      $statement_det = $connection->prepare($query);
	      $attributes = "";
	      if ($statement_det) { 
	         $statement_det->bind_param("i",$CollectionObjectID);
                 $statement_det->execute();
	         $statement_det->bind_result($sex, $phenology, $preptype );
	         $statement_det->store_result();
		 $separator = "";
	         while ($statement_det->fetch()) { 
		    if (trim($sex)!="") { 
                       $attributes.= "<tr><td class='cap'>Sex</td><td class='val'>$sex</td></tr>";
		    }
		    if (trim($phenology)!="") { 
                       $attributes.= "<tr><td class='cap'>Phenology</td><td class='val'>$phenology</td></tr>";
		    }
		    if (trim($preptype)!="") { 
                       $attributes.= "<tr><td class='cap'>Preparation Type</td><td class='val'>$preptype</td></tr>";
		    }
	         }
	      } else {
	        echo "Error: " . $connection->error;
	      }

              if ($debug===true) {  echo "CollectionObjectID:[$CollectionObjectID]<BR>"; }
	      if (trim($maxElevation)!="" && $maxElevation!=$minElevation) { 
	         $elevation = "$minElevation - $maxElevation";
	      } else { 
	         $elevation = "$minElevation";
	      }
              $startDate = transformDateText($startDate, $startdateprecision);
              $endDate = transformDateText($endDate, $enddateprecision);
	      if (trim($startDate)!="" && trim($endDate)!="" && $endDate!=$startDate) { 
	         $dateCollected = "$startDate - $endDate";
	      } else { 
	         $dateCollected = "$startDate";
	      }
	      if ($verbatimDate != "") { 
	         $dateCollected .= " [$verbatimDate]";
	      }
	      $georeference = "";
	      if ($lat1text !="" && $long1text != "") { 
	         $georeference = "$lat1text, $long1text ";
	         if ($lat2text !="" && $long2text != "") { 
	            $georeference = "$lat2text, $long2text ";
		 }
	         if ($datum!= "") { 
		    $georeference .= "$datum ";
	         }
	         if ($latlongmethod != "") { 
		    $georeference .= "Method: $latlongmethod ";
	         }
	      }

              echo "<tr><td class='cap'>Harvard University Herbaria Barcode</td><td class='val'>$CatalogNumber</td></tr>";
              echo "<tr><td class='cap'>Herbarium</td><td class='val'>$acronym</td></tr>";
              echo "<tr><td class='cap'>Collector</td><td class='val'>$collector</td></tr>";
              if (trim($fieldnumber!="")) { echo "<tr><td class='cap'>Collector number</td><td class='val'>$fieldnumber</td></tr>"; } 
	      echo $accession;
	      echo $determinationHistory;
              if (trim($country!="")) { echo "<tr><td class='cap'>Country</td><td class='val'>$country</td></tr>"; } 
              if (trim($state!=""))   { echo "<tr><td class='cap'>State</td><td class='val'>$state</td></tr>"; }
              echo "<tr><td class='cap'>Geography</td><td class='val'>$geography</td></tr>";
              echo "<tr><td class='cap'>Locality</td><td class='val'>$lname</td></tr>";
              if (trim($georeference!=""))  { echo "<tr><td class='cap'>Georeference</td><td class='val'>$georeference</td></tr>"; }
              echo "<tr><td class='cap'>Date Collected</td><td class='val'>$dateCollected</td></tr>";
              if (trim($elevation!="")) {  echo "<tr><td class='cap'>Elevation</td><td class='val'>$elevation</td></tr>"; }
	      echo $attributes;
              echo "<tr><td class='cap'>Remarks</td><td class='val'>$specimenRemarks</td></tr>";
              echo "<BR>\n";
          }
           echo "</table>";
     
         }
	 $statement->close();
       }
       $oldid = $id;
   }
}

/*
// barcode bits to refactor into details search

	$barcode = substr(preg_replace("/[^0-9]/","", $_GET['barcode']),0,59);
	$catnum = $barcode;
	$barcode = barcode_to_catalog_number($barcode);
	if ($barcode==barcode_to_catalog_number("")) {
	   $barcode = "";
	}
	if ($barcode!="") { 
	   $question .= "Search for Barcode:[$barcode]<BR>";
	   $wherebit = " fragment.catalognumber = ? or fragment.catalognumber = ? ";
	   $query = "select geography.name country, gloc.name locality, a.fullname, a.geoid, a.catalognumber, a.collectionobjectid from geography, (select distinct taxon.fullname, locality.geographyid geoid, fragment.catalognumber, collectionobject.collectionobjectid from collectionobject left join fragment on collectionobject.collectionobjectid = fragment.collectionobjectid left join determination on fragment.fragmentid = determination.fragmentid left join taxon on determination.taxonid = taxon.taxonid left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join locality on collectingevent.localityid = locality.localityid  where $wherebit) a left join geography gloc on a.geoid = gloc.geographyid where geography.rankid = 200 and geography.highestchildnodenumber >= a.geoid and geography.nodenumber <= a.geoid";
	}
	if ($barcode!="") { 
	    $statement->bind_param("ss",$barcode,$catnum);
	}

*/

 
function search() {  
global $connection, $errormessage, $debug;
        $question = "";
	$quick = substr(preg_replace("/[^A-Za-z\ \%\*\.0-9]/","", $_GET['quick']),0,59);
	$locality = "";
	$country = "";
	if ($quick!="") { 
	   $question .= "Quick Search :[$quick]<BR>";
	   $query = "select distinct(collectionobjectid) from web_quicksearch where match (searchable) against (?)";
	} else { 
	$and = "";
	$locality = substr(preg_replace("/[^A-Za-z%]/","", $_GET['loc']),0,59);
	if ($locality!="") { 
	   $question .= "Search for geographic name:[$locality]<BR>";
	   $gwherebit = " name = ?  ";
	   $and = " or ";
	}
	$country = substr(preg_replace("/[^A-Za-z%]/","", $_GET['country']),0,59);
	if ($country!="") { 
	   $question .= "Search for country:[$country]<BR>";
	   $gwherebit .= "$and (name = ? and rankid = 200) ";
	   $and = " or ";
	}
        $query = "select geography.name country, lname locality, c.fullname, c.geoid, c.catalognumber, c.collectionobjectid from geography, (select distinct taxon.fullname, locality.localityname lname, locality.geographyid geoid, collectionobject.catalognumber, collectionobject.collectionobjectid from collectionobject left join determination on collectionobject.collectionobjectid = determination.collectionobjectid left join taxon on determination.taxonid = taxon.taxonid left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join locality on collectingevent.localityid = locality.localityid, (select leaf.geographyid geoid from geography leaf, (select highestchildnodenumber, nodenumber from geography where $gwherebit  ) a where a.nodenumber <= leaf.nodenumber and a.highestchildnodenumber >= leaf.nodenumber) b where locality.geographyid = b.geoid) c left join geography gloc on c.geoid = gloc.geographyid where geography.rankid = 200 and geography.highestchildnodenumber >= c.geoid and geography.nodenumber <= c.geoid ";

	/**
	$query = "select taxon.fullname, gcountry.name, gstate.name, collectionobject.catalognumber, collectionobject.collectionobjectid 
	          from collectionobject 
		     left join determination on collectionobject.collectionobjectid = determination.collectionobjectid 
		     left join taxon on determination.taxonid = taxon.taxonid 
		     left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid 
		     left join locality on collectingevent.localityid = locality.localityid 
		     left join geography as gchild on locality.geographyid = gchild.geographyid, 
		     geography gcountry, 
		     geography gstate 
		  where 
		     gcountry.rankid = 200 and 
		     gcountry.nodenumber < gchild.nodenumber and 
		     gcountry.highestchildnodenumber > gchild.highestchildnodenumber 
		     and 
		     gstate.rankid = 300 and 
		     gstate.nodenumber < gchild.nodenumber and 
		     gstate.highestchildnodenumber > gchild.highestchildnodenumber $wherebit ";
        */
        } 
	if ($debug===true) {
	  echo "[$query]<BR>\n";
	}
	$statement = $connection->prepare($query);
	if ($statement) { 
	        if ($quick!="") { 
		    $statement->bind_param("s",$quick);
		} else { 
	            if ($locality!="") { 
		       $statement->bind_param("s",$locality);
	   	    }
	            if ($country!="") { 
		       $statement->bind_param("s",$country);
		    }
		}
		$statement->execute();
		
		$statement->bind_result($CollectionObjectID);
		$statement->store_result();

                echo "<div>\n";
		echo $statement->num_rows . " matches to query ";
                echo "    <span class='query'>$question</span>\n";
                echo "</div>\n";
                echo "<HR>\n";

		if ($statement->num_rows > 0 ) {
                        echo "<div id='image-key'><img height='16' alt='has image' src='images/leaf.gif' /> = with images</div>\n";
		        echo "<form  action='specimen_search.php' method='get'>\n";
		        echo "<input type='hidden' name='mode' value='details'>\n";
                        echo "<input type='image' src='images/display_recs.gif' name='display' alt='Display selected records' />\n";
		        echo "<BR><div>\n";
			while ($statement->fetch()) { 
	                    $wherebit = " collectionobject.collectionobjectid = ? ";
	                    $query = "select geography.name country, gloc.name locality, a.fullname, a.geoid, a.catalognumber, a.collectionobjectid from geography, (select distinct taxon.fullname, locality.geographyid geoid, fragment.catalognumber, collectionobject.collectionobjectid from collectionobject left join fragment on collectionobject.collectionobjectid = fragment.collectionobjectid left join determination on fragment.fragmentid = determination.fragmentid left join taxon on determination.taxonid = taxon.taxonid left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join locality on collectingevent.localityid = locality.localityid  where $wherebit) a left join geography gloc on a.geoid = gloc.geographyid where geography.rankid = 200 and geography.highestchildnodenumber >= a.geoid and geography.nodenumber <= a.geoid";
	                    $statement2 = $connection->prepare($query);
			    if ($statement2) { 
		            $statement2->bind_param("s",$CollectionObjectID);
		            $statement2->execute();
		            $statement2->bind_result($country, $locality, $FullName, $geoid, $CatalogNumber, $CollectionObjectID);
		            $statement2->store_result();
		            if ($statement2->num_rows > 0 ) {
			        while ($statement2->fetch()) { 
                                   if (strlen($locality) > 12) { 
    				       $locality = substr($locality,0,11) . "...";
				    }
				    echo "<input type='checkbox' name='id[]' value='$CollectionObjectID'> <a href='specimen_search.php?mode=details&id=$CollectionObjectID'>$FullName</a> $country: $state $locality [Harvard University Herbaria Barcode: $CatalogNumber]";
				    echo "<BR>\n";
				}
			    } else { 
				echo "<input type='checkbox' name='id[]' value='$CollectionObjectID'> <a href='specimen_search.php?mode=details&id=$CollectionObjectID'>[Missing Data]</a> ";
				echo "<BR>\n";
			    }
			    } else { 
			        $errormessage .= "Query error. ";
			    }
			}
			echo "</div>\n";
                        echo "<input type='image' src='images/display_recs.gif' name='display' alt='Display selected records' />\n";
			echo "</form>\n";
			
		} else {
			$errormessage .= "No matching results. ";
		}
		
	} 
	$statement->close();
 
}
 
function pageheader($mode) { 
	$result="<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<head>
	<meta http-equiv='content-type' content='text/html; charset=utf-8' />
	<title>HUH - Databases - Specimen Search</title>
	<link rel='stylesheet' type='text/css' media='print' href='print.css'></link>
	<link rel='stylesheet' type='text/css' href='dbstyles.css'></link>	
	<link rel='stylesheet' type='text/css' media='print' href='cms-db.css'></link>
</head>
<body>
<div id='allcontent'>
	
		<!-- header code begins -->
		<div id='header'>
			<div id='top_menu'>
			
		        <!-- SiteSearch Google HERE -->
		         
				<div id='embed_nav'>
		  			<ul>
						<li><a href='/'>Home</a></li>
						<li>&#124;</li>
						<li><a href='http://zatoichi.huh.harvard.edu/people/index.php'>Contact</a></li>
						<li>&#124;</li>
						<li><a href='/news_events/news_events.html'>News &#38; Events</a></li>

						<li>&#124;</li>
						<li><a href='/news_events/calendar.html'>Calendar</a></li>
						<li>&#124;</li>
						<li><a href='/sitemap.html'>Sitemap</a></li>
						<li>&#124;</li>
						<li><a href='/links.html'>Links</a></li>
		  			</ul>

				</div>
			
			</div>
			<!-- top menu ends -->
		
				<div id='mid_sect'>
					<a href='http://www.huh.harvard.edu'><img width='100' src='http://www.huh.harvard.edu/images/huh_logo_bw_100.png' alt='HUH logo' title='Home page' /></a>
				</div>
	

				<div id='topnav'>
		 			<ul>
			 			<li ><a href='http://www.huh.harvard.edu/collections/'>Collections</a></li>

			 			<li class=active><a href='http://www.huh.harvard.edu/databases/'>Databases</a></li>
			 			<li ><a href='http://www.huh.harvard.edu/research/'>Research</a></li>
			 			<li ><a href='http://www.huh.harvard.edu/seminar_series/'>Seminar Series</a></li>
			 			<li ><a href='http://www.huh.harvard.edu/libraries/'>Libraries</a></li>
			 			<li  ><a href='http://zatoichi.huh.harvard.edu/people/'>People</a></li>
			 			<li ><a href='http://www.huh.harvard.edu/publications/'>HPB Journal</a></li>

			 			<li ><a href='http://www.huh.harvard.edu/visiting/'>Visiting</a></li>
		   			</ul>
		 		</div>
		
		</div>

		<!-- header code ends -->
		
<div id='sidenav'>
  <ul>
    <li><a href='addenda.html'>SEARCH HINTS</a></li>
    <li><a href='addenda.html#policy'>DISTRIBUTION AND USE POLICY</a></li>
  <hr />
    <li><a href='botanist_index.html'>BOTANISTS</a></li>
    <li><a href='publication_index.html'>PUBLICATIONS</a></li>
    <li><a href='specimen_index.html' class='active'>SPECIMENS</a></li>
  <hr />   
    <li><a href='add_correct.html'>Contribute additions/corrections</a></li>
    <li><a href='comment.html'>Send comments/questions</a></li>
    
  </ul>
</div>  <!-- sidenav ends -->		
		
		
<div id='main'>
   <!-- main content begins -->
   <div id='main_text'>
   <div id='title'>
      <h3>Index of Botanical Specimens</h3>
   </div>
"; 
   return $result;
}

function pagefooter() { 
   $result = "
   </div>
</div>
	<!-- main content ends -->

<!-- footer include begins -->		
	<div id='footer'>

			<div id='embed_nav2'>
		  		<ul>
					<li><a href='http://www.arboretum.harvard.edu/' target='_blank'>Arnold Arboretum</a></li>
					<li>&#124;</li>
					<li><a href='http://www.oeb.harvard.edu/' target='_blank'>OEB</a></li>

					<li>&#124;</li>
					<li><a href='http://www.pbi.fas.harvard.edu/' target='_blank'>PBI</a></li>
					<li>&#124;</li>
					<li><a href='http://www.hmnh.harvard.edu/' target='_blank'>HMNH</a></li> 
		  		</ul>
			</div>
			<h5>&copy; 2001 - <span id='cdate'></span> by the President and Fellows of <a href='http://www.harvard.edu/' target='_blank'>Harvard</a> College
		 	<br /><a href='priv_statement.html'>Privacy Statement</a> <span class='footer_indent'>Updated: 
		 		
		 		2009 May 21		 		
				</span></h5>

		 		
		 		<!-- gets current year for copyright date -->
		 		<script type='text/javascript'>
					// <![CDATA[
						var now = new Date()
						document.getElementById('cdate').innerHTML = now.getFullYear();
					// ]]>
				</script>

	</div>

</div> <!-- all content div tag ends -->
</body>
</html>";
   return $result;
} 


?>
