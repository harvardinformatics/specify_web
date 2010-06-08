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

if ($_GET['mode']!="")  {
	if ($_GET['mode']=="details") {
		$mode = "details"; 
	}
	if ($_GET['mode']=="stats") {
		$mode = "stats"; 
	}
}

if (preg_replace("[^0-9]","",$_GET['botanistid'])!="") { 
	$mode = "details"; 
}
if (preg_replace("[^0-9]","",$_GET['id'])!="") { 
	$mode = "details"; 
}

echo pageheader('agent'); 

if ($connection) { 
	
	switch ($mode) {
	
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


function details() { 
	global $connection, $errormessage, $debug;
	$id = $_GET['id'];
	if (is_array($id)) { 
		$ids = $id;
	} else { 
		$ids[0] = $id;
	}
	$botanistid = preg_replace("[^0-9]","",$_GET['botanistid']);
	if ($botanistid != "") {
		$ids[] = $botanistid;
	}
	$oldid = "";
	foreach($ids as $value) { 
		$id = substr(preg_replace("[^0-9]","",$value),0,20);
		// skip adgacent duplicates, if any
		if ($oldid!=$id)  { 
			$wherebit = "where agent.agentid = ? ";
			$query = "select lastname, firstname, remarks, dateofbirth, dateofbirthprecision, " .
				" dateofdeath, dateofdeathprecision, url, agentid " .
				" from agent  $wherebit";
			if ($debug) { echo "[$query]<BR>"; } 
			if ($debug) { echo "[$id]"; } 
			$statement = $connection->prepare($query);
			$agent = "";
			if ($statement) {
				$statement->bind_param("i",$id);
				$statement->execute();
				$statement->bind_result($lastname,$firstname, $remarks,$dateofbirth, $dateofbirthprecision, $dateofdeath, $dateofdeathprecision, $url,  $agentid);
				$statement->store_result();
				while ($statement->fetch()) {
					$agent .=  "<tr><td class='cap'>Name</td><td class='val'>$lastname, $firstname</td></tr>";
					// Limit date of birth information for people who are living to the year of birth.
					if ($dateofbirthprecision=="") { $dateofbirthprecision = 3;   } 
					if ($dateofdeathprecision=="") { $dateofdeathprecision = 3;   } 
					if ($dateofdeath=="") { $dateofbirthprecision = 3;   } 
					echo "[$dateofbirth][$dateofdeath][$dateofbirthprecision][$dateofdeathprecision]";
					$dateofbirth = transformDateText($dateofbirth,$dateofbirthprecision);
					$dateofdeath = transformDateText($dateofdeath,$dateofdeathprecision);
					echo "[$dateofbirth][$dateofdeath][$dateofbirthprecision][$dateofdeathprecision]";
					if (trim($dateofbirth!=""))   { $agent .= "<tr><td class='cap'>Date of birth</td><td class='val'>$dateofbirth</td></tr>"; }
					if (trim($dateofdeath!=""))   { $agent .=  "<tr><td class='cap'>Date of death</td><td class='val'>$dateofdeath</td></tr>"; }
					if (trim($remarks!=""))   { $agent .=  "<tr><td class='cap'>Remarks</td><td class='val'>$remarks</td></tr>"; }
					if (trim($url!=""))   { $agent .=  "<tr><td class='cap'>URL</td><td class='val'><a href='$url'>$url</a></td></tr>"; }
					$query = "select name, vartype from agentvariant where agentid = ? order by vartype ";
					if ($debug) { echo "[$query]<BR>"; } 
					$statement_var = $connection->prepare($query);
					if ($statement_var) {
						$statement_var->bind_param("i",$agentid);
						$statement_var->execute();
						$statement_var->bind_result($name,$type);
						$statement_var->store_result();
						while ($statement_var->fetch()) {
							// For types, see Specify  config/common/picklist.xml <picklist name="AgentVariant">
							$typestring = "Variant name";
							switch ($type) { 
							    case 0: 
							       $typestring = "Variant name";
							       break;
							    case 1: 
							       $typestring = "Vernacular name";
							       break;
							    case 2: 
							       $typestring = "Author name";
							       break;
							    case 3: 
							       $typestring = "B & P Author Abbrev.";
							       break;
							    case 4: 
							       $typestring = "Standard/Label Name";
							       break;
							    case 5: 
							       $typestring = "Full Name";
							       break;
							}
							$agent .= "<tr><td class='cap'>$typestring</td><td class='val'>$name</td></tr>";
						}
						$query = "select geography.name, agentgeography.role " .
							" from agentgeography left join geography on agentgeography.geographyid = geography.geographyid " .
							" where agentid = ? " .
							" order by agentgeography.role ";
						if ($debug) { echo "[$query]<BR>"; } 
						$statement_geo = $connection->prepare($query);
						if ($statement_geo) {
							$statement_geo->bind_param("i",$agentid);
							$statement_geo->execute();
							$statement_geo->bind_result($geography,$role);
							$statement_geo->store_result();
							while ($statement_geo->fetch()) {
								$agent .= "<tr><td class='cap'>Geography $role</td><td class='val'>$geography</td></tr>";
							}
						}
						$query = " select specialtyname, role, ordernumber from agentspecialty where agentid = ? order by role, ordernumber";
						if ($debug) { echo "[$query]<BR>"; } 
						$statement_geo = $connection->prepare($query);
						if ($statement_geo) {
							$statement_geo->bind_param("i",$agentid);
							$statement_geo->execute();
							$statement_geo->bind_result($specialty,$role,$order);
							$statement_geo->store_result();
							while ($statement_geo->fetch()) {
								$agent .= "<tr><td class='cap'>Specialty $role</td><td class='val'>$specialty</td></tr>";
							}
						}
						$query = "select remarks, role from agentcitation where agentid = ? ";
						if ($debug) { echo "[$query]<BR>"; } 
						$statement_geo = $connection->prepare($query);
						if ($statement_geo) {
							$statement_geo->bind_param("i",$agentid);
							$statement_geo->execute();
							$statement_geo->bind_result($citation,$role);
							$statement_geo->store_result();
							while ($statement_geo->fetch()) {
								$agent .= "<tr><td class='cap'>Citation as $role</td><td class='val'>$citation</td></tr>";
							}
						}
						$query = "select title, author.referenceworkid " .
								" from author left join referencework on author.referenceworkid = referencework.referenceworkid " .
								" where agentid = ? ";
						if ($debug) { echo "[$query]<BR>"; } 
						$statement_geo = $connection->prepare($query);
						if ($statement_geo) {
							$statement_geo->bind_param("i",$agentid);
							$statement_geo->execute();
							$statement_geo->bind_result($title,$referenceworkid);
							$statement_geo->store_result();
							while ($statement_geo->fetch()) {
								if ($title != "") {
									// TODO: Handle case of citations within works (where title of cited reference is null) 
								    $agent .= "<tr><td class='cap'>Author in </td><td class='val'><a href='publication_search.php?mode=details&id=$referenceworkid'>$title</a></td></tr>";
								}
							}
						}
						$query = "select count(collectionobjectid), year(startdate) " .
								" from collector left join collectingevent on collector.collectingeventid = collectingevent.collectingeventid " .
								" left join collectionobject on collectingevent.collectingeventid = collectionobject.collectingeventid " .
								" where agentid = ? " .
								" group by year(startdate)";
						if ($debug) { echo "[$query]<BR>"; } 
						$statement_geo = $connection->prepare($query);
						if ($statement_geo) {
							$statement_geo->bind_param("i",$agentid);
							$statement_geo->execute();
							$statement_geo->bind_result($count, $year);
							$statement_geo->store_result();
							if ($statement_geo->num_rows()>0 ) {
								$agent .= "<tr><td class='cap'>Holdings</td><td class='val'><a href='specimen_search.php?start=1&cltr=$lastname'>Search for specimens collected by $lastname</a></td></tr>";
								while ($statement_geo->fetch()) {
									// for each year
									// obtain the list of collection objects collected by this collector in this year
									if ($year=="") {
										$query = "select collectionobjectid " .
											" from collector left join collectingevent on collector.collectingeventid = collectingevent.collectingeventid " .
											" left join collectionobject on collectingevent.collectingeventid = collectionobject.collectingeventid " .
											" where agentid = ? and year(startdate) is null ";
									} else { 
										$query = "select collectionobjectid " .
											" from collector left join collectingevent on collector.collectingeventid = collectingevent.collectingeventid " .
											" left join collectionobject on collectingevent.collectingeventid = collectionobject.collectingeventid " .
											" where agentid = ? and year(startdate) = ? ";
									}
									$statement_co = $connection->prepare($query);
									$link = "";
									if ($statement_co) {
										$link = "href='specimen_search.php?mode=details";
									    if ($year=="") {
											$statement_co->bind_param("i",$agentid);
									    } else { 
											$statement_co->bind_param("ii",$agentid,$year);
									    }
										$statement_co->execute();
										$statement_co->bind_result($collectionobjectid);
										$statement_co->store_result();
								        while ($statement_co->fetch()) {
								        	$link .= "&id[]=$collectionobjectid";
										}
										$link .= "'";
									}
									$statement_co->close();
									// for each year, provide the count of the number of collections objects held with a link to those collection objects.
									if ($year=="") { $year = "[no date]"; }
									$agent .= "<tr><td class='cap'>Collections in</td><td class='val'><a $link>$year ($count)</a></td></tr>";
								} 
							}
						}
						// If internal, check for out of range collecting events: 
						if (preg_match("/^140\.247\.98\./",$_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR']=='127.0.0.1') { 
							
						    $query = "select dateofbirth, startdate, dateofdeath, startdate < dateofbirth " .
							    	" from agent left join collector on agent.agentid = collector.agentid " .
								    " left join collectingevent on collector.collectingeventid = collectingevent.collectingeventid " .
								    " where startdate is not null " .
								    " and (startdate < dateofbirth or startdate > dateofdeath) " .
								    " and agent.agentid = ? ";
						    if ($debug) { echo "[$query]<BR>"; } 
						    $statement_qc = $connection->prepare($query);
						    if ($statement_qc) {
							    $statement_qc->bind_param("i",$agentid);
							    $statement_qc->execute();
							    $statement_qc->bind_result($dob,$startdate,$dod, $beforebirth);
							    $statement_qc->store_result();
							    if ($statement_qc->num_rows()>0) { 
									$agent .= "<tr><td class='cap'>Questionable records</td><td class='val'>Collecting Event Dates before birth or after death</td></tr>";
							        while ($statement_qc->fetch()) {
					                    $dod = transformDateText($dod,3);
					                    $dob = transformDateText($dob,3);
					                    $startdate = transformDateText($startdate,3);
					                    if ($beforebirth==1) {
					                    	$qc_message  = "Collected before birth"; 
									        $agent .= "<tr><td class='cap'>$qc_message</td><td class='val'>Coll:$startdate DOB: $dob</td></tr>";
					                    } else { 
					                    	$qc_message  = "Collected after death"; 
									        $agent .= "<tr><td class='cap'>$qc_message</td><td class='val'>DOD:$dod Coll:$startdate</td></tr>";
					                    }
							        }
							    }
						    }
						}
					}
					
				}
				echo "<table>";
				echo "$agent";
				echo "</table>";
				$statement->close();
			}
			$oldid = $id;
		}
	}
}


function search() {  
	global $connection, $errormessage, $debug;
	$question = "";
	$country = "";
	$joins = "";
	$joined_to_specialty = false;
	$joined_to_geography = false;
	$wherebit = " where "; 
	$and = "";
	$types = "";
	$parametercount = 0;
	$name = substr(preg_replace("/[^A-Za-z,\. _%]/","", $_GET['name']),0,59);
	if ($name!="") { 
		$hasquery = true;
		$namepad = "%$name%";
		$question .= "$and lastname:[$name] or name like:[$namepad] ";
		$types .= "sss";
		$operator = "=";
		$parameters[$parametercount] = &$name;
		$parametercount++;
		$parameters[$parametercount] = &$name;
		$parametercount++;
		$parameters[$parametercount] = &$namepad;
		$parametercount++;
		if (preg_match("/[%_]/",$name))  { $operator = " like "; }
		$wherebit .= "$and (agent.lastname $operator ? or soundex(agent.lastname)=soundex(?) or agentvariant.name like ? )";
		$and = " and ";
	}
	$is_author = substr(preg_replace("/[^a-z]/","", $_GET['is_author']),0,3);
	if ($is_author=="on") { 
		$hasquery = true;
		$question .= "$and is a taxon author ";
		$wherebit .= "$and agentspecialty.role = 'Author' ";
		$joins .= " left join agentspecialty on agent.agentid = agentspecialty.agentid ";
		$joined_to_specialty = true;
		$and = " and ";
	}
	$team = substr(preg_replace("/[^a-z]/","", $_GET['team']),0,3);
	if ($team=="on") { 
		$hasquery = true;
		$question .= "$and is a team/group ";
		$wherebit .= "$and agent.agenttype = 3 ";
		$and = " and ";
	}
	$specialty = substr(preg_replace("/[^A-Za-z\-\ ]/","", $_GET['specialty']),0,59);
	if ($specialty!="") { 
		$hasquery = true;
		$question .= "$and author specialty:[$authorspecialty] ";
		$types .= "s";
		$operator = "=";
		$parameters[$parametercount] = &$specialty;
		$parametercount++;
		if (preg_match("/[%_]/",$specialty))  { $operator = " like "; }
		$wherebit .= "$and agentspecialty.specialtyname $operator ? ";
		if (!$joined_to_specialty) { 
			$joins .= " left join agentspecialty on agent.agentid = agentspecialty.agentid ";
		}
		$and = " and ";
	}
	$country = substr(preg_replace("/[^A-Za-z\ ,\.\(\)]/","_", $_GET['country']),0,59);
	if ($country!="") { 
		$hasquery = true;
		$question .= "$and country:[$country] ";
		$types .= "s";
		$operator = "=";
		$parameters[$parametercount] = &$country;
		$parametercount++;
		if (preg_match("/[%_]/",$country))  { $operator = " like "; }
		$wherebit .= "$and geography.name $operator ? ";
		if (!$joined_to_geography) { 
			$joins .= " left join agentgeography on agent.agentid = agentgeography.agentid " .
				" left join geography on agentgeography.geographyid = geography.geographyid ";
		}
		$and = " and ";
	}
	if ($question!="") {
		$question = "Search for $question <BR>";
	} else {
		$question = "No search criteria provided.";
	}
	$query = "select distinct agent.agentid, " .
		" agent.agenttype, agent.firstname, agent.lastname, year(agent.dateofbirth), year(agent.dateofdeath) " . 
		" from agent " .  
		" left join agentvariant on agent.agentid = agentvariant.agentid  $joins $wherebit order by agent.agenttype, agent.lastname, agent.firstname, agent.dateofbirth ";
	if ($debug===true  && $hasquery===true) {
		echo "[$query]<BR>\n";
	}
	if ($hasquery===true) { 
		$statement = $connection->prepare($query);
		if ($statement) { 
			$array = Array();
			$array[] = $types;
			foreach($parameters as $par)
			$array[] = $par;
			call_user_func_array(array($statement, 'bind_param'),$array);
			$statement->execute();
			$statement->bind_result($agentid, $agenttype, $firstname, $lastname, $yearofbirth, $yearofdeath);
			$statement->store_result();
			echo "<div>\n";
			echo $statement->num_rows . " matches to query ";
			echo "    <span class='query'>$question</span>\n";
			echo "</div>\n";
			echo "<HR>\n";
			
			if ($statement->num_rows > 0 ) {
				echo "<form  action='botanist_search.php' method='get'>\n";
				echo "<input type='hidden' name='mode' value='details'>\n";
				echo "<input type='image' src='images/display_recs.gif' name='display' alt='Display selected records' />\n";
				echo "<BR><div>\n";
				while ($statement->fetch()) { 
					if ($agenttype==3)  { $team = "[Team]"; } else { $team = ""; }
					echo "<input type='checkbox' name='id[]' value='$agentid'> <a href='botanist_search.php?mode=details&id=$agentid'>$lastname, $firstname</a> ($yearofbirth - $yearofdeath) $team";
					echo "<BR>\n";
				}
				echo "</div>\n";
				echo "<input type='image' src='images/display_recs.gif' name='display' alt='Display selected records' />\n";
				echo "</form>\n";
			} else {
				$errormessage .= "No matching results. ";
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
