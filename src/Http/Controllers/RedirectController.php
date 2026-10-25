<?php

declare(strict_types=1);

namespace Atannex\Share\Http\Controllers;

use Atannex\Share\ShareManager;
use Atannex\Share\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GET /share/{platform}?to=…&u=…&ref=…&signature=…: count the share, then
 * send the reader on to the platform. Only signed links work, so the route
 * can't be used to redirect anywhere else.
 */
final class RedirectController
{
    public function __invoke(Request $request, ShareManager $share, string $platform): RedirectResponse
    {
        abort_unless($request->hasValidSignature(false) && $share->platforms()->has($platform), 404);

        $to = (string) $request->query('to');
        abort_unless(preg_match('~^https?://~i', $to) === 1, 404);

        $share->record($platform, $request->query('u'), Subject::parse($request->query('ref')), $request);

        return (new RedirectResponse($to))
            ->setPrivate()
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
