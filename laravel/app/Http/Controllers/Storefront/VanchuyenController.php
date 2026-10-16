<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * TODO B5+: implement logic tu CI3 $n.
 */
class VanchuyenController extends Controller
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
