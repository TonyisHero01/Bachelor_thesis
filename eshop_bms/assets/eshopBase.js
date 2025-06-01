function backToHomePage() {
    window.location.href = '/homepage';
}

function handleSearch(event) {
    event.preventDefault(); // 阻止默认提交行为
    const query = document.getElementById('searchInput').value.trim();
    if (!query) return;

    // 显示加载动画
    document.getElementById('loadingSpinner').style.display = 'block';

    // 模拟加载或跳转
    setTimeout(() => {
        window.location.href = '/search/results?query=' + encodeURIComponent(query);
    }, 2000);
}