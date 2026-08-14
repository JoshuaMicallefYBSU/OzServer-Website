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

            .oz-controllers-panel { top: 16px; right: 16px; min-width: 200px; max-height: 60vh; overflow-y: auto; }
            .oz-controllers-panel h3 { margin: 0 0 8px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; }
            .oz-controllers-panel ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
            .oz-controllers-panel li { display: flex; gap: 8px; font-family: ui-monospace, monospace; }
            .oz-controllers-panel li span { color: #67e8f9; }
            .oz-controllers-panel .oz-empty { color: #64748b; font-family: inherit; }

            .oz-sector-popup, .oz-aircraft-popup { color: #0f172a; font-family: ui-sans-serif, system-ui, sans-serif; }
            .oz-sector-popup h3, .oz-aircraft-popup h3 { margin: 0 0 4px; font-size: 14px; }
            .oz-sector-popup p, .oz-aircraft-popup p { margin: 2px 0; font-size: 12px; }

            .oz-aircraft-marker { pointer-events: auto; }
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
                            'fill-color': [
                                'case',
                                ['!=', ['get', 'owner_cid'], null], '#f97316',
                                ['==', ['get', 'online'], true], '#22d3ee',
                                '#334155',
                            ],
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
                    marker.getPopup().setHTML(`
                        <div class="oz-aircraft-popup">
                            <h3>${flight.callsign}</h3>
                            <p>${flight.aircraft_type ?? ''}</p>
                            <p>${flight.dep_airport ?? '????'} → ${flight.des_airport ?? '????'}</p>
                            <p>${flight.altitude ?? '?'} ft · ${flight.ground_speed ?? '?'} kt</p>
                        </div>
                    `);
                    marker.addTo(map);
                });

                for (const [callsign, marker] of aircraftMarkers) {
                    if (!seen.has(callsign)) {
                        marker.remove();
                        aircraftMarkers.delete(callsign);
                    }
                }
            }

            async function refreshControllers() {
                const response = await fetch('/api/v1/map/controllers', { headers: { Accept: 'application/json' } });
                const controllers = await response.json();

                controllersList.innerHTML = controllers.length
                    ? controllers.map((c) => `<li>${c.sector_name} <span>${c.frequencies.join(', ')}</span></li>`).join('')
                    : '<li class="oz-empty">No sectors staffed</li>';
            }

            function refreshAll() {
                refreshSectors();
                refreshAircraft();
                refreshControllers();
            }

            map.on('load', () => {
                refreshAll();
                setInterval(refreshAll, POLL_INTERVAL_MS);
            });
        </script>
    </body>
</html>
