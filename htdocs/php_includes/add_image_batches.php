<?php

// scans one or more directory trees and places metadata about files
// found matching LAPI/GPI filenameing convention into a the IMAGE_BATCH, 
// IMAGE_SET, IMAGE_OBJECT, IMAGE_SET_collectionobject, and 
// IMAGE_LOCAL_FILE tables in Specify-HUH.

/*  *************  Begin Configuration  *********** */

// Set to true for more output.
$debug=TRUE;

// Generate thumbnail copies of image files
$generate_thumbs = TRUE;
// Generate full size jpeg images from tiffs
$generate_jpeg = TRUE;

// The path to the directory above the directory 
// in which image mount points can be found
// This directory will be provided in configuration in each
// image application, and not stored in the database.
$mountpointparent = "/mnt/";

// Array of base directories to be checked, starting from
// the directory containing the mountpoints.
// Expectation is that the top of this path will be 
// huhimagestorage/ and that the mount points Herbaria1, 
// Herbaria1, Herbaria3, etc will be found inside this directory.
// A base directory to check can be anywhere down this path.
// Each of these will be stored as IMAGE_LOCAL_FILE.base
// Each file found within will have the path from this base
// stored in IMAGE_LOCAL_FILE.path
//
// Path below huhimagestorage/Herbaria{n}/ will be used as batch name.
// This will be the path stored in the database.
$basedirectories[0] = 'huhimagestorage/Herbaria2/GPI-Types/HUHGPI0069';

// The, decided by convention for all applications, directory
// that contains the mountpoints and the list of mountpoints
define('MOUNTPOINT_REGEX',"/^huhimagestorage\/Herbaria[0-9]+/");
define('THUMBNAIL_WIDTH',250);

// Patterns for matching filenames.
$PREFIXPATTERN = "^(GH|AMES|ECON|FH|A|NEBC)";  // note, contains () affects array index in $matches
$BARCODEPATTERN = "([0-9]{8})";  // note, contains () affects array index in $matches
$EXTENSIONPATTERN = "\.(TIFF|tiff|tif|TIF|JPG|jpg|JPEG|JPG|DNG|dng)$"; // note, contains () affects array index in $matches
$SUFFIXPATTERN = "(_{0,1}[a-z]){0,1}"; // note, contains () affects array index in $matches

// Example of a file to skip.
$skip['GH00053156.tif']=1;  

/*  *************  End Configuration  *********** */

include_once('connection_library.php');
//include_once('specify_library.php');
//include_once('ImageShared.php');

//$connection = specify_spasa1_adm_connect();
$connection = specify_connect();


$errormessage = "";

if ($connection) {
   for ($x=0;$x<count($basedirectories);$x++) { 
       echo "Checking: " . $mountpointparent . $basedirectories[$x] . "\n";
       checkDirectory($mountpointparent, $basedirectories[$x]);
   }
} 

/* *************  Supporting functions *********** */

/** 
 * Recursively check a directory tree for image files. 
 * Process each file found.
 *
 * @param base Base directory for path (mount point parent).
 * @param path Path below base (below mount point parent).
 */
function checkDirectory($base, $path)  {
global $debug, $skip;
  
   if (!(preg_match("/\/$/",$path)==1)) { 
       $path = $path . '/';
   }
 
   if ($debug) { echo "PATH: $path\n"; } 
   echo "PATH: $path\n"; 

   @$files = scandir($base.$path);
   for ($i=0; $i<count($files); $i++) { 
     if ($files[$i]!='.' && $files[$i]!='..') { 
        if (is_dir($base.$path . $files[$i])) { 
          checkDirectory($base, $path . $files[$i]);
        } else { 
          if (!array_key_exists($files[$i],$skip)) { 
              if (filesize($base.$path.$files[$i])>0 ) { 
                  checkFile($base, $path, $files[$i]);
              } else { 
                  echo "Error: file with size of zero: " . $files[$i] . "\n"; 
              }
          } else {  
             echo "Skipping: " . $files[$i] . "\n"; 
          }
        }
     }
   } 

} 

/** 
 * Check to see if a file matches the pattern for image files, and 
 * if so, store a record of it in the database.
 * 
 * @param base Base directory for path (mount point parent).
 * @param path Path below base (below mount point parent).
 * @param filename name of file to check against filenaming pattern.
 */
