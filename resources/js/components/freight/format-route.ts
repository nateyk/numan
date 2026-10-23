/**
 * Metres/seconds to the lane figures a card or stat strip can carry.
 *
 * One implementation because both browse surfaces draw the same snapshot and
 * "932 km · ~14 h" has to mean the same thing on each.
 */

/** Metres to a lane figure: "932 km"; a sub-kilometre lane stays in metres. */
export const formatDistance = (meters: number): string =>
    meters >= 1000 ? `${Math.round(meters / 1000)} km` : `${meters} m`;

/** Seconds to an estimate the surface can carry: "~14 h". */
export const formatDuration = (seconds: number): string => {
    if (seconds >= 3600) {
        return `~${Math.round(seconds / 3600)} h`;
    }
    if (seconds >= 60) {
        return `~${Math.round(seconds / 60)} min`;
    }
    return '';
};
