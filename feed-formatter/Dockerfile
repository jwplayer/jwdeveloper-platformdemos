FROM python:2
WORKDIR /app
COPY requirements.txt /app
COPY app.py /app
COPY feeds.py /app
COPY /feed_templates /app/feed_templates
RUN pip install -r requirements.txt
EXPOSE 5000
ENTRYPOINT ["python", "app.py"]
