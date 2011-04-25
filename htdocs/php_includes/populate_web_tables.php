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
 
     $result = $connection->query($query);
     if (!$result) {
     	echo $connection->error; 
     }
     
 
 }
 
?>
