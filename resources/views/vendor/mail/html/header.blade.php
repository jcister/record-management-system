@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<div style="text-align: center;">
<img src="{{ asset('logo.svg') }}" class="logo" alt="{{ config('app.name') }}" style="width: 80px; height: 80px; display: block; margin: 0 auto;">
<div style="margin-top: 10px; font-size: 18px; font-weight: 600; color: #0038A7;">{!! $slot !!}</div>
</div>
</a>
</td>
</tr>
