<?php
/*
 * Created on Nov 8, 2013
 *
 * Copyright © 2013 President and Fellows of Harvard College
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

if (preg_replace("[^0-9]","",$_GET['taxonid'])!="") { 
	$mode = "details"; 
}
if (preg_replace("[^0-9]","",$_GET['id'])!="") { 
	$mode = "details"; 
}
if (preg_replace("[^0-9A-Za-z\-]","",$_GET['taxonuuid'])!="") { 
	$mode = "details"; 
}

echo pageheader('taxon'); 

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
    $uuid = preg_replace("[^0-9A-Za-z\-]","",$_GET['taxonguid']);
    if ($uuid != "") { 
        $query = "select primarykey, state from guids where tablename = 'taxon' and uuid = ? ";
        if ($debug) { echo "[$uuid]"; }
        $statement = $connection->prepare($query);
        $taxonresult = "";
        if ($statement) {
           $statement->bind_param("s",$uuid);
           $statement->execute();
           $statement->bind_result($primarykey, $state);
           $statement->store_result();
           while ($statement->fetch()) {
               $taxonid = $primarykey;
               if ($state!='') { 
                  echo "$uuid HUH Taxon $state";
               } 
           } 
        }
    } else { 
	    $taxonid = preg_replace("[^0-9]","",$_GET['taxonid']);
    }
	if ($taxonid != "") {
		$ids[] = $taxonid;
	}
	$oldid = "";
	foreach($ids as $value) { 
		$id = substr(preg_replace("[^0-9]","",$value),0,20);
		// skip ajacent duplicates, if any
		if ($oldid!=$id)  { 
			$query = "select t.author, t.citesstatus, t.fullname, t.groupnumber, t.guid, t.isaccepted, " .
				" t.ishybrid, t.name, t.rankid, t.remarks, t.source, t.text1, t.parentid, " .
                " t.stdauthorid, t.stdexauthorid, t.parauthorid, t.parexauthorid, t.sanctauthorid, t.parsanctauthorid, t.citinauthorid,  " .
                " td.name, td.isinfullname, p.fullname, p.author " .
				" from taxon t left join taxontreedefitem td on t.rankid = td.rankid " .
				" left join taxon p on t.parentid = p.taxonid " .
                " where t.taxonid = ?  ";
			if ($debug) { echo "[$query]<BR>"; } 
			if ($debug) { echo "[$id]"; } 
			$statement = $connection->prepare($query);
			$taxonresult = "";
			if ($statement) {
				$statement->bind_param("i",$id);
				$statement->execute();
				$statement->bind_result($author, $citesstatus, $fullname, $groupnumber, $guid, $isaccepted, $isybrid, $name, $rankid, $remarks, $source, $status, $parentid, $stdauthorid, $stdexauthorid, $parauthorid, $parexauthorid, $sanctauthorid, $parsanctauthorid, $citinauthorid, $rank, $isinfullname, $parentname, $parentauthor);
				$statement->store_result();
				while ($statement->fetch()) {
					$is_group = false;
					$taxonresult .=  "<tr><td class='cap'>Name</td><td class='val'><em>$fullname</em> $author</td></tr>";
					if ($isaccepted=="0") { 
					      $is_group = true;
					      $taxonresult .=  "<tr><td class='cap'>Taxonomic Status</td><td class='val'>Not Accepted Name</td></tr>";
					}
					
					if (trim($status!=""))   { $taxonresult .=  "<tr><td class='cap'>NomenclaturalStatus</td><td class='val'>$status</td></tr>"; }
					if (trim($parentname!=""))   { $taxonresult .=  "<tr><td class='cap'>Placed in</td><td class='val'><a href='taxon_search.php?mode=details&id=$parentid'><em>$parentname</em> $parentauthor</a></td></tr>"; }

                    // Find child taxa
                    $query = "select fullname, author, taxonid from taxon where parentid = ? order by name ";
                    if ($debug) { echo "[$query]<BR>"; }
                    $statement_c = $connection->prepare($query);
                    if ($statement_c) {
                        $statement_c->bind_param("i",$id);
                        $statement_c->execute();
                        $statement_c->bind_result($childname,$childauthor,$childtaxonid);
                        $statement_c->store_result();
                        $collectorname = "";
						if ($statement_c->num_rows()>0 ) {
                            $taxonresult .= "<tr><td class='cap'>Contains</td><td class='val'>" ;
                            while ($statement_c->fetch()) {
                            $taxonresult .= "<a href='taxon_search.php?mode=details&id=$childtaxonid'><em>$childname</em> $childauthor</a>&nbsp; ";
                            }
                            $taxonresult .= "</td></tr>";
                        }
                    }

					if (trim($remarks!=""))   { $taxonresult .=  "<tr><td class='cap'>Remarks</td><td class='val'>$remarks</td></tr>"; }
					if (trim($guid!=""))   { $taxonresult .=  "<tr><td class='cap'>GUID</td><td class='val'><a href='$guid'>$guid</a></td></tr>"; }
                    // List citations
					$query = "select rw.text1 as title, rw.title as abbrev, tc.text2 as date, tc.text1 as volnumpage, rw.referenceworkid from taxoncitation tc left join referencework rw on tc.referenceworkid = rw.referenceworkid where taxonid = ? ";
					if ($debug) { echo "[$query]<BR>"; } 
					$statement_var = $connection->prepare($query);
					if ($statement_var) {
						$statement_var->bind_param("i",$id);
						$statement_var->execute();
						$statement_var->bind_result($title, $abbrev, $date, $volnumpage, $referenceworkid);
						$statement_var->store_result();
						$collectorname = "";
						while ($statement_var->fetch()) {
							$taxonresult .= "<tr><td class='cap'>Citation</td><td class='val'>$date <a href='publication_search.php?mode=details&id=$referenceworkid'>$abbrev</a> $volnumpage </td></tr>";
						}
						
						$query = "select count(fragmentid), year(determineddate) " .
								" from determination " .
								" where taxonid = ? " .
								" group by year(determineddate)";
						if ($debug) { echo "[$query]<BR>"; } 
						$statement_geo = $connection->prepare($query);
						if ($statement_geo) {
							$statement_geo->bind_param("i",$id);
							$statement_geo->execute();
							$statement_geo->bind_result($count, $year);
							$statement_geo->store_result();
							if ($statement_geo->num_rows()>0 ) {
                                $records = 0;
								while ($statement_geo->fetch()) {
                                    $records += $count;
									// for each year
									// obtain the list of collection objects with this taxon in det for year
									if ($year=="") {
										$query = "select collectionobjectid " .
											" from determination d left join fragment f on d.fragmentid = f.fragmentid  " .
											" where taxonid = ? and determineddate is null ";
									} else { 
										$query = "select collectionobjectid " .
											" from determination d left join fragment f on d.fragmentid = f.fragmentid  " .
											" where taxonid = ? and year(determineddate) = ? ";
									}
									$statement_co = $connection->prepare($query);
									$link = "";
									if ($statement_co) {
										$link = "href='specimen_search.php?mode=details";
									    if ($year=="") {
											$statement_co->bind_param("i",$id);
									    } else { 
											$statement_co->bind_param("ii",$id,$year);
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
                                    $collist .= "$collistseparator<a $link>$year ($count)</a>";
                                    $collistseparator = ",&nbsp; ";
								} 
                                if ($records==1) { $s = ""; } else { $s="s"; }
								$taxonresult .= "<tr><td class='cap'>Holdings</td><td class='val'>$records Specimen$s held in the Harvard University Herbaria identified as $fullname $author</td></tr>";
							    $taxonresult .= "<tr><td class='cap'>Identification made in</td><td class='val'>$collist</td></tr>";
							}
						}
					}
					
				}
				echo "<table>";
				echo "$taxonresult";
				echo "</table>";
				echo "<hr />";
				$statement->close();
			}
			$oldid = $id;
		}
	}
}


function search() {  
  // Not implemented yet	
  $_GET['id']=1;
  details();
}




mysqli_report(MYSQLI_REPORT_OFF);

?>
