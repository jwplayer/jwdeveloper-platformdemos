<?php

require_once '../../mgmtapi/jwapi.php';
require_once 'analyticsApiWrapper.php';

/* 
 * This example uses the JW MGMT API to get a list of videos with metadata
 * It then gets multiple analytics metrics to enrich the video information
 * The final output is a CSV with 1 row per video with the following information
 * 
 * Video Title
 * Video ID
 * Account Property Key
 * Publication Date
 * Duration
 * Type
 * Embeds
 * Total Plays
 * Completes
 * Unique Viewers
 * Plays to 25% of complete
 * Plays to 50% of complete
 * Plays to 75% of complete
 * Average time watched per viewer
 * Mobile Plays
 * Desktop Plays
 * Tablet Plays
 * Other Plays 
 */

/*
 * Utility function to format a date string
 */
function formatNow() {
    $dateTime = new DateTime(); 
    return $dateTime->format('m/d/y H:i:s');
}
/*
 * Function: processJsonResponse:
 *    Process the JSON response that is returned by the API Wrapper
 */
function processJsonResponse($response) {
    global $videoList;
    $found = 0;
    
    if ($response['httpCode'] != 200) {
        printf("API Responded with HTTP: %s\n", $response['httpCode']);
        return false;
    }
    // First dimension is always media_id
    //
    foreach ($response['dimensions'] as $dimension) {
        $dimensions[] = $dimension['field'];
    }
    
    // Save the metrics information
    //
    $metrics = [];
    foreach ($response['metrics'] as $metric) {
        $metrics[] = $metric['field'];
    }    
    
    // Process each returned Data point
    //
    foreach ($response['data'] as $row) {
        $mediaId = $row[0];
        $prefix = '';
        if (count($dimensions) > 1) {
            $prefix = $row[1] . '_';
        }
        $video = $videoList->Get($mediaId);
        if ($video != null) {
            $found++;

            for ($i = 0; $i < count($metrics); $i++) {
                $key = $prefix . $metrics[$i];
                $value = $row[$i + count($dimensions)];
                //printf("ID: %s, Key: %s, Value: %s\n", $mediaId, $key, $value);
                $video->stats[$key] = $value;
            }
        }
    }    
    if ($response['requestRemaining'] < 10) {
        printf("Getting close to request limit, sleeping\n");
        sleep(10);
    }
}
/*
 * Process a list of videos in CSV format
 */
function processCSVResponse($response) {
    global $videoList;
    
    printf("HTTP Code: %d, Limit: %d, Remaining: %d\n", 
            $response['httpCode'], $response['requestLimit'], 
            $response['requestRemaining']);
    if (!array_key_exists('data', $response)) {
        printf("No Data found in query\n");
        print_r($response);
        return;
    }
    
    $separator = "\r\n";
    $line = strtok($response['data'], $separator);
    
    // First line is the header row
    $headers = str_getcsv($line, ",");
    for ($i = 2; $i < count($headers); $i++) {
        $metrics[] = $headers[$i];
    }
    
    // Get first line of data
    $line = strtok( $separator );            
    $cnt = 0;
    while ($line !== false) {
        $cnt++;
        $row = str_getcsv($line, ",");        
        $id = $row[0];
        
        // Is the id in the video list
        //
        $video = $videoList->Get($id);
        if ($video != null) {
            $found++;
            printf("Found video with key: %s\n", $id);
            for ($i = 0; $i < count($metrics); $i++) {
                $video->stats[$metrics[$i]] = $row[$i+2];
            }
        } else {
            $notfound++;
        }
        $line = strtok( $separator );        
    }    
    printf("%d Videos were Found, %d Videos not found\n", $found, $notfound);
    
}
/*
 * Run the query using the apiWrapper
 * This may have to run multiple queries to retrieve multiple pages
 * of responses.
 */
