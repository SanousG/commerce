<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;background:#f1eee6;font-family:Arial,Helvetica,sans-serif;color:#17211b;">
    <div style="max-width:560px;margin:0 auto;padding:32px 24px;">
        <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.2em;text-transform:uppercase;color:#065f46;">Panel administrativo</p>
        <h1 style="margin:0 0 16px;font-size:22px;">{{ $title }}</h1>
        <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#516057;">{{ $notificationMessage }}</p>
        @if ($actionUrl)
            <p style="margin:0 0 24px;">
                <a href="{{ $actionUrl }}" style="display:inline-block;background:#17211b;color:#ffffff;padding:12px 20px;text-decoration:none;font-size:14px;font-weight:bold;">Abrir en el panel</a>
            </p>
        @endif
        <p style="margin:0;font-size:12px;color:#637067;">Este mensaje es automático. El detalle se consulta dentro del panel con tus permisos.</p>
    </div>
</body>
</html>
