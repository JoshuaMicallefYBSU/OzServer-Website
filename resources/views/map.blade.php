<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>OzServer | Live Map</title>
        <meta name="mapbox-token" content="{{ config('services.mapbox.token') }}">
        <link href="https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.css" rel="stylesheet">
        <script src="https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.js"></script>
        <style>
            html, body { margin: 0; height: 100%; background: #020617; }
            #map { position: absolute; inset: 0; }

            .oz-panel {
                position: absolute;
                z-index: 1;
                background: rgba(2, 6, 23, 0.85);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 12px;
                padding: 12px 16px;
                color: #e2e8f0;
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 13px;
            }

            .oz-controllers-panel { top: 16px; right: 16px; min-width: 220px; max-height: 60vh; overflow-y: auto; }
            .oz-controllers-panel h3 { margin: 0 0 8px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; }
            .oz-controllers-panel ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
            .oz-controllers-panel li { display: flex; align-items: center; gap: 8px; font-family: ui-monospace, monospace; }
            .oz-controllers-panel li span { color: #67e8f9; }
            .oz-controllers-panel li .oz-controller-main { flex: 1; }
            .oz-controllers-panel .oz-empty { color: #64748b; font-family: inherit; }

            .oz-source-icon {
                flex-shrink: 0; width: 15px; height: 15px; border-radius: 50%;
                display: inline-flex; align-items: center; justify-content: center;
                font-size: 10px; font-weight: 700; line-height: 1; font-family: ui-sans-serif, system-ui, sans-serif;
            }
            .oz-source-icon.oz-ozserver { background: rgba(34, 211, 238, 0.2); color: #22d3ee; border: 1px solid #22d3ee; }
            .oz-source-icon.oz-not-ozserver { background: rgba(248, 113, 113, 0.15); color: #f87171; border: 1px solid #f87171; }

            .oz-sector-popup, .oz-aircraft-popup, .oz-atis-popup { color: #0f172a; font-family: ui-sans-serif, system-ui, sans-serif; }
            .oz-sector-popup h3, .oz-aircraft-popup h3, .oz-atis-popup h3 { margin: 0 0 4px; font-size: 14px; }
            .oz-sector-popup p, .oz-aircraft-popup p, .oz-atis-popup p { margin: 2px 0; font-size: 12px; }
            .oz-aircraft-popup { max-width: 260px; }
            .oz-aircraft-popup .oz-route {
                font-family: ui-monospace, monospace; font-size: 11px; color: #334155;
                white-space: normal; word-break: break-word; margin-top: 6px;
            }
            .oz-aircraft-popup .oz-remarks {
                font-size: 11px; color: #64748b; white-space: normal; word-break: break-word;
            }
            .oz-atis-popup { max-width: 240px; }

            .oz-aircraft-marker { pointer-events: auto; }

            .oz-atis-marker {
                pointer-events: auto; width: 22px; height: 22px; border-radius: 50%;
                background: #1e293b; border: 2px solid #a78bfa; color: #a78bfa;
                display: flex; align-items: center; justify-content: center;
                font-size: 10px; font-weight: 700; font-family: ui-sans-serif, system-ui, sans-serif;
            }
        </style>
    </head>
    <body>
        <div id="map"></div>
        <script>
            const POLL_INTERVAL_MS = 15000;

            mapboxgl.accessToken = document.querySelector('meta[name="mapbox-token"]').content;

            const map = new mapboxgl.Map({
                container: 'map',
                style: 'mapbox://styles/mapbox/dark-v11',
                projection: 'mercator',
                center: [134, -25],
                zoom: 3.6,
            });

            const aircraftMarkers = new Map(); // callsign -> mapboxgl.Marker
            const atisMarkers = new Map(); // icao -> mapboxgl.Marker

            // Info icon for connections claimed via the OzServer plugin, X
            // for ones only visible on the raw VATSIM datafeed.
            function sourceIconHtml(isOzserver) {
                return isOzserver
                    ? '<span class="oz-source-icon oz-ozserver" title="Connected via OzServer">i</span>'
                    : '<span class="oz-source-icon oz-not-ozserver" title="Not connected via OzServer">&times;</span>';
            }

            // A handful of oceanic sectors (e.g. NZZO, NFFF) cross the
            // antimeridian. Without unwrapping, those rings jump straight
            // across the whole map instead of following the sector's edge.
            function unwrapRingLongitudes(ring) {
                const unwrapped = [ring[0]];
                for (let i = 1; i < ring.length; i++) {
                    const prevLon = unwrapped[i - 1][0];
                    let [lon, lat] = ring[i];
                    while (lon - prevLon > 180) lon -= 360;
                    while (lon - prevLon < -180) lon += 360;
                    unwrapped.push([lon, lat]);
                }
                return unwrapped;
            }

            function sectorsToGeoJson(sectors) {
                return {
                    type: 'FeatureCollection',
                    features: sectors
                        .filter((sector) => sector.boundary.length > 0)
                        .map((sector) => ({
                            type: 'Feature',
                            properties: {
                                name: sector.name,
                                full_name: sector.full_name,
                                callsign: sector.callsign,
                                frequency: sector.frequency,
                                owner_cid: sector.owner?.cid ?? null,
                                owner_callsign: sector.owner?.callsign ?? null,
                                online: sector.online,
                            },
                            geometry: {
                                type: 'MultiPolygon',
                                // Each volume is one ring; stored as [lat, lon] pairs, GeoJSON wants [lon, lat].
                                coordinates: sector.boundary.map((ring) => [
                                    unwrapRingLongitudes(ring.map((point) => [point.lon, point.lat])),
                                ]),
                            },
                        })),
                };
            }

            function buildControllersPanel() {
                const panel = document.createElement('div');
                panel.className = 'oz-panel oz-controllers-panel';
                panel.innerHTML = '<h3>Online</h3><ul id="oz-controllers-list"></ul>';
                document.body.appendChild(panel);
                return panel.querySelector('#oz-controllers-list');
            }

            const controllersList = buildControllersPanel();

            // Read-only - claiming/releasing sectors happens through the
            // vatSys plugin, not this site.
            function sectorPopupHtml(properties) {
                const owned = properties.owner_cid !== null;
                const statusLine = owned
                    ? `Owned by <strong>${properties.owner_callsign}</strong> (${properties.owner_cid})`
                    : 'Unclaimed';

                return `
                    <div class="oz-sector-popup">
                        <h3>${properties.full_name} (${properties.name})</h3>
                        <p>${properties.callsign ?? ''} ${properties.frequency ? '· ' + properties.frequency : ''}</p>
                        <p>${statusLine}</p>
                    </div>
                `;
            }

            function formatTime(iso) {
                if (!iso) return null;
                return new Date(iso).toISOString().slice(11, 16) + 'z';
            }

            function aircraftPopupHtml(flight) {
                const squawk = flight.assigned_ssr_code !== null
                    ? String(flight.assigned_ssr_code).padStart(4, '0')
                    : null;

                const levels = [
                    flight.cfl_lower && flight.cfl_upper && flight.cfl_lower !== flight.cfl_upper
                        ? `CFL ${flight.cfl_lower}-${flight.cfl_upper}`
                        : (flight.cfl_lower ?? flight.cfl_upper) ? `CFL ${flight.cfl_lower ?? flight.cfl_upper}` : null,
                    flight.rfl ? `RFL ${flight.rfl}` : null,
                ].filter(Boolean).join(' · ');

                const sidStarRunway = [flight.sid_star_string, flight.runway_string ?? flight.departure_runway]
                    .filter(Boolean).join(' ');

                const times = [
                    flight.atd ? `ATD ${formatTime(flight.atd)}` : (flight.etd ? `ETD ${formatTime(flight.etd)}` : null),
                    flight.eet_minutes ? `EET ${flight.eet_minutes}m` : null,
                ].filter(Boolean).join(' · ');

                const rows = [
                    [flight.aircraft_type, flight.aircraft_wake ? `/${flight.aircraft_wake}` : ''].join(''),
                    `${flight.dep_airport ?? '????'} → ${flight.des_airport ?? '????'}${sidStarRunway ? ' · ' + sidStarRunway : ''}`,
                    levels || null,
                    `${flight.altitude ?? '?'} ft · ${flight.ground_speed ?? '?'} kt${flight.heading !== null ? ' · ' + flight.heading + '°' : ''}`,
                    squawk ? `Squawk ${squawk}` : null,
                    times || null,
                    flight.flight_rules ? `Rules: ${flight.flight_rules}` : null,
                    flight.state ? `State: ${flight.state}` : null,
                    flight.controlling_callsign ? `Controlling: ${flight.controlling_callsign}` : null,
                ].filter(Boolean);

                const routeLine = flight.route ? `<p class="oz-route">${flight.route}</p>` : '';
                const remarksLine = flight.remarks ? `<p class="oz-remarks">${flight.remarks}</p>` : '';

                return `
                    <div class="oz-aircraft-popup">
                        <h3>${flight.callsign}</h3>
                        ${rows.map((row) => `<p>${row}</p>`).join('')}
                        ${routeLine}
                        ${remarksLine}
                    </div>
                `;
            }

            // Frequencies are stored FSD-style (freq_mhz - 100) * 1000, e.g.
            // 132.500 MHz is saved as 32500 - see AtisController/migration.
            function formatAtisFrequency(frequency) {
                return frequency !== null ? (100 + frequency / 1000).toFixed(3) : null;
            }

            function atisPopupHtml(atis) {
                const freq = formatAtisFrequency(atis.frequency);
                const contentRows = Object.entries(atis.content ?? {})
                    .map(([field, value]) => `<p><strong>${field}:</strong> ${value}</p>`)
                    .join('');

                return `
                    <div class="oz-atis-popup">
                        <h3>${atis.icao} ATIS ${atis.atis_letter}</h3>
                        ${freq ? `<p>${freq} MHz</p>` : ''}
                        ${contentRows}
                        <p>Last seen ${formatTime(atis.last_seen_at)}</p>
                    </div>
                `;
            }

            async function refreshSectors() {
                const response = await fetch('/api/v1/map/sectors', { headers: { Accept: 'application/json' } });
                const sectors = await response.json();

                const source = map.getSource('sectors');
                const geojson = sectorsToGeoJson(sectors);

                if (source) {
                    source.setData(geojson);
                } else {
                    map.addSource('sectors', { type: 'geojson', data: geojson });

                    map.addLayer({
                        id: 'sectors-fill',
                        type: 'fill',
                        source: 'sectors',
                        paint: {
                            // Only claimed sectors are ever sent here now,
                            // so this is just the one dark-blue fill for
                            // all of them (no separate owned/unclaimed
                            // distinction to draw anymore).
                            'fill-color': '#334155',
                            'fill-opacity': 0.35,
                        },
                    });

                    map.addLayer({
                        id: 'sectors-line',
                        type: 'line',
                        source: 'sectors',
                        paint: {
                            'line-color': ['case', ['==', ['get', 'online'], true], '#22d3ee', '#64748b'],
                            'line-width': 1.5,
                        },
                    });

                    map.on('click', 'sectors-fill', (e) => {
                        const properties = e.features[0].properties;
                        new mapboxgl.Popup()
                            .setLngLat(e.lngLat)
                            .setHTML(sectorPopupHtml(properties))
                            .addTo(map);
                    });

                    map.on('mouseenter', 'sectors-fill', () => { map.getCanvas().style.cursor = 'pointer'; });
                    map.on('mouseleave', 'sectors-fill', () => { map.getCanvas().style.cursor = ''; });
                }
            }

            async function refreshAircraft() {
                const response = await fetch('/api/v1/map/aircraft', { headers: { Accept: 'application/json' } });
                const flights = await response.json();

                const seen = new Set();

                flights.forEach((flight) => {
                    seen.add(flight.callsign);

                    let marker = aircraftMarkers.get(flight.callsign);

                    if (!marker) {
                        const el = document.createElement('div');
                        el.className = 'oz-aircraft-marker';
                        el.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16"><polygon points="8,0 14,16 8,12 2,16" fill="#facc15" /></svg>';

                        marker = new mapboxgl.Marker({ element: el, rotationAlignment: 'map' });
                        marker.setPopup(new mapboxgl.Popup({ offset: 12 }));
                        aircraftMarkers.set(flight.callsign, marker);
                    }

                    marker.setLngLat([flight.lon, flight.lat]);
                    marker.setRotation(flight.heading ?? 0);
                    marker.getPopup().setHTML(aircraftPopupHtml(flight));
                    marker.addTo(map);
                });

                for (const [callsign, marker] of aircraftMarkers) {
                    if (!seen.has(callsign)) {
                        marker.remove();
                        aircraftMarkers.delete(callsign);
                    }
                }
            }

            async function refreshAtis() {
                const response = await fetch('/api/v1/map/atis', { headers: { Accept: 'application/json' } });
                const broadcasts = await response.json();

                const seen = new Set();

                broadcasts.forEach((atis) => {
                    seen.add(atis.icao);

                    let marker = atisMarkers.get(atis.icao);

                    if (!marker) {
                        const el = document.createElement('div');
                        el.className = 'oz-atis-marker';
                        el.textContent = 'A';

                        marker = new mapboxgl.Marker({ element: el });
                        marker.setPopup(new mapboxgl.Popup({ offset: 12 }));
                        atisMarkers.set(atis.icao, marker);
                    }

                    marker.setLngLat([atis.lon, atis.lat]);
                    marker.getPopup().setHTML(atisPopupHtml(atis));
                    marker.addTo(map);
                });

                for (const [icao, marker] of atisMarkers) {
                    if (!seen.has(icao)) {
                        marker.remove();
                        atisMarkers.delete(icao);
                    }
                }
            }

            async function refreshControllers() {
                const response = await fetch('/api/v1/map/controllers', { headers: { Accept: 'application/json' } });
                const controllers = await response.json();

                controllersList.innerHTML = controllers.length
                    ? controllers.map((c) => `
                        <li>
                            <span class="oz-controller-main">${c.callsign} · ${c.sector_name} <span>${c.frequencies.join(', ')}</span></span>
                            ${sourceIconHtml(c.is_ozserver)}
                        </li>
                    `).join('')
                    : '<li class="oz-empty">No sectors staffed</li>';
            }

            function refreshAll() {
                refreshSectors();
                refreshAircraft();
                refreshAtis();
                refreshControllers();
            }

            map.on('load', () => {
                refreshAll();
                setInterval(refreshAll, POLL_INTERVAL_MS);
            });
        </script>
    </body>
</html>
