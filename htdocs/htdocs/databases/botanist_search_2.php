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
$debug=true;

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

$json = FALSE;
if (preg_replace("/[^a-z]/","",$_GET['json'])!="") {
    if ($_GET['json']=='y') {
	   $json=TRUE;
    }
}
if (preg_replace("/[^0-9]/","",$_GET['botanistid'])!="") {
	$mode = "details";
}
if (preg_replace("/[^0-9]/","",$_GET['id'])!="") {
	$mode = "details";
}
if (preg_replace("/[^0-9A-Za-z\-]/","",$_GET['botanistguid'])!="") {
	$mode = "details";
}

if (!$json) {
   echo pageheader('agent');
}

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

if (!$json) {
   echo pagefooter();
}

// ******* main code block ends here, supporting functions follow. *****


function details() {
	global $connection, $errormessage, $debug;

	$id = $_GET['id'];
	$collectorid = $id;
	if (is_array($id)) {
		$ids = $id;
	} else {
		$ids[0] = $id;
	}
    $uuid = preg_replace("/[^0-9A-Za-z\-]/","",$_GET['botanistguid']);
    if ($uuid != "") {
        $query = "select primarykey, state from guids where tablename = 'agent' and uuid = ? ";
        if ($debug) { echo "[$uuid]"; }
        $statement = $connection->prepare($query);
        $agent = "";
        if ($statement) {
           $statement->bind_param("s",$uuid);
           $statement->execute();
           $statement->bind_result($primarykey, $state);
           $statement->store_result();
           while ($statement->fetch()) {
               $botanistid = $primarykey;
               if ($state!='') {
                  echo "$uuid HUH Botanist $state";
               }
           }
        }
    } else {
	    $botanistid = preg_replace("/[^0-9]/","",$_GET['botanistid']);
    }
	if ($botanistid != "") {
		$ids[] = $botanistid;
	}
	$oldid = "";
	foreach($ids as $value) {
		$id = substr(preg_replace("/[^0-9]/","",$value),0,20);
		// skip adgacent duplicates, if any
		if ($oldid!=$id)  {
                        // See edu.ku.brc.specify.datamodel.Agent
                        // Definitions for agentype:
                        //  public static final byte                ORG    = 0;
                        //  public static final byte                PERSON = 1;
                        //  public static final byte                OTHER  = 2;
                        //  public static final byte                GROUP  = 3;
                        // Definitions for datestype:
                        //  public static final byte                BIRTH              = 0;
                        //  public static final byte                FLOURISHED         = 1;
                        //  public static final byte                COLLECTED          = 2;
                        //  public static final byte                RECEIVED_SPECIMENS = 3;
			$query = "select guids.uuid " .
				" from agent left join guids on agent.agentid = guids.primarykey " .
                                " where guids.tablename = 'agent' and agent.agentid = ?  ";
			if ($debug) { echo "[$query]<BR>"; }
			$statement = $connection->prepare($query);
			$uuid = "";
			if ($statement) {
				$statement->bind_param("i",$id);
				$statement->execute();
				$statement->bind_result($uuidfetch);
				$statement->store_result();
				while ($statement->fetch()) {
                                   $uuid = $uuidfetch;
                                }
                        }
			$query = "select firstname, lastname, remarks, dateofbirth, dateofbirthprecision, " .
				" dateofdeath, dateofdeathprecision, url, agentid, agenttype, datestype as datetype, dateofbirthconfidence, dateofdeathconfidence, agent.guid " .
				" from agent where agent.agentid = ?  ";
			if ($debug) { echo "[$query]<BR>"; }
			if ($debug) { echo "[$id]"; }
			$statement = $connection->prepare($query);
			$agent = "";
			if ($statement) {
				$statement->bind_param("i",$id);
				$statement->execute();
				$statement->bind_result($firstname, $lastname, $remarks,$dateofbirth, $dateofbirthprecision, $dateofdeath, $dateofdeathprecision, $url,  $agentid, $agenttype, $datetype, $dateofbirthconfidence, $dateofdeathconfidence, $botanistid);
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
					if ($datetype=="0") {
					     if ($dateofdeath=="") { $dateofbirthprecision = 3;   }
					}

                    // temporary workaround for dates not being editable below year.
                    if (substr($dateofbirth,4,10)=="-01-01") {
                       $dateofbirthprecision = 3;
                    }
                    if (substr($dateofdeath,4,10)=="-01-01") {
                       $dateofdeathprecision = 3;
                    }

					$dateofbirth = transformDateText($dateofbirth,$dateofbirthprecision);
					$dateofdeath = transformDateText($dateofdeath,$dateofdeathprecision);
                                        $startdatetext = "Start Date";
                                        $enddatetext = "End Date";
						if ($datetype=="0") {
						   $startdatetext = "Date of birth";
						   $enddatetext = "Date of death";
						}
						if ($datetype=="1") {
						   $startdatetext = "First date flourished";
						   $enddatetext = "Last date flourished";
						}
						if ($datetype=="3") {
						   $startdatetext = "First date received";
						   $enddatetext = "Last date received";
						}
						if ($datetype=="2") {
						   $startdatetext = "First date collected";
						   $enddatetext = "Last date collected";
						}
					if (trim($dateofbirth!=""))   { $agent .= "<tr><td class='cap'>$startdatetext</td><td class='val'>$dateofbirth $dateofbirthconfidence</td></tr>"; }
					if (trim($dateofdeath!=""))   { $agent .=  "<tr><td class='cap'>$enddatetext</td><td class='val'>$dateofdeath $dateofdeathconfidence</td></tr>"; }

					$name = "$firstname $lastname";
					$numberofvariants = 0;
					if (trim($remarks!=""))   { $agent .=  "<tr><td class='cap'>Remarks</td><td class='val'>$remarks</td></tr>"; }
					if (trim($url!=""))   { $agent .=  "<tr><td class='cap'>URL</td><td class='val'><a href='$url'>$url</a></td></tr>"; }
					if (trim($botanistid!=""))   { $agent .=  "<tr><td class='cap'>ASA Botanist ID</td><td class='val'>$botanistid</td></tr>"; }
					if (trim($uuid!=""&& ($agenttype==1 || $agenttype==3)))   { $agent .=  "<tr><td class='cap'>GUID</td><td class='val'><a href='http://purl.oclc.org/net/edu.harvard.huh/guid/uuid/$uuid'>http://purl.oclc.org/net/edu.harvard.huh/guid/uuid/$uuid</a></td></tr>"; }
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
							       $typestring = "Vernacular&nbsp;name";
							       break;
							    case 2:
							       $typestring = "Author name";
							       break;
							    case 3:
							       $typestring = "B&nbsp;&amp;&nbsp;P&nbsp;Author&nbsp;Abbrev.";
							       break;
							    case 4:
							       $typestring = "Standard/Label&nbsp;Name";
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

                                                        $images = array();
                                                        $firstimage = array();
                                                        $query = "select concat(url_prefix,uri) as url, pixel_height, pixel_width, t.name, file_size, ims.caption " .
                                                                " from IMAGE_SET_agent c " .
                                                                " left join IMAGE_SET ims on c.imagesetid = ims.id " .
                                                                " left join IMAGE_OBJECT o on ims.id = o.image_set_id " .
                                                                " left join REPOSITORY r on o.repository_id = r.id " .
                                                                " left join IMAGE_OBJECT_TYPE t on o.object_type_id = t.id " .
                                                                " where c.agentid = ? " .
                                                                " order by object_type_id desc ";
                                                        if ($debug===true) {  echo "[$query]<BR>"; }
                                                        $statement_img = $connection->prepare($query);
                                                        if ($statement_img) {
                                                                $statement_img->bind_param("i",$agentid);
                                                                $statement_img->execute();
                                                                $statement_img->bind_result($url,$height,$width,$imagename,$filesize,$caption);
                                                                $statement_img->store_result();
                                                                $fullurl = "";
                                                                $thumb = "";
                                                                $imnum = 0;
                                                                while ($statement_img->fetch()) {
                                                                        if ($imagename == "Thumbnail") {
                                                                                //$firstimage .= "<tr><td class='cap'></td><td class='val'><a href='$fullurl'><img src='$url' height='205' width='150' alt='Thumbnail image of sheet' ></a></td></tr>";
                                                                                $thumb = "<a href='$fullurl'><img src='$url' height='205' width='150' alt='Thumbnail image $caption' ></a>";
                                                                                $images[$imnum] .=  " $thumb";
                                                                        } elseif ($imagename=='PDS') {
                                                                                $images[] .= "Images: <a href='$url'>$imagename</a> [Page Turned Object] $caption";
                                                                        } else {
                                                                                if ($imagename == "Full") {
                                                                                        $fullurl = $url;
                                                                                }
                                                                                $size = floor($filesize / 1024);
                                                                                $size = $size . " kb";
                                                                                //$images .= "<tr><td class='cap'></td><td class='val'>Image: <a href='$url'>$imagename</a> [$size]</td></tr>";
                                                                                $images[] .= "Image: <a href='$url'>$imagename</a> [$size] $caption";
                                                                                $imnum ++;
                                                                        }
                                                                }
                                                        } else {
                                                                echo "Error: " . $connection->error;
                                                        }
                                                        $statement_img->close();

                                                        foreach ($images as $value) {
                                                              if (trim(value!=""))   {
					                           $agent =  $agent . "<tr><td class='cap'>Image</td><td class='val'>$value</td></tr>";
							      }
                                                        }


						$query = "select geography.name, agentgeography.role " .
							" from agentgeography left join geography on agentgeography.geographyid = geography.geographyid " .
							" where agentid = ? " .
							" order by agentgeography.role, geography.name ";
						if ($debug) { echo "[$query]<BR>"; }
						$statement_geo = $connection->prepare($query);
						if ($statement_geo) {
							$statement_geo->bind_param("i",$agentid);
							$statement_geo->execute();
							$statement_geo->bind_result($geography,$role);
							$statement_geo->store_result();
                                                        $authorgeographies = "";
                                                        $authorgeographiesseparator = "";
                                                        $collgeographies = "";
                                                        $collgeographiesseparator = "";
							while ($statement_geo->fetch()) {
                                                                if ($role=="Author") {
                                                                   $authorgeographies .= "$authorgeographiesseparator$geography";
                                                                   $authorgeographiesseparator = ",&nbsp; ";
                                                                } else if ($role=="Collector") {
                                                                   $collgeographies .= "$collgeographiesseparator$geography";
                                                                   $collgeographiesseparator = ",&nbsp; ";
                                                                } else {

								   $agent .= "<tr><td class='cap'>Geography $role</td><td class='val'>$geography</td></tr>";
                                                                }
							}
                                                        if ($authorgeographies!="") {
						            $agent .= "<tr><td class='cap'>Geography Author</td><td class='val'>$authorgeographies</td></tr>";
                                                        }
                                                        if ($collgeographies!="") {
								   $agent .= "<tr><td class='cap'>Geography Collector</td><td class='val'>$collgeographies</td></tr>";
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
						$query = "select c.remarks, c.role, w.text1 from agentcitation c left join referencework w on c.referenceworkid = w.referenceworkid where c.agentid = ? ";
						if ($debug) { echo "[$query]<BR>"; }
						$statement_geo = $connection->prepare($query);
						if ($statement_geo) {
							$statement_geo->bind_param("i",$agentid);
							$statement_geo->execute();
							$statement_geo->bind_result($citation,$role,$citationtitle);
							$statement_geo->store_result();
							while ($statement_geo->fetch()) {
								$agent .= "<tr><td class='cap'>Citation as $role</td><td class='val'>$citation $citationtitle</td></tr>";
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
      								$ucollectorname = urlencode($collectorname);
								$agent .= "<tr><td class='cap'>Holdings</td><td class='val'><a href='specimen_search.php?start=1&cltrid=$id'>Search for specimens collected by $collectorname</a></td></tr>";
                                                                $collist = "";
                                                                $collistseparator = "";
								while ($statement_geo->fetch()) {
									$searchyear = $year;
									if ($year=="") {
										$year = "[no date]";
										$searchyear=0;
									}
									//$agent .= "<tr><td class='cap'>Collections in</td><td class='val'><a $link>$year ($count)</a></td></tr>";
                  $collist .= "$collistseparator<a href='specimen_search.php?cltrid=$id&yearcollected=$searchyear'>$year ($count)</a>";
                  $collistseparator = ",&nbsp; ";
								}
							    $agent .= "<tr><td class='cap'>Collections in</td><td class='val'>$collist</td></tr>";
							}
						}
						// If internal, check for out of range collecting events:
						if (preg_match("/^140\.247\.98\./",$_SERVER['REMOTE_ADDR']) ||
						    preg_match("/^10\.1\.147\./",$_SERVER['REMOTE_ADDR']) ||
						    preg_match("/^140\.247\.98\./",$_SERVER['HTTP_X_FORWARDED_FOR']) ||
						    preg_match("/^10\.1\.147\./",$_SERVER['HTTP_X_FORWARDED_FOR']) ||
						    $_SERVER['REMOTE_ADDR']=='127.0.0.1') {

                                                    // Definitions for datestype:
                                                    //  public static final byte                BIRTH              = 0;
                                                    //  public static final byte                FLOURISHED         = 1;
                                                    //  public static final byte                COLLECTED          = 2;
                                                    //  public static final byte                RECEIVED_SPECIMENS = 3;
						    $query = "select dateofbirth, startdate, dateofdeath, startdate < dateofbirth, datestype as datetype " .
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
                                                                    if ($datetype==0) {
									$agent .= "<tr><td class='cap'>Questionable records</td><td class='val'>Collecting Event Dates before birth or after death.</td></tr>";
                                                                    } else {
									$agent .= "<tr><td class='cap'>Questionable records</td><td class='val'>Collecting Event Dates outside known range for collector.</td></tr>";
                                                                    }
							        while ($statement_qc->fetch()) {
					                    $dod = transformDateText($dod,3);
					                    $dob = transformDateText($dob,3);
					                    $startdate = transformDateText($startdate,3);
					                    if ($beforebirth==1) {
                                                                switch ($datetype) {
                                                                   case 0:
                                                                      $datetypetext = "Birth";  $dtt = "DOB";
                                                                      break;
                                                                   case 1:
                                                                      $datetypetext = "Collector Flourished";  $dtt = "Flourished from";
                                                                      break;
                                                                   case 2:
                                                                      $datetypetext = "First Known Collection"; $dtt = "Collected from";
                                                                      break;
                                                                   case 3:
                                                                      $datetypetext = "First Recieved Material"; $dtt = "First Recieved";
                                                                      break;
                                                                }
					                    	$qc_message  = "Collected before $datetypetext";
									        $agent .= "<tr><td class='cap'>$qc_message</td><td class='val'>Coll:$startdate $dtt:$dob</td></tr>";
					                    } else {
                                                                switch ($datetype) {
                                                                   case 0:
                                                                      $datetypetext = "Death";  $dtt = "DOD";
                                                                      break;
                                                                   case 1:
                                                                      $datetypetext = "Collector Flourished"; $dtt= "Flourished to";
                                                                      break;
                                                                   case 2:
                                                                      $datetypetext = "Last Known Collection"; $dtt = "Collected to";
                                                                      break;
                                                                   case 3:
                                                                      $datetypetext = "Last Recieved Material"; $dtt = "Last Recieved";
                                                                      break;
                                                                }
					                    	$qc_message  = "Collected after $datetypetext";
									        $agent .= "<tr><td class='cap'>$qc_message</td><td class='val'>$dtt:$dod Coll:$startdate</td></tr>";
					                    }
							        }
							    }
						    }
						}
                                                    // Display teams of which this agent is a member
                                                    $query = "select agentid,agentvariant.name from groupperson left join agentvariant on groupid = agentid  where memberid = ? and vartype =4";
						    if ($debug) { echo "[$query]<BR>"; }
						    $statement_qc = $connection->prepare($query);
						    if ($statement_qc) {
							    $statement_qc->bind_param("i",$agentid);
							    $statement_qc->execute();
							    $statement_qc->bind_result($groupagentid,$groupagentname);
							    $statement_qc->store_result();
                                                            $teams = "";
							    if ($statement_qc->num_rows()>0) {
							        while ($statement_qc->fetch()) {
							            $teams .= "<a href='botanist_search.php?mode=details&id=$groupagentid'>$groupagentname</a>&nbsp; ";
                                                                }
							        $agent .= "<tr><td class='cap'>Collector Teams:</td><td class='val'>$teams</td></tr>";
                                                            }
                                                     }
                                                    $query = "select agentid,agentvariant.name from groupperson left join agentvariant on groupid = agentid  where memberid = ? and vartype =2";
						    if ($debug) { echo "[$query]<BR>"; }
						    $statement_qc = $connection->prepare($query);
						    if ($statement_qc) {
							    $statement_qc->bind_param("i",$agentid);
							    $statement_qc->execute();
							    $statement_qc->bind_result($groupagentid,$groupagentname);
							    $statement_qc->store_result();
                                                            $teams = "";
							    if ($statement_qc->num_rows()>0) {
							        while ($statement_qc->fetch()) {
							            $teams .= "<a href='botanist_search.php?mode=details&id=$groupagentid'>$groupagentname</a>&nbsp; ";
                                                                }
							        $agent .= "<tr><td class='cap'>Author Teams:</td><td class='val'>$teams</td></tr>";
                                                            }
                                                     }
                                                     // display members of teams
                                                     if ($agenttype=='3') {
                                                    $query = "select agentid,agentvariant.name from groupperson left join agentvariant on memberid = agentid  where groupid = ? and vartype =?";
						    if ($debug) { echo "[$query]<BR>"; }
						    $statement_qc = $connection->prepare($query);
						    if ($statement_qc) {
							    $statement_qc->bind_param("ii",$agentid,$type);
							    $statement_qc->execute();
							    $statement_qc->bind_result($groupagentid,$groupagentname);
							    $statement_qc->store_result();
                                                            $teams = "";
							    if ($statement_qc->num_rows()>0) {
							        while ($statement_qc->fetch()) {
							            $teams .= "<a href='botanist_search.php?mode=details&id=$groupagentid'>$groupagentname</a>&nbsp; ";
                                                                }
							        $agent .= "<tr><td class='cap'>Team Members:</td><td class='val'>$teams</td></tr>";
                                                            }
                                                     }
                                                     }
					}

				}
				// Don't display details for agents that are only involved in transactions to users outside the herbarium.
				if (preg_match("/^140\.247\.98\./",$_SERVER['REMOTE_ADDR']) ||
				    preg_match("/^10\.1\.147\./",$_SERVER['REMOTE_ADDR']) ||
				    preg_match("/^140\.247\.98\./",$_SERVER['HTTP_X_FORWARDED_FOR']) ||
				    preg_match("/^10\.1\.147\./",$_SERVER['HTTP_X_FORWARDED_FOR']) ||
				    $_SERVER['REMOTE_ADDR']=='127.0.0.1') {

					if ($numberofvariants==0) {
						 $agent = "[Redacted]";
					}
				}
				echo "<table>";
				echo "$agent";
				echo "</table>";
				echo "<hr />";
				$statement->close();
			}
			$oldid = $id;
		}
	}
}


