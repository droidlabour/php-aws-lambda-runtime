# A minimal custom PHP runtime for AWS Lambda container images - no
# framework, no vendor layers, just AWS's own base image + PHP installed
# straight from Amazon Linux 2023's package repo + a generic runtime loop.
#
# See ../examples/laravel-mongodb for a worked example that adds a
# framework bridge and a PECL extension compiled from source.

FROM public.ecr.aws/lambda/provided:al2023

# AL2023 currently packages PHP 8.1 through 8.5 - override at build time
# with `--build-arg PHP_VERSION=8.3` (or any other version it ships) to
# pin a different one. See the main README's "Choosing a PHP version"
# section.
ARG PHP_VERSION=8.5

# Install PHP itself here, plus whatever extensions your app needs that
# are packaged by AL2023 (e.g. php${PHP_VERSION}-mbstring, -gd, -pdo).
# Anything not packaged (like ext-mongodb) needs compiling from PECL
# source - see the Laravel/MongoDB example for that pattern.
RUN dnf install -y \
    php${PHP_VERSION} \
    php${PHP_VERSION}-cli \
    --setopt=install_weak_deps=0 \
    && dnf clean all

# Let Lambda's own memory limit be the only ceiling, not PHP's.
RUN echo "memory_limit = -1" > /etc/php.d/99-memory-limit.ini

WORKDIR /var/task

COPY bootstrap /var/runtime/
COPY example.php .

# _HANDLER convention (parsed by bootstrap): "<file-without-.php>.<function-name>"
# bootstrap splits on the FIRST dot only, so the file part itself must not
# contain a dot (e.g. "handler.example.php" would break this - "example.php"
# with function "handler" gives the correct "example.handler").
CMD ["example.handler"]
