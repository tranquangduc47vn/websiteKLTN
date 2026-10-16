<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * TODO B8+: implement logic tu CI3 admin/Hoptac.
 */
class HoptacController extends Controller
{
    public function index(Request $request)
    {
        abort(501, 'Chua implement — xem TODO trong routes/web.php.');
    }

    public function handlePath(Request $request, string $path = '')
    {
        abort(501, 'Chua implement — xem TODO trong routes/web.php.');
    }

    public function __call(string $method, array $args)
    {
        abort(501, 'Action $method chua implement — xem TODO trong routes/web.php.');
    }
}