function checkFile($base, $path, $filename) { 
global $debug, $connection, $PREFIXPATTERN, $BARCODEPATTERN, $SUFFIXPATTERN, $EXTENSIONPATTERN, $generate_thumbs, $generate_jpeg;
   if ($debug) { echo "File: $filename\n"; } 

   $fullpath = "$base$path";

   // extract a name to use as the batch for this file from the path.
   $batchname = preg_replace("/[\\/]/",":",preg_replace(MOUNTPOINT_REGEX,"",$path));
   if ($debug) { echo "Batchname: $batchname\n"; } 
   $batchdate = date("Y-M-d",filemtime("$fullpath"));

   if ($debug) { echo "File: $path $filename\n"; } 

   if (preg_match("/$PREFIXPATTERN$BARCODEPATTERN$SUFFIXPATTERN$EXTENSIONPATTERN/",$filename,$matches)) { 

       $herbarium = $matches[1]; // note, index affected by patterns that contain ()
       $barcode = $matches[2]; // note, index affected by patterns that contain ()
       $extension = $matches[4]; // note, index affected by patterns that contain ()
       $mimetype = mime_content_type($fullpath.'/'.$filename);
       if ($debug) { echo "Matched: $barcode $extension $mimetype \n"; } 


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
           $hasspecimenrecord = TRUE;
           if ($debug) { echo "fragmentid=[$fragmentid]\n"; } 
           // find out if we have databased this file already, if not, create a record.
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
                $insert = "insert into IMAGE_LOCAL_FILE (fragmentid,base,path,filename,barcode,extension,mimetype) values (?,?,?,?,?,?,?)";
                if ($debug) { echo "$insert\n"; } 
                $statement2 = $connection->stmt_init();
                $statement2->prepare($insert);
                $statement2->bind_param("issssss",$fragmentid,$base,$path,$filename,$barcode,$extension,$mimetype);
                if ($statement2->execute()) { 
                   if ($statement2->affected_rows==1) { 
                      $imagelocalfileid = $connection->insert_id;
                   }
                } else { 
                     echo "Query Error: ($statement2->errno) $statement2->error [$sql]\n";
                }
                $statement2->close();
           }
           $stmt->close();

           // check to see if an image_object exists for this file
           // if not, create image_object record, with image_set and image_batch if needed.
           if (preg_match("/thumbs\//",$path)) {
               // thumbs subdirectory isn't a separate batch.
               $parentpath = preg_replace("/thumbs\//",'',$path);
               $batchid = findOrCreateBatch($batchname,null, $parentpath, $filename);
           } else { 
               $batchid = findOrCreateBatch($batchname,null, $path, $filename);
           }

           $setid = findOrCreateSet($batchid,$collectionobjectid,$path,$filename);

           if (preg_match("/(TIFF|TIF|tiff|tif)$/",$filename)) { 
              $objecttypeid = 1;
           } 
           if (preg_match("/(DNG|dng)$/",$filename)) { 
              $objecttypeid = 7;
           } 
           if (preg_match("/(JPG|JPEG|jpg|jpeg)$/",$filename)) { 
              $objecttypeid = 4;
              if (preg_match("/^thumb_/",$filename)) { 
                  $objecttypeid = 2;
              }
              if (preg_match("/^half_/",$filename)) { 
                  $objecttypeid = 3;
              }
              if (preg_match("/^full_/",$filename)) { 
                  $objecttypeid = 4;
              }
           } 
           $objectid = findOrCreateObject($setid, $objecttypeid, $imagelocalfileid,$barcode,$path,$filename);


           // if requested, create a thumbnail, create an image object record, and link it to the image_set 
           if ($generate_thumbs) { 
              $thumbfileid = createThumbnail($base,$path,$filename,$fragmentid,$barcode);
              if ($thumbfileid!==FALSE) { 
                 $thumbfilename = preg_replace("/(TIFF|TIF|tiff|tif)$/",'jpg',$filename);
                 $objectid = findOrCreateObject($setid, 2, $thumbfileid,$barcode,$path."thumbs/","thumb_$thumbfilename");    
              }
           }

           if ($generate_jpeg) { 
               // if requested, create a fullsize jpeg, create an image object record, and link it to the image_set 
              $fullfileid = createJpeg($base,$path,$filename,$fragmentid,$barcode);
              if ($fullfileid!==FALSE) { 
                 $fullfilename = preg_replace("/(TIFF|TIF|tiff|tif)$/",'jpg',$filename);
                 $objectid = findOrCreateObject($setid, 4, $fullfileid,$barcode,$path."full/","full_$fullfilename");
              }

           } else { 
               // otherwise create an image object record for a generated on the fly jpeg and link it to the image_set 
               $objectid = findOrCreateObject($setid, 4, $imagelocalfileid,$barcode,$path,$filename,"&convert=jpeg");
           } 

           // make sure there is a link between the image_set and the collection object.
           $sql = "select id from IMAGE_SET_collectionobject where imagesetid = ? and collectionobjectid = ? ";
           if ($debug) { echo "$sql\n"; } 
           $stmt = $connection->stmt_init();
           $stmt->prepare($sql);
           $stmt->bind_param('ii',$setid,$collectionobjectid);
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
                $statement2->bind_param("ii",$collectionobjectid,$setid);
                if ($statement2->execute()) { 
                   if ($statement2->affected_rows==1) { 
                      $iscoid = $connection->insert_id;
                   }
                } else { 
                     echo "Query Error: ($statement2->errno) $statement2->error [$sql]\n";
                }
                $statement2->close();
           }
           $stmt->close();
           if ($debug) { echo "Done with file. [$iscoid]\n"; }
           
       } else { 
           echo "No barcode found for $herbarium-$barcode [$filename] $statement->error\n";
       } 
       if ($connection->error !=null ) { echo $connection->error . "\n"; }
   } // end match filename
   if ($debug) { echo "\n"; } 
}

