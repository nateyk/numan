export interface ContactChannelsData {
    phone?: string | null;
    whatsapp?: string | null;
    email?: string | null;
    phone_numbers?: string[];
    whatsapp_numbers?: string[];
}

/** Render only contact data already authorized by the server. */
export default function ContactChannels({
    contact,
}: {
    contact: ContactChannelsData;
}) {
    const phones =
        contact.phone_numbers ?? (contact.phone ? [contact.phone] : []);
    const whatsapp =
        contact.whatsapp_numbers ??
        (contact.whatsapp ? [contact.whatsapp] : []);
    return (
        <div className="space-y-1 text-sm">
            {phones.map((number) => (
                <p key={`phone-${number}`} className="break-words">
                    <span className="text-muted-foreground">Phone </span>
                    <a
                        className="font-medium underline-offset-4 hover:underline"
                        href={`tel:${number}`}
                    >
                        {number}
                    </a>
                </p>
            ))}
            {whatsapp.map((number) => (
                <p key={`whatsapp-${number}`} className="break-words">
                    <span className="text-muted-foreground">WhatsApp </span>
                    <a
                        className="font-medium underline-offset-4 hover:underline"
                        href={`https://wa.me/${number.replace(/\D/g, '')}`}
                        target="_blank"
                        rel="noreferrer"
                    >
                        {number}
                    </a>
                </p>
            ))}
            {contact.email && (
                <p className="break-all">
                    <span className="text-muted-foreground">Email </span>
                    <a
                        className="font-medium underline-offset-4 hover:underline"
                        href={`mailto:${contact.email}`}
                    >
                        {contact.email}
                    </a>
                </p>
            )}
            {!phones.length && !whatsapp.length && !contact.email && (
                <p className="text-muted-foreground">
                    No shared contact details.
                </p>
            )}
        </div>
    );
}
