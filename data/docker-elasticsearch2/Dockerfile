FROM debian:11
ARG DEBIAN_FRONTEND=noninteractive

RUN apt update
RUN apt install -y --no-install-recommends\
        openjdk-11-jre-headless\
        wget \
    && rm -rf /var/lib/apt/lists/*
# https://www.elastic.co/downloads/past-releases/elasticsearch-2-4-6
RUN wget https://download.elastic.co/elasticsearch/release/org/elasticsearch/distribution/deb/elasticsearch/2.4.6/elasticsearch-2.4.6.deb \
    && dpkg -i elasticsearch-2.4.6.deb

RUN cd /usr/share/elasticsearch/ \
    && bin/plugin install delete-by-query

# ----
#FROM debian:12
#COPY --from=0 / /

EXPOSE 9200 9300

ENV ES_GC_OPTS=" "
CMD [\
    "chroot", "--userspec=101", "/", \
    "/usr/share/elasticsearch/bin/elasticsearch", \
    "-Des.default.path.home=/usr/share/elasticsearch", \
    "-Des.default.path.logs=/var/log/elasticsearch", \
    "-Des.default.path.data=/var/lib/elasticsearch", \
    "-Des.default.path.conf=/etc/elasticsearch" \
    ]
