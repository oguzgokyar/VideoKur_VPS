FROM php:8.3-cli-bookworm

ENV PYTHONUNBUFFERED=1 \
    PYTHONIOENCODING=utf-8 \
    PIP_NO_CACHE_DIR=1

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ffmpeg \
        fonts-dejavu-core \
        fonts-liberation \
        libcurl4-openssl-dev \
        libonig-dev \
        python3 \
        python3-pip \
        python3-venv \
        procps \
        unzip \
    && docker-php-ext-install curl mbstring \
    && ln -sf /usr/bin/python3 /usr/local/bin/python \
    && python3 -m venv /opt/videokur-venv \
    && /opt/videokur-venv/bin/pip install --upgrade pip setuptools wheel \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

ENV PATH="/opt/videokur-venv/bin:${PATH}"

COPY python/requirements.txt /tmp/requirements.txt
RUN pip install -r /tmp/requirements.txt

COPY docker/entrypoint.sh /usr/local/bin/videokur-entrypoint
RUN chmod +x /usr/local/bin/videokur-entrypoint

COPY . /app

EXPOSE 8000

ENTRYPOINT ["videokur-entrypoint"]
CMD ["php", "-S", "0.0.0.0:8000", "router.php"]
