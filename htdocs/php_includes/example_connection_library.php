<?php
// Example of a connection_library.php file. 

// set $debug = true to turn on debugging information (e.g. queries).  
$debug = true;
// set $debug to false to turn off debugging information
// $debug = false;

/**
 * Create a connection to a specify database.
 * 
 * @return a MySQLi object, or false if the connection failed.
 */
function specify_connect() {
   $returnvalue = false;
   // Set the values of hostname (probably 'localhost'), 
   // a mysql user with select only access to the specify database, 
   // that user's password, 
   // and the name of the database (probably 'specify') 
   // for appropirate values for your installation.
   // To create a select only user, issue the sql query: 
   // grant select on specify.* to 'specify_username'@'hostname' identified by 'password' 
   $connection = mysqli_connect('hostname','specify_username','password', 'specify');
   if ($connection) { 
      $connection->set_charset('utf8');   
      $returnvalue = $connection;
   } else { 
      echo mysqli_connect_errorno();
   }
   return $returnvalue;
}

/**
 * Connect to specify database with a user that is able to run the populate web tables 
 * procedure.  This user and function isn't needed if the populate web tables query will
 * run as a scheduled event in MySQL.    
 * 
 * @see populate_web_tables.php
 * 
 * @return a MySQLi object, or false if connection attempt failed.
 */
function specify_adm_connect() {
   $returnvalue = false;
   // Set the values of hostname (probably 'localhost'), 
   // a mysql user with limited access to the specify database (see populate_web_tables.php), 
   // that user's password, 
   // and the name of the database (probably 'specify') 
   // for appropirate values for your installation.
   // To create a select only user, issue the sql query: 
   // grant select on specify.* to 'specify_username'@'hostname' identified by 'password' 
   $connection = mysqli_connect('hostname','specify_admin_username','password', 'specify');
   if ($connection) { 
      $connection->set_charset('utf8');   
      $returnvalue = $connection;
   } else { 
      echo mysqli_connect_errorno();
   }
   return $returnvalue;
}


?>

