import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Plus, X } from 'lucide-react';

const COUNTRIES = [
    { code: '+251', label: 'ET +251' },
    { code: '+253', label: 'DJ +253' },
    { code: '+254', label: 'KE +254' },
    { code: '+252', label: 'SO +252' },
    { code: '+291', label: 'ER +291' },
    { code: '+249', label: 'SD +249' },
    { code: '', label: 'Other' },
];

function NumberInput({
    id,
    label,
    value,
    required,
    invalid,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    required: boolean;
    invalid: boolean;
    onChange: (value: string) => void;
}) {
    const country = value.startsWith('+')
        ? (COUNTRIES.find(({ code }) => code && value.startsWith(code))?.code ??
          '')
        : '+251';
    const national =
        country && value.startsWith(country)
            ? value.slice(country.length)
            : value;
    return (
        <div className="flex min-w-0 gap-2">
            <Select
                value={country || 'other'}
                onValueChange={(code) =>
                    onChange(
                        code === 'other'
                            ? '+'
                            : `${code}${national.replace(/^0/, '')}`,
                    )
                }
            >
                <SelectTrigger
                    className="w-28 shrink-0"
                    aria-label={`Country code for ${label}`}
                >
                    <SelectValue>
                        {COUNTRIES.find((item) => item.code === country)
                            ?.label ?? 'Other'}
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    {COUNTRIES.map(({ code, label }) => (
                        <SelectItem
                            key={code || 'other'}
                            value={code || 'other'}
                        >
                            {label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Input
                id={id}
                type="tel"
                inputMode="tel"
                autoComplete="tel-national"
                value={national}
                placeholder={country ? '91 123 4567' : '+44 7700 900123'}
                required={required}
                aria-invalid={invalid}
                onChange={(event) => {
                    const raw = event.target.value;
                    onChange(
                        raw.startsWith('+') || raw.startsWith('00')
                            ? raw
                            : raw
                              ? `${country}${raw.replace(/^0/, '')}`
                              : '',
                    );
                }}
            />
        </div>
    );
}

export default function ContactNumberFields({
    field,
    label,
    numbers,
    required = false,
    errors,
    onChange,
}: {
    field: 'phone_numbers' | 'whatsapp_numbers';
    label: string;
    numbers: string[];
    required?: boolean;
    errors: Partial<Record<string, string>>;
    onChange: (values: string[]) => void;
}) {
    return (
        <fieldset className="space-y-3">
            <legend className="mb-2 text-sm font-medium">
                {label}
                {required ? ' *' : ' (optional)'}
            </legend>
            {numbers.map((number, index) => {
                const id = `${field}-${index}`;
                return (
                    <div key={id} className="space-y-1">
                        <Label htmlFor={id} className="sr-only">
                            {label} {index + 1}
                        </Label>
                        <div className="flex items-center gap-2">
                            <div className="min-w-0 flex-1">
                                <NumberInput
                                    id={id}
                                    label={`${label} ${index + 1}`}
                                    value={number}
                                    required={required && index === 0}
                                    invalid={Boolean(
                                        errors[`${field}.${index}`],
                                    )}
                                    onChange={(value) =>
                                        onChange(
                                            numbers.map((item, i) =>
                                                i === index ? value : item,
                                            ),
                                        )
                                    }
                                />
                            </div>
                            {(!required || numbers.length > 1) && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove ${label.toLowerCase()} ${index + 1}`}
                                    onClick={() => {
                                        onChange(
                                            numbers.filter(
                                                (_, i) => i !== index,
                                            ),
                                        );
                                    }}
                                >
                                    <X className="size-4" />
                                </Button>
                            )}
                        </div>
                        <InputError message={errors[`${field}.${index}`]} />
                    </div>
                );
            })}
            <InputError message={errors[field]} />
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onChange([...numbers, ''])}
            >
                <Plus className="size-4" />
                Add {field === 'phone_numbers' ? 'phone' : 'WhatsApp'} number
            </Button>
        </fieldset>
    );
}
