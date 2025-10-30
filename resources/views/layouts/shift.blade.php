<!DOCTYPE html>
<html lang="ja">
<head>
  <style>
/* モーダル本体を最前面へ */
.modal {
  position: fixed !important;
  z-index: 2000 !important;
  display: block;
}

/* 背景の黒幕をモーダルの後ろに */
.modal-backdrop {
  position: fixed !important;
  z-index: 1500 !important;
}

/* ほかの要素が被らないように明示的に下げる */
body, .container, table, .shift-day {
  position: relative;
  z-index: 1 !important;
}
</style>


    <meta charset="UTF-8">
    <title>シフト登録</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    @yield('content')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
