<?php

include_once('../connection_library.php');;

$connection = specify_connect();

$debug = false;

if (!isset($argv[1]) || !isset($argv[2])) {
	echo "USAGE: add_image_set.php <batchid> <set identifier> [barcode1] [barcode2] ... \n";
	exit(1);
}

$batchid  = $argv[1];
$identifier = $argv[2]; # unique identifier for the set (camera serialnumber + image datetime for the mass imaging workflow)

$imagesetid = findOrCreateSet($batchid,$identifier);

# link associated collection objects given a list of barcodes
if (isset($argv[3])) {
	$barcodes = array_slice($argv,3);

	foreach ($barcodes as $barcode) {
		$collectionobjectid = lookupCollectionObjectId($barcode);
    	linkImageSet($imagesetid,$collectionobjectid);
	}
}
echo $imagesetid;


function lookupCollectionObjectId($barcode) {
    global $connection, $debug;

	// check to see if we have a collection object matching this file
   	$sql = "select fragmentid, collectionobjectid from fragment where identifier = ? ";
   	if ($debug) { echo "$sql\n"; }
   	$statement = $connection->stmt_init();
   	$statement->prepare($sql);
   	$statement->bind_param("s",$barcode);
   	$statement->execute();
   	$statement->bind_result($fragmentid,$collectionobjectid);
   	$statement->store_result();
   	$hasspecimenrecord = FALSE;
   	if ($statement->fetch()) {
		return $collectionobjectid;
   	} else {
   		error_log("No fragment or collectionobject found for barcode $barcode");
   	}
}


function findOrCreateSet($batchid,$identifier) {
    global $connection, $debug;

	$imagesetid = null;

    $sql = "select ID from IMAGE_SET where set_identifier = ?";
    if ($debug) { echo "$sql\n"; }
    $statement = $connection->stmt_init();
    if ($statement->prepare($sql)) {
       $statement->bind_param("s",$identifier);
       if ($statement->execute()) {
           $statement->bind_result($imagesetid);
           $statement->store_result();
           if ($statement->num_rows>0) {
              $statement->fetch();
           } else {
              $copyright = "Copyright© by President and Fellows of Harvard College";
              $accesstypeid = 114301;  // unrestricted
              $sourcetypeid = 114101;  // curated specimen photo
              $activeflag = 1;
              $owner = "Harvard University";
              $remarks = "";
              $sql = "insert into IMAGE_SET (specimen_id,batch_id,access_type_id,source_type_id,active_flag,owner,copyright,remarks,set_identifier) values (0, ?, ?, ?, ?, ?, ?, ?, ?)";
              if ($debug) { echo "$sql\n"; }
              $stmtinsert = $connection->prepare($sql);
              $stmtinsert->bind_param('iiiissss',$batchid,$accesstypeid,$sourcetypeid,$activeflag,$owner,$copyright,$remarks,$identifier);
              if ($stmtinsert->execute()) {
                 if ($stmtinsert->affected_rows==1) {
                    $imagesetid = $connection->insert_id;
                 }
              } else {
                 error_log("Query Error: ($stmtinsert->errno) $stmtinsert->error [$sql]\n");
                 exit(1);
              }
              $stmtinsert->close();
           }
       } else {
           error_log("Query Error: ($statement->errno) $statement->error [$sql] \n");
           exit(1);
       }
       $statement->close();
    } else {
       error_log("Query Error: ($statement->errno) $statement->error $connection->error \n");
       exit(1);
    }

    return $imagesetid;
}


function linkImageSet($imagesetid, $collectionobjectid) {
	global $connection, $debug;

	$iscoid = null;

	// make sure there is a link between the image_set and the collection object.
	$sql = "select id from IMAGE_SET_collectionobject where imagesetid = ? and collectionobjectid = ? ";
	if ($debug) { echo "$sql\n"; }
		$stmt = $connection->stmt_init();
		$stmt->prepare($sql);
		$stmt->bind_param('ii',$imagesetid,$collectionobjectid);
		$stmt->execute();
		$stmt->bind_result($iscoid);
		$stmt->store_result();
	if ($stmt->num_rows>0) {
		$stmt->fetch();
	} else {
		$sql = "insert into IMAGE_SET_collectionobject (collectionobjectid, imagesetid) values (?, ?)";
		if ($debug) { echo "$sql\n"; }
		$statement2 = $connection->stmt_init();
		$statement2->prepare($sql);
		$statement2->bind_param("ii",$collectionobjectid,$imagesetid);
		if ($statement2->execute()) {
		   if ($statement2->affected_rows==1) {
			  $iscoid = $connection->insert_id;
		   }
		} else {
			 error_log("Query Error: ($statement2->errno) $statement2->error [$sql]\n");
			 exit(1);
		}
		$statement2->close();
	}
	$stmt->close();

	return $iscoid;
}
?>
