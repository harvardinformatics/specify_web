<?php

include_once('../connection_library.php');

$connection = specify_connect();

$debug = false;

if (! isset($argv[1])) {
	echo "USAGE: php add_tr_batch.php <imagebatchid>\n";
	exit(1);
}

$imagebatchid = $argv[1];
$imagebatchname = 'NOTSET';
$trbatchid = 0;

# fetch the image batch
$sql = "select SUBSTRING_INDEX(batch_name,'/',-2) as path from IMAGE_BATCH where id = ? ";
if ($debug) { echo "$sql\n"; }
$statement = $connection->stmt_init();
if ($statement->prepare($sql)) {
    $statement->bind_param("s",$imagebatchid);
    if ($statement->execute()) {
        $statement->bind_result($imagebatchname);
        $statement->store_result();
        if ($statement->num_rows>0) {
            $statement->fetch();
        } else {
					error_log("Error: No image batch found for id $imagebatchid\n");
					exit(1);
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


# Check for existing tr_batch or insert new
$sql = "select tr_batch_id from TR_BATCH where image_batch_id = ? ";
if ($debug) { echo "$sql\n"; }
$statement = $connection->stmt_init();
if ($statement->prepare($sql)) {
    $statement->bind_param("i",$imagebatchid);
    if ($statement->execute()) {
        $statement->bind_result($trbatchid);
        $statement->store_result();
				if ($debug) { echo "TR Batch exists for image batch $imagebatchid, skipping.\n"; }
				exit(0);
    } else {
        $sql = "insert into TR_BATCH (path, image_batch_id) values (?, ?)";
        if ($debug) { echo "$sql\n"; }
      	$stmtinsert = $connection->prepare($sql);
      	$stmtinsert->bind_param('si',$imagebatchname,$imagebatchid);
      	if ($stmtinsert->execute()) {
      		if ($stmtinsert->affected_rows==1) {
						$trbatchid = $connection->insert_id;
          }
        } else {
      		error_log("Query Error: ($stmtinsert->errno) $stmtinsert->error [$sql]\n");
      		exit(1);
      	}
      	$stmtinsert->close();

				# Call stored procedure to set up batch images
				$sql = 'CALL setup_tr_batch(?, ?)';
	      $stmt = $connection->prepare($sql);
	      $stmt->bind_param('is', $trbatchid, $imagebatchname);
	      if ($stmt->execute()) {
					if ($debug) { echo "Added TR_BARTCH $imagebatchname\n"; }
				}	else {
					error_log("Query Error: ($stmt->errno) $stmt->error [$sql]\n");
					exit(1);
				}
		}
		
	$statement->close();
} else {
	error_log("Query Error: ($statement->errno) $statement->error $connection->error \n");
	exit(1);
}

echo $trbatchid."\n";

?>
