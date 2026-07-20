# OzServer

A central authority server for [vatSys](https://vatsys.net/) that keeps airspace ownership and tag data consistent across every controller on the network — built specifically for the **VATPAC Division**.

> **Status: concept stage.** No server or client code exists yet. This README documents the current plan and will be updated as design decisions are made and implementation begins.

## The problem

Right now, each vatSys client on a network operates independently. There's no shared authority over who owns which sector and no synchronized aircraft tag data between controllers — which leads to conflicting sector claims and stale or diverging tag data.

## What OzServer does

OzServer is a server that every controller's vatSys client connects to via a companion plugin, acting as the single source of truth for:

- **Sector / airspace ownership** — exactly one controller can own a given sector at a time.
- **Tag data authority** — the controller who has an aircraft's tag picked up is the only one who can update it; every other controller receives the same data, read-only.

## How it works (planned)

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

- **This repository** houses the OzServer backend — the authoritative server vatSys clients connect to — as well as the public website ([ozserver.org](https://ozserver.org)). It's currently a Laravel application.
- **vatSys client plugin** — a separate repository, not yet started. It will run in the background on each controller's vatSys install and talk to the OzServer backend.
- **Client–server protocol** — not yet decided. A polling-based API is the current leading option; a persistent connection is also under consideration. This section will be updated once that's settled.

## Open questions

These are unresolved and will shape the eventual implementation:

- Exact client–server communication protocol (poll vs. push)
- Conflict resolution if two controllers request the same sector at the same time
- Controller identity/authentication source (likely VATSIM CID + role — to be confirmed)
- Data persistence / history requirements
- Licensing

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

## Links

- Website: [ozserver.org](https://ozserver.org)
- vatSys client plugin: coming soon
