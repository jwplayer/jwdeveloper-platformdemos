#!/usr/bin/env python
# -*- coding: utf-8 -*-

import json
import logging
import os
import time

import jwplatform


def sync_local_library(path_to_local_library=None, updated=None):
    """
    Function which serves as entry point for synchronizing local a local system *cache* of a videos list call.

    :param path_to_local_library: <str> Local system path to "library" file
    :param updated: <int> Integer time stamp which filters the video list API call for videos updated AFTER that time.
    :return: <int> Time at which synchronization has finished.
    """
    local_library = dict()
    path_to_local_library = path_to_local_library or os.path.join(os.getcwd(), 'video_list.json')

    # If local library file exists read it into memory.
    if os.path.exists(path_to_local_library):
        with open(path_to_local_library, 'r') as fd:
            local_library = json.load(fd)

    videos = fetch_video_list(api_key=os.getenv('API_KEY'), api_secret=os.getenv('API_SECRET'), updated=updated)

    # Map video_key->video_metadata locally
    local_library = {video.get('key'): video for video in videos}

    # Dump updated library to file
    with open(path_to_local_library, 'w+') as fd:
        json.dump(local_library, fd)

    return int(time.time())


def fetch_video_list(api_key=None, api_secret=None, result_limit=1000, **kwargs):
    """
    Function which fetches a video library and writes each video_objects Metadata to CSV. Useful for CMS systems.

    :param api_key: <string> JWPlatform api-key
    :param api_secret: <string> JWPlatform shared-secret
    :param result_limit: <int> Number of video results returned in response. (Suggested to leave at default of 1000)
    :param kwargs: Arguments conforming to standards found @ https://developer.jwplayer.com/jw-platform/reference/v1/methods/videos/list.html
    :return: (<list> of Dictionaries which represents the JSON response., <int> time last updated)
    """

    timeout_in_seconds = 2
    max_retries = 3
    retries = 0
    offset = 0
    videos = list()

    jwplatform_client = jwplatform.Client(api_key, api_secret)
    logging.info("Querying for video list.")

    # If updated is None, this implies we desire to fetch the entire customers library.
    # Therefore, we remove updated from kwargs, which in turn removes it from the API call. Which result in fetching the
    # entirety of the customers collection.
    if kwargs.get('updated') is None:
        del kwargs['updated']

    while True:
        try:
            response = jwplatform_client.videos.list(result_limit=result_limit,
                                                     result_offest=offset,
                                                     **kwargs)
        except jwplatform.errors.JWPlatformRateLimitExceededError:
            logging.error("Encountered rate limiting error. Backing off on request time.")
            if retries == max_retries:
                raise jwplatform.errors.JWPlatformRateLimitExceededError()
            timeout_in_seconds *= timeout_in_seconds  # Exponential back off for timeout in seconds. 2->4->8->etc.etc.
            retries += 1
            time.sleep(timeout_in_seconds)
            continue
        except jwplatform.errors.JWPlatformError as e:
            logging.error("Encountered an error querying for videos list.\n{}".format(e))
            raise e

        # Reset retry flow-control variables upon a non successful query (AKA not rate limited)
        retries = 0
        timeout_in_seconds = 0

        # Add all fetched video objects to our videos list.
        videos.extend(response.get('videos', []))
        last_query_total = response.get('total', 0)
        if last_query_total < result_limit:  # Condition which defines you've reached the end of the library
            break
        offset += last_query_total
    return videos


SLEEP_TIME = 10
while 1:
    last_updated = sync_local_library()
    print(time.time())
    time.sleep(SLEEP_TIME)
