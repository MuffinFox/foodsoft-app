# Pickup App

Foodsoft apps for picking up and distributing foodcoop orders. For setup and configuration see [pickup/readme.md](pickup/readme.md), for usage instructions (in German) see [pickup/usage-de.md](pickup/usage-de.md).

## Development

For local development, a Docker Compose setup is available. It runs the PHP built-in server (with Xdebug on port 9003) behind a Caddy proxy with HTTPS support. This allows usage of oauth application configuration in remote foodsoft instances, as HTTPS is required.

Install the PHP dependencies first, then start the containers:

```sh
cd pickup && composer install && cd ..
docker compose up
```

The app is then available at `https://localhost:8082/<fc-name>/`.