<?php
/*
 * Created on Jun 8, 2010
 *
 * Copyright 2010 The President and Fellows of Harvard College
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
 * @Author: Paul J. Morris  bdim@oeb.harvard.edu
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
$errormessage = "";

$mode = "menu";
 
$isinternal = FALSE;

if (preg_match("/^140\.247\.98\./",$_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR']=='127.0.0.1' || preg_match("/^128\.103\.155\./",$_SERVER['REMOTE_ADDR']) ) {
   $isinternal = TRUE;
}

if ($_GET['mode']!="")  {
	if ($_GET['mode']=="family_type_count") {
		$mode = "family_type_count"; 
	}
	if ($_GET['mode']=="herbarium_type_count") {
		$mode = "herbarium_type_count"; 
	}
	if ($_GET['mode']=="family_type_count_summary") {
		$mode = "family_type_count_summary"; 
	}
	if ($_GET['mode']=="annualreport") {
		$mode = "annualreport"; 
	}
	if ($_GET['mode']=="annualreport_details") {
		$mode = "annualreport_details"; 
	}
} 
	
echo pageheader('qc'); 

// Only display if internal 
if ($isinternal===TRUE) { 
  
	if ($connection) {
		if ($debug) {  echo "[$mode]"; } 
		
		switch ($mode) {
		    case "family_type_count":
		        echo family_type_count();
		        break;
		    case "herbarium_type_count":
		        echo herbarium_type_count();
		        break;
		    case "annualreport":
                        $year = preg_replace("[^0-9]","",$_GET['year']);
		        echo annualreport($year,FALSE);
		        break;
		    case "annualreport_details":
                        $year = preg_replace("[^0-9]","",$_GET['year']);
		        echo annualreport($year,TRUE);
		        break;
		    case "family_type_count_summary":
		        echo family_type_count_summary();
		        break;
			case "menu": 	
			default:
				echo menu(); 
		}
		
		$connection->close();
		
	} else { 
		$errormessage .= "Unable to connect to database. ";
	}
	
	if ($errormessage!="") {
		echo "<strong>Error: $errormessage</strong>";
	}
	
	
    echo "<h3><a href='stats.php'>Database Statistics</a></h3>";						
	
} else {
	echo "<h2>Stats pages are available only within HUH</h2>"; 
}

echo pagefooter();

// ******* main code block ends here, supporting functions follow. *****

function menu() { 
   $returnvalue = "";

   $returnvalue .= "<div>";
   $returnvalue .= "<h2>Type Counts</h2>";
   $returnvalue .= "<ul>";
   $returnvalue .= "<li><a href='stats.php?mode=herbarium_type_count'>Counts of number of types by herbarium (slow)</a></li>";
   $returnvalue .= "<li><a href='stats.php?mode=family_type_count'>Counts of number of types by family (slow)</a></li>";
   $returnvalue .= "<li><a href='stats.php?mode=family_type_count_summary'>Counts of number of types by family (from web search cache)</a></li>";
   $returnvalue .= "</ul>";
   $returnvalue .= "<h2>Reports</h2>";
   $returnvalue .= "<ul>";
   $returnvalue .= "<li><a href='stats.php?mode=annualreport&year='>Annual Report Statistics (summary)</li>";
   $returnvalue .= "<li><a href='stats.php?mode=annualreport_details&year='>Annual Report Statistics, with details (slow)</li>";
   for ($year=intval(date("Y"));$year>1958;$year--) { 
      $nextyear = $year-1;
      $returnvalue .= "<li><a href='stats.php?mode=annualreport&year=$year'>$nextyear-$year Annual Report Statistics (summary)</li>";
   }
   $returnvalue .= "</ul>";
   $returnvalue .= "</div>";

   return $returnvalue;
}

function family_type_count() { 
	global $connection,$debug;
   $returnvalue = "";
    
   $query = "select name, highestchildnodenumber, nodenumber from taxon where rankid = 140 order by name ";
   if ($debug) { echo "[$query]<BR>"; } 
      $returnvalue .= "<h2>Counts of the number of specimens in each family that have a type status (other than just 'NotType').</h2>";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($family,$highestchildnodenumber,$nodenumber);
		$statement->store_result();
	        $returnvalue .= "<table>";
		while ($statement->fetch()) {
	            $returnvalue .= "<tr><td>$family</td>";
                    $query2 = "select count(distinct fragment.identifier) from taxon left join determination on taxon.taxonid = determination.taxonid left join fragment on determination.fragmentid = fragment.fragmentid where nodenumber >= ? and highestchildnodenumber <= ? and determination.typestatusname is not null and determination.typestatusname <> 'NotType';";
	            $statement2 = $connection->prepare($query2);
                    $statement2->bind_param("ii",$nodenumber,$highestchildnodenumber);
		    $statement2->bind_result($count);
		    $statement2->execute();
		    $statement2->store_result();
		    while ($statement2->fetch()) {
	                $returnvalue .= "<td>$count</td>";
		    }
	            $returnvalue .= "</tr>";
                }
	        $returnvalue .= "</table>";
	}

   return $returnvalue;
}


function herbarium_type_count() { 
	global $connection,$debug;
   $returnvalue = "";
    
   $query = "select count(distinct fragment.identifier), typestatusname, fragment.text1 from determination left join fragment on determination.fragmentid = fragment.fragmentid group by typestatusname, fragment.text1 order by fragment.text1, typestatusname; ";
   if ($debug) { echo "[$query]<BR>"; }  
      $date = date(DATE_ISO8601);
      $returnvalue .= "<h2>Counts of the number of barcodes in each herbarium by type status.  As of $date</h2>";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($count, $typestatusname, $herbarium);
		$statement->store_result();
	        $returnvalue .= "<table>";
	        $returnvalue .= "<tr><th>Herbarium</th><th>Type Status</th><th>Count</th></tr>";
		while ($statement->fetch()) {
                    if ($typestatusname=='') { $typestatusname='[non-type]'; }
	            $returnvalue .= "<tr><td>$herbarium</td><td>$typestatusname</td><td>$count</td></tr>";
                }
	        $returnvalue .= "</table>";
	}

   return $returnvalue;
}

function family_type_count_summary() { 
	global $connection,$debug;
   $returnvalue = "";
    
   $query = "select distinct family from web_search where family is not null order by family ";
   if ($debug) { echo "[$query]<BR>"; } 
      $returnvalue .= "<h2>Counts of the number of specimens in each family that have a type status (other than just 'NotType').  Counts calculated from the web search cache table, may be slightly different than counts calculated from current data.</h2>";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($family);
		$statement->store_result();
	        $returnvalue .= "<table>";
		while ($statement->fetch()) {
	            $returnvalue .= "<tr><td>$family</td>";
                    $query2 = "select count(distinct barcode) from web_search where typestatus is not null and typestatus <> 'NotType' and family = ? ;";
	            $statement2 = $connection->prepare($query2);
                    $statement2->bind_param("s",$family);
		    $statement2->bind_result($count);
		    $statement2->execute();
		    $statement2->store_result();
		    while ($statement2->fetch()) {
	                $returnvalue .= "<td>$count</td>";
		    }
	            $returnvalue .= "</tr>";
                }
	        $returnvalue .= "</table>";
	}

   return $returnvalue;
}

function annualreport($year,$showdetails=FALSE) { 
   global $connection,$debug;
  
   $returnvalue = "";
   $year = substr(preg_replace("[^0-9]","",$year),0,4);
   if ($year=='') { $year = date('Y'); } 
   $syear = intval($year) - 1;
   $datestart = "$syear-05-31";
   $dateend = "$year-06-01";

   if ($showdetails) { 
      $returnvalue .= "Change to: <a href='stats.php?mode=annualreport&year=$year'>Annual Report Statistics (summary)</a><br>";
   } else { 
      $returnvalue .= "Change to: <a href='stats.php?mode=annualreport_details&year=$year'>Annual Report Statistics, with details (slow)</a><br>";
   }

   // ************  Accessions   ********** 

   $query = "select count(accession.accessionid), accession.text1, 
       sum(itemcount), sum(typecount), sum(nonspecimencount), 
       sum(returncount), sum(distributecount), sum(discardcount) 
   from accession left join accessionpreparation 
      on accession.accessionid = accessionpreparation.accessionid 
   where dateaccessioned > '$datestart' and dateaccessioned < '$dateend' 
   group by accession.text1;
 ";
   if ($debug) { echo "[$query]<BR>"; } 

   $returnvalue .= "<h2>Accessions in fiscal year $syear-$year</h2>";
   $statement = $connection->prepare($query);
   if ($statement) {
		$statement->execute();
		$statement->bind_result($accessions,$herbarium,$itemcount,$typecount,$nonspecimencount,$returncount,$distributecount,$discardcount);
		$statement->store_result();
	        $returnvalue .= "<table>";
                $taccessions=0;$titemcount=0;$ttypecount=0;$tnonspecimencount=0;
                $treturncount=0;$tdistributecount=0;$tdiscardcount=0;
	        $returnvalue .= "<tr><th>Herbarium</th><th>Accessions</th><th>Items</th><th>Types</th><th>Non-Specimens</th><th>Returned</th><th>Distributed</th><th>Discarded</th></tr>";
		while ($statement->fetch()) {
	            $returnvalue .= "<tr><td>$herbarium</td><td>$accessions</td><td>$itemcount</td><td>$typecount</td><td>$nonspecimencount</td><td>$returncount</td><td>$distributecount</td><td>$discardcount</td></tr>";
                    $taccessions+=$accessions;
                    $titemcount+=$itemcount;
                    $ttypecount+=$typecount;
                    $tnonspecimencount+=$nonspecimencount;
                    $treturncount+=$returncount;
                    $tdistributecount+=$distributecount;
                    $tdiscardcount+=$discardcount;
                }
	        $returnvalue .= "<tr><td><strong>Totals</strong></td><td>$taccessions</td><td>$titemcount</td><td>$ttypecount</td><td>$tnonspecimencount</td><td>$treturncount</td><td>$tdistributecount</td><td>$tdiscardcount</td></tr>";
	        $returnvalue .= "</table>";
   }

   $query = "select count(accession.accessionid), accession.text1, 
       sum(itemcount), sum(typecount), sum(nonspecimencount), 
       sum(returncount), sum(distributecount), sum(discardcount),
       accession.type
   from accession left join accessionpreparation 
      on accession.accessionid = accessionpreparation.accessionid 
   where dateaccessioned > '$datestart' and dateaccessioned < '$dateend' 
   group by accession.type, accession.text1;
 ";
   if ($debug) { echo "[$query]<BR>"; } 

   $returnvalue .= "<h2>Accessions in fiscal year $syear-$year by type</h2>";
   $statement = $connection->prepare($query);
   if ($statement) {
                $statement->execute();
                $statement->bind_result($accessions,$herbarium,$itemcount,$typecount,$nonspecimencount,$returncount,$distributecount,$discardcount,$accessiontype);
                $statement->store_result();
                $returnvalue .= "<table>";
                $taccessions=0;$titemcount=0;$ttypecount=0;$tnonspecimencount=0;
                $treturncount=0;$tdistributecount=0;$tdiscardcount=0;
                $returnvalue .= "<tr><th>Type</th><th>Herbarium</th><th>Accessions</th><th>Items</th><th>Types</th><th>Non-Specimens</th><th>Returned</th><th>Distributed</th><th>Discarded</th></tr>";
                while ($statement->fetch()) {
                    $returnvalue .= "<tr><td>$accessiontype</td><td>$herbarium</td><td>$accessions</td><td>$itemcount</td><td>$typecount</td><td>$nonspecimencount</td><td>$returncount</td><td>$distributecount</td><td>$discardcount</td></tr>";
                    $taccessions+=$accessions;
                    $titemcount+=$itemcount;
                    $ttypecount+=$typecount;
                    $tnonspecimencount+=$nonspecimencount;
                    $treturncount+=$returncount;
                    $tdistributecount+=$distributecount;
                    $tdiscardcount+=$discardcount;
                }
                $returnvalue .= "<tr><td><strong>Totals</strong></td><td>$taccessions</td><td>$titemcount</td><td>$ttypecount</td><td>$tnonspecimencount</td><td>$treturncount</td><td>$tdistributecount</td><td>$tdiscardcount</td></tr>";
                $returnvalue .= "</table>";
   }


   // ************  Loans   ********** 

   $query = "select count(*), text2, purposeofloan, text3 from loan 
    where loandate > '$datestart' and loandate < '$dateend' 
    group by text2, purposeofloan, text3;";
   if ($debug) { echo "[$query]<BR>"; } 
      $returnvalue .= "<h2>Loans out in fiscal year $syear-$year</h2>";
	$statement = $connection->prepare($query);
	if ($statement) {
		$statement->execute();
		$statement->bind_result($count,$unit,$purposeofloan,$role);
		$statement->store_result();
	        $returnvalue .= "<table>";
	        $returnvalue .= "<tr><th>Unit</th><th>Loans</th><th>Purpose</th><th>Recipient Role</th></tr>";
		while ($statement->fetch()) {
                    if ($unit=='FC') { $unit = "Farlow Collections"; } 
                    if ($unit=='GC') { $unit = "General Collections"; } 
	            $returnvalue .= "<tr><td>$unit</td><td>$count</td><td>$purposeofloan</td><td>$role</td></tr>";
                }
	        $returnvalue .= "</table>";
	}
   $query = "select count(*), text2, purposeofloan, text3 from loan 
    where dateclosed > '$datestart' and dateclosed < '$dateend' 
    group by text2, purposeofloan, text3;";
   if ($debug) { echo "[$query]<BR>"; }
      $returnvalue .= "<h2>Loans closed in fiscal year $syear-$year</h2>";
        $statement = $connection->prepare($query);
        if ($statement) {
                $statement->execute();
                $statement->bind_result($count,$unit,$purposeofloan,$role);
                $statement->store_result();
                $returnvalue .= "<table>";
                $returnvalue .= "<tr><th>Unit</th><th>Loans</th><th>Purpose</th><th>Recipient Role</th></tr>";
                while ($statement->fetch()) {
                    if ($unit=='FC') { $unit = "Farlow Collections"; } 
                    if ($unit=='GC') { $unit = "General Collections"; } 
                    $returnvalue .= "<tr><td>$unit</td><td>$count</td><td>$purposeofloan</td><td>$role</td></tr>";
                }
                $returnvalue .= "</table>";
        }

   // ************  Loan detailed breakdowns   ********** 

   if ($showdetails) { 

   $query = "select count(distinct loan.loanid), sum(itemcount), sum(nonspecimencount),sum(typecount), count(identifier), fragment.text1, loan.text2, text3 from loan left join loanpreparation on loan.loanid = loanpreparation.loanid  left join fragment on loanpreparation.preparationid = fragment.preparationid where loandate > '$datestart' and loandate < '$dateend' group by fragment.text1, text3, loan.text2 order by loan.text2, loan.text1, text3";
   $returnvalue .= transactionitemtotals($query,"Counts of material outgoing in Loans opened in fiscal year $syear-$year");

   $query = "select count(distinct loan.loanid), sum(itemcount), sum(nonspecimencount),sum(typecount), count(fragment.identifier), '', loan.text2, concat(ifnull(if(gethighertaxonofrank(140,t.highestchildnodenumber,t.nodenumber) is null, t.fullname, gethighertaxonofrank(140,t.highestchildnodenumber,t.nodenumber)),if(gethighertaxonofrank(140,t1.highestchildnodenumber,t1.nodenumber) is null, t1.fullname, gethighertaxonofrank(140,t1.highestchildnodenumber,t1.nodenumber)))) from loan left join loanpreparation on loan.loanid = loanpreparation.loanid left join fragment on loanpreparation.preparationid = fragment.preparationid left join preparation on loanpreparation.preparationid = preparation.preparationid left join taxon t on preparation.taxonid = t.taxonid left join determination on fragment.fragmentid = determination.fragmentid left join taxon t1 on determination.taxonid = t1.taxonid where loandate > '2011-05-31' and loandate < '2012-06-01' group by loan.text2, concat(ifnull(if(gethighertaxonofrank(140,t.highestchildnodenumber,t.nodenumber) is null, t.fullname, gethighertaxonofrank(140,t.highestchildnodenumber,t.nodenumber)),if(gethighertaxonofrank(140,t1.highestchildnodenumber,t1.nodenumber) is null, t1.fullname, gethighertaxonofrank(140,t1.highestchildnodenumber,t1.nodenumber)))) order by loan.text2, concat(ifnull(if(gethighertaxonofrank(140,t.highestchildnodenumber,t.nodenumber) is null, t.fullname, gethighertaxonofrank(140,t.highestchildnodenumber,t.nodenumber)),if(gethighertaxonofrank(140,t1.highestchildnodenumber,t1.nodenumber) is null, t1.fullname, gethighertaxonofrank(140,t1.highestchildnodenumber,t1.nodenumber))));";
   $returnvalue .= transactionitemtotals($query,"Counts of material outgoing in Loans opened in fiscal year $syear-$year by family/taxon","Loans","Taxon");

   $query = "select count(distinct loan.loanid), sum(rp.itemcount), sum(rp.nonspecimencount),sum(rp.typecount), count(identifier), fragment.text1, loan.text2, text3 from loan left join loanpreparation on loan.loanid = loanpreparation.loanid left join loanreturnpreparation rp on loanpreparation.loanpreparationid = rp.loanpreparationid  left join fragment on loanpreparation.preparationid = fragment.preparationid where returneddate > '$datestart' and returneddate < '$dateend' group by fragment.text1, text3, loan.text2 order by loan.text2, loan.text1, text3";
   $returnvalue .= transactionitemtotals($query,"Counts of material on loan returned in fiscal year $syear-$year (loans may still be open)");

   $query = "select count(distinct loan.loanid), sum(itemcount), sum(nonspecimencount),sum(typecount), count(identifier), fragment.text1, loan.text2, text3 from loan left join loanpreparation on loan.loanid = loanpreparation.loanid  left join fragment on loanpreparation.preparationid = fragment.preparationid where dateclosed > '$datestart' and dateclosed < '$dateend' group by fragment.text1, text3, loan.text2 order by loan.text2, loan.text1, text3";
   $returnvalue .= transactionitemtotals($query,"Counts of all material returned in Loans that were closed in fiscal year $syear-$year (includes material returned in previous years)");

   $query = "select count(distinct loan.loanid), sum(itemcount), sum(nonspecimencount),sum(typecount), count(identifier), fragment.text1, loan.text2, text3 from loan left join loanpreparation on loan.loanid = loanpreparation.loanid  left join fragment on loanpreparation.preparationid = fragment.preparationid where loandate <= '$datestart' and isclosed = 0 group by fragment.text1, text3, loan.text2 order by loan.text2, loan.text1, text3";
   $returnvalue .= transactionitemtotals($query,"Counts of outstanding material in open Loans opened before fiscal year $syear-$year");

   for ($x=0;$x<2;$x++) { 
      if ($x==0) { $direction = 'Sent'; } else { $direction = 'Returned (closed)'; } 
      if ($x==0) { $date = 'loandate'; } else { $date = 'dateclosed'; } 

   $query = "select case country when 'USA' then country else 'International' end as isUSA, 'Any', count(distinct loan.loanid), sum(itemcount), sum(nonspecimencount),sum(typecount), count(identifier), fragment.text1, loan.text2, text3 from loan left join loanpreparation on loan.loanid = loanpreparation.loanid  left join fragment on loanpreparation.preparationid = fragment.preparationid left join loanagent on loan.loanid = loanagent.loanid left join agent on loanagent.agentid = agent.agentid left join address on agent.agentid = address.agentid where $date > '$datestart' and $date < '$dateend' and role = 'Borrower' group by case country when 'USA' then country else 'International' end, fragment.text1, text3, loan.text2 order by case country when 'USA' then country else 'International' end, loan.text2, loan.text1, text3;";

   $returnvalue .= transactionitemlist($query,"Loans $direction by US/International in fiscal year $syear-$year","Loans");
  
   $query = "select country, abbreviation, count(distinct loan.loanid), sum(itemcount), sum(nonspecimencount),sum(typecount), count(identifier), fragment.text1, loan.text2, text3 from loan left join loanpreparation on loan.loanid = loanpreparation.loanid  left join fragment on loanpreparation.preparationid = fragment.preparationid left join loanagent on loan.loanid = loanagent.loanid left join agent on loanagent.agentid = agent.agentid left join address on agent.agentid = address.agentid where $date > '$datestart' and $date < '$dateend' and role = 'Borrower' group by country, abbreviation, fragment.text1, text3, loan.text2 order by country, abbreviation, loan.text2, loan.text1, text3;";

   $returnvalue .= transactionitemlist($query,"Loans $direction by country and herbarium in fiscal year $syear-$year","Loans");

   $query = "select case country when 'USA' then country else 'International' end as isUSA, case yesno3 when 1 then 'Visitor' else 'Not visitor' end as isvisitor, count(distinct loan.loanid), sum(itemcount), sum(nonspecimencount),sum(typecount), count(identifier), fragment.text1, loan.text2, text3 from loan left join loanpreparation on loan.loanid = loanpreparation.loanid  left join fragment on loanpreparation.preparationid = fragment.preparationid left join loanagent on loan.loanid = loanagent.loanid left join agent on loanagent.agentid = agent.agentid left join address on agent.agentid = address.agentid where $date > '$datestart' and $date < '$dateend' and role = 'Borrower' group by case country when 'USA' then country else 'International' end, case yesno3 when 1 then 'Visitor' else 'Not visitor' end, fragment.text1, text3, loan.text2 order by case country when 'USA' then country else 'International' end, case yesno3 when 1 then 'Visitor' else 'Not visitor' end,  loan.text2, loan.text1, text3;";

   $returnvalue .= transactionitemlist($query,"Loans $direction by country and Visitor status in fiscal year $syear-$year","Loans");

   }  // repeat for opened/closed

   }  // show details 

   // ************   Borrows   ********** 

   $query = "select count(distinct borrow.borrowid), sum(itemcount), sum(nonspecimencount),sum(typecount), 'n/a', borrow.text2, if(text2='FH','FC','GC'), text3 from borrow left join borrowmaterial on borrow.borrowid = borrowmaterial.borrowid where receiveddate > '$datestart' and receiveddate < '$dateend' group by text3, borrow.text2 order by borrow.text2, borrow.text1, text3";
   $returnvalue .= transactionitemtotals($query,"Counts of material Borrowed in fiscal year $syear-$year","Borrows");

   $query = "select count(distinct borrow.borrowid), sum(br.itemcount), sum(br.nonspecimencount),sum(br.typecount), 'n/a', borrow.text2, if(text2='FH','FC','GC'), text3 from borrow left join borrowmaterial on borrow.borrowid = borrowmaterial.borrowid left join borrowreturnmaterial br on borrowmaterial.borrowmaterialid = br.borrowmaterialid where returneddate > '$datestart' and returneddate < '$dateend' group by text3, borrow.text2 order by borrow.text2, borrow.text1, text3";
   $returnvalue .= transactionitemtotals($query,"Counts of Borrowed material returned in fiscal year $syear-$year","Borrows");

   if ($showdetails) { 

    $query = "select count(distinct borrow.borrowid), sum(br.itemcount), sum(br.nonspecimencount),sum(br.typecount), 'n/a', borrow.text2, if(borrow.text2='FH','FC','GC'), if(gethighertaxonofrank(140,highestchildnodenumber,nodenumber) is null, fullname, gethighertaxonofrank(140,highestchildnodenumber,nodenumber)) from borrow left join borrowmaterial on borrow.borrowid = borrowmaterial.borrowid left join borrowreturnmaterial br on borrowmaterial.borrowmaterialid = br.borrowmaterialid left join taxon on borrowmaterial.taxonid = taxon.taxonid where returneddate > '$datestart' and returneddate < '$dateend' group by text3, borrow.text2, taxon.taxonid order by borrow.text2, borrow.text1, text3";

   $returnvalue .= transactionitemtotals($query,"Counts of Borrowed material by family/taxon returned in fiscal year $syear-$year","Borrows","Taxon");

   } // show details 

   // ************  Exchanges (out) ********** 

   $query = "select exchangedate, text2, descriptionofmaterial, quantityexchanged, abbreviation from exchangeout
    left join agent on exchangeout.senttoorganizationid = agent.agentid
    where exchangedate > '$datestart' and exchangedate < '$dateend' 
    order by text2, abbreviation
    ;";
   if ($debug) { echo "[$query]<BR>"; }
      $returnvalue .= "<h2>Exchanges Out sent in fiscal year $syear-$year</h2>";
        $statement = $connection->prepare($query);
        if ($statement) {
                $statement->execute();
                $statement->bind_result($date,$unit,$description,$quantity,$tounit);
                $statement->store_result();
                $returnvalue .= "<table>";
                $returnvalue .= "<tr><th>From</th><th>To</th><th>Quantity</th><th>Description</th></tr>";
                while ($statement->fetch()) {
                    $returnvalue .= "<tr><td>$unit</td><td>$tounit</td><td>$quantity</td><td>$description</td></tr>";
                }
                $returnvalue .= "</table>";
        }

   // ************  Gifts (out) ********** 

   $query = "select count(distinct gift.giftid), sum(itemcount), sum(nonspecimencount),sum(typecount), purposeofgift, gift.text2 from gift left join giftpreparation on gift.giftid = giftpreparation.giftid where giftdate > '2011-05-31' and giftdate < '2012-06-01' group by gift.text2, purposeofgift order by gift.text2, purposeofgift";
   if ($debug) { echo "[$query]<BR>"; }
      $returnvalue .= "<h2>Gifts Out sent in fiscal year $syear-$year</h2>";
        $statement = $connection->prepare($query);
        if ($statement) {
                $statement->execute();
                $statement->bind_result($giftcount,$itemcount,$noncount,$typecount,$purpose,$unit);
                $statement->store_result();
                $returnvalue .= "<table>";
                $returnvalue .= "<tr><th>From</th><th>Purpose</th><th>Number of Gifts</th><th>Item&nbsp;Count</th><th>Nonspecimen&nbsp;Count</th><th>Type&nbsp;Count</th></tr>";
                while ($statement->fetch()) {
                    $returnvalue .= "<tr><td>$unit</td><td>$purpose</td><td>$giftcount</td><td>$itemcount</td><td>$noncount</td><td>$typecount</td></tr>";
                }
                $returnvalue .= "</table>";
        }




   // ************  QC Checks   ********** 

   $query = "select country, abbreviation, loan.loannumber from loan left join loanagent on loan.loanid = loanagent.loanid left join agent on loanagent.agentid = agent.agentid left join address on agent.agentid = address.agentid where loandate > '$datestart' and loandate < '$dateend' and role = 'Borrower'   and (country is null or abbreviation is null) order by loannumber;";

   if ($debug) { echo "[$query]<BR>"; }
   $returnvalue .= "<h2>Data Quality issues: Loans where the borrower accronym or country was blank.</h2>";
   $statement = $connection->prepare($query);
   if ($statement) {
       $statement->execute();
       $statement->bind_result($country,$toherbarium,$loannumber);
       $statement->store_result();
       $returnvalue .= "<table>";
       $returnvalue .= "<tr><th>Loan Number</th><th>Country</th><th>Borrower(Herbarium)</th></tr>";
       while ($statement->fetch()) {
           $returnvalue .= "<tr><td>$loannumber</td><td>$country</td><td>$toherbarium</td></tr>";
       }
       $returnvalue .= "</table>";
   }


   return $returnvalue;
}

function transactionitemtotals($query,$title,$type="Loans",$groupcoll="Recipient Role") { 
   global $connection,$debug;
   $returnvalue = "";

   $titemcount=0;
   $tloancount=0;
   $ttypecount=0;
   $tnonspecimencount=0;
   $tbarcodecount=0;
   $fitemcount=0;
   $floancount=0;
   $ftypecount=0;
   $fnonspecimencount=0;
   $fbarcodecount=0;
   $gitemcount=0;
   $gloancount=0;
   $gtypecount=0;
   $gnonspecimencount=0;
   $gbarcodecount=0;
   if ($debug) { echo "[$query]<BR>"; }
      $returnvalue .= "<h2>$title</h2>";
        $statement = $connection->prepare($query);
        if ($statement) {
                $statement->execute();
                $statement->bind_result($loancount,$itemcount,$nonspecimencount,$typecount,$barcodecount,$herbarium,$unit,$purposeofloan);
                $statement->store_result();
                $returnvalue .= "<table>";
                $returnvalue .= "<tr><th>Unit</th><th>Herbarium</th><th>$type</th><th>Items</th><th>Non-specimens</th><th>Types</th><th>Barcoded Items</th><th>$groupcoll</th></tr>";
                while ($statement->fetch()) {
                    if ($unit=='FC') { 
                        $floancount+=$loancount;
                        $fitemcount+=$itemcount;
                        $ftypecount+=$fypecount;
                        $fnonspecimencount+=$nonspecimencount;
                        $fbarcodecount+=$barcodecount;
                        $unit = "Farlow Collections"; 
                    } 
                    if ($unit=='GC') { 
                        $gloancount+=$loancount;
                        $gitemcount+=$itemcount;
                        $gtypecount+=$gypecount;
                        $gnonspecimencount+=$nonspecimencount;
                        $gbarcodecount+=$barcodecount;
                        $unit = "General Collections"; 
                    }
                    $returnvalue .= "<tr> <td>$unit</td><td>$herbarium</td><td>$loancount</td><td>$itemcount</td><td>$nonspecimencount</td><td>$typecount</td><td>$barcodecount</td><td>$purposeofloan</td></tr>";
                    $titemcount+=$itemcount;
                    $tloancount+=$loancount;
                    $ttypecount+=$typecount;
                    $tnonspecimencount+=$nonspecimencount;
                    $tbarcodecount+=$barcodecount;
                }
                $total = $fitemcount+$fnonspecimencount+$fbarcodecount;
                $returnvalue .= "<tr><td><strong>FH Totals</strong></td><td></td><td>$floancount</td><td>$fitemcount</td><td>$fnonspecimencount</td><td>$ftypecount</td><td>$fbarcodecount</td><td><strong>FH Total=$total</td></tr>";
                $total = $gitemcount+$gnonspecimencount+$gbarcodecount;
                $returnvalue .= "<tr><td><strong>GC Totals</strong></td><td></td><td>$gloancount</td><td>$gitemcount</td><td>$gnonspecimencount</td><td>$gtypecount</td><td>$gbarcodecount</td><td><strong>GC Total=$total</td></tr>";
                $total = $titemcount+$tnonspecimencount+$tbarcodecount;
                $returnvalue .= "<tr><td><strong>Totals</strong></td><td></td><td>$tloancount</td><td>$titemcount</td><td>$tnonspecimencount</td><td>$ttypecount</td><td>$tbarcodecount</td><td><strong>Total=$total</td></tr>";
                $returnvalue .= "</table>";
        }
     return $returnvalue;
}

function transactionitemlist($query,$title,$type="Loans") { 
   global $connection,$debug;
   $returnvalue = "";

   if ($debug) { echo "[$query]<BR>"; }
      $returnvalue .= "<h2>$title</h2>";
        $statement = $connection->prepare($query);
        if ($statement) {
                $statement->execute();
                $statement->bind_result($country,$toherbarium,$loancount,$itemcount,$nonspecimencount,$typecount,$barcodecount,$herbarium,$unit,$purposeofloan);
                $statement->store_result();
                $returnvalue .= "<table>";
                $returnvalue .= "<tr><th>Country</th><th>Recipient</th><th>Unit</th><th>Herbarium</th><th>$type</th><th>Items</th><th>Non-specimens</th><th>Types</th><th>Barcoded Items</th><th>Recipient Role</th></tr>";
                while ($statement->fetch()) {
                    if ($unit=='FC') { 
                        $unit = "Farlow Collections"; 
                    } 
                    if ($unit=='GC') { 
                        $unit = "General Collections"; 
                    }
                    $returnvalue .= "<tr><td>$country</td><td>$toherbarium</td><td>$unit</td><td>$herbarium</td><td>$loancount</td><td>$itemcount</td><td>$nonspecimencount</td><td>$typecount</td><td>$barcodecount</td><td>$purposeofloan</td></tr>";
                }
                $returnvalue .= "</table>";
        }
     return $returnvalue;
}

mysqli_report(MYSQLI_REPORT_OFF);
 
?>