function doQuery($apiWrapper) {
    global $pageLen, $maxPages;

    /* Run the analytics query
     */
    $page = 0;
    printf("Starting Query\n");
    
    while ($page >= 0 && $page < $maxPages) {
        printf("Running Query Page: %d\n", $page);
        $response = $apiWrapper->runQuery($page);
        if ($response['httpCode'] == 200) {
            processJsonResponse($response);    
            
            /* If number of rows returned is less than the number of expected rows
             * set the page to -1 so loop exits, otherwise increase the page number
             */
            if (count($response['data']) < $pageLen) {
                $page = -1;
            } else {
                $page++;  // get the next page
            }        
        } else {
            $page = -1;
        }
        
    }
}
/* 
 * Configure and Run an Analytics queries to retrieve multiple sets of metrics
 */
function getMetrics($key, $secret) {
    global $videoList, $pageLen;
    global $queryStartDate, $queryEndDate;
    
    printf("Running Query for metrics\n");
    $found = 0;
    $notfound = 0;
    
    /* Initialize the API helper
    */
    $apiWrapper = new AnalyticsApiWrapper($key, $secret);

    /* Create the body for the query
     */
    $body = new stdClass();
    $body->start_date = $queryStartDate;
    $body->end_date = $queryEndDate;
    
    $body->dimensions = ['media_id'];   
    $body->page_length = $pageLen;
    $body->page = 0;
    //$body->include_metadata = 1;   // Can be used to pull information about videos
    $body->metrics = [
        ["operation" => "sum", "field" => 'embeds'],
        ["operation" => "sum", "field" => 'plays'],
        ["operation" => "sum", "field" => 'completes'],
        ["operation" => "sum", "field" => 'unique_viewers'],        
    ];
    if (!$apiWrapper->setBody($body)) {
        printf("Set body failed\n");
        return false;
    }
    printf("Querying Embeds,playes,completes, unique_vieweres\n");
    doQuery($apiWrapper);
    

    // Run another query with the completes metrics
    $body->metrics = [
        ["operation" => "sum", "field" => '25_percent_completes'],
        ["operation" => "sum", "field" => '50_percent_completes'],
        ["operation" => "sum", "field" => '75_percent_completes'],
        ["operation" => "sum", "field" => 'time_watched_per_viewer'],        
    ];
    if (!$apiWrapper->setBody($body)) {
        printf("Set body failed\n");
        return false;
    }
    printf("Querying completes and average time watched\n");
    doQuery($apiWrapper);    
    
    // Run another query with the completes metrics
    $body->metrics = [
        ["operation" => "sum", "field" => 'plays']
    ];
    $body->dimensions = ['media_id', 'device_id'];       
    if (!$apiWrapper->setBody($body)) {
        printf("Set body failed\n");
        return false;
    }
    printf("Querying device id\n");
    doQuery($apiWrapper);
}
/* 
 * Retrieve metadata information for a list of videos
 */
function getVideos($propkey, $propsecret, $readCSV = null, $writeCSV = null) {
    
    if ($readCSV) {
        // Read in the videos from the CSV
        printf("Reading in videos from : %s\n", $readCSV);
        $fd = fopen($readCSV, "r");
        $list = new VideoList();
        $cnt = 0;
        while ($row = fgetcsv($fd)) {
            $item = ['key' => $row[0], 'propertykey' => $row[1], 
                'title' => $row[2], 'duration' => $row[3], 'date' => $row[4]];
            $key = $item['key'];
            $video = $list->Create($row[0], $item);
            $cnt++;
        }
        fclose($fd);
        printf("Lines read: %d, Count in List: %d\n",
                $cnt, $list->Count());
        
        
    } else {
        /* 
         * Using the API to get the video metadata
         */
        $jwapi = new JWAPI($propkey, $propsecret);
        
        // Make a videos list call using the video class
        //
        $list = $jwapi->getVideos(10000000);
        if (!$list) {
            printf("Unable to retrieve any videos.\n");
            return;
        }
        $list->reset();
        if ($writeCSV) {
            printf("Writing out Video info to %s\n", $writeCSV);                    
            $cnt = 0;
            $fd = fopen($writeCSV, "w");
            while ($video = $list->Next()) {
                fputcsv($fd, [$video->key, $propkey, $video->metadata['title'],
                    $video->metadata['duration'], $video->metadata['date'],
                    $video->metadata['tags']]);
                $cnt++;
            }
            fclose($fd);
        }
    } 

    return $list;

}

