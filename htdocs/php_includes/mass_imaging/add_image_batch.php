<?php

include_once('../connection_library.php');

$connection = specify_connect();

$debug = false;

if (! isset($argv[1])) {
	echo "USAGE: php add_batch.php <batchdir>\n";
	exit(1);
}

$batchdir = $argv[1];
    
$dirs = array_filter(explode('/', $batchdir));
$len = count($dirs);
$host = $dirs[$len-2];
$user = $dirs[$len-1];
$session = $dirs[$len];
$batchname = $host."/".$user."/".$session;
$batchid = 0;

preg_match('/^\d{4}-\d{2}-\d{2}/', $session, $matches);
if ($debug) {
	echo "batchname = ".$batchname."\n";
	echo "dirs, $len: " .implode(",", $dirs)."\n";
	echo "matches: " .implode(",",$matches)."\n";
}
if (! isset($matches[0])) {
	error_log("Directory not formatted as a date: " . $session."\n");
	exit(1);
}
$batchdate = $matches[0];   
   
    
$sql = "select ID from IMAGE_BATCH where BATCH_NAME = ? ";
if ($debug) { echo "$sql\n"; }
$statement = $connection->stmt_init();
if ($statement->prepare($sql)) { 
    $statement->bind_param("s",$batchname);
    if ($statement->execute()) { 
        $statement->bind_result($batchid);
        $statement->store_result();
        if ($statement->num_rows>0) { 
            $statement->fetch();
        } else { 
        	$sql = "insert into IMAGE_BATCH (lab_id,production_date,batch_name,remarks,project,checkedForBarcodes) values (4, ?, ?, null, null, 1)";
        	if ($debug) { echo "$sql\n"; } 
        	$stmtinsert = $connection->prepare($sql);
        	$stmtinsert->bind_param('ss',$batchdate,$batchname);
        	if ($stmtinsert->execute()) { 
        		if ($stmtinsert->affected_rows==1) { 
            		$batchid = $connection->insert_id;
            	}
        	} else { 
        		error_log("Query Error: ($stmtinsert->errno) $stmtinsert->error [$sql]\n");
        		exit(1);
        	}
        	$stmtinsert->close();
    	}
	} else { 
    	error_log("Query Error: ($statement->errno) $statement->error [$sql]\n");
    	exit(1);
	}
	$statement->close();
} else { 
	error_log("Query Error: ($statement->errno) $statement->error $connection->error \n");
	exit(1);
}

echo $batchid."\n";
    
?>