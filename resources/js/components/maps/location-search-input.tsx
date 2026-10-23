import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { MapPin, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/**
 * A search result, from either source.
 *
 * Exported so consumers type their handlers against this rather than writing
 * their own inline copy, which is how the two drifted apart and produced a type
 * error on every handler.
 *
 * An OSM result has a string id of the form "osm_<id>" and no slug; a local row
 * has a numeric id and a slug. Callers that need a real database id must resolve
 * an OSM result first -- see resolveOsmLocation.
 */
export interface Location {
    id: string | number;
    name: string;
    region_name: string;
    slug?: string;
    type: string;
    latitude?: string | number | null;
    longitude?: string | number | null;
    population?: number | null;
    elevation?: number | null;
    wereda?: string | null;
    zone?: string | null;
    is_major_town?: boolean;
    osm_type?: string | null;
    osm_id?: string | number | null;
}

interface LocationSearchInputProps {
    value?: number;
    initialName?: string;
    onChange?: (id: number) => void;
    onSelectLocation?: (loc: Location | null) => void;
    placeholder?: string;
    className?: string;
    debounceMs?: number;
    id?: string;
    ariaLabel?: string;
    /** Narrows the result set. 'freight' biases towards major towns and terminals. */
    type?: 'all' | 'freight' | 'city' | 'town';
    /** ISO 3166-1 alpha-2. Neighbouring countries are included for border towns. */
    country?: string;
    /**
     * Ask the server for local rows only, so no OSM results come back.
     *
     * Required by any consumer that stores the id: a discovery filter matches on
     * location_id, and an "osm_1234" would silently match nothing.
     */
    localOnly?: boolean;
}

export default function LocationSearchInput({
    initialName,
    onChange,
    onSelectLocation,
    placeholder = 'Search locations…',
    className,
    debounceMs = 300,
    id,
    ariaLabel,
    type = 'all',
    country = 'ET',
    localOnly = false,
}: LocationSearchInputProps) {
    const [query, setQuery] = useState(initialName ?? '');
    const [results, setResults] = useState<Location[]>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    // True once a search has come back empty, so the "nothing found" box can
    // appear. Without it the dropdown would either never open on an empty result
    // or flash open on every keystroke.
    const [noResultsYet, setNoResultsYet] = useState(false);
    const containerRef = useRef<HTMLDivElement | null>(null);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        return () => {
            if (timerRef.current) clearTimeout(timerRef.current);
        };
    }, []);

    useEffect(() => {
        if (containerRef.current) {
            const handleClick = (e: MouseEvent) => {
                if (!containerRef.current?.contains(e.target as Node)) {
                    setOpen(false);
                }
            };
            document.addEventListener('mousedown', handleClick);
            return () => document.removeEventListener('mousedown', handleClick);
        }
    }, []);

    const search = (q: string) => {
        setQuery(q);
        if (timerRef.current) clearTimeout(timerRef.current);
        setNoResultsYet(false);

        if (q.length < 2) {
            setResults([]);
            setOpen(false);
            return;
        }
        timerRef.current = setTimeout(async () => {
            setLoading(true);
            try {
                const params = new URLSearchParams({
                    q: q,
                    type: type,
                    country: country,
                });

                if (localOnly) {
                    params.set('local_only', '1');
                }

                const res = await fetch(
                    `/locations/search?${params.toString()}`,
                );

                if (res.ok) {
                    const data = await res.json();

                    const seen = new Set<string>();
                    const deduplicated = (data.locations ?? []).filter(
                        (loc: Location) => {
                            // Two OSM features can share a display name, so key on the
                            // feature id when there is one.
                            const key =
                                loc.osm_id != null
                                    ? `osm:${loc.osm_type}:${loc.osm_id}`
                                    : `name:${loc.name.toLowerCase().trim()}|${loc.region_name.toLowerCase().trim()}`;

                            if (seen.has(key)) return false;
                            seen.add(key);
                            return true;
                        },
                    );

                    setResults(deduplicated);
                    setNoResultsYet(deduplicated.length === 0);
                    setOpen(true);
                }
            } catch {
                setOpen(false);
            } finally {
                setLoading(false);
            }
        }, debounceMs);
    };

    const selectLocation = (loc: Location) => {
        setQuery(`${loc.name}, ${loc.region_name}`);
        setOpen(false);
        setResults([]);

        if (typeof loc.id === 'string') {
            // An OSM result has no database id yet. onChange is deliberately not
            // called for it: it used to be called with 0, which any consumer
            // storing the number would have persisted as a real, wrong id.
            //
            // A consumer that offers OSM results must use onSelectLocation and
            // resolve the feature to a row first. Consumers that only need an id
            // should pass localOnly.
            onSelectLocation?.(loc);
            return;
        }

        onChange?.(loc.id);
        onSelectLocation?.(loc);
    };

    const clear = () => {
        setQuery('');
        setResults([]);
        setOpen(false);
        onChange?.(0);
        onSelectLocation?.(null);
    };

    return (
        <div ref={containerRef} className={cn('relative', className)}>
            <div className="relative">
                <MapPin className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    id={id}
                    value={query}
                    onChange={(e) => search(e.target.value)}
                    onFocus={() => query.length >= 2 && setOpen(true)}
                    placeholder={placeholder}
                    className="pr-9 pl-9"
                    aria-label={ariaLabel ?? placeholder}
                    aria-expanded={open}
                    aria-haspopup="listbox"
                    role="combobox"
                />
                {query && (
                    <button
                        type="button"
                        onClick={clear}
                        aria-label={`Clear ${ariaLabel ?? placeholder}`}
                        className="absolute top-1/2 right-3 -translate-y-1/2 rounded text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <X className="size-4" aria-hidden="true" />
                    </button>
                )}
            </div>
            {open && (results.length > 0 || loading || !noResultsYet) && (
                <div
                    role="listbox"
                    aria-label={ariaLabel ?? 'Location results'}
                    className="absolute z-50 mt-1 min-w-full rounded-md border bg-popover shadow-lg"
                >
                    {loading ? (
                        <div className="px-3 py-4 text-center text-sm text-muted-foreground">
                            Searching…
                        </div>
                    ) : (
                        results.map((loc) => (
                            <button
                                key={loc.id}
                                type="button"
                                role="option"
                                aria-selected={false}
                                onClick={() => selectLocation(loc)}
                                className="w-full px-3 py-2 text-left text-sm hover:bg-accent focus:bg-accent focus:outline-none"
                            >
                                <div className="font-medium">{loc.name}</div>
                                <div className="text-xs text-muted-foreground">
                                    {loc.region_name}{' '}
                                    {loc.type && `• ${loc.type}`}
                                </div>
                            </button>
                        ))
                    )}

                    {!loading && results.length === 0 && (
                        <div className="px-3 py-4 text-center text-sm text-muted-foreground">
                            No locations found.
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
