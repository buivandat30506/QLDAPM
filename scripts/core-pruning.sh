#!/usr/bin/env bash
set -euo pipefail
umask 077
cd "$(dirname "$0")/.."
mode="${1:?backup, restore, source, run, serve, worker, grant}"
shift
target=airline_core_20261113
private=backend/storage/app/private/core-pruning
mkdir -p "$private"
set -a
source <(sed 's/\r$//' .env)
set +a
case "$mode" in
 backup)
  docker compose stop worker mail-worker app
  trap 'docker compose start app worker mail-worker' EXIT
  docker compose run --rm --no-deps -e DB_DATABASE=airline -e DB_USERNAME=root -e DB_PASSWORD="$DB_ROOT_PASSWORD" app php -d memory_limit=512M artisan airline:core-prune inventory storage/app/private/core-pruning/original.json
  docker compose run --rm --no-deps -e DB_DATABASE=airline app php tools/scope-export.php storage/app/private/core-pruning/backup
  ;;
 restore)
  for node in db1 db2 db3; do
   test -s "$private/backup/$node.sql"
   # Refuse an existing schema; never reset or overwrite original/test databases.
   docker compose exec -T "$node" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "CREATE DATABASE airline_core_20261113 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"'
   docker compose exec -T "$node" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot airline_core_20261113' < "$private/backup/$node.sql"
  done
  ;;
 acceptance)
  for node in db1 db2 db3; do
   test -s "$private/compact-backup/$node.sql"
   docker compose exec -T "$node" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "CREATE DATABASE airline_core_acceptance_20261113 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"'
   docker compose exec -T "$node" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot airline_core_acceptance_20261113' < "$private/compact-backup/$node.sql"
  done
  ;;
 app-run)
  docker compose run --rm --no-deps -e DB_DATABASE="$target" app "$@"
  ;;
 source|run|accept-run)
  database="$target"
  if [[ "$mode" == source ]]; then database=airline; fi
  if [[ "$mode" == accept-run ]]; then database=airline_core_acceptance_20261113; fi
  docker compose run --rm --no-deps -e DB_DATABASE="$database" -e DB_USERNAME=root -e DB_PASSWORD="$DB_ROOT_PASSWORD" -e MAIL_MAILER=array -e QUEUE_CONNECTION=sync app "$@"
  ;;
 serve|worker)
  target=airline_core_acceptance_20261113
  if [[ "$mode" == serve ]]; then
   docker compose run -d --no-deps --name airline-core-web -p 127.0.0.1:8002:8000 -e APP_URL=http://127.0.0.1:8002 -e DB_DATABASE="$target" -e DB_USERNAME=root -e DB_PASSWORD="$DB_ROOT_PASSWORD" -e MAIL_MAILER=array -e QUEUE_CONNECTION=sync -e CACHE_STORE=array app php artisan serve --host=0.0.0.0 --port=8000 --no-reload
  else
   docker compose run -d --no-deps --name airline-core-worker -e DB_DATABASE="$target" -e DB_USERNAME=root -e DB_PASSWORD="$DB_ROOT_PASSWORD" -e MAIL_MAILER=array -e QUEUE_CONNECTION=sync app php artisan airline:work
  fi
  ;;
 grant)
  for node in db1 db2 db3; do
   docker compose exec -T "$node" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "GRANT ALL PRIVILEGES ON airline_core_20261113.* TO airline@\"%\""'
  done
  ;;
 *) exit 2 ;;
esac
