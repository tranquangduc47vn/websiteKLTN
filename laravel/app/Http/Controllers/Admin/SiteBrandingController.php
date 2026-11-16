<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SiteLogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteBrandingController extends Controller
{
    public function __construct(private readonly SiteLogoService $logos) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->isMethod('post')) {
            if (! $request->hasFile('logo')) {
                return back()->with('message_fail', 'Vui lòng chọn file logo.');
            }
            $result = $this->logos->save($request->file('logo'));

            return redirect('/admin/branding')->with($result['ok'] ? 'message_success' : 'message_fail', $result['message']);
        }

        $path = $this->logos->relativePath();

        return view('admin.branding.index', [
            'logo_exists' => $this->logos->exists(),
            'logo_url' => url('upload/' . $path),
            'logo_path' => $path,
            'admin_header_title' => 'Logo website',
        ]);
    }

    public function handlePath(Request $request, string $path = ''): never
    {
        abort(404);
    }
}
