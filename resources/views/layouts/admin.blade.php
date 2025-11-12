<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'システム管理')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
<nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
    <div class="container">

        {{-- ✅ 管理画面トップ --}}
        <a class="navbar-brand" href="{{ route('admin.dashboard') }}">🛠 システム管理</a>

        <div class="ms-auto d-flex">
            {{-- ✅ 会社管理 --}}
            <a href="{{ route('admin.companies.index') }}" class="btn btn-outline-light btn-sm me-2">
                会社一覧
            </a>

            {{-- ✅ ユーザー管理 --}}
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-light btn-sm me-3">
                ユーザー一覧
            </a>

            {{-- ✅ ログアウト --}}
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="btn btn-danger btn-sm">ログアウト</button>
            </form>
        </div>

    </div>
</nav>

<main class="container py-4">
    @yield('content')
</main>

<footer class="text-center text-muted py-3">
    © 2025 勤怠管理システム
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
