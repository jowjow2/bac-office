<?php

namespace App\Support;

/**
 * The procuring entity and its designated contact, as every Invitation to
 * Bid must state them (RA 12009 IRR Sec. 50.2(m)). Values come from
 * config/bac-office.php (BAC_CONTACT_* in .env).
 */
final class ProcuringEntity
{
    /** Details the notice must carry; fax is included only when the LGU has one. */
    private const REQUIRED = ['address' => 'Address', 'phone' => 'Telephone', 'email' => 'E-mail', 'person' => 'Contact person'];

    public static function name(): string
    {
        return (string) config('bac-office.procuring_entity');
    }

    /** @return array{office:?string,address:?string,email:?string,phone:?string,fax:?string,website:string,person:?string,person_position:?string} */
    public static function contact(): array
    {
        $contact = (array) config('bac-office.contact', []);
        $contact['website'] = filled($contact['website'] ?? null) ? $contact['website'] : rtrim((string) config('app.url'), '/');

        return array_map(fn ($value) => filled($value) ? trim((string) $value) : null, $contact);
    }

    /** @return array<string, string> key => label of required contact details that are not set */
    public static function missingContact(): array
    {
        $contact = self::contact();

        return array_filter(self::REQUIRED, fn (string $label, string $key) => blank($contact[$key] ?? null), ARRAY_FILTER_USE_BOTH);
    }
}
