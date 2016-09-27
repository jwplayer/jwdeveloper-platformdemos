# Feed Formatter Demo

This directory demonstrates how you can deploy a simple feed formatter service to facilitate syndication of feeds to partners that require custom feed formats.

## Setup

1. Create a virtual environment:
   `$ virtualenv venv --no-site-packages`
2. Activate it:
   `$ source venv/bin/activate`
3. Install dependencies:
   * please make sure you have developer tools installed
   * `# debian/ubuntu: sudo apt-get install build-essential`
   * `# rhel/centos: sudo yum group install "Development Tools"` 
   * `$ pip install -r requirements.txt`
4. Run the app:
   `$ python app.py`
5. Visit the app in your browser
  * <http://localhost:5000/JW_MEDIA_KEY/TEMPLATE_NAME>
  * <http://localhost:5000/Hilgq9Ju/custom1>
6. OR use an application server 
   `http://flask.pocoo.org/docs/0.11/deploying/uwsgi/`
