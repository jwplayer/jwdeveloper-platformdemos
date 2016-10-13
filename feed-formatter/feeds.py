# -*- coding: utf-8 -*-
import jinja2
import requests
import datetime
import os

baseUrl = 'http://content.jwplatform.com/feeds/%s.json'
template_dir = "%s/feed_templates" % os.path.dirname(os.path.realpath(__file__))
loader = jinja2.FileSystemLoader(template_dir)
environment = jinja2.Environment(loader=loader)


def get(key=''):
    """

    :param key: <string> Key of playlist/feed to retrieve
    :return: <dict> JSON response
    """
    r = requests.get(baseUrl % key)
    if r.status_code == 200:
        return r.json()
    else:
        raise Exception('Unable to fetch resource')


def parse(json={}):
    """

    :param json: <dict> JSON response from jwplatform.com/feeds
    :return: <dict> With appropriate JSON
    """
    clean = {}
    items = []

    for video_object in json.get('playlist', []):
        best = {'width': 0, 'url': ''}
        pubdate = datetime.datetime.utcfromtimestamp(video_object.get('pubdate'))

        # Find highest quality candidate
        candidates = filter(lambda x: x.get('type') == 'video/mp4', video_object.get('sources', []))
        highest_quality_candidate = max(candidates, key=lambda x: x.get('width', -2147483648))
        best['url'] = highest_quality_candidate.get('file')
        best['width'] = highest_quality_candidate.get('width')

        # Construct item level dict, includes custom parameters from item level and NOT custom block level
        item_level_ignored_fields = ['sources', 'tracks', 'custom']
        item = {k: v for k, v in video_object.items() if k not in item_level_ignored_fields}
        item['date_utc'] = pubdate.strftime('%Y-%m-%d %H:%M:%S')
        item['date_rss'] = pubdate.strftime("%a, %d %b %Y %H:%M:%S %z")

        items.append(item)

    clean['items'] = items

    # Extract all metadata fields a.k.a things that are not the 'playlist' field.
    # Note this includes feed-level custom parameters
    feed_level_ignored_fields = ['playlist']
    clean['metadata'] = {k: v for k, v in json.items() if k not in feed_level_ignored_fields}
    return clean


def toXML(json={}, template_name='standard'):
    """

    :param json: <dict> Key:Value pairs to be injected
    :param template_name: <string> Represents a particular template format found within feed_templates/
    :return: <string> A string which represents the XML to returned in the response
    """
    try:
        template = environment.get_template(template_name)
        rendered = template.render(**json)
    except:
        raise Exception('Unable to render: %s' % template_name)
    else:
        return rendered


def process(key='', template_name='standard'):
    """

    :param key: <string> Represents feed/playlist id
    :param template_name: <string> Represents a particular template format found within feed_templates/
    :return: <string> Rendered XML string with appropriate Key:Values injected into it.
    """
    try:
        data = get(key)
        parsed = parse(data)
    except:
        raise Exception('Unable to fetch and parse feed')
    else:
        rendered = toXML(parsed, "%s.xml" % template_name)
        return rendered
