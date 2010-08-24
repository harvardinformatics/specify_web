<?php
/*
 * populate_web_tables.php
 * 
 * Runs stored procedure in MySQL to populate the web tables on systems in which
 * MySQL Scheduled Events are not available.
 * 
 * Created on Aug 24, 2010
 * @author Paul J. Morris
 *
 *The following grants will be needed:
   grant select  on specify.* to 'specify_web_admi'@'localhost' identified by 'password';
   grant all on specify.web_search to 'specify_web_admi'@'localhost' identified by 'password';
   grant all on specify.temp_web_search to 'specify_web_admi'@'localhost' identified by 'password';
   grant all on specify.web_quicksearch to 'specify_web_admi'@'localhost' identified by 'password';
   grant all on specify.temp_web_quicksearch to 'specify_web_admi'@'localhost' identified by 'password';
   grant all on specify.temp_geography to 'specify_web_admi'@'localhost' identified by 'password';
   grant all on specify.temp_taxon to 'specify_web_admi'@'localhost' identified by 'password';
   grant execute on procedure specify.populate_web_tables to 'specify_web_admi'@'localhost' identified by 'password';
 *
 */
 
 include_once('connection_library.php');
 $connection = specify_adm_connect();
 
 if ($connection) { 
 
     $query = " CALL specify.populate_web_tables ";
 
     $result = $connection->exec($query);
     if (!$result) {
     	echo $connection->mysql_error(); 
     }
     
 
 }
 
?>
