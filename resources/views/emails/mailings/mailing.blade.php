<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f7;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1f2937;">

<div style="display:none !important;visibility:hidden;opacity:0;max-height:0;overflow:hidden;color:transparent;line-height:1px;">
    {{ $subject }}
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f7;">
    <tr>
        <td align="center" valign="top" style="padding:24px 12px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                   style="width:600px;max-width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">

                @if(! empty($slug))
                    <tr>
                        <td align="right" style="font-size:11px;color:#6b7280;padding:8px 16px;">
                            <a href="{{ route('mailing', ['slug' => $slug]) }}" style="color:#4f46e5;text-decoration:none;">
                                {{ __('View this email in your browser') }}
                            </a>
                        </td>
                    </tr>
                @endif

                <tr>
                    <td style="padding:24px 24px 8px 24px;">
                        <strong style="font-size:18px;color:#111827;">{{ config('app.name') }}</strong>
                    </td>
                </tr>

                @if($content)
                    <tr>
                        <td style="padding:8px 24px 16px 24px;font-size:14px;line-height:1.6;color:#1f2937;">
                            {!! \Illuminate\Support\Str::markdown($content) !!}
                        </td>
                    </tr>
                @endif

                @if($cta_url && $cta_content)
                    <tr>
                        <td align="center" style="padding:8px 24px 24px 24px;">
                            <a href="{{ $cta_url }}"
                               style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 24px;border-radius:8px;">
                                {{ $cta_content }}
                            </a>
                        </td>
                    </tr>
                @endif

                @if($content_extra)
                    <tr>
                        <td style="padding:0 24px 24px 24px;font-size:14px;line-height:1.6;color:#1f2937;">
                            {!! \Illuminate\Support\Str::markdown($content_extra) !!}
                        </td>
                    </tr>
                @endif

                <tr>
                    <td style="padding:20px 24px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
                        &copy; {{ date('Y') }} {{ config('app.name') }}.
                        @if(! empty($user_uuid))
                            &nbsp;
                            <a href="{{ route('unsubscribe.show', ['uuid' => $user_uuid]) }}" style="color:#4f46e5;text-decoration:none;">
                                {{ __('Unsubscribe') }}
                            </a>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@if(! empty($extra_html))
    {!! $extra_html !!}
@endif
</body>
</html>
