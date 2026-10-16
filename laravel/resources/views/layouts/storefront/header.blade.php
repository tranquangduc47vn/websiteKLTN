@php
    $wf_cart_total = (int) ($total_items ?? 0);
    $wf_carts = is_array($carts ?? null) ? $carts : [];
    $wf_order_badge = (int) ($user_order_badge ?? 0);

    $wf_nav_men = url("moi");
    $wf_nav_women = url("moi");
    $wf_nav_family = url("moi");
    $wf_nav_men_id = 0;
    $wf_nav_women_id = 8;
    $wf_nav_family_id = 9;
    if (!empty($catalog) && is_iterable($catalog)) {
        foreach ($catalog as $parent) {
            if (isset($parent->name) && $parent->name === "Thời trang nam") {
                $wf_nav_men = \App\Support\Storefront\Urls::category($parent);
                $wf_nav_men_id = (int) $parent->id;
            }
            if ((int) $parent->id === 8) {
                $wf_nav_women = \App\Support\Storefront\Urls::category($parent);
                $wf_nav_women_id = (int) $parent->id;
            }
            if ((int) $parent->id === 9) {
                $wf_nav_family = \App\Support\Storefront\Urls::category($parent);
                $wf_nav_family_id = (int) $parent->id;
            }
        }
    }

    $wf_nav_active = "home";
    $wf_uri = trim((string) request()->path(), "/");
    if ($wf_uri === "" || $wf_uri === "home") {
        $wf_nav_active = "home";
    } else {
        $wf_cat_id = (int) request()->segment(2);
        if ($wf_cat_id === $wf_nav_women_id) {
            $wf_nav_active = "women";
        } elseif ($wf_cat_id === $wf_nav_men_id && $wf_nav_men_id > 0) {
            $wf_nav_active = "men";
        } elseif ($wf_cat_id === $wf_nav_family_id) {
            $wf_nav_active = "family";
        } else {
            $wf_path = parse_url($wf_nav_women, PHP_URL_PATH);
            if ($wf_path && strpos("/" . $wf_uri, rtrim($wf_path, "/")) === 0) {
                $wf_nav_active = "women";
            }
        }
    }
