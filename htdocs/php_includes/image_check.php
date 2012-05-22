<?php

// scans one or more directory trees and places metadata about files
// found matching LAPI/GPI filenameing convention into a database table
// defined by: 
// create table IMAGE_LOCAL_FILE (id bigint not null primary key auto_increment, base text,  path text, filename varchar(900), extension varchar(4), barcode varchar(10), mimetype varchar(255), fragmentid bigint);

// NOTE:
// New rows are added to IMAGE_LOCAL_FILE for each image found.  To update the
// list of image files, first fire the query: 
// 
// delete * from IMAGE_LOCAL_FILE;
//
// then re-run image_check.php.

// Set to true for more output.
$debug=FALSE;

// Array of base directories to be checked.
// Each of these will be stored as IMAGE_LOCAL_FILE.base
// Each file found within will have the path from this base
// stored in IMAGE_LOCAL_FILE.path
$basedirectories[0] = "/mount/hideki/private/var/automount/nfs_reshares/share_root-1/";

include_once('connection_library.php');
include_once('specify_library.php');

$connection = specify_spasa1_adm_connect();
$errormessage = "";

$mode = "rebuild";

$PREFIXPATTERN = "^(GH|AMES|ECON|FH|A|NEBC)";  // note, contains () affects array index in $matches
$BARCODEPATTERN = "([0-9]{8})";  // note, contains () affects array index in $matches
$EXTENSIONPATTERN = "\.([Tt][Ii][Ff]{1,2})$"; // note, contains () affects array index in $matches
$SUFFIXPATTERN = "(_[a-z]){0,1}"; // note, contains () affects array index in $matches

if ($connection) {

for ($x=0;$x<count($basedirectories);$x++) { 
   echo "Checking: " . $basedirectories[$x] . "\n";
   checkDirectory($basedirectories[$x], $basedirectories[$x]);
}

}

/** 
 * Check a directory for image files. 
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

   $pathbelowbase = substr($path,strlen($base));

   if ($debug) { echo "$pathbelowbase $filename"; } 

   if (preg_match("/$PREFIXPATTERN$BARCODEPATTERN$SUFFIXPATTERN$EXTENSIONPATTERN/",$filename,$matches)) { 

       $barcode = $matches[2]; // note, index affected by patterns that contain ()
       $extension = $matches[4]; // note, index affected by patterns that contain ()
       $mimetype = mime_content_type($path.'/'.$filename);
       if ($debug) { echo " matched $barcode $extension $mimetype "; } 

       $sql = "select fragmentid from fragment where identifier = ? ";
        $statement = $connection->prepare($sql);
        if ($statement) {
           $statement->bind_param("s",$barcode);
           $statement->execute();
           $statement->bind_result($fragmentid);
           $statement->store_result();
           while ($statement->fetch()) {
               if ($debug) { echo "$fragmentid"; } 
               $insert = "insert into IMAGE_LOCAL_FILE (fragmentid,base,path,filename,barcode,extension,mimetype) values ('$fragmentid','$base','$pathbelowbase','$filename','$barcode','$extension','$mimetype')";
               $statement2 = $connection->prepare($insert);
               if ($statement2) {
                   // $statement->bind_param("s",$barcode);
                   $statement2->execute();
                   $statement2->close();
               }
               echo $connection->error;
           } // end statement fetch
        } // end if statement
        echo $connection->error;
   } // end match filename

   if ($debug) { echo "\n"; } 

}
 
?>
