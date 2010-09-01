<?php
/* 
 * specimen_search.php
 * Created on Dec 3, 2009
 *
 * Searches a customized Specify6 database 
 */

// ***** Specify Database Customization Required *********
// This code won't work on an out of the box Specify6 installation without customization.
// specimen_search.php depends on the construction of the tables web_search and web_quicksearch
// See the sql script populate_web_table_queries.sql 
// *******************************************************

// Uses of local customization fields in this version: 
// 
// collectionobject.text1  Host         [In web_search]
// collectionobject.text2  Substrate    [In web_search]
// collectionobject.text3  Vernacularname  ** depreciated, moving to determination.alternatename
// collectionobject.text4  Frequency	
// fragment.text1   Herbarium acronym	[In web_search]
// reference.text1  Collation
// reference.text2  Year/Volumes
// determination.text1  Annotator (non-typifications) or Verifier (typifications)	
// determination.text2  Annotation Text		
//
// Other custom field uses: 
//
// Accession number for specimen in otheridentifier typed by Remarks=accession

// ****
// NOTE: Departures from Specify6 design.  
//
// HUH Specify6-botany modifies the base Specify6 data model.
// Instead of collectionobject->preparation Specify6-Botany uses
// collectionobject->fragment(item)<-preparation 
// where collecting events are associated with collection objects, and 
// determinations are associated with fragments.
// Either fragment or preparation may bear a barcode number (identifier).
// 

// ****
// NOTE: HUH Instance of Specify6-botany has special case handling of images:
//
// HUH Images are of collection objects
// HUH images are in separate set of IMAGE_ tables imported from legacy database ASA.

include_once('connection_library.php');
include_once('specify_library.php');

