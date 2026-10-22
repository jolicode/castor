FROM python:3-slim

WORKDIR /doc/

RUN pip install \
    zensical \
    markdown-callouts

CMD ["zensical", "serve"]
