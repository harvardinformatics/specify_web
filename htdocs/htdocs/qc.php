<?php
/*
 * Created on Jun 8, 2010
 *
 * Copyright 2010 The President and Fellows of Harvard College
 * Author: Paul J. Morris
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
 
if ($_GET['mode']!="")  {
	if ($_GET['mode']=="unlinked_collectionobjects") {
		$mode = "unlinked_collectionobjects"; 
	}
	if ($_GET['mode']=="unlinked_preparations") {
		$mode = "unlinked_preparations"; 
	}
	if ($_GET['mode']=="collectionobjects_without_barcodes") {
		$mode = "collectionobjects_without_barcodes"; 
	}
	if ($_GET['mode']=="collectingevents_without_locality") {
		$mode = "collectingevents_without_locality"; 
	}
	if ($_GET['mode']=="list_entry_for_collectingevents_without_locality") {
		$mode = "list_entry_for_collectingevents_without_locality"; 
	}
	if ($_GET['mode']=="agent_ages") {
		$mode = "agent_ages"; 
	}
	if ($_GET['mode']=="individual_agent_ages") {
		$mode = "individual_agent_ages"; 
	}
	if ($_GET['mode']=="team_agent_ages") {
		$mode = "team_agent_ages"; 
	}
	if ($_GET['mode']=="collection_when_not_alive") {
		$mode = "collection_when_not_alive"; 
	}
} 
	
echo pageheader('qc'); 

// Only display if internal 
if (preg_match("/^140\.247\.98\./",$_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR']=='127.0.0.1') { 
						

	if ($connection) { 
		
		switch ($mode) {
		
			case "unlinked_collectionobjects":	
				echo unlinked_collectionobjects();
				break;
			case "unlinked_preparations":	
				echo unlinked_preparations();
				break;
			case "collectionobjects_without_barcodes":	
				echo collectionobjects_without_barcodes();
				break;
			case "list_entry_for_collectingevents_without_locality":	
				echo list_entry_for_collectingevents_without_locality();
				break;
			case "collectingevents_without_locality":	
			    $agentid = preg_replace("/[^0-9]/","",$_GET['agentid']);
				echo collectingevents_without_locality($agentid);
				break;
			case "agent_ages":
				echo agent_ages();
				break;
			case "individual_agent_ages":
				echo agent_ages(1);
				break;
			case "team_agent_ages":
				echo agent_ages(3);
				break;
			case "collection_when_not_alive":	
				echo collection_out_of_date_range();
				break;
			case "menu": 	
			default:
				echo menu(); 
		}
		
		$connection->close();
		
	} else { 
		$errormessage .= "Unable to connect to database. ";
	}
	
	if ($errormessage!="") {
		echo "<strong>Error: $errormessage</strong>";
	}
	
	
    echo "<h3><a href='qc.php'>Quality Control Tests</a></h3>";						
	
} else {
	echo "<h2>QC pages are available only within HUH</h2>"; 
}

echo pagefooter();

// ******* main code block ends here, supporting functions follow. *****

function menu() { 
   $returnvalue = "";

   $returnvalue .= "<div>";
   $returnvalue .= "<h2>Find anomalous values for Collection Objects</h2>";
   $returnvalue .= "<ul>";
   $returnvalue .= "<li><a href='qc.php?mode=unlinked_collectionobjects'>Collection objects without Items</a></li>";
   $returnvalue .= "<li><a href='qc.php?mode=unlinked_preparations'>Preparations without Items</a></li>";
   $returnvalue .= "<li><a href='qc.php?mode=collectionobjects_without_barcodes'>Collection objects without barcodes</a></li>";
   $returnvalue .= "<li><a href='qc.php?mode=list_entry_for_collectingevents_without_locality'>Collecting Events without Localities</a></li>";
   $returnvalue .= "</ul>";
   $returnvalue .= "</ul>";
   $returnvalue .= "</ul>";
   $returnvalue .= "<h2>Find anomalous values for Agents/Botanists</h2>";
   $returnvalue .= "<ul>";
   $returnvalue .= "<li><a href='qc.php?mode=agent_ages'>Agents ages</a></li>";
   $returnvalue .= "<li><a href='qc.php?mode=individual_agent_ages'>Individual Agent ages</a></li>";
   $returnvalue .= "<li><a href='qc.php?mode=team_agent_ages'>Team Agent ages</a></li>";
   $returnvalue .= "<li><a href='qc.php?mode=collection_when_not_alive'>Collections before birth/after death</a></li>";
   $returnvalue .= "</ul>";
   $returnvalue .= "</div>";

   return $returnvalue;
}

function collection_out_of_date_range() { 
	global $connection;
   $returnvalue = "";
    
   $query = "select collectionobjectid, agent.lastname, agent.agentid, dateofbirth, startdate, dateofdeath, agent.initials as datetype " .
   		" from agent left join collector on agent.agentid = collector.agentid " .
   		" left join collectingevent on collector.collectingeventid = collectingevent.collectingeventid " .
   		" left join collectionobject on collectingevent.collectingeventid = collectionobject.collectingeventid " .
   		" where dateofbirth is not null " .
   		" and (startdate < dateofbirth or enddate > dateofdeath) " .
   		" order by agent.initials, agent.lastname, startdate "; 
	if ($debug) { echo "[$query]<BR>"; } 
    $returnvalue .= "<h2>Cases where collecting event dates are outside of the birth/death, flourished, collected, or received dates for a collector.</h2>";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($collectionobjectid,$name,$agentid, $dob, $collectiondate, $dod, $datetype);
		$statement->store_result();
        $returnvalue .= "<h2>There are ". $statement->num_rows() . " anomalous collecting events</h2>";
	    $returnvalue .= "<table>";
	    $returnvalue .= "<tr><th>Begin Date</th><th>Collecting Event</th><th>End Date</th><th>Type</th><th>Collector</th></tr>";
		while ($statement->fetch()) {
	        $returnvalue .= "<tr><td>$dob</td><td><a href='specimen_search.php?mode=details&id=$collectionobjectid'>$collectiondate</a></td><td>$dod</td><td>$datetype</td><td>$name</td></tr>";
		}
	    $returnvalue .= "</table>";
	}

   return $returnvalue;
}

function agent_ages($type="all") {
	global $connection;
	$returnvalue = "";
	
	$agenttype = "All";
	if ($type==3) {
		$agenttype = "Group"; 
	}
	if ($type==1) {
		$agenttype = "Individual"; 
	}
	
	$returnvalue .= "<h2>Distribution of $agenttype agents by difference between date of birth and date of death.</h2>";
	$query = "select count(*), year(dateofdeath)-year(dateofbirth) from agent group by year(dateofdeath)-year(dateofbirth)";
	if ($type==3) { 
		$query = "select count(*), year(dateofdeath)-year(dateofbirth) from agent where agenttype = 3 group by year(dateofdeath)-year(dateofbirth)";
	}
	if ($type==1) { 
		$query = "select count(*), year(dateofdeath)-year(dateofbirth) from agent where agenttype = 1 group by year(dateofdeath)-year(dateofbirth)";
	}
	if ($debug) { echo "[$query]<BR>"; } 
	$returnvalue .= "<table>";
	$returnvalue .= "<tr><th>Age</th><th>Number of agents</th><th>Anomalous Agents</th></tr>";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($count,$age);
		$statement->store_result();
		while ($statement->fetch()) {
			$agents = "";
			if (($type=="all" && $count<20) || (($type==1 && $age < 20 || $age > 100)) || ($type==3 &&( $age <1 || $age > 50))) { 
				$query = "select lastname, agentid from agent where year(dateofdeath)-year(dateofbirth) = ? ";
	            if ($type==3) {
				    $query = "select lastname, agentid from agent where year(dateofdeath)-year(dateofbirth) = ? and agenttype = 3 ";
	            } 
	            if ($type==1) {
				    $query = "select lastname, agentid from agent where year(dateofdeath)-year(dateofbirth) = ? and agenttype = 1 ";
	            } 
				if ($debug) { echo "[$query]<BR>"; } 
				$statement_geo = $connection->prepare($query);
				if ($statement_geo) {
					$statement_geo->bind_param("i",$age);
					$statement_geo->execute();
					$statement_geo->bind_result($agentname,$agentid);
					$statement_geo->store_result();
					$separator = "";
					while ($statement_geo->fetch()) {
						$agents .= "$separator<a href='botanist_search.php?mode=details&id=$agentid'>$agentname</a>";
						$separator = "; ";
					}
				}
			}
			$returnvalue .= "<tr><td>$age</td><td>$count</td><td>$agents</td></tr>";
		}
	}
	$returnvalue .= "</table>";

    return $returnvalue;
}
 
function unlinked_collectionobjects() { 
	global $connection;
   $returnvalue = "";
   $query = "select collectionobject.collectionobjectid, collectionobject.timestampcreated, lastname, collectionobject.description, fieldnumber " .
   		" from collectionobject left join fragment on collectionobject.collectionobjectid = fragment.collectionobjectid " .
   		" left join agent on collectionobject.createdbyagentid = agent.agentid " .
   		" where collectionobject.collectionobjectid is not null " .
   		" and fragment.fragmentid is null";
	if ($debug) { echo "[$query]<BR>"; } 
    $returnvalue .= "<h2>Cases where a CollectionObject is not linked to any Item.  All of these are errors and need to be corrected.</h2>";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($collectionobjectid,$timestampcreated,$createdby, $description, $fieldnumber);
		$statement->store_result();
        $returnvalue .= "<h2>There are ". $statement->num_rows() . " orphan collection objects.</h2>";
	    $returnvalue .= "<table>";
	    $returnvalue .= "<tr><th>Record Created By</th><th>Date Created</th><th>Collector Number</th><th>Type</th><th>Description</th></tr>";
		while ($statement->fetch()) {
	        $returnvalue .= "<tr><td>$createdby</td><td><a href='specimen_search.php?mode=details&id=$collectionobjectid'>$createdby</a></td><td>$fieldnumber</td><td>$description</td></tr>";
		}
	    $returnvalue .= "</table>";
	}
   return $returnvalue;
} 
 
function unlinked_preparations() { 
	global $connection;
   $returnvalue = "";
   $query = "select preptype.name, preparation.identifier, preparation.preparationid, preparation.timestampcreated, lastname " .
   		" from preparation left join fragment on preparation.preparationid = fragment.preparationid " .
   		" left join agent on preparation.createdbyagentid = agent.agentid " .
   		" left join preptype on preparation.preptypeid = preptype.preptypeid " .
   		" where preparation.preparationid is not null and fragment.fragmentid is null " .
   		" and ((agent.lastname is null and preptype.name <> 'Lot') or agent.lastname is not null)";
	if ($debug) { echo "[$query]<BR>"; } 
    $returnvalue .= "<h2>Cases where a Preparation is not linked to any Item.  These may be errors or may be loaned lots with no further information.</h2>";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($preptype,$barcode,$preparationid, $datecreated, $createdby);
		$statement->store_result();
        $returnvalue .= "<h2>There are ". $statement->num_rows() . " orphan preparations.</h2>";
	    $returnvalue .= "<table>";
	    $returnvalue .= "<tr><th>Record Created By</th><th>Date Created</th><th>Preparation Barcode</th><th>Type</th></tr>";
		while ($statement->fetch()) {
	        $returnvalue .= "<tr><td>$createdby</td><td>$datecreated</td><td>$barcode</td><td>$preptype</td></tr>";
		}
	    $returnvalue .= "</table>";
	}
   return $returnvalue;
} 

function list_entry_for_collectingevents_without_locality() { 
	global $connection;
    $returnvalue = "";

    $query = "select count(*),agent.lastname, agent.agentid from collectionobject left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join agent on collectionobject.createdbyagentid = agent.agentid where localityid is null and year(collectionobject.timestampcreated) > 2009 group by agent.lastname";
	if ($debug) { echo "[$query]<BR>"; } 
    $returnvalue .= "<h2>Cases where a collecting event isn't linked to a locality.</h2>";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($count, $createdby, $agentid);
		$statement->store_result();
	    $returnvalue .= "<table>";
	    $returnvalue .= "<tr><th>Number of Records</th><th>Collection Object Record Created By</th></tr>";
		while ($statement->fetch()) {
	        $returnvalue .= "<tr><td>$count</td><td><a href='qc.php?mode=collectingevents_without_locality&agentid=$agentid'>$createdby</td></tr>";
		}
	    $returnvalue .= "</table>";
	}
   return $returnvalue;

}

function collectingevents_without_locality($agentid) { 
	global $connection;
   $returnvalue = "";
   $query = " select collectionobjectid, collectionobject.timestampcreated, agent.lastname, collectingevent.remarks, collectionobject.remarks " .
   		" from collectionobject left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid " .
   		" left join agent on collectionobject.createdbyagentid = agent.agentid " .
   		" where localityid is null and year(collectionobject.timestampcreated) > 2009 and agentid = ? ";
	if ($debug) { echo "[$query]<BR>"; } 
    $returnvalue .= "<h2>Cases where a Collecting Event isn't linked to a locality for $agent.</h2>";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->bind_param("s",$agentid);
		$statement->execute();
		$statement->bind_result($collectionobjectid, $datecreated, $createdby, $eventremarks, $objectremarks);
		$statement->store_result();
        $returnvalue .= "<h2>There are ". $statement->num_rows() . " collecting events without a locality for this person.</h2>";
	    $returnvalue .= "<table>";
	    $returnvalue .= "<tr><th>Record Created By</th><th>Date Created</th><th>Remarks</th></tr>";
		while ($statement->fetch()) {
	        $returnvalue .= "<tr><td>$createdby</td><td><a href='specimen_search.php?mode=details&id=$collectionobjectid'>$datecreated</a></td><td>$eventremarks $objectremarks</td></tr>";
		}
	    $returnvalue .= "</table>";
	}
   return $returnvalue;
}

 
function collectionobjects_without_barcodes() { 
	global $connection;
   $returnvalue = "";
   $query = " select c.collectionobjectid, a.lastname, c.timestampcreated, c.fieldnumber, fragment.text1, c.remarks " .
   		" from collectionobject c left join fragment on c.collectionobjectid = fragment.collectionobjectid " .
   		" left join preparation on fragment.preparationid = preparation.preparationid " .
   		" left join agent a on c.createdbyagentid = a.agentid " .
   		" where fragment.identifier is null and preparation.identifier is null " .
   		" order by a.lastname, c.timestampcreated ";
	if ($debug) { echo "[$query]<BR>"; } 
    $returnvalue .= "<h2>Cases where a Collection object lacks a barcode on one or more items.</h2>";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($collectionobjectid, $createdby, $datecreated, $fieldnumber, $herbarium, $objectremarks);
		$statement->store_result();
        $returnvalue .= "<h2>There are ". $statement->num_rows() . " collection objects without a barcode.</h2>";
	    $returnvalue .= "<table>";
	    $returnvalue .= "<tr><th>Record Created By</th><th>Date Created</th><th>Field Number</th><th>Herbarium</th><th>Remarks</th></tr>";
		while ($statement->fetch()) {
	        $returnvalue .= "<tr><td>$createdby</td><td><a href='specimen_search.php?mode=details&id=$collectionobjectid'>$datecreated</a></td><td>$fieldnumber</td><td>$herbarium</td><td>$objectremarks</td></tr>";
		}
	    $returnvalue .= "</table>";
	}
   return $returnvalue;
}

mysqli_report(MYSQLI_REPORT_OFF);
 
?>
