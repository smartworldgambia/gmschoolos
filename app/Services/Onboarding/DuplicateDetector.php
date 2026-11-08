<?php

namespace App\Services\Onboarding;

use App\Models\School;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

/**
 * Looks for an institution that is already on the platform before a new school is provisioned.
 *
 * Two outcomes, deliberately different:
 *  - BLOCK  : the registration is certainly the same institution (same official registration number, or the exact
 *             same name in the same region). Nothing is created and the applicant is told to sign in / contact support.
 *  - FLAG   : it merely looks similar (close name, same phone, same address, same school e-mail, an e-mail alias of an
 *             existing administrator). The school IS created - a genuine second campus must not be refused - but a
 *             platform review is queued and a reviewer can end the trial if it was a repeated claim.
 *
 * The matched school is never named to the applicant (it would leak other schools' details).
 */
class DuplicateDetector
{
    /** Words that carry no identity: "St John's Primary School" and "St Johns" share the same core. */
    protected const NOISE = [
        'the', 'of', 'and', 'school', 'schools', 'academy', 'college', 'institute', 'institution', 'nursery', 'primary',
        'secondary', 'basic', 'senior', 'junior', 'upper', 'lower', 'international', 'preparatory', 'prep', 'high',
        'lycee', 'ecole', 'centre', 'center',
    ];

    public function __construct(protected TenantContext $tenant) {}

    /**
     * @param  array{school_name?: ?string, region?: ?string, registration_number?: ?string, school_email?: ?string, school_phone?: ?string, address?: ?string, admin_email?: ?string}  $input
     * @return array{blocked: bool, block: list<string>, flags: list<string>, matched: list<int>}
     */
    public function check(array $input): array
    {
        return $this->tenant->runAsPlatform(fn () => $this->evaluate($input));
    }

    /** @return array{blocked: bool, block: list<string>, flags: list<string>, matched: list<int>} */
    protected function evaluate(array $input): array
    {
        $block = [];
        $flags = [];
        $matched = [];

        $name = (string) ($input['school_name'] ?? '');
        $region = $this->plain((string) ($input['region'] ?? ''));
        $regNo = $this->registrationKey((string) ($input['registration_number'] ?? ''));
        $email = Str::lower(trim((string) ($input['school_email'] ?? '')));
        $phone = $this->phoneKey((string) ($input['school_phone'] ?? ''));
        $address = $this->plain((string) ($input['address'] ?? ''));

        $fullName = $this->plain($name);
        $coreName = $this->coreName($name);

        $schools = School::query()->select(['id', 'name', 'region', 'registration_number', 'email', 'billing_email', 'phone', 'address'])->get();

        foreach ($schools as $existing) {
            $hit = false;

            if ($regNo !== '' && $regNo === $this->registrationKey((string) $existing->registration_number)) {
                $block[] = 'registration_number';
                $hit = true;
            }

            if ($fullName !== '' && $fullName === $this->plain((string) $existing->name)
                && $region !== '' && $region === $this->plain((string) $existing->region)) {
                $block[] = 'same_name_region';
                $hit = true;
            }

            if (! $hit) {
                if ($coreName !== '' && $this->similar($coreName, $this->coreName((string) $existing->name)) >= 85.0) {
                    $flags[] = 'similar_name';
                    $hit = true;
                }
                if ($phone !== '' && $phone === $this->phoneKey((string) $existing->phone)) {
                    $flags[] = 'same_phone';
                    $hit = true;
                }
                if (strlen($address) >= 10 && $address === $this->plain((string) $existing->address)) {
                    $flags[] = 'same_address';
                    $hit = true;
                }
                if ($email !== '' && in_array($email, [Str::lower((string) $existing->email), Str::lower((string) $existing->billing_email)], true)) {
                    $flags[] = 'same_school_email';
                    $hit = true;
                }
            }

            if ($hit) {
                $matched[] = (int) $existing->id;
            }
        }

        // The same person registering again with an e-mail alias (a+x@gmail.com, a.b@gmail.com) to claim a second trial.
        $admin = Str::lower(trim((string) ($input['admin_email'] ?? '')));
        if ($admin !== '' && str_contains($admin, '@')) {
            $canonical = $this->canonicalEmail($admin);
            $domain = Str::after($admin, '@');

            $aliases = User::query()->whereNotNull('school_id')->where('email', 'like', '%@'.$domain)->get(['id', 'school_id', 'email'])
                ->filter(fn (User $u) => Str::lower($u->email) !== $admin && $this->canonicalEmail((string) $u->email) === $canonical);

            if ($aliases->isNotEmpty()) {
                $flags[] = 'admin_email_alias';
                $matched = array_merge($matched, $aliases->pluck('school_id')->map(fn ($id) => (int) $id)->all());
            }
        }

        return [
            'blocked' => $block !== [],
            'block' => array_values(array_unique($block)),
            'flags' => array_values(array_unique($flags)),
            'matched' => array_values(array_unique($matched)),
        ];
    }

    // ------------------------------------------------------------------ normalisation

    /** Lower-case ASCII letters and digits separated by single spaces. */
    public function plain(string $value): string
    {
        $value = Str::lower(Str::ascii($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /** Identity words only ("St John's Primary School" => "st johns"). */
    public function coreName(string $name): string
    {
        $value = Str::lower(Str::ascii($name));
        $value = str_replace(["'", '’'], '', $value); // john's -> johns
        $words = array_filter(explode(' ', $this->plain($value)), fn (string $w) => $w !== '' && ! in_array($w, self::NOISE, true));

        return implode(' ', $words);
    }

    /** True when another school (not `$exceptSchoolId`) already uses this registration number. */
    public function registrationNumberTakenByOther(string $registrationNumber, int $exceptSchoolId): bool
    {
        $key = $this->registrationKey($registrationNumber);

        if ($key === '') {
            return false;
        }

        return $this->tenant->runAsPlatform(fn () => School::query()
            ->where('id', '!=', $exceptSchoolId)
            ->whereNotNull('registration_number')
            ->get(['id', 'registration_number'])
            ->contains(fn (School $s) => $this->registrationKey((string) $s->registration_number) === $key));
    }
    public function registrationKey(string $value): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '');
    }

    /** Last 7 digits: tolerant of +220 / 00220 / spaces, strict enough not to match unrelated numbers. */
    public function phoneKey(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 7 ? substr($digits, -7) : '';
    }

    public function canonicalEmail(string $email): string
    {
        $email = Str::lower(trim($email));
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $local = explode('+', $local, 2)[0];
        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local.'@'.$domain;
    }

    protected function similar(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        similar_text($a, $b, $percent);

        return $percent;
    }
}
