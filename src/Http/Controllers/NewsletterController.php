<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Http\Controllers;

use A2ZWeb\Newsletter\Models\Mailing;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * Public newsletter archive. Presentation lives in the publishable
 * `newsletter::web.*` views — publish and override them to apply your own
 * layout, SEO and navigation.
 */
class NewsletterController extends Controller
{
    public function index(): View
    {
        return view('newsletter::web.index', [
            'newsletters' => $this->approvedMailings(),
        ]);
    }

    public function form(): View
    {
        return view('newsletter::web.form');
    }

    public function item(string $slug): View
    {
        $newsletter = Mailing::where('slug', $slug)
            ->whereNotNull('approved_at')
            ->firstOrFail();

        return view('newsletter::web.item', [
            'newsletter' => $newsletter,
            'newsletters' => $this->approvedMailings(),
        ]);
    }

    /**
     * Browser preview of a single mailing (the "view in browser" link).
     */
    public function preview(string $slug): View
    {
        $newsletter = Mailing::where('slug', $slug)
            ->whereNotNull('approved_at')
            ->firstOrFail();

        return view('newsletter::web.item', [
            'newsletter' => $newsletter,
            'newsletters' => $this->approvedMailings(),
        ]);
    }

    protected function approvedMailings()
    {
        return Mailing::query()
            ->whereNotNull('approved_at')
            ->orderByDesc('created_at')
            ->get();
    }
}
