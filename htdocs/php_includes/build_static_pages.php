<?php
/*
 * Created on Dec 3, 2009
 *
 */
$debug=true;

$targetdirectory = "/var/www/htdocs/specify_web/";

include_once('connection_library.php');
include_once('specify_library.php');

$connection = specify_connect();
$errormessage = "";

$mode = "rebuild";

if ($connection) { 
	
	switch ($mode) {
		
	   case "rebuild":
	      $targetfile = $targetdirectory."familylist.html";
	      $file = fopen($targetfile,"w");
          fwrite ($file, pageheader($mode)); 
	      fwrite($file,browse("families"));
          fwrite($file,pagefooter());
          fclose($file);
          
	      $targetfile = $targetdirectory."countrylist.html";
	      $file = fopen($targetfile,"w");
          fwrite ($file, pageheader($mode)); 
	      fwrite($file,browse("countries"));
          fwrite($file,pagefooter());
          fclose($file);
          
	      $targetfile = $targetdirectory."sitestatistics.html";
	      $file = fopen($targetfile,"w");
          fwrite ($file, pageheader($mode)); 
	      fwrite($file,stats());
          fwrite($file,pagefooter());
          fclose($file);
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

 
// ******* main code block ends here, supporting functions follow. *****

// TODO: Refactor: build static stats page fragment here, include in specify_library.stats() 
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
   $query = "select count(CollectionObjectID), preparationattribute.text3 from preparation left join preparationattribute on preparation.preparationattributeid = preparationattribute.preparationattributeid group by preparationattribute.text3";
   $returnvalue .= nameCountSearch($query, 'taxonGroup');
   return $returnvalue;
}




function browse($target = "families") { 
global $connection, $errormessage;
$returnvalue = "";

$FAMILYRANKID = 140;  // Magic Value. Check taxontreedefitem to make sure that the RankID for Name=Family is 
                      // 140 in your specify instance.  This is the default, and is recommended, 
                      // but could differ if you imported your own taxon tree.  
$COUNTRYRANKID = 200; // Magic Value.  geography.rankid = 200 is the default for countries in Specify, and
                      // is the recommended value, but could differ.  

if ($target == "countries") { 
   $sql = "select count(g.Name) as ct, g.Name
              from (select CollectionObjectID, CO.CollectionMemberID, 
	                CE.LocalityID, LOC.GeographyID, GEO.NodeNumber  
	            from collectionobject as CO 
		         inner join collectingevent as CE on CE.CollectingEventID = CO.CollectingEventID 
			 inner join locality LOC on CE.LocalityID = LOC.LocalityID 
			 inner join geography as GEO on GEO.GeographyID = LOC.GeographyID
		   ) as COLGEO, 
		   geography as g 
	      where 
	         g.rankid = $COUNTRYRANKID and 
             COLGEO.nodenumber between g.nodenumber and g.HighestChildNodeNumber 
              group by g.Name 
	      order by g.Name";
    $field = "country";
} else { 
   // Browse Families 
   // Fastest, but only returns number of specimens identified to rank of family.
   $sql = "select count(*) as ct, name from taxon left join determination on taxon.taxonid = determination.taxonid where taxon.rankid = $FAMILYRANKID group by taxon.name";
   // Comprehensive, but slow - takes about 11 minutes on HUH data.
   // Includes All families, including those that have no determinations, and 
   // counts of all collection objects identified within each family.
   $sql =  "select count(taxon2.taxonid) as ct, t.name " .
	   "from taxon as taxon2, " .
	   "   taxon as t left join determination on t.taxonid = determination.taxonid " .
	   "where t.rankid = $FAMILYRANKEID " .
	   "  and taxon2.nodenumber > t.nodenumber " .
	   "  and taxon2.nodenumber < t.highestchildnodenumber " .
	   "group by t.name";  

// Faster, and relatively comprehensive, takes about 2 minutes on HUH data.
// Includes all families that have specimens identified to or within them.  
   $sql = "select count(t.Name) as ct, t.Name 
             from 
   	     (select fragment.CollectionObjectID, tax.NodeNumber 
	       from determination as det 
	       inner join taxon as tax on tax.taxonid = det.taxonid
	       left join fragment on det.fragmentid = fragment.fragmentid
	      ) as COLTAX, 
	      taxon as t 
	   where 
	     t.rankid = $FAMILYRANKID and 
	     COLTAX.nodenumber between t.nodenumber and t.HighestChildNodeNumber
	   group by t.Name 
	   order by t.Name"; 
   $field = "ht";
} 
   $returnvalue .= nameCountSearch($sql, $field);
   return $returnvalue;
} 
 

?>
