<?php
/*
 * Created on Dec 3, 2009
 *
 * Copyright © 2010 President and Fellows of Harvard College
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
			$query = "select firstname, lastname, remarks, dateofbirth, dateofbirthprecision, " .
				" dateofdeath, dateofdeathprecision, url, agentid, agenttype, initials as datetype " .
				" from agent where agent.agentid = ?  ";
			if ($debug) { echo "[$query]<BR>"; } 
			if ($debug) { echo "[$id]"; } 
			$statement = $connection->prepare($query);
			$agent = "";
			if ($statement) {
				$statement->bind_param("i",$id);
				$statement->execute();
				$statement->bind_result($firstname, $lastname, $remarks,$dateofbirth, $dateofbirthprecision, $dateofdeath, $dateofdeathprecision, $url,  $agentid, $agenttype, $datetype);
				$statement->store_result();
				while ($statement->fetch()) {
					$is_group = false;
					//$agent .=  "<tr><td class='cap'>Name</td><td class='val'>$lastname, $firstname</td></tr>";
					if ($agenttype=="3") { 
					      $is_group = true;
					      $agent .=  "<tr><td class='cap'>Agent type</td><td class='val'>Team/Group</td></tr>";
					}
					
					if ($dateofbirthprecision=="") { $dateofbirthprecision = 3;   } 
					if ($dateofdeathprecision=="") { $dateofdeathprecision = 3;   } 
					// Limit date of birth information for people who are living to the year of birth.
					if ($datetype=="birth") { 
					     if ($dateofdeath=="") { $dateofbirthprecision = 3;   } 
					}
					$dateofbirth = transformDateText($dateofbirth,$dateofbirthprecision);
					$dateofdeath = transformDateText($dateofdeath,$dateofdeathprecision);
						if ($datetype=="birth") { 
						   $startdatetext = "Date of birth"; 
						   $enddatetext = "Date of death"; 
						}
						if ($datetype=="fl") { 
						   $startdatetext = "First date flourished"; 
						   $enddatetext = "Last date flourished"; 
						}
						if ($datetype=="rec") { 
						   $startdatetext = "First date recieved"; 
						   $enddatetext = "Last date recieved"; 
						}
						if ($datetype=="coll") { 
						   $startdatetext = "First date collected"; 
						   $enddatetext = "Last date collected"; 
						}
					if (trim($dateofbirth!=""))   { $agent .= "<tr><td class='cap'>$startdatetext</td><td class='val'>$dateofbirth</td></tr>"; }
					if (trim($dateofdeath!=""))   { $agent .=  "<tr><td class='cap'>$enddatetext</td><td class='val'>$dateofdeath</td></tr>"; }
					
					$name = "$firstname $lastname";
					$numberofvariants = 0;
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
						$collectorname = "";
						while ($statement_var->fetch()) {
             						$numberofvariants++;
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
							       $collectorname = $name;
							       break;
							    case 5: 
							       $typestring = "Full Name";
							       break;
							}
							$agent .= "<tr><td class='cap'>$typestring</td><td class='val'>$name</td></tr>";
						}
						// set the name from the highest valued variant name found, or from first/last if no variants.
						
						// if a collector name wasn't found, set from any found name.
						if ($collectorname == "") { $collectorname = $name; }
					    $agent =  "<tr><td class='cap'>Name</td><td class='val'>$name</td></tr>" . $agent ;
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
						$query = "select lastname, agentid from groupperson left join agent on groupperson.groupid = agent.agentid where groupperson.memberid = ? order by ordernumber ";
						if ($debug) { echo "[$query]<BR>"; } 
						$statement_geo = $connection->prepare($query);
						if ($statement_geo) {
							$statement_geo->bind_param("i",$agentid);
							$statement_geo->execute();
							$statement_geo->bind_result($groupname,$groupagentid);
							$statement_geo->store_result();
							if ($statement_geo->num_rows()>0) {  
							$agent .= "<tr><td class='cap'>Member of teams/groups</td><td class='val'></td></tr>";
							while ($statement_geo->fetch()) {
								if ($groupname != "") {
								    $agent .= "<tr><td class='cap'>Member of</td><td class='val'><a href='botanist_search.php?mode=details&id=$groupagentid'>$groupname</a></td></tr>";
								}
							}
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
								$agent .= "<tr><td class='cap'>Holdings</td><td class='val'><a href='specimen_search.php?start=1&cltr=$collectorname'>Search for specimens collected by $collectorname</a></td></tr>";
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
							
						    $query = "select dateofbirth, startdate, dateofdeath, startdate < dateofbirth, initials as datetype " .
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
							    $statement_qc->bind_result($dob,$startdate,$dod, $beforebirth, $datetype);
							    $statement_qc->store_result();
							    if ($statement_qc->num_rows()>0) { 
									$agent .= "<tr><td class='cap'>Questionable records</td><td class='val'>Collecting Event Dates before birth or after death</td></tr>";
							        while ($statement_qc->fetch()) {
					                    $dod = transformDateText($dod,3);
					                    $dob = transformDateText($dob,3);
					                    $startdate = transformDateText($startdate,3);
					                    if ($beforebirth==1) {
					                    	$qc_message  = "Collected before $datetype"; 
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
				// Don't display details for agents that are only involved in transactions to users outside the herbarium. 
				if (!preg_match("/^140\.247\.98\./",$_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR']=='127.0.0.1') {
					if ($numberofvariants==0) {
						 $agent = "[Redacted]"; 
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

	$name = substr(preg_replace("/[^A-Za-zÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ,\. _%]/","", $_GET['name']),0,59);
	if ($name!="") { 
	    $soundslike = substr(preg_replace("/[^a-z]/","", $_GET['soundslike']),0,4);
		$hasquery = true;
		$namepad = "%$name%";
		$question .= "$and name:[$name] or name like:[$namepad]  ";
		$types .= "s";
		$operator = "=";
		$parameters[$parametercount] = &$namepad;
		$parametercount++;
		if ($soundslike=="true") { 
		    $question .= " or name sounds like [$name] ";
		    $types .= "s";
		    $parameters[$parametercount] = &$name;
		    $parametercount++;
		} 
		if (preg_match("/[%_]/",$name))  { $operator = " like "; }
		$wherebit .= "$and (agentvariant.name like ? ";
		if ($soundslike=="true") { 
		    $wherebit .= " or soundex(agentvariant.name)=soundex(?) ";
		} 
		$wherebit .= " )";
		$and = " and ";
	}
	$remarks = substr(preg_replace("/[^A-Za-zÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ,\. _%]/","", $_GET['remarks']),0,59);
	if ($remarks!="") { 
		$hasquery = true;
		$remarkpad = "%$remarks%";
		$question .= "$and remarks like:[$remarkpad] ";
		$types .= "s";
		$operator = "=";
		$parameters[$parametercount] = &$remarkpad;
		$parametercount++;
		$wherebit .= "$and agent.remarks like ? ";
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
	$is_collector= substr(preg_replace("/[^a-z]/","", $_GET['is_collector']),0,3);
	if ($is_collector=="on") { 
		$hasquery = true;
		$question .= "$and is a collector ";
		$wherebit .= "$and agentspecialty.role = 'Collector' ";
		if (!$joined_to_specialty) { 
		    $joins .= " left join agentspecialty on agent.agentid = agentspecialty.agentid ";
		}
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
	$query = "select agent.agentid, " .
		" agent.agenttype, agent.firstname, agent.lastname, agentvariant.name, year(agent.dateofbirth), year(agent.dateofdeath) " . 
		" from agent " .  
		" left join agentvariant on agent.agentid = agentvariant.agentid  $joins $wherebit order by agent.agenttype, agentvariant.name, agent.lastname, agent.firstname, agent.dateofbirth ";
	if ($debug===true  && $hasquery===true) {
		echo "[$query]<BR>\n";
		echo "[".phpversion()."]<BR>\n";
	}
	if ($hasquery===true) { 
		$statement = $connection->prepare($query);
		if ($statement) { 
			$array = Array();
			$array[] = $types;
			foreach($parameters as $par)
			    $array[] = $par;
			if (substr(phpversion(),0,4)=="5.3.") { 
			   // work around for bug in __call, or is it? 
			   // http://bugs.php.net/bug.php?id=50394
			   // http://stackoverflow.com/questions/2045875/pass-by-reference-problem-with-php-5-3-1
			   call_user_func_array(array($statement, 'bind_param'),make_values_referenced($array));
			} else {   
			   call_user_func_array(array($statement, 'bind_param'),$array);
			}
			$statement->execute();
			$statement->bind_result($agentid, $agenttype, $firstname, $lastname, $fullname, $yearofbirth, $yearofdeath);
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
				$lastpair = "";
				while ($statement->fetch()) {
					if ($lastpair != "$agentid$fullname")  {
						// omit identical agent records with identical names 
					    if ($agenttype==3)  { $team = "[Team]"; } else { $team = ""; }
					    if ($fullname=="") { $fullname = "$firstname $lastname"; }
					    if ($name != '') { 
					       $plainname = preg_replace("/[^A-Za-zÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ ]/","",$name);
					       $highlightedname = preg_replace("/$plainname/","<strong>$plainname</strong>","$fullname");
					    } else {
					       $highlightedname = $fullname;	 
					    }
					    echo "<input type='checkbox' name='id[]' value='$agentid'><a href='botanist_search.php?mode=details&id=$agentid'>$highlightedname</a> ($yearofbirth - $yearofdeath) $team";
					    echo "<BR>\n";
					}
					$lastpair = "$agentid$fullname";
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