/**
 * If the provided file is a tiff or jpeg file, create a thumbnail image and
 * a record in IMAGE_LOCAL_FILE for the thumbnail.
 * Thumbnail will be named thumb_{filename}.jpg, and placed into 
 * path/thumbs/.  For example from batch/A123456789.tif, a thumbnail
 * will be created as batch/thumbs/thumb_A123456789.jpg.  Thumbnail 
 * width is given by constand THUMBNAIL_WIDTH, height is proportional.
 * 
 * @param base Base directory for path (mount point parent).
 * @param path Path below base (below mount point parent).
 * @param filename the name of the file from which to create a thumbnail.
 * @param fragmentid fragment.fragmentid for the specimen with this barcode.
 * @param barcode the barcode of a specimen shown in the image.
 *
 * @return IMAGE_LOCAL_FILE.id for the thumbnail file, or FALSE on an error.
 */
function createThumbnail($base,$path,$filename,$fragmentid,$barcode) { 
   global $debug, $connection;
   $result = FALSE;
   if (preg_match("/(TIFF|TIF|tiff|tif)$/",$filename) || (preg_match("/(JPEG|JPG|jpeg|jpg)$/",$filename) && strpos($path,'thumbs')===FALSE && strpos($filename,'thumb')===FALSE)) { 
      // only create thumbnails for tiff files (or jpegs that aren't thumbnails
      if (!file_exists("$base$path/thumbs")) { 
          mkdir("$base$path/thumbs");
      }
      if (preg_match("/(TIFF|TIF|tiff|tif)$/",$filename)) { 
         $thumbfilename = preg_replace("/(TIFF|TIF|tiff|tif)$/",'jpg',$filename);
      } else if (preg_match("/(JPEG|JPG|jpeg|jpg)$/",$filename)) { 
         $thumbfilename = preg_replace("/(JPEG|JPG|jpeg)$/",'jpg',$filename);
      }
      if (!file_exists("$base$path/thumbs/thumb_$thumbfilename")) { 
         //? try to obtain the thumbnail from the exif
         // $thumb = exif_thumbnail("$base$path$filename");
         // test file doesn't have a thumbnail in the exif.
         if ($debug) { echo "Creating $path/thumbs/thumb_$thumbfilename\n"; }
         $image = new Imagick("$base$path/$filename");
         $image->thumbnailImage(THUMBNAIL_WIDTH,0);
         $image->writeImage("$base$path/thumbs/thumb_$thumbfilename");
         $image->destroy();
      }
      $tpath = $path."thumbs/";
      $tfile = "thumb_$thumbfilename";
      if (file_exists("$base$path/thumbs/thumb_$thumbfilename")) { 
         // check to see if the thumbnail is databased
         $sql = "select id from IMAGE_LOCAL_FILE where path = ? and filename = ? and fragmentid = ? ";
         if ($debug) { echo "$sql [$tpath][$tfile][$fragmentid]\n"; } 
         $stmt = $connection->stmt_init();
         $stmt->prepare($sql);
         $stmt->bind_param('ssi',$tpath,$tfile,$fragmentid);
         if ($stmt->execute()) { 
            $stmt->bind_result($ifid);
            $stmt->store_result();
            if ($stmt->num_rows>0) { 
               // if so, return the record
               $stmt->fetch();
               $result = $ifid;
            } else { 
               // if not, insert an image local file record.
               $insert = "insert into IMAGE_LOCAL_FILE (fragmentid,base,path,filename,barcode,extension,mimetype) values (?,?,?,?,?,?,?)";
               if ($debug) { echo "$insert [$fragmentid][$tpath][$tfile][$barcode][$extension][$mimetype]\n"; } 
               $statement2 = $connection->stmt_init();
               $statement2->prepare($insert);
               $extension = "jpg";
               $mimetype = "image/jpeg";
               $statement2->bind_param("issssss",$fragmentid,$base,$tpath,$tfile,$barcode,$extension,$mimetype);
               if ($statement2->execute()) { 
                  if ($statement2->affected_rows==1) { 
                     $result = $connection->insert_id;
                  }
               } else { 
                  echo "Query Error: ($statement2->errno) $statement2->error [$insert]\n";
               } 
               $statement2->close();
            }
         } else { 
             echo "Query Error: ($stmt->errno) $stmt->error [$sql]\n";
         }
      }
   }
   return $result;
}

 
/**
 * If the provided file is a tiff file, create a full size jpeg image 
 * and a record in IMAGE_LOCAL_FILE for the jpeg.
 * Thumbnail will be named full_{filename}.jpg, and placed into 
 * path/full/.  For example from batch/A123456789.tif, a jpeg
 * will be created as batch/full/full_A123456789.jpg.  
 * 
 * @param base Base directory for path (mount point parent).
 * @param path Path below base (below mount point parent).
 * @param filename the name of the file from which to create a jpeg.
 * @param fragmentid fragment.fragmentid for the specimen with this barcode.
 * @param barcode the barcode of a specimen shown in the image.
 *
 * @return IMAGE_LOCAL_FILE.id for the image file, or FALSE on an error.
 */
