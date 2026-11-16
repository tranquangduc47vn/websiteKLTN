@php
    $productId = (int) ($productId ?? 0);
    $buttonClass = (string) ($buttonClass ?? 'jm-catalog-add-cart');
    $label = (string) ($label ?? 'Thêm vào giỏ');
@endphp
<form method="post" action="{{ url('gio-hang/them/' . $productId) }}" class="jm-add-cart-form">
    @csrf
    <button type="submit" class="{{ $buttonClass }}">{{ $label }}</button>
</form>
