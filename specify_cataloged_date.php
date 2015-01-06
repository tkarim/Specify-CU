<?php

/* Specify Date Clean Up
 * Based on: https://github.com/jlauters/script-bootstrap
 */

$config = array(
    'db_type'      => 'mysql' // pick one
   ,'mysql_host'   => 'localhost'
   ,'mysql_user'   => 'root'
   ,'mysql_pass'   => 'root'
   ,'mysql_dbname' => 'specify_hack'
   ,'environment'  => 'local' // pick one
);

/* if local take it all */
if('local' == $config['environment']) {
    ini_set('memory_limit', -1);
}

if('mysql' == $config['db_type']) {
    require_once 'mysql_connect.php';
    mysql_db::factory()->connect($config);
}

// Init our counter and our bad date check
$success_count = 0;
$bad_date = date('Y-m-d', strtotime('1970-01-01'));

$sql = <<<EOSQL
SELECT CollectionObjectID, Name 
FROM collectionobject
WHERE Name IS NOT NULL
EOSQL;

$result = mysql_db::query($sql);
error_log('row count: '.count($result));

foreach($result as $row) {

    //one off fix for 2 digit years
    if('5/28/04' === $row['Name']) { $row['Name'] = "2004May28"; }
    if('9/7/04' === $row['Name']) { $row['Name'] = "2004Sep07"; }


    /* Simple Attempt -- Does the string conver to our date format already? */
    $new_date = date('Y-m-d', strtotime($row['Name']));
    if($new_date && $new_date !== $bad_date) {
        update($new_date, $row['CollectionObjectID']);
        $success_count++;
        continue;
    }

    /* Our Main Date Case
     * Format: YYYY{mon}DD or YYYY{mon}D
     */
    if( (strlen($row['Name']) >= 8) && (FALSE === strpos($row['Name'], '-')) ) {

        $year  = substr($row['Name'], 0, 4);
        $month = substr($row['Name'], 4, 3);
        $day   = substr($row['Name'], 7);

        // If single digit day we pad with a 0 to keep consistent format
        if(strlen($day) == 1) { $day = "0".$day; }

        // If our string parsing worked -- let's format a new date.
        if(is_numeric($year) && is_numeric($day) && !is_numeric($month)) {
            $new_date = date('Y-m-d', strtotime($year."-".$month."-".$day));

            // If our date format worked -- let's insert into CatalogedDate
            if($new_date && $new_date !== $bad_date) {
                update($new_date, $row['CollectionObjectID']);
                $success_count++;
            } else {
                // If we can't format the date, move Name to CatalogedDateVerbatim
                update_verbatim($row['Name'], $row['CollectionObjectID']);
            }
        }
    } else {

        // Check for other legit cases
        $row_name = str_replace(array(' ', ','), array('-', ''), $row['Name']);
        $new_date = date('Y-m-d', strtotime($row_name));
        
        if( ($new_date && $new_date !== $bad_date) && (strlen($row_name) > 7) ) {
                update($new_date, $row['CollectionObjectID']);
                $success_count++;
        } else {

            // We check for partial dates, other wise store "as is" in CatalogedDateVerbatim
            if(strlen($row['Name']) == 7) {

                $year  = substr($row['Name'], 0, 4);
                $month = substr($row['Name'], 4, 3);           
                $date_month = date('m', strtotime($month));
               
                $partial_date = $year."-".$date_month."-00";
            } else if (strlen($row['Name']) == 4) {
               $partial_date = $row['Name']."-00-00";
            } else {
                $partial_date = $row['Name'];
            }

            update_verbatim($partial_date, $row['CollectionObjectID']);
        }
    }
}

// Results Reporting
error_log('script complete!');
error_log('total updates: '.$success_count);

mysql_db::factory()->close();

function update($new_date, $object_id) {

    $update_sql = <<<EOSQL
UPDATE collectionobject SET CatalogedDate = '{$new_date}' WHERE CollectionObjectID = {$object_id}
EOSQL;

    $update_result = mysql_db::query($update_sql, true);
    if(!empty($update_result)) {
        error_log(print_r($update_result,1));
        die();
    }
}

function update_verbatim($orig_date, $object_id) {

    $update_sql = <<<EOSQL
UPDATE collectionobject SET CatalogedDateVerbatim = "{$orig_date}", CatalogedDate = NULL WHERE CollectionObjectID = {$object_id}
EOSQL;

    $update_result = mysql_db::query($update_sql, true);
    if(!empty($update_result)) {
        error_log(print_r($update_result,1));
        die();
    }

}


