<?php
/*
 * Created on Dec 3, 2009
 *
 * Copyright © 2010 President and Fellows of Harvard College
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
 * @Author: Paul J. Morris  bdim@oeb.harvard.edu
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
$errormessage = "";

$mode = "search";

if ($_GET['mode']!="")  {
	if ($_GET['mode']=="details") {
		$mode = "details";
	}
	if ($_GET['mode']=="stats") {
		$mode = "stats";
	}
	if ($_GET['mode']=="exsiccatae") {
		$mode = "exsiccatae";
	}
}

if (preg_replace("/[^0-9]/","",$_GET['barcode'])!="") {
	$mode = "details";
}

echo pageheader('publication');

if ($connection) {

	switch ($mode) {

		case "browse_families":
			browse("families");
			break;

		case "browse_countries":
			browse("countries");
			break;

		case "details":
			details();
			break;

		case "stats":
			echo stats();
			break;

		case "search":
			search();
			break;

                case "exsiccatae":
                        browse_exsiccatae();
                        break;

		default:
			$errormessage = "Undefined search mode";
	}

	$connection->close();

} else {
	$errormessage .= "Unable to connect to database. ";
}

if ($errormessage!="") {
	echo "<strong>Error: $errormessage</strong>";
}

echo pagefooter();

// ******* main code block ends here, supporting functions follow. *****

function details() {
	global $connection, $errormessage, $debug;
	$id = $_GET['id'];
	if (is_array($id)) {
		$ids = $id;
	} else {
		$ids[0] = $id;
	}
	$guid = preg_replace("/[^0-9a-zA-Z\-]/","",$_GET['guid']);
	if ($guid != "") {
		$sql = "select referenceworkid from referencework where guid = ? ";
		$statement = $connection->prepare($sql);
		if ($statement) {
			$statement->bind_param("s",$guid);
			$statement->execute();
			$statement->bind_result($id);
			$statement->store_result();
			while ($statement->fetch()) {
				$ids[] = $id;
			}
		}
	}
	$oldid = "";
	foreach($ids as $value) {
		$id = substr(preg_replace("/[^0-9]/","",$value),0,20);
		// Might be duplicates next to each other in list of checkbox records from search results
		// (from there being more than one current determination/typification for a specimen, or
		// from there being two specimens on one sheet).
		if ($oldid!=$id)  {
			if ($debug==true) { echo "[$id]"; }
			$wherebit = " referencework.referenceworkid = ? ";
			$query = "select text2 as year, text1 as worktitle, title as abbreviation, placeofpublication, publisher, referenceworktype, volume, pages, url, remarks, ContainedRFParentID, precedingworkid, succeedingworkid from referencework where $wherebit";
			if ($debug) { echo "[$query]<BR>"; }
			$statement = $connection->prepare($query);
			if ($statement) {
				$statement->bind_param("i",$id);
				$statement->execute();
				$statement->bind_result($year, $title, $abbreviation, $placeofpublication, $publisher, $referenceworktype, $volume, $pages, $url, $remarks, $ContainedRFParentID, $precededby, $succededby);
				$statement->store_result();
				echo "<table>";
				$authors = "";
				while ($statement->fetch()) {
					// referenceworktype = 0 normal reference works.
					// referenceworktype = 5 an import generated citation in a work.
					// referenceworktype = 6 Excicatii.
					if ( $referenceworktype=="0") { $referenceworktype = ""; }
					if ( $referenceworktype=="6")  { $referenceworktype = "Exsiccata"; }
					$query = " select agentvariant.name, agentvariant.agentid from referencework r left join author a on r.referenceworkid = a.referenceworkid left join agentvariant on a.agentid = agentvariant.agentid where r.referenceworkid = ? and vartype = 4 ";
					if ($debug) { echo "[$query]<BR>"; }
					$statement_hg = $connection->prepare($query);
					if ($statement_hg) {
						$statement_hg->bind_param("i",$id);
						$statement_hg->execute();
						$statement_hg->bind_result($authorname,$agentid);
						$statement_hg->store_result();
						$separator = "";
						while ($statement_hg->fetch()) {
							$authors .= "$separator<a href=botanist_search.php?mode=details&botanistid=$agentid>$authorname</a>";
							$separator = ": ";
						}
					}

					$identifiers = "";
					$query = "select identifier,type from referenceworkidentifier where referenceworkid = ? ";
					if ($debug===true) {  echo "[$query]<BR>"; }
					$statement_geo = $connection->prepare($query);
					if ($statement_geo) {
						$statement_geo->bind_param("i",$id);
						$statement_geo->execute();
						$statement_geo->bind_result($identifier, $identifiertype);
						$statement_geo->store_result();
						while ($statement_geo->fetch()) {
                                                        if ($identifiertype=='HOLLIS') {
							    $identifiers .= "<tr><td class='cap'>HOLLIS</td><td class='val'><a href='http://hollis.harvard.edu/?itemid=|library/m/aleph|$identifier'>$identifier</a></td></tr>";
                                                        } else {
							    $identifiers .= "<tr><td class='cap'>$identifiertype</td><td class='val'>$identifier</td></tr>";
                                                        }
						}
					} else {
						echo "Error: " . $connection->error;
					}

                                        $specimens = "";
					if ( $referenceworktype=="Exsiccata")  {
					$query = " select f.collectionobjectid, fc.text1, fc.text2, f.text1, f.identifier, t.fullname, t.author from referencework r left join fragmentcitation fc on r.referenceworkid = fc.referenceworkid left join fragment f on fc.fragmentid = f.fragmentid left join determination d on f.fragmentid = d.fragmentid left join taxon t on d.taxonid = t.taxonid where r.referenceworkid = ? and (d.iscurrent=true or d.iscurrent is null) ";
					if ($debug) { echo "[$query]<BR>"; }
					$statement_fc = $connection->prepare($query);
					if ($statement_fc) {
						$statement_fc->bind_param("i",$id);
						$statement_fc->execute();
						$statement_fc->bind_result($collobjid, $fascicle,$fnumber,$herbarium, $barcode,$taxon,$authorship);
						$statement_fc->store_result();
						$separator = "";
						while ($statement_fc->fetch()) {
                                                    if ($collobjid!=null) {
                                                        if ($fascicle != "") { $fascicle = "Fasicle $fascicle"; }
                                                        if ($fnumber!= "") { $fnumber = "Number $fnumber"; }
							$specimens .= "$separator$fascicle $fnumber <em>$taxon</em> $authorship <a href=specimen_search.php?mode=details&id=$collobjid>$herbarium $barcode</a>";
							$separator = "<BR>";
                                                    }
						}
					}

                                        }
                                        if ($precededby!=null) {
					   $query = "select title from referencework where referenceworkid = ? ";
  					   if ($debug===true) {  echo "[$query]<BR>"; }
					   $statement_pre = $connection->prepare($query);
					   if ($statement_pre) {
						$statement_pre->bind_param("i",$precededby);
						$statement_pre->execute();
						$statement_pre->bind_result($precededbytitle);
						$statement_pre->store_result();
						$statement_pre->fetch();
					   } else {
						echo "Error: " . $connection->error;
					   }
                                           $statement_pre->close();
                                        }
                                        if ($succededby!=null) {
					   $query = "select title from referencework where referenceworkid = ? ";
  					   if ($debug===true) {  echo "[$query]<BR>"; }
					   $statement_pre = $connection->prepare($query);
					   if ($statement_pre) {
						$statement_pre->bind_param("i",$succededby);
						$statement_pre->execute();
						$statement_pre->bind_result($succededbytitle);
						$statement_pre->store_result();
						$statement_pre->fetch();
					   } else {
						echo "Error: " . $connection->error;
					   }
                                           $statement_pre->close();
                                        }

					// Variant titles
                                        $variant = "";
					$query = "select name from referenceworkvariant where referenceworkid = ? ";
  					if ($debug===true) {  echo "[$query]<BR>"; }
					$statement_pre = $connection->prepare($query);
					if ($statement_pre) {
					   $statement_pre->bind_param("i",$id);
					   $statement_pre->execute();
					   $statement_pre->bind_result($varname);
					   $statement_pre->store_result();
					   while ($statement_pre->fetch()) {
					        $variant .= "<tr><td class='cap'>Variant title:</td><td class='val'>$varname</td></tr>";
                                           }
					} else {
					   echo "Error: " . $connection->error;
					}
                                        $statement_pre->close();

					echo "<tr><td class='cap'>Title</td><td class='val'>$title</td></tr>";
					if (trim($abbreviation!=""))   { echo "<tr><td class='cap'>Abbreviation</td><td class='val'>$abbreviation</td></tr>"; }
					if (trim($variant!=""))   { echo "$variant"; }
					if (trim($authors!=""))   { echo "<tr><td class='cap'>Authors</td><td class='val'>$authors</td></tr>"; }
					if (trim($year!=""))   { echo "<tr><td class='cap'>Publication Dates</td><td class='val'>$year</td></tr>"; }
					if (trim($placeofpublication!=""))   { echo "<tr><td class='cap'>Place of publication</td><td class='val'>$placeofpublication</td></tr>"; }
                                        $upublisher = urlencode($publisher);
					if (trim($publisher!=""))   { echo "<tr><td class='cap'>Publisher</td><td class='val'><a href='publication_search.php?publisher=$upublisher'>$publisher</a></td></tr>"; }
					if (trim($referenceworktype!=""))   { echo "<tr><td class='cap'>ReferenceWorkType</td><td class='val'>$referenceworktype</td></tr>"; }
					if (trim($volume!=""))   { echo "<tr><td class='cap'>Volume</td><td class='val'>$volume</td></tr>"; }
					if (trim($pages!=""))   { echo "<tr><td class='cap'>Pages</td><td class='val'>$pages</td></tr>"; }
					if (trim($url!=""))   { echo "<tr><td class='cap'>URL</td><td class='val'><a href='$url'>$url</a></td></tr>"; }
					if (trim($identifiers!=""))   { echo "$identifiers"; }
					if (trim($precededby!=""))   { echo "<tr><td class='cap'>Preceded By</td><td class='val'><a href='publication_search.php?mode=details&id=$precededby'>$precededbytitle</a></td></tr>"; }
					if (trim($succededby!=""))   { echo "<tr><td class='cap'>Succeded By</td><td class='val'><a href='publication_search.php?mode=details&id=$succededby'>$succededbytitle</a></td></tr>"; }
					if (trim($ContainedRFParentID!=""))   { echo "<tr><td class='cap'>ContainedRFParentID</td><td class='val'>$ContainedRFParentID</td></tr>"; }
					if (trim($remarks!="")) { echo "<tr><td class='cap'>Remarks</td><td class='val'>$remarks</td></tr>"; }
					if (trim($ContainedRFParentID!=""))   { echo "<tr><td class='cap'>ContainedRFParentID</td><td class='val'>$ContainedRFParentID</td></tr>"; }
					if (trim($specimens!=""))   { echo "<tr><td class='cap'>Specimens</td><td class='val'>$specimens</td></tr>"; }
					echo "<BR>\n";
				}
				echo "</table>";
			    $query = "select taxon.fullname, taxon.author, d.typestatusname, d.fragmentid, tc.remarks, tc.text1, tc.text2 " .
			    		"from determination d left join taxon on d.taxonid = taxon.taxonid " .
			    		"left join taxoncitation tc on taxon.taxonid = tc.taxonid " .
			    		"where typestatusname is not null and tc.referenceworkid = ? ";
				if ($debug) { echo "[$query]<BR>"; }
				$statement_ts = $connection->prepare($query);
				if ($statement_ts) {
					$statement_ts->bind_param("i",$id);
					$statement_ts->execute();
					$statement_ts->bind_result($fullname, $author, $typestatusname, $fragmentid, $remarks, $pages, $year);
					$statement_ts->store_result();
					$separator = "";
					$count = $statement_ts->num_rows;
			        if ($count > 0 ) {
			        	echo "<h3>The Harvard University Herbaria hold $count type specimens of taxa described in this publication</h3>";
					    while ($statement_ts->fetch()) {
					    	if ($remarks !="") { $remarks = " ($remarks)"; }
					    	echo "$fullname $author [<a href='specimen_search.php?fragmentid=$fragmentid'>$typestatusname</a>$remarks] $year, $pages<BR>";
					    }
			        }
				}
			    $statement_ts->close();
				$query  = "select taxon.fullname, taxon.author, d.typestatusname, d.fragmentid, dc.remarks, dc.text1, dc.text2 " .
						"from determination d left join taxon on d.taxonid = taxon.taxonid " .
						"left join determinationcitation dc on d.determinationid = dc.determinationid " .
						"where typestatusname is not null and dc.referenceworkid = ? ";
				if ($debug) { echo "[$query]<BR>"; }
				$statement_cit = $connection->prepare($query);
				if ($statement_cit) {
					$statement_cit->bind_param("i",$id);
					$statement_cit->execute();
					$statement_cit->bind_result($fullname, $author, $typestatusname, $fragmentid, $remarks, $pages, $year);
					$statement_cit->store_result();
					$separator = "";
					$count = $statement_cit->num_rows;
			        if ($count > 0 ) {
			        	echo "<h3>The Harvard University Herbaria hold $count type specimens of taxa cited in this publication</h3>";
					    while ($statement_cit->fetch()) {
					    	if ($remarks !="") { $remarks = " ($remarks)"; }
					    	echo "$fullname $author [$typestatusname$remarks] $year, $pages<BR>";
					    }
			        }
				}
			    $statement_cit->close();
			}
			$statement->close();
		}
                echo "<hr />";
		$oldid = $id;
	}
}


function search() {
	global $connection, $errormessage, $debug;
	$question = "";
	$joins = "";
	$wherebit = " where ";
        $relaxedwherebit = " where " ;
	$and = "";
	$types = "";
	$joins = "";
	$order = "";
	$parametercount = 0;
        $relaxedparametercount = 0;
	$hasauthor = false;
	$publisher = substr(preg_replace("/[^A-Za-zÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ\:\.\, _0-9%]/","", $_GET['publisher']),0,59);
	if ($publisher!="") {
		$hasquery = true;
		$question .= "$and publisher:[$publisher] ";
		$types .= "s";
		$operator = "=";
		$parameters[$parametercount] = &$publisher;
		$parametercount++;
		if (preg_match("/[%_]/",$publisher))  { $operator = " like "; }
		$wherebit .= "$and r.publisher $operator ?  ";
		$order = " order by r.text1 ";
		$relaxedwherebit .= "$and r.publisher like ?  ";
                $relaxedpublisher = "%$publisher%";
		$relaxedparameters[$relaxedparametercount] = &$relaxedpublisher;
		$relaxedparametercount++;
		$and = " and ";
	}
	$place = substr(preg_replace("/[^A-Za-zÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ\:\.\, _0-9%]/","", $_GET['place']),0,59);
	if ($place!="") {
		$hasquery = true;
		$question .= "$and place of publication:[$place] ";
		$types .= "s";
		$operator = "=";
		$parameters[$parametercount] = &$place;
		$parametercount++;
		if (preg_match("/[%_]/",$place))  { $operator = " like "; }
		$wherebit .= "$and r.placeofpublication $operator ?  ";
		$order = " order by r.text1 ";
		$relaxedwherebit .= "$and r.placeofpublication like ?  ";
                $relaxedplace = "%$place%";
		$relaxedparameters[$relaxedparametercount] = &$relaxedplace;
		$relaxedparametercount++;
		$and = " and ";
	}
	$title = substr(preg_replace("/[^A-Za-zÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ\:\.\, _0-9%]/","", $_GET['title']),0,59);
	if ($title!="") {
		$hasquery = true;
		$question .= "$and title:[$title] ";
		$types .= "sss";
		$operator = "=";
		$parameters[$parametercount] = &$title;
		$parametercount++;
		$parameters[$parametercount] = &$title;
		$parametercount++;
		$parameters[$parametercount] = &$title;
		$parametercount++;
		if (preg_match("/[%_]/",$title))  { $operator = " like "; }
		$wherebit .= "$and ( r.text1 $operator ? or r.title $operator ? or rwv.name $operator ? ) ";
                $joins .= " left join referenceworkvariant rwv on r.referenceworkid = rwv.referenceworkid ";
		$order = " order by r.text1 ";
		$relaxedwherebit .= "$and ( r.text1 like ? or r.title like ? or rwv.name like ? ) ";
                $relaxedtitle = "%$title%";
		$relaxedparameters[$relaxedparametercount] = &$relaxedtitle;
		$relaxedparametercount++;
		$relaxedparameters[$relaxedparametercount] = &$relaxedtitle;
		$relaxedparametercount++;
		$relaxedparameters[$relaxedparametercount] = &$relaxedtitle;
		$relaxedparametercount++;
		$and = " and ";
	}
	$author = substr(preg_replace("/[^A-Za-zÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïñòóôõöøùúûüýÿĀāĂăĄąĆćĈĉĊċČčĎďĐđĒēĔĕĖėĘęĚěĜĝĞğĠġĢģĤĥĦħĨĩĪīĬĭĮįİıĲĳĴĵĶķĹĺĻļĽľĿŀŁłŃńŅņŇňŉŌōŎŏŐőŒœŔŕŖŗŘřŚśŜŝŞşŠšŢţŤťŦŧŨũŪūŬŭŮůŰűŲųŴŵŶŷŸŹźŻżŽžſƒƠơƯưǍǎǏǐǑǒǓǔǕǖǗǘǙǚǛǜǺǻǼǽǾǿ_0-9\. %]/","", $_GET['author']),0,59);
	if ($author!="") {
		$hasauthor = true;
		$hasquery = true;
		$question .= "$and author:[$author] ";
		$types .= "sss";
		$operator = "=";
		$parameters[$parametercount] = &$author;
		$parametercount++;
		$parameters[$parametercount] = &$author;
		$parametercount++;
		$parameters[$parametercount] = &$author;
		$parametercount++;
		if (preg_match("/[%_]/",$author))  { $operator = " like "; }
		$wherebit .= "$and (agent.lastname $operator ? or agentvariant.name $operator ? or agent.lastname $operator ?)";
		$joins .= " left join author a on r.referenceworkid = a.referenceworkid left join agentvariant on a.agentid = agentvariant.agentid left join agent on a.agentid = agent.agentid ";
		$order = " order by agent.lastname, agent.firstname, r.text1 ";
		$relaxedwherebit .= "$and (agent.lastname like ? or agentvariant.name like ? or agent.lastname like ?)";
                $realaxedauthor = "%$author%";
		$relaxedparameters[$relaxedparametercount] = &$relaxedauthor;
		$relaxedparametercount++;
		$relaxedparameters[$relaxedparametercount] = &$relaxedauthor;
		$relaxedparametercount++;
		$relaxedparameters[$relaxedparametercount] = &$relaxedauthor;
		$relaxedparametercount++;
		$and = " and ";
	} else {
		// author or agentid search is exclusive.
	$agentid = substr(preg_replace("/[^A-Za-z_0-9\. %]/","", $_GET['agentid']),0,59);
	if ($agentid!="") {
		$hasauthor = true;
		$hasquery = true;
		$question .= "$and author agentid:[$agentid] ";
		$types .= "i";
		$parameters[$parametercount] = &$agentid;
		$parametercount++;
		$wherebit .= "$and a.agentid = ? ";
		$joins .= " left join author a on r.referenceworkid = a.referenceworkid left join agent on a.agentid = agent.agentid ";
		$order = " order by agent.lastname, agent.firstname, r.text1 ";
		$relaxedwherebit .= "$and a.agentid = ? ";
		$relaxedparameters[$relaxedparametercount] = &$agentid;
		$relaxedparametercount++;
		$and = " and ";
	}
	}
	$identifier = substr(preg_replace("/[^A-Za-z\/\\0-9\.%]/","", $_GET['identifier']),0,59);
	if ($identifier!="") {
		$ttype = substr(preg_replace("/[^A-Z0-9\.%]/","", $_GET['type']),0,59);
		$hasquery = true;
		$question .= "$and ( identifier:[$identifier] and type = [$ttype] )";
		$types .= "ss";
		$operator = "=";
		$parameters[$parametercount] = &$identifier;
		$parametercount++;
		$parameters[$parametercount] = &$ttype;
		$parametercount++;
		if (preg_match("/[%_]/",$identifier))  { $operator = " like "; }
		$wherebit .= "$and ( ri.identifier $operator ? and ri.type = ? ) ";
		$joins .= " left join referenceworkidentifier ri on r.referenceworkid = ri.referenceworkid  ";
		$relaxedwherebit .= "$and ( ri.identifier like ? and ri.type = ? ) ";
                $relaxedidentifier = "%$identifier%";
		$relaxedparameters[$relaxedparametercount] = &$relaxedidentifier;
		$relaxedparametercount++;
		$relaxedparameters[$relaxedparametercount] = &$ttype;
		$relaxedparametercount++;
		$and = " and ";
	}
	if ($question!="") {
		$question = "Search for $question <BR>";
	} else {
		$question = "No search criteria provided.";
	}
	if ($hasauthor) { $third = ", agent.lastname, agent.firstname "; } else { $third = ""; }
	// referenceworktype = 5 an import generated citation in a work.
	$query = "select distinct r.referenceworkid, r.text1 as title $third
		from referencework r
		$joins $wherebit $and r.referenceworktype <> 5 $order ";
	if ($debug===true  && $hasquery===true) {
		echo "[$query]<BR>\n";
	}
	$relaxedquery = "select distinct r.referenceworkid, r.text1 as title $third
		from referencework r
		$joins $relaxedwherebit $and r.referenceworktype <> 5 $order ";
	if ($hasquery===true) {
		$statement = $connection->prepare($query);
		if ($statement) {
			$array = Array();
			$array[] = $types;
			foreach($parameters as $par) {
			    $array[] = $par;
			}
			//call_user_func_array(array($statement, 'bind_param'),$array);
            if (substr(phpversion(),0,4)=="5.3." || substr(phpversion(),0,4)=="5.5.") {
               // work around for bug in __call, or is it?
               // http://bugs.php.net/bug.php?id=50394
               // http://stackoverflow.com/questions/2045875/pass-by-reference-problem-with-php-5-3-1
               call_user_func_array(array($statement, 'bind_param'),make_values_referenced($array));
            } else {
               call_user_func_array(array($statement, 'bind_param'),$array);
            }

			$statement->execute();
			if ($hasauthor) {
				$statement->bind_result($referenceid,$title,$authorlast, $authorfirst);
			} else {
				$statement->bind_result($referenceid,$title);
			}
			$statement->store_result();
                        $resultcount = $statement->num_rows;
			echo "<div>\n";
			echo $resultcount . " matches to query ";
			echo "    <span class='query'>$question</span>\n";
			echo "</div>\n";
			echo "<HR>\n";

                        if ($resultcount < 1 ) {
                           $statement->close();
		           $statement = $connection->prepare($relaxedquery);
                           if ($debug) { echo "[$relaxedquery]"; }
			   $rarray = Array();
  			   $rarray[] = $types;
			   foreach($relaxedparameters as $par) {
			      $rarray[] = $par;
			   }
                           if (substr(phpversion(),0,4)=="5.3." || substr(phpversion(),0,4)=="5.5.") {
                               call_user_func_array(array($statement, 'bind_param'),make_values_referenced($rarray));
                           } else {
                               call_user_func_array(array($statement, 'bind_param'),$rarray);
                           }
		   	   $statement->execute();
			   if ($hasauthor) {
				$statement->bind_result($referenceid,$title,$authorlast, $authorfirst);
			   } else {
				$statement->bind_result($referenceid,$title);
			   }
			   $statement->store_result();
			   echo "<div>\n";
			   echo $statement->num_rows . " matches to wildcard query ";
			   echo "    <span class='query'>$question</span>\n";
			   echo "</div>\n";
			   echo "<HR>\n";

                        }

			if ($statement->num_rows > 0 ) {
				echo "<form  action='publication_search.php' method='get'>\n";
				echo "<input type='hidden' name='mode' value='details'>\n";
				echo "<input type='image' src='images/display_recs.gif' name='display' alt='Display selected records' />\n";
				echo "<BR><div>\n";
				$oldauthor = "";
				while ($statement->fetch()) {
					$currentauthor = $authorlast.$authorfirst;
					if ($hasauthor && $oldauthor!=$currentauthor) {
						echo "<strong>$authorlast, $authorfirst</strong><BR>";
						$oldauthor = $currentauthor;
					}
					echo "<input type='checkbox' name='id[]' value='$referenceid'> <a href='publication_search.php?mode=details&id=$referenceid'>$title</a> ";
					echo "<BR>\n";
				}
				echo "</div>\n";
				echo "<input type='image' src='images/display_recs.gif' name='display' alt='Display selected records' />\n";
				echo "</form>\n";

			} else {
				$errormessage .= "No matching results. ";
			}
			if ($hasauthor && $author != "") {
				$statement->close();
				// Look for possibly related authors
				$query = " select agentvariant.name, agent.agentid, count(author.referenceworkid) " .
						" from referencework " .
						" left join author on referencework.referenceworkid = author.referenceworkid " .
						" left join agent on author.agentid = agent.agentid " .
						" left join agentvariant on agent.agentid = agentvariant.agentid " .
						" where referenceworktype<>5 " .
						" and (agentvariant.name like ? " .
						"     or soundex(agentvariant.name) = soundex(?) " .
						"     ) " .
						" group by agent.firstname, agent.lastname, agent.agentid ";
				$wildauthor = "%$author%";
				$plainauthor = str_replace("%","",$author);
       		    $authorparameters[0] = &$wildauthor;   // agentvariant like
 		        $types = "s";
       		    $authorparameters[1] = &$plainauthor;  // agentvariant soundex
 		        $types .= "s";
       		    //$authorparameters[2] = &$wildauthor;   // agent like
 		        //$types .= "s";
       		    //$authorparameters[3] = &$plainauthor;  // agent soundex
 		        //$types .= "s";
             	if ($debug===true  && $hasquery===true) {
		              echo "[$query][$wildauthor][$plainauthor][$wildauthor][$plainauthor]<BR>\n";
	            }
				$statement = $connection->prepare($query);
				if ($statement) {
					$array = Array();
					$array[] = $types;
					foreach($authorparameters as $par)
					      $array[] = $par;
					call_user_func_array(array($statement, 'bind_param'),$array);
					$statement->execute();
					$statement->bind_result($authorname, $agentid, $count);
					$statement->store_result();
			        if ($statement->num_rows > 0 ) {
			        	echo "<h3>Possibly matching authors</h3>";
			        	$separator = "";
				         while ($statement->fetch()) {
			        	    $highlightedauthor = preg_replace("/$plainauthor/","<strong>$plainauthor</strong>","$authorname");
				         	echo "$highlightedauthor [<a href='publication_search.php?mode=search&agentid=$agentid'>$count pubs</a>]<BR>";
				         }
				         echo "<BR>";
			        }
				}
			}
			$statement->close();
		} else {
			echo $connection->error;
		}
	} else {
		echo "<div>\n";
		echo "No query parameters provided.";
		echo "</div>\n";
		echo "<HR>\n";
	}

}

function browse_exsiccatae() {
   global $connection, $errormessage, $debug;

   echo "<h3>Exsiccatae known to the Harvard University Herbaria</h3>";
   $sql = "select r.text1, r.title, r.referenceworkid, count(fc.fragmentid) from referencework r left join fragmentcitation fc on r.referenceworkid = fc.referenceworkid where referenceworktype = 6 group by r.text1, r.title, r.referenceworkid";
   if ($debug) { echo "[$sql]<BR>"; }
   $statement = $connection->prepare($sql);
   if ($statement) {
       $statement->execute();
       $statement->bind_result($full,$title,$id,$count);
       $statement->store_result();
       while ($statement->fetch()) {
           if ($title=="") { $title=$full; }
           if ($count>0) { $count = "($count)"; } else { $count = ""; }
           echo "<a href='/databases/publication_search.php?mode=details&id=$id'>$title</a> $count<br>";
       }
   } else {
       $errormessage = $connection->error;
   }
   $statement->close();
}

mysqli_report(MYSQLI_REPORT_OFF);

?>
