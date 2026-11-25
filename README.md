# OzServer

A central authority server for [vatSys](https://vatsys.net/) that keeps airspace ownership and tag data consistent across every controller on the network — built specifically for the **VATPAC Division**.

> **Status: in testing.** The API, the vatSys plugin and this website are all running. This repository is the website only: [ozserver.org](https://ozserver.org).

## The problem

Right now, each vatSys client on a network operates independently. There's no shared authority over who owns which sector and no synchronized aircraft tag data between controllers — which leads to conflicting sector claims and stale or diverging tag data.

## What OzServer does

OzServer is a server that every controller's vatSys client connects to via a companion plugin, acting as the single source of truth for:

- **Sector / airspace ownership** — exactly one controller can own a given sector at a time.
- **Tag data authority** — the controller who has an aircraft's tag picked up is the only one who can update it; every other controller receives the same data, read-only.

## How it works

### Sector ownership

- OzServer maintains a live model of every sector, independent of any single client.
- When a controller opens a position, OzServer locks the sectors that position covers so no other controller can open into them.
- Controllers manage ownership through a "Manage Airspace Ownership" panel in the plugin:
  - Selecting an unclaimed sector claims it immediately.
  - Selecting a sector already owned by another controller sends that controller a request; if they approve it, ownership — and everything tied to it — transfers.
- Adjacent controllers can split their combined airspace between themselves however they like; OzServer recalculates sector infill and tag ownership for every affected aircraft automatically.

### Tag / aircraft data

- OzServer tracks each aircraft's position and which sector it is currently inside.
- The controller who owns that sector and has the aircraft picked up holds write authority over that aircraft's tag — position reports, CFL, scratchpad, etc.
- Every other connected controller receives the same data in real time, but strictly read-only, until ownership changes hands.

## Architecture

- **This repository** is the public website ([ozserver.org](https://ozserver.org)), a Laravel application with no domain data of its own: the homepage, the controller guide (`/how-to`), the live map (`/live-map`) and the developers' console (`/console`). Everything they show is read from the OzServer API.
- **[OzServer-API](https://github.com/JoshuaMicallefYBSU/OzServer-API)** — the authoritative server (Fastify, TypeScript, PostgreSQL) at `api.ozserver.org`. The plugin and the live map both talk to it directly.
- **[OzServer-Plugin](https://github.com/JoshuaMicallefYBSU/OzServer-Plugin)** — the vatSys plugin each controller runs. It polls the API and re-reads on its Server-Sent Events signals.

## Configuration

Beyond Laravel's own settings, `.env` takes:

| Variable | Purpose |
| --- | --- |
| `OZSERVER_API_URL` | The API's origin. Defaults to `https://api.ozserver.org` |
| `MAPBOX_ACCESS_TOKEN` | The live map's base map (a public Mapbox token) |
| `OZSERVER_CONSOLE_PASSWORD` | Gates `/console`. Unset, there is no console |
| `OZSERVER_CONSOLE_TOKEN` | The API's `CONSOLE_TOKEN`, for sending developer messages from the console |
| `OZSERVER_CLIENT_LOGS_TOKEN` | The API's `CLIENT_LOGS_TOKEN`, for the console's log feed. Unset while the API has none |

## Local development

This is a standard Laravel app.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan serve
```

Then, in a separate terminal, run `npm run dev` to build front-end assets.

`.env.example` is written for a server (`APP_ENV=production`, `APP_DEBUG=false`), so a copy of it put on one is safe as it stands. On your own machine, set `APP_ENV=local` and `APP_DEBUG=true` in `.env`.

## Links

- Website: [ozserver.org](https://ozserver.org)
- API: [OzServer-API](https://github.com/JoshuaMicallefYBSU/OzServer-API)
- vatSys client plugin: [OzServer-Plugin](https://github.com/JoshuaMicallefYBSU/OzServer-Plugin)