// value for $debug is set in specify_library.php
if ($debug) { 
    // See PHP documentation. 
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

echo pageheader('specimen');  // defined in function_lib.php

if ($connection) { 
	
	switch ($mode) {
	
		case "browse_families":
			echo browse("families");   // browse() is defined in specify_library.php
			break;
			
		case "browse_countries":       // browse() is defined in specify_library.php
			echo browse("countries");
			break;
			
		case "details":
			details();
			break;
			
		case "stats": 
			echo stats();  // stats() is defined in specify_library.php
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

echo pagefooter();   // defined in function_lib.php 

// ******* main code block ends here, supporting functions follow. *****

/** 
 * function details() retrieves a set of one or more collection objects and displays their
 *    details, drawing on fields from collecting event, locality, geography, fragment, determination
 *    preparation, etc.  
 * 
 * Has no return value, embeds calls to echo in order to display the results. 
 * 
 * @param none passed to function,  id obtained from GET as one collectionobjectid to display 
 * @param none passed to function,  id[] obtained from GET as array of collectionobjectids to display
 * @param none passed to function,  barcode obtained from GET and used to find collectionobject(s) to display (HUH)
 * @param none passed to function,  fragmentid obtained from GET and used to find collectionobject(s) to display (Specify6-Botany)
 * 
 */
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
		// Barcode number most likely in fragment.identifier, may also be in preparation.identifier.
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
		// There are no collectionobjectid values to retrieve.  Produce a suitable error message.
		if ($barcode!="") { 
			echo "<h2>No such barcode as [$barcode]</h2>";
		}
		if ($fragmentid!="") { 
			echo "<h2>No such fragmentid as [$fragmentid]</h2>";
		}
	} else { 
		// There are collectionobjectid values to retrieve.
		$oldid = "";
		// Retrieve collectionobject records by the collectionobjectid values.
		foreach($ids as $value) { 
			$id = substr(preg_replace("[^0-9]","",$value),0,20);
			
			// There might be duplicates next to each other in list of checkbox records from search results 
			// (from there being more than one current determination/typification for a specimen, or
			// from there being two specimens on one sheet), handle this by comparing with previous id in list.  
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
					" collectingevent.startdateprecision, collectingevent.enddateprecision, collectingevent.remarks as habitat, " .
					" collectingevent.verbatimlocality, collectionobject.text2 as substrate, collectionobject.text1 as host, " .
					" collectionobject.text3 as vernacularname, collectionobject.text4 as frequency " .
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
					$statement->bind_result($geoid, $lname, $lat1text, $lat2text, $long1text, $long2text, $datum, $latlongmethod, $AltCatalogNumber, $CollectionObjectID, $fieldnumber, $specimenRemarks, $verbatimdate, $startDate, $endDate, $maxElevation, $minElevation, $startdateprecision, $enddateprecision, $habitat, $verbatimlocality, $substrate, $host, $vernacularname, $frequency);
					$statement->store_result();
					if ($statement->num_rows()==0) { 
						echo "<h2>collectionobjectid [$id] not found.</h2>";
					} else { 
						while ($statement->fetch()) {
							// Retrieve  each collection object
							if ($debug) { echo "[$CollectionObjectID]"; }
							
							// Determine if this is a simple collection object (one collectionobject, one fragment, one preparation) or not. 
							$objectcomplexity = array();
							$query = "select count(*) from collectionobject c " .
									" left join fragment f on c.collectionobjectid = f.collectionobjectid " .
									" left join preparation p on f.preparationid = p.preparationid " .
									" left join fragment f2 on p.preparationid = f2.preparationid " .
									" where c.collectionobjectid = ? ";
							if ($debug) { echo "[$query]<BR>"; } 
							$statement_cmp = $connection->prepare($query);
							if ($statement_cmp) {
								$statement_cmp->bind_param("i",$id);
								$statement_cmp->execute();
								$statement_cmp->bind_result($objectfragmentprepfragmentrows);
								$statement_cmp->store_result();
								while ($statement_cmp->fetch()) { 
									if ($objectfragmentprepfragmentrows==1) { 
										//$objectcomplexity = "<tr><td class='cap'>Simple Object</td><td class='val'>This is a simple collection object (one sheet-item-preparation)</td></tr>";
										$objectcomplexity['Simple Object'] = "This is a simple collection object (one sheet-item-preparation)";
									} else { 
										//$objectcomplexity = "<tr><td class='cap'>Complex Object</td><td class='val'>This is a complex collection object ($objectfragmentprepfragmentrows sheet(s)-item(s)-preparation(s))</td></tr>";
										$objectcomplexity['Complex Object'] = "This is a complex collection object ($objectfragmentprepfragmentrows sheet(s)-item(s)-preparation(s))";
									}
								}
							}	    
							$statement_cmp->close();
							
							// Retrieve any other identifying numbers associated with this collection object.
							// other identifier is linked to collection object (original label).
							$otheridentifiers = array();
							$query = "select identifier, institution, remarks from otheridentifier where collectionobjectid = ? ";
							
							if ($debug===true) {  echo "[$query]<BR>"; }
							$statement_acc = $connection->prepare($query);
							if ($statement_acc) { 
								$statement_acc->bind_param("i",$id);
								$statement_acc->execute();
								$statement_acc->bind_result($identifier,$institution,$remarks);
								$statement_acc->store_result();
								while ($statement_acc->fetch()) {
									//$otheridentifiers .= "<tr><td class='cap'>Accession</td><td class='val'>$institution $identifier</td></tr>";
									// Note: otheridentifiers is a numeric array of values, not key-value pairs.
									$otheridentifiers[] = "$institution: $identifier [$remarks]";
								}
							} else { 
								echo "Error: " . $connection->error;
							}
							$statement_acc->close();
							
							// HUH Images are of collection objects
							// HUH images are in separate set of IMAGE_ tables imported from ASA.
							$images = array();
							$firstimage = array();
							$query = "select concat(url_prefix,uri) as url, pixel_height, pixel_width, t.name, file_size " .
								" from IMAGE_SET_collectionobject c left join IMAGE_OBJECT o on c.imagesetid = o.image_set_id " .
								" left join REPOSITORY r on o.repository_id = r.id " .
								" left join IMAGE_OBJECT_TYPE t on o.object_type_id = t.id " .
								" where c.collectionobjectid = ? and o.active_flag = 1 " .
								" order by object_type_id desc ";
							if ($debug===true) {  echo "[$query]<BR>"; }
							$statement_img = $connection->prepare($query);
							if ($statement_img) { 
								$statement_img->bind_param("i",$id);
								$statement_img->execute();
								$statement_img->bind_result($url,$height,$width,$imagename,$filesize);
								$statement_img->store_result();
								$fullurl = "";
								while ($statement_img->fetch()) { 
									if ($imagename == "Thumbnail") {
										//$firstimage .= "<tr><td class='cap'></td><td class='val'><a href='$fullurl'><img src='$url' height='205' width='150' alt='Thumbnail image of sheet' ></a></td></tr>";
										$firstimage[] = "<a href='$fullurl'><img src='$url' height='205' width='150' alt='Thumbnail image of sheet' ></a>";
									} else { 
										if ($imagename == "Full") { 
											$fullurl = $url;
										}
										$size = floor($filesize / 1024);
										$size = $size . " kb";
										//$images .= "<tr><td class='cap'></td><td class='val'>Image: <a href='$url'>$imagename</a> [$size]</td></tr>";
										$images[] .= "Image: <a href='$url'>$imagename</a> [$size]";
									}
								}
							} else { 
								echo "Error: " . $connection->error;
							}
							$statement_img->close();
							// **** End HUH Specific Block *****************
							// *********************************************
							
							// Locality and higher geography are tied to collecting events which are tied to collection objects.
							// Obtain higher geography
							// find the node numbers for the node that is linked to locality.
							$query = "select nodenumber, highestchildnodenumber " .
									" from geography " .
									" where geographyid = ? ";
							if ($debug) { echo "[$query]<BR>"; } 
							$statement_hg = $connection->prepare($query);
							if ($statement_hg) {
								$statement_hg->bind_param("i",$geoid);
								$statement_hg->execute();
								$statement_hg->bind_result($geonodenumber, $geohighestchildnodenumber);
								$statement_hg->store_result();
								while ($statement_hg->fetch()) { 
									// get the row
								}
							}	    
							$statement_hg->close();
							// find the path to root from that node in the geography tree.
							$geography = "";
							$country = "";
							$state = "";
							$query = "select g.rankid, g.name from geography g where g.highestchildnodenumber >= ? and g.nodenumber<= ? order by g.rankid";
							if ($debug===true) {  echo "[$query]<BR>"; }
							$statement_geo = $connection->prepare($query);
							if ($statement_geo) { 
								$statement_geo->bind_param("ii",$geohighestchildnodenumber, $geonodenumber);
								$statement_geo->execute();
								$statement_geo->bind_result($geoRank,$geoName);
								$statement_geo->store_result();
								$separator = "";
								$oldname = "";  // skip adjacent identical names if present in tree path.
								while ($statement_geo->fetch()) { 
									if ($geoName!='Earth' && $geoName!=$oldname) { 
									   $geography .= $separator.$geoName;
									   $separator = ": ";
									}
									if ($geoRank == "200") { $country = $geoName; } 
									if ($geoRank == "300") { $state = $geoName; }
									$oldname = $geoName; 
								}
							} else { 
								echo "Error: " . $connection->error;
							}
							$statement_geo->close();
							
							// Set up to be able to redact locality information for CITES species on external connections.
							$redactlocality = false;
							
							// collector is associated with collection objects through collecting events.
							$collector = "";
							$comma = "";
							// TODO: Add new collector.etal field 
							$query = "select agentvariant.name, agentvariant.agentid, collector.etal from collectionobject " .
									" left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid " .
									" left join collector on collectingevent.collectingeventid = collector.collectingeventid " .
									" left join agent on collector.agentid = agent.agentid " .
									" left join agentvariant on agent.agentid = agentvariant.agentid " .
									" where agentvariant.vartype = 4 and collectionobjectid = ? ";
							if ($debug===true) {  echo "[$query]<BR>"; }
							$statement_det = $connection->prepare($query);
							if ($statement_det) { 
								$statement_det->bind_param("i",$id);
								$statement_det->execute();
								$statement_det->bind_result($collectorName, $agentid, $etal);
								$statement_det->store_result();
								$separator = "";
								while ($statement_det->fetch()) { 
									$collector .= "$comma<a href='botanist_search.php?botanistid=$agentid'>$collectorName $etal</a>";
									$comma = "; ";
								}
							} else {
								echo "Error: " . $connection->error;
							}
							$statement_det->close();
							
							// ***********  Begin Fragments (Items) section ************** 
							// obtain the list of fragments (items) for this collection object, and query for fragment related elements.
							$query = "select fragmentid, provenance from fragment where collectionobjectid = ? ";  
							if ($debug===true) {  echo "[$query]<BR>"; }
							if ($debug===true) {  echo "CollectionObjectID=[".$id."]<BR>"; }
							$statement_frag = $connection->prepare($query);
							// Note: the array $itemarrray is an array of items, each of which contains a set of key-value pairs.
							// An item array is a list of key-value descriptors of that fragment(item).
							// Where the items with keys beginning with "Determination" are themesleves arrays of key-value pairs
							// A determination array is a list of key-value decriptors of an identification of a fragment(item).
							$itemarray = array();   // $itemarray[0] is the first item, a $item[] array
							$itemcount = 0;
							// obtain a full list of barcodes associated with this collection object
							$barcodelist = "";
							$barcodelistseparator = "";
							if ($statement_frag) { 
								$statement_frag->bind_param("i",$id);
								$statement_frag->execute();
								$statement_frag->bind_result($fragmentid, $provenance);
								$statement_frag->store_result();
								$fragmentcount = $statement_frag->num_rows();
								$itemheader = array();
								while ($statement_frag->fetch()) {
							        $item = array();        // Each $item array contains key - value pairs for the fields associated with that item.
									$itemcount ++; 
									//$items .= "<tr><td class='cap'>Item</td><td class='val'>$itemcount of $fragmentcount</td></tr>";
									$itemheader['Item'] = "$itemcount of $fragmentcount";
									$item[] = $itemheader;
									
									// **** HUH Specific *****
									// Acronym for herbarium is stored in fragment.text1
									$acronym = "";
									$query = "select text1 from fragment where fragment.fragmentid = ? ";
									if ($debug===true) {  echo "[$query]<BR>"; }
									if ($debug===true) {  echo "FragmentID=[". $fragmentid. "]<BR>"; }
									$statement_herb = $connection->prepare($query);
									if ($statement_herb) { 
										$statement_herb->bind_param("i",$fragmentid);
										$statement_herb->execute();
										$statement_herb->bind_result($text1);
										$statement_herb->store_result();
										$separator = "";
										while ($statement_herb->fetch()) { 
											$acronym = $text1;
										}
									} else { 
										echo "Error: " . $connection->error;
									}
									$statement_herb->close();
									
									// **** HUH Specific *****
									// Barcode number is in fragment.identifier or prepration.identifier
									// Obtain the barcodes associated with this fragment.  
									$query = "select distinct fragment.identifier, preparation.identifier " .
										" from fragment left join preparation on fragment.preparationid = preparation.preparationid " .
										" where fragment.fragmentid = ? ";
									if ($debug===true) {  echo "[$query]<BR>"; }
									$CatalogNumber = "";
									$fragments = array();
									$statement_bar = $connection->prepare($query);
									if ($statement_bar) { 
										$statement_bar->bind_param("i",$fragmentid);
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
										// obtain a full list of barcodes associated with this collection object
										$barcodelist .= $barcodelistseparator.$CatalogNumber;
										$barcodelistseparator = "; ";
									} else { 
										echo "Error: " . $connection->error;
									}
									//$items .= "<tr class='item_row' ><td class='cap'>Harvard University Herbaria Barcode</td><td class='val'>$CatalogNumber</td></tr>";
									//$items .= "<tr class='item_row'><td class='cap'>Herbarium</td><td class='val'>$acronym</td></tr>";
									//if ($provenance != "") { $items .= "<tr><td class='cap'>Provenance</td><td class='val'>$provenance</td></tr>"; } 
									$itemcuration = array();
									$itemcuration['Harvard University Herbaria Barcode'] = $CatalogNumber;
									$itemcuration['Herbarium'] = $acronym;
									if ($provenance != "") { $itemcuration['Provenance'].= "$provenance"; } 
									$item[] = $itemcuration;
									// get any references linked to the fragment.
									$query = "select r.title, r.text2 as volumes, r.referenceworkid " .
										" from referencework r left join fragmentcitation c on r.referenceworkid = c.referenceworkid " .
										" where c.fragmentid = ? ";
									if ($debug===true) {  echo "[$query]<BR>"; }
									$statement_ref = $connection->prepare($query);
									$fragmentcitations = array();
									if ($statement_ref) { 
										$statement_ref->bind_param("i",$fragmentid);
										$statement_ref->execute();
										$statement_ref->bind_result($title, $volumes,$referenceworkid);
										$statement_ref->store_result();
										$separator = "";
										while ($statement_ref->fetch()) { 
											if (trim($title)!="") { 
												//$fragmentcitations.= "<tr class='item_row'><td class='cap'>Reference</td><td class='val'>$title</td></tr>";
												$fragmentcitations['Item Reference'] = $title;
											}
										}
									} else {
										echo "Error: " . $connection->error;
									}
									$item[] = $fragmentcitations;
									
									// **** Specify6-Botany Specific *****
									// Determinations are associated with items (fragments).
									//$query = "select fullname, typeStatusName, determinedDate, isCurrent, determination.remarks, taxon.nodenumber from determination left join taxon on determination.taxonid = taxon.taxonid where determination.collectionobjectid = ? order by typeStatusName desc, isCurrent, determinedDate"; 
									$query = "select fullname, typeStatusName, confidence, qualifier, determinedDate, isCurrent, " .
										" determination.remarks, taxon.nodenumber, taxon.author, determination.text1 as verifier, citesstatus, taxon.taxonid, " .
										" getAgentName(agent.agentid), determination.text2 as annotationtext, determination.determinationid " .
										" from fragment " .
										" left join determination on fragment.fragmentid = determination.fragmentid " .
										" left join taxon on determination.taxonid = taxon.taxonid " .
										" left join agent on determinerid = agentid " .
										" where fragment.fragmentid = ? order by typeStatusName, isCurrent, determinedDate"; 
									if ($debug===true) {  echo "[$query]<BR>"; }
									if ($debug===true) {  echo "FragmentId=[$fragmentid]<BR>"; }
									$statement_det = $connection->prepare($query);
									$determinationcounter = 0;
									$determination = array();
									if ($statement_det) { 
										$statement_det->bind_param("i",$fragmentid);
										$statement_det->execute();
										$statement_det->bind_result($fullName, $typeStatusName, $confidence, $qualifier, $determinedDate, $isCurrent, 
										              $determinationRemarks, $nodenumber, $author, $verifier, $citesstatus, $taxonid,
										              $determineragent, $text2, $determinationid );
										$statement_det->store_result();
										$separator = "";
										$typeStatus = "";
										$nodes = array();
										while ($statement_det->fetch()) {
											// Retrieve one determination from the determination of this fragment(item)
											$determinationcounter++;   
											if ($debug===true) {  echo "TaxonID=[$taxonid]<BR>"; }
											$nodes[] = $nodenumber;  // Store the taxon's node number to look up higher taxonomy later.
											if ($determinationid != "") {
												if ($debug===true) { 
												    $determination['detnumber'] = $determinationcounter;
												}
												// retrieve determination/annotation details and store in an array  
												if (trim($typeStatusName)=="") { 
													$det = "Determination"; 
												    $taxonname = "$qualifier <em>$fullName</em> $author";
												    $determination["Determination"] = $taxonname;
												    $determination["Type Status"] = "";
												} else {
													$det = trim("$confidence $typeStatusName of");
												    $determination["Type Status"] = "$confidence $typeStatusName";
													// Append to list of type statuses for this collection object
													$typeStatus .= trim("$separator$confidence $typeStatusName");
													$separator = ", ";
												    $taxonname = "$qualifier <em>$fullName</em> $author";
												    $determination["$det"] = $taxonname;
												    $determination["Determination"] = "";
												}
												//$determinationHistory.= "<tr class='item_row'><td class='cap'>$det</td><td class='val'>$qualifier <em>$fullName</em> $author</td></tr>";
												// Determiner might be a text value
												// Determiner for non types, type status verifier for types.
												if (trim($verifier)!="") {
													if ($typeStatusName != "") { 
														//$determinationHistory.= "<tr class='item_row'><td class='cap'>Verified by</td><td class='val'>$verifier</td></tr>";
														$determination['Verified by'] = "$verifier";
														$determination['Determined by'] = "";
													}  else { 
														//$determinationHistory.= "<tr class='item_row'><td class='cap'>Determined by</td><td class='val'>$verifier</td></tr>";
														$determination['Determined by'] = "$verifier";
														$determination['Verified by'] = "";
													}
												}
												// Determiner might be an agent instead of a text value
												if (trim($determineragent)!="") {
													if ($typeStatusName != "") { 
														//$determinationHistory.= "<tr class='item_row'><td class='cap'>Verified by</td><td class='val'>$determineragent</td></tr>";
														$determination['Verified by'] = "$detrmineragent";
														$determination['Determined by'] = "";
													}  else { 
														//$determinationHistory.= "<tr class='item_row'><td class='cap'>Determined by</td><td class='val'>$determineragent</td></tr>";
														$determination['Determined by'] = "$determineragent";
														$determination['Verified by'] = "";
													}
												}
												if (trim($determinedDate)!="") { 
													$determinationHistory.= "<tr class='item_row'><td class='cap'>DateDetermined</td><td class='val'>$determinedDate</td></tr>";
													$determination['Date Determined'] = "$determinedDate";
												}
												if (trim($determinationRemarks)!="") { 
													$determinationHistory.= "<tr class='item_row'><td class='cap'>Determination Remarks</td><td class='val'>$determinationRemarks</td></tr>";
													$determination['Determination Remarks'] = "$determinationRemarks";
												}
												if (trim($text2)!="") { 
													$determinationHistory.= "<tr class='item_row'><td class='cap'>Annotation Text</td><td class='val'>$text2</td></tr>";
													$determination['Annotation Text'] = "$text2";
												}
												// If a CITES listed species, set redactlocality flag.
												if ($citesstatus != "None") {
													$redactlocality = true; 
												}
												if ($typeStatusName != "") {
													// If this has any type status (including 'Not a type'), link to taxon name reference.
													// Add any references that are linked to the taxon name
													$query = "select r.title, c.text1 as pages, c.text2 as year, r.referenceworkid " .
														" from referencework r left join taxoncitation c on r.referenceworkid = c.referenceworkid " .
														" where c.taxonid = ? ";
													if ($debug===true) {  echo "[$query]<BR>"; }
													$statement_ref = $connection->prepare($query);
													if ($statement_ref) { 
														$statement_ref->bind_param("i",$taxonid);
														$statement_ref->execute();
														$statement_ref->bind_result($title, $pages, $year, $referenceworkid);
														$statement_ref->store_result();
														$x= 0;
														while ($statement_ref->fetch()) {
															$x++; 
															if (trim($title)!="") { 
																if ($year!="") { $year = "$year."; }
																if ($pages!="") { $pages = "$pages."; }
																// $determinationHistory.= "<tr class='item_row'><td class='cap'>Reference</td><td class='val'><a href='publication_search.php?mode=details&id=$referenceworkid'>$title</a> $year $pages</td></tr>";
																$determination["Taxon Reference $x"] = "(for $taxonname) <a href='publication_search.php?mode=details&id=$referenceworkid'>$title</a> $year $pages";
															}
														}
													} else {
														echo "Error: " . $connection->error;
													}
												}
												// add this determination history item as an array element of $item 
												$det = array();
												if ($debug===true) {  echo "Determination [$determinationcounter][$determination]<BR>"; }
												$det[] = $determination;
												$item["Determination $determinationcounter"] = $det;
											} // end if $determinationid != ""
										}  // end retrieve list of determination/annotations of this fragment(item)
										if (count($nodes)>0) { 
											$oldhigher = "";
											$highertaxonomy = array();
											$highercount = 0;
											foreach ($nodes as $nodenumber) { 
												$query = "select taxon.name from taxon where taxon.nodenumber < ? and taxon.highestchildnodenumber > ? and rankid > 180 ";
												$statement_ht = $connection->prepare($query);
												if ($statement_ht) { 
													if ($debug===true) {  echo "[$query]<BR>"; }
													$statement_ht->bind_param("ii",$nodenumber,$nodenumber);
													$statement_ht->execute();
													$higherName = "";
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
													//$highertaxonomy.= "<tr><td class='cap'>Classification</td><td class='val'>$higher</td></tr>";
													if ($highercount==0) { 
													    $highertaxonomy['Classification'] = "$higher";
													} else { 
													    $highertaxonomy["Classification $highercount"] = "$higher";
													}
													$highercount++;
												}
											}
										}
										
									} else {
										echo "Error: " . $connection->error;
									}
									
									// **** Specify6-Botany Specific [Fragment] *****
									// Retrieve the fragment and preparation information about this fragment.
									$query = "select fragment.sex, fragment.phenology, preptype.name, fragment.identifier, preparation.identifier, " .
										" fragment.remarks, preparation.remarks, fragment.prepmethod, fragment.description, " .
										" fragment.text1 as herbarium, fragment.accessionnumber " .
										" from fragment left join preparation on fragment.preparationid = preparation.preparationid " .
										" left join preptype on preparation.preptypeid = preptype.preptypeid where fragment.fragmentid = ?";
									if ($debug===true) {  echo "[$query]<BR>"; }
									$statement_det = $connection->prepare($query);
									$attributes = array();
									if ($statement_det) { 
										$statement_det->bind_param("i",$fragmentid);
										$statement_det->execute();
										$statement_det->bind_result($sex, $phenology, $preptype, $fbarcode, $pbarcode, $fremarks, $premarks, $prepmethod, $description, $institution, $accessionnumber);
										$statement_det->store_result();
										$separator = "";
										while ($statement_det->fetch()) { 
											if (trim($accessionnumber)!="") { 
												//$attributes.= "<tr class='item_row'><td class='cap'>Accession Number</td><td class='val'>$institution $accessionnumber</td></tr>";
												$attributes['Accession Number'] = "$institution $accessionnumber";
											}
											if (trim($sex)!="") { 
												//$attributes.= "<tr class='item_row'><td class='cap'>Sex</td><td class='val'>$sex</td></tr>";
												$attributes['Sex'] = $sex;
											}
											if (trim($phenology)!="") { 
												//$attributes.= "<tr class='item_row'><td class='cap'>Phenology</td><td class='val'>$phenology</td></tr>";
												$attributes['Phenology'] = $phenology;
											}
											if (trim($preptype)!="") { 
												//$attributes.= "<tr class='item_row'><td class='cap'>Preparation Type</td><td class='val'>$preptype</td></tr>";
												$attributes['Preparation Type'] = $preptype;
											}
											if (trim($prepmethod)!="") { 
												//$attributes.= "<tr class='item_row'><td class='cap'>Preparation Method</td><td class='val'>$prepmethod</td></tr>";
												$attributes['Preparation Method'] = $prepmethod;
											}
											if (trim($fremarks)!="") { 
												//$attributes.= "<tr class='item_row'><td class='cap'>Item Remarks</td><td class='val'>$fremarks</td></tr>";
												$attributes['Item Remarks'] = $fremarks;
											}
											if (trim($premarks)!="") { 
												//$attributes.= "<tr class='item_row'><td class='cap'>Preparation Remarks</td><td class='val'>$premarks</td></tr>";
												$attributes['Preparation Remarks'] = $premarks;
											}
											if (trim($description)!="") { 
												//$attributes.= "<tr class='item_row'><td class='cap'>Description</td><td class='val'>$description</td></tr>";
												$attributes['Description'] = $description;
											}
										}
									} else {
										echo "Error: " . $connection->error;
									}
									// Add each attribute key-value pair to the item.
									$item[] = $attributes;
									
									// Copy the array of key-value pairs for this fragment(item) as an element in $itemarray
									$itemarray[] = $item;
								}
							} else {
								echo "Error: " . $connection->error;
							}  
							// **********  End fragments section ********** 
							
							
							// ********  Begin display information section *********** 
							// Manipulate data if needed
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
							if ($verbatimdate != "") { 
								$dateCollected .= " [$verbatimdate]";
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
							
							// **** Presentation:  Display results ******  
						    echo "<table class='h-object'>";
						    echo "<tr><td><table class='text'>\n";
							foreach ($highertaxonomy as $key => $value) { 
							      if (trim(value!=""))   { echo "<tr><td class='cap'>$key</td><td class='val'>$value</td></tr>"; }
							}
							foreach ($objectcomplexity as $key => $value) { 
							      if (trim(value!=""))   { echo "<tr><td class='cap'>$key</td><td class='val'>$value</td></tr>"; }
							}
							if (trim($barcodelist!=""))   { echo "<tr><td class='cap'>Harvard University Herbaria Barcode(s)</td><td class='val'>$barcodelist</td></tr>"; }
							// list of other identifiers for collection object is just array of values, not key-value pairs.
							foreach ($otheridentifiers as $value) { 
							      if (trim(value!=""))   { echo "<tr><td class='cap'>Other Number</td><td class='val'>$value</td></tr>"; }
							}
							if (trim($typeStatus!=""))   { echo "<tr><td class='cap'>Type Status</td><td class='val'>$typeStatus</td></tr>"; }
							echo "<tr><td class='cap'>Collector</td><td class='val'>$collector</td></tr>";
							if (trim($fieldnumber!="")) { echo "<tr><td class='cap'>Collector number</td><td class='val'>$fieldnumber</td></tr>"; } 
							if (trim($country!="")) { echo "<tr><td class='cap'>Country</td><td class='val'>$country</td></tr>"; } 
							if (trim($state!=""))   { echo "<tr><td class='cap'>State</td><td class='val'>$state</td></tr>"; }
							echo "<tr><td class='cap'>Geography</td><td class='val'>$geography</td></tr>";
							if (trim($lname !=""))  { 
								if ($redactlocality === true ) { $lname = "[Redacted]"; }
								echo "<tr><td class='cap'>Locality</td><td class='val'>$lname</td></tr>";
							}
							if (trim($verbatimlocality !=""))  { 
								if ($redactlocality === true ) { $verbatimlocality = "[Redacted]"; }
								echo "<tr><td class='cap'>Verbatim Locality</td><td class='val'>$verbatimlocality</td></tr>";
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
							if (trim($habitat!=""))   { echo "<tr><td class='cap'>Habitat</td><td class='val'>$habitat</td></tr>"; }
							if (trim($substrate!=""))   { echo "<tr><td class='cap'>Substrate</td><td class='val'>$substrate</td></tr>"; }
							if (trim($host!=""))   { echo "<tr><td class='cap'>Host</td><td class='val'>$host</td></tr>"; }
							if (trim($vernacularname!=""))   { echo "<tr><td class='cap'>Vernacular Name</td><td class='val'>$vernacularname</td></tr>"; }
							if (trim($frequency!=""))   { echo "<tr><td class='cap'>Frequency</td><td class='val'>$frequency</td></tr>"; }
							$itemcounter = 0;  // check if even or odd to distinguish alternate pairs of items.
							foreach ($itemarray as  $item) {
								$itemcounter ++;  
								$detcounter = 0;   // check if even or odd to distinguish alternate pairs of determinations 
								foreach ($item as $itemnumber => $values) {
									// These are elements of the item
									$detcounter ++; 
									foreach ($values as $key => $value) { 
										if (is_array($value)) {
											foreach ($value as $detkey => $detvalue) {
												// strip off unneeded numbers for taxon refences (1 for most or all cases
												if (substr($detkey,0,15)=="Taxon Reference") { $detkey = "Taxon Reference"; }
												if ($itemcounter % 2) { $rowclass = "item_row"; } else { $rowclass = "odd_item_row"; }
												if ($detcounter % 2) { $tdclass = "det_item_val"; } else { $tdclass = "odd_det_item_val"; }
												if (trim($detvalue!=""))   { echo "<tr class='$rowclass'><td class='det_item_cap'>$detkey</td><td class='$tdclass'>$detvalue</td></tr>"; }
											}
										} else {
											if ($itemcounter % 2) { $rowclass = "item_row"; } else { $rowclass = "odd_item_row"; }
											if (trim($value!=""))   { echo "<tr class='$rowclass'><td class='item_cap'>$key</td><td class='item_val'>$value</td></tr>"; }
										}
									}
								}
							}
							echo "</table></td>\n";
							echo "<td><table class='images'>\n";
							foreach ($firstimage as $value) { 
							      if (trim(value!=""))   { echo "<tr><td class='cap'></td><td class='val'>$value</td></tr>"; }
							}
							foreach ($images as $value) { 
							      if (trim(value!=""))   { echo "<tr><td class='cap'></td><td class='val'>$value</td></tr>"; }
							}
							echo "</table></td></tr>\n";
							if (trim($specimenRemarks!="")) { 
								echo "<tr><td colspan='2'><table class='remarks'>";
								echo "<td class='cap'>Remarks</td><td class='val'>$specimenRemarks</td></tr>";
								echo "</table></td></tr>";
							} 
						    echo "</table>";
						    echo "<BR>\n";
						}
					}  // end else num_rows>0
				}
				$statement->close();
			}
			$oldid = $id;
		}  // end foreach ids as value
	} // end else block countids<>0
}


/** 
 * function search() either runs a free text search on table web_quicksearch, or obtains a list of
 *     parameters from a GET submission and then constructs and runs an appropriate query on web_search.
 *     Some search parameters and search result conditions can produce results in the form of lists of 
 *     possible alternative search terms.  
 * 
 * search() has no return value, but uses echo to display the results of searches.
 * 
 * @param takes no parameters, but obtains quick from GET to run a free text search on web_quicksearch
 * @param takes no parameters, but obtains form parameters by GET from specimen_index.html to construct 
 *     and run searches on web_search;
 * 
 */
function search() {  
	global $connection, $errormessage, $debug;
	$question = "";
	$locality = "";
	$country = "";
	
	// ***** Step 1: Obtain query parameters and assemble query ***********
	$quick = substr(preg_replace("/[^A-Za-z\ \%\*\.0-9]/","", $_GET['quick']),0,59);
	if ($quick!="") { 
		// If a value was passed in _GET['quick'] then run free text search on quick_search table.
		$question .= "Quick Search :[$quick] (limit 100 records)<BR>";
		// Note: Changes to select field list need to be synchronized with query on web_search and bind_result below. 
		$query = "select distinct q.collectionobjectid,  c.family, c.genus, c.species, c.infraspecific, c.author, c.country, c.state, c.location, c.herbaria, c.barcode, i.imagesetid, c.datecollected " .
			" from web_quicksearch  q left join web_search c on q.collectionobjectid = c.collectionobjectid " .
			" left join IMAGE_SET_collectionobject i on q.collectionobjectid = i.collectionobjectid " .
			" where match (searchable) against (?) limit 100";
		$hasquery = true;
	} else {
		// Otherwise, obtain parameters from _GET[] and build a query on web_search table.
		$joins = "";
		$wherebit = " where "; 
		$and = "";
		$types = "";
		$parametercount = 0;
		$genus = substr(preg_replace("/[^A-Za-z_%*]/","", $_GET['gen']),0,59);
		$genus = str_replace("*","%",$genus);
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
		$istype = substr(preg_replace("/[^a-z]/","", $_GET['istype']),0,59);
		if ($istype=="on") { 
			$hasquery = true;
			$question .= "$and is a type ";
			$wherebit .= "$and web_search.typestatus is not null and web_search.typestatus <> 'Not a type' ";
			$and = " and ";
		}
		$typestatus = substr(preg_replace("/[^A-Za-z_%*]/","", $_GET['typestatus']),0,59);
		$typestatus = str_replace("*","%",$typestatus);
		if ($typestatus!="") {
			if ($typestatus=="any") {
			    $hasquery = true;
			    $question .= "$and type status is not null ";
			    $wherebit .= "$and web_search.typestatus is not null ";
			    $and = " and ";
			} else { 
			    $hasquery = true;
			    $question .= "$and type status:[$typestatus] ";
			    $types .= "s";
			    $operator = "=";
			    $parameters[$parametercount] = &$typestatus;
			    $parametercount++;
			    if (preg_match("/[%_]/",$typestatus))  { $operator = " like "; }
			    if ($typestatus=="Type") { 
			    	// use 'Type' as either of the vauge indications of possible type status
			        $wherebit .= "$and (web_search.typestatus = ? or web_search.typestatus = 'TypeMaterial') ";
			    } else { 
			        $wherebit .= "$and web_search.typestatus $operator ? ";
			    }
			    $and = " and ";
			} 
		}
		$hasimage = substr(preg_replace("/[^a-z]/","", $_GET['hasimage']),0,59);
		if ($hasimage=="on") { 
			$hasquery = true;
			$question .= "$and has an image ";
			$wherebit .= "$and i.collectionobjectid is not null ";
			$and = " and ";
		}
		$locality = substr(preg_replace("/[^A-Za-z%*]/","", $_GET['loc']),0,59);
		$locality = str_replace("*","%",$locality);
		if ($locality!="") { 
			// Search on both specific locality and on country/state/county.  
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
		$host = substr(preg_replace("/[^A-Za-z0-9 _%*\[\]\(\)\:\,\.]/","", $_GET['host']),0,100);
		$host = str_replace("*","%",$host);
		if ($host!="") { 
			$hasquery = true;
			$question .= "$and host:[$host] ";
			$types .= "s";
			$parameters[$parametercount] = &$host;
			$parametercount++;
			$operator = "=";
			if (preg_match("/[%_]/",$host))  { $operator = " like "; }
			$wherebit .= "$and web_search.host $operator ? ";
			$and = " and ";
		}
		$provenance = substr(preg_replace("/[^A-Za-z0-9 _%*\[\]\(\)\:\,\.]/","", $_GET['provenance']),0,100);
		$provenance = str_replace("*","%",$provenance);
		if ($provenance!="") { 
		    $provenance = "%$provenance%";   // automatic wildcard search for this field, values highly variable
			$hasquery = true;
			$question .= "$and provenance:[$provenance] ";
			$types .= "s";
			$parameters[$parametercount] = &$provenance;
			$parametercount++;
			$operator = "=";
			if (preg_match("/[%_]/",$provenance))  { $operator = " like "; }
			$wherebit .= "$and web_search.provenance $operator ? ";
			$and = " and ";
		}
		$substrate = substr(preg_replace("/[^A-Za-z0-9 _%*\[\]\(\)\:\,\.]/","", $_GET['substrate']),0,100);
		$substrate = str_replace("*","%",$substrate);
		if ($substrate!="") { 
			$hasquery = true;
			$question .= "$and substrate:[$substrate] ";
			$types .= "s";
			$parameters[$parametercount] = &$substrate;
			$parametercount++;
			$operator = "=";
			if (preg_match("/[%_]/",$substrate))  { $operator = " like "; }
			$wherebit .= "$and web_search.substrate $operator ? ";
			$and = " and ";
		}
		$habitat = substr(preg_replace("/[^A-Za-z0-9 _%*\[\]\(\)\:\,\.]/","", $_GET['habitat']),0,100);
		$habitat = str_replace("*","%",$habitat);
		if ($habitat!="") { 
			$hasquery = true;
			$question .= "$and habitat:[$habitat] ";
			$types .= "s";
			$parameters[$parametercount] = &$habitat;
			$parametercount++;
			$operator = "=";
			if (preg_match("/[%_]/",$habitat))  { $operator = " like "; }
			$wherebit .= "$and web_search.habitat $operator ? ";
			$and = " and ";
		}		
		$country = substr(preg_replace("/[^A-Za-z _%*\(\)\.]/","", $_GET['country']),0,59);
		$country = str_replace("*","%",$country);
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
		$species = substr(preg_replace("/[^A-Za-z _%*]/","", $_GET['sp']),0,59);
		$species = str_replace("*","%",$species);
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
		$infraspecific = substr(preg_replace("/[^A-Za-z _%*]/","", $_GET['infra']),0,59);
		$infraspecific = str_replace("*","%",$infraspecific);
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
		$author = substr(preg_replace("/[^A-Za-zÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ _%*]/","", $_GET['author']),0,59);
		$author = str_replace("*","%",$author);
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
		$collector = substr(preg_replace("/[^A-Za-zÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ _%*\.\,]/","", $_GET['cltr']),0,59);
		$collector = str_replace("*","%",$collector);
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
		$collectornumber = substr(preg_replace("/[^1-9\.A-Za-z _%*]/","", $_GET['collectornumber']),0,59);
		$collectornumber = str_replace("*","%",$collectornumber);
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
		$yearpublished = substr(preg_replace("/[^0-9 _%*]/","", $_GET['year']),0,59);
		$yearpublished = str_replace("*","%",$yearpublished);
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
		$yearcollected = substr(preg_replace("/[^0-9 _%*]/","", $_GET['yearcollected']),0,59);
		$yearcollected = str_replace("*","%",$yearcollected);
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
		// Note: Changes to select field list need to be synchronized with query on web_quicksearch above, and bind_result below. 
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
	
	// ***** Step 2: Run the query and assemble the results ***********
	if ($hasquery===true) { 
		$statement = $connection->prepare($query);
		if ($statement) { 
			if ($quick!="") { 
				$statement->bind_param("s",$quick);
			} else { 
				$array = Array();
				$array[] = $types;
				foreach($parameters as $par)
				$array[] = $par;
				call_user_func_array(array($statement, 'bind_param'),$array);
			}
			$statement->execute();
		    // Note: Changes to select field list need to be synchronized with queries on web_search and on web_quicksearch above. 
		    $CollectionObjectID = ""; $family = ""; $genus = ""; $species = ""; $infraspecific = "";
		    $author = ""; $country = ""; $state = ""; $locality = ""; $herbaria = ""; $barcode = ""; $imagesetid = ""; $datecollected = "";
			$statement->bind_result($CollectionObjectID,  $family, $genus, $species, $infraspecific, $author, $country, $state, $locality, $herbaria, $barcode, $imagesetid, $datecollected);
			$statement->store_result();
			
			echo "<div>\n";
			$count = $statement->num_rows;
			if ($count==1) { $s = ""; } else { $s = "es"; }
			echo "$count match$s to query ";
			echo "    <span class='query'>$question</span>\n";
			echo "</div>\n";
			echo "<HR>\n";
			
			if ($count > 0 ) {
				echo "<div id='image-key'><img height='16' alt='has image' src='images/leaf.gif' /> = with images</div>\n";
				echo "<form  action='specimen_search.php' method='get'>\n";
				echo "<input type='hidden' name='mode' value='details'>\n";
				echo "<input type='image' src='images/display_recs.gif' name='display' alt='Display selected records' />\n";
				echo "<BR><div>\n";
				$oldfamilylink = "";
				while ($statement->fetch()) { 
					$familylink = "<strong><a href='sepecimen_search.php?family=$family'>$family</a></strong>";
					if ($familylink != $oldfamilylink) {
						 echo "$familylink<BR>"; 
					}
					$oldfamilylink = $familylink;
					if (strlen($locality) > 12) { 
						$locality = substr($locality,0,11) . "...";
					}
					if (strlen($imagesetid)>0) { 
						$imageicon = "<img src='images/leaf.gif'>";
					} else {
						$imageicon = "";
					}
					$FullName = " <em>$genus $species $infraspecific</em> $author";
					$geography = "$country: $state $locality ";
					$specimenidentifier =  "<a href='specimen_search.php?mode=details&id=$CollectionObjectID'>$herbaria Barcode: $barcode</a>"; 
					echo "<input type='checkbox' name='id[]' value='$CollectionObjectID'> $specimenidentifier $FullName $geography $datecollected $imageicon";
					echo "<BR>\n";
				}
				echo "</div>\n";
				echo "<input type='image' src='images/display_recs.gif' name='display' alt='Display selected records' />\n";
				echo "</form>\n";
				
			} else {
				$errormessage .= "No matching results. ";
			}
	            // ***** Step 3: Optionally look for alternate possible search terms ***********
							if ($collector != "") {  
					$statement->close();
					// Look for possibly related collectors
					$query = " select  trim(ifnull(agentvariant.name,'')), count(collector.collectingeventid) " .
						" from collector left join agent on collector.agentid = agent.agentid " .
						" left join agentvariant on agent.agentid = agentvariant.agentid " .
						" where (agentvariant.name like ? or soundex(agentvariant.name) = soundex(?)) " .
						" and agentvariant.vartype =  4 " .
						" group by agentvariant.name, agent.agentid order by agentvariant.name, count(collector.collectingeventid) ";
					$wildcollector = "%$collector%";
					$plaincollector = str_replace("%","",$collector);
					$collectorparameters[0] = &$wildcollector;   // agentvariant like 
					$types = "s";
					$collectorparameters[1] = &$plaincollector;  // agentvariant soundex
					$types .= "s";
					//$collectorparameters[2] = &$wildcollector;   // agent like 
					//$types .= "s";
					//$collectorparameters[3] = &$plaincollector;  // agent soundex
					//$types .= "s";
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
								if ($count>1) { $s = "s"; } else { $s = ""; }
								echo "$highlightedcollector [<a href='specimen_search.php?mode=search&cltr=$collector'>$count record$s</a>]<br>";
							}
							echo "<BR>";
						}
					}
					
				}
				if ($host!= "" && preg_match('/[%_]/',$host)==0) {  
					// Look for possibly related hosts
					$query = " select text1 as host, count(collectionobjectid) " .
						" from collectionobject  " .
						" where text1 like ? or soundex(text1) = soundex(?) or text1 like ? " .
						" group by text1 order by text1, count(collectionobjectid) ";
					$wildhost = "%$host%";
					$plainhost = str_replace("%","",$host);
					$hostparameters[0] = &$wildhost;   // agentvariant like 
					$types = "s";
					$hostparameters[1] = &$plainhost;  // agentvariant soundex
					$types .= "s";
					$hostparameters[2] = &$wildhost;   // agent like 
					$types .= "s";
					if ($debug===true  && $hasquery===true) {
						echo "[$query][$wildhost][$plainhost][$wildhost]<BR>\n";
					}
					$statement = $connection->prepare($query);
					$searchhost = preg_replace("/[^A-Za-z ]/","", $plainhost);
					if ($statement) { 
						$array = Array();
						$array[] = $types;
						foreach($hostparameters as $par)
						$array[] = $par;
						call_user_func_array(array($statement, 'bind_param'),$array);
						$statement->execute();
						$statement->bind_result($host, $count);
						$statement->store_result();
						if ($statement->num_rows > 0 ) {
							echo "<h3>Possibly matching hosts</h3>";
							while ($statement->fetch()) {
								$highlightedhost = preg_replace("/$searchhost/","<strong>$plainhost</strong>",$host);
								if ($count>1) { $s = "s"; } else { $s = ""; }
								echo "$highlightedhost [<a href='specimen_search.php?mode=search&host=$host'>$count record$s</a>]<br>";
							}
							echo "<BR>";
						}
					}
					
				}
				if ($substrate!= "" && preg_match('/[%_]/',$substrate)==0) {  
					// Look for possibly related substrates
					$query = " select text2 as substrate, count(collectionobjectid) " .
						" from collectionobject  " .
						" where text2 like ? or soundex(text2) = soundex(?) or text2 like ? " .
						" group by text2 order by text2, count(collectionobjectid) ";
					$wildsubstrate = "%$substrate%";
					$plainsubstrate = str_replace("%","",$substrate);
					$substrateparameters[0] = &$wildsubstrate;   // agentvariant like 
					$types = "s";
					$substrateparameters[1] = &$plainsubstrate;  // agentvariant soundex
					$types .= "s";
					$substrateparameters[2] = &$wildsubstrate;   // agent like 
					$types .= "s";
					if ($debug===true  && $hasquery===true) {
						echo "[$query][$wildsubstrate][$plainsubstrate][$wildsubstrate]<BR>\n";
					}
					$statement = $connection->prepare($query);
					$searchsubstrate = preg_replace("/[^A-Za-z ]/","", $plainsubstrate);
					if ($statement) { 
						$array = Array();
						$array[] = $types;
						foreach($substrateparameters as $par)
						$array[] = $par;
						call_user_func_array(array($statement, 'bind_param'),$array);
						$statement->execute();
						$statement->bind_result($substrate, $count);
						$statement->store_result();
						if ($statement->num_rows > 0 ) {
							echo "<h3>Possibly matching substrates</h3>";
							while ($statement->fetch()) {
								$highlightedsubstrate = preg_replace("/$searchsubstrate/","<strong>$plainsubstrate</strong>",$substrate);
								if ($count>1) { $s = "s"; } else { $s = ""; }
								echo "$highlightedsubstrate [<a href='specimen_search.php?mode=search&substrate=$substrate'>$count record$s</a>]<br>";
							}
							echo "<BR>";
						}
					}
					
				}
                if ($habitat!= "" && preg_match('/[%_]/',$habitat)==0) {  
					// Look for possibly related habitats
					$query = " select habitat, count(collectionobjectid) " .
						" from web_search  " .
						" where habitat like ? or soundex(habitat) = soundex(?) or habitat like ? " .
						" group by habitat order by habitat, count(collectionobjectid) ";
					$wildhabitat = "%$habitat%";
					$plainhabitat = str_replace("%","",$habitat);
					$habitatparameters[0] = &$wildhabitat;   // agentvariant like 
					$types = "s";
					$habitatparameters[1] = &$plainhabitat;  // agentvariant soundex
					$types .= "s";
					$habitatparameters[2] = &$wildhabitat;   // agent like 
					$types .= "s";
					if ($debug===true  && $hasquery===true) {
						echo "[$query][$wildhabitat][$plainhabitat][$wildhabitat]<BR>\n";
					}
					$statement = $connection->prepare($query);
					$searchhabitat = preg_replace("/[^A-Za-z ]/","", $plainhabitat);
					if ($statement) { 
						$array = Array();
						$array[] = $types;
						foreach($habitatparameters as $par)
						$array[] = $par;
						call_user_func_array(array($statement, 'bind_param'),$array);
						$statement->execute();
						$statement->bind_result($habitat, $count);
						$statement->store_result();
						if ($statement->num_rows > 0 ) {
							echo "<h3>Possibly matching habitats</h3>";
							while ($statement->fetch()) {
								$highlightedhabitat = preg_replace("/$searchhabitat/","<strong>$plainhabitat</strong>",$habitat);
								if ($count>1) { $s = "s"; } else { $s = ""; }
								echo "$highlightedhabitat [<a href='specimen_search.php?mode=search&habitat=$habitat'>$count record$s</a>]<br>";
							}
							echo "<BR>";
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

// See PHP documentation. 
mysqli_report(MYSQLI_REPORT_OFF);

?>
