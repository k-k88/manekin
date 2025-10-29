@extends('layouts.app')

@section('content')
<div class="container text-center mt-5">
    <h4>⚠️ {{ $message }}</h4>
    <p>LINEから正しく登録してからアクセスしてください。</p>
</div>
@endsection
