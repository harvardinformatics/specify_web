<?php
/*
 * Created on 2010 May 13
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
// ******* this file contains only supporting functions. *****


function barcode_to_catalog_number($aBarcode) {
  $LOCALLENGTH = 9;    // HUH Barcode is a zero padded string of length 9 
  $returnvalue = $aBarcode;
  if (strlen($returnvalue) < $LOCALLENGTH) { 
     $returnvalue = str_pad($returnvalue, $LOCALLENGTH, "0", STR_PAD_LEFT);
  }
  return $returnvalue;
}

/** given some date and some precision where precision is 0,1,2,or 3, 
**  this will return the date as a string truncated to the appropriate precision
**  @date = dash separated date year - month - day
**  @precision = 0,1,2,3 where 0 = none, 1 = full, 2 = month, 3 = year
**  @return = date as a truncated text string
**/
function transformDate($date, $precision) { 
   if ($precision <= 0 || $precision > 3)
      return "";

   if (!preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $date))
     return "";

  if ($precision == 1)
     return $date;
  
  $parts = explode("-",$date);
  
  if ($precision == 2)
    return $parts[0]."-".$parts[1];

  if ($precision == 3)
    return $parts[0];
  
  return "";
}


/** given some date and some precision where precision is 0,1,2,or 3, 
**  this will return the date as a string truncated to the appropriate precision
**  in the text form  99, Month 9999  day, month year
**  @date = dash separated date year - month - day
**  @precision = 0,1,2,3 where 0 = none, 1 = full, 2 = month, 3 = year
**  @return = date as a truncated text string 
**/
function transformDateText($date, $precision) { 
   $returnvalue = "";
   if ($precision <= 0 || $precision > 3)
      return "";

   if (!preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $date))
     return "";

  $matches = array();
  $match = preg_match("/^([0-9][0-9][0-9][0-9])-([0-9][0-9])-([0-9][0-9])$/", $date, $matches);
  if ($precision == 1) { 
     $returnvalue =  $matches[1]."-".$matches[2]."-".$matches[3];
  }
  if ($precision == 2) { 
     $returnvalue =  $matches[1]."-".$matches[2]."-**";
  }
  if ($precision == 3) { 
     $returnvalue =  $matches[1];
  }   
  
  return $returnvalue;
}
 
 
 
function pageheader($mode = "specimen") {
	$title = "Specimen Search"; 
	$active['s'] = " class='active' ";
	$active['p'] = "";
	$active['b'] = "";
	switch ($mode) {
		case "specimen":
			$title = "Specimen Search"; 
			$active['s'] = " class='active' ";
			$active['p'] = "";
			$active['b'] = "";
			break;
		case "agent":
			$title = "Botanist Search"; 
			$active['s'] = " ";
			$active['p'] = " ";
			$active['b'] = " class='active' ";
			break;
		case "publication":
			$title = "Publication Search"; 
			$active['s'] = " ";
			$active['p'] = " class='active' ";
			$active['b'] = " ";
			break;
		default;
		
	}
	$result="<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<head>
	<meta http-equiv='content-type' content='text/html; charset=utf-8' />
	<title>HUH - Databases - $title</title>
	<link rel='stylesheet' type='text/css' media='print' href='print.css'></link>
	<link rel='stylesheet' type='text/css' href='dbstyles.css'></link>	
	<link rel='stylesheet' type='text/css' media='print' href='cms-db.css'></link>
</head>
<body>
<div id='allcontent'>
	
		<!-- header code begins -->
		<div id='header'>
			<div id='top_menu'>
			
		        <!-- SiteSearch Google HERE -->
		         
				<div id='embed_nav'>
		  			<ul>
						<li><a href='/'>Home</a></li>
						<li>&#124;</li>
						<li><a href='http://zatoichi.huh.harvard.edu/people/index.php'>Contact</a></li>
						<li>&#124;</li>
						<li><a href='/news_events/news_events.html'>News &#38; Events</a></li>

						<li>&#124;</li>
						<li><a href='/news_events/calendar.html'>Calendar</a></li>
						<li>&#124;</li>
						<li><a href='/sitemap.html'>Sitemap</a></li>
						<li>&#124;</li>
						<li><a href='/links.html'>Links</a></li>
		  			</ul>

				</div>
			
			</div>
			<!-- top menu ends -->
		
				<div id='mid_sect'>
					<a href='http://www.huh.harvard.edu'><img width='100' src='http://www.huh.harvard.edu/images/huh_logo_bw_100.png' alt='HUH logo' title='Home page' /></a>
				</div>
	

				<div id='topnav'>
		 			<ul>
			 			<li ><a href='http://www.huh.harvard.edu/collections/'>Collections</a></li>

			 			<li class=active><a href='http://www.huh.harvard.edu/databases/'>Databases</a></li>
			 			<li ><a href='http://www.huh.harvard.edu/research/'>Research</a></li>
			 			<li ><a href='http://www.huh.harvard.edu/seminar_series/'>Seminar Series</a></li>
			 			<li ><a href='http://www.huh.harvard.edu/libraries/'>Libraries</a></li>
			 			<li  ><a href='http://zatoichi.huh.harvard.edu/people/'>People</a></li>
			 			<li ><a href='http://www.huh.harvard.edu/publications/'>HPB Journal</a></li>

			 			<li ><a href='http://www.huh.harvard.edu/visiting/'>Visiting</a></li>
		   			</ul>
		 		</div>
		
		</div>

		<!-- header code ends -->
		
<div id='nav2'>
  <ul>
    <li><a href='addenda.html'>Search Hints</a></li>
    <li><a href='addenda.html#policy'>Distribution and Use Policy</a></li>
    <li><a href='botanist_index.html' ". $active['b'].">BOTANISTS</a></li>
    <li><a href='publication_index.html' ". $active['p'] .">PUBLICATIONS</a></li>
    <li><a href='specimen_index.html' ". $active['s'] .">SPECIMENS</a></li>
    <li><a href='add_correct.html'>Contribute</a></li>
    <li><a href='comment.html'>Comments/questions</a></li>
    
  </ul>
</div>  <!-- nav2 ends -->		
		
		
<div id='main'>
   <!-- main content begins -->
   <div id='main_text_wide'>
   <div id='title'>
      <h3>Index of Botanical Specimens</h3>
   </div>
"; 
   return $result;
}

