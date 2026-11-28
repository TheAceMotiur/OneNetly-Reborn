<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\ProfileAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VCardController extends Controller
{
    public function __construct(private readonly ProfileAccess $access) {}

    /**
     * Download the public contact card for a bio page.
     */
    public function __invoke(Request $request, string $username): Response
    {
        $profile = Profile::query()
            ->where('username', $username)
            ->where('is_published', true)
            ->with('user:id,name')
            ->firstOrFail();

        if (! $this->access->isUnlocked($request, $profile)) {
            return redirect()->to(route('bio.show', $profile->username, false));
        }

        $displayName = $profile->display_name ?: $profile->user->name;

        $lines = array_filter([
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:;'.$this->escape($displayName).';;;',
            'FN:'.$this->escape($displayName),
            $this->line('ORG', $profile->company),
            $this->line('TITLE', $profile->job_title),
            $this->line('EMAIL;TYPE=INTERNET', $profile->contact_email),
            $this->line('TEL;TYPE=CELL', $profile->phone),
            $this->line('URL', $profile->contact_website),
            filled($profile->address)
                ? 'ADR;TYPE=WORK:;;'.$this->escape((string) $profile->address).';;;;'
                : null,
            $this->line('NOTE', $profile->bio),
            'END:VCARD',
        ]);

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/vcard; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$profile->username.'.vcf"',
        ]);
    }

    /**
     * Build an optional vCard line.
     */
    private function line(string $name, ?string $value): ?string
    {
        return filled($value) ? $name.':'.$this->escape($value) : null;
    }

    /**
     * Escape a value for the vCard text format.
     */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\r", "\n", ';', ','],
            ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'],
            $value,
        );
    }
}