function createJpeg($base,$path,$filename,$fragmentid,$barcode) { 
   global $debug, $connection;
   $result = FALSE;
   if (preg_match("/(TIFF|TIF|tiff|tif)$/",$filename)) { 
      // only create jpegs for tiff files
      if (!file_exists("$base$path/full")) { 
          mkdir("$base$path/full");
      }
      $jpegfilename = preg_replace("/(TIFF|TIF|tiff|tif)$/",'jpg',$filename);
      if (!file_exists("$base$path/full/full_$jpegfilename")) { 
         if ($debug) { echo "Creating $path/full/full_$jpegfilename\n"; }
         $image = new Imagick("$base$path/$filename");
         $image->setImageCompressionQuality(95);
         $image->writeImage("$base$path/full/full_$jpegfilename");
         $image->destroy();
      }
      if (file_exists("$base$path/full/full_$jpegfilename")) { 
         $tpath = $path."full/";
         $tfile = "full_$jpegfilename";
         // check to see if the thumbnail is databased
         $sql = "select id from IMAGE_LOCAL_FILE where path = ? and filename = ? and fragmentid = ? ";
         if ($debug) { echo "$sql [$tpath][$tfile][$fragmentid]\n"; } 
         $stmt = $connection->stmt_init();
         $stmt->prepare($sql);
         $stmt->bind_param('ssi',$tpath,$tfile,$fragmentid);
         if ($stmt->execute()) { 
            $stmt->bind_result($ifid);
            $stmt->store_result();
            if ($stmt->num_rows>0) { 
               // if so, return the record
               $stmt->fetch();
               $result = $ifid;
            } else { 
               $insert = "insert into IMAGE_LOCAL_FILE (fragmentid,base,path,filename,barcode,extension,mimetype) values (?,?,?,?,?,?,?)";
               $statement2 = $connection->stmt_init();
               $statement2->prepare($insert);
               $extension = "jpg";
               $mimetype = "image/jpeg";
               if ($debug) { echo "$insert [$fragmentid][$tpath][$tfile][$barcode][$extension][$mimetype]\n"; } 
               $statement2->bind_param("issssss",$fragmentid,$base,$tpath,$tfile,$barcode,$extension,$mimetype);
               if ($statement2->execute()) { 
                  if ($statement2->affected_rows==1) { 
                     $result = $connection->insert_id;
                  }
               } else { 
                  echo "Query Error: ($statement2->errno) $statement2->error [$insert]\n";
               }
               $statement2->close();
            }
         } else { 
             echo "Query Error: ($stmt->errno) $stmt->error [$sql]\n";
         }
      }
   }
   return $result;
}

