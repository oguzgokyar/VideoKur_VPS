FROM php:8.3-fpm-bookworm

ARG DEBIAN_FRONTEND=noninteractive
ENV PYTHONUNBUFFERED=1 \
    PYTHONIOENCODING=utf-8 \
    PIP_NO_CACHE_DIR=1 \
    TZ=Europe/Istanbul \
    PATH="/opt/videokur-venv/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        ffmpeg \
        fonts-dejavu-core \
        fonts-liberation \
        libcurl4-openssl-dev \
        libonig-dev \
        nginx \
        procps \
        python3 \
        python3-pip \
        python3-venv \
        supervisor \
        tzdata \
        unzip \
    && docker-php-ext-install curl mbstring \
    && ln -sf /usr/bin/python3 /usr/local/bin/python \
    && python3 -m venv /opt/videokur-venv \
    && /opt/videokur-venv/bin/pip install --upgrade pip setuptools wheel \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY python/requirements.txt python/requirements.lock /tmp/
RUN /opt/videokur-venv/bin/pip install -r /tmp/requirements.lock

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/videokur.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-videokur.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-videokur.conf
COPY docker/entrypoint.sh /usr/local/bin/videokur-entrypoint
RUN chmod +x /usr/local/bin/videokur-entrypoint \
    && rm -f /etc/nginx/sites-enabled/default

COPY . /app
COPY docker/data-seed/ /app/data-seed/

RUN mkdir -p /app/data /app/output /app/logs /app/assets /run/nginx /var/log/supervisor \
    && chown -R www-data:www-data /app/data /app/output /app/logs /app/assets

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --retries=3 --start-period=40s \
    CMD curl -fsS http://127.0.0.1/api/health.php >/dev/null || exit 1

ENTRYPOINT ["videokur-entrypoint"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
