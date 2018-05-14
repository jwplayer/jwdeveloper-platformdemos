# -*- coding: utf-8 -*-

#
# Python 3.6, argparse
# Submits query to JW-Player data-retrieval URL
# Dumps data into CSV file
#
# Usage: python <PYTHON_MODULE> --site-id <SITE_ID> --authorization <AUTHORIZATION>
#

import argparse
import json
import requests
import shutil
import sys

from datetime import datetime

# Report configurations
_REPORT_NAME = 'your-favorite-jw-report' # Report name, will be used as prefix for the CSV file
# Available dimensions: [ 'ad_schedule_id', 'city', 'country_code', 'device_id', 'page_domain',
#                         'eastern_date', 'is_first_play', 'media_id', 'tag', 'player_id', 'playlist_id',
#                         'playlist_type', 'play_reason', 'promotion', 'region', 'platform_id', 'page_url',
#                         'video_duration', 'date'
#                       ]
# Available aggregations: [ 'max', 'min', 'sum'
#                         ]
# Available metrics: [ 'ads_per_viewer', 'ad_clicks', 'ad_completes', 'ad_impressions', 'ad_skips',
#                      'completes', 'complete_rate', 'embeds', 'plays', 'plays_per_viewer', 'play_rate',
#                      '25_percent_completes', '50_percent_completes', '75_percent_completes',
#                      'time_watched', 'time_watched_per_viewer', 'unique_viewers'
#                    ]
# Available sorts: [ 'ASCENDING', 'DESCENDING'
#                  ]
_REPORT_QUERY = { # Query to be executed
	'start_date': '2018-02-01',
	'end_date'  : '2018-03-15',
	'dimensions': [
		'platform_id'
	],
	'metrics': [
		{
			'operation': 'sum',
			'field': 'plays'
		}
	],
	'sort': [
		{
			'field': 'plays',
			'order': 'DESCENDING'
		}
	],
	'include_metadata': 1 # 1 to include meta-data, 0 not to
}

parser = argparse.ArgumentParser(usage='usage: --site-id <SITE_ID> --authorization <AUTHORIZATION>')
parser.add_argument('--site-id', dest='site_id',
					type=str, required=True,
					help='Your site ID')
parser.add_argument('--authorization', dest='authorization',
					type=str, required=True,
					help='Your HTTP authorization ID')
args = parser.parse_args()

print('Submits query')
response = requests.post('https://api.jwplayer.com/v2/sites/' + args.site_id + '/analytics/queries/?format=csv', # JW-Player data-retrieval URL
						 stream=True,
						 headers={ 'Authorization': args.authorization }, # Indicates your clearance
						 data=json.dumps(_REPORT_QUERY)) # Passes the query specified above
print('Got response')
if response.status_code != 200:
	error = json.loads(response.text)
	print("Wasn't able to download report due to: "+str(error))
	sys.exit(1)
file_name = _REPORT_NAME + '-' + datetime.today().strftime('%Y%m%d') + '.csv'
print('Creates file: '+file_name)
with open(file_name, 'wb') as out_file:
    shutil.copyfileobj(response.raw, out_file)
print('Wrote response to file: '+file_name)