/**
 * Given a batch name (IMAGE_BATCH.batch_name), find and return the
 * batch id, otherwise create a new batch and return its id.  If
 * batch exists, it is not modified.
 *
 * @param batchname the name of the batch to find or create.
 * @param batchdate a creation date for the batch if created.
 * @param path to the batch, used to find a file to obtain 
 *   a batch date if none is provided and if batch is created.
 * @param filename within path checked for date in exif data
 *   to set a batch date if none is provided and if batch is created.
 *
 * @return IMAGE_BATCH.id for the image batch, or null on an error.
 */
function findOrCreateBatch($batchname,$batchdate, $path, $filename) { 
    global $connection, $debug, $mountpointparent;
    $result = null;
    $fullpath = "$mountpointparent$path";
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
              $result = $batchid;
           } else { 
              if ($batchdate==null) { 
                 // check the exif of the file to find a date created, use this as the batch date.
                 if ($debug) { echo "$fullpath$filename"; }
                 $exif = exif_read_data("$fullpath$filename","FILE");
                 $timestamp = $exif['FileDateTime'];
                 $batchdate = date("Y-m-d",$timestamp);
              }
              $labid = 4;
              $project = "Unknown, image files found on fileserver.";
              $remarks = "Path: $path";
              if (strpos(strtoupper($batchname),"GPI-TYPES")!==FALSE) { 
                 $labid = 2;
                 $project = "LAPI/GPI";
              }
              if (strpos(strtoupper($batchname),"NON-TYPES")!==FALSE) { 
                 $labid = 2;
                 $project = "Adhoc Non-Type imaging.";
              }
              $sql = "insert into IMAGE_BATCH (lab_id,production_date,batch_name,remarks,project,checkedForBarcodes) values (?, ?, ?, ?, ?, 0)";
              if ($debug) { echo "$sql\n"; } 
              $stmtinsert = $connection->prepare($sql);
              $stmtinsert->bind_param('issss',$labid,$batchdate,$batchname,$remarks,$project);
              if ($stmtinsert->execute()) { 
                 if ($stmtinsert->affected_rows==1) { 
                    $result = $connection->insert_id;
                 }
              } else { 
                 echo "Query Error: ($stmtinsert->errno) $stmtinsert->error [$sql]\n";
              }
              $stmtinsert->close();
           }
       } else { 
           echo "Query Error: ($statement->errno) $statement->error [$sql]\n";
       }
       $statement->close();
    } else { 
       echo "Query Error: ($statement->errno) $statement->error $connection->error \n";
    }

    return $result;
}

/**
 * Given a batch_id (IMAGE_SET.batch_id and collectionobjectid
 * (IMAGE_SET.specimen)id) find and return the set id (IMAGE_SET.id)
 * otherwise create a new set and return its id.  If
 * set exists, it is not modified.  
 * 
 * Assumes that a collection object will have only one image set within
 * a single batch.  
 *
 * TODO: Support _a, _b, etc files.
 *
 * @param batchid the id of the batch of the image set to find or create.
 * @param collectionobjectid the id of the collectionobject of the 
 *   image set to find or create.
 * @param path to the set, used to find a file to obtain 
 *   a date if none is provided and if set is created.
 * @param filename in set within path checked for date in exif data
 *   to set a date if none is provided and if set is created.
 *
 * @return IMAGE_SET.id for the image set, or null on an error.
 */