@endphp
<div class="wf-chrome-2026">
	<div class="wf-chrome-shell">
		<header class="wf-chrome-header" id="wfChromeHeader" role="banner">
			<div class="wf-chrome-header__inner">
				<a href="{{ url("/") }}" class="wf-chrome-logo" title="Trang chủ">
					<img src="{{ \App\Support\Storefront\Shop::logoUrl() }}" alt="{{ \App\Support\Storefront\Shop::name() }}">
				</a>

				<button type="button" class="wf-chrome-nav-toggle" id="wfChromeNavToggle" aria-expanded="false" aria-controls="wfChromeNavDrawer" aria-label="Mở menu">
					<i class="fa-solid fa-bars" aria-hidden="true"></i>
				</button>

				<nav class="wf-chrome-nav" id="wfChromeNavDrawer" aria-label="Menu chính">
					<ul class="wf-chrome-menu">
						<li class="wf-chrome-menu__item{{ $wf_nav_active === "home" ? " is-active" : "" }}"><a href="{{ url("/") }}">HOME</a></li>
						<li class="wf-chrome-menu__item{{ $wf_nav_active === "men" ? " is-active" : "" }}"><a href="{{ $wf_nav_men }}">THỜI TRANG NAM</a></li>
						<li class="wf-chrome-menu__item{{ $wf_nav_active === "women" ? " is-active" : "" }}"><a href="{{ $wf_nav_women }}">THỜI TRANG NỮ</a></li>
						<li class="wf-chrome-menu__item{{ $wf_nav_active === "family" ? " is-active" : "" }}"><a href="{{ $wf_nav_family }}">THỜI TRANG GIA ĐÌNH</a></li>
					</ul>
				</nav>

				<div class="jm-header-icons wf-chrome-actions">
					<div class="wf-chrome-greeting-wrap">
						@if (!empty($user->name))
							@php $wf_greeting_name = \App\Support\Storefront\Product::displayName($user->name); @endphp
							<p class="wf-chrome-greeting">
								<span class="wf-chrome-greeting__label">Xin chào</span>
								<span class="wf-chrome-greeting__name">{{ $wf_greeting_name }}</span>
							</p>
						@else
							<p class="wf-chrome-greeting wf-chrome-greeting--guest">
								<span class="wf-chrome-greeting__label">Xin chào</span>
								<a class="wf-chrome-greeting__name wf-chrome-greeting__link" href="{{ $login_url }}">Đăng nhập</a>
							</p>
						@endif
					</div>

					<div class="jm-icon-slot jm-user-slot">
						@if (empty($user))
							<button type="button" class="jm-icon-btn jm-icon-toggle wf-chrome-icon-btn" title="Tài khoản" aria-label="Tài khoản" aria-expanded="false" aria-haspopup="true">
								<i class="fa-regular fa-user" aria-hidden="true"></i>
							</button>
							<ul class="jm-icon-popover jm-user-popover wf-chrome-popover wf-chrome-popover--menu">
								<li><a href="{{ $login_url }}">Đăng nhập</a></li>
								<li><a href="{{ $register_url }}">Đăng ký</a></li>
							</ul>
						@else
							<button type="button" class="jm-icon-btn jm-icon-toggle wf-chrome-icon-btn" title="Tài khoản" aria-label="Tài khoản{{ $wf_order_badge > 0 ? " — " . $wf_order_badge . " đơn đang xử lý" : "" }}" aria-expanded="false" aria-haspopup="true">
								<i class="fa-solid fa-user" aria-hidden="true"></i>
								@if ($wf_order_badge > 0)
									<span class="jm-icon-dot wf-chrome-order-badge">{{ $wf_order_badge > 99 ? "99+" : $wf_order_badge }}</span>
								@endif
							</button>
							<ul class="jm-icon-popover jm-user-popover wf-chrome-popover wf-chrome-popover--menu">
								<li><a href="{{ url("user") }}">Tài khoản</a></li>
								<li><a href="{{ url("user/logout") }}">Đăng xuất</a></li>
							</ul>
						@endif
					</div>

					<div class="jm-icon-slot jm-cart-slot">
						<button type="button" class="jm-icon-btn jm-icon-toggle wf-chrome-icon-btn" title="Giỏ hàng" aria-label="Giỏ hàng" aria-expanded="false" aria-haspopup="true">
							<i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
							@if ($wf_cart_total > 0)
								<span class="jm-icon-dot wf-chrome-cart-badge">{{ $wf_cart_total > 99 ? "99+" : $wf_cart_total }}</span>
							@endif
						</button>
						<div class="jm-icon-popover jm-cart-popover wf-chrome-popover">
							@if ($wf_cart_total > 0)
								<ul class="jm-cart-mini-list wf-chrome-cart-list">
									@foreach ($wf_carts as $items)
										<li>
											<img src="{{ \App\Support\Storefront\Product::imageUrl($items["options"]["image_link"] ?? "") }}" alt="">
											<div>
												<span class="jm-cart-mini-name">{{ \App\Support\Storefront\Product::displayName($items["name"] ?? "") }}</span>
												<span class="jm-cart-mini-meta">{{ (int) ($items["qty"] ?? 0) }} × {{ number_format($items["subtotal"] ?? 0) }}đ</span>
											</div>
										</li>
									@endforeach
								</ul>
								<a href="{{ $cart_url }}" class="jm-cart-mini-link wf-chrome-cart-link">Xem giỏ hàng</a>
							@else
								<p class="jm-cart-mini-empty">Giỏ hàng trống</p>
								<a href="{{ url("moi") }}" class="jm-cart-mini-link wf-chrome-cart-link">Mua sắm</a>
							@endif
						</div>
					</div>
				</div>
			</div>
		</header>

		<div class="wf-chrome-trust" aria-label="Cam kết dịch vụ">
			<div class="wf-chrome-trust__inner">
				<nav class="wf-chrome-trust__nav" aria-label="Liên kết dịch vụ">
					<a href="{{ url("hethongcuahang") }}" class="wf-chrome-trust__link">
						<i class="fa-solid fa-location-dot" aria-hidden="true"></i> Hệ thống cửa hàng
					</a>
					<a href="{{ url("VanChuyen") }}" class="wf-chrome-trust__link">
						<i class="fa-solid fa-truck-fast" aria-hidden="true"></i> Thông tin vận chuyển
					</a>
					<a href="{{ url("tich-diem") }}" class="wf-chrome-trust__link">
						<i class="fa-solid fa-star" aria-hidden="true"></i> Chính sách tích điểm
					</a>
				</nav>
			</div>
		</div>
	</div>
	<div class="wf-chrome-header-spacer" id="wfChromeHeaderSpacer" aria-hidden="true"></div>
</div>
