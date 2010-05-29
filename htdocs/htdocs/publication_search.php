<?php
/*
 * Created on Dec 3, 2009
 *
 */
$debug=true;
if ($debug) { 
   mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT);
} else { 
   mysqli_report(MYSQLI_REPORT_OFF);
} 

include_once('connection_library.php');
include_once('specify_library.php');

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

if (preg_replace("[^0-9]","",$_GET['barcode'])!="") { 
	$mode = "details"; 
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
	$id = $_GET['id'];
	if (is_array($id)) { 
		$ids = $id;
	} else { 
		$ids[0] = $id;
	}
	$guid = preg_replace("[^0-9]","",$_GET['guid']);
	if ($guid != "") {
		$sql = "select referenceworkid from referencework where guid = ? ";
		$statement = $connection->prepare($sql);
		if ($statement) {
			$statement->bind_param("s",$guid);
			$statement->execute();
			$statement->bind_result($id);
			$statement->store_result();
			while ($statement->fetch()) {
				$ids[] = $id;
			}
		}
	}
	$oldid = "";
	foreach($ids as $value) { 
		$id = substr(preg_replace("[^0-9]","",$value),0,20);
		// Might be duplicates next to each other in list of checkbox records from search results 
		// (from there being more than one current determination/typification for a specimen, or
		// from there being two specimens on one sheet).  
		if ($oldid!=$id)  { 
			echo "[$id]";
			$wherebit = " referencework.referenceworkid = ? ";
			$query = "select text2 as year, text1 as worktitle, title as abbreviation, placeofpublication, publisher, referenceworktype, volume, pages, url, remarks, ContainedRFParentID from referencework where $wherebit";
			if ($debug) { echo "[$query]<BR>"; } 
			$statement = $connection->prepare($query);
			if ($statement) {
				$statement->bind_param("i",$id);
				$statement->execute();
				$statement->bind_result($year, $title, $abbreviation, $placeofpublication, $publisher, $referenceworktype, $volume, $pages, $url, $remarks, $ContainedRFParentID);
				$statement->store_result();
				echo "<table>";
				$authors = "";
				while ($statement->fetch()) {
					// referenceworktype = 0 normal reference works.
					// referenceworktype = 5 an import generated citation in a work.
					// referenceworktype = 6 Excicatii.
					if ( $referenceworktype=="0") { $referenceworktype = ""; }  
					if ( $referenceworktype=="6")  { $referenceworktype = "Excicata"; }  
					$query = " select agentvariant.name, agentvariant.agentid from referencework r left join author a on r.referenceworkid = a.referenceworkid left join agentvariant on a.agentid = agentvariant.agentid where r.referenceworkid = ? and vartype = 4 ";
					if ($debug) { echo "[$query]<BR>"; } 
					$statement_hg = $connection->prepare($query);
					if ($statement_hg) {
						$statement_hg->bind_param("i",$id);
						$statement_hg->execute();
						$statement_hg->bind_result($authorname,$agentid);
						$statement_hg->store_result();
						$separator = "";
						while ($statement_hg->fetch()) {
							$authors .= "$separator<a href=botanist_search.php?mode=details&botanistid=$agentid>$authorname</a>"; 
							$separator = ": ";
						}
					}	    
					
					$identifiers = "";
					$query = "select identifier,type from referenceworkidentifier where referenceworkid = ? ";
					if ($debug===true) {  echo "[$query]<BR>"; }
					$statement_geo = $connection->prepare($query);
					if ($statement_geo) { 
						$statement_geo->bind_param("i",$id);
						$statement_geo->execute();
						$statement_geo->bind_result($identifier, $identifiertype);
						$statement_geo->store_result();
						while ($statement_geo->fetch()) { 
							$identifiers .= "<tr><td class='cap'>$identifiertype</td><td class='val'>$identifier</td></tr>";
						}
					} else { 
						echo "Error: " . $connection->error;
					}
					
					echo "<tr><td class='cap'>Title</td><td class='val'>$title</td></tr>";
					if (trim($abbreviation!=""))   { echo "<tr><td class='cap'>Abbreviation</td><td class='val'>$abbreviation</td></tr>"; }
					if (trim($authors!=""))   { echo "<tr><td class='cap'>Authors</td><td class='val'>$authors</td></tr>"; }
					if (trim($year!=""))   { echo "<tr><td class='cap'>Publication Dates</td><td class='val'>$year</td></tr>"; }
					if (trim($placeofpublication!=""))   { echo "<tr><td class='cap'>Place of publication</td><td class='val'>$placeofpublication</td></tr>"; }
					if (trim($publisher!=""))   { echo "<tr><td class='cap'>Publisher</td><td class='val'>$publisher</td></tr>"; }
					if (trim($referenceworktype!=""))   { echo "<tr><td class='cap'>ReferenceWorkType</td><td class='val'>$referenceworktype</td></tr>"; }
					if (trim($volume!=""))   { echo "<tr><td class='cap'>Volume</td><td class='val'>$volume</td></tr>"; }
					if (trim($pages!=""))   { echo "<tr><td class='cap'>Pages</td><td class='val'>$pages</td></tr>"; }
					if (trim($url!=""))   { echo "<tr><td class='cap'>URL</td><td class='val'><a href='$url'>$url</a></td></tr>"; }
					if (trim($identifiers!=""))   { echo "$identifiers"; }
					if (trim($remarks!="")) { echo "<tr><td class='cap'>Remarks</td><td class='val'>$remarks</td></tr>"; } 
					if (trim($ContainedRFParentID!=""))   { echo "<tr><td class='cap'>ContainedRFParentID</td><td class='val'>$ContainedRFParentID</td></tr>"; }
					echo "<BR>\n";
				}
				echo "</table>";
			    $query = "select taxon.fullname, taxon.author, d.typestatusname, d.fragmentid, tc.remarks, tc.text1, tc.text2 " .
			    		"from determination d left join taxon on d.taxonid = taxon.taxonid " .
			    		"left join taxoncitation tc on taxon.taxonid = tc.taxonid " .
			    		"where typestatusname is not null and tc.referenceworkid = ? "; 
				if ($debug) { echo "[$query]<BR>"; } 
				$statement_ts = $connection->prepare($query);
				if ($statement_ts) {
					$statement_ts->bind_param("i",$id);
					$statement_ts->execute();
					$statement_ts->bind_result($fullname, $author, $typestatusname, $fragmentid, $remarks, $pages, $year);
					$statement_ts->store_result();
					$separator = "";
					$count = $statement_ts->num_rows;
			        if ($count > 0 ) {
			        	echo "<h3>The Harvard University Herbaria hold $count type specimens of taxa described in this publication</h3>";
					    while ($statement_ts->fetch()) {
					    	if ($remarks !="") { $remarks = " ($remarks)"; }
					    	echo "$fullname $author [<a href='specimen_search.php?fragmentid=$fragmentid'>$typestatusname</a>$remarks] $year, $pages<BR>";
					    }
			        }
				}
			    $statement_ts->close();
				$query  = "select taxon.fullname, taxon.author, d.typestatusname, d.fragmentid, dc.remarks, dc.text1, dc.text2 " .
						"from determination d left join taxon on d.taxonid = taxon.taxonid " .
						"left join determinationcitation dc on d.determinationid = dc.determinationid " .
						"where typestatusname is not null and dc.referenceworkid = ? ";
				if ($debug) { echo "[$query]<BR>"; } 
				$statement_cit = $connection->prepare($query);
				if ($statement_cit) {
					$statement_cit->bind_param("i",$id);
					$statement_cit->execute();
					$statement_cit->bind_result($fullname, $author, $typestatusname, $fragmentid, $remarks, $pages, $year);
					$statement_cit->store_result();
					$separator = "";
					$count = $statement_cit->num_rows;
			        if ($count > 0 ) {
			        	echo "<h3>The Harvard University Herbaria hold $count type specimens of taxa cited in this publication</h3>";
					    while ($statement_cit->fetch()) {
					    	if ($remarks !="") { $remarks = " ($remarks)"; }
					    	echo "$fullname $author [$typestatusname$remarks] $year, $pages<BR>";
					    }
			        }
				}
			    $statement_cit->close();
			}
			$statement->close();
		}
		$oldid = $id;
	}
}


