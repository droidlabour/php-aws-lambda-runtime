# A minimal custom PHP runtime for AWS Lambda container images - no
# framework, no vendor layers, just AWS's own base image + PHP installed
# straight from Amazon Linux 2023's package repo + a generic runtime loop.
#
# See ../examples/laravel-mongodb for a worked example that adds a
# framework bridge and a PECL extension compiled from source.

FROM public.ecr.aws/lambda/provided:al2023

# Install PHP itself here, plus whatever extensions your app needs that
# are packaged by AL2023 (e.g. php8.3-mbstring, php8.3-gd, php8.3-pdo).
# Anything not packaged (like ext-mongodb) needs compiling from PECL
# source - see the Laravel/MongoDB example for that pattern.
RUN dnf install -y \
    php8.3 \
    php8.3-cli \
    --setopt=install_weak_deps=0 \
    && dnf clean all

WORKDIR /var/task

COPY bootstrap /var/runtime/
COPY example.php .

# _HANDLER convention (parsed by bootstrap): "<file-without-.php>.<function-name>"
# bootstrap splits on the FIRST dot only, so the file part itself must not
# contain a dot (e.g. "handler.example.php" would break this - "example.php"
# with function "handler" gives the correct "example.handler").
CMD ["example.handler"]
