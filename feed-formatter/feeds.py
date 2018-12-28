# -*- coding: utf-8 -*-
import jinja2
import requests
import datetime
import os

# baseUrl = 'http://content.jwplatform.com/feeds/{}.json'
# Updated to Delivery API V2
baseUrl = 'http://cdn.jwplayer.com/v2/playlists/{}?page_limit={}&page_offset={}'
template_dir = "{}/feed_templates".format(os.path.dirname(os.path.realpath(__file__)))
loader = jinja2.FileSystemLoader(template_dir)
environment = jinja2.Environment(loader=loader)

clean = {}
clean['items'] = []
html_escape_table = {
   "&": "&amp;",
   '"': "&quot;",
   "'": "&apos;",
   ">": "&gt;",
   "<": "&lt;",
}
def html_escape(text):
  """Produce entities within text."""
  return "".join(html_escape_table.get(c,c) for c in text)

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


def get(key='', pageoffset=1, pagelimit=1):
    """

    :param key: <string> Key of playlist/feed to retrieve
    :return: <dict> JSON response
    """
    print 'retrieving JSON'
    url = baseUrl.format(key,pagelimit,pageoffset)
    print url
    r = requests.get(url)
    if r.status_code == 200:
        print 'got JSON'
        return r.json()
    else:
        # If first call to playlist then throw an exception
        if pageoffset == 1:
            raise Exception('Unable to fetch resource')
        else:
            return None


def parse(json=None):
    """
    The purpose of this function is to parse a JSON response from jwplatform.com/feeds and tailor it to your needs. This
    function illustrates how a publisher may tailor fit the response from /feeds to their needs. Adding and subtracting
    various fields for their particular use cases on the fly.

    :param json: <dict> JSON response from jwplatform.com/feeds
    :return: <dict> With appropriate JSON
    """

    global clean

    json = json if json is not None else {}

    items = []

    print 'parseJSON: Enter'
    for video_object in json.get('playlist', []):
        #print u'Title: {}'.format(video_object.get('title'))
        #print u'Description: {}'.format(video_object.get('description'))
        best = {'width': 0, 'url': ''}
        pubdate = datetime.datetime.utcfromtimestamp(video_object.get('pubdate'))

        # An example in which we cycle through various sources of a media object and extract the highest quality
        # We then record the url & width to be rendered in a Jinja2 template.
        video_sources = filter(lambda x: x.get('type') == 'video/mp4', video_object.get('sources', []))
        highest_quality_video = max(video_sources, key=lambda x: x.get('width', -2147483648))
        best['url'] = highest_quality_video.get('file')
        best['width'] = highest_quality_video.get('width')


        # An example in which we extract all item level information including custom paramters & required parameters.
        # Some fields need to be escaped for HTML output
        item = {}
        for key, value in video_object.items():
            if (key == 'title') or (key == 'description') or (key == 'link'):
                item[key] = html_escape(value)
            else:
                item[key] = value

        # Additionally, nifty date tags are added at the item level for convenience.
        item['date_utc'] = pubdate.strftime('%Y-%m-%d %H:%M:%S')
        item['date_rss'] = pubdate.strftime("%a, %d %b %Y %H:%M:%S %z")

        # Bringing the example full circle we may utilize, the previously recorded "best" source to add a custom field,
        # which may be rendered in a custom Jinja2 template (found within feed_templates/)
        item['highest_quality_url'] = best.get('url')

        # mvernick ; add in HLS
        hls_sources = filter(lambda x: x.get('type') == 'application/vnd.apple.mpegurl',
                             video_object.get('sources', []))
        if len(hls_sources) == 1:
            item['hls'] = hls_sources[0].get('file')
        else:
            item['hls'] = ""

        items.append(item)

    clean['items'] = clean['items'] + items
    print 'ParseJson: Items Found: ', len(items)

    # Extract all feed level metadata fields.
    # Note this includes feed-level custom parameters
    feed_level_ignored_fields = ['playlist']
    clean['playlist_metadata'] = {key: value for key, value in json.items() if key not in feed_level_ignored_fields}

    print 'parseJSON: Exit'
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
    global clean

    pageoffset = 1
    pagelimit = 10
    clean = {}
    clean['items'] = []

    print "process: Enter, Key: {} offset: {}".format(key, pageoffset)
    while pageoffset > 0:
        data = get(key, pageoffset, pagelimit)
        if data is not None:
            parsed = parse(data)
            #print parsed['playlist_metadata']['links']
            if parsed['playlist_metadata']['links'].has_key("next"):
                pageoffset += pagelimit
            else:
                print 'Finished looping'
                pageoffset = 0
        else:
            pageoffset = 0

    print 'Rendering XML: Number parsed Items: ', len(parsed['items'])
    rendered = toXML(parsed, "{}.xml".format(template_name))

    return rendered
