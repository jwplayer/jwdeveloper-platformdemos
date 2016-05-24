# -*- coding: utf-8 -*-
from flask import Flask
import feeds
import lxml

app = Flask(__name__)

@app.route('/<media_id>/<template_name>')
def feed(media_id='', template_name=''):
    try:
        xml_str  = feeds.process(media_id, template_name)
    except:
        xml_str  = '<?xml version="1.0" encoding="UTF-8"?><rss></rss>'
    return xml_str, 200, {'Content-Type': 'application/rss+xml; charset=utf-8'}

if __name__ == '__main__':
    app.run()