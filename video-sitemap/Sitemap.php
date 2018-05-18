<?php

require_once 'XmlWrapper.php';
require_once '../mgmtapi/jwapi.php';
require_once 'JWDB.php';

// Set Constants here for the Feed
//
define ('BASE_PATH', 'Sitemap');
define ('LAST_UPDATED_FN', 'lastUpdated.txt');
define ('RSS_EXT', '.xml');
define ('DB_NAME', 'SitemapDB.db');  // The SQLITE3 Database to use
define ('TBL_NAME', 'DB_Titles'); // Table name in Database
define ('PROP_KEY', '');
define ('PROP_SECRET', '');

/*
 * Return the time this process was last run (UNIX timestamp format)
 *
 * @return integer
 */
function readLastRun() {
    if (file_exists(LAST_UPDATED_FN)) {
        // Read the integer
        //
        return intval(file_get_contents(LAST_UPDATED_FN));
    } else {
        // File does not exist.  Time is in UNIX timestamp format so just return 0
        //
        return 0;
    }
}
/*
 * Write out the time this process was run
 *
 * @param none
 * @return none
 */
function writeLastRun() {
    // Write out current UNIX time as a string
    //
    file_put_contents(LAST_UPDATED_FN, strval(time()));
}

/*
 * Set the XML header to the document
 *
 * @param none
 * @return none
 */
function writeHeader() {
    global $XmlWrapper;
        
    $XmlWrapper->writeAttribute ('xmlns' , 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $XmlWrapper->writeAttributeNS ('xmlns' , 'video' , null, 'http://www.google.com/schemas/sitemap-video/1.1');
}

/*
 * Create the Video Element and it's sub elements
 *
 * @param (video) Associative array of video information
 * @return none
 */
function writeItem($video) {
    global $XmlWrapper;
    
    $XmlWrapper->startElement('video:video');

    $XmlWrapper->setElement('video:title', $video['title']);
    $XmlWrapper->setElement('video:description', $video['description']);    
    $XmlWrapper->setElement('video:duration', $video['duration']);  
    $XmlWrapper->setElement('video:publication_date', gmdate("Y-m-d\TH:i:s\Z", $video['published']));
    $XmlWrapper->setElement('video:thumbnail_loc', $video['previewUrl']);
    $XmlWrapper->setElement('video:view_count', $video['views']);    
    $XmlWrapper->setElement('video:content_loc', $video['contentUrl']);
    
    $XmlWrapper->endElement();
}

/*
 * Write out the entire document to a file
 *
 * @param none
 * @return none
 */
function writeDocument() {
    global $XmlWrapper;
    
    // Get the document as a string and write it out
    //
    $doc = $XmlWrapper->getDocument();
    file_put_contents(BASE_PATH  . RSS_EXT, $doc);
}

/*
 * Process each video in the Database and write out the XML
 *
 * @param none
 * @return none
 */
function writeFeed($max = 500000) {
    global $db, $XmlWrapper;

    $cnt = 0;
    $lastUrl = '';
    
    $total = $db->getNumRows(TBL_NAME);
    printf("Writing Feed of {$total} Items\n");

    $XmlWrapper = new XmlWrapper('urlset'); 

    // Write the XML header
    //
    writeHeader();
    
    // Get the videos from the database and order by the pageurl so they are 
    // grouped together
    //
    $command = 'SELECT * from ' . TBL_NAME . ' ORDER BY pageurl ';
                
    printf("Retrieving videos: %s\n", $command);
    $result = $db->query($command);
        
    // Fetch each row from the DB query response
    //
    while (($row = $result->fetchArray(SQLITE3_ASSOC)) && ($cnt < $max)) {  
        
        // Items are grouped by pageUrls
        // If this is a new pageUrl, start a new 'url' element
        //
        if ($row['pageurl'] != $lastUrl) {
            if ($lastUrl !== '') {
                $XmlWrapper->endElement();
            }
            $XmlWrapper->startElement('url');
            $XmlWrapper->setElement('loc', $row['pageurl']);
        }
        // Write out the video item
        //
        writeItem($row);
        $cnt++;
        $lastUrl = $row['pageurl'];
    }
    // Write the last url element
    //
    if ($lastUrl !== '') {
        $XmlWrapper->endElement();
    }
    
    printf("{$cnt} videos were read from the datbase, writing out the document\n");
    
    // Write the XML document from memory to a file
    //
    writeDocument();    

}
/*
 * Use the deliveryApi to get the thumbnail image URL and content Url
 *
 * @param (id) JW Media Id,
 * @param (record) Associative information about the video
 * @return none
 */
function getDeliveryInfo($id, &$record) {
    global $jwapi;
    
    // Create the URL
    //
    $url = "http://cdn.jwplayer.com/v2/media/" . $id;
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_URL, $url);
    $response = json_decode(curl_exec($curl), TRUE);
    curl_close($curl);

    // Get the Preview Image URL and HLS Manifest URL
    //
    if (array_key_exists('playlist', $response)) {

        $record['previewUrl'] = $response['playlist'][0]['image'];

        // Iterate over the 'sources' array to get the HLS manifest URL
        //
        $sources = $response['playlist'][0]['sources'];
        foreach ($sources as $source) {
            if ($source['type'] === 'application/vnd.apple.mpegurl') {
                $record['contentUrl'] = $source['file'];
                return;
            }
        }
    }
    return '';
}

