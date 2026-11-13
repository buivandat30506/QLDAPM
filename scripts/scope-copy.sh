#!/usr/bin/env bash
set -euo pipefail
umask 077
cd "$(dirname "$0")/.."
mode="${1:?restore or run}"
if [[ "$mode" == restore ]]; then
  for node in db1 db2 db3; do
    test -s "backend/storage/app/private/scope-backup/$node.sql"
    # CREATE deliberately fails if a previous copy exists; never reset a schema.
    docker compose exec -T "$node" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "CREATE DATABASE airline_scope_copy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"'
    docker compose exec -T "$node" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot airline_scope_copy' < "backend/storage/app/private/scope-backup/$node.sql"
  done
elif [[ "$mode" == run || "$mode" == serve || "$mode" == worker ]]; then
  shift
  set -a
  source <(sed 's/\r$//' .env)
  set +a
  if [[ "$mode" == serve ]]; then
    docker compose run -d --no-deps --name airline-scope-web -p 127.0.0.1:8002:8000 -e APP_URL=http://127.0.0.1:8002 -e DB_DATABASE=airline_scope_copy -e DB_USERNAME=root -e DB_PASSWORD="$DB_ROOT_PASSWORD" -e MAIL_MAILER=array -e QUEUE_CONNECTION=sync -e CACHE_STORE=array app php artisan serve --host=0.0.0.0 --port=8000 --no-reload
  elif [[ "$mode" == worker ]]; then
    docker compose run -d --no-deps --name airline-scope-worker -e DB_DATABASE=airline_scope_copy -e DB_USERNAME=root -e DB_PASSWORD="$DB_ROOT_PASSWORD" -e MAIL_MAILER=array -e QUEUE_CONNECTION=sync app php artisan airline:work
  else
    docker compose run --rm --no-deps -e DB_DATABASE=airline_scope_copy -e DB_USERNAME=root -e DB_PASSWORD="$DB_ROOT_PASSWORD" app "$@"
  fi
else
  exit 2
fi
