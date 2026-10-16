<div class="container">

    <div class="slider-wrapper">
        <div id="carousel-example-generic" class="carousel slide" data-ride="carousel" data-interval="3000" data-pause="false">

            <!-- THANH TÌM KIẾM -->
            <div class="slider-search-box">
                <form action="{{ url("tim-kiem") }}" method="get" class="slider-search-form">
                    <div class="slider-search-field">
                        <input type="text"
                               name="key-search"
                               class="slider-search-input"
                               placeholder="Tìm kiếm sản phẩm..."
                               autocomplete="off"
                               aria-label="Tìm kiếm sản phẩm">
                        <button class="slider-search-btn" type="submit">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <span>Tìm kiếm</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Wrapper for slides -->
            <div class="carousel-inner" role="listbox">

            @php $i = 0; @endphp
            @foreach ($slider ?? [] as $value)
                <div class="item {{ $i == 0 ? "active" : "" }}">
                    @php
                        $slider_href = trim((string) $value->link);
                        if ($slider_href !== "" && strpos($slider_href, "http") !== 0) {
                            $slider_href = url(ltrim($slider_href, "/"));
                        }
                    @endphp
                    <a href="{{ $slider_href }}">
                        <img src="{{ \App\Support\Storefront\Shop::uploadImageUrl($value->image_link, "slider") }}"
                             alt="{{ $value->name ?? "" }}">
                    </a>

                    <div class="carousel-caption">
                    </div>

                </div>
                @php $i++; @endphp
            @endforeach

        </div>

         </div>

    </div>
</div>