/*
 * Process a list of videos returned by the videos/list call
 *
 * @param (videos) Associate array with information about the videos
 * @return none
 */
function processVideos($videos) {
    global $db;
    
    printf("Processing %d videos\n", count($videos));
    foreach ($videos as $video) {    
        
        // Only include if the link field exists in the JW Platform
        //
        if (strlen($video['link']) > 0) {
            $record = array();
            $record['title'] = $video['title'];
            $record['description'] = $video['description'];
            $record['duration'] = $video['duration'];
            $record['pageurl'] = $video['link'];
            $record['published'] = $video['date'];
            getDeliveryInfo($video['key'], $record);
            $record['views'] = $video['views'];

            // If the video key already exists, just update it, otherwise
            // create a new record for the video with the given key
            //
            if ($db->keyExists(TBL_NAME, $video['key'])) {
                // Update it
                $response = $db->updateRow(TBL_NAME, $video['key'], $record);
            } else {
                // Create a new record with the video key
                //
                $record['jwId'] = $video['key'];
                $response = $db->insertArray(TBL_NAME, $record);
            }
        }
    }
}
/*
 * Retrieve a list of videos from the JW Platform.  Only retrieve videos
 * that have been updated since the last time this script was run
 *
 * @param (lastUpdated) Time/Date when this script was last run
 * @return none
 */
function getVideos($lastUpdated) {
    global $jwapi;
    $max = 200000;  // Set this low for testing, high for production
    $total = 0;
    $limit = 200;   // how many videos to retrieve at a time, max is 1000
    
    printf("Retrieving Videos\n");
    
    // Retrieve videos and add to table
    //
    do {
        // Sorting by last modified
        //
        $params = array('result_offset' => $total, 'result_limit' => $limit,
            'order_by' => 'updated:desc',
            'updated_after' => $lastUpdated);

        var_dump($params);        
        $response = $jwapi->call("/videos/list", $params);
        printf("getVideos:  Status of /videos/list: %s\n", $response['status']);
        if ($response['status'] != 'ok') {
            printf('ERROR: getVideos returned bad status');
            
            // TODO is there a message
            //
            return false;
        } else {
            if (count($response['videos']) > 0) {
                processVideos($response['videos']);
                $total = $total + count($response['videos']);
            } else {
                // No more videos to retrieve
                break;
            }
        }
        // Keep going til the total is greater than the total available.
    } while ($total < $max);
    printf("%d Videos were retrieved\n", $total);
    return $total;
}

/*
 * MAIN 
 * 
 */


/* Initialize the JW API with your api key and secret
*/ 
$jwapi = new JWAPI(PROP_KEY, PROP_SECRET);


/* Get time of last run.  If not 0, subtract 1 hour of seconds as a buffer.
*/
$lastUpdated = readLastRun();
if ($lastUpdated > 0) {
    $lastUpdated = $lastUpdated - (3600);
}
// $lastUpdated = 0;  Uncomment this to force a complete refresh

/* Write out time of this run
*/
writeLastRun();

/* Check existance of Database and Table
*/
$db = new JWDB(DB_NAME);
if (!$db->tableExists(TBL_NAME)) {
    printf("Creating new table\n");
    $db->newTable(TBL_NAME);
} else {
    printf("Table already exists\n");
}

/*
 * This script works as follows:
 *    There is a local sqlite3 database and table which keeps information about the
 *    videos in the JW account.  The getVideos function will find all of the videos
 *    in the JW Platform that have been updated since the last time that this
 *    script was run.  Thus, the database is kept in sync the videos in the JW
 *    Platform.  Once the database is updated, a query is run to get all of the videos
 *    grouped by the page Url and the feed is written out.
 * 
 *    For multiple properties, you'll want to use a different TABLE
 */
getVideos($lastUpdated);
writeFeed();  // Maximum number of videos

?>
