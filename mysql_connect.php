<?php

class mysql_db {

     static $instance;
     static $dbh;

     function __construct() {  }

     public static function factory() {
         if(!isset(self::$instance)) {
             self::$instance = new mysql_db();
             self::$dbh = null;
         }

         return self::$instance;
     }

     public function connect($config) {

         if(!isset($config['mysql_host']) || !isset($config['mysql_user']) || !isset($config['mysql_pass'])) {
             die('Please supply `mysql_host`, `mysql_user`, `mysql_pass`');
         }

         $host   = $config['mysql_host'];
         $user   = $config['mysql_user'];
         $pass   = $config['mysql_pass'];
         $dbname = $config['mysql_dbname']; 

         /* $db_conn = mysql_connect($host, $user, $pass);
         if(!$db_conn) {
             die('could not connect: '.mysql_error());
         } */

         try {
             $DBH = new PDO("mysql:host={$host};dbname={$dbname}", $user, $pass);
             $DBH->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         } catch(PDOException $e) {
             echo $e->getMessage();
         }

         self::$dbh = $DBH;
     }

     public function close() {
         self::$dbh = null;
     }

     public static function query($sql, $insert_update = false) {
         
         $ret_arr = array();
        
         try {
             $sth = self::$dbh->query($sql);
             $sth->setFetchMode(PDO::FETCH_ASSOC);

             // Don't Call Fetch on Inserts or updates
             if(!$insert_update) {
                 while($row = $sth->fetch()) {
                     $ret_arr[] = $row;
                 }
             }
         } catch(PDOException $e) {
             echo $e->getMessage();
         } 

         return $ret_arr;
     }

     /* public static function insert($db_conn, $sql) {

         $results = mysql_query($sql);
         if($results) {
             $insert_id = mysql_insert_id();
             return $insert_id;
         } else {
             error_log('bad query. insert was not successful: '.$sql);
             return false;
         }
     } */

     /* simple insert function
      * @params $table = 'db_table_name', $data = array(array(column_name => value))
      */
     public static function insert($table, $data) {

         foreach($data as $datum) {
             $columns = array_keys($datum);
             $values  = array_values($datum);

             $insert_string = "INSERT INTO {$table} (".implode(', ', $columns).") VALUES (".implode(', ', $columns).")";
             error_log('insert string: '.$insert_string);
         }
         
     }
}
