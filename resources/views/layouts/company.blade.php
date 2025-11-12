<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '会社管理')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

    <!-- ナビバー -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="{{ route('company.dashboard', $company->id ?? 1) }}">
                🏢 {{ $company->name ?? '勤怠管理システム' }}
            </a>
            <div class="ms-auto">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">ログアウト</button>
                </form>
            </div>
        </div>
    </nav>

    <!-- メインコンテンツ -->
    <main class="container mt-4">
        @yield('content')
    </main>

    <!-- フッター -->
    <footer class="text-center mt-5 py-3 bg-light">
        © 2025 勤怠管理システム
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
