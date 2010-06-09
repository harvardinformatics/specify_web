<?php
/*
 * Created on Dec 3, 2009
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

$mode = "search";

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
   if ($_GET['mode']=="stats") {
   	   $mode = "stats"; 
   }
}

// Set up for special cases of requests for specific collection objects
// Where an identifier is given for a single collection object
if (preg_replace("[^0-9]","",$_GET['barcode'])!="") { 
   	   $mode = "details"; 
}
if (preg_replace("[^0-9]","",$_GET['fragmentid'])!="") { 
   	   $mode = "details"; 
}

echo pageheader('specimen'); 

if ($connection) { 
	
	switch ($mode) {
		
	   case "browse_families":
	      echo browse("families");
	   break;

	   case "browse_countries":
	      echo browse("countries");
	   break;
		 
	   case "details":
	      details();
	   break;

       case "stats": 
          echo stats();
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

 
function details() { 
	global $connection, $errormessage, $debug;
	$id = preg_replace("[^0-9]","",$_GET['id']);
	if ($id!="") { 
		if (is_array($id)) { 
			$ids = $id;
		} else { 
			$ids[0] = $id;
		}
	}
	$barcode = preg_replace("[^0-9]","",$_GET['barcode']);
	if ($barcode != "") {
		
		// TODO: Barcode could be in preparation or in fragment, presence in collection object is just legacy. 
		
		$sql = "select collectionobjectid from collectionobject where altcatalognumber = ? ";
		$sql = "select collectionobjectid from fragment left join preparation on fragment.preparationid = preparation.preparationid " .
			"  where fragment.identifier = ? or preparation.identifier = ? ";
		$statement = $connection->prepare($sql);
		if ($statement) {
			$statement->bind_param("ii",$barcode,$barcode);
			$statement->execute();
			$statement->bind_result($id);
			$statement->store_result();
			while ($statement->fetch()) {
				$ids[] = $id;
			}
		}
	}
	$fragmentid = preg_replace("[^0-9]","",$_GET['fragmentid']);
	if ($fragmentid != "") {
		$sql = "select collectionobjectid from fragment where fragmentid = ? ";
		$statement = $connection->prepare($sql);
		if ($statement) {
			$statement->bind_param("i",$fragmentid);
			$statement->execute();
			$statement->bind_result($id);
			$statement->store_result();
			while ($statement->fetch()) {
				$ids[] = $id;
			}
		}
	}
	if (count($ids)==0) { 
		if ($barcode!="") { 
			echo "<h2>No such barcode as [$barcode]</h2>";
		}
		if ($fragmentid!="") { 
			echo "<h2>No such fragmentid as [$fragmentid]</h2>";
		}
	} else { 
		$oldid = "";
		foreach($ids as $value) { 
			$id = substr(preg_replace("[^0-9]","",$value),0,20);
			echo "id=[$id]<BR>";
			// Might be duplicates next to each other in list of checkbox records from search results 
			// (from there being more than one current determination/typification for a specimen, or
			// from there being two specimens on one sheet).  
			if ($oldid!=$id)  { 
				// Start by finding collection object specific information, then for each collection object,
				// find information specific to each fragment/item on that collection object.   
				$wherebit = " collectionobject.collectionobjectid = ? ";
				//$query = "select distinct gloc.name locality, locality.geographyid, collectionobject.catalognumber, collectionobject.collectionobjectid, collectionobject.remarks, collectingevent.startdate, collectingevent.enddate, locality.maxelevation, locality.minelevation from collectionobject left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join locality on collectingevent.localityid = locality.localityid left join geography gloc on locality.geographyid = gloc.geographyid where $wherebit ";
				$query = "select distinct locality.geographyid geoid, locality.localityname, locality.lat1text, locality.lat2text, locality.long1text, " .
						" locality.long2text, locality.datum, locality.latlongmethod, " .
						" collectionobject.altcatalognumber as altcatalognumber, collectionobject.collectionobjectid, " .
						" collectionobject.fieldnumber, collectionobject.remarks, collectingevent.verbatimdate, " .
						" collectingevent.startdate, collectingevent.enddate, locality.maxelevation, locality.minelevation, " .
						" collectingevent.startdateprecision, collectingevent.enddateprecision " .
						" from collectionobject " .
						"    left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid " .
						"    left join locality on collectingevent.localityid = locality.localityid  " .
						" where $wherebit";
				if ($debug) { echo "[$query][$id]<BR>"; } 
				$statement = $connection->prepare($query);
				if ($statement) {
					$statement->bind_param("i",$id);
					$statement->execute();
					//$statement->bind_result($country, $locality, $FullName, $geoid, $CatalogNumber, $CollectionObjectID, $state);
					$statement->bind_result($geoid, $lname, $lat1text, $lat2text, $long1text, $long2text, $datum, $latlongmethod, $AltCatalogNumber, $CollectionObjectID, $fieldnumber, $specimenRemarks, $verbatimdate, $startDate, $endDate, $maxElevation, $minElevation, $startdateprecision, $enddateprecision);
					$statement->store_result();
					if ($statement->num_rows()==0) { 
			            echo "<h2>collectionobjectid [$id] not found.</h2>";
					} else { 
					echo "<table>";
					while ($statement->fetch()) {
						// For each collection object
						if ($debug) { echo "[$CollectionObjectID]"; } 
						//$query = "select gloc.name locality, a.localityname lname, a.geoid, a.catalognumber, a.collectionobjectid, a.remarks, a.startdate, a.enddate, a.maxelevation, a.minelevation, a.lat1text, a.lat2text, a.long1text, a.long2text, a.datum, a.latlongmethod, a.startdateprecision, a.enddateprecision, a.verbatimdate, a.fieldnumber from (select distinct locality.geographyid geoid, locality.localityname, locality.lat1text, locality.lat2text, locality.long1text, locality.long2text, locality.datum, locality.latlongmethod, fragment.catalognumber, collectionobject.collectionobjectid, collectionobject.fieldnumber, collectionobject.remarks, collectingevent.verbatimdate, collectingevent.startdate, collectingevent.enddate, locality.maxelevation, locality.minelevation, collectingevent.startdateprecision, collectingevent.enddateprecision from collectionobject left join fragment on collectionobject.collectionobjectid = fragment.collectionobjectid left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join locality on collectingevent.localityid = locality.localityid  where $wherebit) a left join geography gloc on a.geoid = gloc.geographyid";
						// Collection objects are linked to collecting events.
						$query = "select gloc.name locality, a.geoid from " .
								" (select distinct locality.geographyid geoid " .
								"  from collectionobject " .
								"  left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid " .
								"  left join locality on collectingevent.localityid = locality.localityid  where $wherebit) a " .
								"  left join geography gloc on a.geoid = gloc.geographyid";
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
						// Images are of collection objects
						// HUH images are in separate set of IMAGE_ tables imported from ASA.
						$images = "";
						$query = "select concat(url_prefix,uri) as url, pixel_height, pixel_width, t.name, file_size " .
								" from IMAGE_SET_collectionobject c left join IMAGE_OBJECT o on c.imagesetid = o.image_set_id " .
								" left join REPOSITORY r on o.repository_id = r.id " .
								" left join IMAGE_OBJECT_TYPE t on o.object_type_id = t.id " .
								" where c.collectionobjectid = ? and o.active_flag = 1 " .
								" order by object_type_id desc ";
						if ($debug===true) {  echo "[$query]<BR>"; }
						$statement_img = $connection->prepare($query);
						if ($statement_img) { 
							$statement_img->bind_param("i",$CollectionObjectID);
							$statement_img->execute();
							$statement_img->bind_result($url,$height,$width,$imagename,$filesize);
							$statement_img->store_result();
							$fullurl = "";
							while ($statement_img->fetch()) { 
								if ($imagename == "Thumbnail") {
								    $images .= "<tr><td class='cap'>Image </td><td class='val'><a href='$fullurl'><img src='$url' height='205px' width='150' ></a></td></tr>";
								} else { 
									if ($imagename == "Full") { 
										 $fullurl = $url;
									}
									$size = floor($filesize / 1024);
									$size = $size . " kb";
								    $images .= "<tr><td class='cap'>Image </td><td class='val'><a href='$url'>$imagename</a> [$size]</td></tr>";
								}
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
						$redactlocality = false;
						//$query = "select fullname, typeStatusName, determinedDate, isCurrent, determination.remarks, taxon.nodenumber from determination left join taxon on determination.taxonid = taxon.taxonid where determination.collectionobjectid = ? order by typeStatusName desc, isCurrent, determinedDate"; 
						$query = "select fullname, typeStatusName, confidence, qualifier, determinedDate, isCurrent, " .
								" determination.remarks, taxon.nodenumber, taxon.author, determination.text1 as verifier, citesstatus, taxon.taxonid " .
								" from fragment " .
								" left join determination on fragment.fragmentid = determination.fragmentid " .
								" left join taxon on determination.taxonid = taxon.taxonid " .
								" where fragment.collectionobjectid = ? order by typeStatusName, isCurrent, determinedDate"; 
						if ($debug===true) {  echo "[$query]<BR>"; }
						$statement_det = $connection->prepare($query);
						$determinationHistory = "";
						if ($statement_det) { 
							$statement_det->bind_param("i",$CollectionObjectID);
							$statement_det->execute();
							$statement_det->bind_result($fullName, $typeStatusName, $confidence, $qualifier, $determinedDate, $isCurrent, $determinationRemarks, $nodenumber, $author, $verifier, $citesstatus, $taxonid );
							$statement_det->store_result();
							$separator = "";
							$typeStatus = "";
							$nodes = array();
							while ($statement_det->fetch()) {
						        if ($debug===true) {  echo "TaxonID=[$taxonid]<BR>"; }
								$nodes[] = $nodenumber;
								if (trim($typeStatusName)=="") { 
									$det = "Determination"; 
								} else {
									$det = trim("$confidence $typeStatusName of");
									$typeStatus .= trim("$separator$confidence $typeStatusName");
									$separator = ", ";
								} 
								$determinationHistory.= "<tr><td class='cap'>$det</td><td class='val'>$qualifier <em>$fullName</em> $author</td></tr>";
								if (trim($verifier)!="") { 
									$determinationHistory.= "<tr><td class='cap'>Verified by</td><td class='val'>$verifier</td></tr>";
								}
								if (trim($determinedDate)!="") { 
									$determinationHistory.= "<tr><td class='cap'>DateDetermined</td><td class='val'>$determinedDate</td></tr>";
								}
								if (trim($determinationRemarks)!="") { 
									$determinationHistory.= "<tr><td class='cap'>Remarks</td><td class='val'>$determinationRemarks</td></tr>";
								}
								// If a CITES listed species, set redactlocality flag.a
								if ($citesstatus != "None") {
									$redactlocality = true; 
								}
							}
							if (count($nodes)>0) { 
								$oldhigher = "";
								$highertaxonomy = "";
								foreach ($nodes as $nodenumber) { 
									$query = "select taxon.name from taxon where taxon.nodenumber < ? and taxon.highestchildnodenumber > ? ";
									$statement_ht = $connection->prepare($query);
									if ($statement_ht) { 
										if ($debug===true) {  echo "[$query]<BR>"; }
										$statement_ht->bind_param("ii",$nodenumber,$nodenumber);
										$statement_ht->execute();
										$statement_ht->bind_result($higherName);
										$statement_ht->store_result();
										$colon = "";
										$higher = "";
										while ($statement_ht->fetch()) { 
											if ($higherName!="Life") { 
												$higher .= $colon . $higherName;
												$colon = ":";
											}
										}
										$statement_ht->close();
									}
									if ($higher!="" && $higher!=$oldhigher ) {
										$oldhigher = $higher; 
										$highertaxonomy.= "<tr><td class='cap'>Classification</td><td class='val'>$higher</td></tr>";
									}
								}
							}
							$query = "select r.title, c.text1 as pages, c.text2 as year, r.referenceworkid " .
								" from referencework r left join taxoncitation c on r.referenceworkid = c.referenceworkid " .
								" where c.taxonid = ? ";
							if ($debug===true) {  echo "[$query]<BR>"; }
							$statement_ref = $connection->prepare($query);
							$fragmentcitations = "";
							if ($statement_ref) { 
								$statement_ref->bind_param("i",$taxonid);
								$statement_ref->execute();
								$statement_ref->bind_result($title, $pages, $year, $referenceworkid);
								$statement_ref->store_result();
								$separator = "";
								while ($statement_ref->fetch()) { 
									if (trim($title)!="") { 
										if ($year!="") { $year = "$year."; }
										if ($pages!="") { $pages = "$pages."; }
										$determinationHistory.= "<tr><td class='cap'>Reference</td><td class='val'><a href='publication_search.php?mode=details&id=$referenceworkid'>$title</a> $year $pages</td></tr>";
									}
								}
							} else {
								echo "Error: " . $connection->error;
							}
						} else {
							echo "Error: " . $connection->error;
						}
						$collector = "";
						$comma = "";
						$query = "select agentvariant.name, agentvariant.agentid from collectionobject left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join collector on collectingevent.collectingeventid = collector.collectingeventid left join agent on collector.agentid = agent.agentid left join agentvariant on agent.agentid = agentvariant.agentid where agentvariant.vartype = 4 and collectionobjectid = ? ";
						if ($debug===true) {  echo "[$query]<BR>"; }
						$statement_det = $connection->prepare($query);
						if ($statement_det) { 
							$statement_det->bind_param("i",$CollectionObjectID);
							$statement_det->execute();
							$statement_det->bind_result($collectorName, $agentid);
							$statement_det->store_result();
							$separator = "";
							while ($statement_det->fetch()) { 
								$collector .= "$comma<a href='botanist_search.php?botanistid=$agentid'>$collectorName</a>";
								$comma = "; ";
							}
						} else {
							echo "Error: " . $connection->error;
						}
						$query = "select fragment.sex, fragment.phenology, preptype.name, fragment.identifier, preparation.identifier from fragment left join preparation on fragment.preparationid = preparation.preparationid left join preptype on preparation.preptypeid = preptype.preptypeid where collectionobjectid = ?";
						if ($debug===true) {  echo "[$query]<BR>"; }
						$statement_det = $connection->prepare($query);
						$attributes = "";
						if ($statement_det) { 
							$statement_det->bind_param("i",$CollectionObjectID);
							$statement_det->execute();
							$statement_det->bind_result($sex, $phenology, $preptype, $fbarcode, $pbarcode);
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
									$attributes.= "<tr><td class='cap'>Preparation Type</td><td class='val'>$preptype [". trim("$fbarcode $pbarcode") ."]</td></tr>";
								}
							}
						} else {
							echo "Error: " . $connection->error;
						}
						
						// obtain the list of fragments (items) for this collection object, and query for fragment related elements.
						$query = "select fragmentid from fragment where collectionobjectid = ? ";  
						if ($debug===true) {  echo "[$query]<BR>"; }
						$statement_frag = $connection->prepare($query);
						$items = "";
						$itemcount = 0;
						if ($statement_frag) { 
							$statement_frag->bind_param("i",$CollectionObjectID);
							$statement_frag->execute();
							$statement_frag->bind_result($fragmentid);
							$statement_frag->store_result();
							while ($statement_frag->fetch()) {
								$itemcount ++; 
								$items .= "<tr><td class='cap'>Item</td><td class='val'>$itemcount</td></tr>";
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
								// Barcode number is in fragment.identifier or prepration.identifier
								$query = "select distinct fragment.identifier, preparation.identifier " .
									" from fragment left join preparation on fragment.preparationid = preparation.preparationid " .
									" where fragment.collectionobjectid = ? ";
								if ($debug===true) {  echo "[$query]<BR>"; }
								$CatalogNumber = "";
								$fragments = array();
								$statement_bar = $connection->prepare($query);
								if ($statement_bar) { 
									$statement_bar->bind_param("i",$CollectionObjectID);
									$statement_bar->execute();
									$statement_bar->bind_result($identifierf,$identifierp);
									$statement_bar->store_result();
									$separator = "";
									while ($statement_bar->fetch()) { 
										if ($identifierf!="") { 
											$CatalogNumber .= "$separator$identifierf";
											$separator="; ";
										}  
										if ($identifierp!="") { 
											$CatalogNumber .= "$separator$identifierp";
											$separator="; ";  
										}
									}
								} else { 
									echo "Error: " . $connection->error;
								}
						        $items .= "<tr><td class='cap'>Harvard University Herbaria Barcode</td><td class='val'>$CatalogNumber</td></tr>";
						        $items .= "<tr><td class='cap'>Herbarium</td><td class='val'>$acronym</td></tr>";
						        // get any references linked to the fragment.
								$query = "select r.title, r.text2, r.referenceworkid " .
									" from referencework r left join fragmentcitation c on r.referenceworkid = c.referenceworkid " .
									" where c.fragmentid = ? ";
								if ($debug===true) {  echo "[$query]<BR>"; }
								$statement_ref = $connection->prepare($query);
								$fragmentcitations = "";
								if ($statement_ref) { 
									$statement_ref->bind_param("i",$fragmentid);
									$statement_ref->execute();
									$statement_ref->bind_result($title, $volumes,$referenceworkid);
									$statement_ref->store_result();
									$separator = "";
									while ($statement_ref->fetch()) { 
										if (trim($title)!="") { 
											$fragmentcitations.= "<tr><td class='cap'>Reference</td><td class='val'>$title</td></tr>";
										}
									}
								} else {
									echo "Error: " . $connection->error;
								}
								$items .= $fragmentcitations;
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
						
						if (preg_match("/^140\.247\.98\./",$_SERVER['REMOTE_ADDR'])) {
							$redactlocality = false; 
						}
						
						echo $highertaxonomy;
						echo $accession;
						if (trim($typeStatus!=""))   { echo "<tr><td class='cap'>Type Status</td><td class='val'>$typeStatus</td></tr>"; }
						echo $determinationHistory;
						echo "<tr><td class='cap'>Collector</td><td class='val'>$collector</td></tr>";
						if (trim($fieldnumber!="")) { echo "<tr><td class='cap'>Collector number</td><td class='val'>$fieldnumber</td></tr>"; } 
						if (trim($country!="")) { echo "<tr><td class='cap'>Country</td><td class='val'>$country</td></tr>"; } 
						if (trim($state!=""))   { echo "<tr><td class='cap'>State</td><td class='val'>$state</td></tr>"; }
						echo "<tr><td class='cap'>Geography</td><td class='val'>$geography</td></tr>";
						if (trim($lname !=""))  { 
						    if ($redactlocality === true ) { $lname = "[Redacted]"; }
						    echo "<tr><td class='cap'>Locality</td><td class='val'>$lname</td></tr>";
						}
						if (trim($georeference!=""))  { 
						     if ($redactlocality === true ) { $georeference = "[Redacted]"; }
							 echo "<tr><td class='cap'>Georeference</td><td class='val'>$georeference</td></tr>"; 
						}
						if (trim($dateCollected!="")) { echo "<tr><td class='cap'>Date Collected</td><td class='val'>$dateCollected</td></tr>"; }
						if (trim($elevation!="")) {  
						    if ($redactlocality === true ) { $elevation = "[Redacted]"; }
							echo "<tr><td class='cap'>Elevation</td><td class='val'>$elevation</td></tr>"; 
						}
						echo $items;
						echo $attributes;
						echo $images;
						if (trim($specimenRemarks!="")) {  
						    echo "<tr><td class='cap'>Remarks</td><td class='val'>$specimenRemarks</td></tr>";
						} 
						echo "<BR>\n";
					}
					echo "</table>";
					}  // end else num_rows>0
				}
				$statement->close();
			}
			$oldid = $id;
		}  // end foreach ids as value
	} // end else block countids<>0
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
       $hasquery = true;
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
		$query = "select distinct q.collectionobjectid,  c.family, c.genus, c.species, c.infraspecific, c.author, c.country, c.state, c.location, c.herbaria, c.barcode, i.imagesetid " .
				" from web_quicksearch  q left join web_search c on q.collectionobjectid = c.collectionobjectid " .
				" left join IMAGE_SET_collectionobject i on q.collectionobjectid = i.collectionobjectid " .
				" where match (searchable) against (?)";
		$hasquery = true;
	} else {
		$joins = "";
		$wherebit = " where "; 
		$and = "";
		$types = "";
		$parametercount = 0;
		$genus = substr(preg_replace("/[^A-Za-z_%]/","", $_GET['gen']),0,59);
		if ($genus!="") { 
			$hasquery = true;
			$question .= "$and genus:[$genus] ";
			$types .= "s";
			$operator = "=";
			$parameters[$parametercount] = &$genus;
			$parametercount++;
			if (preg_match("/[%_]/",$genus))  { $operator = " like "; }
			$wherebit .= "$and web_search.genus $operator ? ";
			$and = " and ";
		}
		$locality = substr(preg_replace("/[^A-Za-z%]/","", $_GET['loc']),0,59);
		if ($locality!="") { 
			$hasquery = true;
			$locality = "%$locality%";   // append wildcards 
			$question .= "$and geographic name like:[$locality] ";
			$types .= "ssss";
			$parameters[$parametercount] = &$locality;
			$parametercount++;
			$parameters[$parametercount] = &$locality;
			$parametercount++;
			$parameters[$parametercount] = &$locality;
			$parametercount++;
			$parameters[$parametercount] = &$locality;
			$parametercount++;
			$operator = "=";
			if (preg_match("/[%_]/",$locality))  { $operator = " like "; }
			$wherebit .= "$and ( web_search.location $operator ? or web_search.country $operator ? or web_search.state $operator ? or web_search.county $operator ? ) ";
			$and = " and ";
		}
		$country = substr(preg_replace("/[^A-Za-z _%]/","", $_GET['country']),0,59);
		if ($country!="") { 
			$hasquery = true;
			$question .= "$and country:[$country] ";
			$types .= "s";
			$parameters[$parametercount] = &$country;
			$parametercount++;
			$operator = "=";
			if (preg_match("/[%_]/",$country))  { $operator = " like "; }
			$wherebit .= "$and web_search.country $operator ? ";
			$and = " and ";
		}
		$species = substr(preg_replace("/[^A-Za-z _%]/","", $_GET['sp']),0,59);
		if ($species!="") { 
			$hasquery = true;
			$question .= "$and species:[$species]";
			$types .= "s";
			$operator = "=";
			$parameters[$parametercount] = &$species;
			$parametercount++;
			if (preg_match("/[%_]/",$species))  { $operator = " like "; }
			$wherebit .= "$and web_search.species $operator ? ";
			$and = " and ";
		}
		$infraspecific = substr(preg_replace("/[^A-Za-z _%]/","", $_GET['infra']),0,59);
		if ($infraspecific!="") { 
			$hasquery = true;
			$question .= "$and infraspecific epithet:[$infraspecific]";
			$types .= "s";
			$operator = "=";
			$parameters[$parametercount] = &$infraspecific;
			$parametercount++;
			if (preg_match("/[%_]/",$infraspecific))  { $operator = " like "; }
			$wherebit .= "$and web_search.infraspecific $operator ? ";
			$and = " and ";
		}
		$author = substr(preg_replace("/[^A-Za-z _%]/","", $_GET['author']),0,59);
		if ($author!="") { 
			$hasquery = true;
			$question .= "$and author:[$author]";
			$types .= "s";
			$operator = "=";
			$parameters[$parametercount] = &$author;
			$parametercount++;
			if (preg_match("/[%_]/",$author))  { $operator = " like "; }
			$wherebit .= "$and web_search.author $operator ? ";
			$and = " and ";
		}
		$collector = substr(preg_replace("/[^A-Za-z _%\.\,]/","", $_GET['cltr']),0,59);
		if ($collector!="") { 
			$hasquery = true;
			$question .= "$and collector:[$collector]";
			$types .= "s";
			$operator = "=";
			$parameters[$parametercount] = &$collector;
			$parametercount++;
			if (preg_match("/[%_]/",$collector))  { $operator = " like "; }
			$wherebit .= "$and web_search.collector $operator ? ";
			$and = " and ";
		}
		$collectornumber = substr(preg_replace("/[^1-9\.A-Za-z _%]/","", $_GET['collectornumber']),0,59);
		if ($collectornumber!="") { 
			$hasquery = true;
			$question .= "$and collectornumber:[$collectornumber]";
			$types .= "s";
			$operator = "=";
			$parameters[$parametercount] = &$collectornumber;
			$parametercount++;
			if (preg_match("/[%_]/",$collectornumber))  { $operator = " like "; }
			$wherebit .= "$and web_search.collectornumber $operator ? ";
			$and = " and ";
		}
		$yearpublished = substr(preg_replace("/[^0-9 _%]/","", $_GET['year']),0,59);
		if ($yearpublished!="") { 
			$hasquery = true;
			$question .= "$and yearpublished:[$yearpublished]";
			$types .= "s";
			$operator = "=";
			$parameters[$parametercount] = &$yearpublished;
			$parametercount++;
			if (preg_match("/[%_]/",$yearpublished))  { $operator = " like "; }
			$wherebit .= "$and web_search.yearpublished $operator ? ";
			$and = " and ";
		}
		$yearcollected = substr(preg_replace("/[^0-9 _%]/","", $_GET['yearcollected']),0,59);
		if ($yearcollected!="") { 
			$hasquery = true;
			$question .= "$and year collected:[$yearcollected]";
			$types .= "s";
			$operator = "=";
			$parameters[$parametercount] = &$yearcollected;
			$parametercount++;
			if (preg_match("/[%_]/",$yearcollected))  { $operator = " like "; }
			$wherebit .= "$and web_search.yearcollected $operator ? ";
			$and = " and ";
		}
		$family = substr(preg_replace("/[^A-Za-z%\_\*]/","", $_GET['family']),0,59);
		if ($family!="") { 
			$family = str_replace("*","%",$family);   // change to sql wildcard.
			$hasquery = true;
			$question .= "$and higher taxon:[$family] ";
			$types .= "s";
			$parameters[$parametercount] = &$family;
			$parametercount++;
			$operator = "=";
			if (preg_match("/[%_]/",$family))  { $operator = " like "; }
			$wherebit .= "$and web_search.family $operator ? ";
			$and = " and ";
		}
		if ($question!="") {
			$question = "Search for $question <BR>";
		} else {
			$question = "No search criteria provided.";
		}
		$query = "select distinct c.collectionobjectid, web_search.family, web_search.genus, web_search.species, web_search.infraspecific, " .
				" web_search.author, web_search.country, web_search.state, web_search.location, web_search.herbaria, web_search.barcode, " .
				" i.imagesetid, web_search.datecollected " . 
                " from collectionobject c 
			      left join web_search on c.collectionobjectid = web_search.collectionobjectid" .
			    " left join IMAGE_SET_collectionobject i on web_search.collectionobjectid =  i.collectionobjectid  $wherebit ";
	} 
	if ($debug===true  && $hasquery===true) {
		echo "[$query]<BR>\n";
	}
    if ($hasquery===true) { 
	$statement = $connection->prepare($query);
	if ($statement) { 
		if ($quick!="") { 
			$statement->bind_param("s",$quick);
		} else { 
			if ($barcode!="") { 
				$statement->bind_param("s",$barcode);
			} else { 
				$array = Array();
				$array[] = $types;
				foreach($parameters as $par)
				$array[] = $par;
				call_user_func_array(array($statement, 'bind_param'),$array);
			}
		}
		$statement->execute();
		$statement->bind_result($CollectionObjectID,  $family, $genus, $species, $infraspecific, $author, $country, $state, $locality, $herbaria, $barcode, $imagesetid, $datecollected);
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
	            //$wherebit = " collectionobject.collectionobjectid = ? ";
	            //$query = "select geography.name country, gloc.name locality, a.fullname, a.geoid, a.catalognumber, a.collectionobjectid from geography, (select distinct taxon.fullname, locality.geographyid geoid, collectionobject.altcatalognumber as catalognumber, collectionobject.collectionobjectid from collectionobject left join fragment on collectionobject.collectionobjectid = fragment.collectionobjectid left join determination on fragment.fragmentid = determination.fragmentid left join taxon on determination.taxonid = taxon.taxonid left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join locality on collectingevent.localityid = locality.localityid  where $wherebit) a " .
	            //		"left join geography gloc on a.geoid = gloc.geographyid " .
	            //		"where geography.rankid = 200 and geography.highestchildnodenumber >= a.geoid and geography.nodenumber <= a.geoid";
	            //$statement2 = $connection->prepare($query);
			    //if ($statement2) { 
		            //$statement2->bind_param("s",$CollectionObjectID);
		            //$statement2->execute();
		            //$statement2->bind_result($country, $locality, $FullName, $geoid, $CatalogNumber, $CollectionObjectID);
		            //$statement2->store_result();
		            //if ($statement2->num_rows > 0 ) {
			            //while ($statement2->fetch()) { 
                         if (strlen($locality) > 12) { 
    				           $locality = substr($locality,0,11) . "...";
				         }
				         if (strlen($imagesetid)>0) { 
				         	$imageicon = "<img src='images/leaf.gif'>";
				         } else {
				         	$imageicon = "";
				         }
				         $FullName = "[<a href='sepecimen_search.php?family=$family'>$family</a>] <em>$genus $species $infraspecific</em> $author";
				         $geography = "$country: $state $locality ";
				         $specimenidentifier =  "<a href='specimen_search.php?mode=details&id=$CollectionObjectID'>$herbaria Barcode: $barcode</a>"; 
				         echo "<input type='checkbox' name='id[]' value='$CollectionObjectID'> $specimenidentifier $FullName $geography $datecollected $imageicon";
				         echo "<BR>\n";
				         //}
			        //} else { 
				         //echo "<input type='checkbox' name='id[]' value='$CollectionObjectID'> <a href='specimen_search.php?mode=details&id=$CollectionObjectID'>[Missing Data]</a> ";
				         //echo "<BR>\n";
			        //}
			    //} else { 
			    //    $errormessage .= "Query error. ";
			    //    if ($debug) {
			    //    	 $errormessage .= "[" . mysqli_error($connection); 
			    //    }
			    //}
			}
			echo "</div>\n";
			echo "<input type='image' src='images/display_recs.gif' name='display' alt='Display selected records' />\n";
			echo "</form>\n";
			
		} else {
			$errormessage .= "No matching results. ";
			if ($collector != "") {  
				$statement->close();
				// Look for possibly related collectors
				$query = " select  trim(concat(ifnull(agent.firstname,''), ' ', ifnull(agent.lastname,''))), count(collector.collectingeventid) " .
						" from collector left join agent on collector.agentid = agent.agentid " .
						" left join agentvariant on agent.agentid = agentvariant.agentid " .
						" where (agentvariant.name like ? or soundex(agent.lastname) = soundex(?) or agent.lastname like ? or soundex(agent.lastname) = soundex(?)) " .
						" group by agent.firstname, agent.lastname, agent.agentid order by agent.lastname, count(collector.collectingeventid) ";
				$wildcollector = "%$collector%";
				$plaincollector = str_replace("%","",$collector);
       		    $collectorparameters[0] = &$wildcollector;   // agentvariant like 
 		        $types = "s";
       		    $collectorparameters[1] = &$plaincollector;  // agentvariant soundex
 		        $types .= "s";
       		    $collectorparameters[2] = &$wildcollector;   // agent like 
 		        $types .= "s";
       		    $collectorparameters[3] = &$plaincollector;  // agent soundex
 		        $types .= "s";
             	if ($debug===true  && $hasquery===true) {
		              echo "[$query][$wildcollector][$plaincollector][$wildcollector][$plaincollector]<BR>\n";
	            }
				$statement = $connection->prepare($query);
				$searchcollector = preg_replace("/[^A-Za-z ]/","", $plaincollector);
				if ($statement) { 
					$array = Array();
					$array[] = $types;
					foreach($collectorparameters as $par)
					      $array[] = $par;
					call_user_func_array(array($statement, 'bind_param'),$array);
					$statement->execute();
					$statement->bind_result($collector, $count);
					$statement->store_result();
			        if ($statement->num_rows > 0 ) {
			        	echo "<h2>No matching results.</h2>";   // move the error message before this query
			        	echo "<h3>Possibly matching collectors</h3>";
			            $errormessage = "";   // clear the error message so it doesn't show at the end.'
				         while ($statement->fetch()) {
				         	$highlightedcollector = preg_replace("/$searchcollector/","<strong>$plaincollector</strong>",$collector);
				         	echo "$highlightedcollector [<a href='specimen_search.php?mode=search&cltr=$collector'>$count records</a>]<br>";
				         }
				         echo "<BR>";
			        }
				}
				
			}
		}
	    $statement->close();
	} else { 
		echo $connection->error;
	}
    } else { 
		echo "<div>\n";
		echo "No query parameters provided.";
		echo "</div>\n";
		echo "<HR>\n";
        echo stats();	
    } 
	
}
 
mysqli_report(MYSQLI_REPORT_OFF);

?>
