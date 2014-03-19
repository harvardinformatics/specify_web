<?php

/**

File for image delivery from huh image storage.

Requires that the plan layed out in BugID: 648 be followed.

Expects the following conventions: 

/Path from root of filesystem to directory above mountpoints ($base).

A standard name of the directory in which to place the mountpoints:
"huhimagestorage"

Standard names for the mount points: "Herbaria1", "Herbaria2".

Consistent root for mounts, either /Herbaria1/ or /Herbaria2/.

Thus with path from root of filesystem as "/mnt/" fstab entry is: 
//128.102.155.56/Herbaria1 /mnt/huhimagestorage/Herbaria1 cifs user...

Any application working with this storage scheme can set the path from the root
of the filesystem to the directory above the mountpoints (/mnt/ in the example
above, $base herein) as a configuration variable that can vary from machine to machine.

The path starting with huhimagestorage down to the image files is stored as
the path in the database (in IMAGE_LOCAL_FILE.path).   

This scheme will work so long as there is a consistent:

(1) Name of directory containing mountpoints ("huhimagestorage").

(2) Consistent names of mounts (Herbaria1, Herbaria2 as
huhimagestorage/Herbaria1 and huhimagestorage/Herbaria2)

(3) Consistent roots for the mounts themselves (//128.103.155.56/Herbaria1/ and
//128.103.155.56/Herbaria2/).

*/

include_once('connection_library.php');

$base = "/mnt/";
$connection = specify_connect();

class IMAGE_LOCAL_FILE { 
  public $filename;
  public $path;
  public $mimetype;
}

$convert = "";
$id = 47086;
$id = preg_replace("/[^0-9]/","",$_GET['id']);
$convert = preg_replace("/[^a-z]/","",$_GET['convert']);

$imagefile = lookup_image($id);
if ($imagefile->mimetype=="image/tiff" && $convert=="jpeg") { 
   convert_file($imagefile,"jpeg");
} else { 
   fetch_file($imagefile);
}

/**
 * Lookup the filename, path, and mimetype of a file from 
 * the IMAGE_LOCAL_FILE table.
 */
function lookup_image($image_local_file_id) { 
   global $connection;
   $result = new IMAGE_LOCAL_FILE();
   $sql = "select filename, path, mimetype from IMAGE_LOCAL_FILE where id = ? ";
   $stmt = $connection->stmt_init();
   $stmt->prepare($sql);
   $stmt->bind_param('i',$image_local_file_id); 
   $stmt->execute();
   $stmt->bind_result($filename, $path, $mimetype);
   if ($stmt->fetch()) {
      $result->filename = $filename; 
      $result->path = $path; 
      $result->mimetype = $mimetype; 
   } 
   $stmt->close();
   return $result;
}

/** 
 * Fetch the image file converting it to a jpeg, then
 * deliver the jpeg with appropriate headers.
 */
function convert_file($imagefile,$toType="jpeg") { 
   global $base;
   $im = new imagick($base.$imagefile->path.$imagefile->filename);
   $im->setImageFormat('jpeg');
   $name = preg_replace("/\.[a-zA-Z]*$/",".jpg",$imagefile->filename);
   sendheader("image/jpeg",$name);
   echo $im;
   $im->clear();
   $im->destroy();
}

/**
 * Directly fetch the image file from the filesystem and pass 
 * through with headers and without modification.
 */
function fetch_file($imagefile) { 
   global $base;
   sendheader($imagefile->mimetype,$imagefile->filename);
   readfile($base.$imagefile->path.$imagefile->filename);
}

/**
 * Send http headers for this file, providing content type 
 * and a suggested filename if download is desired.  
 */
function sendheader($mimetype,$filename) { 
        header("Content-Type: $mimetype");
        header("Content-Disposition: INLINE; Filename=$filename");
        header("Cache-Control: no-cache, must-revalidate");
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
}

?>