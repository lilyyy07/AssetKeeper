@props(['size' => 'small', 'variant' => 'dark', 'wordmark' => true])
@php($dimension = ['small' => 34, 'medium' => 46, 'large' => 64][$size] ?? 34)
<span {{ $attributes->class(['ak-brand', 'ak-brand-light' => $variant === 'light']) }}><img src="{{ asset('brand-mark.svg') }}" width="{{ $dimension }}" height="{{ $dimension }}" alt="{{ $wordmark ? '' : 'AssetKeeper' }}">@if($wordmark)<span>AssetKeeper</span>@endif</span>
