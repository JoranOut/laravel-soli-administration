import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

type SearchInputProps = {
    value?: string;
    placeholder?: string;
    routeName: string;
    queryParams?: Record<string, string | undefined>;
};

export function SearchInput({ value = '', placeholder = 'Zoeken...', routeName, queryParams = {} }: SearchInputProps) {
    const [search, setSearch] = useState(value);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => {
        setSearch(value);
    }, [value]);

    const performSearch = (searchValue: string) => {
        const params: Record<string, string> = {};
        Object.entries(queryParams).forEach(([key, val]) => {
            if (val) params[key] = val;
        });
        if (searchValue) params.search = searchValue;

        router.get(routeName, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const val = e.target.value;
        setSearch(val);

        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => performSearch(val), 300);
    };

    const handleClear = () => {
        setSearch('');
        performSearch('');
    };

    return (
        <div className="relative">
            <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
            <Input
                type="text"
                value={search}
                onChange={handleChange}
                placeholder={placeholder}
                className="pl-9 pr-9"
            />
            {search && (
                <Button
                    variant="ghost"
                    size="sm"
                    className="absolute top-1/2 right-1 h-7 w-7 -translate-y-1/2 p-0"
                    onClick={handleClear}
                >
                    <X className="h-4 w-4" />
                </Button>
            )}
        </div>
    );
}
