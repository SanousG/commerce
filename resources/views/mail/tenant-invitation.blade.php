<!doctype html>
<html lang="en">
<body>
    <h1>Join {{ $invitation->tenant->name }}</h1>
    <p>You were invited as {{ $invitation->role->name }}.</p>
    <p><a href="{{ $acceptUrl }}">Review and accept invitation</a></p>
    <p>This invitation expires {{ $invitation->expires_at->toDayDateTimeString() }}.</p>
</body>
</html>
