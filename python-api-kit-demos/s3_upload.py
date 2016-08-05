#!/usr/bin/env python
# -*- coding: utf-8 -*-

import logging
import urllib
import os
import os.path
import pycurl

from botr.api import API

logging.basicConfig(level=logging.INFO)

def upload_video(api_key, api_secret, video_file):

    # setup api
    api = API(api_key, api_secret)

    # build parameters for the /videos/create API call
    params = {
        'title': os.path.basename(video_file),
        'upload_method': 's3',
    }

    logging.info("creating video")
    response = api.call('/videos/create', params=params)
    logging.info(response)

    # construct base url for upload
    upload_url = '{}://{}{}'.format(
        response['link']['protocol'],
        response['link']['address'],
        response['link']['path']
    )

    # add query parameters to the upload url
    query_parameters = []
    for key, value in response['link']['query'].iteritems():
        query_parameters.append('{}={}'.format(
            urllib.quote_plus(key),
            urllib.quote_plus(unicode(value)),
        ))
    upload_url += '?' + '&'.join(query_parameters)


    # HTTP PUT uplad using curl
    filesize = os.path.getsize(video_file)
    with open(video_file, 'rb') as f:
        c = pycurl.Curl()
        c.setopt(pycurl.URL, upload_url)
        c.setopt(pycurl.UPLOAD, 1)
        c.setopt(pycurl.READFUNCTION, f.read)
        c.setopt(pycurl.INFILESIZE, filesize)
        logging.info('uploading file {} to url {}'.format(video_file, upload_url))
        c.perform()
        c.close()

if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser(description='Upload a file to JW Platform.')
    parser.add_argument('api_key', type=str,
                       help='The API Key of your JW Platform account.')
    parser.add_argument('api_secret', type=str,
                       help='The API Secret of your JW Platform account.')
    parser.add_argument('video_file', type=str,
                       help='The path and file name you want to upload. ex: path/file.mp4')
    args = parser.parse_args()
    upload_video(args.api_key, args.api_secret, args.video_file)
