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
  public $success=FALSE;
  public $errormessage;
}

$convert = "";
$id = 47086;
$id = preg_replace("/[^0-9]/","",$_GET['id']);
$convert = preg_replace("/[^a-z]/","",$_GET['convert']);

if (strlen($id==0)) {
   return_error_image("No image file id provided add parameter ?id= ");
} else {
   $imagefile = lookup_image($id);
   if ($imagefile->success===TRUE) {
      if ($imagefile->mimetype=="image/tiff" || ($convert=="jpeg" || $convert=="jpg")) {
         echo "The Harvard University Herbaria are working on improving specimen image availability for our web-based database and actively continue to digitize and present JPEG files. We are converting previously available high resolution TIFF files into more accessible JPEG files better suited for web presentation, and they will become available as they are processed. We apologize for any inconvenience. If you require a file sooner, or require a high resolution TIFF file, please contact us at huh-requests@oeb.harvard.edu. Thank you.";
         // convert_file($imagefile,"jpeg");
      } else {
         redirectS3($imagefile);
         //fetch_file($imagefile);
      }
   } else {
      return_error_image($imagefile->errormessage);
   }
}

/**
 * Lookup the filename, path, and mimetype of a file from
 * the IMAGE_LOCAL_FILE table.
 */
function lookup_image($image_local_file_id) {
   global $connection;
   $result = new IMAGE_LOCAL_FILE();
   $result->success=FALSE;
   $sql = "select filename, path, mimetype from IMAGE_LOCAL_FILE where id = ? ";
   $stmt = $connection->stmt_init();
   if ($stmt->prepare($sql)) {
      $stmt->bind_param('i',$image_local_file_id);
      $stmt->execute();
      $stmt->store_result();
      if ($stmt->num_rows>0) {
         $stmt->bind_result($filename, $path, $mimetype);
         if ($stmt->fetch()) {
            $result->filename = $filename;
            $result->path = $path;
            $result->mimetype = $mimetype;
            $result->success = TRUE;
         } else {
            $result->success=FALSE;
            $result->errormessage = $connection->error . " " . $stmt->error;
         }
      } else {
         $result->success=FALSE;
         $result->errormessage = "Image File ID [$image_local_file_id] not found.";
      }
   } else {
      $result->success=FALSE;
      $result->errormessage = $connection->error . " " . $stmt->error;
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
   $file = $base.$imagefile->path.$imagefile->filename;
   if (is_readable($file)) {
      $im = new imagick();
      try {
         $im->readImage($file);
         $im->setImageFormat('jpeg');
      } catch(ImagickException $e) {
         return_error_image("Unable to convert: " .$imagefile->filename . " $e");
      }
      $name = preg_replace("/\.[a-zA-Z]*$/",".jpg",$imagefile->filename);
      sendheader("image/jpeg",$name);
      echo $im;
      $im->clear();
      $im->destroy();
   } else {
      return_error_image("Unable to read file to convert: " .$imagefile->filename);
   }
}

/**
 * Redirect the request to AWS S3
 */
function redirectS3($imagefile) {
    
    $s3file = preg_replace("/huhimagestorage\/Herbaria/", "herbaria", (string)$imagefile->path) . $imagefile->filename;

	$newURL = "https://s3.amazonaws.com/" . $s3file;

	header('Location: '.$newURL);

}


/**
 * Directly fetch the image file from the filesystem and pass
 * through with headers and without modification.
 */
function fetch_file($imagefile) {
   global $base;
   $file = $base.$imagefile->path.$imagefile->filename;
   if (is_readable($file)) {
      sendheader($imagefile->mimetype,$imagefile->filename);
      readfile($file);
   } else {
      return_error_image("Unable to read file: " .$imagefile->filename);
   }
}

function return_error_image($message) {
   echo($message);
}
/*
   sendheader("image/png","errormessage.png");
   $draw = new ImagickDraw();
   $draw->setFillColor('black');
   $draw->setFontSize(16);
   $draw->annotation(10, 30, $message);
   $im = new Imagick();
   $im->newImage(640, 100, "white");
   $im->drawImage($draw);
   $im->borderImage('blue', 1, 1);
   $im->setImageFormat('png');
   echo $im;
}
*/

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

function writeToLog($imageFile) {
  global $connection;

  $server = preg_replace("/[^A-Za-z0-9\.]/","",getenv('SERVER_NAME'));
  $sql = "insert into log (server, file, resource, resourcetype, recordcount, logtime) values ($server,'image.php',?,?,1,now()) ";
  $stmt = $connection->stmt_init();
  if ($stmt->prepare($sql)) {
      $mimetype = $imageFile->mimetype;
      $filename = $imageFile->filename;
      $stmt->bind_param("sss",$server,$filename, $mimetype);
      $stmt->execute();
      $stmt->close();
  }
}

?>
