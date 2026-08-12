# Copy this whole directory into your Laravel app's repo root as
# `aws-lambda/`, then point your docker-compose.yml's build config at
# `dockerfile: aws-lambda/Dockerfile` with build context `.` (repo root) -
# the COPY . . step below needs the full app alongside composer.json.
#
# See ../../README.md for how the pieces fit together.

FROM public.ecr.aws/lambda/provided:al2023 AS mongodb-builder

# ext-mongodb isn't packaged by AL2023, so compile it from PECL source.
# Swap the version / drop this stage entirely if your app doesn't need it.
RUN dnf install -y \
    php8.3 \
    php8.3-cli \
    php8.3-devel \
    gcc \
    gcc-c++ \
    make \
    autoconf \
    pkgconfig \
    openssl-devel \
    gzip \
    --setopt=install_weak_deps=0

RUN curl -sSL -o /tmp/mongodb.tgz https://pecl.php.net/get/mongodb-1.21.0.tgz \
    && mkdir /tmp/mongodb-src \
    && tar xzf /tmp/mongodb.tgz -C /tmp/mongodb-src --strip-components=1 \
    && cd /tmp/mongodb-src \
    && phpize \
    && ./configure \
    && make -j"$(nproc)" \
    && make install

FROM public.ecr.aws/lambda/provided:al2023 AS composer-builder

RUN dnf install -y \
    php8.3 \
    php8.3-cli \
    php8.3-mbstring \
    php8.3-gd \
    php8.3-xml \
    php8.3-process \
    php8.3-pdo \
    git \
    unzip \
    --setopt=install_weak_deps=0

COPY --from=mongodb-builder /usr/lib64/php8.3/modules/mongodb.so /usr/lib64/php8.3/modules/mongodb.so
RUN echo "extension=mongodb" > /etc/php.d/30-mongodb.ini

COPY --link --from=composer/composer:2-bin /composer /usr/bin/composer

WORKDIR /var/task

COPY . .

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-dev

FROM public.ecr.aws/lambda/provided:al2023

ENV TZ=UTC

# Match the extensions to whatever your app's composer.json actually
# requires - php8.3-pdo is included here because mongodb/laravel-mongodb's
# Connection class extends Laravel core's Illuminate\Database\Connection,
# which references the PDO class in its own signatures, so it's needed
# even though this app never touches a relational database.
RUN dnf install -y \
    php8.3 \
    php8.3-cli \
    php8.3-mbstring \
    php8.3-gd \
    php8.3-xml \
    php8.3-process \
    php8.3-pdo \
    --setopt=install_weak_deps=0 \
    && dnf clean all

COPY --from=mongodb-builder /usr/lib64/php8.3/modules/mongodb.so /usr/lib64/php8.3/modules/mongodb.so
RUN echo "extension=mongodb" > /etc/php.d/30-mongodb.ini

WORKDIR /var/task

COPY --from=composer-builder /var/task /var/task

COPY aws-lambda/bootstrap /var/runtime/
COPY aws-lambda/http.php .
COPY aws-lambda/queue.php .
COPY aws-lambda/LambdaSqsJob.php .

# Default to the HTTP handler; override `command:` per Lambda function to
# point at queue.handler for the SQS-triggered one - see the README.
CMD ["http.handler"]
