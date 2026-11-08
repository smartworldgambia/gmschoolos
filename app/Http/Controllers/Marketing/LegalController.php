<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function privacy(): Response
    {
        return $this->page(
            title: 'Privacy Policy',
            description: 'How Gambia SchoolOS collects, uses and protects personal data for schools in The Gambia.',
            componentKey: 'privacy',
        );
    }

    public function terms(): Response
    {
        return $this->page(
            title: 'Terms of Service',
            description: 'Terms governing use of the Gambia SchoolOS school management and learning platform.',
            componentKey: 'terms',
        );
    }

    private function page(string $title, string $description, string $componentKey): Response
    {
        $marketing = config('schoolos.marketing', []);

        return Inertia::render('Marketing/LegalPage', [
            'document' => $componentKey,
            'title' => $title,
            'description' => $description,
            'updatedAt' => '1 November 2026',
            'marketing' => [
                'tagline' => $marketing['tagline'] ?? null,
                'contact_email' => $marketing['contact_email'] ?? null,
                'support_email' => $marketing['support_email'] ?? null,
                'phone' => $marketing['phone'] ?? null,
                'phone_secondary' => $marketing['phone_secondary'] ?? null,
                'address' => $marketing['address'] ?? null,
                'website' => $marketing['website'] ?? null,
                'social' => $marketing['social'] ?? [],
            ],
            'seo' => [
                'title' => $title.' — Gambia SchoolOS',
                'description' => $description,
            ],
        ]);
    }
}
