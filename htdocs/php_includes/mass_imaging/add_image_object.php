<?php

include_once('../connection_library.php');;

$connection = specify_connect();

$debug = false;

$base = "/mnt/";

if ($argc < 5) {
	echo "USAGE: add_image_object.php <imagesetid> <srcfile> <destfile> <image_type> <activeflag> [barcode(s)]\n";
}

$imagesetid = $argv[1];
$srcfile		= $argv[2];
$destfile		= $argv[3];
$imagetype  = $argv[4];
$activeflag = $argv[5];

$barcode     = null;
$barcodelist = null;
if (isset($argv[6])) {
	$barcodelist = $argv[6];

	if (strlen($argv[6]) > 10 || strpos($argv[6], ';') !== false) {
		# don't set individual barcode if list is given
	} else {
		$barcode = $argv[6];
	}
}

$path_parts = pathinfo($srcfile);
$path       = preg_replace("/\/mnt\//", "", $path_parts['dirname'])."/";
$filename   = $path_parts['basename'];
$ext        = $path_parts['extension'];
$mimetype   = null;

$path_parts = pathinfo($destfile);
$destpath       = preg_replace("/\/mnt\//", "", $path_parts['dirname'])."/";
$destfilename   = $path_parts['filename'];

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


$imagelocalfileid = findOrCreateLocalFile($barcode, $destfilename, $base, $destpath, $ext, $mimetype);
$objectid = findOrCreateObject($imagesetid, $imagetype, $imagelocalfileid, $base, $path, $filename, $destpath, $destfilename, $mimetype, $activeflag, $barcodelist);

echo $objectid;

// JPG
//$filename = $barcode.".jpg";
//$path = $basepath."JPG/";
//$mimetype = "image/jpeg";
//$imagelocalfileid = findOrCreateLocalFile($barcode, $filename, $base, $path, "jpg", $mimetype);
//$objectid = findOrCreateObject($imagesetid, 4, $imagelocalfileid, $barcode, $base, $path, $filename, $mimetype, 1);

// JPG-Preview
//$filename = $barcode.".jpg";
//$path = $basepath."JPG-Preview/";
//$mimetype = "image/jpeg";
//$imagelocalfileid = findOrCreateLocalFile($barcode, $filename, $base, $path, "jpg", $mimetype);
//$objectid = findOrCreateObject($imagesetid, 3, $imagelocalfileid, $barcode, $base, $path, $filename, $mimetype, 1);

// JPG-Thumbnail
//$filename = $barcode.".jpg";
//$path = $basepath."JPG-Thumbnail/";
//$mimetype = "image/jpeg";
//$imagelocalfileid = findOrCreateLocalFile($barcode, $filename, $base, $path, "jpg", $mimetype);
//$objectid = findOrCreateObject($imagesetid, 2, $imagelocalfileid, $barcode, $base, $path, $filename, $mimetype, 1);

// DNG
//$filename = $barcode.".dng";
//$path = $basepath."DNG/";
//$mimetype = "image/x-adobe-dng";
//$imagelocalfileid = findOrCreateLocalFile($barcode, $filename, $base, $path, "dng", $mimetype);
//$objectid = findOrCreateObject($imagesetid, 7, $imagelocalfileid, $barcode, $base, $path, $filename, $mimetype, 0);

// CR2
//$filename = $barcode.".CR2";
//$path = $basepath."RAW/";
//$mimetype = "image/x-canon-cr2";
//$imagelocalfileid = findOrCreateLocalFile($barcode, $filename, $base, $path, "CR2", $mimetype);
//$objectid = findOrCreateObject($imagesetid, 8, $imagelocalfileid, $barcode, $base, $path, $filename, $mimetype, 0);


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



