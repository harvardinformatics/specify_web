<?php
/*
 * Created on Feb 16, 2019
 *
 * Copyright 2019 The President and Fellows of Harvard College
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 */
$debug=false;

include_once('connection_library.php');
include_once('specify_library.php');

if ($debug) {
	mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT);
} else {
	mysqli_report(MYSQLI_REPORT_OFF);
}

$connection = specify_connect();

// extract parameters from query string, uuid=x and mode=x
$uuid = $_GET['uuid'];
$barcode = $_GET['barcode'];
$mode = $_GET['mode']; // ok if null
$collectionobjectid = 0;

// check uuid format
if (preg_match("^\w{8}-\w{4}-\w{4}-\w{4}-\w{12}$", $uuid)) {
	$query = "select f.collectionobjectid from guids g, fragment f where f.fragmentid = g.primarykey and g.tablename='fragment' and g.uuid='".$uuid."'";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($collectionobjectid);
		$statement->store_result();
		$statement->fetch();
		$statement->close();
	} else {
		printError("System error");
	}
}

if (preg_match("^\d{1,8}$", $barcode)) {
	$barcode = str_pad($barcode,8,'0',STR_PAD_LEFT);
	$query = "select collectionobjectid from fragment f, preparation p where f.preparationid = p.preparationid and (f.identifier like '$barcode' or p.identifier like '$barcode')";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($collectionobjectid);
		$statement->store_result();
		$statement->fetch();
		$statement->close();
	} else {
		printError("System error");
	}
}


if ($collectionobjectid == 0) {
	send404();
}

// check acceptable mode (image, page, rdf)
switch ($mode) {

	case "image":
		redirectImage($collectionobjectid);
	case "preview":
		redirectPreview($collectionobjectid);
	case "thumb":
		redirectThumbnail($collectionobjectid);
	case "rdf":
		redirectRDF($uuid);
	default:
		redirectPage($collectionobjectid);
}

die();

function redirectImage($coid) {
	global $connection;
	$query="
select concat(r.url_prefix, io.uri) url
from IMAGE_SET_collectionobject isc,
     IMAGE_OBJECT io,
     REPOSITORY r
where
	isc.imagesetid = io.image_set_id and
	io.repository_id = r.id and
	io.object_type_id = 4 and
	io.active_flag = 1 and
	isc.collectionobjectid =".$coid;

	$url = "empty";

	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($url);
		$statement->store_result();
		$statement->fetch();
		$statement->close();
	} else {
		printError("System error");
	}

	if (strcmp($url,"empty") == 0) {
		send404();
		die();
	}

	header("Location: ".$url);
	die();

}

function redirectPreview($coid) {
	global $connection;
	$query="
select concat(r.url_prefix, io.uri) url
from IMAGE_SET_collectionobject isc,
     IMAGE_OBJECT io,
     REPOSITORY r
where
	isc.imagesetid = io.image_set_id and
	io.repository_id = r.id and
	io.object_type_id = 3 and
	io.active_flag = 1 and
	isc.collectionobjectid =".$coid;

	$url = "empty";

	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($url);
		$statement->store_result();
		$statement->fetch();
		$statement->close();
	} else {
		printError("System error");
	}

	if (strcmp($url,"empty") == 0) {
		send404();
		die();
	}

	header("Location: ".$url);
	die();

}

function redirectThumbnail($coid) {
	global $connection;
	$query="
select concat(r.url_prefix, io.uri) url
from IMAGE_SET_collectionobject isc,
     IMAGE_OBJECT io,
     REPOSITORY r
where
	isc.imagesetid = io.image_set_id and
	io.repository_id = r.id and
	io.object_type_id = 2 and
	io.active_flag = 1 and
	isc.collectionobjectid =".$coid;

	$url = "empty";

	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($url);
		$statement->store_result();
		$statement->fetch();
		$statement->close();
	} else {
		printError("System error");
	}

	if (strcmp($url,"empty") == 0) {
		send404();
		die();
	}

	header("Location: ".$url);
	die();

}

function redirectRDF($u) {
	$url = "http://data.huh.harvard.edu/databases/rdfgen.php?uuid=".$u;
	header("Location: ".$url);
	die();
}

function redirectPage($coid) {
	$url = "http://data.huh.harvard.edu/databases/specimen_search.php?mode=details&id=".$coid;
	header("Location: ".$url);
	die();
}

function printError($text) {
	echo "<strong>Error: $text</strong>";
	die();
}
function send404() {

	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
    include("404.html");
    die();
}

mysqli_report(MYSQLI_REPORT_OFF);

?>
