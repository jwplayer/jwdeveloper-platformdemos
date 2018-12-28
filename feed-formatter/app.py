# -*- coding: utf-8 -*-
from flask import Flask
from flask import Response
import feeds
import lxml

app = Flask(__name__)


@app.route('/<media_id>/<template_name>')
def feed(media_id='', template_name=''):
    #try:
    #    xml_str = feeds.process(media_id, template_name)
    #except:
    #    xml_str = '<?xml version="1.0" encoding="UTF-8"?><rss></rss>'

    xml_str = feeds.process(media_id, template_name)
    return Response(xml_str, mimetype='text/xml')


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