function findOrCreateObject($imagesetid, $objecttypeid, $imagelocalfileid, $base, $path, $filename, $destpath, $destfilename, $mimetype, $activeflag, $barcodes, $urlparam="") {
    global $connection, $debug;

    $imageobjectid = null;
    $fullpath = $base.$path;

    $sql = "select ID from IMAGE_OBJECT where image_set_id = ? and object_type_id = ? and image_local_file_id = ? ";
    if ($debug) { echo "$sql [$imagesetid][$objecttypeid][$imagelocalfileid]\n"; }
    $statement = $connection->stmt_init();
    if ($statement->prepare($sql)) {
       $statement->bind_param("iii",$imagesetid,$objecttypeid,$imagelocalfileid);
       if ($statement->execute()) {
           $statement->bind_result($imageobjectid);
           $statement->store_result();
           if ($statement->num_rows>0) {
              $statement->fetch();
           } else {
              $repositoryid = 5;    // locally served
              // set some defaults incase we can't read them from the exif
              // default bit depth if not found later
              $bitsid = st_lookup('24 - RGB');
              $photointerpid = 114902;  // RGB

              // check the exif of the file to find filesize and other parameters
              $exif = exif_read_data($fullpath.$filename,"FILE");
              if ($exif!==FALSE && array_key_exists('ImageWidth',$exif)) {
                 $timestamp = $exif['DateTime'];
                 $filesize = $exif['FileSize'];
                 if (array_key_exists('MimeType',$exif)) {
                    $mime = $exif['MimeType'];
                 }
                 $pixelwidth = $exif['ImageWidth'];
                 $pixelheight = $exif['ImageLength'];
                 if (array_key_exists('BitsPerSample',$exif)) {
                    $bitsarray = $exif['BitsPerSample'];
                    $bits = 0;
                    foreach ($bitsarray as $val) {
                        $bits += $val;
                    }
                    // assume a default
                    if ($bits==1) { $bitsid = st_lookup('1 - bitonal'); }
                    if ($bits==4) { $bitsid = st_lookup('4 - grayscale'); }
                    if ($bits==8) { $bitsid = st_lookup('8 - grayscale'); }
                    if ($bits==24) { $bitsid = st_lookup('24 - RGB'); }
                    if ($bits==32) {
                       $photointerpid = 114902;  // CMYK
                       $bitsid = st_lookup('32 - CMYK');
                    }
                    if ($bits==48) { $bitsid = st_lookup('48'); }
                 }
              } else {
                 // try getimagesize to get info about file
                 $imageinfo = getimagesize("$fullpath$filename");
                 $pixelwidth = $imageinfo[0];
                 $pixelheight = $imageinfo[1];
                 // find filesize
                 $filesize = filesize("$fullpath$filename");
              }
              // look up constants in ST_LOOKUP
              $mimeid = st_lookup($mimetype);
              if ($debug) { echo "[$mimetype][$mimeid]\n";}
              $compressionid = 114802;
              if ($pixelwidth>6500) {
                 $resolution = "600";
              } else {
                 $resolution = "300";
              }
              $uri = "id=$imagelocalfileid$urlparam";

              $sql = "insert into IMAGE_OBJECT (image_set_id,object_type_id,repository_id,active_flag,mime_type_id,bits_per_sample_id,compression_id,photo_interp_id,pixel_width,pixel_height,create_date,resolution,file_size,object_name,uri,image_local_file_id,barcodes) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
              if ($debug) { echo "$sql\n[$imagesetid][$objecttypeid][$repositoryid][$mimeid][$objectname][$uri][$imagelocalfileid][$barcodes]\n"; }
              $stmtinsert = $connection->prepare($sql);
              $objectname = "$destpath$destfilename";
              $stmtinsert->bind_param('iiiiiiiiiisssssis',$imagesetid,$objecttypeid,$repositoryid,$activeflag,$mimeid,$bitsid,$compressionid,$photointerpid,$pixelwidth,$pixelheight,$timestamp,$resolution,$filesize,$objectname,$uri,$imagelocalfileid,$barcodes);
              if ($stmtinsert->execute()) {
                 if ($stmtinsert->affected_rows==1) {
                    $imageobjectid = $connection->insert_id;
                 }
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

    return $imageobjectid;
}


/**
 * Lookup a value in the ST_LOOKUP table.
 *
 * @param string the value to lookup.
 *
 * @return the id for the value, or null if not found.
 */
function st_lookup($string) {
    global $connection,$debug;
    $result = null;
    $sql = "select ID from ST_LOOKUP where NAME = ? ";
    if ($debug) { echo "$sql [$string]\n"; }
    $statement = $connection->stmt_init();
    if ($statement->prepare($sql)) {
       $statement->bind_param("s",$string);
       if ($statement->execute()) {
           $statement->bind_result($id);
           $statement->store_result();
           if ($statement->num_rows>0) {
              if ($statement->fetch()) {
                 $result = $id;
              }
           }
       }
    }
    return $result;
}
