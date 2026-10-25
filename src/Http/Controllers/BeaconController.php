<?php

declare(strict_types=1);

namespace Atannex\Share\Http\Controllers;

use Atannex\Share\ShareManager;
use Atannex\Share\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /share/beacon?u=…&ref=…&signature=… with platform=copy|native|email…:
 * a share the server can't see (done in the browser or by an app scheme),
 * reported by the page script.
 */
final class BeaconController
{
    public function __invoke(Request $request, ShareManager $share): Response
    {
        abort_unless($request->hasValidSignature(false), 403);

        $platform = (string) $request->input('platform');
        abort_unless($share->platforms()->has($platform) && ! $share->platform($platform)->redirects(), 422);

        $share->record($platform, $request->query('u'), Subject::parse($request->query('ref')), $request);

        return response()->noContent();
    }
}
