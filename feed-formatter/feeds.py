# -*- coding: utf-8 -*-
import jinja2
import requests
import datetime
import os

baseUrl      = 'http://content.jwplatform.com/feeds/%s.json'
template_dir = "%s/feed_templates" % os.path.dirname(os.path.realpath(__file__))
loader       = jinja2.FileSystemLoader(template_dir)
environment  = jinja2.Environment(loader=loader)

def get(key=''):
	r = requests.get(baseUrl % key)
	if r.status_code == 200:
		return r.json()
	else:
		raise Exception('Unable to fetch resource')

def parse(json={}):
	clean = {}
	items = []
	items.append({
		'playlistTitle' 		: json.get('title'),
		'playlistDescription' 	: json.get('description'),
		'playlistFeedID'		: json.get('feedid')
	})
	for i in json.get('playlist', []):
		best = {'width': 0, 'url': '', 'duration': 0}
		pubdate = datetime.datetime.utcfromtimestamp(i.get('pubdate'))
		for s in i.get('sources', []):
			if s.get('width')    > best.get('width') and s.get('type') == 'video/mp4':
				best['url']      = s.get('file')
				best['width']    = s.get('width')
				best['duration'] = s.get('duration')
		items.append({
			'key'  : i.get('mediaid'),
			'title': i.get('title'),
			'description': i.get('description'),
			'date' : i.get('pubdate'),
			'date_utc': pubdate.strftime('%Y-%m-%d %H:%M:%S'),
			'date_rss': pubdate.strftime("%a, %d %b %Y %H:%M:%S GMT"),
			'mediaurl': best.get('url'),
			'duration': best.get('duration'),
			'image': i.get('image'),
			'link': i.get('link'),
			'tags': i.get('tags', ''),
			'custom': i.get('custom', {}),
		})
	clean['items'] = items
	return clean

def toXML(json={}, template_name='standard'):
	try:
		template = environment.get_template(template_name)
		rendered = template.render(**json)
	except:
		raise
		exit()
		raise Exception('Unable to render: %s' % template_name)
	else:
		return rendered

def fetchParse(key=''):
	try:
		data = get(key)
		parsed = parse(data)
	except:
		raise Exception('Unable to fetch and parse feed')
	else:
		return parsed

def process(key='', template_name='standard'):
	try:
		data = get(key)
		parsed = parse(data)
	except:
		raise Exception('Unable to fetch and parse feed')
	else:
		rendered = toXML(parsed, "%s.xml" % template_name)
		return rendered
