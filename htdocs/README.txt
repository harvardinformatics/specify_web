The htdocs folder is intended to be placed on the web tree, e.g. /var/www/htdocs, and provides the web interface for users to search the database.

The php_includes folder should be placed somewhere off the public web root, e.g. /var/www/php_includes/ and must be included in the php include path in php.ini.

The php_includes folder contains an example connection_library.php into which the hostname, database name, and access credentials for the specify database should be placed.  These credentials are invoked from the htdocs files to make a database connection, but are made inacessable to web visitors by placing then off the web root.  

The php_includes folder also includes a function_definitions.sql file that creates mysql functions that are invoked by the nightly flat file build script.  The populate_web_table_queries.sql file creates the nightly build stored procedure, and populate_web_tables.php invokes it.  Invoke populate_web_tables.php from a cron script to rebuild the flat web search tables.  This takes about an hour to run on the current HUH data (and thus builds a set of temporary tables and then switches them out for the live search tables).  

The htdocs web pages contain pairs of pages in the pattern specimen_index.html and specimen_search.php.  The html file contains a static web form for invoking searches, the _search.php files render the results of various searches, principally a list of search results and a specimen details page

