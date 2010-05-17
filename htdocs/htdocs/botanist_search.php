<?php
/*
 * Created on Dec 3, 2009
 *
 */
$debug=true;
if ($debug) { 
   mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT);
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

if (preg_replace("[^0-9]","",$_GET['botanistid'])!="") { 
	$mode = "details"; 
}

echo pageheader($mode); 

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
			$query = "select lastname, firstname, remarks from agent  $wherebit";
			if ($debug) { echo "[$query]<BR>"; } 
			if ($debug) { echo "[$id]"; } 
			$statement = $connection->prepare($query);
			if ($statement) {
				$statement->bind_param("i",$id);
				$statement->execute();
				$statement->bind_result($lastname,$firstname, $remarks);
				$statement->store_result();
				echo "<table>";
				while ($statement->fetch()) {
					
					echo "<tr><td class='cap'>Name</td><td class='val'>$lastname, $firstname</td></tr>";
					if (trim($remarks!=""))   { echo "<tr><td class='cap'>Remarks</td><td class='val'>$remarks</td></tr>"; }
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
	$country = "";
		$joins = "";
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
		if ($question!="") {
			$question = "Search for $question <BR>";
		} else {
			$question = "No search criteria provided.";
		}
		$query = "select distinct agent.agentid, " .
				" agent.agenttype, agent.firstname, agent.lastname, year(agent.dateofbirth), year(agent.dateofdeath) " . 
			    " from agent " .  
			    " left join agentvariant on agent.agentid = agentvariant.agentid  $wherebit order by agent.agenttype, agent.lastname, agent.firstname, agent.dateofbirth ";
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
