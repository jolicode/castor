FROM python:3-alpine

RUN apk add build-base

WORKDIR /mkdocs/

RUN pip install \
    mkdocs\
    mkdocs-material\
    markdown-callouts\
    mkdocs-literate-nav\
    mkdocs-include-markdown-plugin

CMD ["mkdocs", "serve"]
