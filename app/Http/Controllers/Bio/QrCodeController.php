<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QrCodeController extends Controller
{
    /**
     * Render a QR code that points at a biolink or one restaurant's public menu.
     */
    public function __invoke(Request $request, ?Profile $profile = null): Response
    {
        if ($profile instanceof Profile) {
            abort_unless($profile->user_id === $request->user()->id, 403);

            $url = route('restaurant.show', $profile->username);
        } else {
            $profile = Profile::forUser($request->user());
            $url = route('bio.show', $profile->username);
        }

        $table = mb_substr(trim((string) $request->query('table', '')), 0, 20);

        if ($table !== '') {
            $url .= '?table='.urlencode($table);
        }

        $renderer = new ImageRenderer(
            new RendererStyle(320, 1),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($url);

        $response = response($svg, 200, ['Content-Type' => 'image/svg+xml']);

        if ($request->boolean('download')) {
            $safeTable = preg_replace('/[^A-Za-z0-9_-]+/', '-', $table) ?: '';
            $filename = $profile->username.($safeTable !== '' ? '-table-'.$safeTable : '').'-qr.svg';
            $response->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
        }

        return $response;
    }
}
