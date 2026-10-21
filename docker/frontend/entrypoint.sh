#!/bin/sh
set -e

export COREPACK_ENABLE_DOWNLOAD_PROMPT=0
pnpm install --frozen-lockfile

exec "$@"
