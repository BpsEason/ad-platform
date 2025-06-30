#!/bin/bash
# docker/laravel/entrypoint.sh

set -e

# Generate APP_KEY if it's still the placeholder
if [ "${APP_KEY}" = "YOUR_GENERATED_APP_KEY_HERE" ]; then
  echo "Generating Laravel APP_KEY..."
  NEW_APP_KEY=$(php artisan key:generate --show --no-ansi)
  # Update the in-memory environment variable for the current container
  export APP_KEY="${NEW_APP_KEY}"
  echo "Generated APP_KEY: ${NEW_APP_KEY}"
else
  echo "APP_KEY is already set."
fi

# Start PHP-FPM in the background
php-fpm -D

# Start Nginx in the background
nginx -g 'daemon off;' &

# Wait for Laravel's health endpoint to be available
echo "Waiting for Laravel API to be healthy..."
until curl --fail http://localhost/api/health; do
  echo "Laravel API not ready yet, waiting..."
  sleep 5
done
echo "Laravel API is healthy."

# Fetch dynamic Traefik config and save it
echo "Fetching dynamic Traefik config..."
# Pass the TRAEFIK_API_KEY from environment to the curl request
curl --fail -H "X-API-Key: ${TRAEFIK_API_KEY}" http://localhost/api/traefik/config > /etc/traefik/dynamic_config/dynamic_routes.json || {
    echo "Failed to fetch dynamic Traefik config. Traefik might not route correctly." >&2
}
echo "Dynamic Traefik config updated."

# Keep the container running
wait %1 %2