// This can work across multiple properties in an account
// jwcredentials is An Array of 'Property Key' => 'Property Secret'
//
$jwcredentials = ['<yourkey' => 'yoursecret']; 

// The analytics Token for the Account.  See JW Dashboard to retrieve API credentials
//
$analyticsAuth = 'yourtoken';

// Set to true if you want to retrieve all the videos in the property and write to a CSV
// Then for testing, rather than use the JW API for video information, the metadata can be read 
// from the local CSV
$writeVideos = false;

// Number of API data points returned in each query
$pageLen = 100;

// Maximum number of API requests per Query.  Set low for testing
$maxPages = 100000;

// Starting and ending date for the Report
//
$queryStartDate = '2018-02-01';
$queryEndDate = '2018-05-01';

// Modify for your timezone
//
date_default_timezone_set('America/New_York');

foreach ($jwcredentials as $propkey => $propsecret) {
    printf("Starting Property %s at %s, for period from %s to %s\n", 
            $propkey, formatNow(), $queryStartDate, $queryEndDate);
    // Comment this out if the Video list has not been written
    //$csvFilename = 'AMI-' . $propkey . '-Videos.csv';
    $csvFilename = null;
    
    // Simply do a run to write out all videos to a CSV.
    //
    if ($writeVideos) {
        printf("Getting a list of videos and writing to CSV\n");
        getVideos($propkey, $propsecret, null, $csvFilename);
    } else {
        printf("Listing Videos for property key: %s\n", $propkey);
        $videoList = getVideos($propkey, $propsecret, $csvFilename, null);
        getMetrics($propkey, $analyticsAuth);

        $videoList->reset();
        printf("Processing %d Videos\n", $videoList->count());
        $cnt = 1;
        $outputFn = 'AMI-' . $propkey . '-Report.csv';
        $fd = fopen($outputFn, 'w');
        /* Write the header row
         */
        fputcsv($fd, ['MediaId', 'PropertyKey', 'Title', 'PublishedDate', 'Duration',
            'TotalPlays',
            'Completes', 'UniqueViewers', 'Complete25%', 'Complete50%',
            'Complete75%', 'AvgTimeWatched', 'PhonePlays', 'DesktopPlays', 'TabletPlays',
            'OtherPlays']);
        while ($video = $videoList->Next()) {
            if ((count($video->stats) > 0) && 
                    (array_key_exists('plays', $video->stats)) &&
                    ($video->stats['plays'] > 0)) {            
                fputcsv($fd, [
                    $video->key, 
                    $propkey, 
                    $video->metadata['title'], 
                    gmdate("Y-m-d", $video->metadata['date']),
                    $video->metadata['duration'],
                    array_key_exists('plays', $video->stats) ? $video->stats['plays'] : '',
                    array_key_exists('completes', $video->stats) ? $video->stats['completes'] : '',                
                    array_key_exists('unique_viewers', $video->stats) ? $video->stats['unique_viewers'] : '',                                
                    array_key_exists('25_percent_completes', $video->stats) ? $video->stats['25_percent_completes'] : '',
                    array_key_exists('50_percent_completes', $video->stats) ? $video->stats['50_percent_completes'] : '',
                    array_key_exists('75_percent_completes', $video->stats) ? $video->stats['75_percent_completes'] : '',                
                    array_key_exists('time_watched_per_viewer', $video->stats) ? $video->stats['time_watched_per_viewer'] : '',                
                    array_key_exists('Phone_plays', $video->stats) ? $video->stats['Phone_plays'] : '',                                
                    array_key_exists('Desktop_plays', $video->stats) ? $video->stats['Desktop_plays'] : '',
                    array_key_exists('Tablet_plays', $video->stats) ? $video->stats['Tablet_plays'] : '',
                    array_key_exists('Other_plays', $video->stats) ? $video->stats['Other_plays'] : '']                    
                );

            }
            $cnt++;
        }
        fclose($fd);
        printf("%s: Completed Queries, wrote report to %s\n", formatNow(), $outputFn);        
    }

}

