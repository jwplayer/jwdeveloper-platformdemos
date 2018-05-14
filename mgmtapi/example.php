<?php

require_once 'jwapi.php';

/* 
 * Test the use of the JWAPI class
 */
$key = '';
$secret = '';

if ($key === '' || $secret === '') {
    printf("JW Key and Secret must be set in code of example.php\n");
    return;
}

// Initialize the API
//
$jwapi = new JWAPI($key, $secret);

// Make a videos list call directly to the API
//
$response = $jwapi->call('/videos/list', ['result_limit' => 10]);
if (array_key_exists('videos', $response)) {
    $cnt = 1;
    foreach ($response['videos'] as $video) {
        printf("%3d. %s: %s\n", $cnt, $video['key'], $video['title']);
        $cnt++;
    }
}

// Make a videos list call using the video class
//
$list = $jwapi->getVideos(10);
if (!$list) {
    printf("Unable to retrieve any videos.\n");
    return;
}
$list->reset();
printf("Processing %d Videos\n", $list->count());
$cnt = 1;
while ($video = $list->Next()) {
    printf("%3d. %s: %s\n", $cnt, $video->key, $video->info['title']);
    $cnt++;    
}



