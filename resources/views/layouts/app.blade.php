<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>勤怠管理システム</title>
    <!-- Bootstrap CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
        <div class="container">

            {{-- ロゴ --}}
            @if(isset($company))
                <a class="navbar-brand" href="{{ route('company.dashboard', ['company' => $company->id]) }}">
                    勤怠管理システム（{{ $company->name }}）
                </a>
            @else
                @can('super-admin')
                    <a class="navbar-brand" href="{{ route('admin.dashboard') }}">システム管理者ダッシュボード</a>
                @else
                    <span class="navbar-brand">勤怠管理システム</span>
                @endcan
            @endif

            <div class="d-flex">
                @auth
                    @can('super-admin')
                        <a class="btn btn-outline-primary me-2" href="{{ route('admin.dashboard') }}">管理者</a>
                    @endcan

                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button class="btn btn-outline-danger">ログアウト</button>
                    </form>
                @endauth
            </div>

        </div>
    </nav>

    <main class="py-4 container">
        @yield('content')
    </main>

    <footer class="text-center py-3 bg-light mt-4">
        © 2025 勤怠管理システム
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    {{-- ⭐⭐ これが無かったからグラフが真っ白になってた --}}
    @yield('scripts')

</body>
</html>
