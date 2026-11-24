<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('branches.migrate.inactive_title') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="text-center d-flex align-items-center justify-content-center vh-100 bg-light">
    <div class="container">
        <h1 class="text-danger">⚠️ {{ __('branches.migrate.inactive_title') }}</h1>
        <p class="lead">{{ __('branches.migrate.inactive_text') }}</p>
       
         <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="btn btn-primary">{{ __('branches.migrate.logout') }}</button>
    </form>
    </div>
</body>
</html>
