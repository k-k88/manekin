<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>企業ログイン</title>
    <style>
        body {
            font-family: sans-serif;
            background: #f4f4f4;
            text-align: center;
            margin-top: 100px;
        }
        form {
            display: inline-block;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        input {
            display: block;
            margin: 10px auto;
            padding: 8px;
            width: 250px;
        }
        button {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

    <h1>企業ログイン</h1>

    <!-- エラーメッセージ表示 -->
    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <!-- 安全なフォーム（相対パス） -->
    <form method="POST" action="/login">
        @csrf
        <input type="email" name="email" placeholder="メールアドレス" required autocomplete="username">
        <input type="password" name="password" placeholder="パスワード" required autocomplete="current-password">
        <button type="submit">ログイン</button>
    </form>

</body>
</html>
