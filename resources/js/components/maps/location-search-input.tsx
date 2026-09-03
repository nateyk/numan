import { useEffect, useRef, useState } from 'react';
import { Search, X, MapPin } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';

interface Location {
    id: number;
    name: string;
    region_name: string;
    slug: string;
    type: string;
    latitude: string | null;
    longitude: string | null;
    population?: number | null;
    elevation?: number | null;
    wereda?: string | null;
    zone?: string | null;
    is_major_town?: boolean;
}

interface LocationSearchInputProps {
    value?: number;
    initialName?: string;
    onChange?: (id: number) => void;
    onSelectLocation?: (loc: Location | null) => void;
    placeholder?: string;
    className?: string;
    debounceMs?: number;
}

export default function LocationSearchInput({
    value,
    initialName,
    onChange,
    onSelectLocation,
    placeholder = 'Search locations…',
    className,
    debounceMs = 300,
}: LocationSearchInputProps) {
    const [query, setQuery] = useState(initialName ?? '');
    const [results, setResults] = useState<Location[]>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
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
        if (q.length < 2) {
            setResults([]);
            setOpen(false);
            return;
        }
        timerRef.current = setTimeout(async () => {
            setLoading(true);
            try {
                const res = await fetch(`/locations/search?q=${encodeURIComponent(q)}`);
                if (res.ok) {
                    const data = await res.json();
                    setResults(data.locations ?? []);
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
                <MapPin className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={query}
                    onChange={(e) => search(e.target.value)}
                    onFocus={() => query.length >= 2 && setOpen(true)}
                    placeholder={placeholder}
                    className="pl-9 pr-9"
                />
                {query && (
                    <button
                        type="button"
                        onClick={clear}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    >
                        <X className="size-4" />
                    </button>
                )}
            </div>
            {open && (results.length > 0 || loading) && (
                <div className="absolute z-50 mt-1 w-full rounded-md border bg-popover shadow-lg">
                    {loading ? (
                        <div className="px-3 py-4 text-center text-sm text-muted-foreground">
                            Searching…
                        </div>
                    ) : (
                        results.map((loc) => (
                            <button
                                key={loc.id}
                                type="button"
                                onClick={() => selectLocation(loc)}
                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
                            >
                                <MapPin className="size-3.5 shrink-0 text-muted-foreground" />
                                <div className="flex-1">
                                    <div>
                                        <span className="font-medium">{loc.name}</span>
                                        <span className="mx-1 text-muted-foreground">·</span>
                                        <span className="text-muted-foreground">{loc.region_name}</span>
                                        <span className="mx-1 text-muted-foreground">·</span>
                                        <span className="text-xs text-muted-foreground capitalize">{loc.type}</span>
                                        {loc.is_major_town && <span className="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800">Major Town</span>}
                                    </div>
                                    {(loc.population || loc.elevation || loc.wereda) && (
                                        <div className="mt-0.5 flex gap-2 text-xs text-muted-foreground">
                                            {loc.population && <span>{loc.population.toLocaleString()} inh.</span>}
                                            {loc.elevation && <span>{loc.elevation} m</span>}
                                            {loc.wereda && <span>{loc.wereda}</span>}
                                        </div>
                                    )}
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
