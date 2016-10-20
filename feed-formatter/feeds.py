# -*- coding: utf-8 -*-
import jinja2
import requests
import datetime
import os

baseUrl = 'http://content.jwplatform.com/feeds/{}.json'
template_dir = "{}/feed_templates".format(os.path.dirname(os.path.realpath(__file__)))
loader = jinja2.FileSystemLoader(template_dir)
environment = jinja2.Environment(loader=loader)


def catch_key_error(key):
    """
    Function defines what to do in the event of a key error within a jinja template.
    Ex {{ variable | catch_key_error }} will be 'routed' here when rendering the template.
    :param key: Jinja template key.
    :return:
    """
    try:
        return key
    except:
        return 'Error rendering value.'


environment.filters['catch_key_error'] = catch_key_error


def get(key=''):
    """

    :param key: <string> Key of playlist/feed to retrieve
    :return: <dict> JSON response
    """
    r = requests.get(baseUrl.format(key))
    if r.status_code == 200:
        return r.json()
    else:
        raise Exception('Unable to fetch resource')


def parse(json={}):
    """
    The purpose of this function is to parse a JSON response from jwplatform.com/feeds and tailor it to your needs. This
    function illustrates how a publisher may tailor fit the response from /feeds to their needs. Adding and subtracting
    various fields for their particular use cases on the fly.

    :param json: <dict> JSON response from jwplatform.com/feeds
    :return: <dict> With appropriate JSON
    """
    clean = {}
    items = []

    for video_object in json.get('playlist', []):
        best = {'width': 0, 'url': ''}
        pubdate = datetime.datetime.utcfromtimestamp(video_object.get('pubdate'))

        # An example in which we cycle through various sources of a media object and extract the highest quality
        # We then record the url & width to be rendered in a Jinja2 template.
        video_sources = filter(lambda x: x.get('type') == 'video/mp4', video_object.get('sources', []))
        highest_quality_video = max(video_sources, key=lambda x: x.get('width', -2147483648))
        best['url'] = highest_quality_video.get('file')
        best['width'] = highest_quality_video.get('width')

        # An example in which we extract all item level information including custom paramters & required parameters.
        # The deprecated custom block is ignored however.
        # Additionally, nifty date tags are added at the item level for convenience.
        item_level_ignored_fields = ['custom']
        item = {key: value for key, value in video_object.items() if key not in item_level_ignored_fields}
        item['date_utc'] = pubdate.strftime('%Y-%m-%d %H:%M:%S')
        item['date_rss'] = pubdate.strftime("%a, %d %b %Y %H:%M:%S %z")

        # Bringing the example full circle we may utilize, the previously recorded "best" source to add a custom field,
        # which may be rendered in a custom Jinja2 template (found within feed_templates/)
        item['highest_quality_url'] = best.get('url')

        items.append(item)

    clean['items'] = items

    # Extract all feed level metadata fields.
    # Note this includes feed-level custom parameters
    feed_level_ignored_fields = ['playlist']
    clean['playlist_metadata'] = {key: value for key, value in json.items() if key not in feed_level_ignored_fields}

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
        raise Exception('Unable to render: {}'.format(template_name))
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
        rendered = toXML(parsed, "{}.xml".format(template_name))
        return rendered
