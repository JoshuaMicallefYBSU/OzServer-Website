{{-- The OzServer mark: one scope shared by two controllers, with a track between them. $ring is
     the first arc and the track, $held the second arc; on a dark ground they default to the
     logo's own white and blue. --}}
<svg viewBox="0 0 32 32" aria-hidden="true"><path d="M5.43 22.86A12.6 12.6 0 0 1 22.86 5.43" fill="none" stroke="{{ $ring ?? '#EEF2FB' }}" stroke-width="2.8" stroke-linecap="round"/><path d="M26.57 9.14A12.6 12.6 0 0 1 9.14 26.57" fill="none" stroke="{{ $held ?? '#5B8CFF' }}" stroke-width="2.8" stroke-linecap="round"/><path transform="translate(16 16) scale(0.56)" d="M0-4C0-4 12-12 4 0C4 0 12 12 0 4C0 4-12 12-4 0C-4 0-12-12 0-4Z" fill="{{ $ring ?? '#EEF2FB' }}"/></svg>
