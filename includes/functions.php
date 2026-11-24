<?php
/**
 * Shared display helpers.
 *
 * The schedules table stores a status an admin set by hand (on_time,
 * delayed, cancelled, boarding, departed). That's fine for disruptions —
 * a human decided "delayed" or "cancelled" and that should stick — but
 * "boarding" or "on_time" set at data-entry time goes stale the moment
 * the clock moves past it. pis_effective_status() re-derives the normal
 * (non-disrupted) states from the actual current time, so the board
 * stays honest between admin edits instead of only updating when
 * someone remembers to.
 */

/**
 * @param string      $status        Raw status from the schedules table.
 * @param int         $delayMinutes  Raw delay_minutes from the schedules table.
 * @param string|null $referenceTime The time this row's state should track —
 *                                   its departure time if it has one,
 *                                   otherwise its arrival time.
 * @return array{0: string, 1: int} [effective status, effective delay minutes]
 */
function pis_effective_status(string $status, int $delayMinutes, ?string $referenceTime): array
{
    // A human called this one — don't let clock math overrule it.
    if (in_array($status, ['delayed', 'cancelled'], true)) {
        return [$status, $delayMinutes];
    }

    if ($referenceTime === null || $referenceTime === '') {
        return [$status, $delayMinutes];
    }

    // Schedules repeat daily, so only the clock-time matters here — the
    // calendar date stored on the row is whatever day the data was last
    // seeded and goes stale on its own. Comparing time-of-day only means
    // this never needs the database re-dated again to stay correct.
    $timeOfDay     = date('H:i:s', strtotime($referenceTime));
    $scheduled     = strtotime(date('Y-m-d') . ' ' . $timeOfDay);
    $minutesToGo   = ($scheduled - time()) / 60;

    if ($minutesToGo < -3) {
        return ['departed', 0];
    }
    if ($minutesToGo <= 10) {
        return ['boarding', 0];
    }
    return ['on_time', 0];
}

function pis_status_label(string $status): string
{
    return ucfirst(str_replace('_', ' ', $status));
}