function findOrCreateSet($batchid,$collectionobjectid, $path, $filename) { 
    global $connection, $debug, $mountpointparent;
    $result = null;
    $fullpath = "$mountpointparent$path";
    $sql = "select ID from IMAGE_SET where batch_id = ? and specimen_id = ? ";
    if ($debug) { echo "$sql\n"; } 
    $statement = $connection->stmt_init();
    if ($statement->prepare($sql)) { 
       $statement->bind_param("ii",$batchid,$collectionobjectid);
       if ($statement->execute()) { 
           $statement->bind_result($imagesetid);
           $statement->store_result();
           if ($statement->num_rows>0) { 
              $statement->fetch();
              $result = $imagesetid;
           } else { 
              // check the exif of the file to find a year created, use this as the copyright date.
              $exif = exif_read_data($fullpath.$filename,"FILE");
              $timestamp = $exif['FileDateTime'];
              $batchdate = date("Y",$timestamp);
              $copyright = "Copyright© $batchdate President and Fellows of Harvard College";
              $accesstypeid = 114301;  // unrestricted 
              $sourcetypeid = 114101;  // curated specimen photo
              $activeflag = 1;
              $owner = "Harvard University";
              $remarks = "";
              $sql = "insert into IMAGE_SET (specimen_id,batch_id,access_type_id,source_type_id,active_flag,owner,copyright,remarks) values (?, ?, ?, ?, ?, ?, ?, ?)";
              if ($debug) { echo "$sql\n"; } 
              $stmtinsert = $connection->prepare($sql);
              $stmtinsert->bind_param('iiiiisss',$collectionobjectid,$batchid,$accesstypeid,$sourcetypeid,$activeflag,$owner,$copyright,$remarks);
              if ($stmtinsert->execute()) { 
                 if ($stmtinsert->affected_rows==1) { 
                    $result = $connection->insert_id;
                 }
              } else { 
                 echo "Query Error: ($stmtinsert->errno) $stmtinsert->error [$sql]\n";
              }
              $stmtinsert->close();
           }
       } else { 
           echo "Query Error: ($statement->errno) $statement->error [$sql] \n";
       }
       $statement->close();
    } else { 
       echo "Query Error: ($statement->errno) $statement->error $connection->error \n";
    }
    return $result;
}

/**
 *  Given image_set_id, object_type_id, image_local_file_id, find or create an 
 *  IMAGE_OBJECT record.
 */
function findOrCreateObject($imagesetid,$objecttypeid, $imagelocalfileid, $barcode, $path, $filename,$urlparam="") { 
    global $connection, $debug, $mountpointparent;
    $result = null;
    $fullpath = "$mountpointparent$path";
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
              $result = $imageobjectid;
           } else { 
              $repositoryid = 5;    // locally served 
              // set some defaults incase we can't read them from the exif
              // default bit depth if not found later
              $bitsid = st_lookup('24 - RGB'); 
              $photointerpid = 114902;  // RGB
              // default mime type from extension if not found later
              if (preg_match("/(TIFF|TIF|tiff|tif)$/",$filename)) { 
                 $mime = 'image/tiff';
              }
              if (preg_match("/(JPEG|JPG|jpeg|jpg)$/",$filename)) { 
                 $mime = 'image/jpeg';
              }
              if (preg_match("/(DNG|dng)$/",$filename)) { 
                 $mime = 'image/x-adobe-dng';
              }
              if (preg_match("/(JP2|jp2)$/",$filename)) { 
                 $mime = 'image/jpeg2000';
              }
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
                 $mime = $imageinfo['mime'];
                 // find filesize
                 $filesize = filesize("$fullpath$filename");
              } 
              // look up constants in ST_LOOKUP
              $mimeid = st_lookup($mime);
              if ($debug) { echo "[$mime][$mimeid]\n";}
              $compressionid = 114802;
              if ($pixelwidth>6500) { 
                 $resolution = "600";
              } else { 
                 $resolution = "300";
              }
              $uri = "id=$imagelocalfileid$urlparam";

              $sql = "insert into IMAGE_OBJECT (image_set_id,object_type_id,repository_id,mime_type_id,bits_per_sample_id,compression_id,photo_interp_id,active_flag,pixel_width,pixel_height,create_date,resolution,file_size,object_name,uri,image_local_file_id) values (?,?,?,?,?,?,?,1,?,?,?,?,?,?,?,?)";
              if ($debug) { echo "$sql\n[$imagesetid][$objecttypeid][$repositoryid][$mimeid][$uri][$imagelocalfileid]\n"; } 
              $stmtinsert = $connection->prepare($sql);
              $objectname = "$path$filename";
              $stmtinsert->bind_param('iiiiiiiiisssssi',$imagesetid,$objecttypeid,$repositoryid,$mimeid,$bitsid,$compressionid,$photointerpid,$pixelwidth,$pixelheight,$timestamp,$resolution,$filesize,$objectname,$uri,$imagelocalfileid);
              if ($stmtinsert->execute()) { 
                 if ($stmtinsert->affected_rows==1) { 
                    $result = $connection->insert_id;
                 }
              } else { 
                  echo "Query Error: ($stmtinsert->errno) $stmtinsert->error [$sql] \n";
              }
              $stmtinsert->close();
           }
       } else { 
           echo "Query Error: ($statement->errno) $statement->error [$sql] \n";
       }
       $statement->close();
    } else { 
       echo "Query Error: ($statement->errno) $statement->error $connection->error \n";
    }
    return $result;
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

?>
