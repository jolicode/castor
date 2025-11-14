FROM python:3-slim

WORKDIR /mkdocs/

RUN pip install \
    mkdocs\
    mkdocs-material\
    mkdocs-material[imaging]\
    markdown-callouts\
    mkdocs-literate-nav\
    mkdocs-include-markdown-plugin

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcairo2-dev \
        libfreetype6-dev \
        libffi-dev \
        libjpeg-dev \
        libpng-dev \
        libz-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* \

CMD ["mkdocs", "serve"]