function pagefooter() { 
   $result = "
   </div>
</div>
	<!-- main content ends -->

<!-- footer include begins -->		
	<div id='footer'>

			<div id='embed_nav2'>
		  		<ul>
					<li><a href='http://www.arboretum.harvard.edu/' target='_blank'>Arnold Arboretum</a></li>
					<li>&#124;</li>
					<li><a href='http://www.oeb.harvard.edu/' target='_blank'>OEB</a></li>

					<li>&#124;</li>
					<li><a href='http://www.pbi.fas.harvard.edu/' target='_blank'>PBI</a></li>
					<li>&#124;</li>
					<li><a href='http://www.hmnh.harvard.edu/' target='_blank'>HMNH</a></li> 
		  		</ul>
			</div>
			<h5>&copy; 2001 - <span id='cdate'></span> by the President and Fellows of <a href='http://www.harvard.edu/' target='_blank'>Harvard</a> College
		 	<br /><a href='priv_statement.html'>Privacy Statement</a> <span class='footer_indent'>Updated: 
		 		
		 		2009 May 21		 		
				</span></h5>

		 		
		 		<!-- gets current year for copyright date -->
		 		<script type='text/javascript'>
					// <![CDATA[
						var now = new Date()
						document.getElementById('cdate').innerHTML = now.getFullYear();
					// ]]>
				</script>

	</div>

</div> <!-- all content div tag ends -->
</body>
</html>";
   return $result;
} 

/** 
 * This function will execute the sql statement for your major category counts Given the query that select count, field name from group by field name, this will return a series of anchor statements that run a search on the field provided in the field parameter displaying the names and counts for each row.
 * @param = $query a string containing a valid sql statement in the form 
                'select count(*), fieldname from ... group by fieldname'
 * @param = $field  a string containing the name of a field that can be searched through the search 
            mode of this application (mode=search&$field=valueOfFieldName) 
 * @returns a list of <a href=  href='specimen_search.php?mode=search&$field={value}'>{value}</a> ({count}) <BR>
**/
function nameCountSearch($query, $field) {
    global $connection, $errormessage;
	
    $result = "";
    $statement = $connection->prepare($query);
	if ($statement) { 
		$statement->execute();
		$statement->bind_result($ct, $name);
		$statement->store_result();
		if ($statement->num_rows > 0 ) {
			while ($statement->fetch()) { 
			    if ($name!="") { 
				    $result .= "<a href='specimen_search.php?mode=search&$field=$name'>$name</a> ($ct) <BR>";
				}
			}
			
		} else {
			$errormessage .= "No matching results. ";
		}
  	    $statement->close();
	} else { 
	    $errormessage .= $connection->error; 
    } 
    
    return $result;
}


// TODO: Replace this with an include of the generated file.
// This function gives html that provides counts for major categories in the database. 
// There are elements to this function that are HUH specific.
function stats() {  
   $returnvalue = "";
   $returnvalue .= "<h1>Statistics</h1>";
   
   $returnvalue .= "<h2>Numbers of specimens in major groups.</h2>";
   $picklistName = 'HUH Taxon Group'; //customize for your own instance of Specify
   $query = "select count(*), Title from taxon left join picklistitem on groupnumber = value where PickListID=(select PickListID from  picklist where name='$picklistName') group by title";
   $returnvalue .= nameCountSearch($query, 'taxonGroup');
   
   $returnvalue .= "<h2>Numbers of specimens by Herbarium.</h2>";
   //you need to customize this for the way you have collections, collection codes and acronyms managed in Specify for your institution
   $query = "select count(PreparationId), preparationattribute.text3 from preparation left join preparationattribute on preparation.preparationattributeid = preparationattribute.preparationattributeid group by preparationattribute.text3";
   $query = "select count(collectionobjectid), text1 from fragment group by text1";
   $returnvalue .= nameCountSearch($query, 'herbarium');
   
   $returnvalue .= "<h2>Numbers of specimens by Type Status.</h2>";
   $query = "select count(collectionobjectid), typestatusname from determination d left join fragment f  on d.fragmentid = f.fragmentid where typestatusname is not null group by typestatusname";
   $returnvalue .= nameCountSearch($query, 'typestatus');
   
   return $returnvalue;
}

function browse($target = 'families') { 
	$result = ""; 
	$field = "";
	switch ($target) { 
		case 'types':
			$sql = 'select count(collectionobjectid), typestatus from web_search group by typestatus ';
			$field = 'typestatus';
			break;	
		case 'countries':
			$sql = 'select count(collectionobjectid), country from web_search group by country ';
			$field = 'country';
			break;	
		case 'families':
		default: 
			$sql = 'select count(collectionobjectid), family from web_search group by family ';
			$field = 'family';
	}
	if ($sql!="") { 
		$result = nameCountSearch($sql, $field);
	} 
	return $result; 
}

?>