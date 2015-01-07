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
$today = date('Y-m-d');

$sql = <<<EOSQL
SELECT DeterminationID, Text1, DeterminedDate
FROM determination
WHERE Text1 IS NOT NULL
EOSQL;

$result = mysql_db::query($sql);
error_log('row count: '.count($result));

foreach($result as $row) {

    //one off fix for 2 digit years
    $one_off = FALSE;
    if('3.17.11'    === $row['Text1']) { $row['Text1'] = '2011-03-17'; $one_off = true; }
    if('1.28.11'    === $row['Text1']) { $row['Text1'] = '2011-01-28'; $one_off = true; }
    if('1.28.2011'  === $row['Text1']) { $row['Text1'] = '2011-01-28'; $one_off = true; }
    if('2011Sept30' === $row['Text1']) { $row['Text1'] = '2011Sep30'; $one_off = true; }
    if('9.30.2011'  === $row['Text1']) { $row['Text1'] = '2011Sep30'; $one_off = true; }
    if('9.6.11'     === $row['Text1']) { $row['Text1'] = '2011-09-06'; $one_off = true; }
    if('2011Sept06' === $row['Text1']) { $row['Text1'] = '2011-09-06'; $one_off = true; }
    if('10.6.2011'  === $row['Text1']) { $row['Text1'] = '2011Oct06'; $one_off = true; }
    if('10.6.11'    === $row['Text1']) { $row['Text1'] = '2011Oct06'; $one_off = true; }
    if('1.26.2012'  === $row['Text1']) { $row['Text1'] = '2012-01-26'; $one_off = true; }
    if('1.31.11'    === $row['Text1']) { $row['Text1'] = '2011-01-31'; $one_off = true; }
    if('10.13.2011' === $row['Text1']) { $row['Text1'] = '2011-10-13'; $one_off = true; }
    

    $row['Text1'] = str_replace(array(' ', '.'), '-', $row['Text1']);
    
    /*Simple Attempt -- Does the string conver to our date format already? */
    $new_date = date('Y-m-d', strtotime($row['Text1']));

    if( ($new_date && $new_date !== $bad_date) && !$one_off && $new_date !== $today) {

        // Regex for partial dates
        $year_month_result = preg_match('/^\d\d\d\d-(\d)?\d-01/', $row['DeterminedDate']); 
        
        if(strlen($row['Text1']) == 4 || $row['DeterminedDate'] == $today || $year_month_result === 1) {
            unset_determined_date($row['DeterminationID']);
        } else {
            update($new_date, $row['DeterminationID']);
            $success_count++;
        }

        continue;
    }

    /* Our Main Date Case
     * Format: YYYY{mon}DD or YYYY{mon}D
     */
    if( (strlen($row['Text1']) >= 8) && (FALSE === strpos($row['Text1'], '-')) ) {

        $year  = substr($row['Text1'], 0, 4);
        $month = substr($row['Text1'], 4, 3);
        $day   = substr($row['Text1'], 7);

        // If single digit day we pad with a 0 to keep consistent format
        if(strlen($day) == 1) { $day = "0".$day; }

        // If our string parsing worked -- let's format a new date.
        if(is_numeric($year) && is_numeric($day) && !is_numeric($month)) {
            $new_date = date('Y-m-d', strtotime($year."-".$month."-".$day));

            // If our date format worked -- let's insert into CatalogedDate
            if($new_date && $new_date !== $bad_date) {
                update($new_date, $row['DeterminationID']);
                $success_count++;
            } 
        }
    } else {

        // Check for other legit cases
        $row_name = str_replace(array(' ', '.', ','), array('-', '-', ''), $row['Text1']);
        $new_date = date('Y-m-d', strtotime($row_name));
        

        if( ($new_date && $new_date !== $bad_date) && (strlen($row_name) > 7) ) {
                update($new_date, $row['DeterminationID']);
                $success_count++;
        } 
    }
}

// Results Reporting
error_log('script complete!');
error_log('total updates: '.$success_count);

mysql_db::factory()->close();

function update($new_date, $object_id) {

    $update_sql = <<<EOSQL
UPDATE determination SET DeterminedDate = '{$new_date}' WHERE DeterminationID = {$object_id}
EOSQL;

    $update_result = mysql_db::query($update_sql, true);
    if(!empty($update_result)) {
        error_log(print_r($update_result,1));
        die();
    }
}

function unset_determined_date($object_id) {

    $update_sql = <<<EOSQL
UPDATE determination SET DeterminedDate = NULL WHERE DeterminationID = {$object_id}
EOSQL;

    $update_result = mysql_db::query($update_sql, true);
    if(!empty($update_result)) {
        error_log(print_r($update_result,1));
        die();
    }
}

