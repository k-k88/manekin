document.addEventListener('DOMContentLoaded', function() {
    const shiftModalEl = document.getElementById('shiftModal');
    const shiftModal = new bootstrap.Modal(shiftModalEl);

    // 日付クリックでモーダル表示
    document.querySelectorAll('.shift-day').forEach(td => {
        td.addEventListener('click', function() {
            document.getElementById('shift_date').value = this.dataset.date;
            document.getElementById('start_time').value = '';
            document.getElementById('end_time').value = '';
            shiftModal.show();
        });
    });

    // 保存ボタンクリックでAjax
    document.getElementById('shiftForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const url = this.action;

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.success){
                alert('保存しました！');
                shiftModal.hide();
            } else {
                alert('保存に失敗しました');
            }
        })
        .catch(err => {
            console.error(err);
            alert('通信エラー');
        });
    });
});
