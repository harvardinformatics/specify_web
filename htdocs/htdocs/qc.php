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

} else {
	echo "<h2>QC pages are available only within HUH</h2>"; 
}

echo pagefooter();

// ******* main code block ends here, supporting functions follow. *****

function menu() { 
   $returnvalue = "";

   $returnvalue .= "<div>";
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
 
 
mysqli_report(MYSQLI_REPORT_OFF);
 
?>