function search() {
	global $connection, $errormessage, $debug, $json;
	$question = "";
	$country = "";
	$joins = "";
	$joined_to_geography = false;
	$wherebit = " where ";
	$and = "";
	$types = "";
	$parametercount = 0;
	$showid = substr(preg_replace("/[^a-z]/","", $_GET['showid']),0,4);

	$name = substr(preg_replace("/[^A-Za-z\-ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ,\.\" _%]/","", $_GET['name']),0,59);
	$nameparts = preg_split('/("[^"]*")|\h+/', $name, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);

	if ($name!="") {
	  //$soundslike = substr(preg_replace("/[^a-z]/","", $_GET['soundslike']),0,4);
		$hasquery = true;

		$wherebit .= "agent.agentid in (select agentid from agentvariant where ";

		foreach ($nameparts as &$part) {
			$part = preg_replace("/\"/", "", $part);
			$question .= "$and name like:[$part]";
			$wherebit .= "$and agentvariant.name like ? ";
			$and = " and ";
			$types .= "s";
			$operator = " like ";
			$parameters[$parametercount] = '%'.$part.'%';
			$parametercount++;
		}

		$wherebit .= ") ";

		//$namepad = "%$name%";
		//$question .= "$and name:[$name] or name like:[$namepad]  ";
		//$types .= "s";
		//$operator = "=";
		//$parameters[$parametercount] = &$namepad;
		//$parametercount++;
		//if ($soundslike=="true") {
		//    $question .= " or name sounds like [$name] ";
		//    $types .= "s";
		//    $parameters[$parametercount] = &$name;
		//    $parametercount++;
		//}
		//if (preg_match("/[%_]/",$name))  { $operator = " like "; }
		//$wherebit .= "$and (agentvariant.name like ? ";
		//if ($soundslike=="true") {
		//    $wherebit .= " or soundex(agentvariant.name)=soundex(?) ";
		//}
		//$wherebit .= " )";
		//$and = " and ";
	}

	$remarks = substr(preg_replace("/[^A-Za-z\-ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ,\.\" _%]/","", $_GET['remarks']),0,59);
	$rparts = preg_split('/("[^"]*")|\h+/', $remarks, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);
	if ($remarks!="") {
		$hasquery = true;

		foreach ($rparts as &$part) {
			$part = preg_replace("/\"/", "", $part);
			$question .= "$and remarks like:[$part]";
			$wherebit .= "$and agent.remarks like ? ";
			$and = " and ";
			$types .= "s";
			$operator = " like ";
			$parameters[$parametercount] = '%'.$part.'%';
			$parametercount++;
		}
	}

//	if ($is_author=="on" || $is_collector=="on" || $individual=="on" || )
//	$wherebit .= "$and (";

	$is_author = substr(preg_replace("/[^a-z]/","", $_GET['is_author']),0,3);
	$is_collector= substr(preg_replace("/[^a-z]/","", $_GET['is_collector']),0,3);

//	if ($is_author=="on" && $is_collector=="on") {
//		$is_author="";
//		$is_collector="";
//		$question .= "$and is a collector or author ";
//	}

	if ($is_author=="on") {
		$hasquery = true;
		$question .= "$and is a taxon author ";
		$wherebit .= "$and agent.agentid in (select agentid from agentspecialty where role='Author') ";
		$and = " and ";
	}

	if ($is_collector=="on") {
		$hasquery = true;
		$question .= "$and is a collector ";
		$wherebit .= "$and agent.agentid in (select agentid from agentspecialty where role='Collector') ";
		//$wherebit .= "$and agentspecialty.role = 'Collector' ";
		//if (!$joined_to_specialty) {
		//    $joins .= " left join agentspecialty on agent.agentid = agentspecialty.agentid ";
		//}
		//$joined_to_specialty = true;
		$and = " and ";
	}

	$individual = substr(preg_replace("/[^a-z]/","", $_GET['individual']),0,3);
	$team = substr(preg_replace("/[^a-z]/","", $_GET['team']),0,3);

	if ($individual=="on" && $team=="on") {
		$individual="";
		$team="";
		$question .= "$and is an individual or team ";
	}

	if ($individual=="on") {
		$hasquery = true;
		$question .= "$and is an individual ";
		$wherebit .= "$and agent.agenttype <> 3 ";
		$and = " and ";
	}

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
		$joins .= " left join agentspecialty on agent.agentid = agentspecialty.agentid ";
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
	$query =
		"select agent.agentid, " .
		" agent.agenttype, agent.firstname, agent.lastname, GROUP_CONCAT(agentvariant.name ORDER BY agentvariant.vartype DESC SEPARATOR ' | ') allnames, year(agent.dateofbirth), year(agent.dateofdeath), datestype " .
		" from agent " .
		" left join agentvariant on agent.agentid = agentvariant.agentid " .
		" $joins " .
		" $wherebit " .
		" group by agent.agentid " .
		" order by agent.agenttype, allnames, agent.lastname, agent.firstname, agent.dateofbirth limit 1000";
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
			if (substr(phpversion(),0,4)=="5.3." || substr(phpversion(),0,4)=="5.5.") {
			   // work around for bug in __call, or is it?
			   // http://bugs.php.net/bug.php?id=50394
			   // http://stackoverflow.com/questions/2045875/pass-by-reference-problem-with-php-5-3-1
			   call_user_func_array(array($statement, 'bind_param'),make_values_referenced($array));
			} else {
			   call_user_func_array(array($statement, 'bind_param'),$array);
			}
			$statement->execute();
			$statement->bind_result($agentid, $agenttype, $firstname, $lastname, $allnames, $yearofbirth, $yearofdeath, $datestype);
			$statement->store_result();
            if (!$json) {
			   echo "<div>\n";
			   echo $statement->num_rows . " matches to query ";
			   echo "    <span class='query'>$question</span>\n";
			   echo "</div>\n";
			   echo "<HR>\n";
			}
			if ($statement->num_rows > 0 ) {
                if (!$json) {
				   echo "<form  action='botanist_search.php' method='get'>\n";
				   echo "<input type='hidden' name='mode' value='details'>\n";
				   echo "<input type='image' src='images/display_recs.gif' name='display' alt='Display selected records' />\n";
				   echo "<BR><div>\n";
                } else {
                   echo "{ \"botanists\" : [";
                   $comma = "";
                }
				$lastpair = "";
				while ($statement->fetch()) {
					if (preg_match("/(^.+?)\|\s(.+)$/", $allnames, $matches)) {
					 		$fullname = $matches[1];
							$othernames = $matches[2];
				 	} else {
							$fullname = $allnames;
						  $othernames = "none";
					}
					if ($lastpair != "$agentid$fullname")  {
						// omit identical agent records with identical names
					    if ($agenttype==3)  { $team = "[Team]"; } else { $team = ""; }
					    if ($fullname=="") { $fullname = "$firstname $lastname"; }
					    //if ($name != '') {
					    //   $plainname = preg_replace("/[^A-Za-zÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ ]/","",$name);
					    //   $highlightedname = preg_replace("/$plainname/","<strong>$plainname</strong>","$fullname");
					    //} else {
					    //   $highlightedname = $fullname;
					    //}
                                            $datemod = "";
                                            if ($datestype==1) { $datemod = "fl. "; }
                                            if ($datestype==2) { $datemod = "col. "; }
                                            if ($datestype==3) { $datemod = "rec. "; }
                                            $showidvalue = "";
                                            if ($showid=="true") { $showidvalue = "[".str_pad($agentid,7,"0",STR_PAD_LEFT)."] "; }
                        if (!$json) {
					       echo "<input type='checkbox' name='id[]' value='$agentid'>$showidvalue<a href='botanist_search.php?mode=details&id=$agentid'>$fullname</a> ($datemod$yearofbirth - $yearofdeath) $team";
								 echo "<br>&nbsp;&nbsp;&nbsp;&nbsp;other variants: $othernames";
								 echo "<BR>\n";
                        } else {
                           if ($datemod=="") { $datemod = "life"; }
					       echo "$comma { \"name\" : \"$fullname\", \"type\" : \"$datemod\", \"start\" : \"$yearofbirth\", \"end\" : \"$yearofdeath\" }";
                           $comma = ",";
                        }
					}
					$lastpair = "$agentid$fullname";
				}
                if (!$json) {
				   echo "</div>\n";
				   echo "<input type='image' src='images/display_recs.gif' name='display' alt='Display selected records' />\n";
   				   echo "</form>\n";
                } else {
                   echo "] }";
                }
			} else {
				$errormessage .= "No matching results. ";
			}
			$statement->close();
		} else {
			echo $connection->error;
		}
	} else {
        if (!$json) {
	       echo "<div>\n";
        }
		echo "No query parameters provided.";
        if (!$json) {
		   echo "</div>\n";
		   echo "<HR>\n";
        }
	}

}




mysqli_report(MYSQLI_REPORT_OFF);

?>
