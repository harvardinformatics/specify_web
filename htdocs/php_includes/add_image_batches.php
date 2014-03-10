<?php

// scans one or more directory trees and places metadata about files
// found matching LAPI/GPI filenameing convention into a the IMAGE_BATCH, 
// IMAGE_SET, IMAGE_OBJECT, IMAGE_SET_collectionobject tables in Specify-HUH.

/*  *************  Begin Configuration  *********** */

// Set to true for more output.
$debug=FALSE;

// Generate thumbnail copies of image files
$generate_thumbs = FALSE;
// Generate full size jpeg images from tiffs
$generate_jpeg = FALSE;

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
$basedirectories[0] = 'huhimagestorage/Herbaria2/GPI-Types';

// The, decided by convention for all applications, directory
// that contains the mountpoints and the list of mountpoints
define('MOUNTPOINT_REGEX',"/^huhimagestorage\/Herbaria[0-9]+/")

// Patterns for matching filenames.
$PREFIXPATTERN = "^(GH|AMES|ECON|FH|A|NEBC)";  // note, contains () affects array index in $matches
$BARCODEPATTERN = "([0-9]{8})";  // note, contains () affects array index in $matches
$EXTENSIONPATTERN = "\.(TIFF|tiff|tif|TIF|JPG|jpg|JPEG|JPG|DNG|dng)$"; // note, contains () affects array index in $matches
$SUFFIXPATTERN = "(_[a-z]){0,1}"; // note, contains () affects array index in $matches

/*  *************  End Configuration  *********** */

include_once('connection_library.php');
include_once('specify_library.php');
include_once('ImageShared.php')

//$connection = specify_spasa1_adm_connect();
$connection = specify_connect();


$errormessage = "";

if ($connection) {
   for ($x=0;$x<count($basedirectories);$x++) { 
       echo "Checking: " . $basedirectories[$x] . "\n";
       checkDirectory($mountpointparent, $basedirectories[$x]);
   }
} 

/** 
 * Recursively check a directory tree for image files. 
 * Process each file found.
 *
 * @param base Base directory for path.
 * @param path Path below base
 */
function checkDirectory($base, $path)  {
global $debug;
 
   if ($debug) { echo "PATH: $path\n"; } 
   echo "PATH: $path\n";

   @$files = scandir($path);
   for ($i=0; $i<count($files); $i++) { 
     if ($files[$i]!='.' && $files[$i]!='..') { 
        if (is_dir($path . '/' . $files[$i])) { 
          checkDirectory($base, $path . '/' . $files[$i]);
        } else { 
          checkFile($base, $path,$files[$i]);
        }
     }
   } 

} 

function createThumbnail($base,$path,$filename) { 
   if (preg_match("/(TIFF|TIF|tiff|tif)$/",$filename) { 
      // only create thumbnails for tiff files
      if (!file_exists("$base$path/thumbs") { 
          mkdir("$base$path/thumbs");
      }
      if (!file_exists("$base$path/thumbs/thumb_$filename") { 
         $image = new Imagick("$base$path/$filename");
         $image->thumbnailImage(150,0);
         $image->writeImage("$base$path/thumbs/thumb_$filename");
         $image->destroy();
      }
   }
}

/** 
 * Check to see if a file matches the pattern for image files, and 
 * if so, store a record of it in the database.
 * 
 * @param base Base directory for path.
 * @param path Path below base
 * @param filename name of file to check against filenaming pattern.
 */
function checkFile($base, $path,$filename) { 
global $debug, $connection, $PREFIXPATTERN, $BARCODEPATTERN, $SUFFIXPATTERN, $EXTENSIONPATTERN;

   $fullpath = "$base$path";

   // extract a name to use as the batch for this file from the path.
   $batchname = preg_replace("/\/\\/",":",preg_replace(MOUNTPOINT_REGEX,"",$path));
   $batchdate = date("Y-M-d",filemtime("$base$path");
 

   if ($debug) { echo "$batchname"; } 
   if ($debug) { echo "$path $filename"; } 

   if (preg_match("/$PREFIXPATTERN$BARCODEPATTERN$SUFFIXPATTERN$EXTENSIONPATTERN/",$filename,$matches)) { 

       $herbarium = $matches[1]; // note, index affected by patterns that contain ()
       $barcode = $matches[2]; // note, index affected by patterns that contain ()
       $extension = $matches[4]; // note, index affected by patterns that contain ()
       $mimetype = mime_content_type($path.'/'.$filename);
       if ($debug) { echo " matched $barcode $extension $mimetype "; } 


       // check to see if we have a collection object matching this file
       $sql = "select fragmentid, collectionobjectid from fragment where identifier = ? ";
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
           $sql = "select id from IMAGE_LOCAL_FILE if path = ? and filename = ? ";
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
                $statement2 = $connection->stmt_init();
                $statement2->prepare($insert);
                $statement->bind_param("issssss",$fragmentid,$base,$path,$filename,$barcode,$extension,$mimetype);
                $statement2->execute();
                if ($statement2->affected_rows==1) { 
                     $imagelocalfileid = $connection->insert_id;
                }
                $statement2->close();
           }
             
           $stmt->close();
 
           // check to see if an image_object exists for this file

           // if not, create image_object record, with image_set and image_batch if needed.
           $batchid = findOrCreateBatch($batchname,$batchdate, $path);

           // if needed, create a thumbnail, and link it to the image_set 

           // make sure there is a link between the image_set and the collection object.
           
       } else { 
           echo "No barcode found for $herbarium-$barcode [$filename] $statement->error\n";
       } 
       if ($hasspecimenrecord) { 
           createThumbnail($base, $path, $filename);
       }
       echo $connection->error;
   } // end match filename

   if ($debug) { echo "\n"; } 

}
 
function findOrCreateBatch($batchname,$batchdate, $path) { 
    global $connection, $debug;
    $result = null;
    $sql = "select ID from IMAGE_BATCH where BATCH_NAME = ? ";
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
              $stmtinsert = $connection->prepare($sql);
              $stmtinsert->bind_param('isss',$labid,$batchdate,$batchname,$remarks,$project);
              $stmtinsert->execute();
              if ($stmtinsert->affected_rows==1) { 
                  $result = $connection->insert_id;
              }
              $stmtinsert->close();
           }
       } else { 
           echo "Query Error: ($statement->errno) $statement->error ";
       }
       $statement->close();
    } else { 
       echo "Query Error: ($statement->errno) $statement->error $connection->error ";
    }

    return $result;
}

?>