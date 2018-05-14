<?php
define('API_ROUTE', 'https://api.jwplayer.com/v2/sites/');
/* 
 * Wrapper Class for accessing the JW Analytics API
 */
class AnalyticsApiWrapper
{ 

    private $_jwKey, $_jwSecret;
    private $_requestHeaders;
    private $_queryBody;
    private $_apiUrl;
    private $_response;
    private $_queryType;

    /**
     * Constructor: 
     * 
     * @param type $key     -- JW Property Key
     * @param type $secret  -- JW Analytics API Secret 
     */
    public function __construct($key, $secret, $type = 'json') {
        $this->_jwKey = $key;
        $this->_jwSecret = $secret;

        /* Setup the URL and Headers for the CURL call
         */
        $this->_apiUrl = API_ROUTE . $this->_jwKey . '/analytics/queries?format=' . $type;
        $this->_requestHeaders = [];
        $this->_requestHeaders[] = 'Authorization: ' . $this->_jwSecret;
        $this->_requestHeaders[] = 'Content-Type: application/json';
        $this->_queryType = $type;
    } 
    
    /**
     * setBody -- Save the query body
     * 
     * @param type $body
     * @return True if body meets minimum spec, false otherwise
     */
    public function setBody($body) {
        /* Minimum is startDate, endDate, 1 dimension and 1 metric
         */
        if (!isset($body->start_date)) {
            printf("startDate not set\n");
            return false;
        }
        if (!isset($body->end_date)) {
            printf("endDate not set\n");            
            return false;
        }
        if (!isset($body->dimensions)) {
            printf("dimensions not set\n");                        
            return false;
        }
        if (!isset($body->metrics)) {
            printf("metrics not set\n");                        
            return false;
        }
        $this->_queryBody = $body;        
        return true;
    }
    
    /**
     * runQuery - public function that executes the API query for a single page
     * 
     * @param type $pageNum
     * @return associative array with the following properties
     *      httpCode, requestLimit, requestRemaining, dimensions, metrics, data
     * 
     */
    public function runQuery($pageNum) {
        $this->_queryBody->page = $pageNum;
        $this->_response = [];        
        $this->sendPost();
        
        return $this->_response;
    }
    
    /**
     * processBody - Decode the response to the query and set the response array
     * 
     * @param type $body
     * @return none
     */
    private function processBody($body) {
        if ($this->_queryType == 'json') {
            /* Turn the response body into a PHP Array
             */
            $json = json_decode($body, true);
            if ($json === NULL || $json === FALSE) {
                $this->_response['errormsg'] = 'Cannot decode body of response';
                print_r($body);
                return;
            }

            /* The response should have a metadata and column headers 
             * field if it was valid
             */
            if (!array_key_exists('metadata', $json)) {
                $this->_response['errormsg'] = "Bad Response: no 'metadata' field.";
                return;
            } else if (!array_key_exists('column_headers', $json['metadata'])) {
                $this->_response['errormsg'] = "No Column Headers specified";
                return;
            }

            /* Get the DIMENSION, METRIC, and DATA Specifiers
             */
            $this->_response['dimensions'] = $json['metadata']['column_headers']['dimensions'];
            $this->_response['metrics'] = $json['metadata']['column_headers']['metrics'];
            $this->_response['data'] = $json['data']['rows'];
            if (array_key_exists('includes', $json)) {
                $this->_response['metadata'] = $json['includes'];
            } else {
                $this->_response['metadata'] = null;
            }
        } else {
            printf("Format of body is not JSON\n");
            $this->_response['data'] = $body;
        }
    }
    
    /**
     * sendPost - Setup the CURL request and execute the API call
     */
    private function sendPost() {

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $this->_apiUrl);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->_requestHeaders);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true); // enable tracking    

        /* Anonymous function is called by curl for each header received
         * This is to retrieve the JW API Limits information
         *        curl_setopt($curl, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$respHeaders, &$jwRequestLimit, &$jwRequestRemaining) {
         */
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, function($curl, $header) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) // ignore invalid headers
                return $len;

            $name = strtolower(trim($header[0]));
            if ($name === 'jw-request-limit') {
                $this->_response['requestLimit'] = intval(trim($header[1]));
            } else if ($name === 'jw-request-remaining') {
                $this->_response['requestRemaining'] = intval(trim($header[1]));
            }
            return $len;
        });

        /* JSON encode the body
         */
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($this->_queryBody));

        /* Execute the query
         */
        $body = curl_exec($curl);
        $err_no = curl_errno($curl);
        $err_msg = curl_error($curl);

        /* Set the http code
         */
        $this->_response['httpCode'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($this->_response['httpCode'] == 200) {
            $this->processBody($body);
        }
    }
}


