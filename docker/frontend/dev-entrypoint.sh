#!/bin/sh

set -eu

if [ ! -f package.json ]; then
    echo "Frontend package.json was not found in /app." >&2
    exit 1
fi

npm install

exec "$@"