function search() {  
	global $connection, $errormessage, $debug;
	$question = "";
	$joins = "";
	$wherebit = " where "; 
	$and = "";
	$types = "";
	$joins = "";
	$order = "";
	$parametercount = 0;
	$hasauthor = false;
	$title = substr(preg_replace("/[^A-Za-z_0-9%]/","", $_GET['title']),0,59);
	if ($title!="") { 
		$hasquery = true;
		$question .= "$and title:[$title] ";
		$types .= "s";
		$operator = "=";
		$parameters[$parametercount] = &$title;
		$parametercount++;
		if (preg_match("/[%_]/",$title))  { $operator = " like "; }
		$wherebit .= "$and r.text1 $operator ? ";
		$and = " and ";
		$order = " order by r.text1 ";
	}
	$author = substr(preg_replace("/[^A-Za-z_0-9\. %]/","", $_GET['author']),0,59);
	if ($author!="") { 
		$hasauthor = true;
		$hasquery = true;
		$question .= "$and author:[$author] ";
		$types .= "sss";
		$operator = "=";
		$parameters[$parametercount] = &$author;
		$parametercount++;
		$parameters[$parametercount] = &$author;
		$parametercount++;
		$parameters[$parametercount] = &$author;
		$parametercount++;
		if (preg_match("/[%_]/",$author))  { $operator = " like "; }
		$wherebit .= "$and (agent.lastname $operator ? or agentvariant.name $operator ? or agent.lastname $operator ?)";
		$joins .= " left join author a on r.referenceworkid = a.referenceworkid left join agentvariant on a.agentid = agentvariant.agentid left join agent on a.agentid = agent.agentid ";
		$order = " order by agent.lastname, agent.firstname, r.text1 ";		
		$and = " and ";
	} else {
		// author or agentid search is exclusive.  
	$agentid = substr(preg_replace("/[^A-Za-z_0-9\. %]/","", $_GET['agentid']),0,59);
	if ($agentid!="") { 
		$hasauthor = true;
		$hasquery = true;
		$question .= "$and author agentid:[$agentid] ";
		$types .= "i";
		$operator = "=";
		$parameters[$parametercount] = &$agentid;
		$parametercount++;
		$wherebit .= "$and a.agentid $operator ? ";
		$joins .= " left join author a on r.referenceworkid = a.referenceworkid left join agent on a.agentid = agent.agentid ";
		$order = " order by agent.lastname, agent.firstname, r.text1 ";		
		$and = " and ";
	}
	}
	$identifier = substr(preg_replace("/[^A-Za-z\/\\0-9\.%]/","", $_GET['identifier']),0,59);
	if ($identifier!="") { 
		$ttype = substr(preg_replace("/[^A-Z0-9\.%]/","", $_GET['type']),0,59);
		$hasquery = true;
		$question .= "$and ( identifier:[$identifier] and type = [$ttype] )";
		$types .= "ss";
		$operator = "=";
		$parameters[$parametercount] = &$identifier;
		$parametercount++;
		$parameters[$parametercount] = &$ttype;
		$parametercount++;
		if (preg_match("/[%_]/",$identifier))  { $operator = " like "; }
		$wherebit .= "$and ( ri.identifier $operator ? and ri.type = ? ) ";
		$joins .= " left join referenceworkidentifier ri on r.referenceworkid = ri.referenceworkid  ";
		$and = " and ";
	}
	if ($question!="") {
		$question = "Search for $question <BR>";
	} else {
		$question = "No search criteria provided.";
	}
	if ($hasauthor) { $third = ", agent.lastname, agent.firstname "; } else { $third = ""; }
	// referenceworktype = 5 an import generated citation in a work.
	$query = "select distinct r.referenceworkid, r.text1 as title $third 
		from referencework r  
		$joins $wherebit $and r.referenceworktype <> 5 $order ";
	if ($debug===true  && $hasquery===true) {
		echo "[$query]<BR>\n";
	}
	if ($hasquery===true) { 
		$statement = $connection->prepare($query);
		if ($statement) { 
			$array = Array();
			$array[] = $types;
			foreach($parameters as $par) {
			    $array[] = $par;
			}
			call_user_func_array(array($statement, 'bind_param'),$array);
			$statement->execute();
			if ($hasauthor) { 
				$statement->bind_result($referenceid,$title,$authorlast, $authorfirst);
			} else { 
				$statement->bind_result($referenceid,$title);
			}
			$statement->store_result();
			
			echo "<div>\n";
			echo $statement->num_rows . " matches to query ";
			echo "    <span class='query'>$question</span>\n";
			echo "</div>\n";
			echo "<HR>\n";
			
			if ($statement->num_rows > 0 ) {
				echo "<form  action='publication_search.php' method='get'>\n";
				echo "<input type='hidden' name='mode' value='details'>\n";
				echo "<input type='image' src='images/display_recs.gif' name='display' alt='Display selected records' />\n";
				echo "<BR><div>\n";
				$oldauthor = "";
				while ($statement->fetch()) {
					$currentauthor = $authorlast.$authorfirst;
					if ($hasauthor && $oldauthor!=$currentauthor) { 
						echo "<strong>$authorlast, $authorfirst</strong><BR>";
						$oldauthor = $currentauthor;
					} 
					echo "<input type='checkbox' name='id[]' value='$referenceid'> <a href='publication_search.php?mode=details&id=$referenceid'>$title</a> ";
					echo "<BR>\n";
				}
				echo "</div>\n";
				echo "<input type='image' src='images/display_recs.gif' name='display' alt='Display selected records' />\n";
				echo "</form>\n";
				
			} else {
				$errormessage .= "No matching results. ";
			}
			if ($hasauthor && $author != "") {
				$statement->close();
				// Look for possibly related authors
				$query = " select agent.lastname, agent.firstname, agent.agentid, count(author.referenceworkid) from referencework left join author on referencework.referenceworkid = author.referenceworkid left join agent on author.agentid = agent.agentid left join agentvariant on agent.agentid = agentvariant.agentid where referenceworktype<>5 and (agentvariant.name like ? or soundex(agent.lastname) = soundex(?) or agent.lastname like ? or soundex(agent.lastname) = soundex(?)) group by agent.firstname, agent.lastname, agent.agentid ";
				$wildauthor = "%$author%";
				$plainauthor = str_replace("%","",$author);
       		    $authorparameters[0] = &$wildauthor;   // agentvariant like 
 		        $types = "s";
       		    $authorparameters[1] = &$plainauthor;  // agentvariant soundex
 		        $types .= "s";
       		    $authorparameters[2] = &$wildauthor;   // agent like 
 		        $types .= "s";
       		    $authorparameters[3] = &$plainauthor;  // agent soundex
 		        $types .= "s";
             	if ($debug===true  && $hasquery===true) {
		              echo "[$query][$wildauthor][$plainauthor][$wildauthor][$plainauthor]<BR>\n";
	            }
				$statement = $connection->prepare($query);
				if ($statement) { 
					$array = Array();
					$array[] = $types;
					foreach($authorparameters as $par)
					      $array[] = $par;
					call_user_func_array(array($statement, 'bind_param'),$array);
					$statement->execute();
					$statement->bind_result($authorlast, $authorfirst, $agentid, $count);
					$statement->store_result();
			        if ($statement->num_rows > 0 ) {
			        	echo "<h3>Possibly matching authors</h3>";
			        	$separator = "";
				         while ($statement->fetch()) {
				         	echo "$separator$authorlast, $authorfirst [<a href='publication_search.php?mode=search&agentid=$agentid'>$count pubs</a>]";
				         	$separator = "; ";
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

mysqli_report(MYSQLI_REPORT_OFF);

?>
