<?php

require_once 'analyticsApiWrapper.php';

/* Usage php example.php [jwPropertyKey] [jwAnalyticsKey]
 */
if (count($argv) != 3) {
    printf("wrong number of args: php example.php <key> <secret>\n");
    return;
}

/* Create the wrapper
 */
$key = $argv[1];
$secret = $argv[2];
$apiWrapper = new AnalyticsApiWrapper($key, $secret);

/* Create the body for the query
 */
$pageLen = 100;
$body = new stdClass();
$body->start_date = '2018-02-01';
$body->end_date = '2018-02-02';
$body->page_length = $pageLen;  // results per page
$body->dimensions = ['media_id'];   
//$body->include_metadata = 1;   // Can be used to pull information about videos
$body->filter = [
        ["field" => "country_code", "operator" => "=", "value" => ["US"]],
        ["field" => "platform_id", "operator" => "=", "value" => ["web"]]
];    
$body->metrics = [
    ["operation" => "sum", "field" => 'embeds'],
    ["operation" => "sum", "field" => 'plays'],
    ["operation" => "sum", "field" => 'completes'],
];
if (!$apiWrapper->setBody($body)) {
    printf("Set body failed\n");
    die(0);
}

/* Create the CSV file to output the report
 */
$fpCSV = fopen('apireport.csv', 'w');
if ($fpCSV === FALSE) {
    printf("Unable to create CSV Output file\n");
    die(0);
}

$wroteHeader = false;
$page = 0;
while ($page >= 0) {

    /*    
     * The returned value is an associative array with the following properties
     *   httpCode - Result of the HTTP request to the API
     *   requestLimit - Number of allowable requests per minute
     *   requestRemaining - Number of requests left in the current minute
     *   dimensions - An array of the returned dimension names
     *   metrics - An array of the returned metric names
     *   data - An array of the data.
     *   metadata - any metadata returned with the query
     */
    $response = $apiWrapper->runQuery($page);
    printf("Page: %d. HTTP Code: %d, Limit: %d, Remaining: %d\n", 
            $page, $response['httpCode'], $response['requestLimit'], 
            $response['requestRemaining']);
    if ($response['httpCode'] == 200) {
        printf("Page: %d.  Result Count: %d\n",
                $page, count($response['data']));
        $numColumns = 0;
        
        if (!$wroteHeader) {
            /* Write the Header row for the CSV.  The dimension fields
             * come first, then the metrics.
             */
            $fields = [];
            foreach ($response['dimensions'] as $dimension) {
                $fields[] = $dimension['field'];
            }
            foreach ($response['metrics'] as $metric) {
                $fields[] = $metric['field'];
            }
            fputcsv($fpCSV, $fields);
            $wroteHeader = true;            
            $numColumns = count($fields);
        }
        
        /* This is where the returned data would be processed.
         * Just write the row to the CSV
         */
        foreach ($response['data'] as $row) {
            fputcsv($fpCSV, $row);
        }
        
        /* If number of rows returned is less than the number of expected rows
         * set the page to -1 so loop exits, otherwise increase the page number
         */
        if (count($response['data']) < $pageLen) {
            $page = -1;
        } else {
            $page++;  // get the next page
        }
    } else {
        printf("Response returned HTTP code: %s\n", $response['http_code']);
        var_dump($response);
        $page = -1;
    }
    break;
}
fclose($fpCSV);




