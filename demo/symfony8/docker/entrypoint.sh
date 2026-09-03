#!/bin/sh
set -e

# FRANKENPHP_MODE: classic | worker (REQ-DEMO-010). Default: worker.
MODE="${FRANKENPHP_MODE:-worker}"
case "$MODE" in
	classic)
		if [ -f /app/Caddyfile.dev ]; then
			cp /app/Caddyfile.dev /etc/caddy/Caddyfile
		elif [ -f /etc/frankenphp/Caddyfile.dev ]; then
			cp /etc/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile
		fi
		;;
	worker)
		if [ -f /app/Caddyfile ]; then
			cp /app/Caddyfile /etc/caddy/Caddyfile
		fi
		;;
	*)
		echo "Unknown FRANKENPHP_MODE=$MODE (expected classic|worker)" >&2
		exit 1
		;;
esac
echo "FrankenPHP mode: $MODE"

git config --global --add safe.directory /app 2>/dev/null || true
git config --global --add safe.directory /var/pdf-editor-bundle 2>/dev/null || true
mkdir -p /app/var/cache /app/var/log
chmod -R 777 /app/var 2>/dev/null || true

# Keep PDF fixtures inside /app (bind-mounted) so FrankenPHP workers always see them.
FIX_DST="/app/fixtures"
mkdir -p "$FIX_DST"
for FIX_SRC in /var/pdf-editor-bundle/demo/fixtures /app/demo-fixtures; do
	if [ -d "$FIX_SRC" ]; then
		cp -f "$FIX_SRC"/*.pdf "$FIX_DST"/ 2>/dev/null || true
	fi
done

if [ ! -f /app/vendor/autoload_runtime.php ]; then
  i=0
  until composer install --no-interaction --prefer-dist; do
    i=$((i+1))
    if [ "$i" -ge 5 ]; then echo "composer install failed after 5 attempts" >&2; exit 1; fi
    echo "composer install failed, retry $$i in 8s..." >&2
    sleep 8
  done
fi

if [ -f /app/bin/console ]; then
	php /app/bin/console cache:clear --no-warmup 2>/dev/null || true
fi

exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile
