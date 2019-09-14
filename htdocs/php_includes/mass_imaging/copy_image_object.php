<?php

include_once('../connection_library.php');;

$connection = specify_connect();

$debug = false;

$base = "/mnt/";

if ($argc < 4) {
	echo "USAGE: copy_image_object.php <imageobjectid> <file> <activeflag> [barcode(s)]\n";
}

$imageobjectid = $argv[1];
$file		= $argv[2];
$activeflag = $argv[3];

$barcode     = null;
$barcodelist = null;
if (isset($argv[4])) {
	$barcodelist = $argv[4];

	if (strlen($argv[4]) > 10 || strpos($argv[4], ';') !== false) {
		# don't set individual barcode if list is given
	} else {
		$barcode = $argv[4];
	}
}

$path_parts = pathinfo($file);
$path       = preg_replace("/\/mnt\//", "", $path_parts['dirname'])."/";
$filename   = $path_parts['basename'];
$ext        = $path_parts['extension'];
$mimetype   = null;

switch (strtolower($ext)) {
	case "jpg":
		$ext = "jpg";
		$mimetype = "image/jpeg";
		break;

	case "tiff":
		$ext = "tiff";
		$mimetype = "image/tiff";
		break;

	case "dng":
		$ext = "dng";
		$mimetype = "image/x-adobe-dng";
		break;

	case "cr2":
		$ext = "CR2";
		$mimetype = "image/x-canon-cr2";
		break;

	default:

		error_log("Invalid file extension $ext for $file");
		exit(1);
}

$imagelocalfileid = findOrCreateLocalFile($barcode, $filename, $base, $path, $ext, $mimetype);
findOrCreateObject($imageobjectid, $imagelocalfileid, $base, $path, $filename, $mimetype, $activeflag, $barcodelist);
//echo $objectid;

function findOrCreateLocalFile($barcode, $filename, $base, $path, $extension, $mimetype) {
	global $connection, $debug;

	$imagelocalfileid = null;

	$sql = "select id from IMAGE_LOCAL_FILE where path = ? and filename = ? ";
	if ($debug) { echo "$sql\n"; }
	$stmt = $connection->stmt_init();
	$stmt->prepare($sql);
	$stmt->bind_param('ss',$path,$filename);
	$stmt->execute();
	$stmt->bind_result($imagelocalfileid);
	$stmt->store_result();
	if ($stmt->num_rows>0) {
		$stmt->fetch();
	} else {
		$insert = "insert into IMAGE_LOCAL_FILE (base,path,filename,barcode,extension,mimetype) values (?,?,?,?,?,?)";
		if ($debug) { echo "$insert\n"; }
		$statement2 = $connection->stmt_init();
		$statement2->prepare($insert);
		$statement2->bind_param("ssssss",$base,$path,$filename,$barcode,$extension,$mimetype);
		if ($statement2->execute()) {
			if ($statement2->affected_rows==1) {
				$imagelocalfileid = $connection->insert_id;
			}
		} else {
			error_log("Query Error: ($statement2->errno) $statement2->error [$sql]\n");
			exit(1);
		}
		$statement2->close();
   	}
   	$stmt->close();

   	return $imagelocalfileid;
}


function findOrCreateObject($imageobjectid, $imagelocalfileid, $base, $path, $filename, $mimetype, $activeflag, $barcodes, $urlparam="") {
    global $connection, $debug;

    $newobjectid = null;
    $fullpath = $base.$path;

    $sql = "select ID from IMAGE_OBJECT where image_local_file_id = ? ";
    if ($debug) { echo "$sql [$imagelocalfileid]\n"; }
    $statement = $connection->stmt_init();
    if ($statement->prepare($sql)) {
       $statement->bind_param("i",$imagelocalfileid);
       if ($statement->execute()) {
           $statement->bind_result($newobjectid);
           $statement->store_result();
           if ($statement->num_rows>0) {
              $statement->fetch();
           } else {
              $objectname = "$path$filename";
              $sql = "insert delayed into IMAGE_OBJECT (image_set_id,object_type_id,repository_id,active_flag,mime_type_id,bits_per_sample_id,compression_id,photo_interp_id,pixel_width,pixel_height,create_date,resolution,file_size,object_name,uri,image_local_file_id,barcodes)
               select (image_set_id,object_type_id,repository_id,?,mime_type_id,bits_per_sample_id,compression_id,photo_interp_id,pixel_width,pixel_height,create_date,resolution,file_size,?,uri,?,? from IMAGE_OBJECT where ID = ?)";
              if ($debug) { echo "$sql\n[$activeflag][$objectname][$imagelocalfileid][$barcodes][$imageobjectid]\n"; }
              $stmtinsert = $connection->prepare($sql);
              $stmtinsert->bind_param('isisi',$activeflag,$objectname,$imagelocalfileid,$barcodes,$imageobjectid);
              if ($stmtinsert->execute()) {
                 //if ($stmtinsert->affected_rows==1) {
                 //   $imageobjectid = $connection->insert_id;
                 //}
              } else {
                  error_log("Query Error: ($stmtinsert->errno) $stmtinsert->error [$sql] \n");
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

    //return $imageobjectid;
}
