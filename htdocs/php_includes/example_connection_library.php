<?php
// Example of a connection_library.php file. 

// set $debug = true to turn on debugging information (e.g. queries).  
$debug = true;
// set $debug to false to turn off debugging information
// $debug = false;

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

?>

